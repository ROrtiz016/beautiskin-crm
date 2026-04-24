const COOKIE = "beautiskin_spa_token";

function cookieSecureFlag(): boolean {
  if (typeof window === "undefined") {
    return false;
  }
  if (window.location.protocol === "https:") {
    return true;
  }
  return process.env.NEXT_PUBLIC_SPA_COOKIE_SECURE === "1";
}

export function getStoredToken(): string | null {
  if (typeof document === "undefined") {
    return null;
  }
  const match = document.cookie.match(new RegExp(`(?:^|; )${COOKIE}=([^;]*)`));
  return match?.[1] ? decodeURIComponent(match[1]) : null;
}

export function setStoredToken(token: string): void {
  const maxAge = 60 * 60 * 24 * 30;
  const secure = cookieSecureFlag() ? "; Secure" : "";
  document.cookie = `${COOKIE}=${encodeURIComponent(token)}; Path=/; Max-Age=${maxAge}; SameSite=Lax${secure}`;
}

export function clearStoredToken(): void {
  const secure = cookieSecureFlag() ? "; Secure" : "";
  document.cookie = `${COOKIE}=; Path=/; Max-Age=0; SameSite=Lax${secure}`;
}
