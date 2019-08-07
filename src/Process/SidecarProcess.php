<?php

namespace Sidecar\Process;

use Sidecar\Sidecar;
use Swoft\Bean\Annotation\Mapping\Bean;
use Swoft\Bean\BeanFactory;
use Swoft\Process\Process;
use Swoft\Process\UserProcess;
use Swoft\Timer;

/**
 * Class SidecarProcess
 * @package Sidecar\Process
 * @Bean()
 */
class SidecarProcess extends UserProcess
{
    /**
     * @param Process $process
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function run(Process $process): void
    {
        $process->name('eureka sidecar process');
        // 定时拉取服务
        Timer::tick(config('sidecar.pullAppTime', 20000), function () {
            /**
             * @var $sidecar Sidecar
             */
            $sidecar = BeanFactory::getBean('sidecar');
            $sidecar->pullInstances();
        });

        // 定时保持心跳
        Timer::tick(config('sidecar.healthTime', 30000), function () {
            /**
             * @var $sidecar Sidecar
             */
            $sidecar = BeanFactory::getBean('sidecar');
            $sidecar->heartbeat();
        });
    }
}
