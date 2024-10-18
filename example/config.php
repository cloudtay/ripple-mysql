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

\var_dump(
    Ripple\App\MySQL\Config::formString('mysql:host=$host;port=3306;dbname=$db;charset=$charset', '123', '123')
);
