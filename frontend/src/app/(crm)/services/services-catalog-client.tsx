"use client";

import { SpaPageFrame } from "@/components/spa-page-frame";
import { useSpaGet } from "@/hooks/use-spa-get";
import { firstErrorMessage } from "@/lib/laravel-form-errors";
import { spaFetch } from "@/lib/spa-fetch";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useMemo, useState } from "react";

type StaffBrief = { id: number; name: string };
type MembershipBrief = { id: number; name: string };

type ServiceRow = {
  id: number;
  name: string;
  category?: string | null;
  duration_minutes: number;
  price: string | number;
  description?: string | null;
  is_active: boolean;
  track_inventory: boolean;
  stock_quantity: number;
  reorder_level: number;
  staff_users?: StaffBrief[];
  staffUsers?: StaffBrief[];
  covered_by_memberships?: MembershipBrief[];
  coveredByMemberships?: MembershipBrief[];
};

type ServicesIndexPayload = {
  services: ServiceRow[];
  staffUsers: StaffBrief[];
  memberships: MembershipBrief[];
  search: string;
  lowStockServices: Pick<ServiceRow, "id" | "name" | "stock_quantity">[];
};

function staffList(s: ServiceRow): StaffBrief[] {
  const v = s.staff_users ?? s.staffUsers;
  return Array.isArray(v) ? v : [];
}

function membershipList(s: ServiceRow): MembershipBrief[] {
  const v = s.covered_by_memberships ?? s.coveredByMemberships;
  return Array.isArray(v) ? v : [];
}

function formatPrice(p: string | number): string {
  const n = typeof p === "string" ? Number(p) : Number(p);
  if (Number.isNaN(n)) {
    return "$0.00";
  }
  return n.toLocaleString(undefined, { style: "currency", currency: "USD" });
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

function ServicesCatalogInner() {
  const sp = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();

  const path = useMemo(() => `/spa/services${sp.toString() ? `?${sp}` : ""}`, [sp]);
  const { data, error, loading, reload } = useSpaGet<ServicesIndexPayload>(path);

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

  const staffUsers = data?.staffUsers ?? [];
  const memberships = data?.memberships ?? [];
  const services = data?.services ?? [];
  const lowStock = data?.lowStockServices ?? [];

  const [createOpen, setCreateOpen] = useState(false);
  const [editSvc, setEditSvc] = useState<ServiceRow | null>(null);
  const [formErr, setFormErr] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const [name, setName] = useState("");
  const [category, setCategory] = useState("");
  const [duration, setDuration] = useState("30");
  const [price, setPrice] = useState("0");
  const [description, setDescription] = useState("");
  const [isActive, setIsActive] = useState(true);
  const [trackInv, setTrackInv] = useState(false);
  const [stockQty, setStockQty] = useState("0");
  const [reorderLevel, setReorderLevel] = useState("5");
  const [staffIds, setStaffIds] = useState<number[]>([]);
  const [membershipIds, setMembershipIds] = useState<number[]>([]);

  const resetForm = useCallback(() => {
    setFormErr(null);
    setName("");
    setCategory("");
    setDuration("30");
    setPrice("0");
    setDescription("");
    setIsActive(true);
    setTrackInv(false);
    setStockQty("0");
    setReorderLevel("5");
    setStaffIds([]);
    setMembershipIds([]);
  }, []);

  const openCreate = useCallback(() => {
    resetForm();
    setCreateOpen(true);
  }, [resetForm]);

  const openEdit = useCallback(
    (s: ServiceRow) => {
      setFormErr(null);
      setEditSvc(s);
      setName(s.name);
      setCategory(s.category ?? "");
      setDuration(String(s.duration_minutes));
      setPrice(String(s.price));
      setDescription(s.description ?? "");
      setIsActive(!!s.is_active);
      setTrackInv(!!s.track_inventory);
      setStockQty(String(s.stock_quantity ?? 0));
      setReorderLevel(String(s.reorder_level ?? 5));
      setStaffIds(staffList(s).map((u) => u.id));
      setMembershipIds(membershipList(s).map((m) => m.id));
    },
    [],
  );

  const toggleStaff = useCallback((id: number) => {
    setStaffIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
  }, []);

  const toggleMembership = useCallback((id: number) => {
    setMembershipIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
  }, []);

  const buildJsonBody = useCallback(() => {
    return {
      name: name.trim(),
      category: category.trim() || null,
      duration_minutes: Number(duration) || 0,
      price: Number(price) || 0,
      description: description.trim() || null,
      is_active: isActive,
      track_inventory: trackInv,
      stock_quantity: Math.max(0, Number(stockQty) || 0),
      reorder_level: Math.max(0, Number(reorderLevel) || 0),
      staff_user_ids: staffIds,
      membership_ids: membershipIds,
    };
  }, [category, description, duration, isActive, membershipIds, name, price, reorderLevel, staffIds, stockQty, trackInv]);

  const submitCreate = useCallback(async () => {
    setFormErr(null);
    if (!name.trim()) {
      setFormErr("Name is required.");
      return;
    }
    setSaving(true);
    try {
      const res = await spaFetch("/services", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildJsonBody()),
      });
      if (!res.ok) {
        const b = await readApiError(res);
        setFormErr(firstErrorMessage(b, "Could not create service."));
        return;
      }
      setCreateOpen(false);
      resetForm();
      await reload();
    } finally {
      setSaving(false);
    }
  }, [buildJsonBody, name, reload, resetForm]);

  const submitEdit = useCallback(async () => {
    if (!editSvc) {
      return;
    }
    setFormErr(null);
    if (!name.trim()) {
      setFormErr("Name is required.");
      return;
    }
    setSaving(true);
    try {
      const res = await spaFetch(`/services/${editSvc.id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildJsonBody()),
      });
      if (!res.ok) {
        const b = await readApiError(res);
        setFormErr(firstErrorMessage(b, "Could not save."));
        return;
      }
      setEditSvc(null);
      resetForm();
      await reload();
    } finally {
      setSaving(false);
    }
  }, [buildJsonBody, editSvc, name, reload, resetForm]);

  const deleteSvc = useCallback(
    async (s: ServiceRow) => {
      if (!window.confirm("Delete this service? This cannot be undone if no appointments reference it.")) {
        return;
      }
      const res = await spaFetch(`/services/${s.id}`, { method: "DELETE" });
      if (!res.ok) {
        const b = await readApiError(res);
        window.alert(firstErrorMessage(b, "Could not delete."));
        return;
      }
      setEditSvc(null);
      await reload();
    },
    [reload],
  );

  return (
    <SpaPageFrame
      title="Services"
      subtitle="Treatment catalog, pricing, staff eligibility, membership coverage, and optional retail stock."
      loading={loading}
      error={error}
    >
      <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
        <button
          type="button"
          onClick={openCreate}
          className="inline-flex rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700"
        >
          + New service
        </button>
      </div>

      {lowStock.length > 0 ? (
        <div className="mb-6 rounded-xl border border-amber-300/90 bg-amber-50 px-4 py-3 text-sm text-amber-950 shadow-sm" role="alert">
          <p className="font-semibold">Low stock (tracked items)</p>
          <p className="mt-1 text-amber-900/95">
            {lowStock.map((svc) => (
              <span key={svc.id} className="mr-2 inline-block">
                {svc.name} ({Number(svc.stock_quantity)})
              </span>
            ))}
          </p>
          <p className="mt-2">
            <Link href="/inventory" className="font-semibold text-amber-950 underline decoration-amber-800/60 hover:text-amber-900">
              View inventory
            </Link>
          </p>
        </div>
      ) : null}

      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
          <div className="flex-1">
            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
            <input
              value={draftSearch}
              onChange={(e) => setDraftSearch(e.target.value)}
              placeholder="Search by service name"
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
                <th className="py-2 pr-3 font-medium">Duration</th>
                <th className="py-2 pr-3 font-medium">Price</th>
                <th className="py-2 pr-3 font-medium">Stock</th>
                <th className="py-2 pr-3 font-medium">Track</th>
                <th className="py-2 pr-3 font-medium">Active</th>
                <th className="py-2 pr-3 font-medium">Staff</th>
                <th className="py-2 pr-3 font-medium">Covered by</th>
                <th className="py-2 pr-3 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {services.length ? (
                services.map((s) => (
                  <tr key={s.id} className="border-b border-slate-100">
                    <td className="py-3 pr-3 font-medium text-slate-900">{s.name}</td>
                    <td className="py-3 pr-3 text-slate-700">{s.category || "—"}</td>
                    <td className="py-3 pr-3 text-slate-700">{s.duration_minutes} min</td>
                    <td className="py-3 pr-3 text-slate-700">{formatPrice(s.price)}</td>
                    <td className="py-3 pr-3 text-xs text-slate-600">
                      {s.track_inventory ? (
                        <>
                          {Number(s.stock_quantity)}
                          {Number(s.stock_quantity) <= Number(s.reorder_level) ? (
                            <span className="ml-1 font-semibold text-amber-800">Low</span>
                          ) : null}
                        </>
                      ) : (
                        "—"
                      )}
                    </td>
                    <td className="py-3 pr-3 text-xs">
                      {s.track_inventory ? (
                        <span className="font-medium text-emerald-800">Yes</span>
                      ) : (
                        <span className="text-slate-500">No</span>
                      )}
                    </td>
                    <td className="py-3 pr-3">
                      {s.is_active ? (
                        <span className="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                          Yes
                        </span>
                      ) : (
                        <span className="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                          No
                        </span>
                      )}
                    </td>
                    <td className="py-3 pr-3 text-xs text-slate-600">
                      {staffList(s)
                        .map((u) => u.name)
                        .join(", ") || "None assigned"}
                    </td>
                    <td className="py-3 pr-3 text-xs text-slate-600">
                      {membershipList(s)
                        .map((m) => m.name)
                        .join(", ") || "No memberships"}
                    </td>
                    <td className="py-3 text-right">
                      <button
                        type="button"
                        className="mr-2 text-slate-700 hover:text-slate-900"
                        onClick={() => openEdit(s)}
                      >
                        Edit
                      </button>
                      <button
                        type="button"
                        className="text-red-600 hover:text-red-700"
                        onClick={() => void deleteSvc(s)}
                      >
                        Delete
                      </button>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={10} className="py-6 text-center text-slate-500">
                    No services yet. Add your first treatment.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>

      {(createOpen || editSvc) && (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 px-4" role="dialog">
          <div className="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl">
            <div className="mb-4 flex items-center justify-between">
              <h2 className="text-lg font-semibold text-slate-900">{editSvc ? "Edit service" : "Add service"}</h2>
              <button
                type="button"
                className="text-slate-500 hover:text-slate-800"
                onClick={() => {
                  setCreateOpen(false);
                  setEditSvc(null);
                  resetForm();
                }}
              >
                ✕
              </button>
            </div>
            {formErr ? <p className="mb-3 text-sm text-red-600">{formErr}</p> : null}
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
              <div className="grid gap-3 md:grid-cols-2">
                <label className="block text-sm">
                  Category
                  <input
                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                    value={category}
                    onChange={(e) => setCategory(e.target.value)}
                    placeholder="e.g. Facial"
                  />
                </label>
                <label className="block text-sm">
                  Duration (minutes)
                  <input
                    type="number"
                    min={0}
                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                    value={duration}
                    onChange={(e) => setDuration(e.target.value)}
                    required
                  />
                </label>
              </div>
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
              <div className="grid gap-3 md:grid-cols-3">
                <label className="flex items-center gap-2 text-sm font-medium text-slate-800">
                  <input type="checkbox" className="rounded border-slate-300" checked={trackInv} onChange={(e) => setTrackInv(e.target.checked)} />
                  Track inventory
                </label>
                <label className="block text-sm">
                  Stock on hand
                  <input
                    type="number"
                    min={0}
                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                    value={stockQty}
                    onChange={(e) => setStockQty(e.target.value)}
                  />
                </label>
                <label className="block text-sm">
                  Reorder at
                  <input
                    type="number"
                    min={0}
                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                    value={reorderLevel}
                    onChange={(e) => setReorderLevel(e.target.value)}
                  />
                </label>
              </div>
              <label className="flex items-center gap-2 text-sm font-medium text-slate-800">
                <input type="checkbox" className="rounded border-slate-300" checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />
                Active (bookable)
              </label>
              <label className="block text-sm">
                Description
                <textarea
                  rows={3}
                  className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                />
              </label>
              <div>
                <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Eligible staff</p>
                <div className="max-h-40 space-y-1 overflow-y-auto rounded border border-slate-200 p-2">
                  {staffUsers.map((u) => (
                    <label key={u.id} className="flex cursor-pointer items-center gap-2 text-sm text-slate-800">
                      <input type="checkbox" className="rounded border-slate-300" checked={staffIds.includes(u.id)} onChange={() => toggleStaff(u.id)} />
                      {u.name}
                    </label>
                  ))}
                </div>
              </div>
              <div>
                <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Covered by memberships</p>
                <div className="max-h-40 space-y-1 overflow-y-auto rounded border border-slate-200 p-2">
                  {memberships.map((m) => (
                    <label key={m.id} className="flex cursor-pointer items-center gap-2 text-sm text-slate-800">
                      <input
                        type="checkbox"
                        className="rounded border-slate-300"
                        checked={membershipIds.includes(m.id)}
                        onChange={() => toggleMembership(m.id)}
                      />
                      {m.name}
                    </label>
                  ))}
                </div>
              </div>
            </div>
            <div className="mt-4 flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-4">
              <button
                type="button"
                className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                onClick={() => {
                  setCreateOpen(false);
                  setEditSvc(null);
                  resetForm();
                }}
              >
                Cancel
              </button>
              <button
                type="button"
                disabled={saving}
                className="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-50"
                onClick={() => void (editSvc ? submitEdit() : submitCreate())}
              >
                {editSvc ? "Save" : "Create"}
              </button>
            </div>
            {editSvc ? (
              <div className="mt-3 border-t border-slate-200 pt-3">
                <button type="button" className="text-sm font-semibold text-red-600 hover:text-red-700" onClick={() => void deleteSvc(editSvc)}>
                  Delete service
                </button>
              </div>
            ) : null}
          </div>
        </div>
      )}
    </SpaPageFrame>
  );
}

export function ServicesCatalogClient() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-slate-600">Loading services…</div>}>
      <ServicesCatalogInner />
    </Suspense>
  );
}
