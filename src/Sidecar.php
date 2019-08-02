<?php


namespace Sidecar;

use Sidecar\Constant\SidecarConstant;
use Sidecar\Exception\SidecarException;
use Sidecar\Util\SidecarTable;
use Sidecar\Http\HttpClient;
use Swoft\Bean\Annotation\Mapping\Bean;
use Swoft\Bean\BeanFactory;
use Swoft\Http\Message\ContentType;
use Swoft\Log\Helper\CLog;
use Swoft\Log\Helper\Log;
use Swoole\Table;

/**
 * Class Sidecar
 * @package Sidecar
 * @Bean("sidecar")
 */
class Sidecar
{
    /**
     * 参数
     * @var array
     */
    private $agentParams = [];

    /**
     * 默认headers
     * @var array
     */
    private $defaultHeaders = [
        'Accept' => ContentType::JSON,
        'Content-Type' => ContentType::JSON
    ];

    /**
     * @var int
     */
    private $lastDirtyTimestamp = 0;

    /**
     * 实例信息
     * @var array
     */
    private $appInstance = [];

    /**
     * swoole table 存储拉取的实例信息
     * @var Table
     */
    private $sidecarTable = null;

    /**
     * @var HttpClient
     */
    private $httpClient = null;

    /**
     * @var null
     */
    private $instanceId = null;

    /**
     * @var string
     */
    private $instanceStatus = 'UP';

    /**
     * @throws SidecarException
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function init()
    {
        $this->initEurekaParams();
        $this->sidecarTable = SidecarTable::getInstance();
        $this->httpClient = BeanFactory::getBean('eurekaHttpClient');
    }

    /**
     * @throws SidecarException
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    private function initEurekaParams()
    {
        $eurekaUrls = explode(',', config('sidecar.eurekaUrls'));
        if (empty($eurekaUrls[0])) {
            throw new SidecarException('sidecar.eurekaUrls is needed,like: http://127.0.0.1:8671/eureka/,http://127.0.0.1:8672/eureka/');
        }
        foreach ($eurekaUrls as $eurekaUrl) {
            $parseUrl = parse_url($eurekaUrl);
            $scheme = $parseUrl['scheme'] ?? 'http';
            $port = $parseUrl['port'] ?? '';
            $port = $port ?: ('http' == $scheme ? '80' : '443');
            $host = $parseUrl['host'];
            $prefix = rtrim($parseUrl['path'], '/');
            $this->agentParams['sidecar.eurekaUrls'][] = [$host, $prefix, $port];
        }

        if (!config('sidecar.serverPort', '')) {
            throw new SidecarException('sidecar.serverPort is needed');
        }
        $this->agentParams['sidecar.serverPort'] = config('sidecar.serverPort');

        if (!config('sidecar.port', '')) {
            throw new SidecarException('sidecar.port is needed');
        }
        $this->agentParams['sidecar.port'] = config('sidecar.port');

        if (!config('sidecar.applicationName', '')) {
            throw new SidecarException('sidecar.applicationName is needed');
        }
        $this->agentParams['sidecar.applicationName'] = config('sidecar.applicationName');

        $ipAddress = config('sidecar.ipAddress', '');
        if (!$ipAddress) {
            $ips = swoole_get_local_ip();
            $ipAddress = $ips['eth0'] ?? '';
            if (!$ipAddress) {
                $hostKey = str_replace('-', '_', $this->agentParams['sidecar.applicationName']) . '_SERVICE_HOST';
                $ipAddress = getenv(strtoupper($hostKey));
            }
            if (!$ipAddress) {
                throw new SidecarException('get ip failed, sidecar.ipAddress is needed');
            }
        }
        if (false === strpos($ipAddress, 'http://')) {
            $ipAddress = 'http://' . $ipAddress;
        }
        $this->agentParams['sidecar.ipAddress'] = $ipAddress;

        if (!config('sidecar.healthUri', '')) {
            throw new SidecarException('sidecar.healthUri is needed');
        }
        $this->agentParams['sidecar.healthUri'] = config('sidecar.healthUri');

        $this->lastDirtyTimestamp = (string)round(microtime(true) * 1000);

        $this->instanceId = $this->agentParams['sidecar.applicationName'] . ':' .
            substr($this->agentParams['sidecar.ipAddress'], 7) . ':' . $this->agentParams['sidecar.serverPort'];

        $this->appInstance = [
            'instance' =>[
                'instanceId' => $this->instanceId,
                'hostName' => substr($this->agentParams['sidecar.ipAddress'], 7),
                'app' => strtoupper($this->agentParams['sidecar.applicationName']),
                'ipAddr' => substr($this->agentParams['sidecar.ipAddress'], 7),
                'status' => 'UP',
                'overriddenstatus' => 'UNKNOWN',
                'port' => [
                    '$' => $this->agentParams['sidecar.port'],
                    '@enabled' => 'true'
                ],
                'securePort' => [
                    '$' => 443,
                    '@enabled' => 'false'
                ],
                'countryId' => 1,
                'dataCenterInfo' =>[
                    '@class' => 'com.netflix.appinfo.InstanceInfo$DefaultDataCenterInfo',
                    'name' => 'MyOwn'
                ],
                'leaseInfo' => [
                    'renewalIntervalInSecs' => (int)(config('healthTime', 30000) / 1000),
                    'durationInSecs' => 90,
                    'registrationTimestamp' => round(microtime(true) * 1000),
                    'lastRenewalTimestamp' => 0,
                    'evictionTimestamp' => 0,
                    'serviceUpTimestamp' => round(microtime(true) * 1000)
                ],
                'metadata' => [
                    //'@class' => 'java.util.Collections$EmptyMap',
                    "management.port" => $this->agentParams['sidecar.serverPort']
                ],
                'homePageUrl' => $this->agentParams['sidecar.ipAddress'] . ':' . $this->agentParams['sidecar.port'] . '/',
                'statusPageUrl' => $this->agentParams['sidecar.ipAddress'] . ':' . $this->agentParams['sidecar.serverPort'] . config('statusPageUrl', '/agent/info'),
                'healthCheckUrl' => $this->agentParams['sidecar.ipAddress'] . ':' . $this->agentParams['sidecar.port'] . $this->agentParams['sidecar.healthUri'],
                'vipAddress' => $this->agentParams['sidecar.applicationName'],
                'secureVipAddress' => $this->agentParams['sidecar.applicationName'],
                'isCoordinatingDiscoveryServer' => 'false',
                'lastUpdatedTimestamp' => (string)(round(microtime(true) * 1000)),
                'lastDirtyTimestamp' => $this->lastDirtyTimestamp
            ]
        ];
    }

    /**
     * @throws SidecarException
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function registerInstance()
    {
        $this->agentParams['sidecar.enable'] = config('sidecar.enable');
        if (!$this->agentParams['sidecar.enable']) {
            return;
        }

        $headers = array_merge([
            'Accept-Encoding' => 'gzip',
            'DiscoveryIdentity-Name' => 'DefaultClient',
            'DiscoveryIdentity-Version' => '1.4',
            'DiscoveryIdentity-Id' => $this->agentParams['sidecar.ipAddress']
        ], $this->defaultHeaders);
        foreach ($this->agentParams['sidecar.eurekaUrls'] as $namePrefix) {
            list($option['base_uri'], $prefix, $option['port']) = $namePrefix;
            $option['headers'] = $headers;
            $uri = $prefix . '/apps/' . strtoupper($this->agentParams['sidecar.applicationName']);
            $option['body'] = json_encode($this->appInstance, JSON_UNESCAPED_SLASHES);

            try {
                $result = $this->httpClient->post($uri, $option);
            } catch (SidecarException $e) {
                Log::error('register instance exception occured: ' . $e->getMessage());
                CLog::info('eureka ' . $option['base_uri'] . ':' . $option['port'] . ' register: failed!');
                $this->instanceStatus = 'DOWN';
                return;
            }

            if (204 == $result->getStatusCode()) {
                $this->pullInstances();
                CLog::info('eureka ' . $option['base_uri'] . ':' . $option['port'] . ' register: success!');
            } else {
                CLog::info('eureka ' . $option['base_uri'] . ':' . $option['port'] . ' register: failed!');
            }
        }
        return;
    }

    /**
     * @return bool
     * @throws SidecarException
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function pullInstances()
    {
        $this->agentParams['sidecar.enable'] = config('sidecar.enable');
        if (!$this->agentParams['sidecar.enable']) {
            return true;
        }

        $lastVersion = $this->sidecarTable->get(SidecarConstant::DELTA_VERSION, SidecarConstant::SIDECAR_INFO);
        $randKey = array_rand($this->agentParams['sidecar.eurekaUrls']);
        list($option['base_uri'], $prefix, $option['port']) = $this->agentParams['sidecar.eurekaUrls'][$randKey];
        
        $uri = $prefix . '/apps/delta';
        $option['headers'] = $this->defaultHeaders;
        try {
            $result = $this->httpClient->get($uri, $option)->getResult();
        } catch (SidecarException $e) {
            Log::error('versions__delta exception occured: ' . $e->getMessage());
        }

        $version = $result['applications']['versions__delta'] ?? '';
        if ($lastVersion && $lastVersion == $version) {
            return true;
        }
        $lastVersion = $version;

        $uri = $prefix . '/apps';
        try {
            $result = $this->httpClient->get($uri, $option)->getResult();
        } catch (SidecarException $e) {
            Log::error('pull instances exception occured: ' . $e->getMessage());
        }
        
        if (!is_array($result)) {
            return false;
        }

        $applicationsKeys = [];
        $applications = $result['applications']['application'] ?? [];
        if (isset($applications['instance']) && isset($applications['name'])) {
            // 兼容老版本只有一个实例时返回格式不一致问题
            $key = SidecarConstant::SIDECAR_KEYS_PREFIX . md5(strtoupper($applications['name']));
            $applicationsKeys[] = $key;
            $this->sidecarTable->set($key, [SidecarConstant::SIDECAR_INFO => json_encode($applications['instance'])]);
        } else {
            foreach ($applications as $app) {
                $key = SidecarConstant::SIDECAR_KEYS_PREFIX . md5(strtoupper($app['name']));
                $applicationsKeys[] = $key;
                $this->sidecarTable->set($key, [SidecarConstant::SIDECAR_INFO => json_encode($app['instance'])]);
            }
        }
        $this->sidecarTable->set(SidecarConstant::SIDECAR_KEYS, [SidecarConstant::SIDECAR_INFO => json_encode($applicationsKeys)]);
        $this->sidecarTable->set(SidecarConstant::DELTA_VERSION, [SidecarConstant::SIDECAR_INFO => $lastVersion]);
        return true;
    }

    /**
     * @return bool
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function heartbeat()
    {
        $this->agentParams['sidecar.enable'] = config('sidecar.enable');
        if (!$this->agentParams['sidecar.enable']) {
            return true;
        }

        $uri = $this->agentParams['sidecar.healthUri'];
        $option['base_uri'] = substr($this->agentParams['sidecar.ipAddress'], 7);
        $option['port'] = $this->agentParams['sidecar.port'];
        $option['headers'] = $this->defaultHeaders;
        $heartbeatParams = [
            'value' => 'UP',
            'lastDirtyTimestamp' => $this->lastDirtyTimestamp,
        ];
        $result = [];
        try {
            $result = $this->httpClient->get($uri, $option)->getResult();
        } catch (\Exception $e) {
            Log::info('eureka sidecar request health uri failed: ' . $e->getMessage());
            $heartbeatParams['value'] = 'DOWN';
        }
        $status = $result['status'] ?? '';
        if (!is_array($result) || $status != 'UP') {
            $heartbeatParams['value'] = 'DOWN';
        }

        if ('UP' == $heartbeatParams['value'] && 'UP' == $this->instanceStatus) {
            $heartbeatParams['status'] = 'UP';
            unset($heartbeatParams['value']);
            $instance = $this->instanceId . '?' . http_build_query($heartbeatParams);
        } elseif ('UP' == $heartbeatParams['value'] && 'DOWN' == $this->instanceStatus) {
            $instance = $this->instanceId . '/status' . '?' . http_build_query($heartbeatParams);
            $this->instanceStatus = 'UP';
        } elseif ('DOWN' == $heartbeatParams['value'] && 'UP' == $this->instanceStatus) {
            $instance = $this->instanceId . '/status' . '?' . http_build_query($heartbeatParams);
            $this->instanceStatus = 'DOWN';
        } elseif ('DOWN' == $heartbeatParams['value'] && 'DOWN' == $this->instanceStatus) {
            $instance = $this->instanceId . '/status' . '?' . http_build_query($heartbeatParams);
        }

        $headers = array_merge([
            'Accept-Encoding' => 'gzip',
            'DiscoveryIdentity-Name' => 'DefaultClient',
            'DiscoveryIdentity-Version' => '1.4',
            'DiscoveryIdentity-Id' => substr($this->agentParams['sidecar.ipAddress'], 7)
        ], $this->defaultHeaders);

        $randKey = array_rand($this->agentParams['sidecar.eurekaUrls']);
        list($option['base_uri'], $prefix, $option['port']) = $this->agentParams['sidecar.eurekaUrls'][$randKey];
        $uri = $prefix . '/apps/' . strtoupper($this->agentParams['sidecar.applicationName']) . '/' . $instance;
        $option['headers'] = $headers;
        try {
            $response = $this->httpClient->put($uri, $option);
            if ($response->getStatusCode() == 404) {
                $this->registerInstance();
                Log::info('eureka retry register:' . $this->agentParams['sidecar.ipAddress']);
            } elseif ($response->getStatusCode() != 200) {
                return false;
            }
        } catch (\Exception $e) {
            Log::info('update heartbeat failed: ' . $e->getMessage());
            $this->instanceStatus = 'DOWN';
        }

        return true;
    }

    /**
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function unregisterInstance(): void
    {
        $this->agentParams['sidecar.enable'] = config('sidecar.enable');
        if (!$this->agentParams['sidecar.enable']) {
            return;
        }

        $flag = true;
        $count = 0;
        $deleteStatus = [];
        while ($flag) {
            if ($count > 10) {
                Log::error('unregister eureka falied: ' . json_encode($deleteStatus));
                break;
            }
            $count++;
            foreach ($this->agentParams['sidecar.eurekaUrls'] as $namePrefix) {
                list($option['base_uri'], $prefix, $option['port']) = $namePrefix;
                $uri = $prefix . '/apps/' . strtoupper($this->agentParams['sidecar.applicationName']) . '/' . $this->instanceId;
                $isDel = $deleteStatus[$option['base_uri']] ?? '';
                if (200 == $isDel) {
                    continue;
                }
                $status = $this->httpClient->delete($uri, $option)->getStatusCode();
                if (200 == $status) {
                    $deleteStatus[$option['base_uri']] = $status;
                }
            }
            if (count($deleteStatus) == count($this->agentParams['sidecar.eurekaUrls'])) {
                $flag = false;
            }
        }
    }

    /**
     * 获取app instance实例
     * @return array
     */
    public function getInstance()
    {
        return $this->appInstance;
    }
}
