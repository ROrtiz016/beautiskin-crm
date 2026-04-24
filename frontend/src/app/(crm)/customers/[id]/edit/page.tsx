"use client";

import { fieldErrors, firstErrorMessage } from "@/lib/laravel-form-errors";
import { spaFetch } from "@/lib/spa-fetch";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useEffect, useState } from "react";

type CustomerForm = {
  id: number;
  first_name: string;
  last_name: string;
  email: string | null;
  phone: string | null;
  date_of_birth: string | null;
  gender: string | null;
  notes: string | null;
};

function dobInputValue(raw: string | null | undefined): string {
  if (!raw) {
    return "";
  }
  return raw.length >= 10 ? raw.slice(0, 10) : raw;
}

export default function CustomerEditPage() {
  const router = useRouter();
  const { id } = useParams<{ id: string }>();
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [form, setForm] = useState<CustomerForm | null>(null);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [fieldErr, setFieldErr] = useState<Record<string, string>>({});
  const [pending, setPending] = useState(false);
  const [deletePending, setDeletePending] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      setLoading(true);
      setLoadError(null);
      try {
        const res = await spaFetch(`/spa/customers/${id}/edit`);
        if (!res.ok) {
          if (!cancelled) {
            setLoadError(`${res.status} ${res.statusText}`);
          }
          return;
        }
        const data = (await res.json()) as { customer: CustomerForm };
        if (!cancelled) {
          setForm(data.customer);
        }
      } catch {
        if (!cancelled) {
          setLoadError("Could not load customer.");
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [id]);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!form) {
      return;
    }
    setSubmitError(null);
    setFieldErr({});
    setPending(true);
    try {
      const res = await spaFetch(`/customers/${id}`, {
        method: "PATCH",
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
        setSubmitError(firstErrorMessage(body, "Update failed."));
        setFieldErr(fieldErrors(body));
        return;
      }
      router.push(`/customers/${id}`);
      router.refresh();
    } catch {
      setSubmitError("Could not reach the server.");
    } finally {
      setPending(false);
    }
  }

  async function onDelete() {
    if (!window.confirm("Delete this customer? This cannot be undone.")) {
      return;
    }
    setDeleteError(null);
    setDeletePending(true);
    try {
      const res = await spaFetch(`/customers/${id}`, { method: "DELETE" });
      if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        setDeleteError(firstErrorMessage(body, "Delete failed."));
        return;
      }
      router.replace("/customers");
      router.refresh();
    } catch {
      setDeleteError("Could not reach the server.");
    } finally {
      setDeletePending(false);
    }
  }

  if (loading) {
    return <p className="text-sm text-slate-600">Loading…</p>;
  }
  if (loadError || !form) {
    return (
      <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
        {loadError ?? "Customer not found."}
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">Edit customer</h1>
        <p className="mt-1 text-sm text-slate-600">Update customer details.</p>
        <p className="mt-2 text-sm">
          <Link href={`/customers/${id}`} className="text-pink-700 hover:underline">
            ← Back to profile
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
              value={dobInputValue(form.date_of_birth)}
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
          <div className="flex flex-wrap gap-3 pt-2">
            <button
              type="submit"
              disabled={pending}
              className="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-60"
            >
              {pending ? "Saving…" : "Update customer"}
            </button>
            <Link
              href={`/customers/${id}`}
              className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
              Cancel
            </Link>
          </div>
        </form>
        <div className="mt-8 border-t border-slate-100 pt-6">
          <h2 className="text-sm font-semibold text-slate-900">Danger zone</h2>
          <p className="mt-1 text-xs text-slate-600">Permanently remove this customer record.</p>
          {deleteError ? <p className="mt-2 text-sm text-rose-600">{deleteError}</p> : null}
          <button
            type="button"
            onClick={onDelete}
            disabled={deletePending}
            className="mt-3 rounded-md border border-rose-300 bg-white px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-60"
          >
            {deletePending ? "Deleting…" : "Delete customer"}
          </button>
        </div>
      </section>
    </div>
  );
}
