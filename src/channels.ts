import { readFileSync, readdirSync } from "node:fs"
import { resolve } from "node:path"
import { parse } from "yaml"

export interface ChannelPersona {
  tone: string
  style: string
  language: string
  max_length?: number
}

export interface ChannelConfig {
  channel: {
    name: string
    enabled: boolean
    type: string
    persona: ChannelPersona
    publish: {
      status?: string
      max_per_day: number
    }
  }
}

export function loadEnabledChannels(): ChannelConfig[] {
  const dir = resolve(import.meta.dirname, "../config/channels")
  const files = readdirSync(dir).filter((f) => f.endsWith(".yaml"))

  return files
    .map((f) => {
      const raw = readFileSync(resolve(dir, f), "utf-8")
      return parse(raw) as ChannelConfig
    })
    .filter((c) => c.channel.enabled)
}
