<?php

declare(strict_types=1);

namespace H13\FeedPulse\Resource\App;

use Be\Framework\BecomingInterface;
use BEAR\Resource\ResourceObject;
use BEAR\ToolUse\Attribute\Tool;
use H13\FeedPulse\Being\BeDraft;
use H13\FeedPulse\Being\ScoredItemForChannel;
use H13\FeedPulse\Contract\MatcherInterface;
use H13\FeedPulse\Contract\NotifierInterface;
use H13\FeedPulse\Contract\SourceInterface;
use H13\FeedPulse\Reason\ChannelConfig;
use H13\FeedPulse\Reason\DraftStore;
use H13\FeedPulse\Reason\Entity\Draft;
use H13\FeedPulse\Reason\StateStore;
use Ray\Di\Di\Inject;

use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function assert;
use function count;

#[Tool(description: 'List or generate content drafts from matched feed items')]
class Drafts extends ResourceObject
{
    #[Inject]
    public function __construct(
        private readonly SourceInterface $source,
        private readonly MatcherInterface $matcher,
        private readonly BecomingInterface $becoming,
        private readonly DraftStore $draftStore,
        private readonly StateStore $stateStore,
        private readonly NotifierInterface $notifier,
        private readonly ChannelConfig $channelConfig,
    ) {
    }

    /** List all pending drafts */
    public function onGet(): static
    {
        $drafts = $this->draftStore->loadAll();

        $this->body = [
            'count' => count($drafts),
            'drafts' => array_map(static fn ($d) => [
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

        $channels = $this->channelConfig->loadEnabled();
        $drafts = [];

        foreach ($channels as $channelCfg) {
            /** @var array<string, mixed> $publish */
            $publish = $channelCfg['publish'] ?? [];
            /** @var int $limit */
            $limit = $publish['max_per_day'] ?? 5;
            /** @var string $channelName */
            $channelName = $channelCfg['name'] ?? '';
            /** @var string $channelType */
            $channelType = $channelCfg['type'] ?? 'x';
            $targets = array_slice($newItems, 0, $limit);

            foreach ($targets as $item) {
                $entry = new ScoredItemForChannel(
                    item: $item,
                    channel: $channelName,
                    channelType: $channelType,
                    channelConfig: $channelCfg,
                );
                $beDraft = ($this->becoming)($entry);
                assert($beDraft instanceof BeDraft);

                $drafts[] = new Draft(
                    id: $beDraft->id,
                    channel: $beDraft->channel,
                    content: $beDraft->content,
                    item: $beDraft->item,
                    createdAt: $beDraft->createdAt,
                );
            }
        }

        foreach ($drafts as $draft) {
            $this->draftStore->save($draft);
        }

        $this->stateStore->markProcessed(
            array_map(static fn ($item) => $item->feed->link, $newItems),
        );

        if ($notify) {
            $this->notifier->notify($drafts);
        }

        $this->code = 201;
        $this->body = [
            'count' => count($drafts),
            'drafts' => array_map(static fn ($d) => [
                'id' => $d->id,
                'channel' => $d->channel,
                'title' => $d->item->feed->title,
            ], $drafts),
        ];

        return $this;
    }
}
