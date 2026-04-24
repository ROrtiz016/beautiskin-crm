"use client";

import { SpaPageFrame } from "@/components/spa-page-frame";
import { useAuth } from "@/context/auth-context";
import { useSpaGet } from "@/hooks/use-spa-get";
import { firstErrorMessage } from "@/lib/laravel-form-errors";
import { spaFetch } from "@/lib/spa-fetch";
import { Suspense, useCallback, useEffect, useState } from "react";

type UnknownRec = Record<string, unknown>;

type StaffRow = {
  staff_id: number;
  staff_name: string;
  booked_minutes: number;
  utilization_percent: number;
  appointment_count: number;
};

type OperationsPayload = {
  metricsDateLabel?: string;
  metricsTimezone?: string;
  todaysRevenue?: number;
  noShowsToday?: number;
  waitlistDepth?: number;
  staffUtilizationRows?: StaffRow[];
  clinicSettings?: UnknownRec;
};

function money(n: number): string {
  return n.toLocaleString(undefined, { style: "currency", currency: "USD" });
}

function OperationsInner() {
  const { user } = useAuth();
  const { data, error, loading, reload } = useSpaGet<OperationsPayload>("/spa/admin/operations");
  const clinic = data?.clinicSettings as UnknownRec | undefined;
  const flags = (clinic?.feature_flags as Record<string, unknown> | undefined) ?? {};

  const [cancelHrs, setCancelHrs] = useState("");
  const [maxBookings, setMaxBookings] = useState("");
  const [depositRequired, setDepositRequired] = useState(false);
  const [defaultDeposit, setDefaultDeposit] = useState("");
  const [policyBusy, setPolicyBusy] = useState(false);
  const [policyErr, setPolicyErr] = useState<string | null>(null);
  const [policyOk, setPolicyOk] = useState<string | null>(null);

  const [experimentalUi, setExperimentalUi] = useState(false);
  const [flagsBusy, setFlagsBusy] = useState(false);
  const [flagsErr, setFlagsErr] = useState<string | null>(null);
  const [flagsOk, setFlagsOk] = useState<string | null>(null);

  useEffect(() => {
    if (!clinic) {
      return;
    }
    setCancelHrs(String(clinic.appointment_cancellation_hours ?? ""));
    setMaxBookings(clinic.max_bookings_per_day != null && clinic.max_bookings_per_day !== "" ? String(clinic.max_bookings_per_day) : "");
    setDepositRequired(Boolean(clinic.deposit_required));
    setDefaultDeposit(
      clinic.default_deposit_amount != null && clinic.default_deposit_amount !== "" ? String(clinic.default_deposit_amount) : "",
    );
    setExperimentalUi(Boolean(flags.experimental_ui));
  }, [clinic, flags.experimental_ui]);

  const savePolicy = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault();
      setPolicyErr(null);
      setPolicyOk(null);
      setPolicyBusy(true);
      try {
        const hrs = Number(cancelHrs);
        if (!Number.isFinite(hrs) || hrs < 0) {
          setPolicyErr("Enter a valid cancellation notice (hours).");
          return;
        }
        const body: Record<string, unknown> = {
          appointment_cancellation_hours: hrs,
          deposit_required: depositRequired,
          default_deposit_amount: defaultDeposit.trim() === "" ? null : Number(defaultDeposit),
          max_bookings_per_day: maxBookings.trim() === "" ? null : Number(maxBookings),
        };
        const res = await spaFetch("/admin/operations/appointment-policy", {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(body),
        });
        const b = await res.json().catch(() => ({}));
        if (!res.ok) {
          setPolicyErr(firstErrorMessage(b, "Could not save policy."));
          return;
        }
        setPolicyOk((b as { message?: string }).message ?? "Saved.");
        await reload();
      } catch {
        setPolicyErr("Could not reach the server.");
      } finally {
        setPolicyBusy(false);
      }
    },
    [cancelHrs, defaultDeposit, depositRequired, maxBookings, reload],
  );

  const saveFlags = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault();
      setFlagsErr(null);
      setFlagsOk(null);
      setFlagsBusy(true);
      try {
        const res = await spaFetch("/admin/operations/feature-flags", {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ experimental_ui: experimentalUi }),
        });
        const b = await res.json().catch(() => ({}));
        if (!res.ok) {
          setFlagsErr(firstErrorMessage(b, "Could not save feature flags."));
          return;
        }
        setFlagsOk((b as { message?: string }).message ?? "Saved.");
        await reload();
      } catch {
        setFlagsErr("Could not reach the server.");
      } finally {
        setFlagsBusy(false);
      }
    },
    [experimentalUi, reload],
  );

  const rows = data?.staffUtilizationRows ?? [];

  return (
    <SpaPageFrame
      title="Operations"
      subtitle={data?.metricsDateLabel ? `Today · ${data.metricsDateLabel}` : "Clinic operations snapshot"}
      loading={loading}
      error={error}
    >
      {data ? (
        <>
          <div className="mb-6 grid gap-3 sm:grid-cols-3">
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <p className="text-xs font-semibold uppercase text-slate-500">Today&apos;s revenue (completed)</p>
              <p className="mt-1 text-2xl font-bold text-slate-900">{money(Number(data.todaysRevenue ?? 0))}</p>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <p className="text-xs font-semibold uppercase text-slate-500">No-shows today</p>
              <p className="mt-1 text-2xl font-bold text-slate-900">{data.noShowsToday ?? 0}</p>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <p className="text-xs font-semibold uppercase text-slate-500">Waitlist depth</p>
              <p className="mt-1 text-2xl font-bold text-slate-900">{data.waitlistDepth ?? 0}</p>
              <p className="mt-1 text-xs text-slate-500">Waiting + contacted</p>
            </div>
          </div>

          {clinic ? (
            <div className="mb-6 grid gap-6 lg:grid-cols-2">
              <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 className="text-lg font-semibold text-slate-900">Appointment policy</h2>
                <p className="mt-1 text-sm text-slate-600">Enforced when creating, rescheduling, or cancelling appointments.</p>
                <form className="mt-4 space-y-3" onSubmit={savePolicy}>
                  <label className="block text-sm">
                    <span className="font-medium text-slate-700">Cancellation notice (hours)</span>
                    <input
                      type="number"
                      min={0}
                      max={8760}
                      className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                      value={cancelHrs}
                      onChange={(ev) => setCancelHrs(ev.target.value)}
                    />
                    <span className="mt-1 block text-xs text-slate-500">Minimum hours before start to allow cancellation. Use 0 to disable.</span>
                  </label>
                  <label className="block text-sm">
                    <span className="font-medium text-slate-700">Max active bookings per clinic day</span>
                    <input
                      type="number"
                      min={1}
                      max={500}
                      placeholder="Unlimited"
                      className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                      value={maxBookings}
                      onChange={(ev) => setMaxBookings(ev.target.value)}
                    />
                  </label>
                  <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={depositRequired} onChange={(ev) => setDepositRequired(ev.target.checked)} />
                    <span className="font-medium text-slate-700">Deposit required for new appointments</span>
                  </label>
                  <label className="block text-sm">
                    <span className="font-medium text-slate-700">Default deposit amount (optional)</span>
                    <input
                      type="number"
                      step="0.01"
                      min={0}
                      className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                      value={defaultDeposit}
                      onChange={(ev) => setDefaultDeposit(ev.target.value)}
                    />
                  </label>
                  {policyErr ? <p className="text-xs text-rose-600">{policyErr}</p> : null}
                  {policyOk ? <p className="text-xs text-emerald-700">{policyOk}</p> : null}
                  <button
                    type="submit"
                    disabled={policyBusy}
                    className="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-50"
                  >
                    {policyBusy ? "Saving…" : "Save policy"}
                  </button>
                </form>
              </section>

              {user?.can.manage_feature_flags ? (
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                  <h2 className="text-lg font-semibold text-slate-900">Feature flags</h2>
                  <p className="mt-1 text-sm text-slate-600">Experimental UI is visible only to full administrators when enabled.</p>
                  <form className="mt-4 space-y-3" onSubmit={saveFlags}>
                    <label className="flex items-center gap-2 text-sm">
                      <input type="checkbox" checked={experimentalUi} onChange={(ev) => setExperimentalUi(ev.target.checked)} />
                      <span className="font-medium text-slate-700">Experimental UI (admins only)</span>
                    </label>
                    {flagsErr ? <p className="text-xs text-rose-600">{flagsErr}</p> : null}
                    {flagsOk ? <p className="text-xs text-emerald-700">{flagsOk}</p> : null}
                    <button
                      type="submit"
                      disabled={flagsBusy}
                      className="rounded-md bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900 disabled:opacity-50"
                    >
                      {flagsBusy ? "Saving…" : "Save feature flags"}
                    </button>
                  </form>
                </section>
              ) : (
                <section className="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-5">
                  <h2 className="text-lg font-semibold text-slate-700">Feature flags</h2>
                  <p className="mt-2 text-sm text-slate-600">Only full administrators can change experimental UI flags.</p>
                </section>
              )}
            </div>
          ) : null}

          {clinic ? (
            <section className="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <h2 className="text-lg font-semibold text-slate-900">Clinic settings (snapshot)</h2>
              <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                <div>
                  <dt className="text-xs text-slate-500">Cancellation window (hours)</dt>
                  <dd className="font-medium">{String(clinic.appointment_cancellation_hours ?? "—")}</dd>
                </div>
                <div>
                  <dt className="text-xs text-slate-500">Deposit required</dt>
                  <dd className="font-medium">{clinic.deposit_required ? "Yes" : "No"}</dd>
                </div>
                <div>
                  <dt className="text-xs text-slate-500">Default appointment length (min)</dt>
                  <dd className="font-medium">{String(clinic.default_appointment_length_minutes ?? "—")}</dd>
                </div>
                <div>
                  <dt className="text-xs text-slate-500">Max bookings / day</dt>
                  <dd className="font-medium">{clinic.max_bookings_per_day != null ? String(clinic.max_bookings_per_day) : "—"}</dd>
                </div>
              </dl>
            </section>
          ) : null}

          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">Staff utilization (today)</h2>
            <p className="mt-1 text-xs text-slate-500">Approximate booked minutes vs an 8h workday model.</p>
            <div className="mt-4 overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead className="border-b border-slate-200 text-xs text-slate-500">
                  <tr>
                    <th className="py-2 pr-3">Staff</th>
                    <th className="py-2 pr-3">Appointments</th>
                    <th className="py-2 pr-3">Booked min</th>
                    <th className="py-2 pr-3">Utilization</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.length ? (
                    rows.map((r) => (
                      <tr key={r.staff_id} className="border-b border-slate-100">
                        <td className="py-2 pr-3 font-medium">{r.staff_name}</td>
                        <td className="py-2 pr-3">{r.appointment_count}</td>
                        <td className="py-2 pr-3">{r.booked_minutes}</td>
                        <td className="py-2 pr-3">{r.utilization_percent}%</td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={4} className="py-6 text-slate-500">
                        No appointments on the board for today.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </section>
        </>
      ) : null}
    </SpaPageFrame>
  );
}

export function OperationsPageClient() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-slate-600">Loading operations…</div>}>
      <OperationsInner />
    </Suspense>
  );
}
