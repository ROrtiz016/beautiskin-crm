"use client";

import { SpaPageFrame } from "@/components/spa-page-frame";
import { useSpaGet } from "@/hooks/use-spa-get";
import { firstErrorMessage } from "@/lib/laravel-form-errors";
import { spaFetch } from "@/lib/spa-fetch";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useMemo, useState } from "react";

type CustomersPayload = {
  customers: { id: number; first_name: string; last_name: string; email?: string | null; phone?: string | null }[];
};

function customerLabel(c: { first_name: string; last_name: string }): string {
  return `${c.first_name} ${c.last_name}`.trim();
}

export default function NewQuotePage() {
  const router = useRouter();
  const { data, error, loading } = useSpaGet<CustomersPayload>("/spa/quotes");
  const customers = useMemo(() => data?.customers ?? [], [data]);

  const [customerId, setCustomerId] = useState("");
  const [title, setTitle] = useState("");
  const [submitErr, setSubmitErr] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSubmitErr(null);
    const cid = Number(customerId);
    if (!cid) {
      setSubmitErr("Select a customer.");
      return;
    }
    setPending(true);
    try {
      const res = await spaFetch("/quotes", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          customer_id: cid,
          title: title.trim() || null,
        }),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) {
        setSubmitErr(firstErrorMessage(body, "Could not create quote."));
        return;
      }
      const q = (body as { quote?: { id?: number } }).quote;
      if (q?.id) {
        router.replace(`/quotes/${q.id}`);
        router.refresh();
        return;
      }
      setSubmitErr("Unexpected response from server.");
    } catch {
      setSubmitErr("Could not reach the server.");
    } finally {
      setPending(false);
    }
  }

  return (
    <SpaPageFrame title="New quote" loading={loading} error={error}>
      <p className="text-sm">
        <Link href="/quotes" className="text-pink-700 hover:underline">
          ← Quotes
        </Link>
      </p>
      <form className="mx-auto mt-6 max-w-lg space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" onSubmit={onSubmit}>
        <label className="block text-sm font-medium text-slate-800">
          Customer
          <select
            required
            className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={customerId}
            onChange={(ev) => setCustomerId(ev.target.value)}
          >
            <option value="">Select…</option>
            {customers.map((c) => (
              <option key={c.id} value={c.id}>
                {customerLabel(c)}
              </option>
            ))}
          </select>
        </label>
        <label className="block text-sm font-medium text-slate-800">
          Title (optional)
          <input
            className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            value={title}
            onChange={(ev) => setTitle(ev.target.value)}
            placeholder="e.g. Spring facial package"
          />
        </label>
        {submitErr ? <p className="text-sm text-rose-600">{submitErr}</p> : null}
        <div className="flex gap-3 pt-2">
          <button
            type="submit"
            disabled={pending}
            className="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-50"
          >
            {pending ? "Creating…" : "Create draft quote"}
          </button>
          <Link href="/quotes" className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Cancel
          </Link>
        </div>
      </form>
    </SpaPageFrame>
  );
}
