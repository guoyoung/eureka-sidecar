<?php declare(strict_types=1);

namespace Sidecar\Controller;

use Swoft\Http\Message\ContentType;
use Swoft\Http\Server\Annotation\Mapping\Controller;
use Swoft\Http\Server\Annotation\Mapping\RequestMapping;

/**
 * Class Actuator
 * @Controller()
 */
class Actuator
{
    /**
     * @return \Swoft\Http\Message\Response|\Swoft\Rpc\Server\Response
     * @throws \Swoft\Exception\SwoftException
     * @RequestMapping("/")
     */
    public function index()
    {
        return $this->json([
            'code' => 200,
            'msg' => 'success',
            'status' => true,
            'data' => null
        ]);
    }

    /**
     * @return \Swoft\Http\Message\Response|\Swoft\Rpc\Server\Response
     * @throws \Swoft\Exception\SwoftException
     * @RequestMapping("/actuator")
     */
    public function actuator()
    {
        return $this->json([
            'code' => 200,
            'msg' => 'success',
            'status' => true,
            'data' => null
        ]);
    }
    
    /**
     * @return \Swoft\Http\Message\Response|\Swoft\Rpc\Server\Response
     * @throws \Swoft\Exception\SwoftException
     * @RequestMapping("/actuator/info")
     */
    public function info()
    {
        return $this->json([
            'code' => 200,
            'msg' => 'success',
            'status' => true,
            'data' => null
        ]);
    }

    /**
     * @return \Swoft\Http\Message\Response|\Swoft\Rpc\Server\Response
     * @throws \Swoft\Exception\SwoftException
     * @RequestMapping("/health")
     */
    public function health()
    {
        return $this->json(['status' => 'UP']);
    }

    /**
     * @param array $data
     * @param int $status
     * @param string $type
     * @return \Swoft\Http\Message\Response|\Swoft\Rpc\Server\Response
     * @throws \Swoft\Exception\SwoftException
     */
    private function json($data = [], $status = 200, $type = ContentType::JSON)
    {
        $response = \context()->getResponse();
        return $response->withData($data)->withStatus($status)->withContentType($type);
    }
}
