<?php


namespace Sidecar\Http;

use Swlib\Http\ContentType;
use Swlib\Http\Exception\HttpExceptionMask;
use Swlib\Saber;
use Swoft\Log\Helper\CLog;
use Swoft\Log\Helper\Log;

/**
 * 使用说明：
 * 只允许通过getInstance获取httpClient对象，request单个请求，multi并发请求
 * 配置说明：
| base_uri              | string                | 基础路径           如：http://127.0.0.1:8081
| uri                   | string                | 资源标识符         如：/service/dispatch?node=1001000
| method                | string                | 请求方法           如：GET
| headers               | array                 | 请求报头
| cookies               | `array`|`string`      |
| useragent             | string                | 用户代理
| referer               | string                | 来源地址
| redirect              | int                   | 最大重定向次数
| keep_alive            | bool                  | 是否保持连接
| content_type          | string                | 发送的内容编码类型
| data                  | `array`  | `string`   | 发送的数据
| before                | `callable`  | `array` | 请求前拦截器
| after                 | `callable`  | `array` | 响应后拦截器
| before_redirect       | `callable`  | `array` | 重定向后拦截器
| timeout               | float                 | 超时时间
| proxy                 | string                | 代理
| ssl                   | int                   | 是否开启ssl连接
| cafile                | string                | ca文件
| ssl_verify_peer       | bool                  | 验证服务器端证书
| ssl_allow_self_signed | bool                  | 允许自签名证书
| iconv                 | array                 | 指定编码转换
| exception_report      | int                   | 异常报告级别
| exception_handle      | callable\|array       | 异常自定义处理函数
| retry                 | callable              | 自动重试拦截器
| retry_time            | int                   | 自动重试次数
| use_pool              | bool\|int             | 连接池
 * Class HttpClient
 * @author gyy
 * @package App\Utils\Http
 */
class HttpClient
{
    private static $instance = null;
    
    private $timeout = 10;
    
    private $usePool = false;

    private function __construct(){}

    private function __clone(){}

    /**
     * 单例获取
     * @return HttpClient|null
     */
    public static function getInstance()
    {
        self::$instance || self::$instance = new static();
        return self::$instance;
    }

    /**
     * 单个请求
     * 默认content-type: application/json
     * 默认超时60s
     * @param null $data 请求数据
     * @param array $options 请求设置
     * @param bool $isCLog
     * @param bool $isRaw
     * @param $isLog
     * @return bool|Saber\Request|Saber\Response|array
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function request($data = null, $options = [], $isCLog = false, $isRaw = false, $isLog = true)
    {
        if (!$options['base_uri'] || !$options['uri']) {
            return false;
        }

        $saber = Saber::create();
        $saber->exceptionReport(HttpExceptionMask::E_ALL);
        $uri = $options['uri'] ?? '';
        $saber->exceptionHandle(function (\Exception $e) use ($uri, $isCLog, $isLog) {
            if ($isLog) {
                $isCLog ? CLog::error('request ' . $uri . '\'s exception' . get_class($e) . ' occurred, exception message: ' . $e->getMessage())
                    : Log::pushLog('[request ' . $uri . '\'s exception' . get_class($e) . ' occurred, exception message]', $e->getMessage());
            }
        });

        !isset($options['method']) && $options['method'] = 'GET';

        null !== $data && $options['data'] = (is_array($data) ? json_encode($data) : $data);

        if (!isset($options['content-type']) || !isset($options['headers']['content-type']) || !isset($options['headers']['Content-Type'])) {
            $options['content-type'] = ContentType::JSON;
        }

        if (!isset($options['timeout'])) {
            $options['timeout'] = $this->timeout;
        }

        if (!isset($options['use_pool'])) {
            $options['use_pool'] = $this->usePool;
        }

        $options['headers']['Accept'] = ContentType::JSON;

        if ($isLog) {
            $isCLog ? CLog::info('request ' . $options['uri'] . '\'s params: ' . ($options['data'] ?? null)) :
                Log::pushLog('[request ' . $options['uri'] . '\'s params]', ($options['data'] ?? []));
        }
        $response = $saber->request($options);
        if ($isLog) {
            $isCLog ? CLog::info('request ' . $options['uri'] . '\'s time: ' . $response->time) :
                Log::pushLog('[request ' . $options['uri'] . '\'s time]', $response->time);
        }
        $return = null;
        if ($isRaw) {
            $return = $response;
        } elseif (false !== strpos($response->getHeaderLine('Content-Type'), ContentType::JSON)) {
            $return = $response->getParsedJsonArray();
        } elseif (false !== strpos($response->getHeaderLine('Content-Type'), ContentType::XML)) {
            $return = $response->getParsedXmlArray();
        } elseif (false !== strpos($response->getHeaderLine('Content-Type'), ContentType::HTML)) {
            $return = $response->getParsedDomObject();
        } else {
            $return = $response;
        }
        if ($isLog) {
            $isCLog ? CLog::info('request ' . $options['uri'] . '\'s result: ' . $return) :
                Log::pushLog('[request ' . $options['uri'] . '\'s result]', $return);
        }
        $response = null;
        unset($response);
        return $return;
    }
}
