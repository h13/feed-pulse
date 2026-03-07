import { createHmac, randomBytes } from "node:crypto"
import { env } from "../env.js"
import type { GeneratedContent } from "../generate.js"

const API_URL = "https://api.x.com/2/tweets"

function percentEncode(str: string): string {
  return encodeURIComponent(str).replace(
    /[!'()*]/g,
    (c) => `%${c.charCodeAt(0).toString(16).toUpperCase()}`,
  )
}

function buildOAuthHeader(method: string, url: string, body: string): string {
  const { X_API_KEY, X_API_SECRET, X_ACCESS_TOKEN, X_ACCESS_SECRET } = env

  if (!X_API_KEY || !X_API_SECRET || !X_ACCESS_TOKEN || !X_ACCESS_SECRET) {
    throw new Error(
      "X credentials not configured: X_API_KEY, X_API_SECRET, X_ACCESS_TOKEN, X_ACCESS_SECRET",
    )
  }

  const timestamp = Math.floor(Date.now() / 1000).toString()
  const nonce = randomBytes(16).toString("hex")

  const oauthParams: Record<string, string> = {
    oauth_consumer_key: X_API_KEY,
    oauth_nonce: nonce,
    oauth_signature_method: "HMAC-SHA1",
    oauth_timestamp: timestamp,
    oauth_token: X_ACCESS_TOKEN,
    oauth_version: "1.0",
  }

  const paramString = Object.keys(oauthParams)
    .sort()
    .map((k) => `${percentEncode(k)}=${percentEncode(oauthParams[k]!)}`)
    .join("&")

  const baseString = [
    method.toUpperCase(),
    percentEncode(url),
    percentEncode(paramString),
  ].join("&")

  const signingKey = `${percentEncode(X_API_SECRET)}&${percentEncode(X_ACCESS_SECRET)}`
  const signature = createHmac("sha1", signingKey)
    .update(baseString)
    .digest("base64")

  oauthParams["oauth_signature"] = signature

  const header = Object.keys(oauthParams)
    .sort()
    .map((k) => `${percentEncode(k)}="${percentEncode(oauthParams[k]!)}"`)
    .join(", ")

  return `OAuth ${header}`
}

export async function publishToX(
  content: GeneratedContent,
): Promise<string> {
  const body = JSON.stringify({ text: content.content })
  const authHeader = buildOAuthHeader("POST", API_URL, body)

  const response = await fetch(API_URL, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: authHeader,
    },
    body,
  })

  if (!response.ok) {
    const responseBody = await response.text()
    throw new Error(`X API error ${response.status}: ${responseBody}`)
  }

  const result = (await response.json()) as {
    data: { id: string }
  }
  return `https://x.com/i/status/${result.data.id}`
}
