<?php

declare(strict_types=1);

use BEAR\Package\Injector;
use BEAR\ToolUse\AgentFactoryInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

$injector = Injector::getInstance('H13\FeedPulse', 'app', dirname(__DIR__));
$factory = $injector->getInstance(AgentFactoryInterface::class);

$agent = $factory
    ->addResources([
        'app://self/feed',
        'app://self/drafts',
        'app://self/publish',
        'app://self/history',
    ])
    ->create(<<<'PROMPT'
You are feed-pulse, an automated content pipeline assistant.
You help the user crawl RSS feeds, match them against interests,
generate content drafts, and publish to configured channels.

Available actions:
- GET feed: Crawl and score RSS feeds against interests
- GET drafts: List pending drafts
- POST drafts: Generate new drafts from matched feeds
- POST publish: Publish drafts to channels (requires confirmation)
- GET history: View publish history

Always explain what you're about to do before taking action.
PROMPT);

$prompt = $argv[1] ?? 'Check for new feed items and generate drafts if any match my interests.';
echo "[agent] Running: {$prompt}\n\n";

$response = $agent->run($prompt);
echo $response . "\n";
