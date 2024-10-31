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

namespace Ripple\RDOMySQL\Packet;

use Ripple\RDOMySQL\Connection;
use Ripple\RDOMySQL\Constant\Capabilities;
use Ripple\RDOMySQL\Type\Decode;

use function strlen;

class StatementOkPacket
{
    /**
     * @param int      $code
     * @param int      $stmtId
     * @param int      $columnsCount
     * @param int      $paramsCount
     * @param string   $reserved
     * @param int|null $warningCount
     * @param int|null $metadata
     */
    public function __construct(
        public int      $code,
        public int      $stmtId,
        public int      $columnsCount,
        public int      $paramsCount,
        public string   $reserved,
        public int|null $warningCount,
        public int|null $metadata
    ) {
    }

    /**
     * @param string $content
     *
     * @return \Ripple\RDOMySQL\Packet\StatementOkPacket
     */
    public static function fromString(string &$content): StatementOkPacket
    {
        $code         = Decode::FixedLengthInteger($content, 1);
        $stmtId       = Decode::FixedLengthInteger($content, 4);
        $columnsCount = Decode::FixedLengthInteger($content, 2);
        $paramsCount  = Decode::FixedLengthInteger($content, 2);
        $reserved     = Decode::FixedLengthString($content, 1);
        if (strlen($content) > 12) {
            $warningCount = Decode::FixedLengthInteger($content, 2);
        }

        if (Connection::capabilities() & Capabilities::CLIENT_OPTIONAL_RESULTSET_METADATA->value) {
            $metadata = Decode::FixedLengthInteger($content, 1);
        }
        return new StatementOkPacket($code, $stmtId, $columnsCount, $paramsCount, $reserved, $warningCount ?? null, $metadata ?? null);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'code'         => $this->code,
            'stmtId'       => $this->stmtId,
            'columnsCount' => $this->columnsCount,
            'paramsCount'  => $this->paramsCount,
            'reserved'     => $this->reserved,
            'warningCount' => $this->warningCount,
            'metadata'     => $this->metadata,
        ];
    }
}
