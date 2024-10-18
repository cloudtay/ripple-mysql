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

use Ripple\RDOMySQL\Data\Column;
use Ripple\RDOMySQL\Data\Heap\HeapInterface;
use Ripple\RDOMySQL\Data\ResultSet;
use Ripple\RDOMySQL\Exception\Exception;
use Ripple\RDOMySQL\Packet\EofPacket;
use Ripple\RDOMySQL\Packet\ErrPacket;
use Ripple\RDOMySQL\Packet\OkPacket;
use Ripple\RDOMySQL\StreamConsume\Decode;

use function intval;
use function substr;

class Binary extends ResultSet implements HeapInterface
{
    /** @var int */
    protected int $columnsCount;

    /*** @var int */
    protected int $columnsCounter = 0;

    /*** @var \Ripple\RDOMySQL\Data\Column[] */
    protected array $columns = [];

    /*** @var array */
    protected array $data = [];

    /*** @var array */
    protected array $dataKv = [];

    /**
     * @param string $content
     *
     * @return bool
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    public function filling(string $content): bool
    {
        if ($content[0] === "\xfe") {
            $this->eofPacket = EofPacket::decode($content);
            return true;
        } elseif ($content[0] === "\xff") {
            throw new Exception(($this->errPacket = ErrPacket::decode($content))->msg);
        } elseif ($content[0] === "\x00") {
            // Step 1: Parse column count if not set
            if (!isset($this->columnsCount)) {
                $this->okPacket = OkPacket::decode($content);
                return true;
            }
        } elseif (!isset($this->columnsCount)) {
            $this->columnsCount = Decode::LengthEncodedInteger($content);
            return false;
        }

        // Step 2: Parse columns metadata
        if ($this->columnsCounter < $this->columnsCount) {
            $this->columns[] = Column::decode($content);
            $this->columnsCounter++;
            return false;
        }
        $content = substr($content, 1);

        // Parse NULL bitmap
        $data             = [];
        $dataKv           = [];
        $nullBitmapLength = intval(($this->columnsCount + 7 + 2) / 8);
        $nullBitmap       = Decode::FixedLengthInteger($content, $nullBitmapLength);

        /**
         * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_binary_resultset.html#
         */
        foreach ($this->columns as $index => $column) {
            if (($nullBitmap & (1 << ($index + 2))) !== 0) {
                $data[] = null;
                $dataKv[$column->name] = null;
                continue;
            }
            $data[] = $value = $column->parseBinary($content);
            $dataKv[$column->name] = $value;
        }

        $this->data[] = $data;
        $this->dataKv[] = $dataKv;
        return false;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
