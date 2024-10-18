### README

暂不支持事务  
暂不支持单次传输大于16M的数据

### Example

```php
use Ripple\App\MySQL\Connection;
use function Co\async;

class Setup
{
    public static Connection $connection;
}

Setup::$connection = new Connection([
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'user'     => 'root',
    'password' => '123456',
    'database' => 'fnc'
]);


async(function () {
    echo "Connected to MySQL server", \microtime(true), \PHP_EOL;
    try {
        $statement = Setup::$connection->prepare('123;');
        $statement->execute(['id' => 10000]);
        \var_dump($statement->fetchAll());
    } catch (Throwable $exception) {
        echo $exception->getMessage();
    }
});

async(function () {
    echo "Connected to MySQL server", \microtime(true), \PHP_EOL;
    try {
        $statement = Setup::$connection->prepare('select SLEEP(1);');
        $statement->execute(['id' => 10000]);
        \var_dump($statement->fetchAll());
    } catch (Throwable $exception) {
        echo $exception->getMessage(), \PHP_EOL;
    }
});

\Co\wait();
```
