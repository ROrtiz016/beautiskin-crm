"use client";

import { setStoredToken } from "@/lib/auth-token";
import { firstErrorMessage } from "@/lib/laravel-form-errors";
import { spaFetch } from "@/lib/spa-fetch";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useState } from "react";

function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const resetOk = searchParams.get("reset") === "1";
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setPending(true);
    try {
      const res = await spaFetch("/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password }),
      });
      if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        setError(firstErrorMessage(body, "Invalid credentials."));
        return;
      }
      const data = (await res.json()) as { token: string };
      setStoredToken(data.token);
      router.replace("/");
      router.refresh();
    } catch {
      setError("Could not reach the server.");
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-slate-100 px-4">
      <div className="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 className="text-lg font-semibold text-slate-900">BeautiFlow</h1>
        <p className="mt-1 text-sm text-slate-600">Sign in with your clinic account.</p>
        {resetOk ? (
          <p className="mt-3 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
            Your password was reset. Sign in with your new password.
          </p>
        ) : null}
        <form className="mt-6 space-y-4" onSubmit={onSubmit}>
          <div>
            <label htmlFor="email" className="block text-xs font-medium text-slate-700">
              Email
            </label>
            <input
              id="email"
              type="email"
              autoComplete="username"
              required
              value={email}
              onChange={(ev) => setEmail(ev.target.value)}
              className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm placeholder:text-slate-400 focus:border-pink-500 focus:outline-none focus:ring-1 focus:ring-pink-500"
            />
          </div>
          <div>
            <label htmlFor="password" className="block text-xs font-medium text-slate-700">
              Password
            </label>
            <input
              id="password"
              type="password"
              autoComplete="current-password"
              required
              value={password}
              onChange={(ev) => setPassword(ev.target.value)}
              className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm placeholder:text-slate-400 focus:border-pink-500 focus:outline-none focus:ring-1 focus:ring-pink-500"
            />
          </div>
          {error ? <p className="text-sm text-rose-600">{error}</p> : null}
          <button
            type="submit"
            disabled={pending}
            className="w-full rounded-md bg-pink-600 px-3 py-2 text-sm font-semibold text-white shadow hover:bg-pink-700 disabled:opacity-60"
          >
            {pending ? "Signing in…" : "Sign in"}
          </button>
        </form>
        <p className="mt-4 flex flex-wrap justify-center gap-x-4 gap-y-1 text-center text-xs text-slate-600">
          <Link href="/register" className="text-pink-700 hover:underline">
            Create an account
          </Link>
          <Link href="/forgot-password" className="text-pink-700 hover:underline">
            Forgot password?
          </Link>
        </p>
      </div>
    </div>
  );
}

export default function LoginPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-screen items-center justify-center bg-slate-100 text-sm text-slate-600">
          Loading…
        </div>
      }
    >
      <LoginForm />
    </Suspense>
  );
}
