<?php

declare(strict_types=1);

namespace H13\FeedPulse\Module;

use BEAR\FakeJson\FakeJsonModule;

class FakeModule extends AppModule
{
    protected function configure(): void
    {
        $this->install(new FakeJsonModule($this->appDir . '/var/fake'));

        parent::configure();
    }
}
