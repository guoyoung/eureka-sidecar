<?php

namespace Sidecar\Contract;

interface AgentInterface
{
    /**
     * 获取当前实例信息
     * @return mixed
     */
    public function info();

    /**
     * 获取所有实例信息
     * @return mixed
     */
    public function applications();

    /**
     * @param $appName          服务名
     * @param $uri              uri
     * @param string $method    请求方式
     * @param array $data       请求数据
     * @param array $option     option配置，同saber
     * @param bool $isRaw       是否返回原始saber请求数据
     * @return mixed
     */
    public function proxy($appName, $uri, $method = 'GET', $data = [], $option = [], $isRaw = false);
}
