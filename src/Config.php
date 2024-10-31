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

use function array_merge;
use function intval;
use function parse_str;
use function str_replace;
use function strpos;
use function substr;

class Config
{
    public function __construct(
        public readonly string $driver,
        public readonly string $host,
        public readonly int    $port,
        public readonly string $user,
        public readonly string $password,
        public readonly string $database,
        public readonly string $charset
    ) {
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
        return Config::fromArray(array_merge(
            Config::parseDsn($config),
            ['user' => $username, 'password' => $password]
        ));
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

        if (!isset($config['driver'])) {
            throw new InvalidArgumentException('Driver must be set in the configuration.');
        }

        return new Config(
            $config['driver'],
            $config['host'],
            intval($config['port']),
            $config['user'],
            $config['password'],
            $config['database'] ?? $config['dbname'],
            $config['charset'] ?? 'utf8mb4'
        );
    }

    /**
     * @param string $dsn
     *
     * @return array
     */
    private static function parseDsn(string $dsn): array
    {
        if (!$driverEndPos = strpos($dsn, ':')) {
            throw new InvalidArgumentException('Invalid DSN.');
        }

        $driver    = substr($dsn, 0, $driverEndPos);
        $dsnParams = substr($dsn, $driverEndPos + 1);
        $config    = ['driver' => $driver];
        parse_str(str_replace(';', '&', $dsnParams), $params);
        return array_merge($config, $params);
    }
}
