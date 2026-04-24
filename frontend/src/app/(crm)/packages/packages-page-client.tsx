"use client";

import { SpaPageFrame } from "@/components/spa-page-frame";
import { useSpaGet } from "@/hooks/use-spa-get";
import { firstErrorMessage } from "@/lib/laravel-form-errors";
import { spaFetch } from "@/lib/spa-fetch";
import { Suspense, useCallback, useMemo, useState } from "react";

type ServiceOption = { id: number; name: string; price?: string | number };

type PackageService = {
  id: number;
  name: string;
  pivot?: { quantity: number };
};

type PackageRow = {
  id: number;
  name: string;
  description?: string | null;
  package_price: string | number;
  is_active: boolean;
  services?: PackageService[];
};

type PackagesPayload = {
  packages: PackageRow[];
  services: ServiceOption[];
};

function formatPrice(p: string | number): string {
  const n = typeof p === "string" ? Number(p) : Number(p);
  if (Number.isNaN(n)) {
    return "$0.00";
  }
  return n.toLocaleString(undefined, { style: "currency", currency: "USD" });
}

function includesSummary(pkg: PackageRow): string {
  const svcs = pkg.services ?? [];
  if (!svcs.length) {
    return "—";
  }
  return svcs.map((s) => `${s.name} ×${Number(s.pivot?.quantity ?? 1)}`).join(", ");
}

type ItemLine = { service_id: number; quantity: number };

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

function PackagesPageInner() {
  const { data, error, loading, reload } = useSpaGet<PackagesPayload>("/spa/packages");

  const [createOpen, setCreateOpen] = useState(false);
  const [editPkg, setEditPkg] = useState<PackageRow | null>(null);
  const [formErr, setFormErr] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [packagePrice, setPackagePrice] = useState("0");
  const [isActive, setIsActive] = useState(true);
  const [items, setItems] = useState<ItemLine[]>([]);

  const serviceOptions = data?.services ?? [];
  const packages = data?.packages ?? [];

  const defaultServiceId = useMemo(() => serviceOptions[0]?.id ?? 0, [serviceOptions]);

  const resetForm = useCallback(() => {
    setFormErr(null);
    setName("");
    setDescription("");
    setPackagePrice("0");
    setIsActive(true);
    setItems([]);
  }, []);

  const openCreate = useCallback(() => {
    resetForm();
    if (defaultServiceId) {
      setItems([{ service_id: defaultServiceId, quantity: 1 }]);
    }
    setCreateOpen(true);
  }, [defaultServiceId, resetForm]);

  const openEdit = useCallback(
    (p: PackageRow) => {
      setFormErr(null);
      setEditPkg(p);
      setName(p.name);
      setDescription(p.description ?? "");
      setPackagePrice(String(p.package_price));
      setIsActive(!!p.is_active);
      const lines =
        (p.services ?? []).map((s) => ({
          service_id: s.id,
          quantity: Math.max(1, Number(s.pivot?.quantity ?? 1)),
        })) ?? [];
      setItems(lines.length ? lines : defaultServiceId ? [{ service_id: defaultServiceId, quantity: 1 }] : []);
    },
    [defaultServiceId],
  );

  const addItemRow = useCallback(() => {
    const sid = defaultServiceId || serviceOptions[0]?.id;
    if (!sid) {
      return;
    }
    setItems((prev) => [...prev, { service_id: sid, quantity: 1 }]);
  }, [defaultServiceId, serviceOptions]);

  const removeItemRow = useCallback((idx: number) => {
    setItems((prev) => prev.filter((_, i) => i !== idx));
  }, []);

  const updateItemRow = useCallback((idx: number, patch: Partial<ItemLine>) => {
    setItems((prev) => prev.map((row, i) => (i === idx ? { ...row, ...patch } : row)));
  }, []);

  const buildBody = useCallback(() => {
    return {
      name: name.trim(),
      description: description.trim() || null,
      package_price: Number(packagePrice) || 0,
      is_active: isActive,
      items: items.map((r) => ({
        service_id: r.service_id,
        quantity: Math.max(1, Number(r.quantity) || 1),
      })),
    };
  }, [description, isActive, items, name, packagePrice]);

  const submitCreate = useCallback(async () => {
    setFormErr(null);
    if (!name.trim()) {
      setFormErr("Name is required.");
      return;
    }
    setSaving(true);
    try {
      const res = await spaFetch("/packages", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildBody()),
      });
      if (!res.ok) {
        const b = await readApiError(res);
        setFormErr(firstErrorMessage(b, "Could not create package."));
        return;
      }
      setCreateOpen(false);
      resetForm();
      await reload();
    } finally {
      setSaving(false);
    }
  }, [buildBody, name, reload, resetForm]);

  const submitEdit = useCallback(async () => {
    if (!editPkg) {
      return;
    }
    setFormErr(null);
    if (!name.trim()) {
      setFormErr("Name is required.");
      return;
    }
    setSaving(true);
    try {
      const res = await spaFetch(`/packages/${editPkg.id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildBody()),
      });
      if (!res.ok) {
        const b = await readApiError(res);
        setFormErr(firstErrorMessage(b, "Could not save."));
        return;
      }
      setEditPkg(null);
      resetForm();
      await reload();
    } finally {
      setSaving(false);
    }
  }, [buildBody, editPkg, name, reload, resetForm]);

  const deletePkg = useCallback(
    async (p: PackageRow) => {
      if (!window.confirm("Delete this package?")) {
        return;
      }
      const res = await spaFetch(`/packages/${p.id}`, { method: "DELETE" });
      if (!res.ok) {
        const b = await readApiError(res);
        window.alert(firstErrorMessage(b, "Could not delete."));
        return;
      }
      setEditPkg(null);
      await reload();
    },
    [reload],
  );

  const modalOpen = createOpen || !!editPkg;

  return (
    <SpaPageFrame
      title="Treatment packages"
      subtitle="Bundle services at a package price for quotes and in-room explanations."
      loading={loading}
      error={error}
    >
      <div className="mb-6 flex items-center justify-between">
        <button
          type="button"
          onClick={openCreate}
          className="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700"
        >
          + New package
        </button>
      </div>

      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div className="overflow-x-auto">
          <table className="min-w-full text-left text-sm">
            <thead className="border-b border-slate-200 text-slate-500">
              <tr>
                <th className="py-2 pr-3 font-medium">Name</th>
                <th className="py-2 pr-3 font-medium">Package price</th>
                <th className="py-2 pr-3 font-medium">Includes</th>
                <th className="py-2 pr-3 font-medium">Active</th>
                <th className="py-2 pr-3 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {packages.length ? (
                packages.map((p) => (
                  <tr key={p.id} className="border-b border-slate-100">
                    <td className="py-3 pr-3 font-medium text-slate-900">{p.name}</td>
                    <td className="py-3 pr-3 text-slate-700">{formatPrice(p.package_price)}</td>
                    <td className="py-3 pr-3 text-xs text-slate-600">{includesSummary(p)}</td>
                    <td className="py-3 pr-3">
                      {p.is_active ? (
                        <span className="text-xs font-medium text-emerald-800">Yes</span>
                      ) : (
                        <span className="text-xs text-slate-500">No</span>
                      )}
                    </td>
                    <td className="py-3 text-right">
                      <button
                        type="button"
                        className="mr-2 text-slate-700 hover:text-slate-900"
                        onClick={() => openEdit(p)}
                      >
                        Edit
                      </button>
                      <button
                        type="button"
                        className="text-red-600 hover:text-red-700"
                        onClick={() => void deletePkg(p)}
                      >
                        Delete
                      </button>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={5} className="py-6 text-center text-slate-500">
                    No packages yet.
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
              <h2 className="text-lg font-semibold text-slate-900">{editPkg ? "Edit package" : "Add package"}</h2>
              <button
                type="button"
                className="text-slate-500 hover:text-slate-800"
                onClick={() => {
                  setCreateOpen(false);
                  setEditPkg(null);
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
                <textarea
                  rows={2}
                  className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                />
              </label>
              <label className="block text-sm">
                Package price (USD)
                <input
                  type="number"
                  step="0.01"
                  min={0}
                  className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                  value={packagePrice}
                  onChange={(e) => setPackagePrice(e.target.value)}
                  required
                />
              </label>
              <div>
                <p className="mb-1 text-sm font-medium text-slate-800">Included services</p>
                <div className="space-y-2">
                  {items.map((row, idx) => (
                    <div key={idx} className="flex flex-wrap items-end gap-2">
                      <label className="min-w-[160px] flex-1 text-xs text-slate-600">
                        Service
                        <select
                          className="mt-0.5 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                          value={row.service_id}
                          onChange={(e) => updateItemRow(idx, { service_id: Number(e.target.value) })}
                        >
                          {serviceOptions.map((s) => (
                            <option key={s.id} value={s.id}>
                              {s.name}
                            </option>
                          ))}
                        </select>
                      </label>
                      <label className="w-24 text-xs text-slate-600">
                        Qty
                        <input
                          type="number"
                          min={1}
                          className="mt-0.5 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                          value={row.quantity}
                          onChange={(e) => updateItemRow(idx, { quantity: Number(e.target.value) })}
                        />
                      </label>
                      <button
                        type="button"
                        className="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50"
                        onClick={() => removeItemRow(idx)}
                      >
                        Remove
                      </button>
                    </div>
                  ))}
                </div>
                <button type="button" className="mt-2 text-xs font-semibold text-pink-700 hover:text-pink-800" onClick={addItemRow}>
                  + Add service line
                </button>
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
                  setEditPkg(null);
                  resetForm();
                }}
              >
                Cancel
              </button>
              <button
                type="button"
                disabled={saving || !serviceOptions.length}
                className="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-50"
                onClick={() => void (editPkg ? submitEdit() : submitCreate())}
              >
                {editPkg ? "Save" : "Create"}
              </button>
            </div>
            {!serviceOptions.length ? (
              <p className="mt-2 text-xs text-amber-800">Add at least one active service in the catalog before creating packages.</p>
            ) : null}
            {editPkg ? (
              <div className="mt-3 border-t border-slate-200 pt-3">
                <button type="button" className="text-sm font-semibold text-red-600 hover:text-red-700" onClick={() => void deletePkg(editPkg)}>
                  Delete package
                </button>
              </div>
            ) : null}
          </div>
        </div>
      )}
    </SpaPageFrame>
  );
}

export function PackagesPageClient() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-slate-600">Loading packages…</div>}>
      <PackagesPageInner />
    </Suspense>
  );
}
