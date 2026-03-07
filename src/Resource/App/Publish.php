<?php

declare(strict_types=1);

namespace H13\FeedPulse\Resource\App;

use BEAR\Resource\ResourceObject;
use BEAR\ToolUse\Attribute\Tool;
use H13\FeedPulse\Reason\DraftStore;
use H13\FeedPulse\Reason\HistoryStore;
use H13\FeedPulse\Reason\Publisher;
use Ray\Di\Di\Inject;

#[Tool(description: 'Publish pending drafts to configured channels', confirm: true)]
class Publish extends ResourceObject
{
    private Publisher $publisher;
    private DraftStore $draftStore;
    private HistoryStore $historyStore;

    #[Inject]
    public function __construct(Publisher $publisher, DraftStore $draftStore, HistoryStore $historyStore)
    {
        $this->publisher = $publisher;
        $this->draftStore = $draftStore;
        $this->historyStore = $historyStore;
    }

    /**
     * Publish drafts to their target channels.
     *
     * @param string|null $draftId Publish a specific draft (null = publish all)
     */
    public function onPost(?string $draftId = null): static
    {
        $drafts = $this->draftStore->loadAll();

        if ($draftId !== null) {
            $drafts = array_values(array_filter($drafts, fn ($d) => $d->id === $draftId));
        }

        if ($drafts === []) {
            $this->code = 204;
            $this->body = ['message' => 'No drafts to publish'];
            return $this;
        }

        $results = [];
        foreach ($drafts as $draft) {
            $result = $this->publisher->publish($draft);
            $results[] = $result;

            if ($result->isSuccess()) {
                $this->draftStore->delete($draft->id);
            }
        }

        $this->historyStore->save($results);

        $failures = array_filter($results, fn ($r) => ! $r->isSuccess());

        $this->code = $failures !== [] ? 207 : 200;
        $this->body = [
            'published' => count($results) - count($failures),
            'failed' => count($failures),
            'results' => array_map(fn ($r) => [
                'channel' => $r->channel,
                'title' => $r->title,
                'url' => $r->url,
                'error' => $r->error,
            ], $results),
        ];

        return $this;
    }
}
