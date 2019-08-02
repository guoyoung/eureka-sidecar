<?php


namespace Sidecar\Util;

use Sidecar\Constant\SidecarConstant;
use Sidecar\Exception\SidecarException;
use Sidecar\Http\HttpClient;
use Sidecar\Http\Response;
use Swoft\Bean\BeanFactory;

class SidecarRequest
{
    /**
     * @var array 
     */
    private $allowMethod = [
        'get',
        'head',
        'delete',
        'put',
        'patch',
        'post',
        'options'
    ];

    /**
     * @var SidecarRequest
     */
    private static $instance = null;

    private function __clone(){}

    private function __construct(){}

    /**
     * @return SidecarRequest
     */
    public static function getInstance()
    {
        self::$instance || self::$instance = new self();
        return self::$instance;
    }

    /**
     * @param $appName
     * @param $uri
     * @param string $method
     * @param array $data
     * @param array $option
     * @return Response
     * @throws SidecarException
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function call($appName, $uri, $method = 'GET', $data = [], $option = [])
    {
        if (!config('sidecar.enable', false)) {
            throw new SidecarException('eureka is unabled');
        }
        $instance = $this->getUsableInstance($appName);
        if (false == $instance) {
            throw new SidecarException('no usable instance');
        }
        if (isset($instance['port']['@enabled']) && $instance['port']['@enabled']) {
            $port = $instance['port']['$'];
            $schema = 'http://';
        } elseif (isset($instance['securePort']['@enabled']) && $instance['securePort']['@enabled']) {
            $port = $instance['securePort']['$'];
            $schema = 'https://';
        } else {
            $port = 80;
            $schema = 'http://';
        }

        if (isset($instance['ipAddr']) && $instance['ipAddr']) {
            $host = $instance['ipAddr'];
        } else {
            $host = $instance['hostName'] ?? '';
        }
        if (empty($host)) {
            throw new SidecarException('instance host error');
        }

        $option['base_uri'] = $host;
        $option['port'] = $port;
        $method = strtolower($method);
        if (!in_array($method, $this->allowMethod)) {
            throw new SidecarException('method not allowed');
        }
        if ('get' == $method) {
            if (!empty($data)) {
                $option['query'] = $data;
                $data = [];
            }
        }
        $data && $option['body'] = json_encode($data);
        /**
         * @var $http HttpClient
         */
        $http = BeanFactory::getBean('eurekaHttpClient');
        /**
         * @var $result Response
         */
        $result = $http->$method($uri, $option);
        return $result;
    }

    /**
     * 获取可用实例，没有则返回false
     * @param $name
     * @return bool|mixed
     */
    private function getUsableInstance($name)
    {
        $table = SidecarTable::getInstance();
        $instanceKey = SidecarConstant::SIDECAR_KEYS_PREFIX . md5(strtoupper($name));
        $app = json_decode($table->get($instanceKey, SidecarConstant::SIDECAR_INFO), true);
        if (empty($app)) {
            return false;
        }
        if (isset($app['status']) && 'UP' == $app['status']) {
            return $app;
        }
        shuffle($app);
        foreach ($app as $instance) {
            $status = $instance['status'] ?? 'DOWN';
            if ('UP' == $status) {
                return $instance;
            }
        }
        return false;
    }
}
