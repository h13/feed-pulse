import { readFileSync } from "node:fs"
import { resolve } from "node:path"
import { parse } from "yaml"

interface Source {
  name: string
  url: string
  category: string
}

interface SourcesConfig {
  sources: Source[]
}

export interface FeedItem {
  title: string
  link: string
  description: string
  pubDate: string
  source: string
  category: string
}

export function loadSources(): Source[] {
  const raw = readFileSync(
    resolve(import.meta.dirname, "../config/sources.yaml"),
    "utf-8",
  )
  const config = parse(raw) as SourcesConfig
  return config.sources
}

export async function fetchFeed(source: Source): Promise<FeedItem[]> {
  const response = await fetch(source.url)
  if (!response.ok) {
    console.error(`Failed to fetch ${source.name}: ${response.status}`)
    return []
  }

  const xml = await response.text()
  return parseRssItems(xml, source)
}

function parseRssItems(xml: string, source: Source): FeedItem[] {
  const items: FeedItem[] = []
  const itemRegex = /<item>([\s\S]*?)<\/item>/g

  let match = itemRegex.exec(xml)
  while (match) {
    const block = match[1] ?? ""
    const title = extractTag(block, "title")
    const link = extractTag(block, "link")
    const description = extractTag(block, "description")
    const pubDate = extractTag(block, "pubDate")

    if (title && link) {
      items.push({
        title,
        link,
        description: stripHtml(description),
        pubDate,
        source: source.name,
        category: source.category,
      })
    }

    match = itemRegex.exec(xml)
  }

  return items
}

function extractTag(xml: string, tag: string): string {
  const cdataRegex = new RegExp(
    `<${tag}[^>]*><!\\[CDATA\\[([\\s\\S]*?)\\]\\]></${tag}>`,
  )
  const cdataMatch = cdataRegex.exec(xml)
  if (cdataMatch) return cdataMatch[1]?.trim() ?? ""

  const regex = new RegExp(`<${tag}[^>]*>([\\s\\S]*?)</${tag}>`)
  const match = regex.exec(xml)
  return match?.[1]?.trim() ?? ""
}

function stripHtml(html: string): string {
  return html
    .replace(/<[^>]*>/g, "")
    .replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .trim()
}

export async function crawlAll(): Promise<FeedItem[]> {
  const sources = loadSources()
  const results = await Promise.allSettled(sources.map(fetchFeed))

  return results.flatMap((r) => (r.status === "fulfilled" ? r.value : []))
}
