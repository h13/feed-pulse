<?php

declare(strict_types=1);

namespace H13\FeedPulse\Being;

use H13\FeedPulse\Contract\LlmInterface;
use H13\FeedPulse\Reason\Entity\FeedItem;
use H13\FeedPulse\Reason\Entity\ScoredItem;
use H13\FeedPulse\Reason\PromptBuilder;
use Ray\Di\Di\Inject;
use Ray\InputQuery\Attribute\Input;

use function date;
use function is_array;
use function preg_replace;
use function strtolower;
use function substr;

/**
 * Draft being — the state of generated content awaiting review
 *
 * Receives scored item data via #[Input] and generates content
 * through LLM in the constructor (metamorphosis).
 *
 * Terminal state for the generation chain.
 *
 * @link alps/profile.xml#Draft ALPS state
 */
final readonly class BeDraft
{
    public string $id;
    public string $content;
    public ScoredItem $item;
    public string $createdAt;

    /**
     * @param list<string>         $matchedTopics
     * @param array<string, mixed> $channelConfig
     */
    public function __construct(
        #[Input]
        public FeedItem $feed,
        #[Input]
        public float $score,
        #[Input]
        public array $matchedTopics,
        #[Input]
        public string $channel,
        #[Input]
        public string $channelType,
        #[Input]
        public array $channelConfig,
        #[Inject]
        LlmInterface $llm,
        #[Inject]
        PromptBuilder $promptBuilder,
    ) {
        $this->item = new ScoredItem(
            feed: $feed,
            score: $score,
            matchedTopics: $matchedTopics,
        );

        /** @var array<string, mixed> $persona */
        $persona = is_array($channelConfig['persona'] ?? null) ? $channelConfig['persona'] : [];

        $systemPrompt = $promptBuilder->buildSystemPrompt($persona);
        $userPrompt = $promptBuilder->buildUserPrompt($channelType, $this->item);
        $this->content = $llm->generate($systemPrompt, $userPrompt);

        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($feed->title)) ?? '';
        $this->id = $channel . '-' . substr($slug, 0, 60);
        $this->createdAt = date('c');
    }
}
