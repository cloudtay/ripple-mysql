<?php declare(strict_types=1);

namespace Ripple\App\MySQL\StreamConsume;

use InvalidArgumentException;

use function dechex;
use function ord;
use function str_repeat;
use function strlen;
use function strpos;
use function substr;
use function unpack;

/**
 * Basic Data Types Parse
 *
 * @see: https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_dt_integers.html
 * @see: https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_dt_strings.html
 */
class Decode
{
    public const INT_1 = 0x01; // 1 字节
    public const INT_2 = 0x02; // 2 字节
    public const INT_3 = 0x03; // 3 字节
    public const INT_4 = 0x04; // 4 字节
    public const INT_6 = 0x06; // 6 字节
    public const INT_8 = 0x08; // 8 字节

    /**
     * Reads a fixed-length integer from content
     *
     * @param string $content Reference to the content string
     * @param int    $length  Length of the integer to read
     *
     * @return int The parsed integer
     * @throws InvalidArgumentException if the content is not long enough or the length is invalid
     */
    public static function FixedLengthInteger(string &$content, int $length): int
    {
        if (strlen($content) < $length) {
            throw new InvalidArgumentException("Not enough data in content for length {$length}.");
        }

        $value = match ($length) {
            self::INT_1 => ord($content[0]),
            self::INT_2 => unpack('v', substr($content, 0, 2))[1],
            self::INT_3 => unpack('V', substr($content, 0, 3) . "\0")[1],
            self::INT_4 => unpack('V', substr($content, 0, 4))[1],
            self::INT_6 => unpack('P', substr($content, 0, 6) . "\0\0")[1],
            self::INT_8 => unpack('P', substr($content, 0, 8))[1],
            default     => throw new InvalidArgumentException('Invalid length for fixed-length integer.')
        };

        // Remove read bytes from content
        $content = substr($content, $length);
        return $value;
    }

    /**
     * Reads a fixed-length string from content
     *
     * @param string $content Reference to the content string
     * @param int    $length  Length of the string to read
     *
     * @return string The parsed string
     * @throws InvalidArgumentException if not enough data is available
     */
    public static function FixedLengthString(string &$content, int $length): string
    {
        if (strlen($content) < $length) {
            throw new InvalidArgumentException('Not enough data for FixedLengthString.');
        }

        $value   = substr($content, 0, $length);
        $content = substr($content, $length); // Remove the read bytes
        return $value;
    }

    /**
     * Reads a null-terminated string from content
     *
     * @param string $content Reference to the content string
     *
     * @return string The parsed string
     */
    public static function NullTerminatedString(string &$content): string
    {
        $nullPos = strpos($content, "\0");

        if ($nullPos === false) {
            $value   = $content;
            $content = ''; // Clear content after extracting
            return $value;
        }

        $value   = substr($content, 0, $nullPos);
        $content = substr($content, $nullPos + 1); // Remove the null character
        return $value;
    }

    /**
     * Reads a variable-length string from content
     *
     * @param string $content Reference to the content string
     *
     * @return string The parsed string
     * @throws InvalidArgumentException if not enough data is available
     */
    public static function VariableLengthString(string &$content): string
    {
        $length = self::LengthEncodedInteger($content);
        if (strlen($content) < $length) {
            throw new InvalidArgumentException('Not enough data for VariableLengthString.');
        }

        $value   = substr($content, 0, $length);
        $content = substr($content, $length); // Remove the read bytes
        return $value;
    }

    /**
     * Reads a length-encoded integer from content
     *
     * @param string $content Reference to the content string
     *
     * @return int The parsed length-encoded integer
     * @throws InvalidArgumentException if content is empty or not enough data is available
     */
    public static function LengthEncodedInteger(string &$content): int
    {
        if (empty($content)) {
            throw new InvalidArgumentException('Content is empty.');
        }

        $firstByte = ord($content[0]);
        $content   = substr($content, 1); // Remove the first byte

        return match ($firstByte) {
            0xfc    => self::extractLengthEncodedInteger($content, 2),
            0xfd    => self::extractLengthEncodedInteger($content, 3),
            0xfe    => self::extractLengthEncodedInteger($content, 8),
            default => $firstByte,
        };
    }

    private static function extractLengthEncodedInteger(string &$content, int $bytes): int
    {
        if (strlen($content) < $bytes) {
            throw new InvalidArgumentException("Not enough data for LengthEncodedInteger (0x" . dechex(0xfc + ($bytes - 2)) . ").");
        }

        return unpack('V', substr($content, 0, $bytes) . str_repeat("\0", 4 - $bytes))[1];
    }

    /**
     * Reads a length-encoded string from content
     *
     * @param string $content Reference to the content string
     *
     * @return string The parsed string
     * @throws InvalidArgumentException if not enough data is available
     */
    public static function LengthEncodedString(string &$content): string
    {
        $length = self::LengthEncodedInteger($content);
        if (strlen($content) < $length) {
            throw new InvalidArgumentException('Not enough data for LengthEncodedString.');
        }

        $value   = substr($content, 0, $length);
        $content = substr($content, $length); // Remove the read bytes
        return $value;
    }

    /**
     * Reads the rest of the packet as a string
     *
     * @param string $content Reference to the content string
     *
     * @return string The rest of the packet
     */
    public static function RestOfPacketString(string &$content): string
    {
        $value   = $content;
        $content = '';
        return $value;
    }
}
