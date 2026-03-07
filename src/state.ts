import { existsSync, readFileSync, writeFileSync, mkdirSync } from "node:fs"
import { resolve, dirname } from "node:path"

interface State {
  processedUrls: string[]
  lastRun: string | null
}

const STATE_PATH = resolve(import.meta.dirname, "../state/processed.json")

function ensureDir(filePath: string): void {
  const dir = dirname(filePath)
  if (!existsSync(dir)) {
    mkdirSync(dir, { recursive: true })
  }
}

export function loadState(): State {
  if (!existsSync(STATE_PATH)) {
    return { processedUrls: [], lastRun: null }
  }
  const raw = readFileSync(STATE_PATH, "utf-8")
  return JSON.parse(raw) as State
}

export function saveState(state: State): void {
  ensureDir(STATE_PATH)
  writeFileSync(STATE_PATH, JSON.stringify(state, null, 2) + "\n")
}

export function isProcessed(url: string, state: State): boolean {
  return state.processedUrls.includes(url)
}

export function markProcessed(urls: string[], state: State): State {
  return {
    processedUrls: [...new Set([...state.processedUrls, ...urls])],
    lastRun: new Date().toISOString(),
  }
}
