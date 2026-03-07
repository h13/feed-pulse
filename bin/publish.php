<?php

declare(strict_types=1);

use BEAR\Package\Injector;
use BEAR\Resource\ResourceInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

$resource = Injector::getInstance('H13\FeedPulse', 'app', dirname(__DIR__))
    ->getInstance(ResourceInterface::class);

$draftId = $argv[1] ?? null;

echo "[publish] Publishing " . ($draftId ? "draft: {$draftId}" : "all drafts") . "\n";

$params = $draftId !== null ? ['draftId' => $draftId] : [];
$result = $resource->post('app://self/publish', $params);

if ($result->code === 204) {
    echo "[publish] No drafts to publish\n";
    exit(0);
}

echo "[publish] Published: {$result->body['published']}, Failed: {$result->body['failed']}\n";

foreach ($result->body['results'] as $r) {
    if ($r['error']) {
        echo "  FAIL [{$r['channel']}] {$r['title']}: {$r['error']}\n";
    } else {
        echo "  OK   [{$r['channel']}] {$r['title']}: {$r['url']}\n";
    }
}
