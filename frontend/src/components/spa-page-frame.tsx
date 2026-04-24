"use client";

import type { ReactNode } from "react";

export function SpaPageFrame({
  title,
  subtitle,
  loading,
  error,
  children,
}: {
  title: string;
  subtitle?: string;
  loading: boolean;
  error: string | null;
  children: ReactNode;
}) {
  if (loading) {
    return <p className="text-sm text-zinc-500">Loading…</p>;
  }
  if (error) {
    return (
      <div className="rounded-lg border border-rose-900/60 bg-rose-950/40 px-4 py-3 text-sm text-rose-100">
        {error}
      </div>
    );
  }
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-white">{title}</h1>
        {subtitle ? <p className="mt-1 text-sm text-zinc-400">{subtitle}</p> : null}
      </div>
      {children}
    </div>
  );
}
