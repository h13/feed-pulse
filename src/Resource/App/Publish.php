<?php

declare(strict_types=1);

namespace H13\FeedPulse\Resource\App;

use BEAR\Resource\ResourceObject;
use BEAR\ToolUse\Attribute\Tool;
use H13\FeedPulse\Publisher\PublisherPool;
use H13\FeedPulse\Reason\DraftStore;
use H13\FeedPulse\Reason\HistoryStore;
use Ray\Di\Di\Inject;

use function array_filter;
use function array_map;
use function array_values;
use function count;

#[Tool(description: 'Publish pending drafts to configured channels', confirm: true)]
class Publish extends ResourceObject
{
    #[Inject]
    public function __construct(
        private readonly PublisherPool $publisherPool,
        private readonly DraftStore $draftStore,
        private readonly HistoryStore $historyStore,
    ) {
    }

    /**
     * Publish drafts to their target channels.
     *
     * @param string|null $draftId Publish a specific draft (null = publish all)
     */
    public function onPost(string|null $draftId = null): static
    {
        $drafts = $this->draftStore->loadAll();

        if ($draftId !== null) {
            $drafts = array_values(array_filter($drafts, static fn ($d) => $d->id === $draftId));
        }

        if ($drafts === []) {
            $this->code = 204;
            $this->body = ['message' => 'No drafts to publish'];

            return $this;
        }

        $results = [];
        foreach ($drafts as $draft) {
            $result = $this->publisherPool->publish($draft);
            $results[] = $result;

            if ($result->isSuccess()) {
                $this->draftStore->delete($draft->id);
            }
        }

        $this->historyStore->save($results);

        $failures = array_filter($results, static fn ($r) => ! $r->isSuccess());

        $this->code = $failures !== [] ? 207 : 200;
        $this->body = [
            'published' => count($results) - count($failures),
            'failed' => count($failures),
            'results' => array_map(static fn ($r) => [
                'channel' => $r->channel,
                'title' => $r->title,
                'url' => $r->url,
                'error' => $r->error,
            ], $results),
        ];

        return $this;
    }
}
