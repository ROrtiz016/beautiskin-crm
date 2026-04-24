"use client";

import { toDatetimeLocalInput, ymd } from "@/app/(crm)/appointments/appointments-helpers";
import { formatCountryLabel, formatUsStateLabel } from "@/lib/geo-select-options";
import { SpaPageFrame } from "@/components/spa-page-frame";
import { useSpaGet } from "@/hooks/use-spa-get";
import { firstErrorMessage } from "@/lib/laravel-form-errors";
import { spaFetch } from "@/lib/spa-fetch";
import Link from "next/link";
import { useParams } from "next/navigation";
import { useCallback, useEffect, useMemo, useState } from "react";

type UnknownRec = Record<string, unknown>;

function money(n: unknown): string {
  const v = typeof n === "string" ? Number(n) : Number(n);
  if (Number.isNaN(v)) {
    return "$0.00";
  }
  return v.toLocaleString(undefined, { style: "currency", currency: "USD" });
}

function dt(iso: unknown): string {
  if (!iso || typeof iso !== "string") {
    return "—";
  }
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? "—" : d.toLocaleString();
}

function dOnly(iso: unknown): string {
  if (!iso || typeof iso !== "string") {
    return "—";
  }
  return iso.slice(0, 10);
}

function formatCustomerAddress(c: UnknownRec): string {
  const lines: string[] = [];
  const l1 = String(c.address_line1 ?? "").trim();
  const l2 = String(c.address_line2 ?? "").trim();
  const city = String(c.city ?? "").trim();
  const st = String(c.state_region ?? "").trim();
  const zip = String(c.postal_code ?? "").trim();
  const country = String(c.country ?? "").trim();
  if (l1) {
    lines.push(l1);
  }
  if (l2) {
    lines.push(l2);
  }
  const stLabel = st ? formatUsStateLabel(st) : "";
  const cityLine = [city, stLabel].filter(Boolean).join(", ");
  if (cityLine) {
    lines.push(cityLine);
  }
  const countryLabel = country ? formatCountryLabel(country) : "";
  const zipCountry = [zip, countryLabel].filter(Boolean).join(" ");
  if (zipCountry) {
    lines.push(zipCountry);
  }
  return lines.join("\n");
}

function staffName(a: UnknownRec): string {
  const su = (a.staff_user ?? a.staffUser) as { name?: string } | null | undefined;
  return su?.name ?? "Unassigned";
}

function svcNames(a: UnknownRec): string {
  const svcs = a.services as unknown[] | undefined;
  if (!Array.isArray(svcs) || !svcs.length) {
    return "TBD";
  }
  return svcs
    .map((s) => (s as UnknownRec).service_name ?? (s as UnknownRec).serviceName ?? "")
    .filter(Boolean)
    .join(", ");
}

function relServiceLinesForEdit(appt: UnknownRec): { service_id: number; quantity: number }[] {
  const raw = appt.services as Record<string, unknown>[] | undefined;
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

function scheduleToLocalInput(iso: unknown): string {
  if (!iso || typeof iso !== "string") {
    return "";
  }
  return `${ymd(iso)}T${new Date(iso).toTimeString().slice(0, 5)}`;
}

type SpaService = { id: number; name: string; price?: string };
type SpaStaff = { id: number; name: string };
type ClinicSettings = { deposit_required?: boolean };

type CustomerShowPayload = {
  customer?: UnknownRec;
  totalSpent?: number;
  paymentHistory?: unknown[];
  servicesReceived?: unknown[];
  currentMemberships?: unknown[];
  pastMemberships?: unknown[];
  nextAppointment?: UnknownRec | null;
  bookedAppointments?: unknown[];
  pastAppointments?: unknown[];
  categoryLabels?: Record<string, string>;
  services?: SpaService[];
  staffUsers?: SpaStaff[];
  retailSaleServices?: SpaService[];
  clinicSettings?: ClinicSettings;
};

function Dialog({
  open,
  title,
  onClose,
  children,
}: {
  open: boolean;
  title: string;
  onClose: () => void;
  children: React.ReactNode;
}) {
  if (!open) {
    return null;
  }
  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      role="dialog"
      aria-modal="true"
    >
      <div
        className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-slate-200 bg-white p-5 shadow-xl"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-start justify-between gap-3">
          <h3 className="text-lg font-semibold text-slate-900">{title}</h3>
          <button
            type="button"
            className="rounded px-2 py-1 text-sm text-slate-500 hover:bg-slate-100 hover:text-slate-800"
            onClick={onClose}
          >
            Close
          </button>
        </div>
        <div className="mt-4">{children}</div>
      </div>
    </div>
  );
}

export function CustomerDetailClient() {
  const params = useParams();
  const id = String(params.id ?? "");
  const path = useMemo(() => `/spa/customers/${id}`, [id]);
  const { data, error, loading, reload } = useSpaGet<CustomerShowPayload>(path);

  const c = data?.customer as UnknownRec | undefined;
  const title = c
    ? `${String(c.first_name ?? "").trim()} ${String(c.last_name ?? "").trim()}`.trim() || "Customer"
    : "Customer";

  const opportunities = (c?.opportunities as unknown[] | undefined) ?? [];
  const tasks = (c?.tasks as unknown[] | undefined) ?? [];
  const activities = (c?.activities as unknown[] | undefined) ?? [];

  const catalogServices = (data?.services as SpaService[] | undefined) ?? [];
  const staffUsers = (data?.staffUsers as SpaStaff[] | undefined) ?? [];
  const retailServices = (data?.retailSaleServices as SpaService[] | undefined) ?? [];
  const depositRequired = Boolean(data?.clinicSettings?.deposit_required);

  const completedVisits = useMemo(() => {
    const past = (data?.pastAppointments as unknown[] | undefined) ?? [];
    return past.filter((row) => String((row as UnknownRec).status) === "completed") as UnknownRec[];
  }, [data?.pastAppointments]);

  const [contactField, setContactField] = useState<"email" | "phone" | "date_of_birth" | null>(null);
  const [contactValue, setContactValue] = useState("");
  const [contactBusy, setContactBusy] = useState(false);
  const [contactErr, setContactErr] = useState<string | null>(null);

  const openContact = useCallback((field: "email" | "phone" | "date_of_birth") => {
    if (!c) {
      return;
    }
    setContactErr(null);
    setContactField(field);
    if (field === "email") {
      setContactValue(String(c.email ?? ""));
    } else if (field === "phone") {
      setContactValue(String(c.phone ?? ""));
    } else {
      setContactValue(dOnly(c.date_of_birth as string | undefined) === "—" ? "" : dOnly(c.date_of_birth as string));
    }
  }, [c]);

  async function saveContact(e: React.FormEvent) {
    e.preventDefault();
    if (!contactField) {
      return;
    }
    setContactBusy(true);
    setContactErr(null);
    try {
      const body: Record<string, unknown> = {};
      if (contactField === "email") {
        body.email = contactValue.trim() === "" ? null : contactValue.trim();
      } else if (contactField === "phone") {
        body.phone = contactValue.trim() === "" ? null : contactValue.trim();
      } else {
        body.date_of_birth = contactValue.trim() === "" ? null : contactValue.trim();
      }
      const res = await spaFetch(`/customers/${id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setContactErr(firstErrorMessage(b, "Update failed."));
        return;
      }
      setContactField(null);
      await reload();
    } catch {
      setContactErr("Could not reach the server.");
    } finally {
      setContactBusy(false);
    }
  }

  const [bookOpen, setBookOpen] = useState(false);
  const [bookScheduled, setBookScheduled] = useState("");
  const [bookEnds, setBookEnds] = useState("");
  const [bookStaff, setBookStaff] = useState("");
  const [bookNotes, setBookNotes] = useState("");
  const [bookDeposit, setBookDeposit] = useState(false);
  const [bookLines, setBookLines] = useState<{ service_id: string; quantity: string }[]>([
    { service_id: "", quantity: "1" },
  ]);
  const [bookBusy, setBookBusy] = useState(false);
  const [bookErr, setBookErr] = useState<string | null>(null);

  useEffect(() => {
    if (!bookOpen) {
      return;
    }
    setBookErr(null);
    setBookScheduled(toDatetimeLocalInput());
    setBookEnds("");
    setBookStaff("");
    setBookNotes("");
    setBookDeposit(depositRequired);
    setBookLines(
      catalogServices[0]
        ? [{ service_id: String(catalogServices[0].id), quantity: "1" }]
        : [{ service_id: "", quantity: "1" }],
    );
  }, [bookOpen, catalogServices, depositRequired]);

  async function submitBook(e: React.FormEvent) {
    e.preventDefault();
    if (!bookScheduled) {
      setBookErr("Choose a start time.");
      return;
    }
    const lines = bookLines
      .map((row) => ({
        service_id: Number(row.service_id),
        quantity: Math.max(1, Math.floor(Number(row.quantity) || 1)),
      }))
      .filter((row) => Number.isFinite(row.service_id) && row.service_id > 0);
    if (lines.length < 1) {
      setBookErr("Add at least one service.");
      return;
    }
    setBookBusy(true);
    setBookErr(null);
    try {
      const payload: Record<string, unknown> = {
        customer_id: Number(id),
        scheduled_at: new Date(bookScheduled).toISOString(),
        staff_user_id: bookStaff === "" ? null : Number(bookStaff),
        notes: bookNotes.trim() === "" ? null : bookNotes.trim(),
        services: lines,
        deposit_paid: depositRequired ? bookDeposit : false,
      };
      if (bookEnds.trim() !== "") {
        payload.ends_at = new Date(bookEnds).toISOString();
      }
      const res = await spaFetch("/appointments", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setBookErr(firstErrorMessage(b, "Booking failed."));
        return;
      }
      setBookOpen(false);
      await reload();
    } catch {
      setBookErr("Could not reach the server.");
    } finally {
      setBookBusy(false);
    }
  }

  const nextAppt = data?.nextAppointment as UnknownRec | undefined;
  const nextId = nextAppt ? Number(nextAppt.id) : 0;

  const [editOpen, setEditOpen] = useState(false);
  const [editScheduled, setEditScheduled] = useState("");
  const [editEnds, setEditEnds] = useState("");
  const [editStaff, setEditStaff] = useState("");
  const [editNotes, setEditNotes] = useState("");
  const [editLines, setEditLines] = useState<{ service_id: string; quantity: string }[]>([]);
  const [editBusy, setEditBusy] = useState(false);
  const [editErr, setEditErr] = useState<string | null>(null);

  useEffect(() => {
    if (!editOpen || !nextAppt) {
      return;
    }
    setEditErr(null);
    setEditScheduled(scheduleToLocalInput(nextAppt.scheduled_at));
    setEditEnds(nextAppt.ends_at ? scheduleToLocalInput(nextAppt.ends_at) : "");
    setEditStaff(String(nextAppt.staff_user_id ?? ""));
    setEditNotes(String(nextAppt.notes ?? ""));
    const lines = relServiceLinesForEdit(nextAppt);
    setEditLines(
      lines.length
        ? lines.map((l) => ({ service_id: String(l.service_id), quantity: String(l.quantity) }))
        : [{ service_id: catalogServices[0] ? String(catalogServices[0].id) : "", quantity: "1" }],
    );
  }, [editOpen, nextAppt, catalogServices]);

  async function submitEditNext(e: React.FormEvent) {
    e.preventDefault();
    if (!nextId || !editScheduled) {
      return;
    }
    const lines = editLines
      .map((row) => ({
        service_id: Number(row.service_id),
        quantity: Math.max(1, Math.floor(Number(row.quantity) || 1)),
      }))
      .filter((row) => Number.isFinite(row.service_id) && row.service_id > 0);
    if (lines.length < 1) {
      setEditErr("Add at least one service.");
      return;
    }
    setEditBusy(true);
    setEditErr(null);
    try {
      const payload: Record<string, unknown> = {
        scheduled_at: new Date(editScheduled).toISOString(),
        staff_user_id: editStaff === "" ? null : Number(editStaff),
        notes: editNotes.trim() === "" ? null : editNotes.trim(),
        services: lines,
      };
      payload.ends_at = editEnds.trim() === "" ? null : new Date(editEnds).toISOString();
      const res = await spaFetch(`/appointments/${nextId}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setEditErr(firstErrorMessage(b, "Update failed."));
        return;
      }
      setEditOpen(false);
      await reload();
    } catch {
      setEditErr("Could not reach the server.");
    } finally {
      setEditBusy(false);
    }
  }

  const [statusBusy, setStatusBusy] = useState<string | null>(null);
  const [statusErr, setStatusErr] = useState<string | null>(null);

  async function patchNextStatus(body: Record<string, unknown>, label: string) {
    if (!nextId) {
      return;
    }
    setStatusErr(null);
    setStatusBusy(label);
    try {
      const res = await spaFetch(`/appointments/${nextId}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setStatusErr(firstErrorMessage(b, "Update failed."));
        return;
      }
      await reload();
    } catch {
      setStatusErr("Could not reach the server.");
    } finally {
      setStatusBusy(null);
    }
  }

  const [cancelOpen, setCancelOpen] = useState(false);
  const [cancelReason, setCancelReason] = useState("");
  const [salesFollowUp, setSalesFollowUp] = useState(false);
  const [cancelBusy, setCancelBusy] = useState(false);
  const [cancelErr, setCancelErr] = useState<string | null>(null);

  async function submitCancel(e: React.FormEvent) {
    e.preventDefault();
    if (!nextId) {
      return;
    }
    const reason = cancelReason.trim();
    if (!reason) {
      setCancelErr("A cancellation reason is required.");
      return;
    }
    setCancelBusy(true);
    setCancelErr(null);
    try {
      const res = await spaFetch(`/appointments/${nextId}`, {
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
        setCancelErr(firstErrorMessage(b, "Cancellation failed."));
        return;
      }
      setCancelOpen(false);
      setCancelReason("");
      setSalesFollowUp(false);
      await reload();
    } catch {
      setCancelErr("Could not reach the server.");
    } finally {
      setCancelBusy(false);
    }
  }

  const [retailAppt, setRetailAppt] = useState("");
  const [retailSvc, setRetailSvc] = useState("");
  const [retailQty, setRetailQty] = useState("1");
  const [retailBusy, setRetailBusy] = useState(false);
  const [retailErr, setRetailErr] = useState<string | null>(null);
  const [retailOk, setRetailOk] = useState<string | null>(null);

  useEffect(() => {
    if (completedVisits.length && !retailAppt) {
      setRetailAppt(String(completedVisits[0].id));
    }
  }, [completedVisits, retailAppt]);

  useEffect(() => {
    if (retailServices.length && !retailSvc) {
      setRetailSvc(String(retailServices[0].id));
    }
  }, [retailServices, retailSvc]);

  async function submitRetail(e: React.FormEvent) {
    e.preventDefault();
    const apptId = Number(retailAppt);
    const svcId = Number(retailSvc);
    const qty = Math.max(1, Math.floor(Number(retailQty) || 1));
    if (!Number.isFinite(apptId) || apptId < 1 || !Number.isFinite(svcId) || svcId < 1) {
      setRetailErr("Choose a visit and a product.");
      return;
    }
    setRetailBusy(true);
    setRetailErr(null);
    setRetailOk(null);
    try {
      const res = await spaFetch(`/appointments/${apptId}/retail-lines`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ service_id: svcId, quantity: qty }),
      });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setRetailErr(firstErrorMessage(b, "Could not add retail line."));
        return;
      }
      setRetailOk("Retail line added.");
      await reload();
    } catch {
      setRetailErr("Could not reach the server.");
    } finally {
      setRetailBusy(false);
    }
  }

  return (
    <SpaPageFrame title={title} loading={loading} error={error}>
      <p className="mb-6 text-sm">
        <Link href="/customers" className="text-pink-700 hover:underline">
          ← Customers
        </Link>
        {" · "}
        <Link href={`/customers/${id}/edit`} className="text-pink-700 hover:underline">
          Edit
        </Link>
        {" · "}
        <Link href={`/customers/${id}/timeline`} className="text-pink-700 hover:underline">
          Timeline
        </Link>
        {" · "}
        <Link href={`/appointments?customer_id=${id}`} className="text-pink-700 hover:underline">
          Appointments
        </Link>
        {" · "}
        <Link href={`/sales/pipeline?customer_id=${id}`} className="text-pink-700 hover:underline">
          Pipeline
        </Link>
      </p>

      <Dialog
        open={contactField !== null}
        title={
          contactField === "email"
            ? "Update email"
            : contactField === "phone"
              ? "Update phone"
              : "Update date of birth"
        }
        onClose={() => (contactBusy ? null : setContactField(null))}
      >
        <form className="space-y-3" onSubmit={saveContact}>
          {contactField === "date_of_birth" ? (
            <label className="block text-sm">
              <span className="font-medium text-slate-700">Date of birth</span>
              <input
                type="date"
                className="mt-1 w-full rounded border border-slate-300 px-2 py-2 text-sm"
                value={contactValue}
                onChange={(ev) => setContactValue(ev.target.value)}
              />
            </label>
          ) : (
            <label className="block text-sm">
              <span className="font-medium text-slate-700">{contactField === "email" ? "Email" : "Phone"}</span>
              <input
                type={contactField === "email" ? "email" : "text"}
                className="mt-1 w-full rounded border border-slate-300 px-2 py-2 text-sm"
                value={contactValue}
                onChange={(ev) => setContactValue(ev.target.value)}
              />
            </label>
          )}
          {contactErr ? <p className="text-sm text-rose-600">{contactErr}</p> : null}
          <div className="flex justify-end gap-2">
            <button
              type="button"
              className="rounded border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50"
              disabled={contactBusy}
              onClick={() => setContactField(null)}
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={contactBusy}
              className="rounded bg-pink-600 px-3 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-50"
            >
              {contactBusy ? "Saving…" : "Save"}
            </button>
          </div>
        </form>
      </Dialog>

      <Dialog open={bookOpen} title="Book appointment" onClose={() => (bookBusy ? null : setBookOpen(false))}>
        <form className="space-y-3" onSubmit={submitBook}>
          <label className="block text-sm">
            <span className="font-medium text-slate-700">Start</span>
            <input
              type="datetime-local"
              required
              className="mt-1 w-full rounded border border-slate-300 px-2 py-2 text-sm"
              value={bookScheduled}
              onChange={(ev) => setBookScheduled(ev.target.value)}
            />
          </label>
          <label className="block text-sm">
            <span className="font-medium text-slate-700">End (optional)</span>
            <input
              type="datetime-local"
              className="mt-1 w-full rounded border border-slate-300 px-2 py-2 text-sm"
              value={bookEnds}
              onChange={(ev) => setBookEnds(ev.target.value)}
            />
          </label>
          <label className="block text-sm">
            <span className="font-medium text-slate-700">Staff</span>
            <select
              className="mt-1 w-full rounded border border-slate-300 px-2 py-2 text-sm"
              value={bookStaff}
              onChange={(ev) => setBookStaff(ev.target.value)}
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
            <span className="font-medium text-slate-700">Notes</span>
            <textarea
              rows={2}
              className="mt-1 w-full rounded border border-slate-300 px-2 py-2 text-sm"
              value={bookNotes}
              onChange={(ev) => setBookNotes(ev.target.value)}
            />
          </label>
          {depositRequired ? (
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={bookDeposit} onChange={(ev) => setBookDeposit(ev.target.checked)} />
              <span>Deposit collected / confirmed</span>
            </label>
          ) : null}
          <div>
            <p className="text-sm font-medium text-slate-700">Services</p>
            <div className="mt-2 space-y-2">
              {bookLines.map((row, idx) => (
                <div key={idx} className="flex flex-wrap gap-2">
                  <select
                    className="min-w-[160px] flex-1 rounded border border-slate-300 px-2 py-1 text-sm"
                    value={row.service_id}
                    onChange={(ev) => {
                      const next = [...bookLines];
                      next[idx] = { ...next[idx], service_id: ev.target.value };
                      setBookLines(next);
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
                    className="w-20 rounded border border-slate-300 px-2 py-1 text-sm"
                    value={row.quantity}
                    onChange={(ev) => {
                      const next = [...bookLines];
                      next[idx] = { ...next[idx], quantity: ev.target.value };
                      setBookLines(next);
                    }}
                  />
                </div>
              ))}
            </div>
            <button
              type="button"
              className="mt-2 text-sm font-medium text-pink-700 hover:underline"
              onClick={() => setBookLines([...bookLines, { service_id: "", quantity: "1" }])}
            >
              + Add line
            </button>
          </div>
          {bookErr ? <p className="text-sm text-rose-600">{bookErr}</p> : null}
          <div className="flex justify-end gap-2 pt-2">
            <button
              type="button"
              className="rounded border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50"
              disabled={bookBusy}
              onClick={() => setBookOpen(false)}
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={bookBusy}
              className="rounded bg-pink-600 px-3 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-50"
            >
              {bookBusy ? "Booking…" : "Book"}
            </button>
          </div>
        </form>
      </Dialog>

      <Dialog open={editOpen} title="Edit next appointment" onClose={() => (editBusy ? null : setEditOpen(false))}>
        <form className="space-y-3" onSubmit={submitEditNext}>
          <label className="block text-sm">
            <span className="font-medium text-slate-700">Start</span>
            <input
              type="datetime-local"
              required
              className="mt-1 w-full rounded border border-slate-300 px-2 py-2 text-sm"
              value={editScheduled}
              onChange={(ev) => setEditScheduled(ev.target.value)}
            />
          </label>
          <label className="block text-sm">
            <span className="font-medium text-slate-700">End (optional)</span>
            <input
              type="datetime-local"
              className="mt-1 w-full rounded border border-slate-300 px-2 py-2 text-sm"
              value={editEnds}
              onChange={(ev) => setEditEnds(ev.target.value)}
            />
          </label>
          <label className="block text-sm">
            <span className="font-medium text-slate-700">Staff</span>
            <select
              className="mt-1 w-full rounded border border-slate-300 px-2 py-2 text-sm"
              value={editStaff}
              onChange={(ev) => setEditStaff(ev.target.value)}
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
            <span className="font-medium text-slate-700">Notes</span>
            <textarea
              rows={2}
              className="mt-1 w-full rounded border border-slate-300 px-2 py-2 text-sm"
              value={editNotes}
              onChange={(ev) => setEditNotes(ev.target.value)}
            />
          </label>
          <div>
            <p className="text-sm font-medium text-slate-700">Services</p>
            <div className="mt-2 space-y-2">
              {editLines.map((row, idx) => (
                <div key={idx} className="flex flex-wrap gap-2">
                  <select
                    className="min-w-[160px] flex-1 rounded border border-slate-300 px-2 py-1 text-sm"
                    value={row.service_id}
                    onChange={(ev) => {
                      const next = [...editLines];
                      next[idx] = { ...next[idx], service_id: ev.target.value };
                      setEditLines(next);
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
                    className="w-20 rounded border border-slate-300 px-2 py-1 text-sm"
                    value={row.quantity}
                    onChange={(ev) => {
                      const next = [...editLines];
                      next[idx] = { ...next[idx], quantity: ev.target.value };
                      setEditLines(next);
                    }}
                  />
                </div>
              ))}
            </div>
            <button
              type="button"
              className="mt-2 text-sm font-medium text-pink-700 hover:underline"
              onClick={() => setEditLines([...editLines, { service_id: "", quantity: "1" }])}
            >
              + Add line
            </button>
          </div>
          {editErr ? <p className="text-sm text-rose-600">{editErr}</p> : null}
          <div className="flex justify-end gap-2 pt-2">
            <button
              type="button"
              className="rounded border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50"
              disabled={editBusy}
              onClick={() => setEditOpen(false)}
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={editBusy}
              className="rounded bg-pink-600 px-3 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-50"
            >
              {editBusy ? "Saving…" : "Save changes"}
            </button>
          </div>
        </form>
      </Dialog>

      <Dialog
        open={cancelOpen}
        title="Cancel appointment"
        onClose={() => (cancelBusy ? null : setCancelOpen(false))}
      >
        <form className="space-y-3" onSubmit={submitCancel}>
          <label className="block text-sm">
            <span className="font-medium text-slate-700">Reason</span>
            <textarea
              required
              rows={3}
              className="mt-1 w-full rounded border border-slate-300 px-2 py-2 text-sm"
              value={cancelReason}
              onChange={(ev) => setCancelReason(ev.target.value)}
            />
          </label>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={salesFollowUp} onChange={(ev) => setSalesFollowUp(ev.target.checked)} />
            <span>Sales follow-up needed</span>
          </label>
          {cancelErr ? <p className="text-sm text-rose-600">{cancelErr}</p> : null}
          <div className="flex justify-end gap-2">
            <button
              type="button"
              className="rounded border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50"
              disabled={cancelBusy}
              onClick={() => setCancelOpen(false)}
            >
              Back
            </button>
            <button
              type="submit"
              disabled={cancelBusy}
              className="rounded bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50"
            >
              {cancelBusy ? "Cancelling…" : "Confirm cancel"}
            </button>
          </div>
        </form>
      </Dialog>

      {c ? (
        <div className="space-y-8">
          <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <div className="flex items-start justify-between gap-2">
                <p className="text-xs uppercase tracking-wide text-slate-500">Email</p>
                <button
                  type="button"
                  className="text-xs font-medium text-pink-700 hover:underline"
                  onClick={() => openContact("email")}
                >
                  Change
                </button>
              </div>
              <p className="mt-1 font-medium text-slate-900">{String(c.email ?? "—")}</p>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <div className="flex items-start justify-between gap-2">
                <p className="text-xs uppercase tracking-wide text-slate-500">Phone</p>
                <button
                  type="button"
                  className="text-xs font-medium text-pink-700 hover:underline"
                  onClick={() => openContact("phone")}
                >
                  Change
                </button>
              </div>
              <p className="mt-1 font-medium text-slate-900">{String(c.phone ?? "—")}</p>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <div className="flex items-start justify-between gap-2">
                <p className="text-xs uppercase tracking-wide text-slate-500">Date of birth</p>
                <button
                  type="button"
                  className="text-xs font-medium text-pink-700 hover:underline"
                  onClick={() => openContact("date_of_birth")}
                >
                  Change
                </button>
              </div>
              <p className="mt-1 font-medium text-slate-900">{dOnly(c.date_of_birth as string | undefined)}</p>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <p className="text-xs uppercase tracking-wide text-slate-500">Total paid</p>
              <p className="mt-1 font-medium text-slate-900">{money(data?.totalSpent ?? 0)}</p>
              <p className="mt-1 text-xs text-slate-500">Completed appointments (all time).</p>
            </div>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-2">
              <h2 className="text-lg font-semibold text-slate-900">Address</h2>
              <Link
                href={`/customers/${id}/edit`}
                className="text-sm font-medium text-pink-700 hover:underline"
              >
                Edit
              </Link>
            </div>
            <p className="mt-2 whitespace-pre-line text-sm text-slate-800">
              {(() => {
                const t = formatCustomerAddress(c).trim();
                return t || "—";
              })()}
            </p>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <h2 className="text-lg font-semibold text-slate-900">Next appointment</h2>
              <button
                type="button"
                className="rounded-md bg-pink-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-pink-700"
                onClick={() => setBookOpen(true)}
              >
                Book appointment
              </button>
            </div>
            <div className="mt-4">
              {data?.nextAppointment ? (
                <div className="rounded-lg border border-pink-200 bg-pink-50 px-3 py-3">
                  <p className="font-medium text-slate-900">{dt((data.nextAppointment as UnknownRec).scheduled_at)}</p>
                  <p className="mt-1 text-xs capitalize text-slate-700">
                    {(data.nextAppointment as UnknownRec).status as string}
                  </p>
                  <p className="mt-1 text-xs text-slate-500">Staff: {staffName(data.nextAppointment as UnknownRec)}</p>
                  <p className="mt-1 text-xs text-slate-500">Services: {svcNames(data.nextAppointment as UnknownRec)}</p>
                  {statusErr ? <p className="mt-2 text-sm text-rose-600">{statusErr}</p> : null}
                  <div className="mt-3 flex flex-wrap gap-2">
                    <button
                      type="button"
                      disabled={statusBusy !== null}
                      className="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold hover:bg-slate-50 disabled:opacity-50"
                      onClick={() => setEditOpen(true)}
                    >
                      Edit details
                    </button>
                    <button
                      type="button"
                      disabled={statusBusy !== null}
                      className="rounded bg-emerald-600 px-2 py-1 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                      onClick={() => void patchNextStatus({ status: "completed" }, "complete")}
                    >
                      {statusBusy === "complete" ? "…" : "Mark completed"}
                    </button>
                    <button
                      type="button"
                      disabled={statusBusy !== null}
                      className="rounded bg-amber-600 px-2 py-1 text-xs font-semibold text-white hover:bg-amber-700 disabled:opacity-50"
                      onClick={() => void patchNextStatus({ status: "no_show" }, "noshow")}
                    >
                      {statusBusy === "noshow" ? "…" : "No-show"}
                    </button>
                    <button
                      type="button"
                      disabled={statusBusy !== null}
                      className="rounded bg-rose-600 px-2 py-1 text-xs font-semibold text-white hover:bg-rose-700 disabled:opacity-50"
                      onClick={() => {
                        setCancelErr(null);
                        setCancelOpen(true);
                      }}
                    >
                      Cancel
                    </button>
                  </div>
                </div>
              ) : (
                <p className="text-sm text-slate-500">No upcoming booked appointment.</p>
              )}
            </div>
          </section>

          {completedVisits.length > 0 && retailServices.length > 0 ? (
            <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <h2 className="text-lg font-semibold text-slate-900">Retail on completed visit</h2>
              <p className="mt-1 text-xs text-slate-500">Add a retail line to a completed visit (inventory rules apply).</p>
              <form className="mt-4 flex flex-wrap items-end gap-3" onSubmit={submitRetail}>
                <label className="text-sm">
                  <span className="font-medium text-slate-700">Visit</span>
                  <select
                    className="mt-1 block min-w-[200px] rounded border border-slate-300 px-2 py-1.5 text-sm"
                    value={retailAppt}
                    onChange={(ev) => setRetailAppt(ev.target.value)}
                  >
                    {completedVisits.map((a) => (
                      <option key={String(a.id)} value={String(a.id)}>
                        {dt(a.scheduled_at)} (#{String(a.id)})
                      </option>
                    ))}
                  </select>
                </label>
                <label className="text-sm">
                  <span className="font-medium text-slate-700">Product</span>
                  <select
                    className="mt-1 block min-w-[180px] rounded border border-slate-300 px-2 py-1.5 text-sm"
                    value={retailSvc}
                    onChange={(ev) => setRetailSvc(ev.target.value)}
                  >
                    {retailServices.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="text-sm">
                  <span className="font-medium text-slate-700">Qty</span>
                  <input
                    type="number"
                    min={1}
                    className="mt-1 w-20 rounded border border-slate-300 px-2 py-1.5 text-sm"
                    value={retailQty}
                    onChange={(ev) => setRetailQty(ev.target.value)}
                  />
                </label>
                <button
                  type="submit"
                  disabled={retailBusy}
                  className="rounded bg-slate-800 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-900 disabled:opacity-50"
                >
                  {retailBusy ? "Adding…" : "Add line"}
                </button>
              </form>
              {retailErr ? <p className="mt-2 text-sm text-rose-600">{retailErr}</p> : null}
              {retailOk ? <p className="mt-2 text-sm text-emerald-700">{retailOk}</p> : null}
            </section>
          ) : null}

          <div className="grid gap-6 lg:grid-cols-2">
            <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <h2 className="text-lg font-semibold text-slate-900">Booked appointments</h2>
              <ul className="mt-3 space-y-2 text-sm">
                {(data?.bookedAppointments as unknown[] | undefined)?.length ? (
                  (data!.bookedAppointments as unknown[]).map((row) => {
                    const a = row as UnknownRec;
                    return (
                      <li key={String(a.id)} className="rounded border border-slate-100 bg-slate-50/80 px-2 py-2">
                        <span className="font-medium text-slate-800">{dt(a.scheduled_at)}</span>
                        <span className="ml-2 text-xs text-slate-600">{String(a.status)}</span>
                        <p className="text-xs text-slate-500">
                          {staffName(a)} · {svcNames(a)}
                        </p>
                      </li>
                    );
                  })
                ) : (
                  <li className="text-slate-500">None.</li>
                )}
              </ul>
            </section>
            <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <h2 className="text-lg font-semibold text-slate-900">Recent past visits</h2>
              <ul className="mt-3 space-y-2 text-sm">
                {(data?.pastAppointments as unknown[] | undefined)?.length ? (
                  (data!.pastAppointments as unknown[]).slice(0, 12).map((row) => {
                    const a = row as UnknownRec;
                    return (
                      <li key={String(a.id)} className="rounded border border-slate-100 px-2 py-2">
                        <span className="font-medium text-slate-800">{dt(a.scheduled_at)}</span>
                        <span className="ml-2 text-xs capitalize text-slate-600">{String(a.status)}</span>
                      </li>
                    );
                  })
                ) : (
                  <li className="text-slate-500">None.</li>
                )}
              </ul>
            </section>
          </div>

          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-center justify-between gap-2">
              <h2 className="text-lg font-semibold text-slate-900">Pipeline</h2>
              <Link href={`/sales/pipeline?customer_id=${id}`} className="text-sm font-medium text-pink-700 hover:underline">
                View in pipeline
              </Link>
            </div>
            <ul className="mt-3 divide-y divide-slate-100 text-sm">
              {opportunities.length ? (
                opportunities.map((o) => {
                  const r = o as UnknownRec;
                  return (
                    <li key={String(r.id)} className="py-2">
                      <span className="font-medium text-slate-900">{String(r.title)}</span>
                      <span className="ml-2 text-slate-600">{money(r.amount)}</span>
                      <span className="ml-2 text-xs uppercase text-slate-500">{String(r.stage)}</span>
                      <p className="text-xs text-slate-500">Close {dOnly(r.expected_close_date as string)}</p>
                    </li>
                  );
                })
              ) : (
                <li className="py-4 text-slate-500">No opportunities yet.</li>
              )}
            </ul>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-center justify-between gap-2">
              <h2 className="text-lg font-semibold text-slate-900">Open tasks</h2>
              <Link href={`/tasks?view=all_pending&customer_id=${id}`} className="text-sm font-medium text-pink-700 hover:underline">
                Tasks
              </Link>
            </div>
            <ul className="mt-3 space-y-2 text-sm">
              {tasks.length ? (
                tasks.slice(0, 15).map((t) => {
                  const r = t as UnknownRec;
                  const assignee = (r.assigned_to ?? r.assignedTo) as { name?: string } | undefined;
                  return (
                    <li key={String(r.id)} className="flex flex-wrap justify-between gap-2 border-b border-slate-50 py-1">
                      <span className="font-medium text-slate-800">{String(r.title)}</span>
                      <span className="text-xs text-slate-500">
                        {dt(r.due_at)} · {assignee?.name ?? "—"}
                      </span>
                    </li>
                  );
                })
              ) : (
                <li className="text-slate-500">No tasks.</li>
              )}
            </ul>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">Recent activity</h2>
            <p className="mt-1 text-xs text-slate-500">Newest first · see full timeline for filters.</p>
            <ul className="mt-3 space-y-2 text-sm">
              {activities.length ? (
                activities.slice(0, 12).map((act) => {
                  const r = act as UnknownRec;
                  const u = r.user as { name?: string } | undefined;
                  return (
                    <li key={String(r.id)} className="border-b border-slate-50 py-1.5">
                      <p className="text-slate-800">{String(r.summary)}</p>
                      <p className="text-xs text-slate-500">
                        {dt(r.created_at)} · {u?.name ?? "System"}
                      </p>
                    </li>
                  );
                })
              ) : (
                <li className="text-slate-500">No activity rows.</li>
              )}
            </ul>
            <p className="mt-3">
              <Link href={`/customers/${id}/timeline`} className="text-sm font-medium text-pink-700 hover:underline">
                Open timeline →
              </Link>
            </p>
          </section>

          <div className="grid gap-6 lg:grid-cols-2">
            <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <h2 className="text-lg font-semibold text-slate-900">Current memberships</h2>
              <ul className="mt-3 space-y-2 text-sm">
                {(data?.currentMemberships as unknown[] | undefined)?.length ? (
                  (data!.currentMemberships as unknown[]).map((m, i) => {
                    const row = m as UnknownRec;
                    const mem = row.membership as UnknownRec | undefined;
                    const name = mem?.name ?? "Membership";
                    return (
                      <li key={String(row.id ?? i)} className="rounded border border-emerald-100 bg-emerald-50/40 px-2 py-2">
                        <span className="font-medium">{String(name)}</span>
                        <span className="ml-2 text-xs text-slate-600">{String(row.status)}</span>
                      </li>
                    );
                  })
                ) : (
                  <li className="text-slate-500">None active.</li>
                )}
              </ul>
            </section>
            <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <h2 className="text-lg font-semibold text-slate-900">Past memberships</h2>
              <ul className="mt-3 space-y-2 text-sm text-slate-700">
                {(data?.pastMemberships as unknown[] | undefined)?.length ? (
                  (data!.pastMemberships as unknown[]).map((m, i) => {
                    const row = m as UnknownRec;
                    const mem = row.membership as UnknownRec | undefined;
                    return (
                      <li key={String(row.id ?? i)} className="border-b border-slate-100 py-1">
                        {String(mem?.name ?? "Plan")} · {String(row.status)}
                      </li>
                    );
                  })
                ) : (
                  <li className="text-slate-500">None.</li>
                )}
              </ul>
            </section>
          </div>

          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">Payment history (recent)</h2>
            <div className="mt-3 overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead className="border-b border-slate-200 text-xs text-slate-500">
                  <tr>
                    <th className="py-2 pr-3">Date</th>
                    <th className="py-2 pr-3">Amount</th>
                    <th className="py-2 pr-3">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {(data?.paymentHistory as unknown[] | undefined)?.length ? (
                    (data!.paymentHistory as unknown[]).map((p) => {
                      const r = p as UnknownRec;
                      return (
                        <tr key={String(r.id)} className="border-b border-slate-100">
                          <td className="py-2 pr-3">{dt(r.scheduled_at)}</td>
                          <td className="py-2 pr-3">{money(r.total_amount)}</td>
                          <td className="py-2 pr-3 capitalize">{String(r.status)}</td>
                        </tr>
                      );
                    })
                  ) : (
                    <tr>
                      <td colSpan={3} className="py-4 text-slate-500">
                        No completed payments in recent list.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">Services received (totals)</h2>
            <div className="mt-3 overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead className="border-b border-slate-200 text-xs text-slate-500">
                  <tr>
                    <th className="py-2 pr-3">Service</th>
                    <th className="py-2 pr-3">Visits</th>
                    <th className="py-2 pr-3">Units</th>
                    <th className="py-2 pr-3">Spent</th>
                  </tr>
                </thead>
                <tbody>
                  {(data?.servicesReceived as unknown[] | undefined)?.length ? (
                    (data!.servicesReceived as unknown[]).map((s, i) => {
                      const r = s as UnknownRec;
                      return (
                        <tr key={String(r.service_name ?? i)} className="border-b border-slate-100">
                          <td className="py-2 pr-3 font-medium">{String(r.service_name)}</td>
                          <td className="py-2 pr-3">{String(r.visits ?? "")}</td>
                          <td className="py-2 pr-3">{String(r.total_quantity ?? "")}</td>
                          <td className="py-2 pr-3">{money(r.total_spent)}</td>
                        </tr>
                      );
                    })
                  ) : (
                    <tr>
                      <td colSpan={4} className="py-4 text-slate-500">
                        No service history yet.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </section>

          {c.notes ? (
            <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <h2 className="text-lg font-semibold text-slate-900">Notes</h2>
              <p className="mt-2 whitespace-pre-wrap text-sm text-slate-700">{String(c.notes)}</p>
            </section>
          ) : null}
        </div>
      ) : null}
    </SpaPageFrame>
  );
}
