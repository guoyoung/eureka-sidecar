# php eureka sidecar
- 基于swoft2.0.5+，简单实现php对eureka的注册，发现，心跳，调用转发等。

# github
- https://github.com/guoyoung/eureka-sidecar

# 安装
- 通过composer安装
```
composer require gyoung/eureka-sidecar
```
# 使用
## 基础配置
- 在config下新增sidecar.php，内容如下：
```
  return [
      'enable' => true,         //是否启用
      'eurekaUrls' => 'http://127.0.0.1:8761/eureka',       //eureka注册地址，多个由逗号隔开
      'serverPort' => 8089,       //sidecar端口
      'port' => 8089,             //代理服务端口
      'ipAddress' => 'http://127.0.0.1',        //代理服务ip,为空则通过swoole_get_local_ip()获取本机eth0的ip
      'healthUri' => '/health',    //被代理服务需提供的心跳uri，返回格式必须为: ['status' => 'UP'], json后返回，默认：/health
      'applicationName' => 'sidecar-test',  //应用名称
      'sidecarTableMaxLength' => 4096,  //swoole table单个key最大储存长度
      'pullAppTime' => 20000,  //定时拉取实例/ms，默认20000ms
      'healthTime' => 30000,   //定时健康检查/ms，默认30000ms
  ];
```

- 采用自定义进程进行健康检查和拉取服务，因此使用时需在bean.php中添加如下配置(http server为例)：
```
  'httpServer' => [
      ...
      'process' => [
          'sidecar' => bean(\Sidecar\Process\SidecarProcess::class)
      ],
      ...
  ],
```

- 代理服务需提供能访问的在sidecar.php中配置的 healthUri
```
/**
 * @return \Swoft\Http\Message\Response|\Swoft\WebSocket\Server\Message\Response
 * @throws \Swoft\Exception\SwoftException
 * @RequestMapping("/health")
 */
public function health()
{
    return $this->json(['status' => 'UP']);
}
```
- 正常启动swoft后就会向eureka注册，定时拉取服务实例，服务实例储存在swoole table中

## Agent
```
/**
 * @Inject()
 * @var Agent
 */
private $agent;

/**
 * @return array|mixed
 * @throws \ReflectionException
 * @throws \Sidecar\Exception\SidecarException
 * @throws \Swoft\Bean\Exception\ContainerException
 */
public function sidecar()
{
    $result = $this->agent->proxy('SERVICE-CLIENT', '/user', 'GET', ['id' => 1]);
    var_dump($result);
}
```
- application:获取所有实例信息
- proxy:服务调用转发


