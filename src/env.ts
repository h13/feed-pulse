import { z } from "zod"

const schema = z.object({
  ANTHROPIC_API_KEY: z.string().min(1, "ANTHROPIC_API_KEY is required"),

  // Slack
  SLACK_WEBHOOK_URL: z.string().url().optional(),

  // GitHub (for PR creation in publish workflow)
  GITHUB_REPOSITORY: z.string().optional(),

  // WordPress (optional, required when blog channel is enabled)
  WORDPRESS_API_URL: z.string().url().optional(),
  WORDPRESS_USER: z.string().optional(),
  WORDPRESS_APP_PASSWORD: z.string().optional(),

  // X / Twitter (optional, required when x channel is enabled)
  X_API_KEY: z.string().optional(),
  X_API_SECRET: z.string().optional(),
  X_ACCESS_TOKEN: z.string().optional(),
  X_ACCESS_SECRET: z.string().optional(),
})

export const env = schema.parse(process.env)
