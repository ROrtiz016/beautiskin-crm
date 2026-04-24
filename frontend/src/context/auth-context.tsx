"use client";

import { clearStoredToken, getStoredToken, setStoredToken } from "@/lib/auth-token";
import { spaFetch } from "@/lib/spa-fetch";
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  is_admin?: boolean;
  permissions?: string[];
  can: {
    view_sales: boolean;
    access_admin_board: boolean;
    view_experimental_ui: boolean;
    manage_feature_flags: boolean;
    manage_users?: boolean;
  };
};

type AuthState = {
  user: AuthUser | null;
  loading: boolean;
  refresh: () => Promise<void>;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
};

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);

  const refresh = useCallback(async () => {
    if (!getStoredToken()) {
      setUser(null);
      setLoading(false);
      return;
    }
    try {
      const res = await spaFetch("/auth/user");
      if (!res.ok) {
        clearStoredToken();
        setUser(null);
        return;
      }
      setUser((await res.json()) as AuthUser);
    } catch {
      clearStoredToken();
      setUser(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  const login = useCallback(async (email: string, password: string) => {
    const res = await spaFetch("/auth/login", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email, password }),
    });
    if (!res.ok) {
      const body = await res.json().catch(() => ({}));
      const msg =
        typeof body === "object" && body !== null && "message" in body
          ? String((body as { message?: unknown }).message)
          : await res.text();
      throw new Error(msg || "Login failed");
    }
    const data = (await res.json()) as { token: string; user: AuthUser };
    setStoredToken(data.token);
    setUser(data.user);
  }, []);

  const logout = useCallback(async () => {
    try {
      await spaFetch("/auth/logout", { method: "POST" });
    } catch {
      /* ignore */
    }
    clearStoredToken();
    setUser(null);
    if (typeof window !== "undefined") {
      window.location.assign("/login");
    }
  }, []);

  const value = useMemo(
    () => ({ user, loading, refresh, login, logout }),
    [user, loading, refresh, login, logout],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error("useAuth must be used within AuthProvider");
  }
  return ctx;
}
