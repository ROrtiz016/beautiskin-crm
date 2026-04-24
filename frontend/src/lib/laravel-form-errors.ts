export function firstErrorMessage(body: unknown, fallback: string): string {
  if (typeof body !== "object" || body === null) {
    return fallback;
  }
  const o = body as Record<string, unknown>;
  if (typeof o.message === "string" && o.message) {
    return o.message;
  }
  const errors = o.errors;
  if (typeof errors !== "object" || errors === null) {
    return fallback;
  }
  for (const v of Object.values(errors as Record<string, unknown>)) {
    if (Array.isArray(v) && typeof v[0] === "string") {
      return v[0];
    }
  }
  return fallback;
}

export function fieldErrors(body: unknown): Record<string, string> {
  const out: Record<string, string> = {};
  if (typeof body !== "object" || body === null) {
    return out;
  }
  const errors = (body as { errors?: unknown }).errors;
  if (typeof errors !== "object" || errors === null) {
    return out;
  }
  for (const [k, v] of Object.entries(errors as Record<string, unknown>)) {
    if (Array.isArray(v) && typeof v[0] === "string") {
      out[k] = v[0];
    }
  }
  return out;
}
