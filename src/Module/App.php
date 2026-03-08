<?php

declare(strict_types=1);

namespace H13\FeedPulse\Module;

use BEAR\Resource\ResourceInterface;
use BEAR\Sunday\Extension\Application\AppInterface;
use BEAR\Sunday\Extension\Error\ErrorInterface;
use BEAR\Sunday\Extension\Router\RouterInterface;
use BEAR\Sunday\Extension\Transfer\HttpCacheInterface;
use BEAR\Sunday\Extension\Transfer\TransferInterface;

class App implements AppInterface
{
    public function __construct(
        public HttpCacheInterface $httpCache,
        public RouterInterface $router,
        public TransferInterface $responder,
        public ResourceInterface $resource,
        public ErrorInterface $error,
    ) {
    }
}
