<?php declare(strict_types=1);

namespace Ripple\App\MySQL\StreamConsume;

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
     * Builds a packet from the given context and sequence ID.
     *
     * @param string $context    The content to include in the packet.
     * @param int    $sequenceId The sequence ID for the packet.
     *
     * @return string The encoded packet.
     */
    public static function packet(string $context, int $sequenceId): string
    {
        $length = strlen($context);
        return pack('V', $length) . pack('C', $sequenceId) . $context;
    }
}
