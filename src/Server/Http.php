<?php
declare(strict_types=1);

namespace ziyoren\Server;

use Co\Http\Server as HttpServer;
use RuntimeException;
use ziyoren\Http\SwooleServerRequest;
use function ziyoren\config;
use function ziyoren\dump;
use function ziyoren\routeDispatch;
use function ziyoren\ziyoHeaderCallable;
use function ziyoren\ziyoQueryStringCallable;

class Http
{
    const VERSION = '0.0.3';
    private $server;
    private $host;
    private $port;
    private static $route;
    private $config = [
        'host' => '127.0.0.1',
        'port' => 9850,
    ];

    public function __construct()
    {

        $config = config('http-server');  //调用框架配置

        $config = array_replace_recursive($this->config, is_array($config) ? $config : []);

        $this->config = $config;

        $this->host = $config['host'];

        $this->port = $config['port'];

        $this->server = new HttpServer($this->host, $this->port, false);

        $this->setHandle();

        $this->routeDispatcher();

        return $this;
    }


    public function run()
    {
        $this->runMessage();
        $this->server->start();
    }

    private function runMessage()
    {
        echo 'Http server started. ', PHP_EOL;

        if ($this->host == '0.0.0.0') {
            $ip = swoole_get_local_ip();
            $ip[] = '127.0.0.1';
        }else{
            $ip = [ $this->host ];
        }

        foreach ($ip as $host) {
            echo '  http://' . $host . ':' . $this->port, PHP_EOL;
        }
    }


    public function stop(){
        dump('Http server stop. 开发中...', '', null);
    }


    private function routeDispatcher()
    {
        if ( !self::$route ) {
            $routeConfig = CONF_PATH . 'router.php';
            if ( file_exists($routeConfig) ) {
                self::$route = require($routeConfig);
            } else {
                throw new RuntimeException('没有找到路由配置文件（'. $routeConfig . '）');
            }
            echo 'Route dispatcher initialization completed.', PHP_EOL;
        }

        return self::$route;
    }


    private function setHandle()
    {
        $this->server->handle('/', function ($request, $response) {

            $request = SwooleServerRequest::getInstance($request);

            //$response = SwooleServerResponse::getInstance($response, $request->fd);

            $response->header('X-Powered-By', 'ZiyoREN/' . self::VERSION);

            $response->header('X-Swoole', 'Swoole/' . SWOOLE_VERSION);

            $response->header('X-PHP', 'PHP/'. PHP_VERSION);

            dump(PHP_EOL . '[' . date('Y-m-d H:i:s') . '] ' . $this->getRequestLogInfo($request), '', null);

            $this->routeForFastRoute($request, $response, self::$route );
        });
    }


    private function getRequestLogInfo($request): string
    {
        $qs = isset($request->server['query_string']) ? '?' . $request->server['query_string'] : '';
        return join(' ', [
            $request->server['remote_addr'],
            $request->server['request_method'],
            $request->server['request_uri'] . $qs,
        ]);
    }


    private function getHandler($request)
    {
        $header = $request->header;
        $get = $request->get;
        return isset($header['controller']) ?
            $header['controller'] :
            (isset($get['_c']) ? $get['_c'] : null);
    }


    private function routeForHeader($request, $response, $dispatch = null)
    {
        go(function () use ($request, $response) {
            ziyoHeaderCallable($request, $response);
        });
    }


    private function routeForQuerystring($request, $response, $dispatch = null)
    {
        go(function () use ($request, $response) {
            ziyoQueryStringCallable($request, $response);
        });
    }


    private function routeForFastRoute($request, $response, $dispatch = null)
    {
        go(function () use ($request, $response, $dispatch) {
            routeDispatch($request, $response, $dispatch);
        });
    }

}