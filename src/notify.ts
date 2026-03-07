import type { Draft } from "./draft.js"

export async function notifySlack(
  webhookUrl: string,
  drafts: Draft[],
  repoUrl: string,
): Promise<void> {
  const blocks = [
    {
      type: "header",
      text: {
        type: "plain_text",
        text: `feed-pulse: ${drafts.length} draft(s) ready`,
      },
    },
    {
      type: "divider",
    },
    ...drafts.flatMap((draft) => [
      {
        type: "section",
        text: {
          type: "mrkdwn",
          text: [
            `*${draft.item.title}*`,
            `Channel: \`${draft.channel}\` | Topics: ${draft.item.matchedTopics.join(", ")}`,
            `Source: <${draft.item.link}|Link>`,
            "",
            draft.content.slice(0, 500) +
              (draft.content.length > 500 ? "..." : ""),
          ].join("\n"),
        },
      },
      {
        type: "divider",
      },
    ]),
    {
      type: "actions",
      elements: [
        {
          type: "button",
          text: {
            type: "plain_text",
            text: "Publish All",
          },
          style: "primary",
          url: `${repoUrl}/actions/workflows/publish.yaml`,
        },
        {
          type: "button",
          text: {
            type: "plain_text",
            text: "Review Drafts",
          },
          url: `${repoUrl}/tree/main/state/drafts`,
        },
      ],
    },
  ]

  const response = await fetch(webhookUrl, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ blocks }),
  })

  if (!response.ok) {
    throw new Error(`Slack webhook error ${response.status}: ${await response.text()}`)
  }
}
