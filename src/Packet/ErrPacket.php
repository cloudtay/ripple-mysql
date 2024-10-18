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

namespace Ripple\App\MySQL\Packet;

class ErrPacket
{
    /**
     * @param int         $title
     * @param int         $code
     * @param string|null $stateMarker
     * @param string|null $sqlState
     * @param string      $msg
     */
    public function __construct(
        public readonly int         $title,
        public readonly int         $code,
        public readonly string|null $stateMarker,
        public readonly string|null $sqlState,
        public readonly string      $msg
    ) {
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
