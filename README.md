### Plan

* ✅ 已支持 ~~暂不支持事务~~
* ✅ 已支持 ~~暂不支持单次传输大于16M的数据~~
* ✅ 已实现`prepare`模式, 待实现MySQL原生`prepare`,`fetch`模式
* ✅ 已实现 ~~暂不支持超时约束~~
* 即将支持SSL交互模式
* 即将实现PDO接口

### Example

```php
use Ripple\App\MySQL\Connection;

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
```
