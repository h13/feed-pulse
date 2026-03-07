import type { GeneratedContent } from "./generate.js"
import { loadEnabledChannels, type ChannelConfig } from "./channels.js"
import { publishToWordPress } from "./publishers/wordpress.js"
import { publishToX } from "./publishers/x.js"

export interface PublishResult {
  channel: string
  title: string
  url: string | null
  error: string | null
}

async function publishOne(
  content: GeneratedContent,
  channel: ChannelConfig,
): Promise<PublishResult> {
  const base = { channel: content.channel, title: content.item.title }

  try {
    switch (channel.channel.type) {
      case "wordpress": {
        const url = await publishToWordPress(content, channel)
        return { ...base, url, error: null }
      }
      case "x": {
        const url = await publishToX(content)
        return { ...base, url, error: null }
      }
      default:
        return {
          ...base,
          url: null,
          error: `Unknown channel type: ${channel.channel.type}`,
        }
    }
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err)
    return { ...base, url: null, error: message }
  }
}

export async function publishAll(
  contents: GeneratedContent[],
): Promise<PublishResult[]> {
  const channels = loadEnabledChannels()
  const channelMap = new Map(channels.map((c) => [c.channel.name, c]))

  const results: PublishResult[] = []

  for (const content of contents) {
    const channel = channelMap.get(content.channel)
    if (!channel) {
      results.push({
        channel: content.channel,
        title: content.item.title,
        url: null,
        error: `Channel "${content.channel}" not found`,
      })
      continue
    }

    console.log(
      `[publish] ${content.channel}: "${content.item.title.slice(0, 50)}..."`,
    )
    const result = await publishOne(content, channel)
    results.push(result)
  }

  return results
}
