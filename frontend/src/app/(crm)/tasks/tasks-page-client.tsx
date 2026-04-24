"use client";

import { CustomerFilterCombobox } from "../appointments/customer-filter-combobox";
import { SpaPageFrame } from "@/components/spa-page-frame";
import { useAuth } from "@/context/auth-context";
import { useSpaGet } from "@/hooks/use-spa-get";
import { firstErrorMessage } from "@/lib/laravel-form-errors";
import { spaFetch } from "@/lib/spa-fetch";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useMemo, useState } from "react";

type SpaUser = { id: number; name: string };

type TaskRow = {
  id: number;
  customer_id: number;
  opportunity_id?: number | null;
  assigned_to_user_id: number;
  kind: string;
  title: string;
  description?: string | null;
  status: string;
  due_at: string;
  remind_at?: string | null;
  customer?: { id: number; first_name: string; last_name: string };
  assigned_to?: SpaUser | null;
  assignedTo?: SpaUser | null;
  opportunity?: { id: number; title: string } | null;
  created_by?: SpaUser | null;
  createdBy?: SpaUser | null;
};

type OpportunityOption = {
  id: number;
  customer_id: number;
  title: string;
  stage?: string | null;
};

type TasksIndexPayload = {
  tasks: TaskRow[];
  currentView: string;
  viewLabels: Record<string, string>;
  customers: {
    id: number;
    first_name: string;
    last_name: string;
    email?: string | null;
    phone?: string | null;
  }[];
  staffUsers: SpaUser[];
  opportunities: OpportunityOption[];
  customerIdFilter: number;
  kindLabels: Record<string, string>;
  clinicTimezone?: string;
};

function mergeTasksHref(sp: URLSearchParams, patch: Record<string, string | undefined>): string {
  const n = new URLSearchParams(sp.toString());
  Object.entries(patch).forEach(([k, v]) => {
    if (v === undefined || v === "") {
      n.delete(k);
    } else {
      n.set(k, v);
    }
  });
  const s = n.toString();
  return s ? `/tasks?${s}` : "/tasks";
}

function assigneeName(t: TaskRow): string {
  const u = t.assigned_to ?? t.assignedTo;
  return u?.name ?? "—";
}

function toDatetimeLocalValue(iso: string | undefined | null): string {
  if (!iso) {
    return "";
  }
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) {
    return "";
  }
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function statusBadgeClass(status: string): string {
  switch (status) {
    case "completed":
      return "bg-emerald-100 text-emerald-800";
    case "cancelled":
      return "bg-slate-200 text-slate-700";
    default:
      return "bg-amber-100 text-amber-900";
  }
}

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

function TasksPageInner() {
  const sp = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const { user } = useAuth();

  const path = useMemo(() => `/spa/tasks${sp.toString() ? `?${sp}` : ""}`, [sp]);
  const { data, error, loading, reload } = useSpaGet<TasksIndexPayload>(path);

  const [draftCustomer, setDraftCustomer] = useState("");
  const [createOpen, setCreateOpen] = useState(false);
  const [createErr, setCreateErr] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const [nCustomer, setNCustomer] = useState("");
  const [nOpp, setNOpp] = useState("");
  const [nAssignee, setNAssignee] = useState("");
  const [nKind, setNKind] = useState("general");
  const [nTitle, setNTitle] = useState("");
  const [nDesc, setNDesc] = useState("");
  const [nDue, setNDue] = useState("");
  const [nRemind, setNRemind] = useState("");

  const [editTask, setEditTask] = useState<TaskRow | null>(null);
  const [editErr, setEditErr] = useState<string | null>(null);

  const [eCustomer, setECustomer] = useState("");
  const [eOpp, setEOpp] = useState("");
  const [eAssignee, setEAssignee] = useState("");
  const [eKind, setEKind] = useState("");
  const [eTitle, setETitle] = useState("");
  const [eDesc, setEDesc] = useState("");
  const [eDue, setEDue] = useState("");
  const [eRemind, setERemind] = useState("");
  const [eStatus, setEStatus] = useState("pending");

  useEffect(() => {
    setDraftCustomer(sp.get("customer_id") ?? "");
  }, [sp]);

  useEffect(() => {
    if (user?.id) {
      setNAssignee(String(user.id));
    }
  }, [user?.id]);

  const customers = data?.customers ?? [];
  const staffUsers = data?.staffUsers ?? [];
  const opportunities = data?.opportunities ?? [];
  const kindLabels = data?.kindLabels ?? {};
  const viewLabels = data?.viewLabels ?? {};
  const currentView = data?.currentView ?? "my_today";

  const applyCustomerFilter = useCallback(() => {
    const n = new URLSearchParams(sp.toString());
    if (draftCustomer) {
      n.set("customer_id", draftCustomer);
    } else {
      n.delete("customer_id");
    }
    const s = n.toString();
    router.push(s ? `${pathname}?${s}` : pathname);
  }, [draftCustomer, pathname, router, sp]);

  const clearCustomerFilter = useCallback(() => {
    setDraftCustomer("");
    const n = new URLSearchParams(sp.toString());
    n.delete("customer_id");
    const s = n.toString();
    router.push(s ? `${pathname}?${s}` : pathname);
  }, [pathname, router, sp]);

  const oppsFor = useCallback(
    (customerIdStr: string) => {
      const cid = Number(customerIdStr);
      if (!cid) {
        return opportunities;
      }
      return opportunities.filter((o) => o.customer_id === cid);
    },
    [opportunities],
  );

  const resetCreateForm = useCallback(() => {
    setCreateErr(null);
    setNCustomer("");
    setNOpp("");
    setNKind("general");
    setNTitle("");
    setNDesc("");
    setNDue("");
    setNRemind("");
    if (user?.id) {
      setNAssignee(String(user.id));
    } else {
      setNAssignee("");
    }
  }, [user?.id]);

  const openEdit = useCallback((t: TaskRow) => {
    setEditErr(null);
    setEditTask(t);
    setECustomer(String(t.customer_id));
    setEOpp(t.opportunity_id ? String(t.opportunity_id) : "");
    setEAssignee(String(t.assigned_to_user_id));
    setEKind(t.kind);
    setETitle(t.title);
    setEDesc(t.description ?? "");
    setEDue(toDatetimeLocalValue(t.due_at));
    setERemind(toDatetimeLocalValue(t.remind_at));
    setEStatus(t.status);
  }, []);

  const submitCreate = useCallback(async () => {
    setCreateErr(null);
    if (!nCustomer || !nAssignee || !nTitle.trim() || !nDue) {
      setCreateErr("Customer, assignee, title, and due date are required.");
      return;
    }
    setSaving(true);
    try {
      const res = await spaFetch("/tasks", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          customer_id: Number(nCustomer),
          opportunity_id: nOpp ? Number(nOpp) : null,
          assigned_to_user_id: Number(nAssignee),
          kind: nKind,
          title: nTitle.trim(),
          description: nDesc.trim() ? nDesc.trim() : null,
          due_at: new Date(nDue).toISOString(),
          remind_at: nRemind ? new Date(nRemind).toISOString() : null,
        }),
      });
      if (!res.ok) {
        const b = await readApiError(res);
        setCreateErr(firstErrorMessage(b, "Could not create task."));
        return;
      }
      resetCreateForm();
      setCreateOpen(false);
      await reload();
    } finally {
      setSaving(false);
    }
  }, [nAssignee, nCustomer, nDesc, nDue, nKind, nOpp, nRemind, nTitle, reload, resetCreateForm]);

  const submitEdit = useCallback(async () => {
    if (!editTask) {
      return;
    }
    setEditErr(null);
    if (!eCustomer || !eAssignee || !eTitle.trim() || !eDue) {
      setEditErr("Customer, assignee, title, and due date are required.");
      return;
    }
    setSaving(true);
    try {
      const res = await spaFetch(`/tasks/${editTask.id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          customer_id: Number(eCustomer),
          opportunity_id: eOpp ? Number(eOpp) : null,
          assigned_to_user_id: Number(eAssignee),
          kind: eKind,
          title: eTitle.trim(),
          description: eDesc.trim() ? eDesc.trim() : null,
          due_at: new Date(eDue).toISOString(),
          remind_at: eRemind ? new Date(eRemind).toISOString() : null,
          status: eStatus,
        }),
      });
      if (!res.ok) {
        const b = await readApiError(res);
        setEditErr(firstErrorMessage(b, "Could not update task."));
        return;
      }
      setEditTask(null);
      await reload();
    } finally {
      setSaving(false);
    }
  }, [
    eAssignee,
    eCustomer,
    eDesc,
    eDue,
    eKind,
    eOpp,
    eRemind,
    eStatus,
    eTitle,
    editTask,
    reload,
  ]);

  const completeTask = useCallback(
    async (id: number) => {
      const res = await spaFetch(`/tasks/${id}/complete`, { method: "PATCH" });
      if (!res.ok) {
        const b = await readApiError(res);
        window.alert(firstErrorMessage(b, "Could not complete."));
        return;
      }
      await reload();
    },
    [reload],
  );

  const reopenTask = useCallback(
    async (id: number) => {
      const res = await spaFetch(`/tasks/${id}/reopen`, { method: "PATCH" });
      if (!res.ok) {
        const b = await readApiError(res);
        window.alert(firstErrorMessage(b, "Could not reopen."));
        return;
      }
      await reload();
    },
    [reload],
  );

  const deleteTask = useCallback(
    async (t: TaskRow) => {
      if (!window.confirm(`Remove task “${t.title}”? This cannot be undone.`)) {
        return;
      }
      const res = await spaFetch(`/tasks/${t.id}`, { method: "DELETE" });
      if (!res.ok) {
        const b = await readApiError(res);
        window.alert(firstErrorMessage(b, "Could not remove."));
        return;
      }
      await reload();
    },
    [reload],
  );

  const viewKeys = useMemo(() => Object.keys(viewLabels), [viewLabels]);

  return (
    <SpaPageFrame
      title="Tasks"
      subtitle={data ? `${data.tasks.length} shown` : undefined}
      loading={loading}
      error={error}
    >
      <div className="mb-4 flex flex-wrap gap-2 border-b border-slate-200 pb-3">
        {viewKeys.map((vk) => (
          <Link
            key={vk}
            href={mergeTasksHref(sp, { view: vk === "my_today" ? undefined : vk })}
            className={`rounded-full px-3 py-1 text-sm font-medium ${
              currentView === vk ? "bg-pink-600 text-white" : "bg-slate-100 text-slate-800 hover:bg-slate-200"
            }`}
          >
            {viewLabels[vk] ?? vk}
          </Link>
        ))}
      </div>

      <div className="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
        <div className="min-w-[200px] flex-1">
          <CustomerFilterCombobox
            customers={customers}
            value={draftCustomer}
            onValueChange={setDraftCustomer}
          />
        </div>
        <button
          type="button"
          onClick={applyCustomerFilter}
          className="rounded-md bg-pink-600 px-3 py-2 text-sm font-semibold text-white hover:bg-pink-700"
        >
          Apply filter
        </button>
        <button
          type="button"
          onClick={clearCustomerFilter}
          className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50"
        >
          Clear customer
        </button>
      </div>

      <div className="mb-4">
        <button
          type="button"
          onClick={() => {
            if (createOpen) {
              setCreateOpen(false);
              resetCreateForm();
            } else {
              resetCreateForm();
              setCreateOpen(true);
            }
          }}
          className="inline-flex rounded-md bg-pink-600 px-3 py-2 text-sm font-semibold text-white hover:bg-pink-700"
        >
          {createOpen ? "Cancel new task" : "New task"}
        </button>
      </div>

      {createOpen ? (
        <div className="mb-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <h2 className="mb-3 text-sm font-semibold text-slate-900">Create task</h2>
          {createErr ? <p className="mb-2 text-sm text-red-600">{createErr}</p> : null}
          <div className="grid gap-3 sm:grid-cols-2">
            <label className="block text-sm">
              Customer
              <select
                className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                value={nCustomer}
                onChange={(ev) => {
                  setNCustomer(ev.target.value);
                  setNOpp("");
                }}
                required
              >
                <option value="">Select…</option>
                {customers.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.first_name} {c.last_name}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm">
              Opportunity (optional)
              <select
                className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                value={nOpp}
                onChange={(ev) => setNOpp(ev.target.value)}
              >
                <option value="">None</option>
                {oppsFor(nCustomer).map((o) => (
                  <option key={o.id} value={o.id}>
                    {o.title}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm">
              Assignee
              <select
                className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                value={nAssignee}
                onChange={(ev) => setNAssignee(ev.target.value)}
                required
              >
                <option value="">Select…</option>
                {staffUsers.map((u) => (
                  <option key={u.id} value={u.id}>
                    {u.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm">
              Kind
              <select
                className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                value={nKind}
                onChange={(ev) => setNKind(ev.target.value)}
              >
                {Object.entries(kindLabels).map(([k, lab]) => (
                  <option key={k} value={k}>
                    {lab}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm sm:col-span-2">
              Title
              <input
                className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                value={nTitle}
                onChange={(ev) => setNTitle(ev.target.value)}
                maxLength={255}
                required
              />
            </label>
            <label className="block text-sm sm:col-span-2">
              Description
              <textarea
                className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                rows={2}
                value={nDesc}
                onChange={(ev) => setNDesc(ev.target.value)}
              />
            </label>
            <label className="block text-sm">
              Due
              <input
                type="datetime-local"
                className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                value={nDue}
                onChange={(ev) => setNDue(ev.target.value)}
                required
              />
            </label>
            <label className="block text-sm">
              Remind (optional)
              <input
                type="datetime-local"
                className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                value={nRemind}
                onChange={(ev) => setNRemind(ev.target.value)}
              />
            </label>
          </div>
          <div className="mt-3 flex gap-2">
            <button
              type="button"
              disabled={saving}
              onClick={() => void submitCreate()}
              className="rounded-md bg-pink-600 px-3 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-50"
            >
              Save task
            </button>
          </div>
        </div>
      ) : null}

      {data?.tasks?.length ? (
        <div className="overflow-x-auto rounded-lg border border-slate-200">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-3 py-2 text-left font-semibold text-slate-700">Customer</th>
                <th className="px-3 py-2 text-left font-semibold text-slate-700">Title</th>
                <th className="px-3 py-2 text-left font-semibold text-slate-700">Kind</th>
                <th className="px-3 py-2 text-left font-semibold text-slate-700">Due</th>
                <th className="px-3 py-2 text-left font-semibold text-slate-700">Assignee</th>
                <th className="px-3 py-2 text-left font-semibold text-slate-700">Status</th>
                <th className="px-3 py-2 text-right font-semibold text-slate-700">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 bg-white">
              {data.tasks.map((t) => (
                <tr key={t.id} className="hover:bg-slate-50">
                  <td className="px-3 py-2 text-slate-800">
                    {t.customer ? (
                      <Link href={`/customers/${t.customer.id}`} className="text-pink-700 hover:underline">
                        {t.customer.first_name} {t.customer.last_name}
                      </Link>
                    ) : (
                      "—"
                    )}
                  </td>
                  <td className="px-3 py-2 font-medium text-slate-900">{t.title}</td>
                  <td className="px-3 py-2 text-slate-600">{kindLabels[t.kind] ?? t.kind}</td>
                  <td className="px-3 py-2 text-slate-600">
                    {t.due_at ? new Date(t.due_at).toLocaleString() : "—"}
                  </td>
                  <td className="px-3 py-2 text-slate-600">{assigneeName(t)}</td>
                  <td className="px-3 py-2">
                    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusBadgeClass(t.status)}`}>
                      {t.status}
                    </span>
                  </td>
                  <td className="px-3 py-2 text-right">
                    <div className="flex flex-wrap justify-end gap-1">
                      <button
                        type="button"
                        onClick={() => openEdit(t)}
                        className="rounded border border-slate-300 bg-white px-2 py-0.5 text-xs font-medium hover:bg-slate-50"
                      >
                        Edit
                      </button>
                      {t.status === "pending" ? (
                        <button
                          type="button"
                          onClick={() => void completeTask(t.id)}
                          className="rounded border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-900 hover:bg-emerald-100"
                        >
                          Complete
                        </button>
                      ) : null}
                      {t.status === "completed" ? (
                        <button
                          type="button"
                          onClick={() => void reopenTask(t.id)}
                          className="rounded border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900 hover:bg-amber-100"
                        >
                          Reopen
                        </button>
                      ) : null}
                      <button
                        type="button"
                        onClick={() => void deleteTask(t)}
                        className="rounded border border-red-200 bg-white px-2 py-0.5 text-xs font-medium text-red-700 hover:bg-red-50"
                      >
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : data ? (
        <p className="text-sm text-slate-600">No tasks in this view.</p>
      ) : null}

      {editTask ? (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" role="dialog">
          <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-4 shadow-xl">
            <h2 className="text-base font-semibold text-slate-900">Edit task</h2>
            {editErr ? <p className="mt-2 text-sm text-red-600">{editErr}</p> : null}
            <div className="mt-3 grid gap-3">
              <label className="block text-sm">
                Customer
                <select
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={eCustomer}
                  onChange={(ev) => {
                    setECustomer(ev.target.value);
                    setEOpp("");
                  }}
                >
                  {customers.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.first_name} {c.last_name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-sm">
                Opportunity
                <select
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={eOpp}
                  onChange={(ev) => setEOpp(ev.target.value)}
                >
                  <option value="">None</option>
                  {oppsFor(eCustomer).map((o) => (
                    <option key={o.id} value={o.id}>
                      {o.title}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-sm">
                Assignee
                <select
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={eAssignee}
                  onChange={(ev) => setEAssignee(ev.target.value)}
                >
                  {staffUsers.map((u) => (
                    <option key={u.id} value={u.id}>
                      {u.name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-sm">
                Kind
                <select
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={eKind}
                  onChange={(ev) => setEKind(ev.target.value)}
                >
                  {Object.entries(kindLabels).map(([k, lab]) => (
                    <option key={k} value={k}>
                      {lab}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-sm">
                Status
                <select
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={eStatus}
                  onChange={(ev) => setEStatus(ev.target.value)}
                >
                  <option value="pending">pending</option>
                  <option value="completed">completed</option>
                  <option value="cancelled">cancelled</option>
                </select>
              </label>
              <label className="block text-sm">
                Title
                <input
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={eTitle}
                  onChange={(ev) => setETitle(ev.target.value)}
                />
              </label>
              <label className="block text-sm">
                Description
                <textarea
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  rows={3}
                  value={eDesc}
                  onChange={(ev) => setEDesc(ev.target.value)}
                />
              </label>
              <label className="block text-sm">
                Due
                <input
                  type="datetime-local"
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={eDue}
                  onChange={(ev) => setEDue(ev.target.value)}
                />
              </label>
              <label className="block text-sm">
                Remind
                <input
                  type="datetime-local"
                  className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={eRemind}
                  onChange={(ev) => setERemind(ev.target.value)}
                />
              </label>
            </div>
            <div className="mt-4 flex flex-wrap gap-2">
              <button
                type="button"
                disabled={saving}
                onClick={() => void submitEdit()}
                className="rounded-md bg-pink-600 px-3 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-50"
              >
                Save
              </button>
              <button
                type="button"
                onClick={() => setEditTask(null)}
                className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </SpaPageFrame>
  );
}

export function TasksPageClient() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-slate-600">Loading tasks…</div>}>
      <TasksPageInner />
    </Suspense>
  );
}
