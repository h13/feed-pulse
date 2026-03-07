# feed-pulse

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
docker compose run agent \
  "Check for new feed items and generate drafts"
```

## Setup

```bash
cp env.dist.json env.json
# Edit env.json with your API keys
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

### Docker (recommended)

```bash
# Generate drafts
docker compose run pipeline

# Publish approved drafts
docker compose run publish

# Agent mode
docker compose run agent \
  "Summarize today's top AI news"
```

### Configuration

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

## Workflows

| Workflow | Trigger                 | Description                  |
| -------- | ----------------------- | ---------------------------- |
| CI       | push, PR                | Build Docker image           |
| Generate | Daily 09:00 JST, manual | Crawl → generate → notify    |
| Publish  | Manual                  | Publish → history PR (merge) |

## Tech Stack

- **PHP 8.4** on Alpine Linux (Docker, SHA-pinned)
- **BEAR.Sunday** — Resource-oriented framework
- **Be Framework** — Ontological programming
- **ALPS** — Application-Level Profile Semantics
- **BEAR.ToolUse** — LLM agent with human-in-the-loop
- **koriym/env-json** — JSON Schema env validation

## License

MIT
