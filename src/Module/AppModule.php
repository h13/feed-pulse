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
use Koriym\EnvJson\EnvJson;

class AppModule extends AbstractAppModule
{
    protected function configure(): void
    {
        $this->install(new PackageModule());
        $this->install(new ToolUseModule());

        (new EnvJson())->load($this->appDir);

        // Generator (requires API key)
        $this->bind(Generator::class)
            ->toConstructor(Generator::class, ['apiKey' => 'anthropic_api_key']);
        $this->bind()->annotatedWith('anthropic_api_key')->toInstance(getenv('ANTHROPIC_API_KEY') ?: '');

        // Notifier
        $this->bind(Notifier::class)
            ->toConstructor(Notifier::class, [
                'webhookUrl' => 'slack_webhook_url',
                'repoUrl' => 'repo_url',
            ]);
        $this->bind()->annotatedWith('slack_webhook_url')->toInstance(getenv('SLACK_WEBHOOK_URL') ?: null);
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
        $this->bind()->annotatedWith('wp_api_url')->toInstance(getenv('WORDPRESS_API_URL') ?: null);
        $this->bind()->annotatedWith('wp_user')->toInstance(getenv('WORDPRESS_USER') ?: null);
        $this->bind()->annotatedWith('wp_password')->toInstance(getenv('WORDPRESS_APP_PASSWORD') ?: null);
        $this->bind()->annotatedWith('x_api_key')->toInstance(getenv('X_API_KEY') ?: null);
        $this->bind()->annotatedWith('x_api_secret')->toInstance(getenv('X_API_SECRET') ?: null);
        $this->bind()->annotatedWith('x_access_token')->toInstance(getenv('X_ACCESS_TOKEN') ?: null);
        $this->bind()->annotatedWith('x_access_secret')->toInstance(getenv('X_ACCESS_SECRET') ?: null);

        // BEAR.ToolUse LLM Client
        $this->bind(LlmClientInterface::class)->to(ClaudeClient::class);
    }
}
