<?php

namespace Sidecar\Contract;

interface AgentInterface
{
    public function register();
    public function deregister();
    public function health();
    public function pull();
}