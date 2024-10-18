<?php declare(strict_types=1);

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
