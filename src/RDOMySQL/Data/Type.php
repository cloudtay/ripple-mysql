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

namespace Ripple\RDOMySQL\Data;

use InvalidArgumentException;

use function gettype;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_null;
use function is_string;
use function strlen;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/field__types_8h_source.html
 */
enum Type: int
{
    case DECIMAL     = 0;
    case TINY        = 1;
    case SHORT       = 2;
    case LONG        = 3;
    case FLOAT       = 4;
    case DOUBLE      = 5;
    case NULL        = 6;
    case TIMESTAMP   = 7;
    case LONGLONG    = 8;
    case INT24       = 9;
    case DATE        = 10;
    case TIME        = 11;
    case DATETIME    = 12;
    case YEAR        = 13;
    case NEWDATE     = 14;
    case VARCHAR     = 15;
    case BIT         = 16;
    case TIMESTAMP2  = 17;
    case DATETIME2   = 18;
    case TIME2       = 19;
    case TYPED_ARRAY = 20;
    case VECTOR      = 242;
    case INVALID     = 243;
    case BOOL        = 244;
    case JSON        = 245;
    case NEWDECIMAL  = 246;
    case ENUM        = 247;
    case SET         = 248;
    case TINY_BLOB   = 249;
    case MEDIUM_BLOB = 250;
    case LONG_BLOB   = 251;
    case BLOB        = 252;
    case VAR_STRING  = 253;
    case STRING      = 254;
    case GEOMETRY    = 255;

    /**
     * Get MySQL type constant by PHP variable type.
     *
     * @param mixed $value The value to infer MySQL type from.
     *
     * @return Type The corresponding MySQL type constant.
     */
    public static function fromValue(mixed $value): Type
    {
        if (is_null($value)) {
            return Type::NULL;
        } elseif (is_int($value)) {
            return ($value >= PHP_INT_MIN && $value <= PHP_INT_MAX) ? Type::LONG : Type::INT24;
        } elseif (is_float($value)) {
            return Type::DOUBLE;
        } elseif (is_string($value)) {
            return strlen($value) < 65536 ? Type::VAR_STRING : Type::STRING;
        } elseif (is_bool($value)) {
            return Type::BOOL;
        } elseif (is_array($value)) {
            return Type::TYPED_ARRAY;
        }

        throw new InvalidArgumentException("Unsupported PHP type for MySQL binding: " . gettype($value));
    }
}
