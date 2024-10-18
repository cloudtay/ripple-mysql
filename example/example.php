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

use Ripple\RDOMySQL\Connection;

use function Co\wait;

include __DIR__ . '/../vendor/autoload.php';

$connection = new Connection(
    'mysql:host=127.0.0.1;port=3306;dbname=fnc;charset=utf8mb4',
    'root',
    '123456'
);

echo "Connected to MySQL server", \microtime(true), \PHP_EOL;

try {
    $result = $connection->query("select count(*) from `iot_area_cn_province` where `code` > 710000;");
    \var_dump($result->fetchColumn());

    $statement = $connection->prepare("select * from `iot_area_cn_province` where `code` > ?;");
    $result = $statement->execute([710000]);
    \var_dump($result->fetchAll());
} catch (Throwable $exception) {
    echo $exception->getMessage(), \PHP_EOL;
}

wait();
