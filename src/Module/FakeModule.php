<?php

declare(strict_types=1);

namespace H13\FeedPulse\Module;

use BEAR\FakeJson\FakeJsonModule;
use Override;

class FakeModule extends AppModule
{
    #[Override]
    protected function configure(): void
    {
        $this->install(new FakeJsonModule($this->appDir . '/var/fake'));

        parent::configure();
    }
}
