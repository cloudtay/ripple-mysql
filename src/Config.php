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

use InvalidArgumentException;

use function intval;
use function parse_str;
use function str_replace;
use function strval;

readonly class Config
{
    public function __construct(
        public string $host,
        public int    $port,
        public string $user,
        public string $password,
        public string $database,
        public string $charset
    ) {
    }

    /**
     * @param array $config
     *
     * @return self
     */
    public static function fromArray(array $config): Config
    {
        if (!isset($config['host'], $config['port'])) {
            throw new InvalidArgumentException('Host and port must be set in the configuration.');
        }

        if (!isset($config['user'], $config['password'])) {
            throw new InvalidArgumentException('User and password must be set in the configuration.');
        }

        if (!isset($config['database']) && !isset($config['dbname'])) {
            throw new InvalidArgumentException('Database must be set in the configuration.');
        }

        return new Config(
            $config['host'],
            $config['port'],
            $config['user'],
            $config['password'],
            $config['database'] ?? $config['dbname'],
            $config['charset'] ?? 'utf8mb4'
        );
    }

    /**
     * @param string $config
     * @param string $username
     * @param string $password
     *
     * @return \Ripple\Config
     */
    public static function formString(string $config, string $username, string $password): Config
    {
        $host    = 'localhost';
        $port    = 3306;
        $dbname  = '';
        $charset = 'utf8mb4';

        parse_str(str_replace(['mysql:', ';'], ['', '&'], $config), $params);

        if (isset($params['host'])) {
            $host = $params['host'];
        }
        if (isset($params['port'])) {
            $port = $params['port'];
        }

        if (isset($params['dbname'])) {
            $dbname = $params['dbname'];
        } elseif (isset($params['database'])) {
            $dbname = $params['database'];
        }

        if (isset($params['charset'])) {
            $charset = $params['charset'];
        }

        return new Config(
            strval($host),
            intval($port),
            $username,
            $password,
            strval($dbname),
            strval($charset),
        );
    }
}
