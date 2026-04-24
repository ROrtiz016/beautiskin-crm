"use client";

import { SpaPageFrame } from "@/components/spa-page-frame";
import { useSpaGet } from "@/hooks/use-spa-get";
import Link from "next/link";

type HomePayload = {
  todaysAppointmentCount: number | null;
  clinicTodayLabel: string | null;
  appointmentsTodayUrl: string | null;
  topServices: Array<{ name: string; revenue: number; units: number }>;
  topProducts: Array<{ name: string; revenue: number; units: number }>;
  topMemberships: Array<{ name: string; sold_count: number; revenue: number }>;
  bestsellerDays: number;
  leadFunnelNewLeads?: number;
  leadFunnelContacted?: number;
  leadFunnelNewCustomers?: number;
  leadFunnelNewMemberships?: number;
};

export default function CrmHomePage() {
  const { data, error, loading } = useSpaGet<HomePayload>("/spa/home");

  return (
    <SpaPageFrame title="Dashboard" loading={loading} error={error}>
      {data ? (
        <div className="space-y-8">
          {data.todaysAppointmentCount !== null && data.clinicTodayLabel ? (
            <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <p className="text-sm text-slate-600">{data.clinicTodayLabel}</p>
              <p className="mt-2 text-2xl font-semibold text-slate-900">
                {data.todaysAppointmentCount}{" "}
                <span className="text-base font-normal text-slate-600">appointments today</span>
              </p>
              {data.appointmentsTodayUrl ? (
                <p className="mt-2 text-sm">
                  <Link
                    href="/appointments"
                    className="font-medium text-pink-700 hover:text-pink-800 hover:underline"
                  >
                    Open calendar
                  </Link>
                </p>
              ) : null}
            </section>
          ) : null}
          <div className="grid gap-6 lg:grid-cols-3">
            <ListCard title={`Top services (${data.bestsellerDays}d)`} rows={data.topServices} />
            <ListCard title={`Top retail (${data.bestsellerDays}d)`} rows={data.topProducts} />
            <ListCard title={`Memberships (${data.bestsellerDays}d)`} rows={data.topMemberships} />
          </div>
          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-sm font-semibold text-slate-900">Lead funnel (rolling)</h2>
            <dl className="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
              <Metric label="New leads" value={data.leadFunnelNewLeads ?? 0} />
              <Metric label="Contacted" value={data.leadFunnelContacted ?? 0} />
              <Metric label="New customers" value={data.leadFunnelNewCustomers ?? 0} />
              <Metric label="New memberships" value={data.leadFunnelNewMemberships ?? 0} />
            </dl>
          </section>
        </div>
      ) : null}
    </SpaPageFrame>
  );
}

function Metric({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-lg bg-slate-50 px-3 py-2">
      <dt className="text-xs text-slate-500">{label}</dt>
      <dd className="text-lg font-semibold text-slate-900">{value}</dd>
    </div>
  );
}

function ListCard({
  title,
  rows,
}: {
  title: string;
  rows: Array<{ name: string; revenue?: number; units?: number; sold_count?: number }>;
}) {
  return (
    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 className="text-sm font-semibold text-slate-900">{title}</h2>
      <ul className="mt-3 divide-y divide-slate-100 text-sm">
        {rows.length === 0 ? (
          <li className="py-2 text-slate-500">No data.</li>
        ) : (
          rows.map((r) => (
            <li key={r.name} className="flex justify-between gap-2 py-2">
              <span className="truncate text-slate-800">{r.name}</span>
              <span className="shrink-0 text-slate-600">
                {"sold_count" in r && r.sold_count !== undefined
                  ? `${r.sold_count} sold`
                  : `${Number(r.revenue ?? 0).toFixed(0)} rev`}
              </span>
            </li>
          ))
        )}
      </ul>
    </section>
  );
}
