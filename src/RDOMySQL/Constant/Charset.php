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

namespace Ripple\RDOMySQL\Constant;

use InvalidArgumentException;

enum Charset: int
{
    case UTF8 = 33;
    case UTF8MB4 = 45;
    case LATIN1 = 8;
    case ASCII = 11;
    case BINARY = 63;
    case BIG5 = 1;
    case UCS2 = 35;
    case SJIS = 13;
    case GBK = 28;
    case GB2312 = 24;
    case EUC_JP = 12;
    case EUC_KR = 19;
    case TIS620 = 18;

    /**
     * @param string $charset
     *
     * @return \Ripple\RDOMySQL\Constant\Charset
     */
    public static function fromString(string $charset): Charset
    {
        return match ($charset) {
            'utf8' => Charset::UTF8,
            'utf8mb4' => Charset::UTF8MB4,
            'latin1' => Charset::LATIN1,
            'ascii' => Charset::ASCII,
            'binary' => Charset::BINARY,
            'big5' => Charset::BIG5,
            'ucs2' => Charset::UCS2,
            'sjis' => Charset::SJIS,
            'gbk' => Charset::GBK,
            'gb2312' => Charset::GB2312,
            'eucjpms' => Charset::EUC_JP,
            'euckr' => Charset::EUC_KR,
            'tis620' => Charset::TIS620,
            default => throw new InvalidArgumentException("Unknown charset: $charset")
        };
    }
}
