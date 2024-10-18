<?php declare(strict_types=1);

namespace Ripple\App\MySQL\Data;

use Ripple\App\MySQL\StreamConsume\Decode;

use function floatval;
use function intval;
use function substr;

/**
 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_com_query_response_text_resultset_column_definition.html
 */
class Column
{
    /**
     * @param string $catalog
     * @param string $schema
     * @param string $table
     * @param string $orgTable
     * @param string $name
     * @param string $orgName
     * @param int    $lengthOfFixedLengthFields
     * @param int    $characterSet
     * @param int    $columnLength
     * @param int    $type
     * @param int    $flags
     * @param int    $decimals
     */
    public function __construct(
        public readonly string $catalog,
        public readonly string $schema,
        public readonly string $table,
        public readonly string $orgTable,
        public readonly string $name,
        public readonly string $orgName,
        public readonly int    $lengthOfFixedLengthFields,
        public readonly int    $characterSet,
        public readonly int    $columnLength,
        public readonly int    $type,
        public readonly int    $flags,
        public readonly int    $decimals
    ) {
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
            0x01, 0x03, 0x08, 0x09 => intval($item),
            0x05, 0x0a, 0x0b       => floatval($item),
            default                => $item,
        };
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
