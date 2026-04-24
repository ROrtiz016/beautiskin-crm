"use client";

import { SpaPageFrame } from "@/components/spa-page-frame";
import { useSpaGet } from "@/hooks/use-spa-get";
import { firstErrorMessage } from "@/lib/laravel-form-errors";
import { spaFetch } from "@/lib/spa-fetch";
import { Suspense, useCallback, useEffect, useMemo, useState } from "react";
import { ControlBoardAdminParity } from "./control-board-admin-parity";

type UnknownRec = Record<string, unknown>;

type AuditLog = {
  id: number;
  action?: string;
  entity_type?: string;
  entity_id?: number;
  created_at?: string;
  actor?: { name?: string; email?: string };
};

type ServiceRow = { id: number; name: string; price: string | number };
type MembershipRow = { id: number; name: string; monthly_price: string | number; billing_cycle_days: number };
type ChangeableRef = { id?: number; name?: string };

type ScheduledPending = {
  id: number;
  new_price: string | number;
  effective_at?: string | null;
  status?: string;
  changeable?: ChangeableRef | null;
  changeable_type?: string;
};

type CustomerBrief = { id: number; first_name?: string; last_name?: string; email?: string | null };

type ControlBoardPayload = {
  users?: UnknownRec[];
  auditLogs?: AuditLog[];
  services?: ServiceRow[];
  memberships?: MembershipRow[];
  promotions?: UnknownRec[];
  scheduledPriceChangesPending?: ScheduledPending[];
  scheduledPriceChangesRecent?: ScheduledPending[];
  customersForRetention?: CustomerBrief[];
  clinicSettings?: UnknownRec;
  permissionOptions?: string[];
  roleTemplateLabels?: Record<string, string>;
  applyRoleTemplateLabels?: Record<string, string>;
};

function moneyFromString(n: string | number): string {
  return Number(n).toLocaleString(undefined, { style: "currency", currency: "USD" });
}

function scheduledTargetLabel(row: ScheduledPending): string {
  const ch = row.changeable;
  if (ch?.name) {
    const t = row.changeable_type ?? "";
    const kind = t.includes("Membership") ? "Membership" : t.includes("Service") ? "Service" : "Item";
    return `${kind}: ${ch.name}`;
  }
  return "Unknown target";
}

function defaultEffectiveLocal(): string {
  const d = new Date();
  d.setMinutes(0, 0, 0);
  d.setHours(d.getHours() + 1);
  const pad = (x: number) => String(x).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function ControlBoardInner() {
  const { data, error, loading, reload } = useSpaGet<ControlBoardPayload>("/spa/admin/control-board");
  const users = data?.users ?? [];
  const logs = data?.auditLogs ?? [];
  const services = data?.services ?? [];
  const memberships = data?.memberships ?? [];
  const promos = (data?.promotions ?? []) as UnknownRec[];
  const clinicSettings = data?.clinicSettings;
  const permissionOptions = data?.permissionOptions;
  const roleTemplateLabels = data?.roleTemplateLabels;
  const applyRoleTemplateLabels = data?.applyRoleTemplateLabels;
  const customersForRetention = data?.customersForRetention;
  const pendingPrices = data?.scheduledPriceChangesPending ?? [];

  const [serviceDrafts, setServiceDrafts] = useState<Record<number, string>>({});
  const [membershipDrafts, setMembershipDrafts] = useState<Record<number, string>>({});
  const [savingServiceId, setSavingServiceId] = useState<number | null>(null);
  const [savingMembershipId, setSavingMembershipId] = useState<number | null>(null);
  const [promoBusyId, setPromoBusyId] = useState<number | null>(null);
  const [schedulePriceable, setSchedulePriceable] = useState("");
  const [scheduleNewPrice, setScheduleNewPrice] = useState("");
  const [scheduleEffective, setScheduleEffective] = useState("");
  const [scheduleBusy, setScheduleBusy] = useState(false);
  const [scheduleErr, setScheduleErr] = useState<string | null>(null);
  const [scheduleOk, setScheduleOk] = useState<string | null>(null);
  const [cancelBusyId, setCancelBusyId] = useState<number | null>(null);
  const [inlineErr, setInlineErr] = useState<string | null>(null);

  useEffect(() => {
    const nextS: Record<number, string> = {};
    for (const s of services) {
      nextS[s.id] = Number(s.price).toFixed(2);
    }
    setServiceDrafts(nextS);
  }, [services]);

  useEffect(() => {
    const nextM: Record<number, string> = {};
    for (const m of memberships) {
      nextM[m.id] = Number(m.monthly_price).toFixed(2);
    }
    setMembershipDrafts(nextM);
  }, [memberships]);

  useEffect(() => {
    if (!data) {
      return;
    }
    setScheduleEffective((prev) => prev || defaultEffectiveLocal());
    setSchedulePriceable((prev) => {
      if (prev) {
        return prev;
      }
      if (services[0]) {
        return `service:${services[0].id}`;
      }
      if (memberships[0]) {
        return `membership:${memberships[0].id}`;
      }
      return prev;
    });
  }, [data, services, memberships]);

  const priceableOptions = useMemo(() => {
    const opts: { value: string; label: string }[] = [];
    for (const s of services) {
      opts.push({ value: `service:${s.id}`, label: `Service · ${s.name}` });
    }
    for (const m of memberships) {
      opts.push({ value: `membership:${m.id}`, label: `Membership · ${m.name}` });
    }
    return opts;
  }, [services, memberships]);

  const saveServicePrice = useCallback(
    async (id: number) => {
      setInlineErr(null);
      setSavingServiceId(id);
      try {
        const raw = serviceDrafts[id] ?? "";
        const price = Number(raw);
        if (!Number.isFinite(price) || price < 0) {
          setInlineErr("Enter a valid service price.");
          return;
        }
        const res = await spaFetch(`/admin/services/${id}/price`, {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ price }),
        });
        const b = await res.json().catch(() => ({}));
        if (!res.ok) {
          setInlineErr(firstErrorMessage(b, "Could not update service price."));
          return;
        }
        await reload();
      } catch {
        setInlineErr("Could not reach the server.");
      } finally {
        setSavingServiceId(null);
      }
    },
    [reload, serviceDrafts],
  );

  const saveMembershipPrice = useCallback(
    async (id: number) => {
      setInlineErr(null);
      setSavingMembershipId(id);
      try {
        const raw = membershipDrafts[id] ?? "";
        const price = Number(raw);
        if (!Number.isFinite(price) || price < 0) {
          setInlineErr("Enter a valid membership price.");
          return;
        }
        const res = await spaFetch(`/admin/memberships/${id}/price`, {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ price }),
        });
        const b = await res.json().catch(() => ({}));
        if (!res.ok) {
          setInlineErr(firstErrorMessage(b, "Could not update membership price."));
          return;
        }
        await reload();
      } catch {
        setInlineErr("Could not reach the server.");
      } finally {
        setSavingMembershipId(null);
      }
    },
    [membershipDrafts, reload],
  );

  const togglePromotion = useCallback(
    async (promotion: UnknownRec, nextActive: boolean) => {
      const pid = Number(promotion.id);
      if (!Number.isFinite(pid)) {
        return;
      }
      setInlineErr(null);
      setPromoBusyId(pid);
      try {
        const res = await spaFetch(`/admin/promotions/${pid}/status`, {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ is_active: nextActive }),
        });
        const b = await res.json().catch(() => ({}));
        if (!res.ok) {
          setInlineErr(firstErrorMessage(b, "Could not update promotion."));
          return;
        }
        await reload();
      } catch {
        setInlineErr("Could not reach the server.");
      } finally {
        setPromoBusyId(null);
      }
    },
    [reload],
  );

  const submitSchedule = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault();
      setScheduleErr(null);
      setScheduleOk(null);
      setScheduleBusy(true);
      try {
        const price = Number(scheduleNewPrice);
        if (!Number.isFinite(price) || price < 0) {
          setScheduleErr("Enter a valid future price.");
          setScheduleBusy(false);
          return;
        }
        if (!schedulePriceable || !scheduleEffective) {
          setScheduleErr("Pick a catalog item and effective time.");
          setScheduleBusy(false);
          return;
        }
        const iso = new Date(scheduleEffective).toISOString();
        const res = await spaFetch("/admin/scheduled-price-changes", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            priceable: schedulePriceable,
            new_price: price,
            effective_at: iso,
          }),
        });
        const b = await res.json().catch(() => ({}));
        if (!res.ok) {
          setScheduleErr(firstErrorMessage(b, "Could not schedule price change."));
          return;
        }
        setScheduleOk((b as { message?: string }).message ?? "Scheduled.");
        setScheduleNewPrice("");
        setScheduleEffective(defaultEffectiveLocal());
        await reload();
      } catch {
        setScheduleErr("Could not reach the server.");
      } finally {
        setScheduleBusy(false);
      }
    },
    [reload, scheduleEffective, scheduleNewPrice, schedulePriceable],
  );

  const cancelScheduled = useCallback(
    async (id: number) => {
      if (!window.confirm("Cancel this scheduled change?")) {
        return;
      }
      setInlineErr(null);
      setCancelBusyId(id);
      try {
        const res = await spaFetch(`/admin/scheduled-price-changes/${id}/cancel`, { method: "POST" });
        const b = await res.json().catch(() => ({}));
        if (!res.ok) {
          setInlineErr(firstErrorMessage(b, "Could not cancel."));
          return;
        }
        await reload();
      } catch {
        setInlineErr("Could not reach the server.");
      } finally {
        setCancelBusyId(null);
      }
    },
    [reload],
  );

  return (
    <SpaPageFrame
      title="Control board"
      subtitle="Pricing, promotions, clinic settings, users, scheduled catalog changes, backups, and GDPR tools."
      loading={loading}
      error={error}
    >
      {data ? (
        <div className="space-y-8">
          {inlineErr ? <p className="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">{inlineErr}</p> : null}

          <div className="grid gap-3 sm:grid-cols-3">
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <p className="text-xs font-semibold uppercase text-slate-500">Users</p>
              <p className="mt-1 text-2xl font-bold text-slate-900">{users.length}</p>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <p className="text-xs font-semibold uppercase text-slate-500">Promotions</p>
              <p className="mt-1 text-2xl font-bold text-slate-900">{promos.length}</p>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <p className="text-xs font-semibold uppercase text-slate-500">Pending price changes</p>
              <p className="mt-1 text-2xl font-bold text-slate-900">{pendingPrices.length}</p>
            </div>
          </div>

          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">Service prices (live)</h2>
            <div className="mt-4 grid gap-3 md:grid-cols-2">
              {services.map((s) => (
                <div key={s.id} className="rounded-lg border border-slate-200 p-3">
                  <label className="mb-1 block text-sm font-medium text-slate-800">{s.name}</label>
                  <div className="flex gap-2">
                    <input
                      type="number"
                      step="0.01"
                      min={0}
                      className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                      value={serviceDrafts[s.id] ?? ""}
                      onChange={(ev) => setServiceDrafts((prev) => ({ ...prev, [s.id]: ev.target.value }))}
                    />
                    <button
                      type="button"
                      onClick={() => void saveServicePrice(s.id)}
                      disabled={savingServiceId === s.id}
                      className="shrink-0 rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                    >
                      {savingServiceId === s.id ? "…" : "Save"}
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">Membership prices (live)</h2>
            <div className="mt-4 grid gap-3 md:grid-cols-2">
              {memberships.map((m) => (
                <div key={m.id} className="rounded-lg border border-slate-200 p-3">
                  <label className="mb-1 block text-sm font-medium text-slate-800">
                    {m.name}{" "}
                    <span className="text-xs font-normal text-slate-500">
                      ({Number(m.billing_cycle_days) >= 365 ? "Yearly" : "Monthly"})
                    </span>
                  </label>
                  <div className="flex gap-2">
                    <input
                      type="number"
                      step="0.01"
                      min={0}
                      className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                      value={membershipDrafts[m.id] ?? ""}
                      onChange={(ev) => setMembershipDrafts((prev) => ({ ...prev, [m.id]: ev.target.value }))}
                    />
                    <button
                      type="button"
                      onClick={() => void saveMembershipPrice(m.id)}
                      disabled={savingMembershipId === m.id}
                      className="shrink-0 rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                    >
                      {savingMembershipId === m.id ? "…" : "Save"}
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </section>

          <ControlBoardAdminParity
            clinicSettings={clinicSettings}
            services={services}
            memberships={memberships}
            users={users}
            permissionOptions={permissionOptions}
            roleTemplateLabels={roleTemplateLabels}
            applyRoleTemplateLabels={applyRoleTemplateLabels}
            customersForRetention={customersForRetention}
            promotions={promos}
            promoBusyId={promoBusyId}
            onTogglePromotionActive={(p, next) => void togglePromotion(p, next)}
            reload={reload}
            setInlineErr={setInlineErr}
          />

          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">Scheduled price changes</h2>
            <div className="mt-4 grid gap-6 lg:grid-cols-2">
              <div>
                <h3 className="text-sm font-semibold text-slate-800">Queue a change</h3>
                <form className="mt-3 space-y-3" onSubmit={submitSchedule}>
                  <label className="block text-sm">
                    <span className="font-medium text-slate-700">Catalog item</span>
                    <select
                      className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                      value={schedulePriceable}
                      onChange={(ev) => setSchedulePriceable(ev.target.value)}
                    >
                      {priceableOptions.length ? (
                        priceableOptions.map((o) => (
                          <option key={o.value} value={o.value}>
                            {o.label}
                          </option>
                        ))
                      ) : (
                        <option value="">No services or memberships</option>
                      )}
                    </select>
                  </label>
                  <label className="block text-sm">
                    <span className="font-medium text-slate-700">New price (USD)</span>
                    <input
                      type="number"
                      step="0.01"
                      min={0}
                      className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                      value={scheduleNewPrice}
                      onChange={(ev) => setScheduleNewPrice(ev.target.value)}
                    />
                  </label>
                  <label className="block text-sm">
                    <span className="font-medium text-slate-700">Effective at (local)</span>
                    <input
                      type="datetime-local"
                      className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                      value={scheduleEffective}
                      onChange={(ev) => setScheduleEffective(ev.target.value)}
                    />
                  </label>
                  {scheduleErr ? <p className="text-xs text-rose-600">{scheduleErr}</p> : null}
                  {scheduleOk ? <p className="text-xs text-emerald-700">{scheduleOk}</p> : null}
                  <button
                    type="submit"
                    disabled={scheduleBusy || !priceableOptions.length}
                    className="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-50"
                  >
                    {scheduleBusy ? "Scheduling…" : "Schedule change"}
                  </button>
                </form>
              </div>
              <div>
                <h3 className="text-sm font-semibold text-slate-800">Pending</h3>
                <ul className="mt-3 space-y-2">
                  {pendingPrices.length ? (
                    pendingPrices.map((ch) => (
                      <li key={ch.id} className="rounded-md border border-slate-200 p-3 text-sm">
                        <div className="font-medium">{scheduledTargetLabel(ch)}</div>
                        <div className="mt-1 text-xs text-slate-600">
                          {moneyFromString(ch.new_price)}
                          {ch.effective_at ? ` · ${new Date(ch.effective_at).toLocaleString()}` : ""}
                        </div>
                        <button
                          type="button"
                          className="mt-2 text-xs font-semibold text-rose-600 hover:text-rose-700 disabled:opacity-50"
                          disabled={cancelBusyId === ch.id}
                          onClick={() => void cancelScheduled(ch.id)}
                        >
                          {cancelBusyId === ch.id ? "Cancelling…" : "Cancel"}
                        </button>
                      </li>
                    ))
                  ) : (
                    <li className="text-xs text-slate-500">No pending changes.</li>
                  )}
                </ul>
              </div>
            </div>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">Recent audit log</h2>
            <p className="mt-1 text-xs text-slate-500">Latest entries (filtered limit from server).</p>
            <ul className="mt-4 divide-y divide-slate-100 text-sm">
              {logs.length ? (
                logs.map((log) => (
                  <li key={log.id} className="py-2">
                    <p className="font-mono text-xs text-slate-800">{log.action}</p>
                    <p className="text-xs text-slate-500">
                      {log.entity_type} #{log.entity_id ?? "—"} · {log.created_at ? new Date(log.created_at).toLocaleString() : ""} ·{" "}
                      {log.actor?.name ?? "—"}
                    </p>
                  </li>
                ))
              ) : (
                <li className="py-6 text-slate-500">No audit entries.</li>
              )}
            </ul>
          </section>
        </div>
      ) : null}
    </SpaPageFrame>
  );
}

export function ControlBoardPageClient() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-slate-600">Loading control board…</div>}>
      <ControlBoardInner />
    </Suspense>
  );
}
