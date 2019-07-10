<?php


namespace Sidecar\Listener;

use Sidecar\Bean\Sidecar;
use Swoft\Bean\BeanFactory;
use Swoft\Event\Annotation\Mapping\Listener;
use Swoft\Event\EventHandlerInterface;
use Swoft\Event\EventInterface;
use Swoft\Server\ServerEvent;
use Swoole\Process;
use Swoole\Timer;

/**
 * Class SidecarTimerListener
 * @package Sidecar\Listener
 * @Listener(ServerEvent::BEFORE_START)
 */
class SidecarTimerListener implements EventHandlerInterface
{
    /**
     * @param EventInterface $event
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function handle(EventInterface $event): void
    {
        if (config('sidecar.enable', false)) {
            $process = new Process(function () {
                // 定时拉取服务
                Timer::tick(config('sidecar.pullAppTime', 10000), function () {
                    /**
                     * @var $sidecar Sidecar
                     */
                    $sidecar = BeanFactory::getBean('sidecar');
                    $sidecar->pullAllApplications();
                });

                // 定时保持心跳
                Timer::tick(config('sidecar.healthTime', 30000), function () {
                    /**
                     * @var $sidecar Sidecar
                     */
                    $sidecar = BeanFactory::getBean('sidecar');
                    $sidecar->applicationInstanceHeartbeat();
                });
            });
            $process->name('php sidecar process');
            $event->getTarget()->getSwooleServer()->addProcess($process);
        }
    }
}
