import { crawlAll } from "./crawl.js"
import { matchItems } from "./match.js"
import { generateAll } from "./generate.js"
import { publishAll } from "./publish.js"
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

  for (const content of generated) {
    console.log(`\n=== Generated [${content.channel}] ===`)
    console.log(content.content)
    console.log("===")
  }

  // Phase 3: publish to channels
  if (generated.length > 0) {
    console.log("\n[publish] Publishing to channels...")
    const results = await publishAll(generated)

    for (const result of results) {
      if (result.error) {
        console.error(`[publish] FAIL ${result.channel}: ${result.error}`)
      } else {
        console.log(`[publish] OK ${result.channel}: ${result.url}`)
      }
    }
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
