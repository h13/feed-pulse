<?php

declare(strict_types=1);

namespace H13\FeedPulse\Module;

use BEAR\Package\AbstractAppModule;
use BEAR\Package\PackageModule;
use BEAR\ToolUse\LlmClientInterface;
use BEAR\ToolUse\ToolUseModule;
use H13\FeedPulse\Llm\ClaudeClient;
use H13\FeedPulse\Reason\Generator;
use H13\FeedPulse\Reason\Notifier;
use H13\FeedPulse\Reason\Publisher;
use Ray\Di\AbstractModule;

class AppModule extends AbstractAppModule
{
    protected function configure(): void
    {
        $this->install(new PackageModule());
        $this->install(new ToolUseModule());

        $env = $this->loadEnv();

        // Generator (requires API key)
        $this->bind(Generator::class)
            ->toConstructor(Generator::class, ['apiKey' => 'anthropic_api_key']);
        $this->bind()->annotatedWith('anthropic_api_key')->toInstance($env['ANTHROPIC_API_KEY'] ?? '');

        // Notifier
        $this->bind(Notifier::class)
            ->toConstructor(Notifier::class, [
                'webhookUrl' => 'slack_webhook_url',
                'repoUrl' => 'repo_url',
            ]);
        $this->bind()->annotatedWith('slack_webhook_url')->toInstance($env['SLACK_WEBHOOK_URL'] ?? null);
        $this->bind()->annotatedWith('repo_url')->toInstance('https://github.com/h13/feed-pulse');

        // Publisher
        $this->bind(Publisher::class)
            ->toConstructor(Publisher::class, [
                'wordpressApiUrl' => 'wp_api_url',
                'wordpressUser' => 'wp_user',
                'wordpressAppPassword' => 'wp_password',
                'xApiKey' => 'x_api_key',
                'xApiSecret' => 'x_api_secret',
                'xAccessToken' => 'x_access_token',
                'xAccessSecret' => 'x_access_secret',
            ]);
        $this->bind()->annotatedWith('wp_api_url')->toInstance($env['WORDPRESS_API_URL'] ?? null);
        $this->bind()->annotatedWith('wp_user')->toInstance($env['WORDPRESS_USER'] ?? null);
        $this->bind()->annotatedWith('wp_password')->toInstance($env['WORDPRESS_APP_PASSWORD'] ?? null);
        $this->bind()->annotatedWith('x_api_key')->toInstance($env['X_API_KEY'] ?? null);
        $this->bind()->annotatedWith('x_api_secret')->toInstance($env['X_API_SECRET'] ?? null);
        $this->bind()->annotatedWith('x_access_token')->toInstance($env['X_ACCESS_TOKEN'] ?? null);
        $this->bind()->annotatedWith('x_access_secret')->toInstance($env['X_ACCESS_SECRET'] ?? null);

        // BEAR.ToolUse LLM Client
        $this->bind(LlmClientInterface::class)->to(ClaudeClient::class);
    }

    /**
     * @return array<string, string>
     */
    private function loadEnv(): array
    {
        $envFile = $this->appDir . '/env.json';
        if (! file_exists($envFile)) {
            // Fallback to environment variables
            return [
                'ANTHROPIC_API_KEY' => getenv('ANTHROPIC_API_KEY') ?: '',
                'SLACK_WEBHOOK_URL' => getenv('SLACK_WEBHOOK_URL') ?: '',
                'WORDPRESS_API_URL' => getenv('WORDPRESS_API_URL') ?: '',
                'WORDPRESS_USER' => getenv('WORDPRESS_USER') ?: '',
                'WORDPRESS_APP_PASSWORD' => getenv('WORDPRESS_APP_PASSWORD') ?: '',
                'X_API_KEY' => getenv('X_API_KEY') ?: '',
                'X_API_SECRET' => getenv('X_API_SECRET') ?: '',
                'X_ACCESS_TOKEN' => getenv('X_ACCESS_TOKEN') ?: '',
                'X_ACCESS_SECRET' => getenv('X_ACCESS_SECRET') ?: '',
            ];
        }

        $raw = file_get_contents($envFile);
        if ($raw === false) {
            throw new \RuntimeException('Failed to read env.json');
        }

        /** @var array<string, string> */
        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
