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

use Ripple\Promise;
use Ripple\RDO;

use function Co\async;
use function Co\wait;

include __DIR__ . '/../vendor/autoload.php';

$time = \microtime(true);
$futures = [];
for ($i = 0; $i < 10; $i++) {
    $futures[] = async(function () {
        $rdo       = new RDO(
            'mysql:host=127.0.0.1;port=3306;dbname=fnc;charset=utf8mb4',
            'root',
            '123456'
        );
        $statement = $rdo->prepare("select * from `iot_area_cn_province` where `code` > :code LIMIT 2;");

        for ($i = 0; $i < 1000; $i++) {
            $statement->execute([':code' => 700000]);
        }
    });
}

Promise::all($futures)->then(static function () use ($time) {
    echo 'RDO: ' , (\microtime(true) - $time) , \PHP_EOL;
})->except(static function (\Throwable $e) {
    echo $e->getMessage() , \PHP_EOL;
});

wait();
