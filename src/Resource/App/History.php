<?php

declare(strict_types=1);

namespace H13\FeedPulse\Resource\App;

use BEAR\Resource\ResourceObject;
use BEAR\ToolUse\Attribute\Tool;
use H13\FeedPulse\Reason\HistoryStore;
use Ray\Di\Di\Inject;

#[Tool(description: 'View publish history')]
class History extends ResourceObject
{
    private HistoryStore $historyStore;

    #[Inject]
    public function __construct(HistoryStore $historyStore)
    {
        $this->historyStore = $historyStore;
    }

    /** List all publish history entries (newest first) */
    public function onGet(): static
    {
        $this->body = $this->historyStore->loadAll();

        return $this;
    }
}
