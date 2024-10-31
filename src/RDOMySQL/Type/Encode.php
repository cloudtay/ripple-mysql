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

namespace Ripple\RDOMySQL\Type;

use InvalidArgumentException;

use function pack;
use function str_pad;
use function strlen;

/**
 * StreamBuilder for MySQL Protocol
 *
 * This class provides methods to build MySQL packets and encode data types.
 *
 * The processing method of this class will consume the bytes required for parsing
 *
 * @see: https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_dt_integers.html
 * @see: https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_dt_strings.html
 */
class Encode
{
    public const  Decimal = 0x00;
    public const  Tiny = 0x01;
    public const  Short = 0x02;
    public const  Long = 0x03;
    public const  Float = 0x04;
    public const  Double = 0x05;
    public const  Null = 0x06;
    public const  Timestamp = 0x07;
    public const  LongLong = 0x08;
    public const  Int24 = 0x09;
    public const  Date = 0x0a;
    public const  Time = 0x0b;
    public const  Datetime = 0x0c;
    public const  Year = 0x0d;
    public const  NewDate = 0x0e; // Internal, not used in protocol, see Date
    public const  Varchar = 0x0f;
    public const  Bit = 0x10;
    public const  Timestamp2 = 0x11; // Internal, not used in protocol, see Timestamp
    public const  Datetime2 = 0x12; // Internal, not used in protocol, see DateTime
    public const  Time2 = 0x13; // Internal, not used in protocol, see Time
    public const  Json = 0xf5;
    public const  NewDecimal = 0xf6;
    public const  Enum = 0xf7;
    public const  Set = 0xf8;
    public const  TinyBlob = 0xf9;
    public const  MediumBlob = 0xfa;
    public const  LongBlob = 0xfb;
    public const  Blob = 0xfc;
    public const  VarString = 0xfd;
    public const  String = 0xfe;
    public const  Geometry = 0xff;

    public const INT_1 = 0x01;
    public const INT_2 = 0x02;
    public const INT_3 = 0x03;
    public const INT_4 = 0x04;
    public const INT_6 = 0x06;
    public const INT_8 = 0x08;

    /**
     * Encodes a fixed-length integer.
     *
     * @param int $value  The integer to encode.
     * @param int $length The length of the integer in bytes.
     *
     * @return string The encoded integer.
     * @throws InvalidArgumentException if the length is invalid.
     */
    public static function FixedLengthInteger(int $value, int $length): string
    {
        return match ($length) {
            self::INT_1 => pack('C', $value),
            self::INT_2 => pack('v', $value),
            self::INT_3 => pack('V', $value) . "\0", // Pad with zero for 3 bytes
            self::INT_4 => pack('V', $value),
            self::INT_6 => pack('P', $value) . "\0\0", // Pad with two zeros for 6 bytes
            self::INT_8 => pack('P', $value),
            default     => throw new InvalidArgumentException('Invalid length for fixed-length integer.')
        };
    }

    /**
     * Encodes a fixed-length string.
     *
     * @param string $value  The string to encode.
     * @param int    $length The length of the string to encode.
     *
     * @return string The encoded fixed-length string.
     * @throws InvalidArgumentException if the string is too long.
     */
    public static function FixedLengthString(string $value, int $length): string
    {
        if (strlen($value) > $length) {
            throw new InvalidArgumentException("String length exceeds fixed length of {$length}.");
        }

        return str_pad($value, $length, "\0");
    }

    /**
     * Encodes a null-terminated string.
     *
     * @param string $value The string to encode.
     *
     * @return string The encoded null-terminated string.
     */
    public static function NullTerminatedString(string $value): string
    {
        return $value . "\0";
    }

    /**
     * @param string $str
     *
     * @return string
     */
    public static function VariableLengthString(string $str): string
    {
        $length = strlen($str);
        $lengthEncoded = self::LengthEncodedInteger($length);
        return $lengthEncoded . $str;
    }

    /**
     * Encodes a length-encoded integer.
     *
     * @param int $value The integer to encode.
     *
     * @return string The encoded length-encoded integer.
     */
    public static function LengthEncodedInteger(int $value): string
    {
        if ($value < 0xfc) {
            return pack('C', $value);
        } elseif ($value <= 0xffff) {
            return pack('C*', 0xfc, $value);
        } elseif ($value <= 0xffffff) {
            return pack('C*', 0xfd, $value & 0xffff, ($value >> 16) & 0xff);
        } else {
            return pack('C*', 0xfe, $value);
        }
    }

    /**
     * Encodes a length-encoded string.
     *
     * @param string $value The string to encode.
     *
     * @return string The encoded length-encoded string.
     */
    public static function LengthEncodedString(string $value): string
    {
        return self::LengthEncodedInteger(strlen($value)) . $value;
    }
}
