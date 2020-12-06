<?php
declare(strict_types=1);

namespace ziyoren\Http;

use Co\Http\Server as HttpServer;
use RuntimeException;
use function ziyoren\config as config;
use function ziyoren\dump as dump;
use function ziyoren\routeDispatch as routeDispatch;
use function ziyoren\ziyoHeaderCallable as ziyoHeaderCallable;

class server
{
    private $server;
    private $host;
    private $port;

    public function __construct()
    {
        $config = config('http-server');
        if (!$config){
            throw new RuntimeException('请检查HTTP服务的配置文件：/config/http-server.php');
        }
        $this->host = $config['host'];
        $this->port = $config['port'];
        $this->server = new HttpServer($this->host, $this->port, false);

        $this->setHandle();

        return $this;
    }

    public function run()
    {
        dump('Http server started. http://' . $this->host . ':' . $this->port, '', null);
        $this->server->start();
    }

    public function stop(){
        dump('Http server stop. 开发中...', '', null);
    }


    private function setHandle()
    {
        $this->server->handle('/', function ($request, $response) {
            dump(PHP_EOL . '[' . date('Y-m-d H:i:s') . '] ' . $this->getRequestLogInfo($request), '', null);
            $handle = $this->getHandler($request);
            if ($handle) {
                $this->routeForHeader($request, $response);
            } else {
                $this->routeForFastRoute($request, $response);
            }
        });
    }

    private function getRequestLogInfo($request)
    {
        $qs = isset($request->server['query_string']) ? '?' . $request->server['query_string'] : '';
        return join("\t", [
            $request->server['remote_addr'],
            $request->server['request_method'],
            $request->server['request_uri'] . $qs,
        ]);
    }

    private function getHandler($request)
    {
        $header = $request->header;
        $get = $request->get;
        $handle = isset($header['Controller']) ?
            $header['Controller'] :
            (isset($get['_c']) ? $get['_c'] : null);
        return $handle;
    }

    private function routeForHeader($request, $response)
    {
        go(function () use ($request, $response) {
            ziyoHeaderCallable($request, $response);
        });
    }

    private function routeForFastRoute($request, $response)
    {
        go(function () use ($request, $response) {
            routeDispatch($request, $response);
        });
    }

}