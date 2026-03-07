import { z } from "zod"

const schema = z.object({
  ANTHROPIC_API_KEY: z.string().min(1, "ANTHROPIC_API_KEY is required"),
})

export const env = schema.parse(process.env)
