<?php declare(strict_types=1);

namespace Ripple\App\MySQL\Packet;

class EofPacket
{
    /**
     * @param int $title
     * @param int $warningCount
     * @param int $serverStatus
     */
    public function __construct(
        public readonly int $title,
        public readonly int $warningCount,
        public readonly int $serverStatus
    ) {
    }
}
