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

namespace Ripple\RDOMySQL\Data;

use Ripple\RDOMySQL\Data\Heap\HeapInterface;
use Ripple\RDOMySQL\Packet\EofPacket;
use Ripple\RDOMySQL\Packet\ErrPacket;
use Ripple\RDOMySQL\Packet\OkPacket;

use function is_int;
use function is_string;

abstract class ResultSet implements HeapInterface
{
    /*** @var int */
    protected int $cursor = 0;

    /*** @var array */
    protected array $data = [];

    /*** @var array */
    protected array $dataKv = [];

    /*** @var \Ripple\RDOMySQL\Packet\OkPacket */
    protected OkPacket $okPacket;

    /*** @var \Ripple\RDOMySQL\Packet\ErrPacket */
    protected ErrPacket $errPacket;

    /*** @var \Ripple\RDOMySQL\Packet\EofPacket */
    protected EofPacket $eofPacket;

    /**
     * @param int|string $columnKey
     *
     * @return mixed
     */
    public function fetchColumn(int|string $columnKey = 0): mixed
    {
        if (is_int($columnKey) && $data = $this->fetchNum()) {
            return $data[$columnKey] ?? false;
        }

        if (is_string($columnKey) && $data = $this->fetch()) {
            return $data[$columnKey] ?? false;
        }

        return false;
    }

    /**
     * @return array|false
     */
    public function fetchNum(): array|false
    {
        if ($data = $this->data[$this->cursor] ?? null) {
            $this->cursor++;
            return $data;
        }
        return false;
    }

    /**
     * @return array|false
     */
    public function fetch(): array|false
    {
        if ($data = $this->dataKv[$this->cursor] ?? null) {
            $this->cursor++;
            return $data;
        }
        return false;
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
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array
     */
    public function getDataKv(): array
    {
        return $this->dataKv;
    }

    /**
     * @return int
     */
    public function rowCount(): int
    {
        return $this->okPacket->affectedRows;
    }
}
