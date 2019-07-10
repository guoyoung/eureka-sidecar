<?php


namespace Sidecar;

use Sidecar\Constant\SidecarConstant;
use Sidecar\SidecarException;
use Sidecar\Util\SidecarContext;
use Sidecar\Util\SidecarTable;
use Sidecar\Http\HttpClient;
use Swlib\Http\ContentType;
use Swoft\Bean\Annotation\Mapping\Bean;
use Swoft\Log\Helper\CLog;
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
        'User-Agent' => 'php-eureka-agent' . '(' . SidecarConstant::SIDECAR_VERSION . ')',
        'Content-Type' => ContentType::JSON
    ];

    /**
     * 初始化时间
     * @var int
     */
    private $lastDirtyTimestamp = 0;

    /**
     * 实例信息
     * @var array
     */
    private $appInstance = [];

    /**
     * 正在注册
     * @var bool
     */
    private $registering = false;

    /**
     * @var Table
     */
    private $sidecarTable = null;

    /**
     * @throws SidecarException
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function init()
    {
        $this->initEurekaParams();
        $this->sidecarTable = SidecarTable::getInstance();
    }

    /**
     * @throws SidecarException
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    private function initEurekaParams()
    {
        $eurekaUrls = explode(',', config('sidecar.eurekaUrls'));
        if (empty($eurekaUrls)) {
            throw new SidecarException('sidecar.eurekaUrls is needed,like: http://127.0.0.1:1111/eureka/,http://127.0.0.1:1112/eureka/');
        }
        foreach ($eurekaUrls as $eurekaUrl) {
            $parseUrl = parse_url($eurekaUrl);
            $scheme = $parseUrl['scheme'] ?? 'http';
            $port = $parseUrl['port'] ?? '';
            $port = $port ?: ('http' == $scheme ? '80' : '443');
            $name = $scheme . '://' . $parseUrl['host'] . ':' . $port;
            $prefix = rtrim($parseUrl['path'], '/');
            $this->agentParams['sidecar.eurekaUrls'][] = [$name, $prefix];
        }

        if (!config('sidecar.serverPort', '')) {
            throw new SidecarException('sidecar.serverPort is needed');
        }
        $this->agentParams['sidecar.serverPort'] = config('sidecar.serverPort');

        if (!config('sidecar.port', '')) {
            throw new SidecarException('sidecar.port is needed');
        }
        $this->agentParams['sidecar.port'] = config('sidecar.port');

        if (!config('sidecar.ipAddress', '')) {
            throw new SidecarException('sidecar.ipAddress is needed');
        }
        $this->agentParams['sidecar.ipAddress'] = config('sidecar.ipAddress');

        if (!config('sidecar.healthUri', '')) {
            throw new SidecarException('sidecar.healthUri is needed');
        }
        $this->agentParams['sidecar.healthUri'] = config('sidecar.healthUri');

        if (!config('sidecar.applicationName', '')) {
            throw new SidecarException('sidecar.applicationName is needed');
        }
        $this->agentParams['sidecar.applicationName'] = config('sidecar.applicationName');

        $this->lastDirtyTimestamp = (string)round(microtime(true) * 1000);

        $this->appInstance = [
            'instance' =>[
                'instanceId' => gethostname() . ':' . $this->agentParams['sidecar.applicationName'] . ':' . $this->agentParams['sidecar.serverPort'],
                'hostName' => gethostname() . ':' . $this->agentParams['sidecar.applicationName'] . ':' . $this->agentParams['sidecar.serverPort'],
                'app' => strtoupper($this->agentParams['sidecar.applicationName']),
                'ipAddr' => $this->agentParams['sidecar.ipAddress'],
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
                    '@class' => ''
                ],
                'homePageUrl' => $this->agentParams['sidecar.ipAddress'] . ':' . $this->agentParams['sidecar.port'] . '/',
                'statusPageUrl' => $this->agentParams['sidecar.ipAddress'] . ':' . $this->agentParams['sidecar.serverPort'] . config('statusPageUrl', '/agent/info'),
                'healthCheckUrl' => $this->agentParams['sidecar.healthUri'],
                'vipAddress' => $this->agentParams['sidecar.applicationName'],
                'secureVipAddress' => $this->agentParams['sidecar.applicationName'],
                'isCoordinatingDiscoveryServer' => 'false',
                'lastUpdatedTimestamp' => (string)(round(microtime(true) * 1000)),
                'lastDirtyTimestamp' => $this->lastDirtyTimestamp
            ]
        ];
    }

    /**
     * 注册服务
     * @return bool
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function registerAppInstance()
    {
        $this->agentParams['sidecar.enable'] = config('sidecar.enable');
        if (!$this->agentParams['sidecar.enable']) {
            return true;
        }

        $this->registering = true;
        try {
            foreach ($this->agentParams['sidecar.eurekaUrls'] as $namePrefix) {
                list($host, $prefix) = $namePrefix;
                $headers = array_merge([
                    'Accept-Encoding' => 'gzip',
                    'DiscoveryIdentity-Name' => 'DefaultClient',
                    'DiscoveryIdentity-Version' => '1.4',
                    'DiscoveryIdentity-Id' => $this->agentParams['sidecar.ipAddress'],
                    'Connection' => 'Keep-Alive'
                ], $this->defaultHeaders);

                $option['headers'] = $headers;
                $option['base_uri'] = $host;
                $option['method'] = 'POST';
                $option['uri'] = $prefix . '/apps/' . strtoupper($this->agentParams['sidecar.applicationName']);
                $option['use_pool'] = false;
                $data = json_encode($this->appInstance, JSON_UNESCAPED_SLASHES);

                go(function () use ($data, $option) {
                    $client = HttpClient::getInstance();
                    $response = $client->request($data, $option, true, true, false);
                    if ($response->getStatusCode() == 204) {
                        CLog::info('eureka-register: success');
                    }
                    $data = null;
                    $option = null;
                    unset($data, $option);
                });

                $headers = null;
                unset($headers);
            }
            return true;
        } finally {
            $this->registering = false;
        }
    }

    /**
     * 全量拉取服务
     * @return bool
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function pullAllApplications()
    {
        $this->agentParams['sidecar.enable'] = config('sidecar.enable');
        if (!$this->agentParams['sidecar.enable']) {
            return true;
        }

        $lastVersion = $this->sidecarTable->get(SidecarConstant::DELTA_VERSION, SidecarConstant::SIDECAR_INFO);
        $randKey = array_rand($this->agentParams['sidecar.eurekaUrls']);
        list($host, $prefix) = $this->agentParams['sidecar.eurekaUrls'][$randKey];

        $http = HttpClient::getInstance();
        $option['method'] = 'GET';
        $option['base_uri'] = $host;
        $option['uri'] = $prefix . '/apps/delta';
        $option['use_pool'] = false;
        $option['headers'] = $this->defaultHeaders;
        $result = $http->request(null, $option, false, false, false);

        $version = $result['applications']['versions__delta'] ?? '';
        if ($lastVersion && $lastVersion == $version) {
            return true;
        }
        $lastVersion = $version;
        $result = null;
        unset($result);

        $option['uri'] = $prefix . '/apps';
        $result = $http->request(null, $option, false, false, false);

        if (!is_array($result)) {
            $result =null;
            unset($result);
            return false;
        }

        $applicationsKeys = [];
        $applications = $result['applications']['application'] ?? [];
        if (isset($applications['instance']) && isset($applications['name'])) {
            $key = SidecarConstant::APP_PREFIX . md5(strtoupper($applications['name']));
            $applicationsKeys[] = $key;
            $this->sidecarTable->set($key, [SidecarConstant::SIDECAR_INFO => json_encode($applications['instance'])]);
        } else {
            foreach ($applications as $app) {
                $key = SidecarConstant::APP_PREFIX . md5(strtoupper($app['name']));
                $applicationsKeys[] = $key;
                $this->sidecarTable->set($key, [SidecarConstant::SIDECAR_INFO => json_encode($app['instance'])]);
            }
        }
        $this->sidecarTable->set(SidecarConstant::EUREKA_APP_KEYS, [SidecarConstant::SIDECAR_INFO => json_encode($applicationsKeys)]);
        $result = null;
        unset($result);
        $applicationsKeys = null;
        unset($applicationsKeys);
        $this->sidecarTable->set(SidecarConstant::DELTA_VERSION, [SidecarConstant::SIDECAR_INFO => $lastVersion]);
        return true;
    }

    /**
     * 与注册中心保持心跳
     * @return bool
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function applicationInstanceHeartbeat()
    {
        $this->agentParams['sidecar.enable'] = config('sidecar.enable');
        if (!$this->agentParams['sidecar.enable']) {
            return true;
        }

        $randKey = array_rand($this->agentParams['sidecar.eurekaUrls']);
        list($host, $prefix) = $this->agentParams['sidecar.eurekaUrls'][$randKey];

        $heartbeatParams = [
            'value' => 'UP',
            'lastDirtyTimestamp' => $this->lastDirtyTimestamp
        ];

        $http = HttpClient::getInstance();
        $option['method'] = 'GET';
        $option['uri'] = $this->agentParams['sidecar.healthUri'];
        $option['base_uri'] = $this->agentParams['sidecar.ipAddress'];
        $option['use_pool'] = false;
        $option['headers'] = $this->defaultHeaders;
        $result = [];
        try {
            $result = $http->request(null, $option, false, false, false);
        } catch (\Exception $e) {
            CLog::info('eureka sidecar request health uri failed: ' . $e->getMessage());
            $heartbeatParams['value'] = 'DOWN';
        }

        $status = $result['status'] ?? '';
        if (!is_array($result) || $status != 'UP') {
            $heartbeatParams['value'] = 'DOWN';
        }
        $result = null;
        unset($result);

        $instance = gethostname() . ':' . $this->agentParams['sidecar.applicationName'] . ':' . $this->agentParams['sidecar.serverPort'] . '/status';
        $instance .= '?' . http_build_query($heartbeatParams);
        $heartbeatParams = null;
        unset($heartbeatParams);

        $headers = array_merge([
            'Accept-Encoding' => 'gzip',
            'DiscoveryIdentity-Name' => 'DefaultClient',
            'DiscoveryIdentity-Version' => '1.4',
            'DiscoveryIdentity-Id' => $this->agentParams['sidecar.ipAddress'],
            'Connection' => 'Keep-Alive'
        ], $this->defaultHeaders);

        $option['method'] = 'PUT';
        $option['uri'] = $prefix . '/apps/' . strtoupper($this->agentParams['sidecar.applicationName']) . '/' . $instance;
        $option['base_uri'] = $host;
        $option['headers'] = $headers;
        try {
            $response = $http->request(null, $option, true, true, false);
            if ($response->getStatusCode() == 404) {
                $this->registerAppInstance();
                CLog::info('eureka-retry-register:' . gethostname());
            } elseif ($response->getStatusCode() != 200) {
                return false;
            }
        } catch (\Exception $e) {
            CLog::info('eureka sidecar request eureka server failed: ' . $e->getMessage());
        }
        $headers = null;
        $option = null;
        unset($headers, $option);

        $this->appInstance['instance']['leaseInfo']['lastRenewalTimestamp'] = round(microtime(true) * 1000);
        $this->sidecarTable->set(SidecarConstant::SYS_CACHE_APP_LAST_RENEW, [SidecarConstant::SIDECAR_INFO => $this->appInstance['instance']['leaseInfo']['lastRenewalTimestamp']]);
        return true;
    }

    /**
     * 关服时移除注册中心
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function unregisterAppInstance(): void
    {
        $this->agentParams['sidecar.enable'] = config('sidecar.enable');
        if (!$this->agentParams['sidecar.enable']) {
            return;
        }

        $flag = true;
        $count = 0;
        $deleteStatus = [];
        while ($flag) {
            try {
                if ($count > 10) {
                    break;
                }
                $count++;
                foreach ($this->agentParams['sidecar.eurekaUrls'] as $namePrefix) {
                    list($host, $prefix) = $namePrefix;
                    $uri = $prefix . '/apps/' . strtoupper($this->agentParams['sidecar.applicationName']) . '/' .
                        gethostname() . ':' . $this->agentParams['sidecar.applicationName'] . ':' . $this->agentParams['sidecar.serverPort'];
                    $url = $host . $uri;
                    $isDel = $deleteStatus[$url] ?? '';
                    if (200 == $isDel) {
                        continue;
                    }
                    $status = $this->curlGetStatusCode($url, $this->defaultHeaders);
                    if (200 == $status) {
                        $deleteStatus[$url] = $status;
                    }
                }
                if (count($deleteStatus) == count($this->agentParams['sidecar.eurekaUrls'])) {
                    $flag = false;
                }
            } catch (\Throwable $e) {
                CLog::info($e);
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

    /**
     * onShutDown专用curl
     * @param $url
     * @param $headers
     * @return mixed
     */
    private function curlGetStatusCode($url, $headers)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');

        curl_setopt($curl, CURLINFO_HEADER_OUT, TRUE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_NOBODY, true);

        curl_exec($curl);

        //响应码
        $statusCode = curl_getinfo($curl,CURLINFO_HTTP_CODE);

        curl_close($curl);

        return $statusCode;
    }
}
