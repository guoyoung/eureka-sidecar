<?php


namespace Sidecar;


use Sidecar\Constant\SidecarConstant;
use Sidecar\Contract\AgentInterface;
use Sidecar\Util\SidecarRequest;
use Sidecar\Util\SidecarTable;
use Swoft\Bean\BeanFactory;
use Swoft\Context\Context;
use Swoft\Http\Message\ContentType;
use Swoft\Http\Server\Annotation\Mapping\Controller;
use Swoft\Http\Server\Annotation\Mapping\RequestMapping;
use Swoft\Http\Server\Annotation\Mapping\RequestMethod;

/**
 * Class Agent
 * @package Sidecar
 */
class Agent implements AgentInterface
{
    /**
     * @return array
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function info()
    {
        /**
         * @var $bean Sidecar
         */
        $bean = BeanFactory::getBean('sidecar');
        $appInstance = $bean->getInstance();
        return [
            'hostName' => $appInstance['instance']['hostName'] ?? '',
            'app' => $appInstance['instance']['app'] ?? '',
            'ipAddr' => $appInstance['instance']['ipAddr'] ?? '',
            'status' => $appInstance['instance']['status'] ?? '',
            'port' => $appInstance['instance']['port']['$'] ?? '',
            'version' => SidecarConstant::SIDECAR_VERSION,
        ];
    }

    /**
     * @return array
     */
    public function applications()
    {
        $table = SidecarTable::getInstance();
        $keys = json_decode($table->get(SidecarConstant::EUREKA_APP_KEYS, SidecarConstant::SIDECAR_INFO), true);
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
     * 代理，服务转发
     * @param $appName
     * @param $uri
     * @param string $method
     * @param array $data
     * @param array $option
     * @param bool $isRaw
     * @return array|bool|\Swlib\Saber\Request|\Swlib\Saber\Response
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function proxy($appName, $uri, $method = 'GET', $data = [], $option = [], $isRaw = false)
    {
        return SidecarRequest::call($appName, $uri, $method = 'GET', $data = [], $option = [], $isRaw = false);
    }
}
