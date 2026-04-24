"use client";

import { SpaPageFrame } from "@/components/spa-page-frame";
import { useSpaGet } from "@/hooks/use-spa-get";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useMemo, useState } from "react";

type UnknownRec = Record<string, unknown>;

type SalesPayload = {
  clinicTimezone?: string;
  fromDate: string;
  toDate: string;
  rangeLabel: string;
  completedRevenue: number;
  appointmentVolume: number;
  completedAppointmentCount: number;
  lineItemRevenue: number;
  newMemberships: number;
  topServices: UnknownRec[];
};

function money(n: number): string {
  return n.toLocaleString(undefined, { style: "currency", currency: "USD" });
}

function SalesOverviewInner() {
  const sp = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();

  const path = useMemo(() => `/spa/sales${sp.toString() ? `?${sp}` : ""}`, [sp]);
  const { data, error, loading } = useSpaGet<SalesPayload>(path);

  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");

  useEffect(() => {
    setFrom(sp.get("from") ?? data?.fromDate ?? "");
    setTo(sp.get("to") ?? data?.toDate ?? "");
  }, [sp, data?.fromDate, data?.toDate]);

  const applyRange = useCallback(() => {
    const n = new URLSearchParams();
    if (from) {
      n.set("from", from);
    }
    if (to) {
      n.set("to", to);
    }
    const s = n.toString();
    router.push(s ? `${pathname}?${s}` : pathname);
  }, [from, pathname, router, to]);

  const top = data?.topServices ?? [];

  return (
    <SpaPageFrame title="Sales" subtitle={data?.rangeLabel} loading={loading} error={error}>
      <p className="mb-4 text-sm">
        <Link href="/sales/pipeline" className="font-medium text-pink-700 hover:underline">
          Sales pipeline →
        </Link>
      </p>

      <section className="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 className="text-sm font-semibold text-slate-900">Date range</h2>
        <p className="mt-1 text-xs text-slate-500">Timezone: {data?.clinicTimezone ?? "—"}</p>
        <div className="mt-3 flex flex-wrap items-end gap-3">
          <label className="text-sm">
            From
            <input type="date" className="mt-1 block rounded border border-slate-300 px-2 py-1.5 text-sm" value={from} onChange={(e) => setFrom(e.target.value)} />
          </label>
          <label className="text-sm">
            To
            <input type="date" className="mt-1 block rounded border border-slate-300 px-2 py-1.5 text-sm" value={to} onChange={(e) => setTo(e.target.value)} />
          </label>
          <button type="button" onClick={applyRange} className="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">
            Apply
          </button>
        </div>
      </section>

      {data ? (
        <>
          <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <p className="text-xs font-semibold uppercase text-slate-500">Completed revenue</p>
              <p className="mt-1 text-2xl font-bold text-slate-900">{money(Number(data.completedRevenue))}</p>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <p className="text-xs font-semibold uppercase text-slate-500">Appointments (non-cancelled)</p>
              <p className="mt-1 text-2xl font-bold text-slate-900">{data.appointmentVolume}</p>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <p className="text-xs font-semibold uppercase text-slate-500">Completed visits</p>
              <p className="mt-1 text-2xl font-bold text-slate-900">{data.completedAppointmentCount}</p>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <p className="text-xs font-semibold uppercase text-slate-500">Line item revenue</p>
              <p className="mt-1 text-2xl font-bold text-slate-900">{money(Number(data.lineItemRevenue))}</p>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <p className="text-xs font-semibold uppercase text-slate-500">New memberships</p>
              <p className="mt-1 text-2xl font-bold text-slate-900">{data.newMemberships}</p>
            </div>
          </div>

          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">Top services</h2>
            <div className="mt-3 overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead className="border-b border-slate-200 text-xs text-slate-500">
                  <tr>
                    <th className="py-2 pr-3">Service</th>
                    <th className="py-2 pr-3">Revenue</th>
                    <th className="py-2 pr-3">Units</th>
                  </tr>
                </thead>
                <tbody>
                  {top.length ? (
                    top.map((row, i) => (
                      <tr key={String((row as UnknownRec).service_id ?? i)} className="border-b border-slate-100">
                        <td className="py-2 pr-3 font-medium">{String((row as UnknownRec).service_name ?? "")}</td>
                        <td className="py-2 pr-3">{money(Number((row as UnknownRec).revenue ?? 0))}</td>
                        <td className="py-2 pr-3">{String((row as UnknownRec).units ?? "")}</td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={3} className="py-6 text-slate-500">
                        No service lines in range.
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

export function SalesOverviewClient() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-slate-600">Loading sales…</div>}>
      <SalesOverviewInner />
    </Suspense>
  );
}
