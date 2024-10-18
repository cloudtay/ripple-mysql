<?php declare(strict_types=1);

include __DIR__ . '/../vendor/autoload.php';

\var_dump(
    Ripple\App\MySQL\Config::formString('mysql:host=$host;port=3306;dbname=$db;charset=$charset', '123', '123')
);
