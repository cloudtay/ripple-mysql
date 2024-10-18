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

namespace Ripple\RDOMySQL\Data\Heap\ResultSet;

use InvalidArgumentException;
use Ripple\RDOMySQL\Data\Type;
use Ripple\RDOMySQL\StreamConsume\Decode;
use Ripple\RDOMySQL\StreamConsume\Encode;

use function intval;
use function pack;
use function preg_match;
use function sprintf;
use function str_pad;
use function strval;
use function unpack;

class ProtocolBinary
{
    /**
     * @param string                           $content
     * @param \Ripple\RDOMySQL\Data\Type $type
     *
     * @return string|int|float
     */
    public static function decode(string &$content, Type $type): string|int|float
    {
        return match ($type) {
            Type::STRING, Type::VARCHAR, Type::VAR_STRING, Type::ENUM, Type::SET,
            Type::LONG_BLOB, Type::MEDIUM_BLOB, Type::BLOB, Type::TINY_BLOB,
            Type::GEOMETRY, Type::BIT, Type::DECIMAL, Type::NEWDECIMAL,
            Type::JSON                                  => Decode::VariableLengthString($content),

            Type::LONGLONG                              => Decode::FixedLengthInteger($content, 8),
            Type::LONG, Type::INT24                     => Decode::FixedLengthInteger($content, 4),
            Type::SHORT, Type::YEAR                     => Decode::FixedLengthInteger($content, 2),
            Type::TINY                                  => Decode::FixedLengthInteger($content, 1),
            Type::DOUBLE                                => ProtocolBinary::decodeFloatDouble($content, 8),
            Type::FLOAT                                 => ProtocolBinary::decodeFloatDouble($content, 4),
            Type::DATE, Type::TIMESTAMP, Type::DATETIME => ProtocolBinary::decodeDateDatetimeTimestamp($content),
            Type::TIME                                  => ProtocolBinary::decodeTime($content),
            default                                     => throw new InvalidArgumentException("Unknown column type: {$type->value}"),
        };
    }


    /**
     * @param string $content
     * @param int    $length
     *
     * @return float
     */
    private static function decodeFloatDouble(string &$content, int $length): float
    {
        $rawValue = Decode::FixedLengthString($content, $length);
        return unpack(
            $length === 8 ? 'd' : 'f',
            $rawValue
        )[1];
    }

    /**
     * @param string $content
     *
     * @return string
     */
    private static function decodeDateDatetimeTimestamp(string &$content): string
    {
        $length = Decode::FixedLengthInteger($content, 1);
        if ($length === 0) {
            return '0000-00-00';
        }

        $year  = Decode::FixedLengthInteger($content, 2);
        $month = Decode::FixedLengthInteger($content, 1);
        $day   = Decode::FixedLengthInteger($content, 1);
        $hour  = $minute = $second = $microsecond = 0;

        if ($length > 4) {
            $hour        = Decode::FixedLengthInteger($content, 1);
            $minute      = Decode::FixedLengthInteger($content, 1);
            $second      = Decode::FixedLengthInteger($content, 1);
            $microsecond = $length > 7 ? Decode::FixedLengthInteger($content, 4) : 0;
        }

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d.%06d', $year, $month, $day, $hour, $minute, $second, $microsecond);
    }

    /**
     * @param string $content
     *
     * @return string
     */
    private static function decodeTime(string &$content): string
    {
        $length = Decode::FixedLengthInteger($content, 1);
        if ($length === 0) {
            return '00:00:00';
        }

        $isNegative  = Decode::FixedLengthInteger($content, 1);
        $days        = Decode::FixedLengthInteger($content, 4);
        $hour        = Decode::FixedLengthInteger($content, 1);
        $minute      = Decode::FixedLengthInteger($content, 1);
        $second      = Decode::FixedLengthInteger($content, 1);
        $microsecond = Decode::FixedLengthInteger($content, 4);

        return sprintf('%s%02d %02d:%02d:%02d.%06d', $isNegative ? '-' : '', $days, $hour, $minute, $second, $microsecond);
    }

    /**
     * @param mixed                                 $value
     * @param \Ripple\RDOMySQL\Data\Type|null $type
     *
     * @return string
     */
    public static function encode(mixed $value, Type $type = null): string
    {
        if (!$type) {
            $type = Type::fromValue($value);
        }
        return Encode::FixedLengthInteger($type->value, 2) .match ($type) {
            Type::NULL        => '',
            Type::LONG        => Encode::FixedLengthInteger($value, 4),
            Type::INT24       => Encode::FixedLengthInteger($value, 3),
            Type::LONGLONG    => Encode::FixedLengthInteger($value, 8),
            Type::TINY        => Encode::FixedLengthInteger($value, 1),
            Type::SHORT       => Encode::FixedLengthInteger($value, 2),
            Type::FLOAT       => ProtocolBinary::encodeFloatDouble($value, 4),
            Type::DOUBLE      => ProtocolBinary::encodeFloatDouble($value, 8),

            Type::STRING, Type::VARCHAR, Type::VAR_STRING, Type::ENUM, Type::SET,
            Type::LONG_BLOB, Type::MEDIUM_BLOB, Type::BLOB, Type::TINY_BLOB,
            Type::GEOMETRY, Type::DECIMAL, Type::NEWDECIMAL,
            Type::JSON       => Encode::VariableLengthString(strval($value)),

            Type::DATE, Type::DATETIME,
            Type::TIMESTAMP  => ProtocolBinary::encodeDateDatetimeTimestamp($value),

            Type::TIME       => ProtocolBinary::encodeTime($value),
            Type::BOOL       => Encode::FixedLengthInteger($value ? 1 : 0, 1),
            default          => throw new InvalidArgumentException("Unsupported type for encoding: {$type->name}")
        };
    }

    /**
     * @param float $value
     * @param int   $length
     *
     * @return string
     */
    private static function encodeFloatDouble(float $value, int $length): string
    {
        return match ($length) {
            4 => pack('f', $value),
            8 => pack('d', $value),
            default => throw new InvalidArgumentException('Invalid length for float/double encoding.')
        };
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private static function encodeDateDatetimeTimestamp(string $value): string
    {
        $pattern = '/^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}):(\d{2}):(\d{2})(?:\.(\d{1,6}))?)?$/';
        if (!preg_match($pattern, $value, $matches)) {
            throw new InvalidArgumentException("Invalid date/datetime format: $value");
        }

        $year   = (int)$matches[1];
        $month  = (int)$matches[2];
        $day    = (int)$matches[3];
        $hour   = $minute = $second = $microsecond = 0;

        $length = 4;
        if (isset($matches[4])) {
            $hour        = intval($matches[4]);
            $minute      = intval($matches[5]);
            $second      = intval($matches[6]);
            $length      = 7;
            if (isset($matches[7])) {
                $microsecond = intval(str_pad($matches[7], 6, '0'));
                $length      = 11;
            }
        }

        $encoded = Encode::FixedLengthInteger($length, 1)
                   . Encode::FixedLengthInteger($year, 2)
                   . Encode::FixedLengthInteger($month, 1)
                   . Encode::FixedLengthInteger($day, 1);

        if ($length > 4) {
            $encoded .= Encode::FixedLengthInteger($hour, 1)
                        . Encode::FixedLengthInteger($minute, 1)
                        . Encode::FixedLengthInteger($second, 1);
            if ($length > 7) {
                $encoded .= Encode::FixedLengthInteger($microsecond, 4);
            }
        }

        return $encoded;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private static function encodeTime(string $value): string
    {
        $pattern = '/^(-)?(?:(\d+) )?(\d{2}):(\d{2}):(\d{2})(?:\.(\d{1,6}))?$/';
        if (!preg_match($pattern, $value, $matches)) {
            throw new InvalidArgumentException("Invalid time format: $value");
        }

        $isNegative  = !empty($matches[1]) ? 1 : 0;
        $days        = isset($matches[2]) ? (int)$matches[2] : 0;
        $hour        = intval($matches[3]);
        $minute      = intval($matches[4]);
        $second      = intval($matches[5]);
        $microsecond = isset($matches[6]) ? intval(str_pad($matches[6], 6, '0')) : 0;

        $length = 8 + ($microsecond > 0 ? 4 : 0);
        $encoded = Encode::FixedLengthInteger($length, 1)
                   . Encode::FixedLengthInteger($isNegative, 1)
                   . Encode::FixedLengthInteger($days, 4)
                   . Encode::FixedLengthInteger($hour, 1)
                   . Encode::FixedLengthInteger($minute, 1)
                   . Encode::FixedLengthInteger($second, 1);

        if ($microsecond > 0) {
            $encoded .= Encode::FixedLengthInteger($microsecond, 4);
        }

        return $encoded;
    }
}
