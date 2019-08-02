# php eureka sidecar
基于swoft2.0.5+，简单实现php对eureka的注册，发现，心跳等。

拉取的服务实例使用swoole table存储。

采用自定义进程进行健康检查和拉取服务，因此使用时需在bean.php中添加如下配置(http server为例)：
```
  'httpServer' => [
      ...
      'process' => [
          'sidecar' => bean(\Sidecar\Process\SidecarProcess::class)
      ],
      ...
  ],
```

提供agent bean，方法:
- info:获取当前实例的基础信息
- application:获取所有实例信息
- proxy:服务调用转发

配置：在config下新增sidecar.php文件

```
  return [
      'enable' => true,         //是否启用
      'eurekaUrls' => 'http://127.0.0.1:8761/eureka',       //eureka注册地址
      'serverPort' => 8089,       //sidecar端口
      'port' => 8089,             //代理服务端口
      'ipAddress' => 'http://127.0.0.1',        //代理服务ip,为空则通过swoole_get_local_ip()获取本机eth0的ip
      'healthUri' => '/health',        //代理服务需提供的心跳uri，返回格式必须为: ['status' => 'UP'], json后返回
      'applicationName' => 'sidecar-test',  //应用名称
      'sidecarTableMaxLength' => 4096,  //swoole table单个key最大储存长度
      'pullAppTime' => 20000,  //定时拉取实例，ms
      'healthTime' => 30000,   //定时健康检查，ms
  ];
```
