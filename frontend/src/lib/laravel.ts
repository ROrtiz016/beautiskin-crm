/**
 * Server-side calls must hit Laravel directly. Browser code can use relative
 * `/api/...` so Next.js rewrites proxy to Laravel (see next.config.ts).
 */
export function laravelOrigin(): string {
  return (process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000").replace(/\/$/, "");
}
