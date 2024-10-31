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

class EofPacket
{
    /**
     * @param int $title
     * @param int $warningCount
     * @param int $serverStatus
     */
    public function __construct(
        public int $title,
        public int $warningCount,
        public int $serverStatus
    ) {
    }

    /**
     * @param string $content
     *
     * @return \Ripple\RDOMySQL\Packet\EofPacket
     */
    public static function fromString(string &$content): EofPacket
    {
        $title = Decode::FixedLengthInteger($content, 1);
        if (Connection::capabilities() & Capabilities::CLIENT_PROTOCOL_41->value) {
            $warningCount = Decode::FixedLengthInteger($content, 2);
            $serverStatus = Decode::FixedLengthInteger($content, 2);
        }

        return new EofPacket($title, $warningCount ?? null, $serverStatus ?? null);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'title'        => $this->title,
            'warningCount' => $this->warningCount,
            'serverStatus' => $this->serverStatus,
        ];
    }
}
