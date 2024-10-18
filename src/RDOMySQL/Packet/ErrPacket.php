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

readonly class ErrPacket
{
    /**
     * @param int         $title
     * @param int         $code
     * @param string|null $stateMarker
     * @param string|null $sqlState
     * @param string      $msg
     */
    public function __construct(
        public int         $title,
        public int         $code,
        public string|null $stateMarker,
        public string|null $sqlState,
        public string      $msg
    ) {
    }

    /**
     * @param string $content
     *
     * @return \Ripple\RDOMySQL\Packet\ErrPacket
     */
    public static function decode(string $content): ErrPacket
    {
        $title = Decode::FixedLengthInteger($content, 1);
        $code  = Decode::FixedLengthInteger($content, 2);
        if (Capabilities::RIPPLE_CAPABILITIES->value & Capabilities::CLIENT_PROTOCOL_41->value) {
            $stateMarker = Decode::FixedLengthString($content, 1);
            $sqlState    = Decode::FixedLengthString($content, 5);
        }
        $msg = Decode::RestOfPacketString($content);
        return new ErrPacket($title, $code, $stateMarker ?? null, $sqlState ?? null, $msg);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'title'       => $this->title,
            'code'        => $this->code,
            'stateMarker' => $this->stateMarker,
            'sqlState'    => $this->sqlState,
            'msg'         => $this->msg,
        ];
    }
}
