<?php

declare(strict_types=1);

namespace H13\FeedPulse\Llm;

use Ray\Di\Di\Named;
use RuntimeException;

use function curl_close;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt_array;
use function is_string;
use function json_decode;
use function json_encode;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const JSON_THROW_ON_ERROR;

/** Generic HTTP client for OpenAI-compatible LLM APIs */
final class LlmHttpClient
{
    public function __construct(
        #[Named('llm_api_url')]
        private readonly string $apiUrl,
        #[Named('llm_api_key')]
        private readonly string $apiKey,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function request(array $payload): array
    {
        $ch = curl_init($this->apiUrl);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize curl');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "Authorization: Bearer {$this->apiKey}",
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($ch);
        /** @var int $httpCode */
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (! is_string($response) || $httpCode !== 200) {
            throw new RuntimeException("LLM API error {$httpCode}: {$response}");
        }

        /** @var array<string, mixed> $result */
        $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        return $result;
    }
}
