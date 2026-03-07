<?php

declare(strict_types=1);

namespace H13\FeedPulse\Publisher;

use H13\FeedPulse\Contract\PublisherInterface;
use H13\FeedPulse\Reason\Entity\Draft;
use H13\FeedPulse\Reason\Entity\PublishResult;
use Ray\Di\Di\Named;

final class XPublisher implements PublisherInterface
{
    public function __construct(
        #[Named('x_api_key')]
        private readonly string $apiKey,
        #[Named('x_api_secret')]
        private readonly string $apiSecret,
        #[Named('x_access_token')]
        private readonly string $accessToken,
        #[Named('x_access_secret')]
        private readonly string $accessSecret,
    ) {
    }

    public function publish(Draft $draft): PublishResult
    {
        try {
            $url = $this->postTweet($draft->content);

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

    private function postTweet(string $text): string
    {
        $url = 'https://api.x.com/2/tweets';
        $body = json_encode(['text' => $text], JSON_THROW_ON_ERROR);
        $authHeader = $this->buildOAuthHeader('POST', $url);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize curl');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "Authorization: {$authHeader}",
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (! is_string($response) || $httpCode >= 400) {
            throw new \RuntimeException("X API error {$httpCode}: {$response}");
        }

        /** @var array{data: array{id: string}} $data */
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        return "https://x.com/i/status/{$data['data']['id']}";
    }

    private function buildOAuthHeader(string $method, string $url): string
    {
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));

        $params = [
            'oauth_consumer_key' => $this->apiKey,
            'oauth_nonce' => $nonce,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => $timestamp,
            'oauth_token' => $this->accessToken,
            'oauth_version' => '1.0',
        ];

        ksort($params);
        $paramString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $baseString = implode('&', array_map('rawurlencode', [$method, $url, $paramString]));
        $signingKey = rawurlencode($this->apiSecret) . '&' . rawurlencode($this->accessSecret);
        $signature = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));

        $params['oauth_signature'] = $signature;
        ksort($params);

        $header = implode(', ', array_map(
            fn (string $k, string $v) => rawurlencode($k) . '="' . rawurlencode($v) . '"',
            array_keys($params),
            array_values($params),
        ));

        return "OAuth {$header}";
    }
}
