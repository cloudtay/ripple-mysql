<?php declare(strict_types=1);

namespace Ripple\App\MySQL\Packet;

class OkPacket
{
    /**
     * @param int|null    $title
     * @param int|null    $affectedRows
     * @param int|null    $insertId
     * @param int|null    $serverStatus
     * @param int|null    $warningCount
     * @param string|null $info
     * @param string|null $sessionStateChanges
     */
    public function __construct(
        public readonly int|null    $title,
        public readonly int|null    $affectedRows,
        public readonly int|null    $insertId,
        public readonly int|null    $serverStatus,
        public readonly int|null    $warningCount,
        public readonly string|null $info,
        public readonly string|null $sessionStateChanges
    ) {
    }
}
