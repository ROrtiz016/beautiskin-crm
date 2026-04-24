"use client";

import { SpaPageFrame } from "@/components/spa-page-frame";
import { useSpaGet } from "@/hooks/use-spa-get";
import { firstErrorMessage } from "@/lib/laravel-form-errors";
import { spaFetch } from "@/lib/spa-fetch";
import Link from "next/link";
import { useParams, usePathname, useRouter, useSearchParams } from "next/navigation";
import { useCallback, useEffect, useMemo, useState } from "react";

type ActivityRow = {
  id: number;
  category: string;
  event_type: string;
  summary: string;
  created_at: string;
  user?: { id: number; name: string } | null;
  related_task?: { id: number; title: string } | null;
};

type PaginatedActivities = {
  data: ActivityRow[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  prev_page_url: string | null;
  next_page_url: string | null;
};

type TimelinePayload = {
  title?: string;
  customer: { id: number; first_name: string; last_name: string };
  activities: PaginatedActivities;
  categoryLabels: Record<string, string>;
  recentAppointments: { id: number; scheduled_at: string; status: string }[];
};

function formatWhen(iso: string): string {
  try {
    return new Date(iso).toLocaleString(undefined, {
      dateStyle: "medium",
      timeStyle: "short",
    });
  } catch {
    return iso;
  }
}

export function CustomerTimelineClient() {
  const { id } = useParams<{ id: string }>();
  const pathname = usePathname();
  const router = useRouter();
  const sp = useSearchParams();

  const listPath = useMemo(() => {
    const q = sp.toString();
    return `/spa/customers/${id}/timeline${q ? `?${q}` : ""}`;
  }, [id, sp]);

  const { data, error, loading, reload } = useSpaGet<TimelinePayload>(listPath);

  const [noteText, setNoteText] = useState("");
  const [notePending, setNotePending] = useState(false);
  const [noteError, setNoteError] = useState<string | null>(null);
  const [noteOk, setNoteOk] = useState<string | null>(null);

  const [commChannel, setCommChannel] = useState<"call" | "email" | "sms">("call");
  const [commSummary, setCommSummary] = useState("");
  const [commPending, setCommPending] = useState(false);
  const [commError, setCommError] = useState<string | null>(null);
  const [commOk, setCommOk] = useState<string | null>(null);

  const [tmplTemplate, setTmplTemplate] = useState<"follow_up" | "no_show" | "reminder">("follow_up");
  const [tmplChannel, setTmplChannel] = useState<"email" | "sms">("email");
  const [tmplAppointmentId, setTmplAppointmentId] = useState("");
  const [tmplPending, setTmplPending] = useState(false);
  const [tmplError, setTmplError] = useState<string | null>(null);
  const [tmplOk, setTmplOk] = useState<string | null>(null);

  const [draftQ, setDraftQ] = useState(sp.get("q") ?? "");
  const [draftCategory, setDraftCategory] = useState(sp.get("category") ?? "");
  const [draftFrom, setDraftFrom] = useState(sp.get("from") ?? "");
  const [draftTo, setDraftTo] = useState(sp.get("to") ?? "");

  useEffect(() => {
    setDraftQ(sp.get("q") ?? "");
    setDraftCategory(sp.get("category") ?? "");
    setDraftFrom(sp.get("from") ?? "");
    setDraftTo(sp.get("to") ?? "");
  }, [sp]);

  const applyFilters = useCallback(() => {
    const qs = new URLSearchParams();
    if (draftQ.trim()) qs.set("q", draftQ.trim());
    if (draftCategory) qs.set("category", draftCategory);
    if (draftFrom) qs.set("from", draftFrom);
    if (draftTo) qs.set("to", draftTo);
    const s = qs.toString();
    router.push(s ? `${pathname}?${s}` : pathname);
  }, [draftCategory, draftFrom, draftQ, draftTo, pathname, router]);

  const clearFilters = useCallback(() => {
    setDraftQ("");
    setDraftCategory("");
    setDraftFrom("");
    setDraftTo("");
    router.push(pathname);
  }, [pathname, router]);

  async function submitNote(e: React.FormEvent) {
    e.preventDefault();
    setNoteError(null);
    setNoteOk(null);
    const summary = noteText.trim();
    if (!summary) {
      setNoteError("Enter a note.");
      return;
    }
    setNotePending(true);
    try {
      const res = await spaFetch(`/customers/${id}/timeline-notes`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ summary }),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) {
        setNoteError(firstErrorMessage(body, "Could not save note."));
        return;
      }
      setNoteText("");
      setNoteOk((body as { message?: string }).message ?? "Note added.");
      await reload();
    } catch {
      setNoteError("Could not reach the server.");
    } finally {
      setNotePending(false);
    }
  }

  async function submitCommunication(e: React.FormEvent) {
    e.preventDefault();
    setCommError(null);
    setCommOk(null);
    const summary = commSummary.trim();
    if (!summary) {
      setCommError("Enter what was discussed or sent.");
      return;
    }
    setCommPending(true);
    try {
      const res = await spaFetch(`/customers/${id}/communications`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ channel: commChannel, summary }),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) {
        setCommError(firstErrorMessage(body, "Could not log communication."));
        return;
      }
      setCommSummary("");
      setCommOk((body as { message?: string }).message ?? "Communication logged.");
      await reload();
    } catch {
      setCommError("Could not reach the server.");
    } finally {
      setCommPending(false);
    }
  }

  async function submitTemplated(e: React.FormEvent) {
    e.preventDefault();
    setTmplError(null);
    setTmplOk(null);
    if (tmplTemplate === "reminder" && !tmplAppointmentId) {
      setTmplError("Reminder template requires an appointment.");
      return;
    }
    setTmplPending(true);
    try {
      const payload: Record<string, unknown> = {
        template: tmplTemplate,
        channel: tmplChannel,
      };
      if (tmplAppointmentId) {
        payload.appointment_id = Number(tmplAppointmentId);
      }
      const res = await spaFetch(`/customers/${id}/communications/templated`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) {
        setTmplError(firstErrorMessage(body, "Could not send message."));
        return;
      }
      setTmplOk((body as { message?: string }).message ?? "Message sent.");
      await reload();
    } catch {
      setTmplError("Could not reach the server.");
    } finally {
      setTmplPending(false);
    }
  }

  const customerTitle = data
    ? `${data.customer.first_name} ${data.customer.last_name}`.trim() || "Customer"
    : "Customer timeline";

  const labels = data?.categoryLabels ?? {};
  const activities = data?.activities;
  const rows = activities?.data ?? [];

  return (
    <SpaPageFrame title={customerTitle} subtitle="Timeline" loading={loading} error={error}>
      <p className="text-sm">
        <Link href={`/customers/${id}`} className="text-pink-700 hover:underline">
          ← Profile
        </Link>
        {" · "}
        <Link href={`/customers/${id}/edit`} className="text-pink-700 hover:underline">
          Edit
        </Link>
      </p>

      {!loading && data ? (
        <div className="mt-6 space-y-6">
          <div className="grid gap-6 lg:grid-cols-2">
            <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <h2 className="text-sm font-semibold text-slate-900">Add timeline note</h2>
              <p className="mt-1 text-xs text-slate-500">Internal note on this customer’s timeline.</p>
              <form className="mt-3 space-y-2" onSubmit={submitNote}>
                <textarea
                  rows={3}
                  value={noteText}
                  onChange={(ev) => setNoteText(ev.target.value)}
                  placeholder="Outcome, internal note, or next step…"
                  className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                />
                {noteError ? <p className="text-xs text-rose-600">{noteError}</p> : null}
                {noteOk ? <p className="text-xs text-emerald-700">{noteOk}</p> : null}
                <button
                  type="submit"
                  disabled={notePending}
                  className="rounded-md bg-pink-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-60"
                >
                  {notePending ? "Saving…" : "Save note"}
                </button>
              </form>
            </section>

            <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <h2 className="text-sm font-semibold text-slate-900">Log call / email / SMS</h2>
              <p className="mt-1 text-xs text-slate-500">Manual log for outreach not synced from other systems.</p>
              <form className="mt-3 space-y-2" onSubmit={submitCommunication}>
                <label className="block text-xs font-medium text-slate-600">
                  Channel
                  <select
                    className="mt-1 block w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                    value={commChannel}
                    onChange={(ev) => setCommChannel(ev.target.value as typeof commChannel)}
                  >
                    <option value="call">Phone call</option>
                    <option value="email">Email</option>
                    <option value="sms">SMS</option>
                  </select>
                </label>
                <textarea
                  rows={3}
                  value={commSummary}
                  onChange={(ev) => setCommSummary(ev.target.value)}
                  placeholder="What was discussed or sent…"
                  className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                />
                {commError ? <p className="text-xs text-rose-600">{commError}</p> : null}
                {commOk ? <p className="text-xs text-emerald-700">{commOk}</p> : null}
                <button
                  type="submit"
                  disabled={commPending}
                  className="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-900 disabled:opacity-60"
                >
                  {commPending ? "Saving…" : "Log communication"}
                </button>
              </form>
            </section>
          </div>

          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 className="text-sm font-semibold text-slate-900">Send using template</h2>
            <p className="mt-1 text-xs text-slate-500">
              Uses clinic messaging templates (same as the control board). Email uses your mailer; SMS uses Twilio when configured.
            </p>
            <form className="mt-4 flex flex-wrap items-end gap-4" onSubmit={submitTemplated}>
              <label className="text-xs font-medium text-slate-600">
                Template
                <select
                  className="mt-1 block min-w-[11rem] rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                  value={tmplTemplate}
                  onChange={(ev) => setTmplTemplate(ev.target.value as typeof tmplTemplate)}
                >
                  <option value="follow_up">Follow-up</option>
                  <option value="no_show">We missed you (no-show)</option>
                  <option value="reminder">Appointment reminder</option>
                </select>
              </label>
              <label className="text-xs font-medium text-slate-600">
                Channel
                <select
                  className="mt-1 block min-w-[8rem] rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                  value={tmplChannel}
                  onChange={(ev) => setTmplChannel(ev.target.value as typeof tmplChannel)}
                >
                  <option value="email">Email</option>
                  <option value="sms">SMS</option>
                </select>
              </label>
              <label className="min-w-[12rem] flex-1 text-xs font-medium text-slate-600 lg:min-w-[16rem]">
                Appointment (optional — required for reminder)
                <select
                  className="mt-1 block w-full max-w-md rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                  value={tmplAppointmentId}
                  onChange={(ev) => setTmplAppointmentId(ev.target.value)}
                >
                  <option value="">— None —</option>
                  {(data.recentAppointments ?? []).map((a) => (
                    <option key={a.id} value={String(a.id)}>
                      #{a.id} {formatWhen(a.scheduled_at)} ({a.status})
                    </option>
                  ))}
                </select>
              </label>
              <button
                type="submit"
                disabled={tmplPending}
                className="rounded-md bg-pink-600 px-3 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-60"
              >
                {tmplPending ? "Sending…" : "Send message"}
              </button>
            </form>
            {tmplError ? <p className="mt-2 text-xs text-rose-600">{tmplError}</p> : null}
            {tmplOk ? <p className="mt-2 text-xs text-emerald-700">{tmplOk}</p> : null}
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 className="text-sm font-semibold text-slate-900">Filters</h2>
            <div className="mt-3 flex flex-wrap items-end gap-3">
              <div>
                <label className="block text-xs font-medium text-slate-600">Search summary</label>
                <input
                  value={draftQ}
                  onChange={(ev) => setDraftQ(ev.target.value)}
                  className="mt-1 w-48 rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-600">Category</label>
                <select
                  value={draftCategory}
                  onChange={(ev) => setDraftCategory(ev.target.value)}
                  className="mt-1 w-44 rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                >
                  <option value="">All</option>
                  {Object.entries(labels).map(([key, label]) => (
                    <option key={key} value={key}>
                      {label}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-600">From</label>
                <input
                  type="date"
                  value={draftFrom}
                  onChange={(ev) => setDraftFrom(ev.target.value)}
                  className="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-600">To</label>
                <input
                  type="date"
                  value={draftTo}
                  onChange={(ev) => setDraftTo(ev.target.value)}
                  className="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                />
              </div>
              <button
                type="button"
                onClick={applyFilters}
                className="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-900"
              >
                Apply
              </button>
              <button
                type="button"
                onClick={clearFilters}
                className="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
              >
                Clear
              </button>
            </div>
          </section>

          {data.recentAppointments?.length ? (
            <section className="rounded-xl border border-slate-200 bg-slate-50/80 p-4 text-sm text-slate-700">
              <span className="font-semibold text-slate-900">Upcoming / recent booked: </span>
              {data.recentAppointments.map((a) => (
                <span key={a.id} className="ml-2 inline-block">
                  #{a.id} {formatWhen(a.scheduled_at)} ({a.status})
                </span>
              ))}
            </section>
          ) : null}

          <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <ul className="divide-y divide-slate-100">
              {rows.length === 0 ? (
                <li className="px-4 py-8 text-center text-sm text-slate-500">No activities match these filters.</li>
              ) : (
                rows.map((row) => (
                  <li key={row.id} className="px-4 py-3">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                      <span className="text-xs font-medium uppercase tracking-wide text-slate-500">
                        {labels[row.category] ?? row.category}
                      </span>
                      <time className="text-xs text-slate-500">{formatWhen(row.created_at)}</time>
                    </div>
                    <p className="mt-1 whitespace-pre-wrap text-sm text-slate-800">{row.summary}</p>
                    <p className="mt-1 text-xs text-slate-500">
                      {row.user?.name ? <>By {row.user.name}</> : null}
                      {row.related_task?.title ? (
                        <>
                          {row.user?.name ? " · " : null}
                          Task: {row.related_task.title}
                        </>
                      ) : null}
                    </p>
                  </li>
                ))
              )}
            </ul>
            {activities && activities.last_page > 1 ? (
              <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm">
                <span className="text-slate-600">
                  Page {activities.current_page} of {activities.last_page} ({activities.total} total)
                </span>
                <span className="flex gap-2">
                  {activities.current_page > 1 ? (
                    <Link
                      href={`${pathname}?${(() => {
                        const n = new URLSearchParams(sp.toString());
                        n.set("page", String(activities.current_page - 1));
                        return n.toString();
                      })()}`}
                      className="text-pink-700 hover:underline"
                    >
                      Previous
                    </Link>
                  ) : (
                    <span className="text-slate-400">Previous</span>
                  )}
                  {activities.current_page < activities.last_page ? (
                    <Link
                      href={`${pathname}?${(() => {
                        const n = new URLSearchParams(sp.toString());
                        n.set("page", String(activities.current_page + 1));
                        return n.toString();
                      })()}`}
                      className="text-pink-700 hover:underline"
                    >
                      Next
                    </Link>
                  ) : (
                    <span className="text-slate-400">Next</span>
                  )}
                </span>
              </div>
            ) : null}
          </section>
        </div>
      ) : null}
    </SpaPageFrame>
  );
}
