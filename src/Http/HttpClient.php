<?php


namespace Sidecar\Http;

use Sidecar\Exception\SidecarException;
use Swoft\Bean\Annotation\Mapping\Bean;
use Swoft\Http\Message\ContentType;
use Swoft\Log\Helper\Log;
use Swoft\Stdlib\Helper\JsonHelper;
use Swoole\Coroutine\Http\Client;

/**
 * Class HttpClient
 * @package Sidecar\Http
 * @Bean("eurekaHttpClient")
 */
class HttpClient
{
    /**
     * Seconds
     *
     * @var int
     */
    private $timeout = 3;

    /**
     * @param string|null $url
     * @param array $options
     * @return Response
     * @throws SidecarException
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function get(string $url = null, array $options = []): Response
    {
        return $this->request('GET', $url, $options);
    }

    /**
     * @param string $url
     * @param array $options
     * @return Response
     * @throws SidecarException
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function head(string $url, array $options = []): Response
    {
        return $this->request('HEAD', $url, $options);
    }

    /**
     * @param string $url
     * @param array $options
     * @return Response
     * @throws SidecarException
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function delete(string $url, array $options = []): Response
    {
        return $this->request('DELETE', $url, $options);
    }

    /**
     * @param string $url
     * @param array $options
     * @return Response
     * @throws SidecarException
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function put(string $url, array $options = []): Response
    {
        return $this->request('PUT', $url, $options);
    }

    /**
     * @param string $url
     * @param array $options
     * @return Response
     * @throws SidecarException
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function patch(string $url, array $options = []): Response
    {
        return $this->request('PATCH', $url, $options);
    }

    /**
     * @param string $url
     * @param array $options
     * @return Response
     * @throws SidecarException
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function post(string $url, array $options = []): Response
    {
        return $this->request('POST', $url, $options);
    }

    /**
     * @param string $url
     * @param array $options
     * @return Response
     * @throws SidecarException
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function options(string $url, array $options = []): Response
    {
        return $this->request('OPTIONS', $url, $options);
    }

    /**
     * @param $method
     * @param $uri
     * @param $options
     * @return Response
     * @throws SidecarException
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    private function request($method, $uri, $options): Response
    {
        $host = $options['base_uri'] ?? '';
        $port = $options['port'] ?? '';
        if (!$host || !$port) {
            throw new SidecarException('base_uri or port is needed');   
        }
        
        $body = $options['body'] ?? '';
        if (is_array($body)) {
            $body = JsonHelper::encode($body, JSON_UNESCAPED_UNICODE);
        }

        $query = $options['query'] ?? [];
        if (!empty($query)) {
            $query = http_build_query($query);
            $uri   = sprintf('%s?%s', $uri, $query);
        }

        $requestHeaders = $options['headers'] ?? [];
        if (!isset($requestHeaders['Accept'])) {
            $requestHeaders['Accept'] = ContentType::JSON;
        }
        if (!isset($requestHeaders['Accept'])) {
            $requestHeaders['Content-Type'] = ContentType::JSON;
        }

        $option = $options['option'] ?? [];
        if (!isset($option['timeout'])) {
            $option['timeout'] = $this->timeout;
        }
        
        Log::Debug('Requesting %s %s %s', $method, $uri, JsonHelper::encode($options));

        try {
            Log::profileStart($uri);

            // Http request
            $client = new Client($options['base_uri'], (int)$options['port']);
            $client->setMethod($method);
            $client->setHeaders($requestHeaders);
            $client->set($option);

            // Set body
            if (!empty($body)) {
                $client->setData($body);
            }

            $client->execute($uri);

            // Response
            $headers    = $client->headers;
            $statusCode = $client->statusCode;
            $body       = $client->body;

            // Close
            $client->close();

            Log::profileEnd($uri);
        } catch (\Exception $e) {
            throw new SidecarException('client exception occured: ' . $e->getMessage());
        }

        if ($statusCode == -1 || $statusCode == -2 || $statusCode == -3) {
            $message = sprintf('request is fail! (uri=%s status=%s body=%s).', $uri, $statusCode, $body);
            throw new SidecarException($message);
        }

        return Response::new($headers, $body, $statusCode);
    }
}
