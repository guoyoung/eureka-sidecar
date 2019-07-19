# php eureka sidecar
基于swoft2.0，简单实现php对eureka的注册，发现等

agent bean:
- info:获取当前实例注册信息
- application:获取注册所有实例信息
- proxy:服务调用转发接口

配置：在config下新增sidecar.php文件

```
  return [
      'enable' => true,         //是否启用
      'eurekaUrls' => 'http://127.0.0.1:8761/eureka',       //eureka注册地址
      'serverPort' => 8089,       //sidecar端口
      'port' => 8089,             //代理服务端口
      'ipAddress' => 'http://127.0.0.1',        //代理服务ip,为空则获取本机ip
      'healthUri' => '/health',        //心跳uri
      'applicationName' => 'sidecar-test',  //应用名称
      'sidecarTableMaxLength' => 8096,  //swoole table单个key最大储存长度
      'pullAppTime' => 10000,  //定时拉取实例，ms
      'healthTime' => 30000,   //定时健康检查，ms
  ];
```