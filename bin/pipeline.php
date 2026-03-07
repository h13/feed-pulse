<?php

declare(strict_types=1);

use BEAR\Package\Injector;
use BEAR\Resource\ResourceInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

$resource = Injector::getInstance('H13\FeedPulse', 'app', dirname(__DIR__))
    ->getInstance(ResourceInterface::class);

echo "[pipeline] Starting feed-pulse pipeline...\n";

// Phase 1: Crawl + Match
echo "[crawl+match] Fetching and scoring feeds...\n";
$feeds = $resource->get('app://self/feed');
echo "[crawl+match] Found {$feeds->body['count']} matched item(s)\n";

if ($feeds->body['count'] === 0) {
    echo "[done] Nothing new to process\n";
    return;
}

foreach ($feeds->body['items'] as $item) {
    echo sprintf(
        "\n--- [%.1f] %s\n    Topics: %s\n    Source: %s\n",
        $item['score'],
        $item['title'],
        implode(', ', $item['matchedTopics']),
        $item['source'],
    );
}

// Phase 2: Generate drafts
echo "\n[generate] Generating drafts...\n";
$drafts = $resource->post('app://self/drafts');

if ($drafts->code === 204) {
    echo "[done] No new items to process\n";
    return;
}

echo "[generate] Created {$drafts->body['count']} draft(s)\n";
foreach ($drafts->body['drafts'] as $draft) {
    echo "  - [{$draft['channel']}] {$draft['title']}\n";
}

echo "\n[done] Drafts saved. Awaiting review.\n";
