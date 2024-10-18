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

namespace Ripple\App\MySQL\Data;

use Revolt\EventLoop\Suspension;
use Ripple\App\MySQL\Connection;
use Ripple\App\MySQL\Constant\Capabilities;
use Ripple\App\MySQL\Packet\EofPacket;
use Ripple\App\MySQL\Packet\ErrPacket;
use Ripple\App\MySQL\Packet\OkPacket;
use Ripple\App\MySQL\StreamConsume\Decode;

use function addslashes;
use function is_bool;
use function is_null;
use function is_numeric;
use function is_string;
use function str_replace;
use function strval;

class Statement
{
    /*** @var \Ripple\App\MySQL\Packet\OkPacket */
    public OkPacket $okPacket;
    /*** @var \Ripple\App\MySQL\Packet\ErrPacket */
    public ErrPacket $errPacket;
    /*** @var \Ripple\App\MySQL\Packet\EofPacket */
    public EofPacket $eofPacket;
    /*** @var \Ripple\App\MySQL\Data\Column[] $columns */
    private array $columns = [];
    /*** @var array */
    private array $data = [];
    /*** @var array */
    private array $dataKv = [];
    /*** @var int */
    private int $columnsCount;
    /*** @var int */
    private int $cursor = 0;
    /*** @var array */
    private array $params = [];
    /*** @var int */
    private int $columnsCounter = 0;
    /*** @var int */
    private int $rowsCounter = 0;
    /**
     * @var \Revolt\EventLoop\Suspension
     */
    private Suspension $suspension;

    /**
     * @param string                       $queryString
     * @param \Ripple\App\MySQL\Connection $connector
     */
    public function __construct(public readonly string $queryString, private readonly Connection $connector)
    {
    }

    /**
     * @param string $content
     *
     * @return void
     */
    public function filling(string $content): void
    {
        if (!isset($this->columnsCount)) {
            // Check if optional result set metadata is supported
            if ($this->connector->getCapabilities() & Capabilities::CLIENT_OPTIONAL_RESULTSET_METADATA) {
                $metadataFollows = Decode::FixedLengthInteger($content, 1);
            }

            // Column data is always required
            $this->columnsCount = Decode::LengthEncodedInteger($content);
            return;
        }

        if ($this->columnsCounter < $this->columnsCount) {
            $this->fillingColumn($content);
        } else {
            $this->fillingData($content);
        }
    }

    /**
     * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_com_query_response_text_resultset_row.html
     *
     * @param string $content
     *
     * @return void
     */
    private function fillingColumn(string &$content): void
    {
        $catalog                   = Decode::LengthEncodedString($content);
        $schema                    = Decode::LengthEncodedString($content);
        $table                     = Decode::LengthEncodedString($content);
        $orgTable                  = Decode::LengthEncodedString($content);
        $name                      = Decode::LengthEncodedString($content);
        $orgName                   = Decode::LengthEncodedString($content);
        $lengthOfFixedLengthFields = Decode::LengthEncodedInteger($content);
        $characterSet              = Decode::FixedLengthInteger($content, 2);
        $columnLength              = Decode::FixedLengthInteger($content, 4);
        $type                      = Decode::FixedLengthInteger($content, 1);
        $flags                     = Decode::FixedLengthInteger($content, 2);
        $decimals                  = Decode::FixedLengthInteger($content, 1);
        $this->addColumn(new Column(
            $catalog,
            $schema,
            $table,
            $orgTable,
            $name,
            $orgName,
            $lengthOfFixedLengthFields,
            $characterSet,
            $columnLength,
            $type,
            $flags,
            $decimals
        ));
    }

    /**
     * @param \Ripple\App\MySQL\Data\Column $column
     *
     * @return void
     */
    private function addColumn(Column $column): void
    {
        $this->columns[] = $column;
        $this->columnsCounter++;
    }

    /**
     * @param string $content
     *
     * @return void
     */
    private function fillingData(string $content): void
    {
        $data  = [];
        $data2 = [];

        foreach ($this->columns as $column) {
            $data[]               = $value = $column->parse($content);
            $data2[$column->name] = $value;
        }
        $this->data[]   = $data;
        $this->dataKv[] = $data2;
        $this->rowsCounter++;
    }

    /**
     * @param array|null $params
     *
     * @return bool
     * @throws \Ripple\App\MySQL\Exception\Exception
     */
    public function execute(array $params = null): bool
    {
        if ($params) {
            foreach ($params as $key => $param) {
                $this->bindParam($key, $param);
            }
        }
        $this->connector->execute($this);
        return true;
    }

    /**
     * @param int|string $param
     * @param mixed      $var
     *
     * @return bool
     */
    public function bindParam(int|string $param, mixed &$var): bool
    {
        $this->params[$param] = &$var;
        return true;
    }

    /**
     * @return int
     */
    public function rowCount(): int
    {
        return $this->rowsCounter;
    }

    /**
     * @param int|string $column
     *
     * @return mixed
     */
    public function fetchColumn(int|string $column = 0): mixed
    {
        if (is_string($column)) {
            return $this->dataKv[$this->cursorNext()][$column];
        } else {
            return $this->data[$this->cursorNext()][$column];
        }
    }

    /**
     * @return int
     */
    public function cursorNext(): int
    {
        return $this->cursor++;
    }

    /**
     * @return array
     */
    public function fetchAll(): array
    {
        return $this->dataKv;
    }

    /**
     * @return array
     */
    public function fetchNum(): array
    {
        return $this->data;
    }

    /**
     * @return array
     */
    public function fetch(): array
    {
        return $this->dataKv[$this->cursorNext()];
    }

    /**
     * @return string
     */
    public function renderQueryString(): string
    {
        $queryString = $this->queryString;
        foreach ($this->params as $key => $value) {
            $queryString = str_replace(
                ":$key",
                $this->quote($value),
                $queryString
            );
        }
        return $queryString;
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    private function quote(mixed $value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return strval($value);
        }

        return "'" . addslashes($value) . "'";
    }

    /**
     * @param \Ripple\App\MySQL\Packet\OkPacket $okPacket
     *
     * @return Statement
     */
    public function ok(OkPacket $okPacket): Statement
    {
        $this->okPacket    = $okPacket;
        $this->rowsCounter = $okPacket->affectedRows;
        return $this;
    }


    /**
     * @param \Ripple\App\MySQL\Packet\ErrPacket $errPacket
     *
     * @return Statement
     */
    public function err(ErrPacket $errPacket): Statement
    {
        $this->errPacket = $errPacket;
        return $this;
    }

    /**
     * @param \Ripple\App\MySQL\Packet\EofPacket $eofPacket
     *
     * @return Statement
     */
    public function eof(EofPacket $eofPacket): Statement
    {
        $this->eofPacket = $eofPacket;
        return $this;
    }
}
