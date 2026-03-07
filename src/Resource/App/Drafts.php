<?php

declare(strict_types=1);

namespace H13\FeedPulse\Resource\App;

use BEAR\Resource\ResourceObject;
use BEAR\ToolUse\Attribute\Tool;
use H13\FeedPulse\Reason\Crawler;
use H13\FeedPulse\Reason\DraftStore;
use H13\FeedPulse\Reason\Generator;
use H13\FeedPulse\Reason\Matcher;
use H13\FeedPulse\Reason\Notifier;
use H13\FeedPulse\Reason\StateStore;
use Ray\Di\Di\Inject;

#[Tool(description: 'List or generate content drafts from matched feed items')]
class Drafts extends ResourceObject
{
    private Crawler $crawler;
    private Matcher $matcher;
    private Generator $generator;
    private DraftStore $draftStore;
    private StateStore $stateStore;
    private Notifier $notifier;

    #[Inject]
    public function __construct(
        Crawler $crawler,
        Matcher $matcher,
        Generator $generator,
        DraftStore $draftStore,
        StateStore $stateStore,
        Notifier $notifier,
    ) {
        $this->crawler = $crawler;
        $this->matcher = $matcher;
        $this->generator = $generator;
        $this->draftStore = $draftStore;
        $this->stateStore = $stateStore;
        $this->notifier = $notifier;
    }

    /** List all pending drafts */
    public function onGet(): static
    {
        $drafts = $this->draftStore->loadAll();

        $this->body = [
            'count' => count($drafts),
            'drafts' => array_map(fn ($d) => [
                'id' => $d->id,
                'channel' => $d->channel,
                'title' => $d->item->feed->title,
                'content' => $d->content,
                'createdAt' => $d->createdAt,
            ], $drafts),
        ];

        return $this;
    }

    /**
     * Generate new drafts from matched feed items.
     *
     * @param bool $notify Send Slack notification after generation (default: true)
     */
    public function onPost(bool $notify = true): static
    {
        $items = $this->crawler->crawl();
        $matched = $this->matcher->match($items);

        $newItems = array_values(array_filter(
            $matched,
            fn ($item) => ! $this->stateStore->isProcessed($item->feed->link),
        ));

        if ($newItems === []) {
            $this->code = 204;
            $this->body = ['message' => 'No new items to process'];
            return $this;
        }

        $drafts = $this->generator->generate($newItems);

        foreach ($drafts as $draft) {
            $this->draftStore->save($draft);
        }

        $this->stateStore->markProcessed(
            array_map(fn ($item) => $item->feed->link, $newItems),
        );

        if ($notify) {
            $this->notifier->notify($drafts);
        }

        $this->code = 201;
        $this->body = [
            'count' => count($drafts),
            'drafts' => array_map(fn ($d) => [
                'id' => $d->id,
                'channel' => $d->channel,
                'title' => $d->item->feed->title,
            ], $drafts),
        ];

        return $this;
    }
}
