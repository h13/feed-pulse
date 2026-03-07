<?php

declare(strict_types=1);

namespace H13\FeedPulse\Reason;

use H13\FeedPulse\Reason\Entity\Draft;

final class Notifier
{
    public function __construct(
        private readonly ?string $webhookUrl = null,
        private readonly string $repoUrl = 'https://github.com/h13/feed-pulse',
    ) {
    }

    /**
     * @param list<Draft> $drafts
     */
    public function notify(array $drafts): void
    {
        if ($this->webhookUrl === null || $this->webhookUrl === '') {
            return;
        }

        $blocks = [
            ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => "feed-pulse: " . count($drafts) . " draft(s) ready"]],
            ['type' => 'divider'],
        ];

        foreach ($drafts as $draft) {
            $preview = mb_substr($draft->content, 0, 500);
            if (mb_strlen($draft->content) > 500) {
                $preview .= '...';
            }

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => implode("\n", [
                        "*{$draft->item->feed->title}*",
                        "Channel: `{$draft->channel}` | Topics: " . implode(', ', $draft->item->matchedTopics),
                        "Source: <{$draft->item->feed->link}|Link>",
                        '',
                        $preview,
                    ]),
                ],
            ];
            $blocks[] = ['type' => 'divider'];
        }

        $blocks[] = [
            'type' => 'actions',
            'elements' => [
                ['type' => 'button', 'text' => ['type' => 'plain_text', 'text' => 'Publish All'], 'style' => 'primary', 'url' => "{$this->repoUrl}/actions/workflows/publish.yaml"],
                ['type' => 'button', 'text' => ['type' => 'plain_text', 'text' => 'Review Drafts'], 'url' => "{$this->repoUrl}/tree/main/state/drafts"],
            ],
        ];

        $this->sendWebhook($blocks);
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    private function sendWebhook(array $blocks): void
    {
        assert($this->webhookUrl !== null);

        $ch = curl_init($this->webhookUrl);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize curl');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['blocks' => $blocks], JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \RuntimeException("Slack webhook error {$httpCode}: {$response}");
        }
    }
}
