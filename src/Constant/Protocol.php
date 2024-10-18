<?php declare(strict_types=1);

namespace Ripple\App\MySQL\Constant;

enum Protocol: int
{
    case HandshakeV9          = 9;
    case HandshakeV10         = 10;
    case HandshakeResponse41  = 41;
    case HandshakeResponse320 = 320;
}
