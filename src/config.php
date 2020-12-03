<?php
declare(strict_types=1);

namespace ziyoren;


class config
{
    private static $instance;

    private static $config = [];

    private function __construct()
    {
    }

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 获取配置
     * @param $keys
     * @param null $default
     * @return mixed|null
     */
    public function get($keys, $default = null)
    {
        $keys = explode('.', strtolower($keys));
        if (empty($keys)) {
            return null;
        }

        $file = array_shift($keys);

        if (empty(self::$config[$file])) {
            $conf = CONF_PATH . $file . '.php';
            if (! is_file($conf)) {
                return null;
            }
            self::$config[$file] = include $conf;
        }
        $config = self::$config[$file];

        while ($keys) {
            $key = array_shift($keys);
            if (! isset($config[$key])) {
                $config = $default;
                break;
            }
            $config = $config[$key];
        }

        return $config;
    }

}