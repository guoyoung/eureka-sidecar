<?php

namespace Sidecar\Contract;

interface AgentInterface
{
    /**
     * 获取所有实例信息
     * @return mixed
     */
    public function applications();

    /**
     * @param $appName          
     * @param $uri              
     * @param string $method    
     * @param array $data       
     * @param array $option     
     * @return mixed
     */
    public function proxy($appName, $uri, $method = 'GET', $data = [], $option = []);
}
