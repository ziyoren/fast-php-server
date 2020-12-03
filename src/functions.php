<?php

declare(strict_types=1);

namespace ziyoren;


if (!function_exists('getInstance')) {
    function getInstance($class)
    {
        return ($class)::getInstance();
    }
}
if (!function_exists('config')) {
    function config($name, $default = null)
    {
        return getInstance('\ziyoren\config')->get($name, $default);
    }
}

if (!function_exists('dump')) {
    function dump($message, string $title = '', $level = 0)
    {
        $debugInfo = debug_backtrace();
//        var_export($debugInfo);
//        echo PHP_EOL;
        $file = $debugInfo[0]['file'];
        $line = $debugInfo[0]['line'];
        echo ziyo_get_level($level);
        if (!empty($title)) {
            echo $title, "\t";
        }
        if (is_string($message)) {
            echo $message, "\t";
        }
        if (!is_null($level)) {
            echo $file, ' 第', $line, '行';
        }
        echo PHP_EOL;
        if (is_array($message)) {
            var_export($message);
            echo PHP_EOL;
        }
    }
}

if (!function_exists('ziyo_get_level')){
    function ziyo_get_level($index=0){
        if (is_null($index)) return '';
        $level = ['[INFO]', '[DEBUG]', '[NOTICE]', '[ERROR]', '[WARNING]'];
        return ($level[$index] ?? $level[0]) . ' ';
    }
}


if (!function_exists('routeDispatch')) {
    function routeDispatch($request, $response)
    {
        $uri = $request->server['request_uri'];
        $httpMethod = $request->server['request_method'];

        if ($uri == '/favicon.ico') {
            $response->end();
            return false;
        }

        $dispatcher = require(CONF_PATH . 'router.php');
        $uri = rawurldecode($uri);
        $routeInfo = $dispatcher->dispatch($httpMethod, $uri);

        // dump($routeInfo, '路由信息');

        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                // ... 404 Not Found
                $response->status(404);
                $result = return404($uri);
                break;
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                // ... 405 Method Not Allowed
                $response->status(405);
                $result = return405($httpMethod, $uri, $routeInfo);
                break;
            case \FastRoute\Dispatcher::FOUND:
                $result = routeCallable($routeInfo, $request, $response);
                $code = isset($result['code']) ? $result['code'] : 200;
                $response->status($code);
                break;
        }

        if (gettype($result) != 'string') {
            $response->header("Content-Type", "application/json;charset=utf-8");
            $result = json_encode($result, 256);
        }
        $response->end($result);
    }
}

if (!function_exists('routeCallable')) {
    function routeCallable($routeInfo, $request, $response)
    {
        $handler = $routeInfo[1];
        // dump( gettype($handler), '路由方法的类型' );
        if ('object' == gettype($handler)) {
            return $handler();
        }

        $handler = str_ireplace('@', '::', $handler);
        $handler = explode('::', $handler);
        // dump($handler, '路由解析结果');

        $cls = null;
        $controller = $handler[0];
        $action = isset($handler[1]) ? $handler[1] : null;
        if ($action) {
            try {
                $classExists = class_exists($controller);
                if (!$classExists) {
                    return ['code' => 500, 'message' => $controller . ' not found.'];
                }
                $cls = new $controller($request, $response);
                $methodExists = method_exists($cls, $action);
            } catch (\Throwable $e) {
                $result = [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                ];
                //  dump($result, '控制器调用失败！');
                return $result;
            }
        } else {
            $methodExists = function_exists($controller);
        }

        if (!$methodExists) {
            return ['code' => 500, 'message' => 'Method [' . $handler . '] does not exist.'];
        }

        $func = $cls ? [$cls, $action] : $controller;
        if (isset($routeInfo[2])) {
            return call_user_func_array($func, $routeInfo[2]);
        } else {
            return call_user_func_array($func);
        }

    }
}

if (!function_exists('return404')) {
    function return404($uri)
    {
        return [
            'code' => 404,
            'message' => '未定义的路由地址。',
            'uri' => $uri,
        ];
    }
}

if (!function_exists('return405')) {
    function return405($httpMethod, $uri, $routeInfo)
    {
        $allowedMethods = $routeInfo[1];
        return [
            'code' => 405,
            'message' => '本路由不支持“' . $httpMethod . '”请求，请使用：' . implode('或', $allowedMethods),
            'uri' => $uri,
            'allowedMethods' => $allowedMethods,
        ];
    }
}


if (!function_exists('ziyoHeaderCallable')) {
    function ziyoHeaderCallable($request, $response)
    {
        $header = $request->header;
        $get = $request->get;
        $handle = $header['Controller'] ?? $get['_c'];
        $action = $header['Action'] ?? $get['_a'] ?? 'index';
        $handle = 'App\\Controller\\' . ltrim($handle, 'App\\Controller\\');

        try {
            $result = ziyoCallable($handle, $action, $request, $response);
        } catch (\Throwable $e) {
            $result = [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ];
            dump($result, '动态调用控制器失败！');
        }
        $result = is_string($result) ? $result : json_encode($result, 256);
        $response->header("Content-Type", "application/json;charset=utf-8");
        $response->end($result);
    }
}

if (!function_exists('ziyoCallable')) {
    function ziyoCallable($handle, $action, $request, $response)
    {
        $classExists = class_exists($handle);
        if (!$classExists) {
            return ['code' => 500, 'message' => $handle . ' not found.'];
        }

        $methodExists = method_exists($handle, $action);
        if (!$methodExists) {
            return ['code' => 500, 'message' => 'Call to undefined method ' . $handle . '::' . $action . '().'];
        }

        try {
            $handler = new $handle($request, $response);
            return $handler->$action();
        } catch (\Throwable $e) {
            $result = [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ];
            dump($result, '控制器调用失败！');
            return $result;
        }
    }
}