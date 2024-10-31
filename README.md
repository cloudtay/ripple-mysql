### 项目简介

基于`PHP`自举的`PDO`驱动, 目前仅支持`MySQL`数据库。

### 兼容版本

> 兼容版本指的是在该版本下测试通过的版本号，不代表其他版本不可用

|  数据库  | 版本号 |
|:-----:|:---:|
| MySQL | 8+  |
|  ...  | ... |

### 快速安装

```php
composer require cloudtay/ripple-rdo
```

### 基础用法

> 就像使用`PDO`一样使用`RDO`驱动

```php
include __DIR__ . '/../vendor/autoload.php';

/**
 * @var \PDO $rdo 已继承不冲突
 */
$rdo = new \Ripple\RDO(
    'mysql:host=127.0.0.1;port=3306;dbname=hello_world;charset=utf8',
    'benchmarkdbuser',
    'benchmarkdbpass',
);

echo 'connection established' , \PHP_EOL;

$statement    = $rdo->prepare("UPDATE `World` SET `randomNumber` = ? WHERE `id` = 1;");
$statement->execute([':randomNumber' => \rand(1, 10000)]);

echo 'update executed ' , $statement->rowCount() , \PHP_EOL;
```

### 异步用法

```php
include __DIR__ . '/../vendor/autoload.php';

\Co\async(static function(){
    echo 'coroutine 1 start ' , microtime(true) , \PHP_EOL;
    $rdo1 = new \Ripple\RDO(
        'mysql:host=127.0.0.1;port=3306;dbname=hello_world;charset=utf8',
        'benchmarkdbuser',
        'benchmarkdbpass',
    );
    
    //模拟耗时一秒查询
    $rdo1->query("SELECT SLEEP(1);");
    
    echo 'coroutine 1 end ' , microtime(true) , \PHP_EOL;
});


\Co\async(static function(){
    echo 'coroutine 2 start ' , microtime(true) , \PHP_EOL;
    $rdo2 = new \Ripple\RDO(
        'mysql:host=127.0.0.1;port=3306;dbname=hello_world;charset=utf8',
        'benchmarkdbuser',
        'benchmarkdbpass',
    );
    
    //模拟耗时一秒查询
    $rdo1->query("SELECT SLEEP(1);");
    
    echo 'coroutine 2 end ' , microtime(true) , \PHP_EOL;
});


\Co\wait();
```

### 附录

> 上述例子中的数据库一键部署

```bash
docker run -d --name tfb-database -p 3306:3306 techempower/mysql:latest
```
