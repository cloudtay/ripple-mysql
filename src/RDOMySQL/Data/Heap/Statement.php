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

namespace Ripple\RDOMySQL\Data\Heap;

use Ripple\RDOMySQL\Connection;
use Ripple\RDOMySQL\Data\Column;
use Ripple\RDOMySQL\Data\Heap\ResultSet\Binary;
use Ripple\RDOMySQL\Data\Heap\ResultSet\ProtocolBinary;
use Ripple\RDOMySQL\Data\ResultSet;
use Ripple\RDOMySQL\Exception\Exception;
use Ripple\RDOMySQL\Packet\ErrPacket;
use Ripple\RDOMySQL\Packet\StatementOkPacket;
use Ripple\RDOMySQL\Type\Encode;
use Throwable;

use function count;
use function intval;

class Statement implements HeapInterface
{
    /*** @var \Ripple\RDOMySQL\Packet\StatementOkPacket */
    protected StatementOkPacket $statementOk;

    /*** @var \Ripple\RDOMySQL\Data\Column[] */
    protected array $columns = [];

    /*** @var \Ripple\RDOMySQL\Data\Column[] */
    protected array $params = [];

    /*** @var int */
    protected int $paramsCounter = 0;

    /*** @var int */
    protected int $columnsCounter = 0;

    /*** @var int */
    protected int $step = 0;

    /**
     * @param string                            $queryString
     * @param \Ripple\RDOMySQL\Connection $connection
     */
    public function __construct(
        public readonly string        $queryString,
        protected readonly Connection $connection,
    ) {
    }


    /**
     * @param string $content
     *
     * @return bool
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    public function filling(string $content): bool
    {
        try {
            if (!isset($this->statementOk)) {
                if ($content[0] === "\xff") {
                    throw new Exception(ErrPacket::fromString($content)->msg);
                }
                $this->statementOk = StatementOkPacket::fromString($content);
            } elseif ($this->paramsCounter < $this->statementOk->paramsCount) {
                $this->fillingParam($content);
            } elseif ($this->columnsCounter < $this->statementOk->columnsCount) {
                $this->fillingColumn($content);
            }

            if ($this->paramsCounter === $this->statementOk->paramsCount && $this->columnsCounter === $this->statementOk->columnsCount) {
                return true;
            }
        } catch (Throwable $e) {
            throw new Exception($e->getMessage());
        }

        return false;
    }

    /**
     * @param string $content
     *
     * @return void
     */
    protected function fillingParam(string $content): void
    {
        $this->params[] = Column::fromString($content);
        $this->paramsCounter++;
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
        $this->columns[] = Column::fromString($content);
        $this->columnsCounter++;
    }

    /**
     * @param array $params
     *
     * @return ResultSet
     * @throws \Ripple\RDOMySQL\Exception\Exception
     */
    public function execute(array $params): ResultSet
    {
        $packet = ["\x17"];
        $packet[] = Encode::FixedLengthInteger($this->statementOk->stmtId, 4);
        $packet[] = Encode::FixedLengthInteger(0, 1);
        $packet[] = Encode::FixedLengthInteger(1, 4);

        $paramCount = count($params);
        if ($paramCount !== $this->statementOk->paramsCount) {
            throw new Exception('The number of parameters does not match the number of placeholders');
        }

        if ($paramCount > 0) {
            // nullBitmap
            $nullBitmap = 0;
            foreach ($params as $index => $value) {
                if ($value === null) {
                    $nullBitmap |= (1 << $index);
                }
            }

            $packet[] = Encode::FixedLengthInteger($nullBitmap, intval(($paramCount + 7) / 8));
            $packet[] = Encode::FixedLengthInteger(1, 1);

            foreach ($params as $index => $value) {
                $packet[] = ProtocolBinary::encode($value, $this->params[$index]->type);
            }
        }

        return $this->connection->connectionTransaction($packet, new Binary());
    }
}
