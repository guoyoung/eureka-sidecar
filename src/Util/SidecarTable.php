<?php


namespace Sidecar\Util;


use Swoole\Table;

/**
 * 用于保存相关实例信息
 * Class SidecarTable
 */
class SidecarTable
{
    private static $instance = null;

    private function __construct(){}

    private function __clone(){}

    /**
     * @return Table|null
     */
    public static function getInstance()
    {
        self::$instance || self::$instance = new Table(config('sidecar.sidecarTable', 1024));
        return self::$instance;
    }
}
