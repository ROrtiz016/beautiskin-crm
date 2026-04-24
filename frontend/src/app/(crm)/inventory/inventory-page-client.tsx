"use client";

import { SpaPageFrame } from "@/components/spa-page-frame";
import { useSpaGet } from "@/hooks/use-spa-get";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useMemo, useState } from "react";

type InventoryItem = {
  id: number;
  name: string;
  category?: string | null;
  track_inventory: boolean;
  stock_quantity: number;
  reorder_level: number;
  price: string | number;
};

type InventoryPayload = {
  items: InventoryItem[];
  lowStockItems: InventoryItem[];
  search: string;
};

function formatPrice(p: string | number): string {
  const n = typeof p === "string" ? Number(p) : Number(p);
  if (Number.isNaN(n)) {
    return "$0.00";
  }
  return n.toLocaleString(undefined, { style: "currency", currency: "USD" });
}

function isLowRow(item: InventoryItem): boolean {
  return item.track_inventory && Number(item.stock_quantity) <= Number(item.reorder_level);
}

function InventoryPageInner() {
  const sp = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();

  const path = useMemo(() => `/spa/inventory${sp.toString() ? `?${sp}` : ""}`, [sp]);
  const { data, error, loading } = useSpaGet<InventoryPayload>(path);

  const [draftSearch, setDraftSearch] = useState("");
  useEffect(() => {
    setDraftSearch(sp.get("search") ?? "");
  }, [sp]);

  const applySearch = useCallback(() => {
    const n = new URLSearchParams(sp.toString());
    if (draftSearch.trim()) {
      n.set("search", draftSearch.trim());
    } else {
      n.delete("search");
    }
    const s = n.toString();
    router.push(s ? `${pathname}?${s}` : pathname);
  }, [draftSearch, pathname, router, sp]);

  const clearSearch = useCallback(() => {
    setDraftSearch("");
    const n = new URLSearchParams(sp.toString());
    n.delete("search");
    const s = n.toString();
    router.push(s ? `${pathname}?${s}` : pathname);
  }, [pathname, router, sp]);

  const items = data?.items ?? [];
  const lowStockItems = data?.lowStockItems ?? [];

  return (
    <SpaPageFrame
      title="Inventory & retail"
      subtitle="Retail SKUs and anything with stock tracking. Low-stock items use the reorder level set on each service."
      loading={loading}
      error={error}
    >
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div />
        <Link
          href="/services"
          className="inline-flex shrink-0 items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
        >
          Edit catalog
        </Link>
      </div>

      {lowStockItems.length > 0 ? (
        <div className="mb-6 rounded-xl border border-amber-300/90 bg-amber-50 px-4 py-3 text-sm text-amber-950 shadow-sm" role="alert">
          <p className="font-semibold">Low stock</p>
          <ul className="mt-2 list-inside list-disc space-y-1 text-amber-900/95">
            {lowStockItems.map((item) => (
              <li key={item.id}>
                {item.name} — {Number(item.stock_quantity)} on hand (reorder at {Number(item.reorder_level)})
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
          <div className="flex-1">
            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
            <input
              value={draftSearch}
              onChange={(e) => setDraftSearch(e.target.value)}
              placeholder="Filter by name"
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
          </div>
          <button
            type="button"
            onClick={applySearch}
            className="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700"
          >
            Search
          </button>
          {sp.get("search") ? (
            <button
              type="button"
              onClick={clearSearch}
              className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
              Clear
            </button>
          ) : null}
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full text-left text-sm">
            <thead className="border-b border-slate-200 text-slate-500">
              <tr>
                <th className="py-2 pr-3 font-medium">Name</th>
                <th className="py-2 pr-3 font-medium">Category</th>
                <th className="py-2 pr-3 font-medium">Track stock</th>
                <th className="py-2 pr-3 font-medium">On hand</th>
                <th className="py-2 pr-3 font-medium">Reorder at</th>
                <th className="py-2 pr-3 font-medium">Price</th>
              </tr>
            </thead>
            <tbody>
              {items.length ? (
                items.map((item) => {
                  const low = isLowRow(item);
                  return (
                    <tr key={item.id} className={`border-b border-slate-100 ${low ? "bg-amber-50/60" : ""}`}>
                      <td className="py-3 pr-3 font-medium text-slate-900">{item.name}</td>
                      <td className="py-3 pr-3 text-slate-700">{item.category || "—"}</td>
                      <td className="py-3 pr-3">
                        {item.track_inventory ? (
                          <span className="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                            Yes
                          </span>
                        ) : (
                          <span className="text-xs text-slate-500">No</span>
                        )}
                      </td>
                      <td className="py-3 pr-3 text-slate-700">
                        {item.track_inventory ? Number(item.stock_quantity) : <span className="text-slate-400">—</span>}
                      </td>
                      <td className="py-3 pr-3 text-slate-700">
                        {item.track_inventory ? Number(item.reorder_level) : <span className="text-slate-400">—</span>}
                      </td>
                      <td className="py-3 pr-3 text-slate-700">{formatPrice(item.price)}</td>
                    </tr>
                  );
                })
              ) : (
                <tr>
                  <td colSpan={6} className="py-6 text-center text-slate-500">
                    No retail or tracked items yet. Mark categories as Product / Retail or enable “Track inventory” on a service.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>
    </SpaPageFrame>
  );
}

export function InventoryPageClient() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-slate-600">Loading inventory…</div>}>
      <InventoryPageInner />
    </Suspense>
  );
}
