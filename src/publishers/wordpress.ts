import { env } from "../env.js"
import type { GeneratedContent } from "../generate.js"
import type { ChannelConfig } from "../channels.js"

export async function publishToWordPress(
  content: GeneratedContent,
  channel: ChannelConfig,
): Promise<string> {
  const { WORDPRESS_API_URL, WORDPRESS_USER, WORDPRESS_APP_PASSWORD } = env

  if (!WORDPRESS_API_URL || !WORDPRESS_USER || !WORDPRESS_APP_PASSWORD) {
    throw new Error(
      "WordPress credentials not configured: WORDPRESS_API_URL, WORDPRESS_USER, WORDPRESS_APP_PASSWORD",
    )
  }

  const status = channel.channel.publish.status ?? "draft"
  const auth = Buffer.from(
    `${WORDPRESS_USER}:${WORDPRESS_APP_PASSWORD}`,
  ).toString("base64")

  const response = await fetch(`${WORDPRESS_API_URL}/posts`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Basic ${auth}`,
    },
    body: JSON.stringify({
      title: content.item.title,
      content: content.content,
      status,
    }),
  })

  if (!response.ok) {
    const body = await response.text()
    throw new Error(`WordPress API error ${response.status}: ${body}`)
  }

  const post = (await response.json()) as { id: number; link: string }
  return post.link
}
