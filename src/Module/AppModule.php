<?php

declare(strict_types=1);

namespace H13\FeedPulse\Module;

use BEAR\Package\AbstractAppModule;
use BEAR\Package\PackageModule;
use BEAR\ToolUse\LlmClientInterface;
use BEAR\ToolUse\ToolUseModule;
use H13\FeedPulse\Contract\LlmInterface;
use H13\FeedPulse\Contract\MatcherInterface;
use H13\FeedPulse\Contract\NotifierInterface;
use H13\FeedPulse\Contract\PublisherInterface;
use H13\FeedPulse\Contract\SourceInterface;
use H13\FeedPulse\Llm\ClaudeClient;
use H13\FeedPulse\Llm\ClaudeLlm;
use H13\FeedPulse\Notifier\NullNotifier;
use H13\FeedPulse\Notifier\SlackNotifier;
use H13\FeedPulse\Publisher\PublisherPool;
use H13\FeedPulse\Publisher\WordPressPublisher;
use H13\FeedPulse\Publisher\XPublisher;
use H13\FeedPulse\Reason\ChannelConfig;
use H13\FeedPulse\Reason\Matcher;
use H13\FeedPulse\Source\RssSource;
use Koriym\EnvJson\EnvJson;

use function assert;
use function getenv;
use function is_string;

class AppModule extends AbstractAppModule
{
    protected function configure(): void
    {
        $this->install(new PackageModule());
        $this->install(new ToolUseModule());

        assert(is_string($this->appDir));
        $appDir = $this->appDir;

        (new EnvJson())->load($appDir);

        // Shared
        $this->bind()->annotatedWith('app_dir')->toInstance($appDir);
        $this->bind()->annotatedWith('anthropic_api_key')->toInstance(getenv('ANTHROPIC_API_KEY') ?: '');
        $this->bind()->annotatedWith('repo_url')->toInstance('https://github.com/h13/feed-pulse');

        // Source
        $this->bind(SourceInterface::class)->to(RssSource::class);

        // Matcher
        $this->bind(MatcherInterface::class)->to(Matcher::class);

        // LLM
        $this->bind(LlmInterface::class)->to(ClaudeLlm::class);
        $this->bind(LlmClientInterface::class)->to(ClaudeClient::class);

        // Notifier
        $this->bindNotifier();

        // Publisher
        $this->bind(PublisherPool::class)->toInstance($this->buildPublisherPool());
    }

    private function bindNotifier(): void
    {
        $webhookUrl = getenv('SLACK_WEBHOOK_URL') ?: '';

        if ($webhookUrl !== '') {
            $this->bind()->annotatedWith('slack_webhook_url')->toInstance($webhookUrl);
            $this->bind(NotifierInterface::class)->to(SlackNotifier::class);

            return;
        }

        $this->bind(NotifierInterface::class)->to(NullNotifier::class);
    }

    private function buildPublisherPool(): PublisherPool
    {
        assert(is_string($this->appDir));
        $channelConfig = new ChannelConfig($this->appDir);
        $channels = $channelConfig->loadEnabled();
        $publishers = [];

        foreach ($channels as $config) {
            /** @var string $name */
            $name = $config['name'] ?? '';
            /** @var string $type */
            $type = $config['type'] ?? '';
            $publisher = $this->createPublisher($type, $config);

            if ($publisher === null) {
                continue;
            }

            $publishers[$name] = $publisher;
        }

        return new PublisherPool($publishers);
    }

    /** @param array<string, mixed> $config */
    private function createPublisher(string $type, array $config): PublisherInterface|null
    {
        return match ($type) {
            'x' => $this->createXPublisher(),
            'wordpress' => $this->createWordPressPublisher($config),
            default => null,
        };
    }

    private function createXPublisher(): XPublisher|null
    {
        $key = getenv('X_API_KEY') ?: '';
        $secret = getenv('X_API_SECRET') ?: '';
        $token = getenv('X_ACCESS_TOKEN') ?: '';
        $tokenSecret = getenv('X_ACCESS_SECRET') ?: '';

        if ($key === '' || $secret === '' || $token === '' || $tokenSecret === '') {
            return null;
        }

        return new XPublisher($key, $secret, $token, $tokenSecret);
    }

    /** @param array<string, mixed> $config */
    private function createWordPressPublisher(array $config): WordPressPublisher|null
    {
        $url = getenv('WORDPRESS_API_URL') ?: '';
        $user = getenv('WORDPRESS_USER') ?: '';
        $password = getenv('WORDPRESS_APP_PASSWORD') ?: '';

        if ($url === '' || $user === '' || $password === '') {
            return null;
        }

        /** @var array<string, mixed> $publish */
        $publish = $config['publish'] ?? [];
        /** @var string $status */
        $status = $publish['status'] ?? 'draft';

        return new WordPressPublisher($url, $user, $password, $status);
    }
}
