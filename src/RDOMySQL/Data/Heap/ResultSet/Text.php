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

namespace Ripple\RDOMySQL\Data\Heap\ResultSet;

use Ripple\RDOMySQL\Connection;
use Ripple\RDOMySQL\Constant\Capabilities;
use Ripple\RDOMySQL\Data\Column;
use Ripple\RDOMySQL\Data\Heap\HeapInterface;
use Ripple\RDOMySQL\Data\ResultSet;
use Ripple\RDOMySQL\Exception\Exception;
use Ripple\RDOMySQL\Packet\EofPacket;
use Ripple\RDOMySQL\Packet\ErrPacket;
use Ripple\RDOMySQL\Packet\OkPacket;
use Ripple\RDOMySQL\StreamConsume\Decode;

use function in_array;

class Text extends ResultSet implements HeapInterface
{
    /*** @var \Ripple\RDOMySQL\Data\Column[] $columns */
    protected array $columns = [];

    /*** @var array */
    protected array $data = [];

    /*** @var array */
    protected array $dataKv = [];

    /*** @var int */
    protected int $columnsCount;

    /*** @var int */
    protected int $columnsCounter = 0;

    /*** @var int */
    protected int $rowsCounter = 0;

    /**
     * @param string                            $queryString
     * @param \Ripple\RDOMySQL\Connection $connection
     */
    public function __construct(public readonly string $queryString, protected readonly Connection $connection)
    {
    }

    /**
     * @param string $content
     *
     * @return bool
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    public function filling(string $content): bool
    {
        if (in_array($content[0], ["\0", "\xfe"])) {
            if ($content[0] === "\xfe") {
                $this->eofPacket = EofPacket::decode($content);
            } elseif ($content[0] === "\0") {
                $this->okPacket = OkPacket::decode($content);
            }
            return true;
        } elseif ($content[0] === "\xff") {
            throw new Exception(ErrPacket::decode($content)->msg);
        }


        if (!isset($this->columnsCount)) {
            // Metadata is not supported by default
            if (Capabilities::RIPPLE_CAPABILITIES->value & Capabilities::CLIENT_OPTIONAL_RESULTSET_METADATA->value) {
                $metadataFollows = Decode::FixedLengthInteger($content, 1);
            }

            // Column data is always required
            $this->columnsCount = Decode::LengthEncodedInteger($content);

            if (empty($content)) {
                return false;
            }
        }

        if ($this->columnsCounter < $this->columnsCount) {
            $this->fillingColumn($content);
        } else {
            $this->fillingData($content);
        }

        return false;
    }

    /**
     * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_com_query_response_text_resultset_row.html
     *
     * @param string $content
     *
     * @return void
     */
    protected function fillingColumn(string $content): void
    {
        $this->columns[] = Column::decode($content);
        $this->columnsCounter++;
    }


    /**
     * @param string $content
     *
     * @return void
     */
    protected function fillingData(string $content): void
    {
        $data = [];
        $dataKv = [];
        foreach ($this->columns as $column) {
            $data[] = $value = $column->parse($content);
            $dataKv[$column->name] = $value;
        }
        $this->data[] = $data;
        $this->dataKv[] = $dataKv;
        $this->rowsCounter++;
    }
}
