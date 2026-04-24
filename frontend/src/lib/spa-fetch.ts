import { clearStoredToken, getStoredToken } from "@/lib/auth-token";

const SPA_AUTH_PUBLIC_PREFIXES = ["/auth/login", "/auth/register", "/auth/forgot-password", "/auth/reset-password"];

function isSpaAuthAnonymousPath(apiPath: string): boolean {
  return SPA_AUTH_PUBLIC_PREFIXES.some((prefix) => apiPath === prefix || apiPath.startsWith(`${prefix}?`));
}

function isBrowserAuthPagePath(): boolean {
  if (typeof window === "undefined") {
    return false;
  }
  const p = window.location.pathname;
  return (
    p === "/login" ||
    p === "/register" ||
    p === "/forgot-password" ||
    p.startsWith("/reset-password/")
  );
}

export async function spaFetch(path: string, init: RequestInit = {}): Promise<Response> {
  const normalized = path.startsWith("/") ? path : `/${path}`;
  const hadToken = Boolean(getStoredToken());
  const token = getStoredToken();
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");
  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }
  const res = await fetch(`/api${normalized}`, { ...init, headers });

  if (res.status === 401 && hadToken && !isSpaAuthAnonymousPath(normalized)) {
    clearStoredToken();
    if (typeof window !== "undefined" && !isBrowserAuthPagePath()) {
      window.location.assign("/login");
    }
  }

  return res;
}

export async function spaJson<T>(path: string, init: RequestInit = {}): Promise<T> {
  const res = await spaFetch(path, init);
  if (!res.ok) {
    const text = await res.text();
    throw new Error(text || `${res.status} ${res.statusText}`);
  }
  return res.json() as Promise<T>;
}
