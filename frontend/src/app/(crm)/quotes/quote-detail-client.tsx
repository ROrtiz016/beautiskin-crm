"use client";

import { SpaPageFrame } from "@/components/spa-page-frame";
import { useSpaGet } from "@/hooks/use-spa-get";
import { firstErrorMessage } from "@/lib/laravel-form-errors";
import { spaFetch } from "@/lib/spa-fetch";
import Link from "next/link";
import { useParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useState } from "react";

type CustomerBrief = { id?: number; first_name: string; last_name: string; email?: string | null; phone?: string | null };

type QuoteLine = {
  id: number;
  line_kind: string;
  label: string;
  quantity: number;
  unit_price: string;
  line_total: string;
};

type QuoteModel = {
  id: number;
  customer_id: number;
  title?: string | null;
  status: string;
  notes?: string | null;
  valid_until?: string | null;
  discount_amount?: string;
  tax_amount?: string;
  subtotal_amount?: string;
  total_amount?: string;
  lines?: QuoteLine[];
};

type ServiceOpt = { id: number; name: string; price?: string };
type PackageOpt = { id: number; name: string; package_price?: string };
type ApptOpt = { id: number; scheduled_at: string | null; status: string; quote_id?: number | null };

type QuoteShowPayload = {
  quote: QuoteModel & { customer?: CustomerBrief };
  services: ServiceOpt[];
  packages: PackageOpt[];
  linkableAppointments: ApptOpt[];
};

const STATUSES = ["draft", "sent", "accepted", "declined", "expired"] as const;

function money(v: string | number | undefined | null): string {
  return Number(v ?? 0).toFixed(2);
}

function ymd(raw: string | null | undefined): string {
  if (!raw) {
    return "";
  }
  return raw.length >= 10 ? raw.slice(0, 10) : raw;
}

function QuoteDetailInner() {
  const { id } = useParams<{ id: string }>();
  const path = `/spa/quotes/${id}`;
  const { data, error, loading, reload } = useSpaGet<QuoteShowPayload>(path);

  const quote = data?.quote;
  const cust = quote?.customer;

  const [title, setTitle] = useState("");
  const [validUntil, setValidUntil] = useState("");
  const [discount, setDiscount] = useState("");
  const [tax, setTax] = useState("");
  const [notes, setNotes] = useState("");
  const [status, setStatus] = useState("draft");
  const [headerErr, setHeaderErr] = useState<string | null>(null);
  const [headerBusy, setHeaderBusy] = useState(false);

  const [statusBusy, setStatusBusy] = useState(false);
  const [statusErr, setStatusErr] = useState<string | null>(null);

  const [lineKind, setLineKind] = useState<"service" | "package" | "custom">("service");
  const [svcId, setSvcId] = useState("");
  const [pkgId, setPkgId] = useState("");
  const [customLabel, setCustomLabel] = useState("");
  const [customUnit, setCustomUnit] = useState("");
  const [lineQty, setLineQty] = useState("1");
  const [lineErr, setLineErr] = useState<string | null>(null);
  const [lineBusy, setLineBusy] = useState(false);

  const [linkAppt, setLinkAppt] = useState("");
  const [linkErr, setLinkErr] = useState<string | null>(null);
  const [linkBusy, setLinkBusy] = useState(false);

  useEffect(() => {
    if (!quote) {
      return;
    }
    setTitle(quote.title ?? "");
    setValidUntil(ymd(quote.valid_until as string | undefined));
    setDiscount(money(quote.discount_amount));
    setTax(money(quote.tax_amount));
    setNotes(quote.notes ?? "");
    setStatus(quote.status);
  }, [quote]);

  const saveHeader = useCallback(async () => {
    if (!id) {
      return;
    }
    setHeaderErr(null);
    setHeaderBusy(true);
    try {
      const res = await spaFetch(`/quotes/${id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          title: title || null,
          valid_until: validUntil || null,
          discount_amount: parseFloat(discount) || 0,
          tax_amount: parseFloat(tax) || 0,
          notes: notes || null,
        }),
      });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setHeaderErr(firstErrorMessage(b, "Could not save."));
        return;
      }
      await reload();
    } catch {
      setHeaderErr("Could not reach the server.");
    } finally {
      setHeaderBusy(false);
    }
  }, [discount, id, notes, reload, tax, title, validUntil]);

  const saveStatus = useCallback(async () => {
    if (!id) {
      return;
    }
    setStatusErr(null);
    setStatusBusy(true);
    try {
      const res = await spaFetch(`/quotes/${id}/status`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ status }),
      });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setStatusErr(firstErrorMessage(b, "Could not update status."));
        return;
      }
      await reload();
    } catch {
      setStatusErr("Could not reach the server.");
    } finally {
      setStatusBusy(false);
    }
  }, [id, reload, status]);

  const addLine = useCallback(async () => {
    if (!id) {
      return;
    }
    setLineErr(null);
    const qty = Math.max(1, parseInt(lineQty, 10) || 1);
    let body: Record<string, unknown> = { line_kind: lineKind, quantity: qty };
    if (lineKind === "service") {
      if (!svcId) {
        setLineErr("Choose a service.");
        return;
      }
      body.service_id = Number(svcId);
    } else if (lineKind === "package") {
      if (!pkgId) {
        setLineErr("Choose a package.");
        return;
      }
      body.treatment_package_id = Number(pkgId);
    } else {
      if (!customLabel.trim()) {
        setLineErr("Description is required for custom lines.");
        return;
      }
      const u = parseFloat(customUnit);
      if (!Number.isFinite(u) || u < 0) {
        setLineErr("Valid unit price required.");
        return;
      }
      body.label = customLabel.trim();
      body.unit_price = u;
    }
    setLineBusy(true);
    try {
      const res = await spaFetch(`/quotes/${id}/lines`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setLineErr(firstErrorMessage(b, "Could not add line."));
        return;
      }
      setCustomLabel("");
      setCustomUnit("");
      setLineQty("1");
      await reload();
    } catch {
      setLineErr("Could not reach the server.");
    } finally {
      setLineBusy(false);
    }
  }, [customLabel, customUnit, id, lineKind, lineQty, pkgId, reload, svcId]);

  const removeLine = useCallback(
    async (lineId: number) => {
      if (!window.confirm("Remove this line?")) {
        return;
      }
      try {
        const res = await spaFetch(`/quote-lines/${lineId}`, { method: "DELETE" });
        if (!res.ok) {
          return;
        }
        await reload();
      } catch {
        /* ignore */
      }
    },
    [reload],
  );

  const linkAppointment = useCallback(async () => {
    if (!id || !linkAppt) {
      return;
    }
    setLinkErr(null);
    setLinkBusy(true);
    try {
      const res = await spaFetch(`/quotes/${id}/link-appointment`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ appointment_id: Number(linkAppt) }),
      });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setLinkErr((b as { message?: string }).message ?? firstErrorMessage(b, "Could not link."));
        return;
      }
      setLinkAppt("");
      await reload();
    } catch {
      setLinkErr("Could not reach the server.");
    } finally {
      setLinkBusy(false);
    }
  }, [id, linkAppt, reload]);

  const titleText = quote
    ? quote.title
      ? `${quote.title} (#${quote.id})`
      : `Quote #${quote.id}`
    : "Quote";

  return (
    <SpaPageFrame title={titleText} loading={loading} error={error}>
      <p className="text-sm">
        <Link href="/quotes" className="text-pink-700 hover:underline">
          ← Quotes
        </Link>
      </p>

      {quote && cust ? (
        <div className="mt-6 space-y-6">
          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p className="text-sm text-slate-700">
              Customer:{" "}
              <Link href={`/customers/${quote.customer_id}`} className="font-medium text-pink-700 hover:underline">
                {cust.first_name} {cust.last_name}
              </Link>
              {cust.email ? <span className="text-slate-500"> · {cust.email}</span> : null}
            </p>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              <label className="text-xs font-medium text-slate-700">
                Title
                <input
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                  value={title}
                  onChange={(ev) => setTitle(ev.target.value)}
                />
              </label>
              <label className="text-xs font-medium text-slate-700">
                Valid until
                <input
                  type="date"
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                  value={validUntil}
                  onChange={(ev) => setValidUntil(ev.target.value)}
                />
              </label>
              <label className="text-xs font-medium text-slate-700">
                Discount ($)
                <input
                  type="number"
                  step="0.01"
                  min={0}
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                  value={discount}
                  onChange={(ev) => setDiscount(ev.target.value)}
                />
              </label>
              <label className="text-xs font-medium text-slate-700">
                Tax ($)
                <input
                  type="number"
                  step="0.01"
                  min={0}
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm"
                  value={tax}
                  onChange={(ev) => setTax(ev.target.value)}
                />
              </label>
            </div>
            <label className="mt-3 block text-xs font-medium text-slate-700">
              Notes
              <textarea
                rows={3}
                className="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm"
                value={notes}
                onChange={(ev) => setNotes(ev.target.value)}
              />
            </label>
            {headerErr ? <p className="mt-2 text-xs text-rose-600">{headerErr}</p> : null}
            <button
              type="button"
              disabled={headerBusy}
              onClick={() => void saveHeader()}
              className="mt-3 rounded-md bg-slate-800 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-900 disabled:opacity-50"
            >
              {headerBusy ? "Saving…" : "Save quote details"}
            </button>
          </div>

          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 className="text-sm font-semibold text-slate-900">Status</h2>
            <div className="mt-2 flex flex-wrap items-end gap-2">
              <select
                className="rounded border border-slate-300 px-2 py-1 text-sm"
                value={status}
                onChange={(ev) => setStatus(ev.target.value)}
              >
                {STATUSES.map((s) => (
                  <option key={s} value={s}>
                    {s}
                  </option>
                ))}
              </select>
              <button
                type="button"
                disabled={statusBusy}
                onClick={() => void saveStatus()}
                className="rounded-md bg-pink-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-50"
              >
                {statusBusy ? "Updating…" : "Update status"}
              </button>
            </div>
            {statusErr ? <p className="mt-2 text-xs text-rose-600">{statusErr}</p> : null}
          </div>

          <div className="rounded-xl border border-slate-200 bg-slate-50/80 p-4 shadow-sm">
            <h2 className="text-sm font-semibold text-slate-900">Totals</h2>
            <dl className="mt-2 grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
              <div>
                <dt className="text-xs text-slate-500">Subtotal</dt>
                <dd className="font-medium">${money(quote.subtotal_amount)}</dd>
              </div>
              <div>
                <dt className="text-xs text-slate-500">Discount</dt>
                <dd className="font-medium">${money(quote.discount_amount)}</dd>
              </div>
              <div>
                <dt className="text-xs text-slate-500">Tax</dt>
                <dd className="font-medium">${money(quote.tax_amount)}</dd>
              </div>
              <div>
                <dt className="text-xs text-slate-500">Total</dt>
                <dd className="font-semibold text-pink-800">${money(quote.total_amount)}</dd>
              </div>
            </dl>
          </div>

          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 className="text-sm font-semibold text-slate-900">Lines</h2>
            <div className="mt-3 overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead className="border-b text-xs uppercase text-slate-500">
                  <tr>
                    <th className="py-2 pr-4">Item</th>
                    <th className="py-2 pr-4">Qty</th>
                    <th className="py-2 pr-4">Unit</th>
                    <th className="py-2 pr-4">Line</th>
                    <th className="py-2" />
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {(quote.lines ?? []).map((ln) => (
                    <tr key={ln.id}>
                      <td className="py-2 pr-4">
                        <span className="text-xs text-slate-500">{ln.line_kind}</span>
                        <div className="font-medium text-slate-900">{ln.label}</div>
                      </td>
                      <td className="py-2 pr-4">{ln.quantity}</td>
                      <td className="py-2 pr-4">${money(ln.unit_price)}</td>
                      <td className="py-2 pr-4 font-medium">${money(ln.line_total)}</td>
                      <td className="py-2">
                        <button
                          type="button"
                          className="text-xs text-rose-600 hover:underline"
                          onClick={() => void removeLine(ln.id)}
                        >
                          Remove
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="mt-6 border-t border-slate-100 pt-4">
              <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-600">Add line</h3>
              <div className="mt-2 flex flex-wrap gap-3 text-xs">
                {(["service", "package", "custom"] as const).map((k) => (
                  <label key={k} className="flex items-center gap-1">
                    <input type="radio" name="lk" checked={lineKind === k} onChange={() => setLineKind(k)} />
                    {k}
                  </label>
                ))}
              </div>
              {lineKind === "service" ? (
                <div className="mt-2 flex flex-wrap gap-2">
                  <select
                    className="min-w-[200px] rounded border border-slate-300 px-2 py-1 text-sm"
                    value={svcId}
                    onChange={(ev) => setSvcId(ev.target.value)}
                  >
                    <option value="">Select service…</option>
                    {(data?.services ?? []).map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name} (${money(s.price)})
                      </option>
                    ))}
                  </select>
                </div>
              ) : null}
              {lineKind === "package" ? (
                <div className="mt-2 flex flex-wrap gap-2">
                  <select
                    className="min-w-[200px] rounded border border-slate-300 px-2 py-1 text-sm"
                    value={pkgId}
                    onChange={(ev) => setPkgId(ev.target.value)}
                  >
                    <option value="">Select package…</option>
                    {(data?.packages ?? []).map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.name} (${money(p.package_price)})
                      </option>
                    ))}
                  </select>
                </div>
              ) : null}
              {lineKind === "custom" ? (
                <div className="mt-2 flex flex-wrap gap-2">
                  <input
                    placeholder="Description"
                    className="min-w-[180px] flex-1 rounded border border-slate-300 px-2 py-1 text-sm"
                    value={customLabel}
                    onChange={(ev) => setCustomLabel(ev.target.value)}
                  />
                  <input
                    type="number"
                    step="0.01"
                    min={0}
                    placeholder="Unit price"
                    className="w-28 rounded border border-slate-300 px-2 py-1 text-sm"
                    value={customUnit}
                    onChange={(ev) => setCustomUnit(ev.target.value)}
                  />
                </div>
              ) : null}
              <label className="mt-2 block text-xs font-medium text-slate-700">
                Quantity
                <input
                  type="number"
                  min={1}
                  className="mt-1 w-24 rounded border border-slate-300 px-2 py-1 text-sm"
                  value={lineQty}
                  onChange={(ev) => setLineQty(ev.target.value)}
                />
              </label>
              {lineErr ? <p className="mt-2 text-xs text-rose-600">{lineErr}</p> : null}
              <button
                type="button"
                disabled={lineBusy}
                onClick={() => void addLine()}
                className="mt-3 rounded-md bg-pink-600 px-3 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-50"
              >
                {lineBusy ? "Adding…" : "Add line"}
              </button>
            </div>
          </div>

          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 className="text-sm font-semibold text-slate-900">Link appointment</h2>
            <p className="mt-1 text-xs text-slate-500">
              Attach this quote to a recent appointment for the same customer (sets appointment.quote_id).
            </p>
            <div className="mt-3 flex flex-wrap items-end gap-2">
              <select
                className="min-w-[240px] rounded border border-slate-300 px-2 py-1 text-sm"
                value={linkAppt}
                onChange={(ev) => setLinkAppt(ev.target.value)}
              >
                <option value="">Select appointment…</option>
                {(data?.linkableAppointments ?? []).map((a) => (
                  <option key={a.id} value={a.id}>
                    #{a.id} · {a.scheduled_at ? new Date(a.scheduled_at).toLocaleString() : "—"} · {a.status}
                    {a.quote_id ? ` · quote #${a.quote_id}` : ""}
                  </option>
                ))}
              </select>
              <button
                type="button"
                disabled={linkBusy || !linkAppt}
                onClick={() => void linkAppointment()}
                className="rounded-md bg-slate-800 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-900 disabled:opacity-50"
              >
                {linkBusy ? "Linking…" : "Link"}
              </button>
            </div>
            {linkErr ? <p className="mt-2 text-xs text-rose-600">{linkErr}</p> : null}
          </div>
        </div>
      ) : null}
    </SpaPageFrame>
  );
}

export function QuoteDetailClient() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-slate-600">Loading quote…</div>}>
      <QuoteDetailInner />
    </Suspense>
  );
}
