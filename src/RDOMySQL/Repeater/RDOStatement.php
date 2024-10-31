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

namespace Ripple\RDOMySQL\Repeater;

use PDO;
use PDOStatement;
use Ripple\RDOMySQL\Data\Heap\Statement;
use Ripple\RDOMySQL\Data\ResultSet;
use Ripple\RDOMySQL\Exception\Exception;

use function array_values;

class RDOStatement extends PDOStatement
{
    /*** @var \Ripple\RDOMySQL\Data\ResultSet */
    private ResultSet $resultSet;

    /**
     * @param \Ripple\RDOMySQL\Data\Heap\Statement|null $rippleStatement
     * @param array                                           $params
     */
    public function __construct(protected Statement|null $rippleStatement = null, protected array $params = [])
    {
    }

    /**
     * @param int|string $column
     *
     * @return mixed
     */
    public function fetchColumn(int|string $column = 0): mixed
    {
        return $this->resultSet->fetchColumn($column);
    }

    /**
     * @return array|false
     */
    public function fetchNum(): array|false
    {
        return $this->resultSet->fetchNum();
    }

    /**
     * @param     $mode
     * @param     $cursorOrientation
     * @param int $cursorOffset
     *
     * @return array|false
     */
    public function fetch($mode = PDO::FETCH_DEFAULT, $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): array|false
    {
        return $this->resultSet->fetch();
    }

    /**
     * @param int        $mode
     * @param mixed|null $fetch_argument
     * @param mixed      ...$args
     *
     * @return array
     */
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed $fetch_argument = null, mixed ...$args): array
    {
        return $this->resultSet->fetchAll();
    }

    /**
     * @param array|null $params
     *
     * @return bool
     */
    public function execute(array $params = null): bool
    {
        if (!isset($this->rippleStatement)) {
            return false;
        }

        foreach ($params ?? [] as $key => $value) {
            $this->bindValue($key, $value);
        }

        try {
            $this->resultSet = $this->rippleStatement->execute(array_values($this->params));
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * @param int|string $param
     * @param mixed      $value
     * @param int        $type
     *
     * @return bool
     */
    public function bindValue(int|string $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->params[$param] = $value;
        return true;
    }

    /**
     * @param int|string $param
     * @param mixed      $var
     * @param int        $type
     * @param int        $maxLength
     * @param mixed|null $driverOptions
     *
     * @return bool
     */
    public function bindParam(int|string $param, mixed &$var, int $type = PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        $this->params[$param] = &$var;
        return true;
    }

    /**
     * @return int
     */
    public function rowCount(): int
    {
        return $this->resultSet->rowCount();
    }

    /**
     * @param \Ripple\RDOMySQL\Data\ResultSet $resultSet
     *
     * @return PDOStatement
     */
    public function complete(ResultSet $resultSet): PDOStatement
    {
        $this->resultSet = $resultSet;
        return $this;
    }
}
