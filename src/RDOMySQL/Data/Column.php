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

use Ripple\RDOMySQL\Data\Heap\ResultSet\ProtocolBinary;
use Ripple\RDOMySQL\StreamConsume\Decode;

use function floatval;
use function gettype;
use function intval;
use function substr;

/**
 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_com_query_response_text_resultset_column_definition.html
 */
readonly class Column
{
    /**
     * @param string                           $catalog
     * @param string                           $schema
     * @param string                           $table
     * @param string                           $orgTable
     * @param string                           $name
     * @param string                           $orgName
     * @param int                              $lengthOfFixedLengthFields
     * @param int                              $characterSet
     * @param int                              $columnLength
     * @param \Ripple\RDOMySQL\Data\Type $type
     * @param int                              $flags
     * @param int                              $decimals
     */
    public function __construct(
        public string $catalog,
        public string $schema,
        public string $table,
        public string $orgTable,
        public string $name,
        public string $orgName,
        public int    $lengthOfFixedLengthFields,
        public int    $characterSet,
        public int    $columnLength,
        public Type   $type,
        public int    $flags,
        public int    $decimals
    ) {
    }

    /**
     * @param mixed $value
     *
     * @return int
     */
    public static function type(mixed $value): int
    {
        return match (gettype($value)) {
            'integer' => 0x08,
            'double'  => 0x05,
            'string'  => 0x0f,
            'NULL'    => 0x00,
            default   => 0
        };
    }

    /**
     * @param string $content
     *
     * @return string|int|float|null
     */
    public function parse(string &$content): string|int|float|null
    {
        if ($content[0] === "\xfb") {
            $content = substr($content, 1);
            return null;
        }

        $item = Decode::LengthEncodedString($content);
        return match ($this->type) {
            Type::DECIMAL, Type::FLOAT, Type::DOUBLE => floatval($item),
            Type::TINY, Type::SHORT, Type::LONG, Type::LONGLONG, Type::INT24 => intval($item),
            default => $item,
        };
    }

    /**
     * @param string $content
     *
     * @return string|int|float
     */
    public function parseBinary(string &$content): string|int|float
    {
        return ProtocolBinary::decode($content, $this->type);
    }

    /**
     * @param string $content
     *
     * @return \Ripple\Data\Column
     */
    public static function decode(string &$content): Column
    {
        $catalog                   = Decode::LengthEncodedString($content);
        $schema                    = Decode::LengthEncodedString($content);
        $table                     = Decode::LengthEncodedString($content);
        $orgTable                  = Decode::LengthEncodedString($content);
        $name                      = Decode::LengthEncodedString($content);
        $orgName                   = Decode::LengthEncodedString($content);
        $lengthOfFixedLengthFields = Decode::LengthEncodedInteger($content);
        $characterSet              = Decode::FixedLengthInteger($content, 2);
        $columnLength              = Decode::FixedLengthInteger($content, 4);
        $type                      = Decode::FixedLengthInteger($content, 1);
        $flags                     = Decode::FixedLengthInteger($content, 2);
        $decimals                  = Decode::FixedLengthInteger($content, 1);

        return new Column(
            $catalog,
            $schema,
            $table,
            $orgTable,
            $name,
            $orgName,
            $lengthOfFixedLengthFields,
            $characterSet,
            $columnLength,
            Type::from($type),
            $flags,
            $decimals
        );
    }

    /**
     * @return string[]
     */
    public function toArray(): array
    {
        return [
            'catalog'                   => $this->catalog,
            'schema'                    => $this->schema,
            'table'                     => $this->table,
            'orgTable'                  => $this->orgTable,
            'name'                      => $this->name,
            'orgName'                   => $this->orgName,
            'lengthOfFixedLengthFields' => $this->lengthOfFixedLengthFields,
            'characterSet'              => $this->characterSet,
            'columnLength'              => $this->columnLength,
            'type'                      => $this->type,
            'flags'                     => $this->flags,
            'decimals'                  => $this->decimals,
        ];
    }
}
