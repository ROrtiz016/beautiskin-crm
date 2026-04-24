"use client";

import { SpaPageFrame } from "@/components/spa-page-frame";
import { useSpaGet } from "@/hooks/use-spa-get";
import { firstErrorMessage } from "@/lib/laravel-form-errors";
import { spaFetch } from "@/lib/spa-fetch";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useMemo, useState } from "react";

const CONTACT_METHODS = ["phone", "email", "whatsapp", "messenger", "social_chat"] as const;

function leadSourceLabel(key: string): string {
  const k = key || "unknown";
  const map: Record<string, string> = {
    social_instagram: "Instagram",
    social_facebook: "Facebook",
    social_tiktok: "TikTok",
    social_x: "X (Twitter)",
    social_youtube: "YouTube",
    social_linkedin: "LinkedIn",
    google_ads: "Google Ads",
    website: "Website / organic",
    referral: "Referral",
    walk_in: "Walk-in",
    email_campaign: "Email campaign",
    phone_inquiry: "Phone inquiry",
    event: "Event / pop-up",
    partner: "Partner / B2B",
    other: "Other",
    unknown: "Not specified",
  };
  return map[k] ?? k;
}

function contactMethodLabel(key: string): string {
  const map: Record<string, string> = {
    phone: "Phone",
    email: "Email",
    whatsapp: "WhatsApp",
    messenger: "Messenger (Meta)",
    social_chat: "Social media chat",
  };
  return map[key] ?? key;
}

type UnknownRec = Record<string, unknown>;

type PaginatedEntries = {
  data: UnknownRec[];
  current_page: number;
  last_page: number;
  total: number;
  per_page?: number;
};

type LeadSourceChart = {
  items: { key: string; label: string; count: number; percent: number; color: string }[];
  total: number;
  hasData: boolean;
};

type LeadsPayload = {
  entries: PaginatedEntries;
  leadSourceChart: LeadSourceChart;
  search: string;
  statusFilter: string;
  preferredFrom: string;
  preferredTo: string;
  serviceIdFilter: number;
  assignedToFilter: string;
  createdFrom: string;
  countsByStatus: Record<string, number>;
  statusLabels: string[];
  serviceOptions: { id: number; name: string }[];
  staffOptions: { id: number; name: string }[];
  hasActiveFilters: boolean;
  leadFunnelNewLeads: number;
  leadFunnelContacted: number;
  leadFunnelNewCustomers: number;
  leadFunnelNewMemberships: number;
  leadFunnelRollingDays: number;
};

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

function LeadsPageInner() {
  const sp = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();

  const path = useMemo(() => `/spa/leads${sp.toString() ? `?${sp}` : ""}`, [sp]);
  const { data, error, loading, reload } = useSpaGet<LeadsPayload>(path);

  const [draft, setDraft] = useState({
    q: "",
    status: "",
    preferred_from: "",
    preferred_to: "",
    service_id: "",
    assigned_to: "",
    created_from: "",
  });

  useEffect(() => {
    setDraft({
      q: sp.get("q") ?? "",
      status: sp.get("status") ?? "",
      preferred_from: sp.get("preferred_from") ?? "",
      preferred_to: sp.get("preferred_to") ?? "",
      service_id: sp.get("service_id") ?? "",
      assigned_to: sp.get("assigned_to") ?? "",
      created_from: sp.get("created_from") ?? "",
    });
  }, [sp]);

  const applyFilters = useCallback(() => {
    const n = new URLSearchParams();
    if (draft.q.trim()) {
      n.set("q", draft.q.trim());
    }
    if (draft.status) {
      n.set("status", draft.status);
    }
    if (draft.preferred_from) {
      n.set("preferred_from", draft.preferred_from);
    }
    if (draft.preferred_to) {
      n.set("preferred_to", draft.preferred_to);
    }
    if (draft.service_id) {
      n.set("service_id", draft.service_id);
    }
    if (draft.assigned_to) {
      n.set("assigned_to", draft.assigned_to);
    }
    if (draft.created_from) {
      n.set("created_from", draft.created_from);
    }
    const s = n.toString();
    router.push(s ? `${pathname}?${s}` : pathname);
  }, [draft, pathname, router]);

  const clearFilters = useCallback(() => {
    setDraft({
      q: "",
      status: "",
      preferred_from: "",
      preferred_to: "",
      service_id: "",
      assigned_to: "",
      created_from: "",
    });
    router.push(pathname);
  }, [pathname, router]);

  const entries = data?.entries;
  const rows = entries?.data ?? [];
  const chart = data?.leadSourceChart;

  const [contactEntry, setContactEntry] = useState<UnknownRec | null>(null);
  const [contactMethod, setContactMethod] = useState<string>("phone");
  const [contactNotes, setContactNotes] = useState("");
  const [contactAt, setContactAt] = useState("");
  const [contactErr, setContactErr] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const openContact = useCallback((entry: UnknownRec) => {
    setContactEntry(entry);
    setContactErr(null);
    setContactNotes("");
    setContactMethod("phone");
    const d = new Date();
    const pad = (n: number) => String(n).padStart(2, "0");
    setContactAt(`${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`);
  }, []);

  const submitContact = useCallback(async () => {
    if (!contactEntry) {
      return;
    }
    setContactErr(null);
    setSaving(true);
    try {
      const res = await spaFetch(`/waitlist-entries/${contactEntry.id}/contact`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          contact_method: contactMethod,
          contact_notes: contactNotes.trim(),
          contacted_at: new Date(contactAt).toISOString(),
        }),
      });
      if (!res.ok) {
        const b = await readApiError(res);
        setContactErr(firstErrorMessage(b, "Could not log contact."));
        return;
      }
      setContactEntry(null);
      await reload();
    } finally {
      setSaving(false);
    }
  }, [contactAt, contactEntry, contactMethod, contactNotes, reload]);

  const patchStatus = useCallback(
    async (entryId: number, status: string) => {
      const res = await spaFetch(`/waitlist-entries/${entryId}/status`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ status }),
      });
      if (!res.ok) {
        const b = await readApiError(res);
        window.alert(firstErrorMessage(b, "Update failed."));
        return;
      }
      await reload();
    },
    [reload],
  );

  const goPage = useCallback(
    (page: number) => {
      const n = new URLSearchParams(sp.toString());
      if (page <= 1) {
        n.delete("page");
      } else {
        n.set("page", String(page));
      }
      const s = n.toString();
      router.push(s ? `${pathname}?${s}` : pathname);
    },
    [pathname, router, sp],
  );

  return (
    <SpaPageFrame
      title="Leads"
      subtitle="Waitlist and standby requests tied to a customer profile."
      loading={loading}
      error={error}
    >
      <p className="mb-4 text-sm text-slate-600">
        Add new entries from the{" "}
        <Link href="/appointments" className="font-semibold text-pink-700 hover:underline">
          Appointments
        </Link>{" "}
        calendar (waitlist on a day).
      </p>

      {data ? (
        <section className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p className="text-xs font-semibold uppercase text-slate-500">New leads ({data.leadFunnelRollingDays}d)</p>
            <p className="mt-1 text-2xl font-bold text-slate-900">{data.leadFunnelNewLeads}</p>
          </div>
          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p className="text-xs font-semibold uppercase text-slate-500">Contacted</p>
            <p className="mt-1 text-2xl font-bold text-slate-900">{data.leadFunnelContacted}</p>
          </div>
          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p className="text-xs font-semibold uppercase text-slate-500">New customers</p>
            <p className="mt-1 text-2xl font-bold text-slate-900">{data.leadFunnelNewCustomers}</p>
          </div>
          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p className="text-xs font-semibold uppercase text-slate-500">New memberships</p>
            <p className="mt-1 text-2xl font-bold text-slate-900">{data.leadFunnelNewMemberships}</p>
          </div>
        </section>
      ) : null}

      {chart?.hasData ? (
        <section className="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 className="text-lg font-semibold text-slate-900">Lead sources</h2>
          <p className="mt-1 text-xs text-slate-500">Share of waitlist leads by channel (filtered).</p>
          <ul className="mt-4 max-w-lg space-y-2 text-sm">
            {chart.items.map((row) => (
              <li key={row.key} className="flex items-center justify-between gap-3 border-b border-slate-100 py-1.5">
                <span className="flex items-center gap-2 text-slate-700">
                  <span className="inline-block size-2.5 shrink-0 rounded-sm" style={{ backgroundColor: row.color }} />
                  {row.label}
                </span>
                <span className="tabular-nums text-slate-900">
                  <span className="font-semibold">{row.percent}%</span>
                  <span className="text-slate-500"> ({row.count})</span>
                </span>
              </li>
            ))}
          </ul>
          <p className="mt-3 text-xs text-slate-500">
            Total in view: <span className="font-semibold text-slate-800">{chart.total}</span>
          </p>
        </section>
      ) : data ? (
        <p className="mb-6 text-sm text-slate-600">No leads match filters for the chart.</p>
      ) : null}

      <section className="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-lg font-semibold text-slate-900">Filters</h2>
        <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <label className="block text-sm sm:col-span-2">
            <span className="text-xs font-semibold uppercase text-slate-500">Search</span>
            <input
              className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              value={draft.q}
              onChange={(e) => setDraft((d) => ({ ...d, q: e.target.value }))}
              placeholder="Name, email, phone…"
            />
          </label>
          <label className="block text-sm">
            <span className="text-xs font-semibold uppercase text-slate-500">Status</span>
            <select
              className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              value={draft.status}
              onChange={(e) => setDraft((d) => ({ ...d, status: e.target.value }))}
            >
              <option value="">All statuses</option>
              {(data?.statusLabels ?? ["waiting", "contacted", "booked", "cancelled"]).map((st) => (
                <option key={st} value={st}>
                  {st.charAt(0).toUpperCase() + st.slice(1)}
                  {data?.countsByStatus?.[st] != null && Number(data.countsByStatus[st]) > 0
                    ? ` (${data.countsByStatus[st]})`
                    : ""}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            <span className="text-xs font-semibold uppercase text-slate-500">Preferred from</span>
            <input
              type="date"
              className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              value={draft.preferred_from}
              onChange={(e) => setDraft((d) => ({ ...d, preferred_from: e.target.value }))}
            />
          </label>
          <label className="block text-sm">
            <span className="text-xs font-semibold uppercase text-slate-500">Preferred to</span>
            <input
              type="date"
              className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              value={draft.preferred_to}
              onChange={(e) => setDraft((d) => ({ ...d, preferred_to: e.target.value }))}
            />
          </label>
          <label className="block text-sm">
            <span className="text-xs font-semibold uppercase text-slate-500">Service</span>
            <select
              className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              value={draft.service_id}
              onChange={(e) => setDraft((d) => ({ ...d, service_id: e.target.value }))}
            >
              <option value="">Any</option>
              {(data?.serviceOptions ?? []).map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            <span className="text-xs font-semibold uppercase text-slate-500">Assigned staff</span>
            <select
              className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              value={draft.assigned_to}
              onChange={(e) => setDraft((d) => ({ ...d, assigned_to: e.target.value }))}
            >
              <option value="">Anyone</option>
              <option value="none">Unassigned</option>
              {(data?.staffOptions ?? []).map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            <span className="text-xs font-semibold uppercase text-slate-500">Added on or after</span>
            <input
              type="date"
              className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              value={draft.created_from}
              onChange={(e) => setDraft((d) => ({ ...d, created_from: e.target.value }))}
            />
          </label>
        </div>
        <div className="mt-4 flex flex-wrap gap-2">
          <button type="button" onClick={applyFilters} className="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
            Apply filters
          </button>
          {data?.hasActiveFilters ? (
            <button type="button" onClick={clearFilters} className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
              Clear all
            </button>
          ) : null}
        </div>
      </section>

      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="min-w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50/90 text-xs font-semibold uppercase tracking-wide text-slate-600">
              <tr>
                <th className="px-4 py-3">Customer</th>
                <th className="px-4 py-3">Preferred</th>
                <th className="px-4 py-3">Service / staff</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3">Source</th>
                <th className="px-4 py-3">Contact</th>
                <th className="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {rows.length ? (
                rows.map((entry) => {
                  const cust = entry.customer as UnknownRec | null | undefined;
                  const svc = entry.service as { name?: string } | null | undefined;
                  const staff = entry.staff_user ?? entry.staffUser;
                  const staffName = (staff as { name?: string } | null)?.name;
                  const pref = entry.preferred_date as string | undefined;
                  const startT = entry.preferred_start_time ? String(entry.preferred_start_time).slice(0, 5) : "";
                  const endT = entry.preferred_end_time ? String(entry.preferred_end_time).slice(0, 5) : "";
                  const status = String(entry.status ?? "");
                  const src = String(entry.lead_source ?? "unknown");
                  return (
                    <tr key={String(entry.id)} className="align-top">
                      <td className="px-4 py-3">
                        {cust && !cust.deleted_at ? (
                          <>
                            <Link href={`/customers/${cust.id}`} className="font-medium text-pink-800 hover:underline">
                              {String(cust.first_name)} {String(cust.last_name)}
                            </Link>
                            <p className="mt-0.5 text-xs text-slate-500">{String(cust.email ?? "—")}</p>
                          </>
                        ) : (
                          <span className="font-medium text-slate-700">Customer unavailable</span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-slate-700">
                        {pref ? new Date(pref).toLocaleDateString(undefined, { month: "short", day: "numeric", year: "numeric" }) : "—"}
                        <p className="mt-0.5 text-xs text-slate-500">
                          {startT || "Any"}
                          {endT ? ` – ${endT}` : ""}
                        </p>
                      </td>
                      <td className="px-4 py-3 text-slate-700">
                        <p>{svc?.name ?? "Any service"}</p>
                        {staffName ? <p className="mt-0.5 text-xs text-slate-500">Staff: {staffName}</p> : null}
                      </td>
                      <td className="px-4 py-3">
                        <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold capitalize text-slate-800">
                          {status}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-slate-700">{leadSourceLabel(src)}</td>
                      <td className="px-4 py-3 text-xs text-slate-600">
                        {status === "contacted" && entry.contacted_at ? (
                          <>
                            <p className="font-medium">{new Date(String(entry.contacted_at)).toLocaleString()}</p>
                            <p>{contactMethodLabel(String(entry.contact_method ?? ""))}</p>
                          </>
                        ) : status === "contacted" ? (
                          <span className="text-slate-500">No log</span>
                        ) : (
                          "—"
                        )}
                      </td>
                      <td className="px-4 py-3 text-right">
                        <div className="flex flex-wrap justify-end gap-1">
                          {status !== "contacted" ? (
                            <button
                              type="button"
                              className="rounded-md bg-slate-700 px-2 py-1 text-xs font-semibold text-white hover:bg-slate-800"
                              onClick={() => openContact(entry)}
                            >
                              Log contact
                            </button>
                          ) : null}
                          {status !== "booked" ? (
                            <button
                              type="button"
                              className="rounded-md bg-emerald-600 px-2 py-1 text-xs font-semibold text-white hover:bg-emerald-700"
                              onClick={() => void patchStatus(Number(entry.id), "booked")}
                            >
                              Booked
                            </button>
                          ) : null}
                          {status !== "cancelled" ? (
                            <button
                              type="button"
                              className="rounded-md border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                              onClick={() => {
                                if (window.confirm("Remove this waitlist entry?")) {
                                  void patchStatus(Number(entry.id), "cancelled");
                                }
                              }}
                            >
                              Remove
                            </button>
                          ) : null}
                          {cust && pref ? (
                            <Link
                              href={`/appointments?date=${String(pref).slice(0, 10)}`}
                              className="inline-flex rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-800 hover:bg-slate-50"
                            >
                              Calendar
                            </Link>
                          ) : null}
                        </div>
                      </td>
                    </tr>
                  );
                })
              ) : (
                <tr>
                  <td colSpan={7} className="px-4 py-8 text-center text-slate-500">
                    No leads match these filters.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        {entries && entries.last_page > 1 ? (
          <div className="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm">
            <span className="text-slate-600">
              Page {entries.current_page} of {entries.last_page} ({entries.total} total)
            </span>
            <span className="flex gap-2">
              <button
                type="button"
                disabled={entries.current_page <= 1}
                className="rounded border border-slate-300 px-2 py-1 disabled:opacity-40"
                onClick={() => goPage(entries.current_page - 1)}
              >
                Previous
              </button>
              <button
                type="button"
                disabled={entries.current_page >= entries.last_page}
                className="rounded border border-slate-300 px-2 py-1 disabled:opacity-40"
                onClick={() => goPage(entries.current_page + 1)}
              >
                Next
              </button>
            </span>
          </div>
        ) : null}
      </section>

      {contactEntry ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog">
          <div className="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
            <h2 className="text-lg font-semibold text-slate-900">Log contact</h2>
            {contactErr ? <p className="mt-2 text-sm text-red-600">{contactErr}</p> : null}
            <div className="mt-3 space-y-3">
              <label className="block text-sm">
                Method
                <select
                  className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={contactMethod}
                  onChange={(e) => setContactMethod(e.target.value)}
                >
                  {CONTACT_METHODS.map((m) => (
                    <option key={m} value={m}>
                      {contactMethodLabel(m)}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-sm">
                When
                <input
                  type="datetime-local"
                  className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={contactAt}
                  onChange={(e) => setContactAt(e.target.value)}
                />
              </label>
              <label className="block text-sm">
                Notes
                <textarea rows={4} className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" value={contactNotes} onChange={(e) => setContactNotes(e.target.value)} />
              </label>
            </div>
            <div className="mt-4 flex justify-end gap-2">
              <button type="button" className="rounded border border-slate-300 px-3 py-1.5 text-sm" onClick={() => setContactEntry(null)}>
                Cancel
              </button>
              <button
                type="button"
                disabled={saving}
                className="rounded bg-pink-600 px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-50"
                onClick={() => void submitContact()}
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

export function LeadsPageClient() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-slate-600">Loading leads…</div>}>
      <LeadsPageInner />
    </Suspense>
  );
}
