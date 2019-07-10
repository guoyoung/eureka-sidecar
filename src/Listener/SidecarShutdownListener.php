<?php


namespace Sidecar\Listener;

use Sidecar\Bean\Sidecar;
use Swoft\Bean\BeanFactory;
use Swoft\Event\Annotation\Mapping\Listener;
use Swoft\Event\EventHandlerInterface;
use Swoft\Event\EventInterface;
use Swoft\Server\Swoole\SwooleEvent;

/**
 * Class SidecarShutdownListener
 * @package Sidecar\Listener
 * @Listener(SwooleEvent::SHUTDOWN)
 */
class SidecarShutdownListener implements EventHandlerInterface
{
    /**
     * @param EventInterface $event
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function handle(EventInterface $event): void
    {
        if (config('sidecar.enable', false)) {
            // 注销服务
            /**
             * @var $bean Sidecar
             */
            $bean = BeanFactory::getBean('sidecar');
            $bean->unregisterAppInstance();
        }
    }
}
