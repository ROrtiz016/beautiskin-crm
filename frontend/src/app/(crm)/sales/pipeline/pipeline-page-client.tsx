"use client";

import { CustomerFilterCombobox } from "../../appointments/customer-filter-combobox";
import { SpaPageFrame } from "@/components/spa-page-frame";
import { useSpaGet } from "@/hooks/use-spa-get";
import { firstErrorMessage } from "@/lib/laravel-form-errors";
import { spaFetch } from "@/lib/spa-fetch";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useMemo, useState } from "react";

type SpaUser = { id: number; name: string };

type OppRow = {
  id: number;
  customer_id: number;
  owner_user_id?: number | null;
  title: string;
  stage: string;
  amount: string | number;
  expected_close_date?: string | null;
  loss_reason?: string | null;
  notes?: string | null;
  customer?: { id: number; first_name: string; last_name: string };
  owner?: SpaUser | null;
};

type FilterCustomer = { id: number; first_name: string; last_name: string };

type PipelinePayload = {
  byStage: Record<string, unknown>;
  openStages: string[];
  closedStages: string[];
  stageLabels: Record<string, string>;
  customers: {
    id: number;
    first_name: string;
    last_name: string;
    email?: string | null;
    phone?: string | null;
  }[];
  staffUsers: SpaUser[];
  openPipelineValue: number;
  closingNext30Days: number;
  countsOpen: Record<string, number>;
  customerIdFilter: number;
  filterCustomer: FilterCustomer | null;
};

function mergePipelineHref(sp: URLSearchParams, patch: Record<string, string | undefined>): string {
  const n = new URLSearchParams(sp.toString());
  Object.entries(patch).forEach(([k, v]) => {
    if (v === undefined || v === "") {
      n.delete(k);
    } else {
      n.set(k, v);
    }
  });
  const s = n.toString();
  return s ? `/sales/pipeline?${s}` : "/sales/pipeline";
}

function normalizeByStage(raw: unknown): Record<string, OppRow[]> {
  if (!raw || typeof raw !== "object") {
    return {};
  }
  const out: Record<string, OppRow[]> = {};
  for (const [k, v] of Object.entries(raw as Record<string, unknown>)) {
    out[k] = Array.isArray(v) ? (v as OppRow[]) : [];
  }
  return out;
}

function formatMoney(amount: string | number | undefined | null): string {
  const n = typeof amount === "string" ? Number(amount) : Number(amount ?? 0);
  if (Number.isNaN(n)) {
    return "$0.00";
  }
  return n.toLocaleString(undefined, { style: "currency", currency: "USD" });
}

function dateOnly(isoOrDate: string | null | undefined): string {
  if (!isoOrDate) {
    return "";
  }
  return isoOrDate.slice(0, 10);
}

function formatCloseDate(isoOrDate: string | null | undefined): string {
  if (!isoOrDate) {
    return "No close date";
  }
  const d = new Date(isoOrDate);
  if (Number.isNaN(d.getTime())) {
    return "No close date";
  }
  return d.toLocaleDateString(undefined, { month: "short", day: "numeric", year: "numeric" });
}

function stageColumnClass(stage: string): string {
  switch (stage) {
    case "new":
      return "border-slate-200 bg-slate-50";
    case "qualified":
      return "border-blue-200 bg-blue-50/60";
    case "proposal":
      return "border-violet-200 bg-violet-50/60";
    case "negotiation":
      return "border-amber-200 bg-amber-50/60";
    default:
      return "border-slate-200 bg-slate-50";
  }
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

function PipelinePageInner() {
  const sp = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();

  const path = useMemo(() => `/spa/sales/pipeline${sp.toString() ? `?${sp}` : ""}`, [sp]);
  const { data, error, loading, reload } = useSpaGet<PipelinePayload>(path);

  const [draftCustomer, setDraftCustomer] = useState("");
  const [createOpen, setCreateOpen] = useState(false);
  const [createErr, setCreateErr] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const [nCustomer, setNCustomer] = useState("");
  const [nTitle, setNTitle] = useState("");
  const [nAmount, setNAmount] = useState("");
  const [nClose, setNClose] = useState("");
  const [nOwner, setNOwner] = useState("");
  const [nNotes, setNNotes] = useState("");

  const [editOpp, setEditOpp] = useState<OppRow | null>(null);
  const [editErr, setEditErr] = useState<string | null>(null);
  const [eCustomer, setECustomer] = useState("");
  const [eTitle, setETitle] = useState("");
  const [eAmount, setEAmount] = useState("");
  const [eClose, setEClose] = useState("");
  const [eStage, setEStage] = useState("new");
  const [eOwner, setEOwner] = useState("");
  const [eNotes, setENotes] = useState("");
  const [eLoss, setELoss] = useState("");

  const [markLostOpp, setMarkLostOpp] = useState<OppRow | null>(null);
  const [markLostReason, setMarkLostReason] = useState("");
  const [markLostErr, setMarkLostErr] = useState<string | null>(null);

  useEffect(() => {
    setDraftCustomer(sp.get("customer_id") ?? "");
  }, [sp]);

  const byStage = useMemo(() => normalizeByStage(data?.byStage), [data?.byStage]);
  const openStages = data?.openStages ?? [];
  const closedStages = data?.closedStages ?? [];
  const stageLabels = data?.stageLabels ?? {};
  const customers = data?.customers ?? [];
  const staffUsers = data?.staffUsers ?? [];
  const countsOpen = data?.countsOpen ?? {};
  const filterCustomer = data?.filterCustomer ?? null;
  const allStages = useMemo(() => [...openStages, ...closedStages], [openStages, closedStages]);

  const applyCustomerFilter = useCallback(() => {
    const n = new URLSearchParams(sp.toString());
    if (draftCustomer) {
      n.set("customer_id", draftCustomer);
    } else {
      n.delete("customer_id");
    }
    const s = n.toString();
    router.push(s ? `${pathname}?${s}` : pathname);
  }, [draftCustomer, pathname, router, sp]);

  const clearCustomerFilter = useCallback(() => {
    setDraftCustomer("");
    const n = new URLSearchParams(sp.toString());
    n.delete("customer_id");
    const s = n.toString();
    router.push(s ? `${pathname}?${s}` : pathname);
  }, [pathname, router, sp]);

  const resetCreate = useCallback(() => {
    setCreateErr(null);
    setNCustomer("");
    setNTitle("");
    setNAmount("");
    setNClose("");
    setNOwner("");
    setNNotes("");
  }, []);

  const openCreate = useCallback(() => {
    resetCreate();
    if (filterCustomer) {
      setNCustomer(String(filterCustomer.id));
    } else if (sp.get("customer_id")) {
      setNCustomer(sp.get("customer_id") ?? "");
    }
    setCreateOpen(true);
  }, [filterCustomer, resetCreate, sp]);

  const openEdit = useCallback((o: OppRow) => {
    setEditErr(null);
    setEditOpp(o);
    setECustomer(String(o.customer_id));
    setETitle(o.title);
    setEAmount(o.amount !== undefined && o.amount !== null ? String(o.amount) : "");
    setEClose(dateOnly(o.expected_close_date ?? null));
    setEStage(o.stage);
    setEOwner(o.owner_user_id != null ? String(o.owner_user_id) : "");
    setENotes(o.notes ?? "");
    setELoss(o.loss_reason ?? "");
  }, []);

  const submitCreate = useCallback(async () => {
    setCreateErr(null);
    if (!nCustomer || !nTitle.trim()) {
      setCreateErr("Customer and title are required.");
      return;
    }
    setSaving(true);
    try {
      const res = await spaFetch("/opportunities", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          customer_id: Number(nCustomer),
          owner_user_id: nOwner ? Number(nOwner) : null,
          title: nTitle.trim(),
          amount: nAmount.trim() === "" ? null : Number(nAmount),
          expected_close_date: nClose || null,
          notes: nNotes.trim() ? nNotes.trim() : null,
        }),
      });
      if (!res.ok) {
        const b = await readApiError(res);
        setCreateErr(firstErrorMessage(b, "Could not create opportunity."));
        return;
      }
      resetCreate();
      setCreateOpen(false);
      await reload();
    } finally {
      setSaving(false);
    }
  }, [nAmount, nClose, nCustomer, nNotes, nOwner, nTitle, reload, resetCreate]);

  const submitEdit = useCallback(async () => {
    if (!editOpp) {
      return;
    }
    setEditErr(null);
    if (!eCustomer || !eTitle.trim()) {
      setEditErr("Customer and title are required.");
      return;
    }
    if (eStage === "lost" && !eLoss.trim()) {
      setEditErr("Loss reason is required when stage is Lost.");
      return;
    }
    setSaving(true);
    try {
      const res = await spaFetch(`/opportunities/${editOpp.id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          customer_id: Number(eCustomer),
          owner_user_id: eOwner ? Number(eOwner) : null,
          title: eTitle.trim(),
          amount: eAmount.trim() === "" ? null : Number(eAmount),
          expected_close_date: eClose || null,
          notes: eNotes.trim() ? eNotes.trim() : null,
          stage: eStage,
          loss_reason: eStage === "lost" ? eLoss.trim() : null,
        }),
      });
      if (!res.ok) {
        const b = await readApiError(res);
        setEditErr(firstErrorMessage(b, "Could not save."));
        return;
      }
      setEditOpp(null);
      await reload();
    } finally {
      setSaving(false);
    }
  }, [eAmount, eClose, eCustomer, eLoss, eNotes, eOwner, eStage, eTitle, editOpp, reload]);

  const submitMarkLost = useCallback(async () => {
    if (!markLostOpp) {
      return;
    }
    setMarkLostErr(null);
    if (!markLostReason.trim()) {
      setMarkLostErr("Please describe why this deal was lost.");
      return;
    }
    setSaving(true);
    try {
      const res = await spaFetch(`/opportunities/${markLostOpp.id}/stage`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          stage: "lost",
          loss_reason: markLostReason.trim(),
        }),
      });
      if (!res.ok) {
        const b = await readApiError(res);
        setMarkLostErr(firstErrorMessage(b, "Could not update stage."));
        return;
      }
      setMarkLostOpp(null);
      setMarkLostReason("");
      await reload();
    } finally {
      setSaving(false);
    }
  }, [markLostOpp, markLostReason, reload]);

  const patchStageQuick = useCallback(
    async (o: OppRow, stage: string): Promise<boolean> => {
      const res = await spaFetch(`/opportunities/${o.id}/stage`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ stage }),
      });
      if (!res.ok) {
        const b = await readApiError(res);
        window.alert(firstErrorMessage(b, "Could not move deal."));
        return false;
      }
      await reload();
      return true;
    },
    [reload],
  );

  const deleteOpp = useCallback(
    async (o: OppRow) => {
      if (!window.confirm(`Remove “${o.title}” from the pipeline?`)) {
        return;
      }
      const res = await spaFetch(`/opportunities/${o.id}`, { method: "DELETE" });
      if (!res.ok) {
        const b = await readApiError(res);
        window.alert(firstErrorMessage(b, "Could not remove."));
        return;
      }
      setEditOpp(null);
      await reload();
    },
    [reload],
  );

  const makeAttemptStageChange = useCallback(
    (o: OppRow) => async (next: string): Promise<boolean> => {
      if (next === "lost") {
        setMarkLostOpp(o);
        setMarkLostReason("");
        setMarkLostErr(null);
        return false;
      }
      return patchStageQuick(o, next);
    },
    [patchStageQuick],
  );

  return (
    <SpaPageFrame title="Sales pipeline" loading={loading} error={error}>
      <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
        <div>
          <p className="mt-1 max-w-2xl text-sm leading-relaxed text-slate-600">
            Track deals by stage, expected close dates, and ownership. Mark <span className="font-medium">Lost</span>{" "}
            with a reason; reopen closed deals by editing the stage.
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Link
            href="/sales"
            className="inline-flex rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50"
          >
            Sales dashboard
          </Link>
          <button
            type="button"
            onClick={openCreate}
            className="inline-flex rounded-md bg-pink-600 px-3 py-2 text-sm font-semibold text-white hover:bg-pink-700"
          >
            + New opportunity
          </button>
        </div>
      </div>

      <div className="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
        <div className="min-w-[200px] flex-1">
          <CustomerFilterCombobox
            customers={customers}
            value={draftCustomer}
            onValueChange={setDraftCustomer}
          />
        </div>
        <button
          type="button"
          onClick={applyCustomerFilter}
          className="rounded-md bg-pink-600 px-3 py-2 text-sm font-semibold text-white hover:bg-pink-700"
        >
          Apply filter
        </button>
        <button
          type="button"
          onClick={clearCustomerFilter}
          className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50"
        >
          Clear customer
        </button>
      </div>

      {filterCustomer ? (
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-pink-200 bg-pink-50/80 px-4 py-3 text-sm text-pink-950">
          <p>
            Showing opportunities for{" "}
            <Link href={`/customers/${filterCustomer.id}`} className="font-semibold underline">
              {filterCustomer.first_name} {filterCustomer.last_name}
            </Link>
          </p>
          <Link href={mergePipelineHref(sp, { customer_id: undefined })} className="text-xs font-semibold text-pink-800 hover:text-pink-900">
            Clear filter
          </Link>
        </div>
      ) : null}

      {data ? (
        <section className="mb-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Open pipeline value</p>
            <p className="mt-1 text-2xl font-bold tabular-nums text-slate-900">{formatMoney(data.openPipelineValue)}</p>
            <p className="mt-1 text-xs text-slate-500">Sum of deal amounts in New through Negotiation.</p>
          </div>
          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Closing in 30 days</p>
            <p className="mt-1 text-2xl font-bold tabular-nums text-slate-900">{formatMoney(data.closingNext30Days)}</p>
            <p className="mt-1 text-xs text-slate-500">Open deals with expected close in the next 30 days.</p>
          </div>
          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:col-span-2 lg:col-span-2">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Open deals by stage</p>
            <div className="mt-2 flex flex-wrap gap-2">
              {openStages.map((st) => (
                <span
                  key={st}
                  className="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-800"
                >
                  {stageLabels[st] ?? st}
                  <span className="tabular-nums text-slate-500">{countsOpen[st] ?? 0}</span>
                </span>
              ))}
            </div>
          </div>
        </section>
      ) : null}

      <div className="mb-4 overflow-x-auto pb-2">
        <div className="flex min-w-[64rem] gap-3">
          {openStages.map((stage) => (
            <div key={stage} className="flex w-64 shrink-0 flex-col rounded-xl border border-slate-200 bg-white/90 shadow-sm">
              <div className={`rounded-t-xl border-b border-slate-200 px-3 py-2 ${stageColumnClass(stage)}`}>
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-700">{stageLabels[stage] ?? stage}</p>
                <p className="text-[11px] text-slate-500">{(byStage[stage] ?? []).length} deal(s)</p>
              </div>
              <div className="flex flex-1 flex-col gap-2 p-2">
                {(byStage[stage] ?? []).map((o) => (
                  <OppCard
                    key={o.id}
                    o={o}
                    compact={false}
                    stageLabels={stageLabels}
                    allStages={allStages}
                    onEdit={() => openEdit(o)}
                    attemptStageChange={makeAttemptStageChange(o)}
                  />
                ))}
                {(byStage[stage] ?? []).length === 0 ? (
                  <p className="px-1 py-6 text-center text-xs text-slate-400">No deals</p>
                ) : null}
              </div>
            </div>
          ))}
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        {closedStages.map((closed) => (
          <section key={closed} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-base font-semibold text-slate-900">{stageLabels[closed] ?? closed}</h2>
            <p className="mt-1 text-xs text-slate-500">Most recent first (same filters as above).</p>
            <div className="mt-4 space-y-2">
              {(byStage[closed] ?? []).length ? (
                (byStage[closed] ?? []).map((o) => (
                  <OppCard
                    key={o.id}
                    o={o}
                    compact
                    stageLabels={stageLabels}
                    allStages={allStages}
                    onEdit={() => openEdit(o)}
                    attemptStageChange={async () => false}
                  />
                ))
              ) : (
                <p className="text-sm text-slate-500">No {(stageLabels[closed] ?? closed).toLowerCase()} deals yet.</p>
              )}
            </div>
          </section>
        ))}
      </div>

      {createOpen ? (
        <div
          className="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm"
          role="dialog"
        >
          <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-slate-200 bg-white p-5 shadow-xl">
            <div className="mb-4 flex items-center justify-between">
              <h2 className="text-lg font-semibold text-slate-900">New opportunity</h2>
              <button
                type="button"
                className="text-slate-500 hover:text-slate-800"
                onClick={() => {
                  setCreateOpen(false);
                  resetCreate();
                }}
              >
                ✕
              </button>
            </div>
            {createErr ? <p className="mb-2 text-sm text-red-600">{createErr}</p> : null}
            <div className="space-y-3">
              <label className="block text-sm">
                Customer
                <select
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={nCustomer}
                  onChange={(ev) => setNCustomer(ev.target.value)}
                  required
                >
                  <option value="">Select customer</option>
                  {customers.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.first_name} {c.last_name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-sm">
                Title
                <input
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={nTitle}
                  onChange={(ev) => setNTitle(ev.target.value)}
                  required
                />
              </label>
              <div className="grid gap-3 sm:grid-cols-2">
                <label className="block text-sm">
                  Amount (USD)
                  <input
                    type="number"
                    min={0}
                    step="0.01"
                    className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                    value={nAmount}
                    onChange={(ev) => setNAmount(ev.target.value)}
                  />
                </label>
                <label className="block text-sm">
                  Expected close
                  <input
                    type="date"
                    className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                    value={nClose}
                    onChange={(ev) => setNClose(ev.target.value)}
                  />
                </label>
              </div>
              <label className="block text-sm">
                Owner (optional)
                <select
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={nOwner}
                  onChange={(ev) => setNOwner(ev.target.value)}
                >
                  <option value="">Unassigned</option>
                  {staffUsers.map((u) => (
                    <option key={u.id} value={u.id}>
                      {u.name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-sm">
                Notes
                <textarea
                  rows={3}
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={nNotes}
                  onChange={(ev) => setNNotes(ev.target.value)}
                />
              </label>
            </div>
            <div className="mt-4 flex justify-end gap-2">
              <button
                type="button"
                className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50"
                onClick={() => {
                  setCreateOpen(false);
                  resetCreate();
                }}
              >
                Cancel
              </button>
              <button
                type="button"
                disabled={saving}
                onClick={() => void submitCreate()}
                className="rounded-md bg-pink-600 px-3 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-50"
              >
                Create
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {editOpp ? (
        <div
          className="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm"
          role="dialog"
        >
          <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-slate-200 bg-white p-5 shadow-xl">
            <div className="mb-4 flex items-center justify-between">
              <h2 className="text-lg font-semibold text-slate-900">Edit opportunity</h2>
              <button type="button" className="text-slate-500 hover:text-slate-800" onClick={() => setEditOpp(null)}>
                ✕
              </button>
            </div>
            {editErr ? <p className="mb-2 text-sm text-red-600">{editErr}</p> : null}
            <div className="space-y-3">
              <label className="block text-sm">
                Customer
                <select
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={eCustomer}
                  onChange={(ev) => setECustomer(ev.target.value)}
                >
                  {customers.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.first_name} {c.last_name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-sm">
                Title
                <input
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={eTitle}
                  onChange={(ev) => setETitle(ev.target.value)}
                />
              </label>
              <div className="grid gap-3 sm:grid-cols-2">
                <label className="block text-sm">
                  Amount (USD)
                  <input
                    type="number"
                    min={0}
                    step="0.01"
                    className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                    value={eAmount}
                    onChange={(ev) => setEAmount(ev.target.value)}
                  />
                </label>
                <label className="block text-sm">
                  Expected close
                  <input
                    type="date"
                    className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                    value={eClose}
                    onChange={(ev) => setEClose(ev.target.value)}
                  />
                </label>
              </div>
              <label className="block text-sm">
                Stage
                <select
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={eStage}
                  onChange={(ev) => setEStage(ev.target.value)}
                >
                  {allStages.map((st) => (
                    <option key={st} value={st}>
                      {stageLabels[st] ?? st}
                    </option>
                  ))}
                </select>
              </label>
              {eStage === "lost" ? (
                <label className="block text-sm">
                  Loss reason <span className="text-rose-600">*</span>
                  <textarea
                    rows={3}
                    className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                    value={eLoss}
                    onChange={(ev) => setELoss(ev.target.value)}
                    placeholder="Why was this deal lost?"
                  />
                </label>
              ) : null}
              <label className="block text-sm">
                Owner (optional)
                <select
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={eOwner}
                  onChange={(ev) => setEOwner(ev.target.value)}
                >
                  <option value="">Unassigned</option>
                  {staffUsers.map((u) => (
                    <option key={u.id} value={u.id}>
                      {u.name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-sm">
                Notes
                <textarea
                  rows={3}
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={eNotes}
                  onChange={(ev) => setENotes(ev.target.value)}
                />
              </label>
            </div>
            <div className="mt-4 flex justify-end gap-2 border-t border-slate-200 pt-3">
              <button
                type="button"
                className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50"
                onClick={() => setEditOpp(null)}
              >
                Cancel
              </button>
              <button
                type="button"
                disabled={saving}
                onClick={() => void submitEdit()}
                className="rounded-md bg-pink-600 px-3 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-50"
              >
                Save
              </button>
            </div>
            <div className="mt-3 border-t border-slate-200 pt-3">
              <button
                type="button"
                onClick={() => void deleteOpp(editOpp)}
                className="text-sm font-semibold text-rose-600 hover:text-rose-700"
              >
                Delete opportunity
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {markLostOpp ? (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm"
          role="dialog"
        >
          <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white p-5 shadow-xl">
            <div className="mb-4 flex items-center justify-between">
              <h2 className="text-lg font-semibold text-slate-900">Mark as lost</h2>
              <button
                type="button"
                className="text-slate-500 hover:text-slate-800"
                onClick={() => {
                  setMarkLostOpp(null);
                  setMarkLostReason("");
                  setMarkLostErr(null);
                }}
              >
                ✕
              </button>
            </div>
            {markLostErr ? <p className="mb-2 text-sm text-red-600">{markLostErr}</p> : null}
            <label className="block text-sm">
              Loss reason <span className="text-rose-600">*</span>
              <textarea
                rows={4}
                className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                value={markLostReason}
                onChange={(ev) => setMarkLostReason(ev.target.value)}
                placeholder="What happened?"
              />
            </label>
            <div className="mt-4 flex justify-end gap-2">
              <button
                type="button"
                className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50"
                onClick={() => {
                  setMarkLostOpp(null);
                  setMarkLostReason("");
                  setMarkLostErr(null);
                }}
              >
                Cancel
              </button>
              <button
                type="button"
                disabled={saving}
                onClick={() => void submitMarkLost()}
                className="rounded-md bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50"
              >
                Save
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </SpaPageFrame>
  );
}

function OppCard({
  o,
  compact,
  stageLabels,
  allStages,
  onEdit,
  attemptStageChange,
}: {
  o: OppRow;
  compact: boolean;
  stageLabels: Record<string, string>;
  allStages: string[];
  onEdit: () => void;
  attemptStageChange: (next: string) => Promise<boolean>;
}) {
  const [localStage, setLocalStage] = useState(o.stage);
  useEffect(() => {
    setLocalStage(o.stage);
  }, [o.id, o.stage]);

  return (
    <div className="rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm">
      <p className="text-sm font-semibold leading-snug text-slate-900">{o.title}</p>
      <p className="mt-1 text-xs text-slate-600">
        {o.customer ? (
          <Link href={`/customers/${o.customer.id}`} className="font-medium text-pink-700 hover:text-pink-800">
            {o.customer.first_name} {o.customer.last_name}
          </Link>
        ) : (
          "—"
        )}
      </p>
      <p className="mt-1 text-xs text-slate-500">
        <span className="font-medium text-slate-700">{formatMoney(o.amount)}</span>
        {o.expected_close_date ? <> · Close {formatCloseDate(o.expected_close_date)}</> : <> · No close date</>}
      </p>
      {o.owner ? <p className="mt-0.5 text-[11px] text-slate-500">Owner: {o.owner.name}</p> : null}
      {o.stage === "lost" && o.loss_reason ? (
        <p className="mt-1 text-[11px] text-rose-800">
          {o.loss_reason.length > 120 ? `${o.loss_reason.slice(0, 120)}…` : o.loss_reason}
        </p>
      ) : null}
      {!compact ? (
        <label className="mt-2 block text-[10px] font-semibold uppercase tracking-wide text-slate-400">Move to</label>
      ) : null}
      {!compact ? (
        <select
          className="mt-0.5 block w-full rounded border border-slate-300 py-1.5 text-xs"
          value={localStage}
          onChange={(ev) => {
            const val = ev.target.value;
            void (async () => {
              if (val === "lost") {
                setLocalStage(o.stage);
                await attemptStageChange("lost");
                return;
              }
              const prev = localStage;
              setLocalStage(val);
              const ok = await attemptStageChange(val);
              if (!ok) {
                setLocalStage(prev);
              }
            })();
          }}
        >
          {allStages.map((st) => (
            <option key={st} value={st}>
              {stageLabels[st] ?? st}
            </option>
          ))}
        </select>
      ) : null}
      <button
        type="button"
        className="mt-2 w-full rounded-md border border-slate-200 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
        onClick={onEdit}
      >
        Edit
      </button>
    </div>
  );
}

export function PipelinePageClient() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-slate-600">Loading pipeline…</div>}>
      <PipelinePageInner />
    </Suspense>
  );
}
