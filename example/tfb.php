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

include __DIR__ . '/../vendor/autoload.php';

$rdo = new \Ripple\RDO(
    'mysql:host=127.0.0.1;port=3306;dbname=hello_world;charset=utf8',
    'benchmarkdbuser',
    'benchmarkdbpass',
);

echo 'connection established' , \PHP_EOL;

$statement    = $rdo->prepare("UPDATE `World` SET `randomNumber` = ? WHERE `id` = 1;");
$statement->execute([':randomNumber' => \rand(1, 10000)]);
echo 'update executed ' , $statement->rowCount() , \PHP_EOL;



\Co\async(static function () {
    echo 'coroutine 1 start ' , \microtime(true) , \PHP_EOL;
    $rdo1 = new \Ripple\RDO(
        'mysql:host=127.0.0.1;port=3306;dbname=hello_world;charset=utf8',
        'benchmarkdbuser',
        'benchmarkdbpass',
    );

    //模拟耗时一秒查询
    $rdo1->query("SELECT SLEEP(1);");

    echo 'coroutine 1 end ' , \microtime(true) , \PHP_EOL;
});


\Co\async(static function () {
    echo 'coroutine 2 start ' , \microtime(true) , \PHP_EOL;
    $rdo2 = new \Ripple\RDO(
        'mysql:host=127.0.0.1;port=3306;dbname=hello_world;charset=utf8',
        'benchmarkdbuser',
        'benchmarkdbpass',
    );

    //模拟耗时一秒查询
    $rdo2->query("SELECT SLEEP(1);");

    echo 'coroutine 2 end ' , \microtime(true) , \PHP_EOL;
});


\Co\wait();
