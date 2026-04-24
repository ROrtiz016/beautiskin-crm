"use client";

import { SpaPageFrame } from "@/components/spa-page-frame";
import { useSpaGet } from "@/hooks/use-spa-get";
import { firstErrorMessage } from "@/lib/laravel-form-errors";
import { spaFetch } from "@/lib/spa-fetch";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { CustomerFilterCombobox } from "./customer-filter-combobox";
import { useCallback, useEffect, useId, useMemo, useState, type ReactNode } from "react";
import {
  formatMonthYearLabel,
  formatTimeRange,
  shiftMonth,
  statusBadgeClass,
  toDatetimeLocalInput,
  ymFromMonthBase,
  ymd,
} from "./appointments-helpers";

type WeekCell = {
  date: string;
  in_month: boolean;
  is_selected: boolean;
  is_today: boolean;
  count: number;
};

type LeadOption = { value: string; label: string; group: string };

type SpaCustomer = { id: number; first_name: string; last_name: string; email?: string | null; phone?: string | null };
type SpaService = { id: number; name: string; price?: string };
type SpaStaff = { id: number; name: string };

type ClinicSettings = {
  deposit_required?: boolean;
};

type WaitlistEntry = {
  id: number;
  status: string;
  notes?: string | null;
  lead_source?: string | null;
  preferred_start_time?: string | null;
  preferred_end_time?: string | null;
  customer?: SpaCustomer | null;
  service?: { id: number; name: string } | null;
  staff_user?: SpaStaff | null;
};

type AppointmentsPayload = {
  weeks: WeekCell[][];
  selectedDate: string;
  monthBase: string;
  filters: Record<string, string | number>;
  customers?: SpaCustomer[];
  services?: SpaService[];
  staffUsers?: SpaStaff[];
  selectedAppointments?: Record<string, unknown>[];
  selectedWaitlistEntries?: WaitlistEntry[];
  staffAvailability?: { label: string; count: number; appointments: Record<string, unknown>[] }[];
  clinicSettings?: ClinicSettings;
  leadSourceOptions?: LeadOption[];
};

function mergeHref(sp: URLSearchParams, patch: Record<string, string | undefined>): string {
  const n = new URLSearchParams(sp.toString());
  Object.entries(patch).forEach(([k, v]) => {
    if (v === undefined || v === "") {
      n.delete(k);
    } else {
      n.set(k, v);
    }
  });
  const s = n.toString();
  return s ? `/appointments?${s}` : "/appointments";
}

function customerLabel(c: SpaCustomer): string {
  return `${c.first_name} ${c.last_name}`.trim();
}

function relStaff(a: Record<string, unknown>): { name?: string } | undefined {
  const u = (a.staff_user ?? a.staffUser) as { name?: string } | undefined;
  return u;
}

function relCustomer(a: Record<string, unknown>): SpaCustomer | undefined {
  return (a.customer ?? a.Customer) as SpaCustomer | undefined;
}

function relServices(a: Record<string, unknown>): { service_name?: string; quantity?: number }[] {
  const raw = (a.services ?? a.Services) as { service_name?: string; quantity?: number }[] | undefined;
  return Array.isArray(raw) ? raw : [];
}

function relPayments(a: Record<string, unknown>): { id: number; amount: string | number; entry_type: string; note?: string | null }[] {
  const raw = (a.payment_entries ?? a.paymentEntries) as
    | { id: number; amount: string | number; entry_type: string; note?: string | null }[]
    | undefined;
  return Array.isArray(raw) ? raw : [];
}

function relServiceLinesForEdit(appt: Record<string, unknown>): { service_id: number; quantity: number }[] {
  const raw = (appt.services ?? appt.Services) as Record<string, unknown>[] | undefined;
  if (!Array.isArray(raw)) {
    return [];
  }
  return raw
    .map((row) => ({
      service_id: Number(row.service_id ?? 0),
      quantity: Math.max(1, Math.floor(Number(row.quantity ?? 1))),
    }))
    .filter((l) => l.service_id > 0);
}

function AppointmentDetailBlock({
  title,
  subtitle,
  children,
}: {
  title: string;
  subtitle?: string;
  children: ReactNode;
}) {
  const baseId = useId();
  const triggerId = `${baseId}-trigger`;
  const panelId = `${baseId}-panel`;
  const [open, setOpen] = useState(false);

  return (
    <div className="mt-3 border-t border-slate-200 pt-3">
      {/* Controlled expand/collapse (not native <details>) so grid-rows can animate on close; browsers drop [open] before CSS can run. */}
      <button
        type="button"
        id={triggerId}
        aria-expanded={open}
        aria-controls={panelId}
        onClick={() => setOpen((v) => !v)}
        className="flex w-full cursor-pointer items-center justify-between gap-2 rounded px-0.5 py-1 -mx-0.5 text-left text-xs font-semibold text-slate-700 outline-none transition-colors duration-200 hover:bg-slate-100/80 focus-visible:ring-2 focus-visible:ring-pink-400"
      >
        <span className="min-w-0 flex-1">
          <span className="block">{title}</span>
          {subtitle ? (
            <span className="mt-0.5 block text-[11px] font-normal text-slate-500">{subtitle}</span>
          ) : null}
        </span>
        <span
          className={`shrink-0 text-[10px] text-slate-400 transition-transform duration-300 ease-out motion-reduce:transition-none ${open ? "rotate-180" : ""}`}
          aria-hidden
        >
          ▼
        </span>
      </button>
      <div
        id={panelId}
        role="region"
        aria-labelledby={triggerId}
        className={`grid overflow-hidden transition-[grid-template-rows] duration-300 ease-in-out motion-reduce:transition-none ${
          open ? "grid-rows-[1fr]" : "grid-rows-[0fr]"
        }`}
      >
        <div className="min-h-0 overflow-hidden" {...(!open ? { inert: true } : {})}>
          <div className="mt-2">{children}</div>
        </div>
      </div>
    </div>
  );
}

function DayAppointmentCard({
  appt,
  staffUsers,
  catalogServices,
  onReload,
}: {
  appt: Record<string, unknown>;
  staffUsers: SpaStaff[];
  catalogServices: SpaService[];
  onReload: () => void;
}) {
  const id = Number(appt.id);
  const status = String(appt.status ?? "");
  const cust = relCustomer(appt);
  const staff = relStaff(appt);
  const services = relServices(appt);
  const payments = relPayments(appt);
  const visitTotal = parseFloat(String(appt.total_amount ?? "0"));
  const paymentsApplied = parseFloat(String(appt.payment_entries_sum_amount ?? "0"));
  const visitSafe = Number.isFinite(visitTotal) ? visitTotal : 0;
  const paidSafe = Number.isFinite(paymentsApplied) ? paymentsApplied : 0;
  const balance = Math.max(0, visitSafe - paidSafe);

  const [busy, setBusy] = useState<string | null>(null);
  const [msg, setMsg] = useState<string | null>(null);
  const [err, setErr] = useState<string | null>(null);

  const [staffDraft, setStaffDraft] = useState<string>(String(appt.staff_user_id ?? ""));
  const [scheduleDraft, setScheduleDraft] = useState<string>(
    appt.scheduled_at ? ymd(String(appt.scheduled_at)) + "T" + new Date(String(appt.scheduled_at)).toTimeString().slice(0, 5) : "",
  );
  const [notesDraft, setNotesDraft] = useState<string>(String(appt.notes ?? ""));

  const [payAmount, setPayAmount] = useState("");
  const [payType, setPayType] = useState<"payment" | "deposit" | "refund" | "adjustment">("payment");
  const [payNote, setPayNote] = useState("");

  const [cancelOpen, setCancelOpen] = useState(false);
  const [cancelReason, setCancelReason] = useState("");
  const [salesFollowUp, setSalesFollowUp] = useState(false);

  const [svcLines, setSvcLines] = useState<{ service_id: string; quantity: string }[]>([]);

  useEffect(() => {
    setStaffDraft(String(appt.staff_user_id ?? ""));
    setScheduleDraft(
      appt.scheduled_at
        ? `${ymd(String(appt.scheduled_at))}T${new Date(String(appt.scheduled_at)).toTimeString().slice(0, 5)}`
        : "",
    );
    setNotesDraft(String(appt.notes ?? ""));
    const lines = relServiceLinesForEdit(appt);
    setSvcLines(
      lines.length
        ? lines.map((l) => ({ service_id: String(l.service_id), quantity: String(l.quantity) }))
        : [{ service_id: catalogServices[0] ? String(catalogServices[0].id) : "", quantity: "1" }],
    );
  }, [appt]);

  async function saveServices() {
    const lines = svcLines
      .map((row) => ({
        service_id: Number(row.service_id),
        quantity: Math.max(1, Math.floor(Number(row.quantity) || 1)),
      }))
      .filter((row) => Number.isFinite(row.service_id) && row.service_id > 0);
    if (lines.length < 1) {
      setErr("Add at least one service line.");
      return;
    }
    await patch({ services: lines }, "services");
  }

  async function patch(body: Record<string, unknown>, label: string) {
    setErr(null);
    setMsg(null);
    setBusy(label);
    try {
      const res = await spaFetch(`/appointments/${id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setErr(firstErrorMessage(b, "Update failed."));
        return;
      }
      setMsg("Saved.");
      await onReload();
    } catch {
      setErr("Could not reach the server.");
    } finally {
      setBusy(null);
    }
  }

  async function sendReminder() {
    setErr(null);
    setMsg(null);
    setBusy("reminder");
    try {
      const res = await spaFetch(`/appointments/${id}/reminder-email`, { method: "POST" });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setErr((b as { message?: string }).message ?? firstErrorMessage(b, "Reminder failed."));
        return;
      }
      setMsg((b as { message?: string }).message ?? "Reminder sent.");
      await onReload();
    } catch {
      setErr("Could not reach the server.");
    } finally {
      setBusy(null);
    }
  }

  async function submitPayment(e: React.FormEvent) {
    e.preventDefault();
    const amount = parseFloat(payAmount);
    if (!Number.isFinite(amount) || amount <= 0) {
      setErr("Enter a valid payment amount.");
      return;
    }
    setErr(null);
    setBusy("pay");
    try {
      const res = await spaFetch(`/appointments/${id}/payment-entries`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ amount, entry_type: payType, note: payNote || null }),
      });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setErr(firstErrorMessage(b, "Payment failed."));
        return;
      }
      setPayAmount("");
      setPayNote("");
      setMsg("Payment recorded.");
      await onReload();
    } catch {
      setErr("Could not reach the server.");
    } finally {
      setBusy(null);
    }
  }

  async function deletePaymentEntry(entryId: number) {
    if (!window.confirm("Remove this payment line?")) {
      return;
    }
    setBusy("delpay");
    try {
      const res = await spaFetch(`/appointment-payment-entries/${entryId}`, { method: "DELETE" });
      if (!res.ok) {
        const b = await res.json().catch(() => ({}));
        setErr(firstErrorMessage(b, "Could not remove."));
        return;
      }
      await onReload();
    } catch {
      setErr("Could not reach the server.");
    } finally {
      setBusy(null);
    }
  }

  async function submitCancel(e: React.FormEvent) {
    e.preventDefault();
    const reason = cancelReason.trim();
    if (!reason) {
      setErr("Cancellation reason is required.");
      return;
    }
    setBusy("cancel");
    try {
      const res = await spaFetch(`/appointments/${id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          status: "cancelled",
          cancellation_reason: reason,
          sales_follow_up_needed: salesFollowUp,
        }),
      });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setErr(firstErrorMessage(b, "Could not cancel."));
        return;
      }
      setCancelOpen(false);
      setCancelReason("");
      setSalesFollowUp(false);
      await onReload();
    } catch {
      setErr("Could not reach the server.");
    } finally {
      setBusy(null);
    }
  }

  const serviceLine = services.map((s) => s.service_name ?? "Service").filter(Boolean).join(", ") || "—";

  return (
    <div className="rounded-lg border border-slate-200 bg-slate-50/50 px-3 py-3 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <p className="font-medium text-slate-900">{formatTimeRange(String(appt.scheduled_at), String(appt.ends_at ?? ""))}</p>
            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusBadgeClass(status)}`}>
              {status.replaceAll("_", " ")}
            </span>
            {status === "booked" ? (
              <span
                draggable
                onDragStart={(e) => {
                  e.dataTransfer.setData("application/x-beautiskin-appointment-id", String(id));
                  e.dataTransfer.effectAllowed = "move";
                }}
                className="inline-flex cursor-grab select-none rounded border border-dashed border-slate-400 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600"
                title="Drag onto a day in the calendar below"
              >
                Drag to day
              </span>
            ) : null}
          </div>
          <p className="mt-1 text-sm text-slate-800">
            {cust ? customerLabel(cust) : "Customer"}
            {cust?.phone ? <span className="text-slate-500"> · {cust.phone}</span> : null}
          </p>
          <p className="text-xs text-slate-600">Staff: {staff?.name ?? "Unassigned"}</p>
          <p className="mt-1 text-xs text-slate-600">Services: {serviceLine}</p>
          <p className="mt-1 text-xs text-slate-600">
            Visit ${visitSafe.toFixed(2)} · Paid ${paidSafe.toFixed(2)} · Due ${balance.toFixed(2)}
          </p>
          {appt.email_reminder_sent_at ? (
            <p className="mt-1 text-xs text-emerald-700">Reminder email was sent.</p>
          ) : null}
        </div>
      </div>

      {err ? <p className="mt-2 text-xs text-rose-600">{err}</p> : null}
      {msg ? <p className="mt-2 text-xs text-emerald-700">{msg}</p> : null}

      <div className="mt-3 flex flex-wrap gap-2 border-t border-slate-200 pt-3">
        <button
          type="button"
          disabled={busy !== null}
          onClick={() => void sendReminder()}
          className="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
        >
          {busy === "reminder" ? "Sending…" : appt.email_reminder_sent_at ? "Re-send reminder" : "Email reminder"}
        </button>
        {status === "booked" ? (
          <>
            <button
              type="button"
              disabled={busy !== null}
              onClick={() => void patch({ status: "completed" }, "complete")}
              className="rounded-md bg-emerald-600 px-2 py-1 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
            >
              {busy === "complete" ? "…" : "Complete"}
            </button>
            <button
              type="button"
              disabled={busy !== null}
              onClick={() => void patch({ status: "no_show" }, "noshow")}
              className="rounded-md bg-amber-600 px-2 py-1 text-xs font-semibold text-white hover:bg-amber-700 disabled:opacity-50"
            >
              No-show
            </button>
            <button
              type="button"
              disabled={busy !== null}
              onClick={() => {
                setCancelOpen(true);
                setErr(null);
              }}
              className="rounded-md bg-rose-600 px-2 py-1 text-xs font-semibold text-white hover:bg-rose-700 disabled:opacity-50"
            >
              Cancel
            </button>
          </>
        ) : (
          <button
            type="button"
            disabled={busy !== null}
            onClick={() => void patch({ status: "booked" }, "rebook")}
            className="rounded-md bg-blue-600 px-2 py-1 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
          >
            Undo to booked
          </button>
        )}
      </div>

      <div className="mt-3 grid gap-3 border-t border-slate-200 pt-3 sm:grid-cols-2">
        <label className="block text-xs">
          <span className="font-medium text-slate-700">Arrived</span>
          <input
            type="checkbox"
            className="ml-2 align-middle"
            checked={Boolean(appt.arrived_confirmed)}
            disabled={busy !== null}
            onChange={(ev) => void patch({ arrived_confirmed: ev.target.checked }, "arrival")}
          />
        </label>
        <div className="flex flex-wrap items-end gap-2">
          <label className="text-xs font-medium text-slate-700">
            Staff
            <select
              className="ml-1 mt-0.5 block w-full rounded border border-slate-300 px-1 py-1 text-xs"
              value={staffDraft}
              onChange={(ev) => setStaffDraft(ev.target.value)}
            >
              <option value="">Unassigned</option>
              {staffUsers.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name}
                </option>
              ))}
            </select>
          </label>
          <button
            type="button"
            disabled={busy !== null}
            onClick={() =>
              void patch(
                { staff_user_id: staffDraft === "" ? null : Number(staffDraft) },
                "staff",
              )
            }
            className="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold hover:bg-slate-50 disabled:opacity-50"
          >
            Save staff
          </button>
        </div>
      </div>

      <div className="mt-3 flex flex-wrap items-end gap-2 border-t border-slate-200 pt-3">
        <label className="text-xs font-medium text-slate-700">
          Start
          <input
            type="datetime-local"
            className="mt-0.5 block rounded border border-slate-300 px-1 py-1 text-xs"
            value={scheduleDraft}
            onChange={(ev) => setScheduleDraft(ev.target.value)}
          />
        </label>
        <button
          type="button"
          disabled={busy !== null || !scheduleDraft}
          onClick={() =>
            void patch(
              {
                scheduled_at: new Date(scheduleDraft).toISOString(),
              },
              "sched",
            )
          }
          className="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold hover:bg-slate-50 disabled:opacity-50"
        >
          Save time
        </button>
      </div>

      {status !== "completed" ? (
        <AppointmentDetailBlock
          title="Service lines"
          subtitle="Edit booked services and totals; payments are unchanged until you save."
        >
          <div className="space-y-2">
            {svcLines.map((row, idx) => (
              <div key={idx} className="flex flex-wrap gap-2">
                <select
                  className="min-w-[120px] flex-1 rounded border border-slate-300 px-1 py-1 text-xs"
                  value={row.service_id}
                  onChange={(ev) => {
                    const next = [...svcLines];
                    next[idx] = { ...next[idx], service_id: ev.target.value };
                    setSvcLines(next);
                  }}
                >
                  <option value="">Service…</option>
                  {catalogServices.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name}
                    </option>
                  ))}
                </select>
                <input
                  type="number"
                  min={1}
                  className="w-16 rounded border border-slate-300 px-1 py-1 text-xs"
                  value={row.quantity}
                  onChange={(ev) => {
                    const next = [...svcLines];
                    next[idx] = { ...next[idx], quantity: ev.target.value };
                    setSvcLines(next);
                  }}
                />
                {svcLines.length > 1 ? (
                  <button
                    type="button"
                    className="text-xs text-rose-600 hover:underline"
                    onClick={() => setSvcLines(svcLines.filter((_, i) => i !== idx))}
                  >
                    Remove
                  </button>
                ) : null}
              </div>
            ))}
            <button
              type="button"
              className="mr-2 text-xs text-pink-700 hover:underline"
              onClick={() => setSvcLines([...svcLines, { service_id: "", quantity: "1" }])}
            >
              + Add line
            </button>
            <button
              type="button"
              disabled={busy !== null}
              onClick={() => void saveServices()}
              className="rounded bg-slate-800 px-2 py-1 text-xs font-semibold text-white hover:bg-slate-900 disabled:opacity-50"
            >
              {busy === "services" ? "Saving…" : "Save services"}
            </button>
          </div>
        </AppointmentDetailBlock>
      ) : (
        <AppointmentDetailBlock title="Service lines" subtitle="Completed visits: lines are locked.">
          <p className="text-xs text-slate-500">Service lines cannot be edited for completed visits.</p>
        </AppointmentDetailBlock>
      )}

      <AppointmentDetailBlock title="Notes">
        <textarea
          rows={2}
          className="w-full rounded border border-slate-300 px-2 py-1 text-xs"
          value={notesDraft}
          onChange={(ev) => setNotesDraft(ev.target.value)}
          aria-label="Appointment notes"
        />
        <button
          type="button"
          disabled={busy !== null}
          onClick={() => void patch({ notes: notesDraft || null }, "notes")}
          className="mt-1 rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold hover:bg-slate-50 disabled:opacity-50"
        >
          Save notes
        </button>
      </AppointmentDetailBlock>

      <AppointmentDetailBlock title="Record payment" subtitle="Add a payment, deposit, refund, or adjustment.">
        <form className="space-y-2" onSubmit={submitPayment}>
          <div className="flex flex-wrap gap-2">
            <input
              type="number"
              step="0.01"
              min="0.01"
              placeholder="Amount"
              className="w-24 rounded border border-slate-300 px-2 py-1 text-xs"
              value={payAmount}
              onChange={(ev) => setPayAmount(ev.target.value)}
            />
            <select
              className="rounded border border-slate-300 px-1 py-1 text-xs"
              value={payType}
              onChange={(ev) => setPayType(ev.target.value as typeof payType)}
            >
              <option value="payment">Payment</option>
              <option value="deposit">Deposit</option>
              <option value="refund">Refund</option>
              <option value="adjustment">Adjustment</option>
            </select>
          </div>
          <input
            type="text"
            placeholder="Note (optional)"
            className="w-full rounded border border-slate-300 px-2 py-1 text-xs"
            value={payNote}
            onChange={(ev) => setPayNote(ev.target.value)}
          />
          <button
            type="submit"
            disabled={busy !== null}
            className="rounded bg-slate-800 px-2 py-1 text-xs font-semibold text-white hover:bg-slate-900 disabled:opacity-50"
          >
            {busy === "pay" ? "Saving…" : "Add payment line"}
          </button>
        </form>
      </AppointmentDetailBlock>

      <AppointmentDetailBlock
        title={payments.length ? `Payment lines (${payments.length})` : "Payment lines"}
        subtitle={payments.length ? "Recorded payment entries for this visit." : "No entries yet."}
      >
        {payments.length ? (
          <ul className="space-y-1 text-xs text-slate-600">
            {payments.map((p) => (
              <li key={p.id} className="flex items-center justify-between gap-2">
                <span>
                  {p.entry_type} ${Number(p.amount).toFixed(2)}
                  {p.note ? ` — ${p.note}` : ""}
                </span>
                <button
                  type="button"
                  className="text-rose-600 hover:underline"
                  disabled={busy !== null}
                  onClick={() => void deletePaymentEntry(p.id)}
                >
                  Remove
                </button>
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-xs text-slate-500">No payment lines recorded yet.</p>
        )}
      </AppointmentDetailBlock>

      {cancelOpen ? (
        <form className="mt-3 space-y-2 rounded-md border border-rose-200 bg-rose-50/50 p-3" onSubmit={submitCancel}>
          <p className="text-xs font-semibold text-rose-900">Cancel appointment</p>
          <textarea
            required
            rows={3}
            placeholder="Cancellation reason (required)"
            className="w-full rounded border border-slate-300 px-2 py-1 text-xs"
            value={cancelReason}
            onChange={(ev) => setCancelReason(ev.target.value)}
          />
          <label className="flex items-center gap-2 text-xs text-slate-700">
            <input type="checkbox" checked={salesFollowUp} onChange={(ev) => setSalesFollowUp(ev.target.checked)} />
            Sales follow-up needed
          </label>
          <div className="flex gap-2">
            <button
              type="submit"
              disabled={busy !== null}
              className="rounded bg-rose-600 px-2 py-1 text-xs font-semibold text-white disabled:opacity-50"
            >
              Confirm cancel
            </button>
            <button
              type="button"
              className="rounded border border-slate-300 px-2 py-1 text-xs"
              onClick={() => setCancelOpen(false)}
            >
              Close
            </button>
          </div>
        </form>
      ) : null}
    </div>
  );
}

export function AppointmentsClient() {
  const sp = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();

  const path = useMemo(() => {
    const q = sp.toString();
    return `/spa/appointments${q ? `?${q}` : ""}`;
  }, [sp]);

  const { data, error, loading, reload } = useSpaGet<AppointmentsPayload>(path);

  const [scheduleFlash, setScheduleFlash] = useState<string | null>(null);
  const [scheduleErr, setScheduleErr] = useState<string | null>(null);

  const [wlContactEntry, setWlContactEntry] = useState<WaitlistEntry | null>(null);
  const [wlContactMethod, setWlContactMethod] = useState("phone");
  const [wlContactNotes, setWlContactNotes] = useState("");
  const [wlContactAt, setWlContactAt] = useState("");
  const [wlContactBusy, setWlContactBusy] = useState(false);
  const [wlContactErr, setWlContactErr] = useState<string | null>(null);

  const rescheduleToDay = useCallback(
    async (appointmentId: number, targetDate: string) => {
      setScheduleErr(null);
      try {
        const res = await spaFetch(`/appointments/${appointmentId}/reschedule`, {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ target_date: targetDate }),
        });
        const b = await res.json().catch(() => ({}));
        if (!res.ok) {
          setScheduleErr(firstErrorMessage(b, "Could not move appointment."));
          return;
        }
        setScheduleFlash(targetDate);
        window.setTimeout(() => setScheduleFlash(null), 900);
        const monthHint =
          typeof (b as { month?: string }).month === "string"
            ? (b as { month: string }).month
            : targetDate.slice(0, 7);
        router.push(mergeHref(sp, { month: monthHint, date: targetDate }));
        await reload();
      } catch {
        setScheduleErr("Could not reach the server.");
      }
    },
    [reload, router, sp],
  );

  const ym = data ? ymFromMonthBase(String(data.monthBase)) : "";
  const selectedYmd = data ? ymd(String(data.selectedDate)) : "";

  const [draftStatus, setDraftStatus] = useState("");
  const [draftSearch, setDraftSearch] = useState("");
  const [draftStaff, setDraftStaff] = useState("");
  const [draftService, setDraftService] = useState("");
  const [draftArrived, setDraftArrived] = useState("");
  const [draftCustomer, setDraftCustomer] = useState("");

  useEffect(() => {
    setDraftStatus(String(sp.get("status") ?? ""));
    setDraftSearch(sp.get("search") ?? "");
    setDraftStaff(sp.get("staff_user_id") ?? "");
    setDraftService(sp.get("service_id") ?? "");
    setDraftArrived(sp.get("arrived") ?? "");
    setDraftCustomer(sp.get("customer_id") ?? "");
  }, [sp]);

  const applyFilters = useCallback(() => {
    const n = new URLSearchParams(sp.toString());
    const setOrDel = (k: string, v: string) => {
      if (!v) {
        n.delete(k);
      } else {
        n.set(k, v);
      }
    };
    setOrDel("status", draftStatus);
    setOrDel("search", draftSearch.trim());
    setOrDel("staff_user_id", draftStaff);
    setOrDel("service_id", draftService);
    setOrDel("arrived", draftArrived);
    setOrDel("customer_id", draftCustomer);
    const s = n.toString();
    router.push(s ? `${pathname}?${s}` : pathname);
  }, [draftArrived, draftCustomer, draftService, draftStaff, draftStatus, draftSearch, pathname, router, sp]);

  const clearFilters = useCallback(() => {
    setDraftStatus("");
    setDraftSearch("");
    setDraftStaff("");
    setDraftService("");
    setDraftArrived("");
    setDraftCustomer("");
    const n = new URLSearchParams(sp.toString());
    ["status", "search", "staff_user_id", "service_id", "arrived", "customer_id"].forEach((k) => n.delete(k));
    const s = n.toString();
    router.push(s ? `${pathname}?${s}` : pathname);
  }, [pathname, router, sp]);

  const customers = data?.customers ?? [];
  const services = data?.services ?? [];
  const staffUsers = data?.staffUsers ?? [];
  const depositRequired = Boolean(data?.clinicSettings?.deposit_required);

  const [createCustomer, setCreateCustomer] = useState<string>("");
  const [createWhen, setCreateWhen] = useState<string>("");
  const [createStaff, setCreateStaff] = useState<string>("");
  const [createLines, setCreateLines] = useState<{ service_id: string; quantity: string }[]>([
    { service_id: "", quantity: "1" },
  ]);
  const [createDeposit, setCreateDeposit] = useState(false);
  const [createBusy, setCreateBusy] = useState(false);
  const [createErr, setCreateErr] = useState<string | null>(null);

  useEffect(() => {
    if (!data || createWhen) {
      return;
    }
    const base = ymd(String(data.selectedDate));
    setCreateWhen(`${base}T09:00`);
  }, [data, createWhen]);

  useEffect(() => {
    if (!services.length) {
      return;
    }
    setCreateLines((prev) => {
      if (prev.length === 1 && prev[0].service_id === "") {
        return [{ service_id: String(services[0].id), quantity: "1" }];
      }
      return prev;
    });
  }, [services]);

  const [wlCustomer, setWlCustomer] = useState("");
  const [wlService, setWlService] = useState("");
  const [wlStaff, setWlStaff] = useState("");
  const [wlDate, setWlDate] = useState("");
  const [wlStart, setWlStart] = useState("");
  const [wlEnd, setWlEnd] = useState("");
  const [wlNotes, setWlNotes] = useState("");
  const [wlLead, setWlLead] = useState("");
  const [wlBusy, setWlBusy] = useState(false);
  const [wlErr, setWlErr] = useState<string | null>(null);

  useEffect(() => {
    if (selectedYmd) {
      setWlDate(selectedYmd);
    }
  }, [selectedYmd]);

  async function submitCreate(e: React.FormEvent) {
    e.preventDefault();
    setCreateErr(null);
    const cid = Number(createCustomer);
    if (!cid) {
      setCreateErr("Choose a customer.");
      return;
    }
    const lines = createLines
      .map((row) => ({ service_id: Number(row.service_id), quantity: Math.max(1, Number(row.quantity) || 1) }))
      .filter((row) => Number.isFinite(row.service_id) && row.service_id > 0);
    if (lines.length === 0) {
      setCreateErr("Add at least one service.");
      return;
    }
    if (!createWhen) {
      setCreateErr("Choose date & time.");
      return;
    }
    if (depositRequired && !createDeposit) {
      setCreateErr("Deposit must be marked paid per clinic settings.");
      return;
    }
    setCreateBusy(true);
    try {
      const body: Record<string, unknown> = {
        customer_id: cid,
        scheduled_at: new Date(createWhen).toISOString(),
        staff_user_id: createStaff ? Number(createStaff) : null,
        services: lines,
        notes: null,
      };
      if (depositRequired) {
        body.deposit_paid = createDeposit;
      }
      const res = await spaFetch("/appointments", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setCreateErr(firstErrorMessage(b, "Could not create appointment."));
        return;
      }
      await reload();
    } catch {
      setCreateErr("Could not reach the server.");
    } finally {
      setCreateBusy(false);
    }
  }

  async function submitWaitlist(e: React.FormEvent) {
    e.preventDefault();
    setWlErr(null);
    const cid = Number(wlCustomer);
    if (!cid || !wlDate) {
      setWlErr("Customer and preferred date are required.");
      return;
    }
    setWlBusy(true);
    try {
      const res = await spaFetch("/waitlist-entries", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          customer_id: cid,
          service_id: wlService ? Number(wlService) : null,
          staff_user_id: wlStaff ? Number(wlStaff) : null,
          preferred_date: wlDate,
          preferred_start_time: wlStart || null,
          preferred_end_time: wlEnd || null,
          notes: wlNotes || null,
          lead_source: wlLead || null,
        }),
      });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setWlErr(firstErrorMessage(b, "Could not add to waitlist."));
        return;
      }
      setWlNotes("");
      await reload();
    } catch {
      setWlErr("Could not reach the server.");
    } finally {
      setWlBusy(false);
    }
  }

  async function patchWaitlist(id: number, status: string) {
    try {
      const res = await spaFetch(`/waitlist-entries/${id}/status`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ status }),
      });
      if (!res.ok) {
        return;
      }
      await reload();
    } catch {
      /* ignore */
    }
  }

  function openWaitlistContact(entry: WaitlistEntry) {
    setWlContactEntry(entry);
    setWlContactErr(null);
    setWlContactNotes("");
    setWlContactMethod("phone");
    setWlContactAt(toDatetimeLocalInput());
  }

  async function submitWaitlistContact(e: React.FormEvent) {
    e.preventDefault();
    if (!wlContactEntry) {
      return;
    }
    const notes = wlContactNotes.trim();
    if (!notes) {
      setWlContactErr("Contact notes are required.");
      return;
    }
    setWlContactErr(null);
    setWlContactBusy(true);
    try {
      const res = await spaFetch(`/waitlist-entries/${wlContactEntry.id}/contact`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          contact_method: wlContactMethod,
          contact_notes: notes,
          contacted_at: new Date(wlContactAt).toISOString(),
        }),
      });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setWlContactErr(firstErrorMessage(b, "Could not log contact."));
        return;
      }
      setWlContactEntry(null);
      await reload();
    } catch {
      setWlContactErr("Could not reach the server.");
    } finally {
      setWlContactBusy(false);
    }
  }

  return (
    <SpaPageFrame title="Appointments" loading={loading} error={error}>
      {data?.weeks ? (
        <div className="space-y-6">
          <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
            <div className="flex flex-wrap items-center gap-2">
              <Link
                href={mergeHref(sp, { month: shiftMonth(ym, -1) })}
                className="rounded border border-slate-300 bg-white px-2 py-1 text-xs hover:bg-slate-50"
              >
                ← Prev month
              </Link>
              <span className="text-slate-700">
                Month: <strong>{ym ? formatMonthYearLabel(ym) : ""}</strong>
              </span>
              <Link
                href={mergeHref(sp, { month: shiftMonth(ym, 1) })}
                className="rounded border border-slate-300 bg-white px-2 py-1 text-xs hover:bg-slate-50"
              >
                Next month →
              </Link>
            </div>
            <button
              type="button"
              className="rounded border border-slate-300 bg-white px-2 py-1 text-xs hover:bg-slate-50"
              onClick={() => reload()}
            >
              Refresh
            </button>
          </div>

          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 className="text-sm font-semibold text-slate-900">Filters</h2>
            <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <label className="text-xs font-medium text-slate-700">
                Status
                <select
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                  value={draftStatus}
                  onChange={(ev) => setDraftStatus(ev.target.value)}
                >
                  <option value="">Any</option>
                  <option value="booked">Booked</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                  <option value="no_show">No-show</option>
                </select>
              </label>
              <label className="text-xs font-medium text-slate-700">
                Search customer
                <input
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                  value={draftSearch}
                  onChange={(ev) => setDraftSearch(ev.target.value)}
                />
              </label>
              <label className="text-xs font-medium text-slate-700">
                Staff
                <select
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                  value={draftStaff}
                  onChange={(ev) => setDraftStaff(ev.target.value)}
                >
                  <option value="">Any</option>
                  {staffUsers.map((u) => (
                    <option key={u.id} value={u.id}>
                      {u.name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="text-xs font-medium text-slate-700">
                Service
                <select
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                  value={draftService}
                  onChange={(ev) => setDraftService(ev.target.value)}
                >
                  <option value="">Any</option>
                  {services.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="text-xs font-medium text-slate-700">
                Arrived
                <select
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                  value={draftArrived}
                  onChange={(ev) => setDraftArrived(ev.target.value)}
                >
                  <option value="">Any</option>
                  <option value="yes">Yes</option>
                  <option value="no">No</option>
                </select>
              </label>
              <div className="sm:col-span-2 lg:col-span-3">
                <CustomerFilterCombobox
                  customers={customers}
                  value={draftCustomer}
                  onValueChange={setDraftCustomer}
                />
              </div>
            </div>
            <div className="mt-3 flex flex-wrap gap-2">
              <button
                type="button"
                onClick={applyFilters}
                className="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-900"
              >
                Apply filters
              </button>
              <button
                type="button"
                onClick={clearFilters}
                className="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
              >
                Clear filters
              </button>
            </div>
          </section>

          <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
            {scheduleErr ? <p className="mb-2 text-xs text-rose-600">{scheduleErr}</p> : null}
            <p className="mb-2 text-[11px] text-slate-500">
              Tip: drag <span className="font-semibold">Drag to day</span> from a booked appointment onto a date cell to
              reschedule (time preserved).
            </p>
            <table className="min-w-full border-collapse text-center text-xs">
              <tbody>
                {data.weeks.map((week, wi) => (
                  <tr key={wi}>
                    {week.map((cell, ci) => {
                      const d = ymd(cell.date);
                      return (
                        <td key={ci} className="border border-slate-100 p-1 align-top">
                          <div
                            role="button"
                            tabIndex={0}
                            className={`block cursor-pointer rounded px-1 py-2 outline-none focus-visible:ring-2 focus-visible:ring-pink-400 ${
                              cell.is_selected ? "bg-pink-100 ring-1 ring-pink-300" : ""
                            } ${cell.is_today ? "font-semibold text-pink-800" : ""} ${
                              cell.in_month ? "text-slate-900" : "text-slate-400"
                            } ${scheduleFlash === d ? "ring-2 ring-emerald-500 ring-offset-1" : ""}`}
                            onClick={() => router.push(mergeHref(sp, { month: ym, date: d }))}
                            onKeyDown={(e) => {
                              if (e.key === "Enter" || e.key === " ") {
                                e.preventDefault();
                                router.push(mergeHref(sp, { month: ym, date: d }));
                              }
                            }}
                            onDragOver={(e) => {
                              e.preventDefault();
                              e.dataTransfer.dropEffect = "move";
                            }}
                            onDrop={(e) => {
                              e.preventDefault();
                              const raw = e.dataTransfer.getData("application/x-beautiskin-appointment-id");
                              const aid = Number(raw);
                              if (!Number.isFinite(aid) || aid <= 0) {
                                return;
                              }
                              void rescheduleToDay(aid, d);
                            }}
                          >
                            <div>{d.slice(8, 10)}</div>
                            <div className="text-[10px] text-slate-500">{cell.count}</div>
                          </div>
                        </td>
                      );
                    })}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 className="text-sm font-semibold text-slate-900">Selected day</h2>
            <p className="mt-1 text-xs text-slate-500">{selectedYmd}</p>
            <div className="mt-4 space-y-3">
              {(data.selectedAppointments ?? []).length === 0 ? (
                <p className="text-sm text-slate-500">No appointments on this day.</p>
              ) : (
                (data.selectedAppointments ?? []).map((row) => (
                  <DayAppointmentCard
                    key={String(row.id)}
                    appt={row}
                    staffUsers={staffUsers}
                    catalogServices={services}
                    onReload={reload}
                  />
                ))
              )}
            </div>
          </div>

          <div className="grid gap-6 lg:grid-cols-2">
            <form className="space-y-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm" onSubmit={submitCreate}>
              <h2 className="text-sm font-semibold text-slate-900">New appointment</h2>
              {createErr ? <p className="text-xs text-rose-600">{createErr}</p> : null}
              <label className="block text-xs font-medium text-slate-700">
                Customer
                <select
                  required
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                  value={createCustomer}
                  onChange={(ev) => setCreateCustomer(ev.target.value)}
                >
                  <option value="">Select…</option>
                  {customers.map((c) => (
                    <option key={c.id} value={c.id}>
                      {customerLabel(c)}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-xs font-medium text-slate-700">
                Start
                <input
                  type="datetime-local"
                  required
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                  value={createWhen}
                  onChange={(ev) => setCreateWhen(ev.target.value)}
                />
              </label>
              <label className="block text-xs font-medium text-slate-700">
                Staff (optional)
                <select
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                  value={createStaff}
                  onChange={(ev) => setCreateStaff(ev.target.value)}
                >
                  <option value="">Unassigned</option>
                  {staffUsers.map((u) => (
                    <option key={u.id} value={u.id}>
                      {u.name}
                    </option>
                  ))}
                </select>
              </label>
              {depositRequired ? (
                <label className="flex items-center gap-2 text-xs text-slate-700">
                  <input type="checkbox" checked={createDeposit} onChange={(ev) => setCreateDeposit(ev.target.checked)} />
                  Deposit paid (required by clinic settings)
                </label>
              ) : null}
              <div>
                <p className="text-xs font-medium text-slate-700">Services</p>
                {createLines.map((row, idx) => (
                  <div key={idx} className="mt-2 flex flex-wrap gap-2">
                    <select
                      className="min-w-[140px] flex-1 rounded border border-slate-300 px-2 py-1 text-sm"
                      value={row.service_id}
                      onChange={(ev) => {
                        const next = [...createLines];
                        next[idx] = { ...next[idx], service_id: ev.target.value };
                        setCreateLines(next);
                      }}
                    >
                      <option value="">Service…</option>
                      {services.map((s) => (
                        <option key={s.id} value={s.id}>
                          {s.name}
                        </option>
                      ))}
                    </select>
                    <input
                      type="number"
                      min={1}
                      className="w-20 rounded border border-slate-300 px-2 py-1 text-sm"
                      value={row.quantity}
                      onChange={(ev) => {
                        const next = [...createLines];
                        next[idx] = { ...next[idx], quantity: ev.target.value };
                        setCreateLines(next);
                      }}
                    />
                    {createLines.length > 1 ? (
                      <button
                        type="button"
                        className="text-xs text-rose-600 hover:underline"
                        onClick={() => setCreateLines(createLines.filter((_, i) => i !== idx))}
                      >
                        Remove
                      </button>
                    ) : null}
                  </div>
                ))}
                <button
                  type="button"
                  className="mt-2 text-xs text-pink-700 hover:underline"
                  onClick={() => setCreateLines([...createLines, { service_id: "", quantity: "1" }])}
                >
                  + Add service line
                </button>
              </div>
              <button
                type="submit"
                disabled={createBusy}
                className="rounded-md bg-pink-600 px-3 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-50"
              >
                {createBusy ? "Creating…" : "Create appointment"}
              </button>
            </form>

            <form className="space-y-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm" onSubmit={submitWaitlist}>
              <h2 className="text-sm font-semibold text-slate-900">Add to waitlist</h2>
              {wlErr ? <p className="text-xs text-rose-600">{wlErr}</p> : null}
              <label className="block text-xs font-medium text-slate-700">
                Customer
                <select
                  required
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                  value={wlCustomer}
                  onChange={(ev) => setWlCustomer(ev.target.value)}
                >
                  <option value="">Select…</option>
                  {customers.map((c) => (
                    <option key={c.id} value={c.id}>
                      {customerLabel(c)}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-xs font-medium text-slate-700">
                Preferred date
                <input
                  type="date"
                  required
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                  value={wlDate}
                  onChange={(ev) => setWlDate(ev.target.value)}
                />
              </label>
              <div className="flex gap-2">
                <label className="flex-1 text-xs font-medium text-slate-700">
                  Start (optional)
                  <input
                    type="time"
                    className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                    value={wlStart}
                    onChange={(ev) => setWlStart(ev.target.value)}
                  />
                </label>
                <label className="flex-1 text-xs font-medium text-slate-700">
                  End (optional)
                  <input
                    type="time"
                    className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                    value={wlEnd}
                    onChange={(ev) => setWlEnd(ev.target.value)}
                  />
                </label>
              </div>
              <label className="block text-xs font-medium text-slate-700">
                Service (optional)
                <select
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                  value={wlService}
                  onChange={(ev) => setWlService(ev.target.value)}
                >
                  <option value="">Any service</option>
                  {services.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-xs font-medium text-slate-700">
                Preferred staff (optional)
                <select
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                  value={wlStaff}
                  onChange={(ev) => setWlStaff(ev.target.value)}
                >
                  <option value="">Any</option>
                  {staffUsers.map((u) => (
                    <option key={u.id} value={u.id}>
                      {u.name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-xs font-medium text-slate-700">
                Lead source
                <select
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                  value={wlLead}
                  onChange={(ev) => setWlLead(ev.target.value)}
                >
                  <option value="">Unknown</option>
                  {(data.leadSourceOptions ?? []).map((o) => (
                    <option key={o.value} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-xs font-medium text-slate-700">
                Notes
                <textarea
                  rows={2}
                  className="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm"
                  value={wlNotes}
                  onChange={(ev) => setWlNotes(ev.target.value)}
                />
              </label>
              <button
                type="submit"
                disabled={wlBusy}
                className="rounded-md bg-slate-800 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-900 disabled:opacity-50"
              >
                {wlBusy ? "Saving…" : "Add to waitlist"}
              </button>
            </form>
          </div>

          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 className="text-sm font-semibold text-slate-900">Waitlist / standby (this day)</h2>
            <div className="mt-3 space-y-3">
              {(data.selectedWaitlistEntries ?? []).length === 0 ? (
                <p className="text-sm text-slate-500">No standby customers for this day.</p>
              ) : (
                (data.selectedWaitlistEntries ?? []).map((w) => (
                  <div key={w.id} className="rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                      <div>
                        <p className="text-sm font-medium text-slate-900">
                          {w.customer ? customerLabel(w.customer) : "Customer"}
                        </p>
                        <p className="text-xs text-slate-600">{w.service?.name ?? "Any service"}</p>
                        <p className="text-xs text-slate-500">
                          {w.preferred_start_time ?? "Any time"}
                          {w.preferred_end_time ? ` – ${w.preferred_end_time}` : ""}
                        </p>
                      </div>
                      <span className="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-800">
                        {w.status}
                      </span>
                    </div>
                    <div className="mt-2 flex flex-wrap gap-2">
                      {w.status !== "contacted" ? (
                        <button
                          type="button"
                          className="rounded bg-slate-700 px-2 py-1 text-xs font-semibold text-white hover:bg-slate-800"
                          onClick={() => openWaitlistContact(w)}
                        >
                          Log contact
                        </button>
                      ) : null}
                      <button
                        type="button"
                        className="rounded bg-emerald-600 px-2 py-1 text-xs font-semibold text-white hover:bg-emerald-700"
                        onClick={() => void patchWaitlist(w.id, "booked")}
                      >
                        Mark booked
                      </button>
                      <button
                        type="button"
                        className="rounded bg-rose-600 px-2 py-1 text-xs font-semibold text-white hover:bg-rose-700"
                        onClick={() => void patchWaitlist(w.id, "cancelled")}
                      >
                        Remove
                      </button>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>

          {wlContactEntry ? (
            <div
              className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
              role="dialog"
              aria-modal="true"
              aria-labelledby="wl-contact-title"
            >
              <div className="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl border border-slate-200 bg-white p-5 shadow-xl">
                <h2 id="wl-contact-title" className="text-sm font-semibold text-slate-900">
                  Log waitlist contact
                </h2>
                <p className="mt-1 text-xs text-slate-600">
                  {wlContactEntry.customer ? customerLabel(wlContactEntry.customer) : "Customer"} · preferred{" "}
                  {wlContactEntry.preferred_start_time ?? "any"} time
                </p>
                <form className="mt-4 space-y-3" onSubmit={submitWaitlistContact}>
                  <label className="block text-xs font-medium text-slate-700">
                    Method
                    <select
                      className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                      value={wlContactMethod}
                      onChange={(ev) => setWlContactMethod(ev.target.value)}
                    >
                      <option value="phone">Phone</option>
                      <option value="email">Email</option>
                      <option value="whatsapp">WhatsApp</option>
                      <option value="messenger">Messenger (Meta)</option>
                      <option value="social_chat">Social media chat</option>
                    </select>
                  </label>
                  <label className="block text-xs font-medium text-slate-700">
                    When
                    <input
                      type="datetime-local"
                      required
                      className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                      value={wlContactAt}
                      onChange={(ev) => setWlContactAt(ev.target.value)}
                    />
                  </label>
                  <label className="block text-xs font-medium text-slate-700">
                    Notes
                    <textarea
                      required
                      rows={4}
                      className="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm"
                      value={wlContactNotes}
                      onChange={(ev) => setWlContactNotes(ev.target.value)}
                      placeholder="What was discussed?"
                    />
                  </label>
                  {wlContactErr ? <p className="text-xs text-rose-600">{wlContactErr}</p> : null}
                  <div className="flex flex-wrap gap-2 pt-2">
                    <button
                      type="submit"
                      disabled={wlContactBusy}
                      className="rounded-md bg-slate-800 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-900 disabled:opacity-50"
                    >
                      {wlContactBusy ? "Saving…" : "Save contact"}
                    </button>
                    <button
                      type="button"
                      className="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                      onClick={() => setWlContactEntry(null)}
                    >
                      Cancel
                    </button>
                  </div>
                </form>
              </div>
            </div>
          ) : null}

          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 className="text-sm font-semibold text-slate-900">Staff availability</h2>
            <p className="mt-1 text-xs text-slate-500">Grouped for {selectedYmd}</p>
            <div className="mt-3 space-y-3">
              {(data.staffAvailability ?? []).map((row, i) => (
                <div key={i} className="rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2">
                  <div className="flex items-center justify-between">
                    <p className="text-sm font-medium">{row.label}</p>
                    <span className="text-xs text-slate-500">
                      {row.count} {row.count === 1 ? "appointment" : "appointments"}
                    </span>
                  </div>
                  {row.count > 0 ? (
                    <ul className="mt-2 space-y-1 text-xs text-slate-600">
                      {row.appointments.map((a) => {
                        const c = relCustomer(a);
                        return (
                          <li key={String(a.id)}>
                            {formatTimeRange(String(a.scheduled_at), String(a.ends_at ?? ""))}:{" "}
                            {c ? customerLabel(c) : "—"}
                          </li>
                        );
                      })}
                    </ul>
                  ) : (
                    <p className="mt-2 text-xs text-emerald-700">Available all day in current filtered view.</p>
                  )}
                </div>
              ))}
            </div>
          </div>
        </div>
      ) : null}
    </SpaPageFrame>
  );
}
