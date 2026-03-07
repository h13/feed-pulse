import Anthropic from "@anthropic-ai/sdk"
import { readFileSync, readdirSync } from "node:fs"
import { resolve } from "node:path"
import { env } from "./env.js"
import {
  loadEnabledChannels,
  type ChannelConfig,
  type ChannelPersona,
} from "./channels.js"
import type { ScoredItem } from "./match.js"

export interface GeneratedContent {
  item: ScoredItem
  channel: string
  content: string
}

const client = new Anthropic({ apiKey: env.ANTHROPIC_API_KEY })

function loadPrompt(name: string): string {
  return readFileSync(
    resolve(import.meta.dirname, `../prompts/${name}.md`),
    "utf-8",
  )
}

function buildPromptForChannel(
  channel: ChannelConfig,
  item: ScoredItem,
): string {
  const type = channel.channel.type
  const promptName = type === "wordpress" ? "blog-article" : "sns-post"
  const template = loadPrompt(promptName)

  return template
    .replace(/\{\{title\}\}/g, item.title)
    .replace(/\{\{description\}\}/g, item.description)
    .replace(/\{\{link\}\}/g, item.link)
    .replace(/\{\{topics\}\}/g, item.matchedTopics.join(", "))
}

function loadVoice(): string {
  return readFileSync(
    resolve(import.meta.dirname, "../prompts/voice.md"),
    "utf-8",
  )
}

function loadExamples(): string {
  const dir = resolve(import.meta.dirname, "../prompts/examples")
  const files = readdirSync(dir).filter((f) => f.endsWith(".md"))
  return files
    .map((f) => readFileSync(resolve(dir, f), "utf-8"))
    .join("\n\n---\n\n")
}

function buildSystemPrompt(persona: ChannelPersona): string {
  const voice = loadVoice()
  const examples = loadExamples()

  const parts = [
    voice,
    "",
    "## Channel Settings",
    `Tone: ${persona.tone}`,
    `Style: ${persona.style}`,
    `Language: ${persona.language}`,
  ]
  if (persona.max_length) {
    parts.push(`Max length: ${persona.max_length} characters`)
  }
  parts.push(
    "",
    "## Writing Examples (match this voice)",
    examples,
  )
  return parts.join("\n")
}

async function generateForChannel(
  channel: ChannelConfig,
  item: ScoredItem,
): Promise<GeneratedContent> {
  const userPrompt = buildPromptForChannel(channel, item)
  const systemPrompt = buildSystemPrompt(channel.channel.persona)

  const message = await client.messages.create({
    model: "claude-haiku-4-5-20251001",
    max_tokens: 1024,
    system: systemPrompt,
    messages: [{ role: "user", content: userPrompt }],
  })

  const text = message.content
    .filter((block): block is Anthropic.TextBlock => block.type === "text")
    .map((block) => block.text)
    .join("\n")

  return {
    item,
    channel: channel.channel.name,
    content: text,
  }
}

export async function generateAll(
  items: ScoredItem[],
): Promise<GeneratedContent[]> {
  const channels = loadEnabledChannels()

  if (channels.length === 0) {
    console.log("[generate] No enabled channels found, skipping generation")
    return []
  }

  console.log(
    `[generate] Generating for ${channels.length} channel(s): ${channels.map((c) => c.channel.name).join(", ")}`,
  )

  const results: GeneratedContent[] = []

  for (const channel of channels) {
    const limit = channel.channel.publish.max_per_day
    const targetItems = items.slice(0, limit)

    for (const item of targetItems) {
      console.log(
        `[generate] ${channel.channel.name}: "${item.title.slice(0, 50)}..."`,
      )
      const content = await generateForChannel(channel, item)
      results.push(content)
    }
  }

  return results
}
