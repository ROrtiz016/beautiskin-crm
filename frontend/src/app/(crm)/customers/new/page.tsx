"use client";

import { fieldErrors, firstErrorMessage } from "@/lib/laravel-form-errors";
import { spaFetch } from "@/lib/spa-fetch";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";

type NewCustomerForm = {
  first_name: string;
  last_name: string;
  email: string | null;
  phone: string | null;
  date_of_birth: string | null;
  gender: string | null;
  notes: string | null;
};

export default function CustomerNewPage() {
  const router = useRouter();
  const [form, setForm] = useState<NewCustomerForm>({
    first_name: "",
    last_name: "",
    email: null,
    phone: null,
    date_of_birth: null,
    gender: null,
    notes: null,
  });
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [fieldErr, setFieldErr] = useState<Record<string, string>>({});
  const [pending, setPending] = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSubmitError(null);
    setFieldErr({});
    setPending(true);
    try {
      const res = await spaFetch("/customers", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          first_name: form.first_name,
          last_name: form.last_name,
          email: form.email || null,
          phone: form.phone || null,
          date_of_birth: form.date_of_birth || null,
          gender: form.gender || null,
          notes: form.notes || null,
        }),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) {
        setSubmitError(firstErrorMessage(body, "Could not create customer."));
        setFieldErr(fieldErrors(body));
        return;
      }
      const created = body as { id?: number };
      if (typeof created.id === "number") {
        router.push(`/customers/${created.id}`);
        router.refresh();
        return;
      }
      setSubmitError("Unexpected response from server.");
    } catch {
      setSubmitError("Could not reach the server.");
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">New customer</h1>
        <p className="mt-1 text-sm text-slate-600">Create a customer record.</p>
        <p className="mt-2 text-sm">
          <Link href="/customers" className="text-pink-700 hover:underline">
            ← Back to customers
          </Link>
        </p>
      </div>
      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <form className="space-y-4" onSubmit={onSubmit}>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-800">First name</label>
            <input
              required
              value={form.first_name}
              onChange={(ev) => setForm({ ...form, first_name: ev.target.value })}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
            {fieldErr.first_name ? <p className="mt-1 text-xs text-rose-600">{fieldErr.first_name}</p> : null}
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-800">Last name</label>
            <input
              required
              value={form.last_name}
              onChange={(ev) => setForm({ ...form, last_name: ev.target.value })}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
            {fieldErr.last_name ? <p className="mt-1 text-xs text-rose-600">{fieldErr.last_name}</p> : null}
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-800">Email</label>
            <input
              type="email"
              value={form.email ?? ""}
              onChange={(ev) => setForm({ ...form, email: ev.target.value || null })}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
            {fieldErr.email ? <p className="mt-1 text-xs text-rose-600">{fieldErr.email}</p> : null}
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-800">Phone</label>
            <input
              value={form.phone ?? ""}
              onChange={(ev) => setForm({ ...form, phone: ev.target.value || null })}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
            {fieldErr.phone ? <p className="mt-1 text-xs text-rose-600">{fieldErr.phone}</p> : null}
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-800">Date of birth</label>
            <input
              type="date"
              value={form.date_of_birth ?? ""}
              onChange={(ev) =>
                setForm({ ...form, date_of_birth: ev.target.value ? ev.target.value : null })
              }
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
            {fieldErr.date_of_birth ? <p className="mt-1 text-xs text-rose-600">{fieldErr.date_of_birth}</p> : null}
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-800">Gender</label>
            <input
              value={form.gender ?? ""}
              onChange={(ev) => setForm({ ...form, gender: ev.target.value || null })}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
            {fieldErr.gender ? <p className="mt-1 text-xs text-rose-600">{fieldErr.gender}</p> : null}
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-800">Notes</label>
            <textarea
              rows={4}
              value={form.notes ?? ""}
              onChange={(ev) => setForm({ ...form, notes: ev.target.value || null })}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
            {fieldErr.notes ? <p className="mt-1 text-xs text-rose-600">{fieldErr.notes}</p> : null}
          </div>
          {submitError ? <p className="text-sm text-rose-600">{submitError}</p> : null}
          <div className="flex gap-3 pt-2">
            <button
              type="submit"
              disabled={pending}
              className="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-60"
            >
              {pending ? "Creating…" : "Create customer"}
            </button>
            <Link
              href="/customers"
              className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
              Cancel
            </Link>
          </div>
        </form>
      </section>
    </div>
  );
}
