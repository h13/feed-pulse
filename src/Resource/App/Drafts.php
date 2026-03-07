<?php

declare(strict_types=1);

namespace H13\FeedPulse\Resource\App;

use BEAR\Resource\ResourceObject;
use BEAR\ToolUse\Attribute\Tool;
use H13\FeedPulse\Contract\MatcherInterface;
use H13\FeedPulse\Contract\NotifierInterface;
use H13\FeedPulse\Contract\SourceInterface;
use H13\FeedPulse\Reason\DraftStore;
use H13\FeedPulse\Reason\Generator;
use H13\FeedPulse\Reason\StateStore;
use Ray\Di\Di\Named;
use Ray\Di\Di\Inject;
use Symfony\Component\Yaml\Yaml;

#[Tool(description: 'List or generate content drafts from matched feed items')]
class Drafts extends ResourceObject
{
    private readonly string $channelsDir;

    #[Inject]
    public function __construct(
        private readonly SourceInterface $source,
        private readonly MatcherInterface $matcher,
        private readonly Generator $generator,
        private readonly DraftStore $draftStore,
        private readonly StateStore $stateStore,
        private readonly NotifierInterface $notifier,
        #[Named('app_dir')]
        string $appDir,
    ) {
        $this->channelsDir = $appDir . '/config/channels';
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

        $channels = $this->loadEnabledChannels();
        $drafts = [];

        foreach ($channels as $channelConfig) {
            $limit = $channelConfig['publish']['max_per_day'] ?? 5;
            $targets = array_slice($newItems, 0, $limit);

            foreach ($targets as $item) {
                $drafts[] = $this->generator->generate($item, $channelConfig);
            }
        }

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

    /** @return list<array<string, mixed>> */
    private function loadEnabledChannels(): array
    {
        $files = glob("{$this->channelsDir}/*.yaml") ?: [];

        $configs = [];
        foreach ($files as $file) {
            $data = Yaml::parseFile($file);
            if (is_array($data) && ($data['channel']['enabled'] ?? false)) {
                $configs[] = $data['channel'];
            }
        }

        return $configs;
    }
}
