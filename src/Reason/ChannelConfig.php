<?php

declare(strict_types=1);

namespace H13\FeedPulse\Reason;

use Ray\Di\Di\Named;
use Symfony\Component\Yaml\Yaml;

final class ChannelConfig
{
    private readonly string $channelsDir;

    public function __construct(
        #[Named('app_dir')]
        string $appDir,
    ) {
        $this->channelsDir = $appDir . '/config/channels';
    }

    /**
     * @return list<array<string, mixed>> Enabled channel configs (the 'channel' key contents)
     */
    public function loadEnabled(): array
    {
        $files = glob("{$this->channelsDir}/*.yaml") ?: [];

        $configs = [];
        foreach ($files as $file) {
            $data = Yaml::parseFile($file);
            if (is_array($data) && ($data['channel']['enabled'] ?? false)) {
                $configs[] = $data['channel'];
            }
        }

        return $configs;
    }
}
