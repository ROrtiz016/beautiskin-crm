"use client";

import { SpaPageFrame } from "@/components/spa-page-frame";
import { useSpaGet } from "@/hooks/use-spa-get";
import { firstErrorMessage } from "@/lib/laravel-form-errors";
import { spaFetch } from "@/lib/spa-fetch";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useMemo, useState } from "react";

type UnknownRec = Record<string, unknown>;

type ReportsPayload = {
  clinicTimezone?: string;
  fromDate: string;
  toDate: string;
  rangeLabel: string;
  completedRevenue: number;
  appointmentVolume: number;
  statusCounts: Record<string, number>;
  noShowCount: number;
  cancelledCount: number;
  newCustomers: number;
  waitlistOpened: number;
  topServices: UnknownRec[];
  dailyRows: { date: string; scheduled_count: number; completed_revenue: number }[];
};

function money(n: number): string {
  return n.toLocaleString(undefined, { style: "currency", currency: "USD" });
}

function ReportsInner() {
  const sp = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const path = useMemo(() => `/spa/admin/reports${sp.toString() ? `?${sp}` : ""}`, [sp]);
  const { data, error, loading } = useSpaGet<ReportsPayload>(path);

  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [exportBusy, setExportBusy] = useState(false);
  const [exportErr, setExportErr] = useState<string | null>(null);

  useEffect(() => {
    setFrom(sp.get("from") ?? data?.fromDate ?? "");
    setTo(sp.get("to") ?? data?.toDate ?? "");
  }, [sp, data?.fromDate, data?.toDate]);

  const apply = useCallback(() => {
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

  const exportQuery = useMemo(() => {
    const n = new URLSearchParams();
    if (from) {
      n.set("from", from);
    }
    if (to) {
      n.set("to", to);
    }
    const s = n.toString();
    return s ? `?${s}` : "";
  }, [from, to]);

  const downloadCsv = useCallback(async () => {
    setExportErr(null);
    setExportBusy(true);
    try {
      const res = await spaFetch(`/admin/reports/export${exportQuery}`);
      if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        setExportErr(firstErrorMessage(body, "Export failed."));
        return;
      }
      const blob = await res.blob();
      const cd = res.headers.get("Content-Disposition");
      let filename = "beautiskin-report.csv";
      const m = cd?.match(/filename="?([^";]+)"?/i);
      if (m?.[1]) {
        filename = m[1];
      }
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = filename;
      a.rel = "noopener";
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    } catch {
      setExportErr("Could not download export.");
    } finally {
      setExportBusy(false);
    }
  }, [exportQuery]);

  const daily = data?.dailyRows ?? [];
  const top = data?.topServices ?? [];
  const sc = data?.statusCounts ?? {};

  return (
    <SpaPageFrame title="Reports" subtitle={data?.rangeLabel} loading={loading} error={error}>
      <p className="mb-6 text-sm text-slate-600">Daily summary and top services below. Export matches the selected date range.</p>

      <section className="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <p className="text-xs text-slate-500">Timezone: {data?.clinicTimezone ?? "—"}</p>
        <div className="mt-3 flex flex-wrap items-end gap-3">
          <label className="text-sm">
            From
            <input type="date" className="mt-1 block rounded border border-slate-300 px-2 py-1.5 text-sm" value={from} onChange={(e) => setFrom(e.target.value)} />
          </label>
          <label className="text-sm">
            To
            <input type="date" className="mt-1 block rounded border border-slate-300 px-2 py-1.5 text-sm" value={to} onChange={(e) => setTo(e.target.value)} />
          </label>
          <button type="button" onClick={apply} className="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">
            Apply
          </button>
          <button
            type="button"
            onClick={() => void downloadCsv()}
            disabled={exportBusy}
            className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 disabled:opacity-50"
          >
            {exportBusy ? "Downloading…" : "Download CSV"}
          </button>
        </div>
        {exportErr ? <p className="mt-2 text-xs text-rose-600">{exportErr}</p> : null}
      </section>

      {data ? (
        <>
          <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <p className="text-xs font-semibold uppercase text-slate-500">Completed revenue</p>
              <p className="mt-1 text-xl font-bold">{money(Number(data.completedRevenue))}</p>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <p className="text-xs font-semibold uppercase text-slate-500">Appointment volume</p>
              <p className="mt-1 text-xl font-bold">{data.appointmentVolume}</p>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <p className="text-xs font-semibold uppercase text-slate-500">No-shows</p>
              <p className="mt-1 text-xl font-bold">{data.noShowCount}</p>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <p className="text-xs font-semibold uppercase text-slate-500">Cancelled</p>
              <p className="mt-1 text-xl font-bold">{data.cancelledCount}</p>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <p className="text-xs font-semibold uppercase text-slate-500">New customers</p>
              <p className="mt-1 text-xl font-bold">{data.newCustomers}</p>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <p className="text-xs font-semibold uppercase text-slate-500">Waitlist opened</p>
              <p className="mt-1 text-xl font-bold">{data.waitlistOpened}</p>
            </div>
          </div>

          <section className="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">Status mix</h2>
            <ul className="mt-2 flex flex-wrap gap-2 text-sm">
              {Object.entries(sc).map(([k, v]) => (
                <li key={k} className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">
                  <span className="capitalize">{k}</span>: <span className="font-semibold">{v}</span>
                </li>
              ))}
            </ul>
          </section>

          <section className="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">Daily summary</h2>
            <div className="mt-3 max-h-80 overflow-auto">
              <table className="min-w-full text-left text-sm">
                <thead className="sticky top-0 border-b border-slate-200 bg-slate-50 text-xs text-slate-500">
                  <tr>
                    <th className="py-2 pr-3">Date</th>
                    <th className="py-2 pr-3">Scheduled</th>
                    <th className="py-2 pr-3">Completed $</th>
                  </tr>
                </thead>
                <tbody>
                  {daily.map((row) => (
                    <tr key={row.date} className="border-b border-slate-100">
                      <td className="py-1.5 pr-3">{row.date}</td>
                      <td className="py-1.5 pr-3">{row.scheduled_count}</td>
                      <td className="py-1.5 pr-3">{money(Number(row.completed_revenue))}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">Top services</h2>
            <div className="mt-3 overflow-x-auto">
              <table className="min-w-full text-sm">
                <thead className="border-b text-xs text-slate-500">
                  <tr>
                    <th className="py-2 pr-3">Service</th>
                    <th className="py-2 pr-3">Revenue</th>
                    <th className="py-2 pr-3">Units</th>
                  </tr>
                </thead>
                <tbody>
                  {top.map((row, i) => (
                    <tr key={String((row as UnknownRec).service_id ?? i)} className="border-b border-slate-100">
                      <td className="py-2 pr-3 font-medium">{String((row as UnknownRec).service_name)}</td>
                      <td className="py-2 pr-3">{money(Number((row as UnknownRec).revenue ?? 0))}</td>
                      <td className="py-2 pr-3">{String((row as UnknownRec).units ?? "")}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        </>
      ) : null}
    </SpaPageFrame>
  );
}

export function ReportsPageClient() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-slate-600">Loading reports…</div>}>
      <ReportsInner />
    </Suspense>
  );
}
