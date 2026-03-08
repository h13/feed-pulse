<?php

declare(strict_types=1);

namespace H13\FeedPulse\Publisher;

use H13\FeedPulse\Contract\PublisherInterface;
use H13\FeedPulse\Reason\Entity\Draft;
use H13\FeedPulse\Reason\Entity\PublishResult;
use Override;
use RuntimeException;
use Throwable;

use function base64_encode;
use function curl_close;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt_array;
use function date;
use function is_string;
use function json_decode;
use function json_encode;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const JSON_THROW_ON_ERROR;

final class WordPressPublisher implements PublisherInterface
{
    public function __construct(
        private readonly string $apiUrl,
        private readonly string $user,
        private readonly string $appPassword,
        private readonly string $postStatus = 'draft',
    ) {
    }

    #[Override]
    public function publish(Draft $draft): PublishResult
    {
        try {
            $url = $this->createPost($draft);

            return new PublishResult(
                channel: $draft->channel,
                title: $draft->item->feed->title,
                url: $url,
                error: null,
                publishedAt: date('c'),
            );
        } catch (Throwable $e) {
            return new PublishResult(
                channel: $draft->channel,
                title: $draft->item->feed->title,
                url: null,
                error: $e->getMessage(),
                publishedAt: date('c'),
            );
        }
    }

    private function createPost(Draft $draft): string
    {
        $auth = base64_encode("{$this->user}:{$this->appPassword}");

        $ch = curl_init("{$this->apiUrl}/posts");
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize curl');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'title' => $draft->item->feed->title,
                'content' => $draft->content,
                'status' => $this->postStatus,
            ], JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "Authorization: Basic {$auth}",
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($ch);
        /** @var int $httpCode */
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (! is_string($response) || $httpCode >= 400) {
            throw new RuntimeException("WordPress API error {$httpCode}: {$response}");
        }

        /** @var array{link: string} $data */
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        return $data['link'];
    }
}
