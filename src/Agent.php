<?php


namespace Sidecar;


use Sidecar\Constant\SidecarConstant;
use Sidecar\Contract\AgentInterface;
use Sidecar\Util\SidecarRequest;
use Sidecar\Util\SidecarTable;
use Swoft\Bean\Annotation\Mapping\Bean;
use Swoft\Bean\BeanFactory;

/**
 * Class Agent
 * @package Sidecar
 * @Bean("agent")
 */
class Agent implements AgentInterface
{
    /**
     * @return array
     */
    public function applications()
    {
        $table = SidecarTable::getInstance();
        $keys = json_decode($table->get(SidecarConstant::SIDECAR_KEYS, SidecarConstant::SIDECAR_INFO), true);
        $keys = $keys ?: [];
        $instances = [];
        foreach ($keys as $instanceKey) {
            $instance = json_decode($table->get($instanceKey, SidecarConstant::SIDECAR_INFO), true);
            $instance = $instance ?: [];
            $appName = $instance['app'] ?? $instance[0]['app'];
            $appName && $instances[$appName] = $instance;
        }
        return $instances;
    }

    /**
     * @param $appName
     * @param $uri
     * @param string $method
     * @param array $data
     * @param array $option
     * @return mixed|Http\Response
     * @throws Exception\SidecarException
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function proxy($appName, $uri, $method = 'GET', $data = [], $option = [])
    {
        return SidecarRequest::getInstance()->call($appName, $uri, $method, $data, $option);
    }
}
