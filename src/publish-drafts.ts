import { loadDrafts, clearDrafts } from "./draft.js"
import { publishAll } from "./publish.js"
import type { PublishResult } from "./publish.js"

interface PublishHistory {
  publishedAt: string
  results: PublishResult[]
}

async function main() {
  const drafts = loadDrafts()

  if (drafts.length === 0) {
    console.log("[publish] No drafts to publish")
    return
  }

  console.log(`[publish] Found ${drafts.length} draft(s)`)

  const results = await publishAll(drafts)

  for (const result of results) {
    if (result.error) {
      console.error(`[publish] FAIL ${result.channel}: ${result.error}`)
    } else {
      console.log(`[publish] OK ${result.channel}: ${result.url}`)
    }
  }

  // Save publish history
  const history: PublishHistory = {
    publishedAt: new Date().toISOString(),
    results,
  }
  const historyJson = JSON.stringify(history, null, 2) + "\n"
  process.stdout.write(`\n::set-output history::\n${historyJson}`)

  // Write history file for the workflow to commit
  const { writeFileSync, mkdirSync, existsSync } = await import("node:fs")
  const { resolve } = await import("node:path")
  const historyDir = resolve(import.meta.dirname, "../state/history")
  if (!existsSync(historyDir)) {
    mkdirSync(historyDir, { recursive: true })
  }
  const date = new Date().toISOString().slice(0, 10)
  const historyPath = resolve(historyDir, `${date}.json`)
  writeFileSync(historyPath, historyJson)
  console.log(`[history] Saved to ${historyPath}`)

  // Clear drafts after successful publish
  const failures = results.filter((r) => r.error)
  if (failures.length === 0) {
    clearDrafts()
    console.log("[draft] Cleared all drafts")
  } else {
    console.warn(
      `[draft] ${failures.length} failure(s), keeping drafts for retry`,
    )
  }
}

main().catch((err) => {
  console.error("Publish failed:", err)
  process.exit(1)
})
