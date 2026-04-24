"use client";

import { SpaPageFrame } from "@/components/spa-page-frame";
import { useSpaGet } from "@/hooks/use-spa-get";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useMemo, useState } from "react";

type UnknownRec = Record<string, unknown>;

type PaginatedActivities = {
  data: UnknownRec[];
  current_page: number;
  last_page: number;
  total: number;
};

type ActivityPayload = {
  activities: PaginatedActivities;
  categoryLabels: Record<string, string>;
};

function ActivityPageInner() {
  const sp = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();

  const path = useMemo(() => `/spa/activities${sp.toString() ? `?${sp}` : ""}`, [sp]);
  const { data, error, loading } = useSpaGet<ActivityPayload>(path);

  const [draft, setDraft] = useState({
    customer: "",
    q: "",
    category: "",
    from: "",
    to: "",
  });

  useEffect(() => {
    setDraft({
      customer: sp.get("customer") ?? "",
      q: sp.get("q") ?? "",
      category: sp.get("category") ?? "",
      from: sp.get("from") ?? "",
      to: sp.get("to") ?? "",
    });
  }, [sp]);

  const apply = useCallback(() => {
    const n = new URLSearchParams();
    if (draft.customer.trim()) {
      n.set("customer", draft.customer.trim());
    }
    if (draft.q.trim()) {
      n.set("q", draft.q.trim());
    }
    if (draft.category) {
      n.set("category", draft.category);
    }
    if (draft.from) {
      n.set("from", draft.from);
    }
    if (draft.to) {
      n.set("to", draft.to);
    }
    const s = n.toString();
    router.push(s ? `${pathname}?${s}` : pathname);
  }, [draft, pathname, router]);

  const clear = useCallback(() => {
    setDraft({ customer: "", q: "", category: "", from: "", to: "" });
    router.push(pathname);
  }, [pathname, router]);

  const pag = data?.activities;
  const rows = pag?.data ?? [];
  const labels = data?.categoryLabels ?? {};

  const goPage = useCallback(
    (p: number) => {
      const n = new URLSearchParams(sp.toString());
      if (p <= 1) {
        n.delete("page");
      } else {
        n.set("page", String(p));
      }
      const s = n.toString();
      router.push(s ? `${pathname}?${s}` : pathname);
    },
    [pathname, router, sp],
  );

  return (
    <SpaPageFrame
      title="Activity"
      subtitle="One chronological feed of notes, tasks, appointments, payments, communications, and pipeline updates across customers."
      loading={loading}
      error={error}
    >
      <p className="mb-4">
        <Link href="/customers" className="text-sm font-medium text-pink-700 hover:underline">
          Customers
        </Link>
      </p>

      <section className="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-lg font-semibold text-slate-900">Filters</h2>
        <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <label className="block text-sm sm:col-span-2">
            <span className="text-xs font-semibold uppercase text-slate-500">Customer</span>
            <input
              className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              value={draft.customer}
              onChange={(e) => setDraft((d) => ({ ...d, customer: e.target.value }))}
              placeholder="Name, email, or phone…"
            />
          </label>
          <label className="block text-sm sm:col-span-2">
            <span className="text-xs font-semibold uppercase text-slate-500">Search summary</span>
            <input
              className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              value={draft.q}
              onChange={(e) => setDraft((d) => ({ ...d, q: e.target.value }))}
              placeholder="Words in the activity text…"
            />
          </label>
          <label className="block text-sm">
            <span className="text-xs font-semibold uppercase text-slate-500">Activity type</span>
            <select
              className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              value={draft.category}
              onChange={(e) => setDraft((d) => ({ ...d, category: e.target.value }))}
            >
              <option value="">All types</option>
              {Object.entries(labels).map(([k, lab]) => (
                <option key={k} value={k}>
                  {lab}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            <span className="text-xs font-semibold uppercase text-slate-500">From</span>
            <input type="date" className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" value={draft.from} onChange={(e) => setDraft((d) => ({ ...d, from: e.target.value }))} />
          </label>
          <label className="block text-sm">
            <span className="text-xs font-semibold uppercase text-slate-500">To</span>
            <input type="date" className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" value={draft.to} onChange={(e) => setDraft((d) => ({ ...d, to: e.target.value }))} />
          </label>
        </div>
        <div className="mt-4 flex flex-wrap gap-2">
          <button type="button" onClick={apply} className="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">
            Apply
          </button>
          <button type="button" onClick={clear} className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Clear all
          </button>
        </div>
      </section>

      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-lg font-semibold text-slate-900">Timeline</h2>
        <p className="mt-1 text-xs text-slate-500">Newest first.</p>
        <ul className="mt-4 divide-y divide-slate-100">
          {rows.length ? (
            rows.map((a) => {
              const cust = a.customer as { id?: number; first_name?: string; last_name?: string } | undefined;
              const u = a.user as { name?: string } | undefined;
              const cat = String(a.category ?? "");
              const catLab = labels[cat] ?? cat;
              return (
                <li key={String(a.id)} className="py-3">
                  <p className="text-sm text-slate-900">{String(a.summary)}</p>
                  <p className="mt-1 text-xs text-slate-500">
                    {a.created_at ? new Date(String(a.created_at)).toLocaleString() : ""} · {u?.name ?? "—"} ·{" "}
                    <span className="font-medium text-slate-600">{catLab}</span>
                    {cust?.id ? (
                      <>
                        {" · "}
                        <Link href={`/customers/${cust.id}`} className="text-pink-700 hover:underline">
                          {cust.first_name} {cust.last_name}
                        </Link>
                      </>
                    ) : null}
                  </p>
                </li>
              );
            })
          ) : (
            <li className="py-8 text-center text-slate-500">No activity matches these filters.</li>
          )}
        </ul>
        {pag && pag.last_page > 1 ? (
          <div className="mt-4 flex items-center justify-between border-t border-slate-200 pt-3 text-sm">
            <span className="text-slate-600">
              Page {pag.current_page} of {pag.last_page} ({pag.total} events)
            </span>
            <span className="flex gap-2">
              <button type="button" disabled={pag.current_page <= 1} className="rounded border px-2 py-1 disabled:opacity-40" onClick={() => goPage(pag.current_page - 1)}>
                Previous
              </button>
              <button
                type="button"
                disabled={pag.current_page >= pag.last_page}
                className="rounded border px-2 py-1 disabled:opacity-40"
                onClick={() => goPage(pag.current_page + 1)}
              >
                Next
              </button>
            </span>
          </div>
        ) : null}
      </section>
    </SpaPageFrame>
  );
}

export function ActivityPageClient() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-slate-600">Loading activity…</div>}>
      <ActivityPageInner />
    </Suspense>
  );
}
