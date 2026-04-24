"use client";

import { SpaPageFrame } from "@/components/spa-page-frame";
import { useSpaGet } from "@/hooks/use-spa-get";
import { CustomerFilterCombobox } from "../appointments/customer-filter-combobox";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useMemo, useState } from "react";

type QuoteRow = {
  id: number;
  title?: string | null;
  status: string;
  total_amount?: string;
  customer?: { id?: number; first_name: string; last_name: string };
};

type PaginatedQuotes = {
  data: QuoteRow[];
  current_page: number;
  last_page: number;
  total: number;
};

type QuotesIndexPayload = {
  quotes: PaginatedQuotes;
  customers: { id: number; first_name: string; last_name: string; email?: string | null; phone?: string | null }[];
  search: string;
  customerId: number;
};

function mergeHref(sp: URLSearchParams, patch: Record<string, string | undefined>): string {
  const n = new URLSearchParams(sp.toString());
  Object.entries(patch).forEach(([k, v]) => {
    if (v === undefined || v === "") {
      n.delete(k);
    } else {
      n.set(k, v);
    }
  });
  const s = n.toString();
  return s ? `/quotes?${s}` : "/quotes";
}

function statusClass(status: string): string {
  switch (status) {
    case "accepted":
      return "bg-emerald-100 text-emerald-800";
    case "sent":
      return "bg-blue-100 text-blue-800";
    case "declined":
    case "expired":
      return "bg-slate-200 text-slate-700";
    default:
      return "bg-amber-100 text-amber-900";
  }
}

function QuotesListInner() {
  const sp = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();

  const path = useMemo(() => `/spa/quotes${sp.toString() ? `?${sp}` : ""}`, [sp]);

  const { data, error, loading, reload } = useSpaGet<QuotesIndexPayload>(path);

  const [draftSearch, setDraftSearch] = useState("");
  const [draftCustomer, setDraftCustomer] = useState("");

  useEffect(() => {
    setDraftSearch(sp.get("search") ?? "");
    setDraftCustomer(sp.get("customer_id") ?? "");
  }, [sp]);

  const applyFilters = useCallback(() => {
    const n = new URLSearchParams(sp.toString());
    if (draftSearch.trim()) {
      n.set("search", draftSearch.trim());
    } else {
      n.delete("search");
    }
    if (draftCustomer) {
      n.set("customer_id", draftCustomer);
    } else {
      n.delete("customer_id");
    }
    n.delete("page");
    const s = n.toString();
    router.push(s ? `${pathname}?${s}` : pathname);
  }, [draftCustomer, draftSearch, pathname, router, sp]);

  const clearFilters = useCallback(() => {
    setDraftSearch("");
    setDraftCustomer("");
    const n = new URLSearchParams(sp.toString());
    ["search", "customer_id", "page"].forEach((k) => n.delete(k));
    const s = n.toString();
    router.push(s ? `${pathname}?${s}` : pathname);
  }, [pathname, router, sp]);

  const customers = data?.customers ?? [];
  const quotes = data?.quotes;

  return (
    <SpaPageFrame
      title="Quotes"
      subtitle={quotes ? `${quotes.total} total` : undefined}
      loading={loading}
      error={error}
    >
      <p className="mb-4">
        <Link
          href="/quotes/new"
          className="inline-flex rounded-md bg-pink-600 px-3 py-2 text-sm font-semibold text-white hover:bg-pink-700"
        >
          New quote
        </Link>
        <button
          type="button"
          className="ml-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
          onClick={() => reload()}
        >
          Refresh
        </button>
      </p>

      <section className="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 className="text-sm font-semibold text-slate-900">Filters</h2>
        <div className="mt-3 grid gap-4 sm:grid-cols-2">
          <label className="block text-xs font-medium text-slate-700">
            Search (customer name / email)
            <input
              className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
              value={draftSearch}
              onChange={(ev) => setDraftSearch(ev.target.value)}
            />
          </label>
          <CustomerFilterCombobox customers={customers} value={draftCustomer} onValueChange={setDraftCustomer} />
        </div>
        <div className="mt-3 flex flex-wrap gap-2">
          <button
            type="button"
            onClick={applyFilters}
            className="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-900"
          >
            Apply
          </button>
          <button
            type="button"
            onClick={clearFilters}
            className="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
          >
            Clear
          </button>
        </div>
      </section>

      {quotes ? (
        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-100 bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-3">Quote</th>
                <th className="px-4 py-3">Customer</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3 text-right">Total</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {quotes.data.map((q) => (
                <tr key={q.id} className="hover:bg-slate-50/80">
                  <td className="px-4 py-3">
                    <Link href={`/quotes/${q.id}`} className="font-medium text-pink-700 hover:underline">
                      #{q.id}
                      {q.title ? ` · ${q.title}` : ""}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-slate-700">
                    {q.customer ? `${q.customer.first_name} ${q.customer.last_name}` : "—"}
                  </td>
                  <td className="px-4 py-3">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusClass(q.status)}`}>
                      {q.status}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right text-slate-800">
                    ${Number(q.total_amount ?? 0).toFixed(2)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {quotes.last_page > 1 ? (
            <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm text-slate-600">
              <span>
                Page {quotes.current_page} of {quotes.last_page}
              </span>
              <span className="flex gap-3">
                {quotes.current_page > 1 ? (
                  <Link
                    href={mergeHref(sp, { page: String(quotes.current_page - 1) })}
                    className="text-pink-700 hover:underline"
                  >
                    Previous
                  </Link>
                ) : (
                  <span className="text-slate-400">Previous</span>
                )}
                {quotes.current_page < quotes.last_page ? (
                  <Link
                    href={mergeHref(sp, { page: String(quotes.current_page + 1) })}
                    className="text-pink-700 hover:underline"
                  >
                    Next
                  </Link>
                ) : (
                  <span className="text-slate-400">Next</span>
                )}
              </span>
            </div>
          ) : null}
        </div>
      ) : null}
    </SpaPageFrame>
  );
}

export function QuotesListClient() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-slate-600">Loading quotes…</div>}>
      <QuotesListInner />
    </Suspense>
  );
}
