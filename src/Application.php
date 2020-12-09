<?php
declare(strict_types=1);

namespace ziyoren;

use ziyoren\Http\Fpm;
use function ziyoren\dump as dump;

class Application
{
    protected static $version = '0.0.3';

    protected static $serverName = 'http';

    protected static $action;

    protected static $server = [
        'http' => \ziyoren\Http\server::class,
        //'tcp' => \ziyoren\Tcp\server::class,
    ];

    private static function parseArgv()
    {
        global $argv;
        $params = array_slice($argv, 1);
        if (count($params) != 2) {
            return false;
        } else {
            if (self::verifyArgv($params)) {
                self::$serverName = $params[0];
                self::$action = $params[1];
                return true;
            } else {
                return false;
            }
        }
    }

    private static function verifyArgv($params)
    {
        $isServer = in_array($params[0], ['http', 'tcp', 'ws']);
        $isAction = in_array($params[1], ['start', 'restart', 'stop']);
        return $isAction && $isServer;
    }


    public static function welcome()
    {
        $version = self::$version;
        $swooleVer = SWOOLE_VERSION;
        $phpVersion = PHP_VERSION;
        echo <<<EOL
  ____  _           ___  _____  __
 /_  / (_)_ _____  / _ \/ __/ |/ /
  / /_/ / // / _ \/ , _/ _//    / 
 /___/_/\_, /\___/_/|_/___/_/|_/  
       /___/          Version:{$version}, Swoole:{$swooleVer}, PHP:{$phpVersion}    


EOL;
    }

    private static function help()
    {
        global $argv;
        echo <<<HELP
Usage: 
  1. ./{$argv[0]} serverName action
  2. php {$argv[0]} serverName action
  
  serverName: http|tcp|ws
  action: start|restart|stop

示例
  php bin/server.php http start  # 启动Http服务
  php bin/server.php tcp start   # 启动Tcp服务


HELP;

    }


    public static function run()
    {
        if (true === ZIYOREN_AT_SWOOLE) {
            self::runAtSwoole();
        } else {
            self::runAtFpm();
        }
    }


    private static function runAtSwoole()
    {
        self::welcome();
        if (!self::parseArgv()) {
            self::help();
            return;
        }
        $serverClass = isset(self::$server[self::$serverName]) ? self::$server[self::$serverName] : null;
        if ($serverClass && class_exists($serverClass)) {
            try {
                $app = new $serverClass();
            } catch (\Throwable $e) {
                dump('实例化服务(' . self::$serverName . ')失败！', '', 3);
                dump($e, $e->getMessage(), 3);
                return;
            }
        } else {
            dump('调用的服务(' . self::$serverName . ')不存在！', '', 3);
            return;
        }
        switch (self::$action) {
            case 'start':
                $app->run();
                break;
            case 'restart':
                $app->restart();
                break;
            case 'stop':
                $app->stop();
                break;
        }
    }


    private static function runAtFpm()
    {
        echo '<pre>';
        print_r($_SERVER);
    }
}