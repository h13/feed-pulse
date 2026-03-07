import { crawlAll } from "./crawl.js"
import { matchItems } from "./match.js"
import { generateAll } from "./generate.js"
import { saveDrafts } from "./draft.js"
import { notifySlack } from "./notify.js"
import { env } from "./env.js"
import { loadState, saveState, isProcessed, markProcessed } from "./state.js"

async function main() {
  console.log("[crawl] Fetching feeds...")
  const items = await crawlAll()
  console.log(`[crawl] Found ${items.length} items`)

  console.log("[match] Scoring against interests...")
  const matched = matchItems(items)
  console.log(`[match] ${matched.length} items above threshold`)

  const state = loadState()
  const newItems = matched.filter((item) => !isProcessed(item.link, state))
  console.log(`[filter] ${newItems.length} new items (not previously processed)`)

  if (newItems.length === 0) {
    console.log("[done] Nothing new to process")
    return
  }

  for (const item of newItems) {
    console.log(`\n--- [${item.score.toFixed(1)}] ${item.title}`)
    console.log(`    Topics: ${item.matchedTopics.join(", ")}`)
    console.log(`    Source: ${item.source}`)
    console.log(`    Link:   ${item.link}`)
  }

  // Phase 2: generate content via Claude API
  const generated = await generateAll(newItems)

  // Save as drafts for review
  const drafts = saveDrafts(generated)
  console.log(`\n[draft] Saved ${drafts.length} draft(s) to state/drafts/`)

  // Notify Slack
  if (env.SLACK_WEBHOOK_URL) {
    const repoUrl = env.GITHUB_REPOSITORY
      ? `https://github.com/${env.GITHUB_REPOSITORY}`
      : "https://github.com/h13/feed-pulse"
    console.log("[notify] Sending Slack notification...")
    await notifySlack(env.SLACK_WEBHOOK_URL, drafts, repoUrl)
    console.log("[notify] Slack notification sent")
  }

  const updatedState = markProcessed(
    newItems.map((item) => item.link),
    state,
  )
  saveState(updatedState)
  console.log(`\n[state] Saved ${newItems.length} new URLs to state`)
}

main().catch((err) => {
  console.error("Pipeline failed:", err)
  process.exit(1)
})
