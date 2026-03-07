<?php

declare(strict_types=1);

namespace H13\FeedPulse\Resource\App;

use BEAR\Resource\ResourceObject;
use BEAR\ToolUse\Attribute\Tool;
use H13\FeedPulse\Contract\NotifierInterface;
use H13\FeedPulse\Contract\SourceInterface;
use H13\FeedPulse\Reason\DraftStore;
use H13\FeedPulse\Reason\Generator;
use H13\FeedPulse\Reason\Matcher;
use H13\FeedPulse\Reason\StateStore;
use Ray\Di\Di\Inject;

#[Tool(description: 'List or generate content drafts from matched feed items')]
class Drafts extends ResourceObject
{
    #[Inject]
    public function __construct(
        private readonly SourceInterface $source,
        private readonly Matcher $matcher,
        private readonly Generator $generator,
        private readonly DraftStore $draftStore,
        private readonly StateStore $stateStore,
        private readonly NotifierInterface $notifier,
    ) {
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
     * @param bool $notify Send notification after generation (default: true)
     */
    public function onPost(bool $notify = true): static
    {
        $items = $this->source->fetch();
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
