<?php declare(strict_types=1);
/**
 * Copyright © 2024 cclilshy
 * Email: jingnigg@gmail.com
 *
 * This software is licensed under the MIT License.
 * For full license details, please visit: https://opensource.org/licenses/MIT
 *
 * By using this software, you agree to the terms of the license.
 * Contributions, suggestions, and feedback are always welcome!
 */

namespace Ripple\RDOMySQL;

use Closure;
use Co\IO;
use InvalidArgumentException;
use PDO;
use Revolt\EventLoop\Suspension;
use Ripple\Config;
use Ripple\Coroutine;
use Ripple\Coroutine\WaitGroup;
use Ripple\RDOMySQL\Constant\Capabilities;
use Ripple\RDOMySQL\Constant\Charset;
use Ripple\RDOMySQL\Constant\Protocol;
use Ripple\RDOMySQL\Data\Heap\HeapInterface;
use Ripple\RDOMySQL\Data\Heap\ResultSet\Text;
use Ripple\RDOMySQL\Data\Heap\Statement;
use Ripple\RDOMySQL\Data\ResultSet;
use Ripple\RDOMySQL\Exception\Exception;
use Ripple\RDOMySQL\Packet\EofPacket;
use Ripple\RDOMySQL\Packet\ErrPacket;
use Ripple\RDOMySQL\Packet\OkPacket;
use Ripple\RDOMySQL\StreamConsume\Decode;
use Ripple\RDOMySQL\StreamConsume\Encode;
use Ripple\Socket\SocketStream;
use Ripple\Stream\Exception\ConnectionException;
use Ripple\Utils\Output;
use Throwable;

use function ceil;
use function Co\async;
use function Co\cancel;
use function Co\delay;
use function Co\getSuspension;
use function implode;
use function in_array;
use function intval;
use function is_array;
use function max;
use function openssl_get_publickey;
use function openssl_public_encrypt;
use function str_repeat;
use function strlen;
use function substr;

use const OPENSSL_PKCS1_OAEP_PADDING;

class Connection
{
    // waiting for handshake
    public const STEP_HANDSHAKE = 0;

    // handshake request
    public const STEP_HANDSHAKE_REQUEST = 1;

    // handshake response
    public const STEP_HANDSHAKE_RESPONSE = 2;

    // connection established
    public const STEP_ESTABLISHED = 5;

    /*** @var HeapInterface */
    public HeapInterface $heap;

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

    /*** @var Charset */
    protected Charset $charset;

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

    /*** @var \Ripple\Config */
    protected Config $config;

    /*** @var int */
    protected int $timeout = 0;

    /*** @var \Revolt\EventLoop\Suspension */
    protected Suspension $lockSuspension;

    /*** @var \Ripple\RDOMySQL\Constant\Charset */
    protected Charset $clientCharset;

    /**
     * @param \Ripple\Config|array|string $config
     * @param string|null                 $username
     * @param null                        $password
     * @param array                       $options
     *
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    public function __construct(Config|array|string $config, string $username = null, $password = null, array $options = [])
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

        if ($charset = $this->config->charset ?? $options['charset'] ?? null) {
            $this->clientCharset = Charset::fromString($charset);
        } else {
            $this->clientCharset = Charset::UTF8;
        }

        if (isset($options['timeout'])) {
            $this->timeout = intval($options['timeout']);
        } elseif (isset($options[PDO::ATTR_TIMEOUT])) {
            $this->timeout = intval($options[PDO::ATTR_TIMEOUT]);
        }

        $this->waitGroup = new WaitGroup();
        $this->connect();
    }

    /**
     * @return void
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    public function connect(): void
    {
        if ($this->handshake) {
            return;
        }

        if ($this->step !== Connection::STEP_HANDSHAKE) {
            throw new Exception("Connection already established.");
        }

        try {
            $this->stream = IO::Socket()->connect("tcp://{$this->config->host}:{$this->config->port}");
        } catch (ConnectionException $e) {
            throw new Exception("Failed to connect to MySQL server: {$e->getMessage()}");
        }
        $this->stream->setBlocking(false);

        async(function () {
            while (1) {
                try {
                    $this->stream->waitForReadable();
                } catch (Throwable) {
                    break;
                }

                $content = $this->stream->readContinuously(8192);
                if ($content === '') {
                    break;
                }

                try {
                    $this->transmitting($content);
                } catch (Throwable $e) {
                    if (isset($this->lockSuspension)) {
                        try {
                            Coroutine::throw($this->lockSuspension, new Exception($e->getMessage()));
                        } catch (Throwable) {

                        }
                    }
                }
            }

            $this->stream->close();
        });

        try {
            $this->takeWaitGroup(getSuspension());

            $packet = Coroutine::suspend($this->lockSuspension);

            if ($packet instanceof OkPacket) {
                $this->step      = Connection::STEP_ESTABLISHED;
                $this->handshake = true;
            }

            if ($packet instanceof EofPacket) {
                $this->step      = Connection::STEP_ESTABLISHED;
                $this->handshake = true;
            }

            if ($packet instanceof ErrPacket) {
                throw new Exception($packet->msg);
            }

            $this->unWaitGroup();
        } catch (Throwable $e) {
            throw new Exception("Failed to connect to MySQL server: {$e->getMessage()}");
        }
    }

    /**
     * @param string $content
     *
     * @return void
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    protected function transmitting(string $content): void
    {
        $this->buffer .= $content;
        do {
            try {
                $packetLength     = Decode::FixedLengthInteger($this->buffer, 3);
                $this->sequenceId = Decode::FixedLengthInteger($this->buffer, 1);
            } catch (InvalidArgumentException $e) {
                throw new Exception("Invalid packet: {$e->getMessage()}");
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
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    protected function handlePayload(string $content): void
    {
        if ($this->step < Connection::STEP_ESTABLISHED) {
            if (in_array($content[0], ["\x00", "\xff"])) {
                $this->fillingPacket($content);
                return;
            }

            if ($this->step === Connection::STEP_HANDSHAKE) {
                $this->handleHandshake($content);
                $this->step = Connection::STEP_HANDSHAKE_REQUEST;
            }

            if ($this->step === Connection::STEP_HANDSHAKE_REQUEST) {
                $this->sendHandshakeResponse();
                $this->step = Connection::STEP_HANDSHAKE_RESPONSE;
                return;
            }

            if ($this->step == Connection::STEP_HANDSHAKE_RESPONSE) {
                if ($content[0] === "\x01") {
                    $extra = substr($content, 1);
                    if (!$extra = Decode::RestOfPacketString($extra)) {
                        throw new Exception("Invalid auth switch request.");
                    }

                    if ($extra[0] === "\x03") {
                        return;
                    }

                    if ($extra[0] === "\x04") {
                        $this->sendPacket("\x02");
                        return;
                    }

                    if ($extra[0] === "\x2d") {
                        $publicKey = openssl_get_publickey($extra);
                        if ($publicKey === false) {
                            throw new Exception("Invalid public key.");
                        }

                        $password = "{$this->config->password}\0";
                        $authDataLength = strlen($this->authPluginData);
                        $passwordLength = strlen($this->config->password);
                        $repeatCount = intval(ceil($passwordLength / $authDataLength));
                        $repeatedAuthPluginData = str_repeat($this->authPluginData, $repeatCount);
                        $isEncrypted = openssl_public_encrypt(
                            $password ^ $repeatedAuthPluginData,
                            $result,
                            $publicKey,
                            OPENSSL_PKCS1_OAEP_PADDING
                        );

                        if (!$isEncrypted) {
                            throw new Exception("Failed to encrypt password.");
                        }

                        $this->sendPacket($result);
                        return;
                    }
                } else {
                    $this->handleSwitchRequest($content);
                }
            }
            return;
        }

        $this->filling($content);
    }

    /**
     * @param string $content
     *
     * @return void
     */
    public function fillingPacket(string $content): void
    {
        if (in_array($content[0], ["\0", "\xfe"])) {
            if (strlen($content) < 9) {
                try {
                    Coroutine::resume($this->lockSuspension, EofPacket::decode($content));
                } catch (Throwable $e) {
                    Output::warning($e->getMessage());
                }
            } else {
                try {
                    Coroutine::resume($this->lockSuspension, OkPacket::decode($content));
                } catch (Throwable $e) {
                    Output::warning($e->getMessage());
                }
            }
        } elseif ($content[0] === "\xff") {
            Coroutine::throw($this->lockSuspension, new Exception(ErrPacket::decode($content)->msg));
        }
    }

    /**
     * Handles the handshake packet for MySQL v10.
     *
     * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_connection_phase_packets_protocol_handshake_v10.html
     *
     * @param string                             $content
     * @param \Ripple\RDOMySQL\Constant\Protocol $version
     *
     * @return void
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    protected function handleHandshake(string &$content, Protocol $version = Protocol::HandshakeV10): void
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

        $this->serverCapabilities = Decode::FixedLengthInteger($content, 2);
        $charset                  = Decode::FixedLengthInteger($content, 1);

        if (!isset($this->clientCharset)) {
            if (!$charset & $this->clientCharset->value) {
                throw new Exception("Unsupported charset: {$charset}");
            }
        }

        $this->serverStatus                = Decode::FixedLengthInteger($content, 2);
        $this->serverCapabilitiesExtension = Decode::FixedLengthInteger($content, 2);
        $this->serverCapabilities          = ($this->serverCapabilitiesExtension << 16) | $this->serverCapabilities;
        $authPluginData2Length             = 0;
        if ($this->serverCapabilities & Capabilities::CLIENT_PLUGIN_AUTH->value) {
            $authPluginData2Length = Decode::FixedLengthInteger($content, 1);
        } else {
            // ignore
            Decode::FixedLengthInteger($content, 1);
        }

        // ignore
        Decode::FixedLengthString($content, 10);

        $authPluginData2Length = max(13, $authPluginData2Length - 8);
        $this->authPluginData  .= Decode::FixedLengthString($content, $authPluginData2Length);

        if ($this->serverCapabilities & Capabilities::CLIENT_PLUGIN_AUTH->value) {
            $this->authPluginName = Decode::NullTerminatedString($content);
        } else {
            Decode::NullTerminatedString($content);
        }
    }

    /**
     * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_connection_phase_packets_protocol_handshake_response.html
     *
     * @param \Ripple\RDOMySQL\Constant\Protocol $version
     *
     * @return void
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    protected function sendHandshakeResponse(Protocol $version = Protocol::HandshakeResponse41): void
    {
        if ($version !== Protocol::HandshakeResponse41) {
            throw new Exception("Unsupported protocol version: {$version->value}");
        }

        $packet   = [];
        $packet[] = Encode::FixedLengthInteger(Capabilities::RIPPLE_CAPABILITIES->value, 4);

        $packet[] = $maxPacketSize = Encode::FixedLengthInteger(0x40000000, 4);
        $packet[] = $charset = Encode::FixedLengthInteger($this->clientCharset->value, 1);
        $packet[] = $unused = str_repeat("\0", 23);
        $packet[] = $username = Encode::NullTerminatedString($this->config->user);
        if (Capabilities::RIPPLE_CAPABILITIES->value & Capabilities::CLIENT_PLUGIN_AUTH_LENENC_CLIENT_DATA->value) {
            $packet[] = $authData = Encode::LengthEncodedString($this->generateAuthData($this->config->password));
        } else {
            $packet[] = $authResponseLength = Encode::FixedLengthInteger(
                strlen($authData = $this->generateAuthData($this->config->password)),
                1
            );
            $packet[] = $authData;
        }

        if (Capabilities::RIPPLE_CAPABILITIES->value & Capabilities::CLIENT_CONNECT_WITH_DB->value) {
            $packet[] = $database = Encode::NullTerminatedString($this->config->database);
        }

        if (Capabilities::RIPPLE_CAPABILITIES->value & Capabilities::CLIENT_PLUGIN_AUTH->value) {
            $packet[] = $pluginName = Encode::NullTerminatedString($this->authPluginName);
        }

        if (Capabilities::RIPPLE_CAPABILITIES->value & Capabilities::CLIENT_CONNECT_ATTRS->value) {
            //TODO: Implement
        }

        if (Capabilities::RIPPLE_CAPABILITIES->value & Capabilities::CLIENT_ZSTD_COMPRESSION_ALGORITHM->value) {
            //TODO: Implement
        }

        $this->sendPacket($packet);
    }

    /**
     * @param string $password
     *
     * @return string
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    protected function generateAuthData(string $password): string
    {
        if ($this->serverCapabilities & Capabilities::CLIENT_PLUGIN_AUTH->value) {
            return match ($this->authPluginName) {
                'mysql_native_password' => Encryptor::nativePassword($password, $this->authPluginData),
                'caching_sha2_password' => Encryptor::sha2Password($password, $this->authPluginData),
                'mysql_clear_password'  => $password,
                'sha1_password'         => Encryptor::sha1Password($password, $this->authPluginData),
                'bcrypt_password'       => Encryptor::bcryptPassword($password),
                'argon2_password'       => Encryptor::argon2Password($password),
                default                 => throw new Exception("Unsupported auth method: {$this->authPluginName}")
            };
        }
        return Encryptor::nativePassword($password, $this->authPluginData);
    }

    /**
     * @param array|string $packet
     *
     * @return void
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    public function sendPacket(array|string $packet): void
    {
        if (is_array($packet)) {
            $packet = implode('', $packet);
        }

        if (strlen($packet) > 0xffffff) {
            throw new Exception("Packet too large.");
        }

        try {
            $this->stream->write($this->packet($packet));
        } catch (ConnectionException $e) {
            throw new Exception("Failed to send packet: {$e->getMessage()}");
        }
    }

    /**
     * @param string $content
     *
     * @return string
     */
    protected function packet(string $content): string
    {
        return Encode::FixedLengthInteger(strlen($content) | (++$this->sequenceId << 24), 4) . $content;
    }

    /**
     * @param string $content
     *
     * @return void
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    protected function handleSwitchRequest(string &$content): void
    {
        $tag                  = Decode::FixedLengthInteger($content, 1);
        $this->authPluginName = Decode::NullTerminatedString($content);
        $this->authPluginData = Decode::RestOfPacketString($content);
        $this->sendAuthSwitchResponse();
    }

    /**
     *
     * @return void
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    protected function sendAuthSwitchResponse(): void
    {
        $this->sendPacket($this->generateAuthData($this->config->password));
    }

    /**
     * @param string $content
     *
     * @return void
     */
    public function filling(string $content): void
    {
        if (!isset($this->heap)) {
            $this->fillingPacket($content);
            return;
        }

        try {
            $result = $this->heap->filling($content);
        } catch (Throwable $e) {
            if (!$e instanceof Exception) {
                $e = new Exception($e->getMessage());
            }
            Coroutine::throw($this->lockSuspension, $e);
            return;
        }

        if ($result) {
            try {
                Coroutine::resume($this->lockSuspension, $this->heap);
            } catch (Throwable $e) {
                Output::warning($e->getMessage());
            }
        }
    }

    /**
     * @return void
     */
    public function close(): void
    {
        $this->stream->close();
    }

    /**
     * @param \Revolt\EventLoop\Suspension $suspension
     *
     * @return void
     */
    public function takeWaitGroup(Suspension $suspension): void
    {
        $this->waitGroup->wait();
        $this->waitGroup->add();
        $this->lockSuspension = $suspension;
    }

    /**
     * @return void
     */
    public function unWaitGroup(): void
    {
        $this->waitGroup->done();
    }

    /**
     * @param string $query
     *
     * @return Statement
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    public function prepare(string $query): Statement
    {
        return $this->connectionTransaction("\x16{$query}", new Statement($query, $this));
    }

    /**
     * @param string|array                             $packet
     * @param \Ripple\RDOMySQL\Data\Heap\HeapInterface $heap
     *
     * @return mixed
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    public function connectionTransaction(string|array $packet, HeapInterface $heap): mixed
    {
        $this->takeWaitGroup($suspension = getSuspension());

        $this->sequenceId = -1;
        $this->sendPacket($packet);
        $this->heap = $heap;

        if ($this->timeout > 0) {
            $timeoutOID = delay(static function () use ($suspension) {
                Coroutine::throw($suspension, new Exception("Query timeout."));
            }, $this->timeout);
        }

        try {
            $result = Coroutine::suspend($suspension);
            isset($timeoutOID) && cancel($timeoutOID);
        } catch (Exception $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new Exception($e->getMessage());
        } finally {
            unset($this->heap);
        }

        try {
            return $result;
        } finally {
            $this->unWaitGroup();
        }
    }

    /**
     * @param Closure $closure
     *
     * @return void
     * @throws Throwable
     */
    public function transaction(Closure $closure): void
    {
        $this->beginTransaction();
        try {
            $closure();
            $this->commit();
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * @return void
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    public function beginTransaction(): void
    {
        $this->query('START TRANSACTION;');
    }

    /**
     * @param string $sql
     *
     * @return ResultSet
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    public function query(string $sql): ResultSet
    {
        return $this->connectionTransaction("\x03{$sql}", new Text($sql, $this));
    }

    /**
     * @return void
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    public function commit(): void
    {
        $this->query('COMMIT;');
    }

    /**
     * @return void
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    public function rollback(): void
    {
        $this->query('ROLLBACK;');
    }
}
