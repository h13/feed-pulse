import {
  existsSync,
  readFileSync,
  writeFileSync,
  mkdirSync,
  readdirSync,
  unlinkSync,
} from "node:fs"
import { resolve } from "node:path"
import type { GeneratedContent } from "./generate.js"

const DRAFTS_DIR = resolve(import.meta.dirname, "../state/drafts")

export interface Draft extends GeneratedContent {
  id: string
  createdAt: string
}

function ensureDraftsDir(): void {
  if (!existsSync(DRAFTS_DIR)) {
    mkdirSync(DRAFTS_DIR, { recursive: true })
  }
}

function toDraftId(item: GeneratedContent): string {
  const slug = item.item.title
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .slice(0, 60)
  return `${item.channel}-${slug}`
}

export function saveDrafts(contents: GeneratedContent[]): Draft[] {
  ensureDraftsDir()
  const drafts: Draft[] = []

  for (const content of contents) {
    const draft: Draft = {
      ...content,
      id: toDraftId(content),
      createdAt: new Date().toISOString(),
    }
    const path = resolve(DRAFTS_DIR, `${draft.id}.json`)
    writeFileSync(path, JSON.stringify(draft, null, 2) + "\n")
    drafts.push(draft)
  }

  return drafts
}

export function loadDrafts(): Draft[] {
  if (!existsSync(DRAFTS_DIR)) return []

  return readdirSync(DRAFTS_DIR)
    .filter((f) => f.endsWith(".json"))
    .map((f) => {
      const raw = readFileSync(resolve(DRAFTS_DIR, f), "utf-8")
      return JSON.parse(raw) as Draft
    })
}

export function clearDrafts(): void {
  if (!existsSync(DRAFTS_DIR)) return

  for (const f of readdirSync(DRAFTS_DIR).filter((f) => f.endsWith(".json"))) {
    unlinkSync(resolve(DRAFTS_DIR, f))
  }
}
