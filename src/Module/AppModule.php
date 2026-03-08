<?php

declare(strict_types=1);

namespace H13\FeedPulse\Module;

use Be\Framework\Module\BeModule;
use BEAR\Package\AbstractAppModule;
use BEAR\Package\PackageModule;
use BEAR\ToolUse\Llm\LlmClientInterface;
use BEAR\ToolUse\Module\ToolUseModule;
use H13\FeedPulse\Contract\LlmInterface;
use H13\FeedPulse\Contract\MatcherInterface;
use H13\FeedPulse\Contract\NotifierInterface;
use H13\FeedPulse\Contract\PublisherInterface;
use H13\FeedPulse\Contract\SourceInterface;
use H13\FeedPulse\Llm\ClaudeClient;
use H13\FeedPulse\Llm\ClaudeLlm;
use H13\FeedPulse\Llm\GlmLlm;
use H13\FeedPulse\Notifier\NullNotifier;
use H13\FeedPulse\Notifier\SlackNotifier;
use H13\FeedPulse\Publisher\PublisherPool;
use H13\FeedPulse\Publisher\WordPressPublisher;
use H13\FeedPulse\Publisher\XPublisher;
use H13\FeedPulse\Reason\ChannelConfig;
use H13\FeedPulse\Reason\Matcher;
use H13\FeedPulse\Source\RssSource;
use Koriym\EnvJson\EnvJson;
use Override;

use function getenv;

class AppModule extends AbstractAppModule
{
    #[Override]
    protected function configure(): void
    {
        $this->install(new PackageModule());
        $this->install(new ToolUseModule());
        $this->install(new BeModule('H13\\FeedPulse\\Semantic'));

        $appDir = $this->appMeta->appDir;

        (new EnvJson())->load($appDir);

        // Shared
        $this->bind()->annotatedWith('app_dir')->toInstance($appDir);
        $this->bind()->annotatedWith('anthropic_api_key')->toInstance(self::env('ANTHROPIC_API_KEY'));
        $this->bind()->annotatedWith('repo_url')->toInstance('https://github.com/h13/feed-pulse');

        // Source
        $this->bind(SourceInterface::class)->to(RssSource::class);

        // Matcher
        $this->bind(MatcherInterface::class)->to(Matcher::class);

        // LLM
        $this->bindLlm();
        $this->bind(LlmClientInterface::class)->to(ClaudeClient::class);

        // Notifier
        $this->bindNotifier();

        // Publisher
        $this->bind(PublisherPool::class)->toInstance($this->buildPublisherPool());
    }

    private function bindLlm(): void
    {
        $glmApiKey = self::env('GLM_API_KEY');

        if ($glmApiKey !== '') {
            $apiUrl = self::env('GLM_API_URL');
            if ($apiUrl === '') {
                $apiUrl = 'https://api.z.ai/api/coding/paas/v4/chat/completions';
            }

            $this->bind()->annotatedWith('llm_api_url')->toInstance($apiUrl);
            $this->bind()->annotatedWith('llm_api_key')->toInstance($glmApiKey);
            $this->bind(LlmInterface::class)->to(GlmLlm::class);

            return;
        }

        $this->bind(LlmInterface::class)->to(ClaudeLlm::class);
    }

    private function bindNotifier(): void
    {
        $webhookUrl = self::env('SLACK_WEBHOOK_URL');

        if ($webhookUrl !== '') {
            $this->bind()->annotatedWith('slack_webhook_url')->toInstance($webhookUrl);
            $this->bind(NotifierInterface::class)->to(SlackNotifier::class);

            return;
        }

        $this->bind(NotifierInterface::class)->to(NullNotifier::class);
    }

    private function buildPublisherPool(): PublisherPool
    {
        $channelConfig = new ChannelConfig($this->appMeta->appDir);
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
        $key = self::env('X_API_KEY');
        $secret = self::env('X_API_SECRET');
        $token = self::env('X_ACCESS_TOKEN');
        $tokenSecret = self::env('X_ACCESS_SECRET');

        if ($key === '' || $secret === '' || $token === '' || $tokenSecret === '') {
            return null;
        }

        return new XPublisher($key, $secret, $token, $tokenSecret);
    }

    /** @param array<string, mixed> $config */
    private function createWordPressPublisher(array $config): WordPressPublisher|null
    {
        $url = self::env('WORDPRESS_API_URL');
        $user = self::env('WORDPRESS_USER');
        $password = self::env('WORDPRESS_APP_PASSWORD');

        if ($url === '' || $user === '' || $password === '') {
            return null;
        }

        /** @var array<string, mixed> $publish */
        $publish = $config['publish'] ?? [];
        /** @var string $status */
        $status = $publish['status'] ?? 'draft';

        return new WordPressPublisher($url, $user, $password, $status);
    }

    private static function env(string $name): string
    {
        $value = getenv($name);

        return $value !== false ? $value : '';
    }
}
