<?php

declare(strict_types=1);

namespace H13\FeedPulse\Reason;

use H13\FeedPulse\Reason\Entity\Draft;
use H13\FeedPulse\Reason\Entity\PublishResult;
use Symfony\Component\Yaml\Yaml;

final class Publisher
{
    private readonly string $channelsDir;

    public function __construct(
        private readonly ?string $wordpressApiUrl = null,
        private readonly ?string $wordpressUser = null,
        private readonly ?string $wordpressAppPassword = null,
        private readonly ?string $xApiKey = null,
        private readonly ?string $xApiSecret = null,
        private readonly ?string $xAccessToken = null,
        private readonly ?string $xAccessSecret = null,
    ) {
        $this->channelsDir = dirname(__DIR__, 2) . '/config/channels';
    }

    public function publish(Draft $draft): PublishResult
    {
        $channel = $this->findChannel($draft->channel);
        if ($channel === null) {
            return $this->failure($draft, "Channel '{$draft->channel}' not found");
        }

        $type = $channel['channel']['type'] ?? '';

        try {
            $url = match ($type) {
                'wordpress' => $this->publishToWordPress($draft, $channel),
                'x' => $this->publishToX($draft),
                default => throw new \RuntimeException("Unknown channel type: {$type}"),
            };

            return new PublishResult(
                channel: $draft->channel,
                title: $draft->item->feed->title,
                url: $url,
                error: null,
                publishedAt: date('c'),
            );
        } catch (\Throwable $e) {
            return $this->failure($draft, $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $channel
     */
    private function publishToWordPress(Draft $draft, array $channel): string
    {
        if (! $this->wordpressApiUrl || ! $this->wordpressUser || ! $this->wordpressAppPassword) {
            throw new \RuntimeException('WordPress credentials not configured');
        }

        $status = $channel['channel']['publish']['status'] ?? 'draft';
        $auth = base64_encode("{$this->wordpressUser}:{$this->wordpressAppPassword}");

        $ch = curl_init("{$this->wordpressApiUrl}/posts");
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize curl');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'title' => $draft->item->feed->title,
                'content' => $draft->content,
                'status' => $status,
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

    private function publishToX(Draft $draft): string
    {
        if (! $this->xApiKey || ! $this->xApiSecret || ! $this->xAccessToken || ! $this->xAccessSecret) {
            throw new \RuntimeException('X credentials not configured');
        }

        $url = 'https://api.x.com/2/tweets';
        $body = json_encode(['text' => $draft->content], JSON_THROW_ON_ERROR);
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
        assert($this->xApiKey !== null);
        assert($this->xApiSecret !== null);
        assert($this->xAccessToken !== null);
        assert($this->xAccessSecret !== null);

        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));

        $params = [
            'oauth_consumer_key' => $this->xApiKey,
            'oauth_nonce' => $nonce,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => $timestamp,
            'oauth_token' => $this->xAccessToken,
            'oauth_version' => '1.0',
        ];

        ksort($params);
        $paramString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $baseString = implode('&', array_map('rawurlencode', [$method, $url, $paramString]));
        $signingKey = rawurlencode($this->xApiSecret) . '&' . rawurlencode($this->xAccessSecret);
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

    private function failure(Draft $draft, string $message): PublishResult
    {
        return new PublishResult(
            channel: $draft->channel,
            title: $draft->item->feed->title,
            url: null,
            error: $message,
            publishedAt: date('c'),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findChannel(string $name): ?array
    {
        $files = glob("{$this->channelsDir}/*.yaml") ?: [];
        foreach ($files as $file) {
            $config = Yaml::parseFile($file);
            if (is_array($config) && ($config['channel']['name'] ?? '') === $name) {
                return $config;
            }
        }

        return null;
    }
}
