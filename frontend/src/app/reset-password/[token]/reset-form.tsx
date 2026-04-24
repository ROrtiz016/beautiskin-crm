"use client";

import { firstErrorMessage } from "@/lib/laravel-form-errors";
import { spaFetch } from "@/lib/spa-fetch";
import Link from "next/link";
import { useParams, useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";

export function ResetPasswordForm() {
  const router = useRouter();
  const params = useParams<{ token: string }>();
  const searchParams = useSearchParams();
  const token = decodeURIComponent(String(params.token ?? ""));
  const emailFromQuery = searchParams.get("email") ?? "";

  const [email, setEmail] = useState(emailFromQuery);
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setPending(true);
    try {
      const res = await spaFetch("/auth/reset-password", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          token,
          email,
          password,
          password_confirmation: passwordConfirmation,
        }),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) {
        setError(firstErrorMessage(body, "Reset failed."));
        return;
      }
      router.replace("/login?reset=1");
    } catch {
      setError("Could not reach the server.");
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-slate-100 px-4">
      <div className="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 className="text-lg font-semibold text-slate-900">Set new password</h1>
        <p className="mt-1 text-sm text-slate-600">Choose a new password for your account.</p>
        <form className="mt-6 space-y-4" onSubmit={onSubmit}>
          <div>
            <label htmlFor="email" className="block text-xs font-medium text-slate-700">
              Email
            </label>
            <input
              id="email"
              type="email"
              autoComplete="email"
              required
              value={email}
              onChange={(ev) => setEmail(ev.target.value)}
              className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-pink-500 focus:outline-none focus:ring-1 focus:ring-pink-500"
            />
          </div>
          <div>
            <label htmlFor="password" className="block text-xs font-medium text-slate-700">
              New password
            </label>
            <input
              id="password"
              type="password"
              autoComplete="new-password"
              required
              value={password}
              onChange={(ev) => setPassword(ev.target.value)}
              className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-pink-500 focus:outline-none focus:ring-1 focus:ring-pink-500"
            />
          </div>
          <div>
            <label htmlFor="password_confirmation" className="block text-xs font-medium text-slate-700">
              Confirm new password
            </label>
            <input
              id="password_confirmation"
              type="password"
              autoComplete="new-password"
              required
              value={passwordConfirmation}
              onChange={(ev) => setPasswordConfirmation(ev.target.value)}
              className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-pink-500 focus:outline-none focus:ring-1 focus:ring-pink-500"
            />
          </div>
          {error ? <p className="text-sm text-rose-600">{error}</p> : null}
          <button
            type="submit"
            disabled={pending}
            className="w-full rounded-md bg-pink-600 px-3 py-2 text-sm font-semibold text-white shadow hover:bg-pink-700 disabled:opacity-60"
          >
            {pending ? "Saving…" : "Update password"}
          </button>
        </form>
        <p className="mt-4 text-center text-xs text-slate-600">
          <Link href="/login" className="text-pink-700 hover:underline">
            Back to sign in
          </Link>
        </p>
      </div>
    </div>
  );
}
