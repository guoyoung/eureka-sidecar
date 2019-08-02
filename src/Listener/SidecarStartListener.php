<?php


namespace Sidecar\Listener;

use Sidecar\Constant\SidecarConstant;
use Sidecar\Sidecar;
use Sidecar\Util\SidecarTable;
use Swoft\Bean\BeanFactory;
use Swoft\Event\Annotation\Mapping\Listener;
use Swoft\Event\EventHandlerInterface;
use Swoft\Event\EventInterface;
use Swoft\Server\SwooleEvent;
use Swoole\Table;

/**
 * Class SidecarStartListener
 * @package Sidecar\Listener
 * @Listener(SwooleEvent::START)
 */
class SidecarStartListener implements EventHandlerInterface
{
    /**
     * SidecarStartListener constructor.
     * @throws \ReflectionException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function __construct()
    {
        if (config('sidecar.enable', false)) {
            $table = SidecarTable::getInstance();
            $table->column(SidecarConstant::SIDECAR_INFO, Table::TYPE_STRING, config('sidecarTableMaxLength', 4096));
            $table->create();
        }
    }

    /**
     * @param EventInterface $event
     * @throws \ReflectionException
     * @throws \Sidecar\Exception\SidecarException
     * @throws \Swoft\Bean\Exception\ContainerException
     */
    public function handle(EventInterface $event): void
    {
        if (config('sidecar.enable', false)) {
            // 注册服务
            /**
             * @var $sidecar Sidecar
             */
            $sidecar = BeanFactory::getBean('sidecar');
            $sidecar->registerInstance();
        }
    }
}
