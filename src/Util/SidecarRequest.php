<?php


namespace Sidecar\Util;

use Sidecar\Constant\SidecarConstant;
use Sidecar\Exception\SidecarException;
use Sidecar\Http\HttpClient;

class SidecarRequest
{
    /**
     * @param $appName
     * @param $uri
     * @param string $method
     * @param array $data
     * @param array $option
     * @param bool $isRaw
     * @return array|bool|\Swlib\Saber\Request|\Swlib\Saber\Response
     * @throws SidecarException
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public static function call($appName, $uri, $method = 'GET', $data = [], $option = [], $isRaw = false)
    {
        $instance = self::getUsableInstance($appName);
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

        $option['base_uri'] = (strpos($host, 'http') !== false) ? $host . ':' . $port : $schema . $host . ':' . $port;
        $option['method'] = strtoupper($method);
        if ('GET' == $option['method']) {
            if (!empty($data)) {
                $uri .= '?' . http_build_query($data);
                $data = [];
            }
        }
        $option['uri'] = $uri;
        $data && $option['data'] = json_encode($data);
        $http = HttpClient::getInstance();
        $result = $http->request(null, $option, false, $isRaw);
        return $result;
    }

    /**
     * 获取可用实例，没有则返回false
     * @param $name
     * @return bool|mixed
     */
    private static function getUsableInstance($name)
    {
        $table = SidecarTable::getInstance();
        $instanceKey = SidecarConstant::APP_PREFIX . md5(strtoupper($name));
        $app = json_decode($table->get($instanceKey, SidecarConstant::SIDECAR_INFO), true);
        if (empty($app)) {
            return false;
        }
        if (isset($app['status']) && 'UP' == $app['status']) {
            return $app;
        }
        shuffle($app);
        foreach ($app as $instance) {
            if ('UP' == $instance['status']) {
                return $instance;
            }
        }
        return false;
    }
}
