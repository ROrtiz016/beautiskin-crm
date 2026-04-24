"use client";

import { SpaPageFrame } from "@/components/spa-page-frame";
import { useSpaGet } from "@/hooks/use-spa-get";
import { firstErrorMessage } from "@/lib/laravel-form-errors";
import { spaFetch } from "@/lib/spa-fetch";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useMemo, useState } from "react";

type MembershipRow = {
  id: number;
  name: string;
  description?: string | null;
  monthly_price: string | number;
  billing_cycle_days: number;
  is_active: boolean;
  customer_memberships_count?: number;
};

type MembershipsPayload = {
  memberships: MembershipRow[];
  search: string;
};

function formatPrice(p: string | number): string {
  const n = typeof p === "string" ? Number(p) : Number(p);
  if (Number.isNaN(n)) {
    return "$0.00";
  }
  return n.toLocaleString(undefined, { style: "currency", currency: "USD" });
}

function billingLabel(days: number): string {
  return Number(days) >= 365 ? "Yearly" : "Monthly";
}

function billingCycleFromDays(days: number): "monthly" | "yearly" {
  return Number(days) >= 365 ? "yearly" : "monthly";
}

async function readApiError(res: Response): Promise<unknown> {
  const text = await res.text();
  if (!text) {
    return {};
  }
  try {
    return JSON.parse(text) as unknown;
  } catch {
    return { message: text };
  }
}

function MembershipsPageInner() {
  const sp = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();

  const path = useMemo(() => `/spa/memberships${sp.toString() ? `?${sp}` : ""}`, [sp]);
  const { data, error, loading, reload } = useSpaGet<MembershipsPayload>(path);

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

  const memberships = data?.memberships ?? [];

  const [createOpen, setCreateOpen] = useState(false);
  const [editRow, setEditRow] = useState<MembershipRow | null>(null);
  const [formErr, setFormErr] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [price, setPrice] = useState("0");
  const [billingCycle, setBillingCycle] = useState<"monthly" | "yearly">("monthly");
  const [isActive, setIsActive] = useState(true);

  const resetForm = useCallback(() => {
    setFormErr(null);
    setName("");
    setDescription("");
    setPrice("0");
    setBillingCycle("monthly");
    setIsActive(true);
  }, []);

  const openCreate = useCallback(() => {
    resetForm();
    setCreateOpen(true);
  }, [resetForm]);

  const openEdit = useCallback(
    (m: MembershipRow) => {
      setFormErr(null);
      setEditRow(m);
      setName(m.name);
      setDescription(m.description ?? "");
      setPrice(String(m.monthly_price));
      setBillingCycle(billingCycleFromDays(m.billing_cycle_days));
      setIsActive(!!m.is_active);
    },
    [],
  );

  const buildApiBody = useCallback(() => {
    return {
      name: name.trim(),
      description: description.trim() || null,
      monthly_price: Number(price) || 0,
      billing_cycle_days: billingCycle === "yearly" ? 365 : 30,
      is_active: isActive,
    };
  }, [billingCycle, description, isActive, name, price]);

  const submitCreate = useCallback(async () => {
    setFormErr(null);
    if (!name.trim()) {
      setFormErr("Name is required.");
      return;
    }
    setSaving(true);
    try {
      const res = await spaFetch("/memberships", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildApiBody()),
      });
      if (!res.ok) {
        const b = await readApiError(res);
        setFormErr(firstErrorMessage(b, "Could not create membership."));
        return;
      }
      setCreateOpen(false);
      resetForm();
      await reload();
    } finally {
      setSaving(false);
    }
  }, [buildApiBody, name, reload, resetForm]);

  const submitEdit = useCallback(async () => {
    if (!editRow) {
      return;
    }
    setFormErr(null);
    if (!name.trim()) {
      setFormErr("Name is required.");
      return;
    }
    setSaving(true);
    try {
      const res = await spaFetch(`/memberships/${editRow.id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildApiBody()),
      });
      if (!res.ok) {
        const b = await readApiError(res);
        setFormErr(firstErrorMessage(b, "Could not save."));
        return;
      }
      setEditRow(null);
      resetForm();
      await reload();
    } finally {
      setSaving(false);
    }
  }, [buildApiBody, editRow, name, reload, resetForm]);

  const deleteMembership = useCallback(
    async (m: MembershipRow) => {
      if (!window.confirm("Delete this membership? This cannot be undone if customers or services still reference it.")) {
        return;
      }
      const res = await spaFetch(`/memberships/${m.id}`, { method: "DELETE" });
      if (!res.ok) {
        const b = await readApiError(res);
        window.alert(firstErrorMessage(b, "Could not delete."));
        return;
      }
      setEditRow(null);
      await reload();
    },
    [reload],
  );

  const modalOpen = createOpen || !!editRow;

  return (
    <SpaPageFrame
      title="Memberships"
      subtitle="Manage recurring plans, pricing, renewal period, and availability."
      loading={loading}
      error={error}
    >
      <div className="mb-6 flex items-center justify-between">
        <button
          type="button"
          onClick={openCreate}
          className="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700"
        >
          + New membership
        </button>
      </div>

      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
          <div className="flex-1">
            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
            <input
              value={draftSearch}
              onChange={(e) => setDraftSearch(e.target.value)}
              placeholder="Search by membership name"
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
                <th className="py-2 pr-3 font-medium">Description</th>
                <th className="py-2 pr-3 font-medium">Price</th>
                <th className="py-2 pr-3 font-medium">Billing</th>
                <th className="py-2 pr-3 font-medium">Active</th>
                <th className="py-2 pr-3 font-medium">Subscribers</th>
                <th className="py-2 pr-3 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {memberships.length ? (
                memberships.map((m) => (
                  <tr key={m.id} className="border-b border-slate-100">
                    <td className="py-3 pr-3 font-medium text-slate-900">{m.name}</td>
                    <td className="py-3 pr-3 text-slate-600">{m.description || "—"}</td>
                    <td className="py-3 pr-3 text-slate-700">{formatPrice(m.monthly_price)}</td>
                    <td className="py-3 pr-3 text-slate-700">{billingLabel(m.billing_cycle_days)}</td>
                    <td className="py-3 pr-3">
                      {m.is_active ? (
                        <span className="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                          Yes
                        </span>
                      ) : (
                        <span className="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                          No
                        </span>
                      )}
                    </td>
                    <td className="py-3 pr-3 text-slate-700">{m.customer_memberships_count ?? 0}</td>
                    <td className="py-3 text-right">
                      <button type="button" className="mr-2 text-slate-700 hover:text-slate-900" onClick={() => openEdit(m)}>
                        Edit
                      </button>
                      <button
                        type="button"
                        className="text-red-600 hover:text-red-700"
                        onClick={() => void deleteMembership(m)}
                      >
                        Delete
                      </button>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={7} className="py-6 text-center text-slate-500">
                    No memberships yet. Add your first plan.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>

      {modalOpen && (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 px-4" role="dialog">
          <div className="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl">
            <div className="mb-4 flex items-center justify-between">
              <h2 className="text-lg font-semibold text-slate-900">{editRow ? "Edit membership" : "Add membership"}</h2>
              <button
                type="button"
                className="text-slate-500 hover:text-slate-800"
                onClick={() => {
                  setCreateOpen(false);
                  setEditRow(null);
                  resetForm();
                }}
              >
                ✕
              </button>
            </div>
            {formErr ? <p className="mb-2 text-sm text-red-600">{formErr}</p> : null}
            <div className="space-y-3">
              <label className="block text-sm">
                Name
                <input
                  className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  required
                />
              </label>
              <label className="block text-sm">
                Description
                <textarea rows={3} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" value={description} onChange={(e) => setDescription(e.target.value)} />
              </label>
              <div className="grid gap-3 md:grid-cols-2">
                <label className="block text-sm">
                  Price (USD)
                  <input
                    type="number"
                    step="0.01"
                    min={0}
                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                    value={price}
                    onChange={(e) => setPrice(e.target.value)}
                    required
                  />
                </label>
                <label className="block text-sm">
                  Billing cycle
                  <select
                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                    value={billingCycle}
                    onChange={(e) => setBillingCycle(e.target.value as "monthly" | "yearly")}
                  >
                    <option value="monthly">Monthly</option>
                    <option value="yearly">Yearly</option>
                  </select>
                </label>
              </div>
              <label className="flex items-center gap-2 text-sm font-medium text-slate-800">
                <input type="checkbox" className="rounded border-slate-300" checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />
                Active
              </label>
            </div>
            <div className="mt-4 flex justify-end gap-2 border-t border-slate-200 pt-4">
              <button
                type="button"
                className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                onClick={() => {
                  setCreateOpen(false);
                  setEditRow(null);
                  resetForm();
                }}
              >
                Cancel
              </button>
              <button
                type="button"
                disabled={saving}
                className="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-50"
                onClick={() => void (editRow ? submitEdit() : submitCreate())}
              >
                {editRow ? "Save" : "Create"}
              </button>
            </div>
            {editRow ? (
              <div className="mt-3 border-t border-slate-200 pt-3">
                <button type="button" className="text-sm font-semibold text-red-600 hover:text-red-700" onClick={() => void deleteMembership(editRow)}>
                  Delete membership
                </button>
              </div>
            ) : null}
          </div>
        </div>
      )}
    </SpaPageFrame>
  );
}

export function MembershipsPageClient() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-slate-600">Loading memberships…</div>}>
      <MembershipsPageInner />
    </Suspense>
  );
}
