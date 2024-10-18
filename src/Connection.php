<?php declare(strict_types=1);

namespace Ripple\App\MySQL;

use Co\IO;
use InvalidArgumentException;
use Revolt\EventLoop\Suspension;
use Ripple\App\MySQL\Constant\Capabilities;
use Ripple\App\MySQL\Constant\Protocol;
use Ripple\App\MySQL\Data\Statement;
use Ripple\App\MySQL\Encrypt\Encode as PasswordEncode;
use Ripple\App\MySQL\Exception\Exception;
use Ripple\App\MySQL\Packet\EofPacket;
use Ripple\App\MySQL\Packet\ErrPacket;
use Ripple\App\MySQL\Packet\OkPacket;
use Ripple\App\MySQL\StreamConsume\Decode;
use Ripple\App\MySQL\StreamConsume\Encode;
use Ripple\Coroutine\Coroutine;
use Ripple\Coroutine\WaitGroup;
use Ripple\Socket\SocketStream;
use Ripple\Stream\Exception\ConnectionException;
use Ripple\Utils\Output;
use Throwable;

use function array_shift;
use function Co\async;
use function Co\getSuspension;
use function implode;
use function in_array;
use function is_array;
use function max;
use function str_repeat;
use function strlen;
use function substr;

class Connection
{
    // 等待握手
    public const STEP_HANDSHAKE = 0;

    // 握手请求
    public const STEP_HANDSHAKE_REQUEST = 1;

    // 握手响应
    public const STEP_HANDSHAKE_RESPONSE = 2;

    // 等待交换请求
    public const STEP_AUTH_SWITCH_REQUEST = 3;

    // 认证交换响应
    public const STEP_AUTH_SWITCH_RESPONSE = 4;

    // 已建立连接
    public const STEP_ESTABLISHED = 5;

    /*** @var int */
    public int $clientCapabilities;

    /*** @var string */
    protected string $buffer = '';

    /*** @var int */
    protected int $sequenceId = 0;

    /*** @var int */
    protected int $step = Connection::STEP_HANDSHAKE;

    /*** @var int */
    protected int $protocol;

    /*** @var string */
    protected string $version;

    /*** @var int */
    protected int $threadId;

    /*** @var string */
    protected string $authPluginData;

    /*** @var int */
    protected int $serverCapabilities;

    /*** @var int */
    protected int $charset;

    /*** @var int */
    protected int $serverStatus;

    /*** @var int */
    protected int $serverCapabilitiesExtension;

    /*** @var string */
    protected string $authPluginName;

    /*** @var bool */
    protected bool $handshake = false;

    /*** @var \Ripple\Socket\SocketStream */
    protected SocketStream $stream;

    /*** @var \Ripple\Coroutine\WaitGroup */
    protected WaitGroup $waitGroup;

    /*** @var \Revolt\EventLoop\Suspension[] */
    protected array $queue = [];

    /*** @var \Ripple\App\MySQL\Data\Statement[] */
    protected array $statements = [];

    /*** @var \Revolt\EventLoop\Suspension */
    private Suspension $suspension;

    /*** @var \Ripple\App\MySQL\Config */
    private Config $config;

    /**
     * @param \Ripple\App\MySQL\Config|array|string $config
     * @param string|null                           $username
     * @param null                                  $password
     *
     * @throws \Ripple\App\MySQL\Exception\Exception
     */
    public function __construct(Config|array|string $config, string $username = null, $password = null)
    {
        if ($config instanceof Config) {
            $this->config = $config;
        } elseif (is_array($config)) {
            $this->config = Config::fromArray($config);
        } elseif (!$username || !$password) {
            throw new Exception("Username and password are required.");
        } else {
            $this->config = Config::formString($config, $username, $password);
        }

        $this->waitGroup = new WaitGroup();
        $this->connect();
    }

    /**
     * @return int
     */
    public function getCapabilities(): int
    {
        return $this->clientCapabilities;
    }

    /**
     * @return void
     * @throws \Ripple\App\MySQL\Exception\Exception
     */
    public function connect(): void
    {
        if ($this->step !== Connection::STEP_HANDSHAKE) {
            throw new Exception("Connection already established.");
        }

        if (!empty($this->queue)) {
            throw new Exception("Connection already established.");
        }

        try {
            $this->stream = IO::Socket()->connect("tcp://{$this->config->host}:{$this->config->port}");
        } catch (ConnectionException $e) {
            throw new Exception("Failed to connect to MySQL server: {$e->getMessage()}");
        }
        $this->stream->setBlocking(false);

        async(function () {
            $this->suspension = getSuspension();
            $this->stream->onClose(function () {
                $this->suspension->throw(new Exception("Connection closed."));
                while ($suspension = array_shift($this->queue)) {
                    $suspension->throw(new Exception("Connection closed."));
                }
            });

            while (1) {
                try {
                    $this->stream->waitForReadable();
                    $content = $this->stream->readContinuously(8192);
                    if ($content === '') {
                        break;
                    }
                    $this->transmitting($content);
                } catch (Throwable $exception) {
                    while ($suspension = array_shift($this->queue)) {
                        $suspension->throw(
                            new Exception(
                                $exception->getMessage(),
                                $exception->getCode(),
                                $exception
                            )
                        );
                    }
                    break;
                }
            }
            $this->stream->close();
        });

        $this->queue[] = $suspension = getSuspension();
        try {
            Coroutine::suspend($suspension);
        } catch (Throwable $e) {
            throw new Exception("Failed to connect to MySQL server: {$e->getMessage()}");
        }
    }

    /**
     * @param string $content
     *
     * @return void
     * @throws \Ripple\App\MySQL\Exception\Exception
     */
    protected function transmitting(string $content): void
    {
        $this->buffer .= $content;
        do {
            try {
                $packetLength     = Decode::FixedLengthInteger($this->buffer, 3);
                $this->sequenceId = Decode::FixedLengthInteger($this->buffer, 1);
            } catch (InvalidArgumentException) {
                break;
            }

            if (strlen($this->buffer) < $packetLength) {
                break;
            }

            $packet       = substr($this->buffer, 0, $packetLength);
            $this->buffer = substr($this->buffer, $packetLength);
            $this->handlePayload($packet);
        } while ($this->buffer);
    }

    /**
     * @param string $content
     *
     * @return void
     * @throws \Ripple\App\MySQL\Exception\Exception
     */
    protected function handlePayload(string $content): void
    {
        if ($this->step < Connection::STEP_ESTABLISHED) {
            if ($this->step === Connection::STEP_HANDSHAKE) {
                $this->handleHandshake($content);
            }

            if ($this->step === Connection::STEP_HANDSHAKE_REQUEST) {
                $this->sendHandshakeResponse();
                return;
            }


            if ($this->step == Connection::STEP_HANDSHAKE_RESPONSE) {
                $this->handleSwitchRequest($content);
            }

            if ($this->step == Connection::STEP_AUTH_SWITCH_REQUEST) {
                $this->sendAuthSwitchResponse();
                return;
            }
        }

        if (in_array($content[0], ["\0", "\xfe"])) {
            $this->__okPacket($content);
            return;
        } elseif ($content[0] === "\xff") {
            $this->__errPacket($content);
            return;
        }
        $this->handleQueryResponseText($content);
    }

    /**
     * Handles the handshake packet for MySQL v10.
     *
     * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_connection_phase_packets_protocol_handshake_v10.html
     *
     * @param string                              $content
     * @param \Ripple\App\MySQL\Constant\Protocol $version
     *
     * @return void
     * @throws \Ripple\App\MySQL\Exception\Exception
     */
    protected function handleHandshake(string $content, Protocol $version = Protocol::HandshakeV10): void
    {
        if ($version !== Protocol::HandshakeV10) {
            throw new Exception("Unsupported protocol version: {$version->value}");
        }

        $this->protocol       = Decode::FixedLengthInteger($content, 1);
        $this->version        = Decode::NullTerminatedString($content);
        $this->threadId       = Decode::FixedLengthInteger($content, 4);
        $this->authPluginData = Decode::FixedLengthString($content, 8);

        // ignore
        Decode::FixedLengthInteger($content, 1);

        $this->serverCapabilities          = Decode::FixedLengthInteger($content, 2);
        $this->charset                     = Decode::FixedLengthInteger($content, 1);
        $this->serverStatus                = Decode::FixedLengthInteger($content, 2);
        $this->serverCapabilitiesExtension = Decode::FixedLengthInteger($content, 2);
        $this->serverCapabilities          = ($this->serverCapabilitiesExtension << 16) | $this->serverCapabilities;
        $authPluginData2Length             = 0;
        if ($this->serverCapabilities & Capabilities::CLIENT_PLUGIN_AUTH) {
            $authPluginData2Length = Decode::FixedLengthInteger($content, 1);
        } else {
            // ignore
            Decode::FixedLengthInteger($content, 1);
        }

        // ignore
        Decode::FixedLengthString($content, 10);

        $authPluginData2Length = max(13, $authPluginData2Length - 8);
        $this->authPluginData  .= Decode::FixedLengthString($content, $authPluginData2Length);

        if ($this->serverCapabilities & Capabilities::CLIENT_PLUGIN_AUTH) {
            $this->authPluginName = Decode::NullTerminatedString($content);
        } else {
            Decode::NullTerminatedString($content);
        }
        $this->step = Connection::STEP_HANDSHAKE_REQUEST;
    }

    /**
     * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_connection_phase_packets_protocol_handshake_response.html
     *
     * @param \Ripple\App\MySQL\Constant\Protocol $version
     *
     * @return void
     * @throws \Ripple\App\MySQL\Exception\Exception
     */
    protected function sendHandshakeResponse(Protocol $version = Protocol::HandshakeResponse41): void
    {
        if ($version !== Protocol::HandshakeResponse41) {
            throw new Exception("Unsupported protocol version: {$version->value}");
        }

        $packet   = [];
        $packet[] = Encode::FixedLengthInteger(
            $this->clientCapabilities =
                Capabilities::CLIENT_PROTOCOL_41 |
                Capabilities::CLIENT_PLUGIN_AUTH |
                Capabilities::CLIENT_CONNECT_WITH_DB |
                Capabilities::CLIENT_PLUGIN_AUTH_LENENC_CLIENT_DATA |
                Capabilities::CLIENT_RESERVED2 |
                Capabilities::CLIENT_DEPRECATE_EOF,
            4
        );

        $packet[] = $maxPacketSize = Encode::FixedLengthInteger(0x40000000, 4);
        $packet[] = $charset = Encode::FixedLengthInteger(33, 1);
        $packet[] = $unused = str_repeat("\0", 23);
        $packet[] = $username = Encode::NullTerminatedString($this->config->user);
        if ($this->clientCapabilities & Capabilities::CLIENT_PLUGIN_AUTH_LENENC_CLIENT_DATA) {
            $packet[] = $authData = Encode::LengthEncodedString($this->generateAuthData($this->config->password));
        } else {
            $packet[] = $authResponseLength = Encode::FixedLengthInteger(
                strlen($authData = $this->generateAuthData($this->config->password)),
                1
            );
            $packet[] = $authData;
        }

        if ($this->clientCapabilities & Capabilities::CLIENT_CONNECT_WITH_DB) {
            $packet[] = $database = Encode::NullTerminatedString($this->config->database);
        }

        if ($this->clientCapabilities & Capabilities::CLIENT_PLUGIN_AUTH) {
            $packet[] = $pluginName = Encode::NullTerminatedString($this->authPluginName);
        }

        if ($this->clientCapabilities & Capabilities::CLIENT_CONNECT_ATTRS) {
            //TODO: Implement
        }

        if ($this->clientCapabilities & Capabilities::CLIENT_ZSTD_COMPRESSION_ALGORITHM) {
            //TODO: Implement
        }

        $this->sendPacket($packet);
        $this->step = Connection::STEP_HANDSHAKE_RESPONSE;
    }

    /**
     * @param string $password
     *
     * @return string
     * @throws \Ripple\App\MySQL\Exception\Exception
     */
    protected function generateAuthData(string $password): string
    {
        if ($this->serverCapabilities & Capabilities::CLIENT_PLUGIN_AUTH) {
            return match ($this->authPluginName) {
                "mysql_native_password" => PasswordEncode::nativePassword($password, $this->authPluginData),
                "caching_sha2_password" => PasswordEncode::sha2Password($password, $this->authPluginData),
                "mysql_clear_password"  => $password,
                default                 => throw new Exception("Unsupported auth method: {$this->authPluginName}")
            };
        }
        return PasswordEncode::nativePassword($password, $this->authPluginData);
    }

    /**
     * @param array|string $packet
     *
     * @return void
     * @throws \Ripple\App\MySQL\Exception\Exception
     */
    protected function sendPacket(array|string $packet): void
    {
        if (is_array($packet)) {
            $packet = implode('', $packet);
        }
        try {
            $this->stream->write($this->header($packet) . $packet);
        } catch (ConnectionException $e) {
            throw new Exception("Failed to send packet: {$e->getMessage()}");
        }
    }

    /**
     * @param string $content
     *
     * @return string
     */
    protected function header(string $content): string
    {
        return Encode::FixedLengthInteger(strlen($content) | (++$this->sequenceId << 24), 4);
    }

    /**
     * @param string $content
     *
     * @return void
     */
    protected function handleSwitchRequest(string $content): void
    {
        $tag                  = Decode::FixedLengthInteger($content, 1);
        $this->authPluginName = Decode::NullTerminatedString($content);
        $this->authPluginData = Decode::RestOfPacketString($content);
        $this->step           = Connection::STEP_AUTH_SWITCH_REQUEST;
    }

    /**
     *
     * @return void
     * @throws \Ripple\App\MySQL\Exception\Exception
     */
    protected function sendAuthSwitchResponse(): void
    {
        $this->sendPacket($this->generateAuthData($this->config->password));
        $this->step = Connection::STEP_AUTH_SWITCH_RESPONSE;
    }

    /**
     * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_ok_packet.html
     *
     * @param string $content
     *
     * @return void
     */
    protected function __okPacket(string $content): void
    {
        if ($this->handshake && strlen($content) < 9) {
            $this->__eofPacket($content);
            return;
        }

        $title        = Decode::FixedLengthInteger($content, 1);
        $affectedRows = Decode::LengthEncodedInteger($content);
        $insertId     = Decode::LengthEncodedInteger($content);
        if ($this->clientCapabilities & Capabilities::CLIENT_PROTOCOL_41) {
            $serverStatus = Decode::FixedLengthInteger($content, 2);
            $warningCount = Decode::FixedLengthInteger($content, 2);
        } elseif ($this->clientCapabilities & Capabilities::CLIENT_TRANSACTIONS) {
            $serverStatus = Decode::FixedLengthInteger($content, 2);
        }

        if ($this->clientCapabilities & Capabilities::CLIENT_SESSION_TRACK) {
            $info = Decode::LengthEncodedString($content);
            if ($this->clientCapabilities & Capabilities::SERVER_SESSION_STATE_CHANGED) {
                $sessionStateChanges = Decode::LengthEncodedString($content);
            }
        } else {
            $info = Decode::NullTerminatedString($content);
        }

        $okPacket = new OkPacket(
            $title,
            $affectedRows,
            $insertId,
            $serverStatus ?? null,
            $warningCount ?? null,
            $info,
            $sessionStateChanges ?? null
        );

        $this->onOkPacket($okPacket);
    }

    /**
     * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_eof_packet.html
     *
     * @param string $content
     *
     * @return void
     */
    protected function __eofPacket(string $content): void
    {
        $title = Decode::FixedLengthInteger($content, 1);
        if ($this->clientCapabilities & Capabilities::CLIENT_PROTOCOL_41) {
            $warningCount = Decode::FixedLengthInteger($content, 2);
            $serverStatus = Decode::FixedLengthInteger($content, 2);
        }

        $eofPacket = new EofPacket($title, $warningCount ?? null, $serverStatus ?? null);
        $this->onEofPacket($eofPacket);
    }

    /**
     * @param \Ripple\App\MySQL\Packet\EofPacket $eofPacket
     *
     * @return void
     */
    protected function onEofPacket(EofPacket $eofPacket): void
    {
        $suspension = array_shift($this->queue);
        $statement  = array_shift($this->statements);
        try {
            Coroutine::resume($suspension, $statement);
        } catch (Throwable $e) {
            Output::error($e->getMessage());
        }
        $this->resetSequenceId();
        $this->waitGroup->done();
    }

    /**
     * @return void
     */
    protected function resetSequenceId(): void
    {
        $this->sequenceId = -1;
    }

    /**
     * @param \Ripple\App\MySQL\Packet\OkPacket $okPacket
     *
     * @return void
     */
    protected function onOkPacket(OkPacket $okPacket): void
    {
        if ($this->step === Connection::STEP_AUTH_SWITCH_RESPONSE) {
            $this->step = Connection::STEP_ESTABLISHED;
            $this->resetSequenceId();
            $this->handshake = true;
            try {
                Coroutine::resume(array_shift($this->queue), true);
            } catch (Throwable $e) {
                Output::error($e->getMessage());
            }
        }
    }

    /**
     * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_err_packet.html
     *
     * @param string $content
     *
     * @return void
     */
    protected function __errPacket(string $content): void
    {
        $title = Decode::FixedLengthInteger($content, 1);
        $code  = Decode::FixedLengthInteger($content, 2);
        if ($this->clientCapabilities & Capabilities::CLIENT_PROTOCOL_41) {
            $stateMarker = Decode::FixedLengthString($content, 1);
            $sqlState    = Decode::FixedLengthString($content, 5);
        }
        $msg       = Decode::RestOfPacketString($content);
        $errPacket = new ErrPacket($title, $code, $stateMarker ?? null, $sqlState ?? null, $msg);
        $this->onErrPacket($errPacket);
    }

    /**
     * @param \Ripple\App\MySQL\Packet\ErrPacket $errPacket
     *
     * @return void
     */
    protected function onErrPacket(ErrPacket $errPacket): void
    {
        $this->resetSequenceId();
        $suspension = array_shift($this->queue);
        $statement  = array_shift($this->statements);
        $suspension->throw(new Exception($errPacket->msg, $errPacket->code));
        $this->waitGroup->done();
    }

    /**
     * @param string $content
     *
     * @return void
     */
    public function handleQueryResponseText(string $content): void
    {
        $this->statements[0]->filling($content);
    }

    /**
     * @param string $sql
     *
     * @return \Ripple\App\MySQL\Data\Statement
     * @throws \Ripple\App\MySQL\Exception\Exception
     */
    public function query(string $sql): Statement
    {
        $statement = $this->prepare($sql);
        $statement->execute();
        return $statement;
    }

    /**
     * @param string $query
     *
     * @return \Ripple\App\MySQL\Data\Statement
     */
    public function prepare(string $query): Statement
    {
        return new Statement($query, $this);
    }

    /**
     * @param \Ripple\App\MySQL\Data\Statement $statement
     *
     * @return \Ripple\App\MySQL\Data\Statement
     * @throws \Ripple\App\MySQL\Exception\Exception
     */
    public function execute(Statement $statement): Statement
    {
        if ($this->step !== Connection::STEP_ESTABLISHED) {
            throw new Exception("Connection not established.");
        }

        $this->waitGroup->wait();
        $this->waitGroup->add();
        $this->statements[] = $statement;
        $this->queue[]      = $suspension = getSuspension();
        $this->comQuery($statement->renderQueryString());
        try {
            return Coroutine::suspend($suspension);
        } catch (Throwable $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param string $command
     *
     * @return bool
     * @throws \Ripple\App\MySQL\Exception\Exception
     */
    public function comQuery(string $command): bool
    {
        $this->sendPacket("\x03{$command}");
        return true;
    }

    /**
     * @return void
     */
    public function close(): void
    {
        $this->stream->close();
    }
}
