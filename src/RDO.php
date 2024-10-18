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

namespace Ripple;

use PDOException;
use PDOStatement;
use Ripple\PDORepeater\PDORepeater;
use Ripple\PDORepeater\RDOStatement;
use Ripple\RDOMySQL\Connection;
use Ripple\RDOMySQL\Exception\Exception;

use function preg_match_all;
use function str_replace;

class RDO extends PDORepeater
{
    /*** @var \Ripple\RDOMySQL\Connection */
    protected Connection $connection;

    /**
     * @param string $dsn
     * @param string $username
     * @param string $passwd
     * @param array  $options
     *
     */
    public function __construct(string $dsn, string $username = '', string $passwd = '', array $options = [])
    {
        try {
            $this->connection = new Connection(
                Config::formString($dsn, $username, $passwd),
                $username,
                $passwd,
                $options
            );
        } catch (Exception $e) {
            throw new PDOException($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param string   $query
     * @param int|null $fetchMode
     * @param          ...$fetch_mode_args
     *
     * @return RDOStatement
     */
    public function query(string $query, ?int $fetchMode = null, ...$fetch_mode_args): PDOStatement
    {
        try {
            $resultSet = $this->connection->query($query);
        } catch (Exception $e) {
            throw new PDOException($e->getMessage(), $e->getCode());
        }
        return (new RDOStatement())->complete($resultSet);
    }

    /**
     * @param string $query
     * @param array  $options
     *
     * @return RDOStatement|false
     */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        try {
            preg_match_all('/:\w+/', $query, $matches);
            $params = [];
            foreach ($matches[0] ?? [] as $item) {
                $params[$item] = null;
                $query = str_replace($item, '?', $query);
            }
            $statement = $this->connection->prepare($query);
            return new RDOStatement($statement, $params);
        } catch (Exception $e) {
            throw new PDOException($e->getMessage(), $e->getCode());
        }
    }
}
