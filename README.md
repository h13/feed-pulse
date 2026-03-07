# feed-pulse

[![CI](https://github.com/h13/feed-pulse/actions/workflows/ci.yml/badge.svg)](https://github.com/h13/feed-pulse/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![BEAR.Sunday](https://img.shields.io/badge/BEAR.Sunday-Framework-orange)](https://bearsunday.github.io/)
[![ALPS](https://img.shields.io/badge/ALPS-Profile-blue)](https://alps-asd.github.io/spec/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

Automated content pipeline that crawls RSS feeds, matches
them against your interests, generates content via Claude
API, and publishes to configured channels.

Built with [BEAR.Sunday](https://bearsunday.github.io/) +
[Be Framework](https://github.com/be-framework) +
[ALPS](https://github.com/alps-asd).

## Architecture

```text
crawl (RSS)
  → match (interest scoring)
    → generate (Claude API)
      → draft (Slack notification + review)
        → publish (WordPress / X)
          → history (PR auto-merge)
```

### BEAR.Sunday Resources

| Resource              | Method | Description                              |
| --------------------- | ------ | ---------------------------------------- |
| `app://self/feed`     | GET    | Crawl RSS feeds, score against interests |
| `app://self/drafts`   | GET    | List pending drafts                      |
| `app://self/drafts`   | POST   | Generate drafts from matched feeds       |
| `app://self/publish`  | POST   | Publish drafts (confirm required)        |
| `app://self/history`  | GET    | View publish history                     |

### BEAR.ToolUse Agent Mode

Resources are annotated with `#[Tool]` attributes,
enabling an LLM agent to autonomously operate the
pipeline:

```bash
docker compose run --rm app php bin/agent.php \
  "Check for new feed items and generate drafts"
```

## Setup

### Requirements

- PHP 8.4+ with curl and xml extensions
- Composer 2.x

### Local Development

```bash
git clone https://github.com/h13/feed-pulse.git
cd feed-pulse

# Configure environment
cp env.dist.json env.json
# Edit env.json with your API keys

# Install dependencies
composer install
```

### Docker (alternative)

```bash
# Build once
docker compose build

# Run any command
docker compose run --rm app composer install
docker compose run --rm app php bin/pipeline.php
docker compose run --rm app composer tests
```

### Environment Variables

| Variable                 | Required | Description                    |
| ------------------------ | -------- | ------------------------------ |
| `ANTHROPIC_API_KEY`      | Yes      | Claude API key                 |
| `SLACK_WEBHOOK_URL`      | No       | Slack webhook for notification |
| `WORDPRESS_API_URL`      | No       | WordPress REST API base URL    |
| `WORDPRESS_USER`         | No       | WordPress username             |
| `WORDPRESS_APP_PASSWORD` | No       | WordPress app password         |
| `X_API_KEY`              | No       | X API consumer key             |
| `X_API_SECRET`           | No       | X API consumer secret          |
| `X_ACCESS_TOKEN`         | No       | X access token                 |
| `X_ACCESS_SECRET`        | No       | X access token secret          |

## Usage

```bash
# Run full pipeline (crawl → match → generate drafts)
composer pipeline

# Publish approved drafts
composer publish

# Agent mode
composer agent

# Run with fake context (no API calls)
composer fake
```

## QA

```bash
# All checks (cs + sa + test)
composer tests

# Individual
composer cs        # Coding standards (phpcs)
composer cs-fix    # Auto-fix (phpcbf)
composer sa        # Static analysis (phpstan + psalm + phpmd)
composer test      # PHPUnit
composer coverage  # Coverage report
composer metrics   # PHPMetrics
```

## Configuration

**RSS Sources** — `config/sources.yaml`

```yaml
sources:
  - name: Hacker News
    url: https://hnrss.org/best
    category: tech
```

**Interest Topics** — `config/interests.yaml`

```yaml
interests:
  - topic: AI & LLM
    keywords:
      - artificial intelligence
      - LLM
      - Claude
      - GPT
    weight: 1.0
```

**Channels** — `config/channels/*.yaml`

Each channel defines persona, tone, and publish settings.
Set `enabled: true` to activate.

**Voice** — `prompts/voice.md`

Writing style definition used in content generation.
Add past posts to `prompts/examples/` as few-shot
references.

## Tech Stack

- **PHP 8.4** — Runtime
- **BEAR.Sunday** — Resource-oriented framework
- **Be Framework** — Ontological programming
- **ALPS** — Application-Level Profile Semantics
- **BEAR.ToolUse** — LLM agent with human-in-the-loop
- **BEAR.QATools** — PHPStan (max), Psalm (level 1), PHPCS, PHPMD, PHPUnit
- **koriym/env-json** — JSON Schema env validation
- **Claude API** (Haiku 4.5) — Content generation

## License

MIT
