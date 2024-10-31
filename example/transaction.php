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

use Ripple\RDO;

include __DIR__ . '/../vendor/autoload.php';

$rdo = new RDO(
    'mysql:host=127.0.0.1;port=3306;dbname=hello_world;charset=utf8',
    'benchmarkdbuser',
    'benchmarkdbpass',
);

echo 'connection established', \PHP_EOL;

$row = $rdo->query('SELECT `randomNumber` FROM `World` WHERE `id` = 1;')->fetch();
\var_dump($row);

$rdo->beginTransaction();
$statement = $rdo->prepare("UPDATE `World` SET `randomNumber` = ? WHERE `id` = 1;");
$statement->execute([':randomNumber' => \rand(1, 10000)]);

echo 'update executed ', $statement->rowCount(), \PHP_EOL;
$row = $rdo->query('SELECT `randomNumber` FROM `World` WHERE `id` = 1;')->fetch();
\var_dump($row);

$rdo->rollBack();

$row = $rdo->query('SELECT `randomNumber` FROM `World` WHERE `id` = 1;')->fetch();
\var_dump($row);
