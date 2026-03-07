import { readFileSync } from "node:fs"
import { resolve } from "node:path"
import { parse } from "yaml"
import type { FeedItem } from "./crawl.js"

interface Interest {
  topic: string
  keywords: string[]
  weight: number
}

interface InterestsConfig {
  interests: Interest[]
}

export interface ScoredItem extends FeedItem {
  score: number
  matchedTopics: string[]
}

export function loadInterests(): Interest[] {
  const raw = readFileSync(
    resolve(import.meta.dirname, "../config/interests.yaml"),
    "utf-8",
  )
  const config = parse(raw) as InterestsConfig
  return config.interests
}

export function scoreItem(item: FeedItem, interests: Interest[]): ScoredItem {
  const text = `${item.title} ${item.description}`.toLowerCase()
  let score = 0
  const matchedTopics: string[] = []

  for (const interest of interests) {
    const matched = interest.keywords.some((kw) =>
      text.includes(kw.toLowerCase()),
    )
    if (matched) {
      score += interest.weight
      matchedTopics.push(interest.topic)
    }
  }

  return { ...item, score, matchedTopics }
}

export function matchItems(
  items: FeedItem[],
  threshold = 0.5,
): ScoredItem[] {
  const interests = loadInterests()

  return items
    .map((item) => scoreItem(item, interests))
    .filter((item) => item.score >= threshold)
    .sort((a, b) => b.score - a.score)
}
