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

use Ripple\App\MySQL\Connection;

include __DIR__ . '/../vendor/autoload.php';

$connection = new Connection(
    'mysql:host=127.0.0.1;port=3306;dbname=fnc;charset=utf8mb4',
    'root',
    '123456'
);

echo "Connected to MySQL server", \microtime(true), \PHP_EOL;

try {
    $connection->beginTransaction();
    $result = $connection->query('update `iot_system_user` set `username` = "ripple";');
    $connection->commit();

    echo 'Affected rows: ', $result->rowCount(), \PHP_EOL;
} catch (Throwable $exception) {
    echo $exception->getMessage(), \PHP_EOL;
}
