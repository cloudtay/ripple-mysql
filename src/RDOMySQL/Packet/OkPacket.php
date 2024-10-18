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

use Ripple\RDOMySQL\Constant\Capabilities;
use Ripple\RDOMySQL\StreamConsume\Decode;

readonly class OkPacket
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
        public int|null    $title,
        public int|null    $affectedRows,
        public int|null    $insertId,
        public int|null    $serverStatus,
        public int|null    $warningCount,
        public string|null $info,
        public string|null $sessionStateChanges
    ) {
    }

    /**
     * @param string $content
     *
     * @return \Ripple\RDOMySQL\Packet\OkPacket
     */
    public static function decode(string $content): OkPacket
    {
        $title        = Decode::FixedLengthInteger($content, 1);
        $affectedRows = Decode::LengthEncodedInteger($content);
        $insertId     = Decode::LengthEncodedInteger($content);
        if (Capabilities::RIPPLE_CAPABILITIES->value & Capabilities::CLIENT_PROTOCOL_41->value) {
            $serverStatus = Decode::FixedLengthInteger($content, 2);
            $warningCount = Decode::FixedLengthInteger($content, 2);
        } elseif (Capabilities::RIPPLE_CAPABILITIES->value & Capabilities::CLIENT_TRANSACTIONS->value) {
            $serverStatus = Decode::FixedLengthInteger($content, 2);
        }

        if (Capabilities::RIPPLE_CAPABILITIES->value & Capabilities::CLIENT_SESSION_TRACK->value) {
            $info = Decode::LengthEncodedString($content);
            //            if (Capabilities::RIPPLE_CAPABILITIES->value & Capabilities::SERVER_SESSION_STATE_CHANGED->value) {
            //                $sessionStateChanges = Decode::LengthEncodedString($content);
            //            }
        } else {
            $info = Decode::NullTerminatedString($content);
        }

        return new OkPacket(
            $title,
            $affectedRows,
            $insertId,
            $serverStatus ?? null,
            $warningCount ?? null,
            $info,
            $sessionStateChanges ?? null
        );
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'title'               => $this->title,
            'affectedRows'        => $this->affectedRows,
            'insertId'            => $this->insertId,
            'serverStatus'        => $this->serverStatus,
            'warningCount'        => $this->warningCount,
            'info'                => $this->info,
            'sessionStateChanges' => $this->sessionStateChanges,
        ];
    }
}
