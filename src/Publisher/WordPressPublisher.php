<?php

declare(strict_types=1);

namespace H13\FeedPulse\Publisher;

use H13\FeedPulse\Contract\PublisherInterface;
use H13\FeedPulse\Reason\Entity\Draft;
use H13\FeedPulse\Reason\Entity\PublishResult;
use Ray\Di\Di\Named;

final class WordPressPublisher implements PublisherInterface
{
    public function __construct(
        #[Named('wp_api_url')]
        private readonly string $apiUrl,
        #[Named('wp_user')]
        private readonly string $user,
        #[Named('wp_password')]
        private readonly string $appPassword,
        #[Named('wp_post_status')]
        private readonly string $postStatus = 'draft',
    ) {
    }

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
        } catch (\Throwable $e) {
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
            throw new \RuntimeException('Failed to initialize curl');
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
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (! is_string($response) || $httpCode >= 400) {
            throw new \RuntimeException("WordPress API error {$httpCode}: {$response}");
        }

        /** @var array{link: string} $data */
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        return $data['link'];
    }
}
