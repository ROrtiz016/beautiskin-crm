"use client";

import { useAuth } from "@/context/auth-context";
import { firstErrorMessage } from "@/lib/laravel-form-errors";
import { spaFetch } from "@/lib/spa-fetch";
import { useCallback, useEffect, useMemo, useState } from "react";

type UnknownRec = Record<string, unknown>;

type ServiceRow = { id: number; name: string };
type MembershipRow = { id: number; name: string };
type CustomerBrief = { id: number; first_name?: string; last_name?: string; email?: string | null };

type ReloadBoard = () => void | Promise<void>;

type PromotionCreateDraft = {
  name: string;
  description: string;
  discount_type: "percent" | "fixed";
  discount_value: string;
  applies_to: "all" | "services" | "memberships";
  starts_on: string;
  ends_on: string;
  stackable: boolean;
  max_discount_cap: string;
  minimum_purchase: string;
  is_active: boolean;
  service_ids: Record<number, boolean>;
  membership_ids: Record<number, boolean>;
};

type ControlBoardAdminParityProps = {
  clinicSettings?: UnknownRec;
  services: ServiceRow[];
  memberships: MembershipRow[];
  users: UnknownRec[];
  permissionOptions?: string[];
  roleTemplateLabels?: Record<string, string>;
  applyRoleTemplateLabels?: Record<string, string>;
  customersForRetention?: CustomerBrief[];
  promotions: UnknownRec[];
  promoBusyId: number | null;
  onTogglePromotionActive: (promotion: UnknownRec, nextActive: boolean) => void;
  reload: ReloadBoard;
  setInlineErr: (msg: string | null) => void;
};

function asStr(v: unknown): string {
  return v == null ? "" : String(v);
}

function asBool(v: unknown): boolean {
  return Boolean(v);
}

function permList(u: UnknownRec): string[] {
  const p = u.permissions;
  return Array.isArray(p) ? p.filter((x): x is string => typeof x === "string") : [];
}

function targetedIds(p: UnknownRec, key: string): number[] {
  const rel = p[key] ?? p[key.replace(/_([a-z])/g, (_, c: string) => c.toUpperCase())];
  if (!Array.isArray(rel)) {
    return [];
  }
  return rel.map((row: unknown) => (typeof row === "object" && row !== null && "id" in row ? Number((row as { id: unknown }).id) : NaN)).filter((n) => Number.isFinite(n));
}

export function ControlBoardAdminParity({
  clinicSettings,
  services,
  memberships,
  users,
  permissionOptions = [],
  roleTemplateLabels = {},
  applyRoleTemplateLabels = {},
  customersForRetention = [],
  promotions,
  promoBusyId,
  onTogglePromotionActive,
  reload,
  setInlineErr,
}: ControlBoardAdminParityProps) {
  const { user } = useAuth();
  const canManageUsers = Boolean(user?.can.manage_users);
  const isAdmin = Boolean(user?.is_admin);
  const currentUserId = user?.id ?? 0;

  const [taxRate, setTaxRate] = useState("");
  const [rounding, setRounding] = useState("half_up");
  const [clinicBusy, setClinicBusy] = useState(false);
  const [clinicMsg, setClinicMsg] = useState<string | null>(null);

  const [clinicName, setClinicName] = useState("");
  const [clinicTz, setClinicTz] = useState("");
  const [businessHours, setBusinessHours] = useState("");
  const [defApptLen, setDefApptLen] = useState("");
  const [remEmailLead, setRemEmailLead] = useState("");
  const [remSmsLead, setRemSmsLead] = useState("");
  const [profileBusy, setProfileBusy] = useState(false);
  const [profileMsg, setProfileMsg] = useState<string | null>(null);

  const [emailFrom, setEmailFrom] = useState("");
  const [emailFromName, setEmailFromName] = useState("");
  const [emailTpl, setEmailTpl] = useState(false);
  const [smsTpl, setSmsTpl] = useState(false);
  const [remSubj, setRemSubj] = useState("");
  const [remBody, setRemBody] = useState("");
  const [remSms, setRemSms] = useState("");
  const [fuSubj, setFuSubj] = useState("");
  const [fuBody, setFuBody] = useState("");
  const [fuSms, setFuSms] = useState("");
  const [nsSubj, setNsSubj] = useState("");
  const [nsBody, setNsBody] = useState("");
  const [nsSms, setNsSms] = useState("");
  const [msgBusy, setMsgBusy] = useState(false);
  const [msgOk, setMsgOk] = useState<string | null>(null);
  const [testEmail, setTestEmail] = useState("");

  const [newName, setNewName] = useState("");
  const [newEmail, setNewEmail] = useState("");
  const [newPass, setNewPass] = useState("");
  const [newPass2, setNewPass2] = useState("");
  const [newRoleTpl, setNewRoleTpl] = useState("custom");
  const [newPerms, setNewPerms] = useState<Record<string, boolean>>({});
  const [newIsAdmin, setNewIsAdmin] = useState(false);
  const [createBusy, setCreateBusy] = useState(false);

  const [exportCustId, setExportCustId] = useState("");
  const [gdprCustId, setGdprCustId] = useState("");
  const [gdprConfirm, setGdprConfirm] = useState(false);
  const [dataBusy, setDataBusy] = useState(false);

  const [promoBusy, setPromoBusy] = useState(false);

  useEffect(() => {
    if (!clinicSettings) {
      return;
    }
    setTaxRate(String(clinicSettings.default_tax_rate ?? "0"));
    setRounding(String(clinicSettings.price_rounding_rule ?? "half_up"));
    setClinicName(String(clinicSettings.clinic_name ?? ""));
    setClinicTz(String(clinicSettings.clinic_timezone ?? ""));
    setBusinessHours(String(clinicSettings.business_hours ?? ""));
    setDefApptLen(String(clinicSettings.default_appointment_length_minutes ?? "60"));
    setRemEmailLead(String(clinicSettings.reminder_email_lead_minutes ?? "1440"));
    setRemSmsLead(String(clinicSettings.reminder_sms_lead_minutes ?? "120"));
    setEmailFrom(String(clinicSettings.email_from_address ?? ""));
    setEmailFromName(String(clinicSettings.email_from_name ?? ""));
    setEmailTpl(Boolean(clinicSettings.email_templates_enabled));
    setSmsTpl(Boolean(clinicSettings.sms_templates_enabled));
    setRemSubj(String(clinicSettings.reminder_email_subject_template ?? ""));
    setRemBody(String(clinicSettings.reminder_email_body_template ?? ""));
    setRemSms(String(clinicSettings.reminder_sms_template ?? ""));
    setFuSubj(String(clinicSettings.followup_email_subject_template ?? ""));
    setFuBody(String(clinicSettings.followup_email_body_template ?? ""));
    setFuSms(String(clinicSettings.followup_sms_template ?? ""));
    setNsSubj(String(clinicSettings.no_show_email_subject_template ?? ""));
    setNsBody(String(clinicSettings.no_show_email_body_template ?? ""));
    setNsSms(String(clinicSettings.no_show_sms_template ?? ""));
  }, [clinicSettings]);

  useEffect(() => {
    const next: Record<string, boolean> = {};
    for (const p of permissionOptions) {
      next[p] = false;
    }
    setNewPerms(next);
  }, [permissionOptions]);

  const saveTax = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault();
      setInlineErr(null);
      setClinicMsg(null);
      setClinicBusy(true);
      try {
        const rate = Number(taxRate);
        if (!Number.isFinite(rate) || rate < 0 || rate > 1) {
          setInlineErr("Tax rate must be between 0 and 1 (e.g. 0.0825 for 8.25%).");
          return;
        }
        const res = await spaFetch("/admin/clinic-settings", {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ default_tax_rate: rate, price_rounding_rule: rounding }),
        });
        const b = await res.json().catch(() => ({}));
        if (!res.ok) {
          setInlineErr(firstErrorMessage(b, "Could not save tax settings."));
          return;
        }
        setClinicMsg((b as { message?: string }).message ?? "Saved.");
        await reload();
      } catch {
        setInlineErr("Could not reach the server.");
      } finally {
        setClinicBusy(false);
      }
    },
    [reload, rounding, setInlineErr, taxRate],
  );

  const saveProfile = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault();
      setInlineErr(null);
      setProfileMsg(null);
      setProfileBusy(true);
      try {
        const res = await spaFetch("/admin/clinic-profile", {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            clinic_name: clinicName,
            clinic_timezone: clinicTz,
            business_hours: businessHours || null,
            default_appointment_length_minutes: Number(defApptLen),
            reminder_email_lead_minutes: Number(remEmailLead),
            reminder_sms_lead_minutes: Number(remSmsLead),
          }),
        });
        const b = await res.json().catch(() => ({}));
        if (!res.ok) {
          setInlineErr(firstErrorMessage(b, "Could not save clinic profile."));
          return;
        }
        setProfileMsg((b as { message?: string }).message ?? "Saved.");
        await reload();
      } catch {
        setInlineErr("Could not reach the server.");
      } finally {
        setProfileBusy(false);
      }
    },
    [businessHours, clinicName, clinicTz, defApptLen, reload, remEmailLead, remSmsLead, setInlineErr],
  );

  const saveMessaging = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault();
      setInlineErr(null);
      setMsgOk(null);
      setMsgBusy(true);
      try {
        const res = await spaFetch("/admin/messaging-settings", {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            email_from_address: emailFrom || null,
            email_from_name: emailFromName || null,
            email_templates_enabled: emailTpl,
            sms_templates_enabled: smsTpl,
            reminder_email_subject_template: remSubj || null,
            reminder_email_body_template: remBody || null,
            reminder_sms_template: remSms || null,
            followup_email_subject_template: fuSubj || null,
            followup_email_body_template: fuBody || null,
            followup_sms_template: fuSms || null,
            no_show_email_subject_template: nsSubj || null,
            no_show_email_body_template: nsBody || null,
            no_show_sms_template: nsSms || null,
          }),
        });
        const b = await res.json().catch(() => ({}));
        if (!res.ok) {
          setInlineErr(firstErrorMessage(b, "Could not save messaging settings."));
          return;
        }
        setMsgOk((b as { message?: string }).message ?? "Saved.");
        await reload();
      } catch {
        setInlineErr("Could not reach the server.");
      } finally {
        setMsgBusy(false);
      }
    },
    [
      emailFrom,
      emailFromName,
      emailTpl,
      fuBody,
      fuSms,
      fuSubj,
      nsBody,
      nsSms,
      nsSubj,
      reload,
      remBody,
      remSms,
      remSubj,
      setInlineErr,
      smsTpl,
    ],
  );

  const sendTest = useCallback(async () => {
    setInlineErr(null);
    setMsgOk(null);
    setMsgBusy(true);
    try {
      const res = await spaFetch("/admin/messaging-settings/test-send", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ test_email: testEmail }),
      });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setInlineErr(firstErrorMessage(b, "Could not send test."));
        return;
      }
      setMsgOk((b as { message?: string }).message ?? "Sent.");
    } catch {
      setInlineErr("Could not reach the server.");
    } finally {
      setMsgBusy(false);
    }
  }, [setInlineErr, testEmail]);

  const createUser = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault();
      setInlineErr(null);
      setCreateBusy(true);
      try {
        const perms = Object.entries(newPerms)
          .filter(([, v]) => v)
          .map(([k]) => k);
        const res = await spaFetch("/admin/users", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            name: newName,
            email: newEmail,
            password: newPass,
            password_confirmation: newPass2,
            role_template: newRoleTpl,
            permissions: perms,
            is_admin: isAdmin && newIsAdmin,
          }),
        });
        const b = await res.json().catch(() => ({}));
        if (!res.ok) {
          setInlineErr(firstErrorMessage(b, "Could not create user."));
          return;
        }
        setNewName("");
        setNewEmail("");
        setNewPass("");
        setNewPass2("");
        setNewIsAdmin(false);
        setNewRoleTpl("custom");
        const cleared: Record<string, boolean> = {};
        for (const p of permissionOptions) {
          cleared[p] = false;
        }
        setNewPerms(cleared);
        await reload();
      } catch {
        setInlineErr("Could not reach the server.");
      } finally {
        setCreateBusy(false);
      }
    },
    [isAdmin, newEmail, newIsAdmin, newName, newPass, newPass2, newPerms, newRoleTpl, permissionOptions, reload, setInlineErr],
  );

  const patchUserAccess = useCallback(
    async (adminUserId: number, body: Record<string, unknown>) => {
      setInlineErr(null);
      try {
        const res = await spaFetch(`/admin/users/${adminUserId}/access`, {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(body),
        });
        const b = await res.json().catch(() => ({}));
        if (!res.ok) {
          setInlineErr(firstErrorMessage(b, "Could not update access."));
          return;
        }
        await reload();
      } catch {
        setInlineErr("Could not reach the server.");
      }
    },
    [reload, setInlineErr],
  );

  const deactivateUser = useCallback(
    async (adminUserId: number, label: string) => {
      if (!window.confirm(`Deactivate ${label}? They will no longer be able to sign in.`)) {
        return;
      }
      setInlineErr(null);
      try {
        const res = await spaFetch(`/admin/users/${adminUserId}/deactivate`, { method: "POST" });
        const b = await res.json().catch(() => ({}));
        if (!res.ok) {
          setInlineErr(firstErrorMessage(b, "Could not deactivate."));
          return;
        }
        await reload();
      } catch {
        setInlineErr("Could not reach the server.");
      }
    },
    [reload, setInlineErr],
  );

  const restoreUser = useCallback(
    async (adminUserId: number) => {
      setInlineErr(null);
      try {
        const res = await spaFetch(`/admin/users/${adminUserId}/restore`, { method: "POST" });
        const b = await res.json().catch(() => ({}));
        if (!res.ok) {
          setInlineErr(firstErrorMessage(b, "Could not restore."));
          return;
        }
        await reload();
      } catch {
        setInlineErr("Could not reach the server.");
      }
    },
    [reload, setInlineErr],
  );

  const downloadBackup = useCallback(async () => {
    setInlineErr(null);
    setDataBusy(true);
    try {
      const res = await spaFetch("/admin/backup-export");
      if (!res.ok) {
        const b = await res.json().catch(() => ({}));
        setInlineErr(firstErrorMessage(b, "Export failed."));
        return;
      }
      const blob = await res.blob();
      const cd = res.headers.get("Content-Disposition");
      let filename = "beautiskin-backup.json";
      const m = cd?.match(/filename="?([^";]+)"?/i);
      if (m?.[1]) {
        filename = m[1];
      }
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = filename;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      setInlineErr("Could not download backup.");
    } finally {
      setDataBusy(false);
    }
  }, [setInlineErr]);

  const downloadCustomerExport = useCallback(async () => {
    const id = Number(exportCustId);
    if (!id) {
      setInlineErr("Select a customer to export.");
      return;
    }
    setInlineErr(null);
    setDataBusy(true);
    try {
      const res = await spaFetch(`/admin/customers/${id}/export`);
      if (!res.ok) {
        const b = await res.json().catch(() => ({}));
        setInlineErr(firstErrorMessage(b, "Export failed."));
        return;
      }
      const blob = await res.blob();
      const cd = res.headers.get("Content-Disposition");
      let filename = `customer-export-${id}.json`;
      const m = cd?.match(/filename="?([^";]+)"?/i);
      if (m?.[1]) {
        filename = m[1];
      }
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = filename;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      setInlineErr("Could not export customer.");
    } finally {
      setDataBusy(false);
    }
  }, [exportCustId, setInlineErr]);

  const gdprDelete = useCallback(async () => {
    const id = Number(gdprCustId);
    if (!id || !gdprConfirm) {
      setInlineErr("Select a customer and confirm GDPR delete.");
      return;
    }
    if (!window.confirm("This permanently anonymizes the customer in the database. Continue?")) {
      return;
    }
    setInlineErr(null);
    setDataBusy(true);
    try {
      const res = await spaFetch(`/admin/customers/${id}/gdpr-delete`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ confirm_gdpr_delete: true }),
      });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setInlineErr(firstErrorMessage(b, "Could not complete GDPR delete."));
        return;
      }
      setGdprConfirm(false);
      setGdprCustId("");
      await reload();
    } catch {
      setInlineErr("Could not reach the server.");
    } finally {
      setDataBusy(false);
    }
  }, [gdprConfirm, gdprCustId, reload, setInlineErr]);

  const roleTemplateKeys = useMemo(() => Object.keys(roleTemplateLabels), [roleTemplateLabels]);

  if (!clinicSettings) {
    return null;
  }

  return (
    <div className="space-y-8">
      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-lg font-semibold text-slate-900">Tax &amp; rounding</h2>
        <p className="mt-1 text-xs text-slate-500">Default tax rate is a decimal fraction (0–1), e.g. 0.0825 for 8.25%.</p>
        <form className="mt-4 flex flex-wrap items-end gap-3" onSubmit={saveTax}>
          <label className="text-sm">
            Default tax rate
            <input
              type="number"
              step="0.000001"
              min={0}
              max={1}
              className="mt-1 block w-40 rounded-md border border-slate-300 px-2 py-1.5 text-sm"
              value={taxRate}
              onChange={(ev) => setTaxRate(ev.target.value)}
            />
          </label>
          <label className="text-sm">
            Rounding
            <select
              className="mt-1 block rounded-md border border-slate-300 px-2 py-1.5 text-sm"
              value={rounding}
              onChange={(ev) => setRounding(ev.target.value)}
            >
              <option value="half_up">Half up</option>
              <option value="floor">Floor</option>
              <option value="ceil">Ceil</option>
            </select>
          </label>
          <button
            type="submit"
            disabled={clinicBusy}
            className="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
          >
            {clinicBusy ? "Saving…" : "Save tax settings"}
          </button>
        </form>
        {clinicMsg ? <p className="mt-2 text-xs text-emerald-700">{clinicMsg}</p> : null}
      </section>

      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-lg font-semibold text-slate-900">Clinic profile</h2>
        <form className="mt-4 grid gap-3 md:grid-cols-2" onSubmit={saveProfile}>
          <label className="text-sm md:col-span-2">
            Clinic name
            <input className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" value={clinicName} onChange={(e) => setClinicName(e.target.value)} required />
          </label>
          <label className="text-sm">
            Timezone
            <input className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" value={clinicTz} onChange={(e) => setClinicTz(e.target.value)} required />
          </label>
          <label className="text-sm">
            Default appointment length (min)
            <input
              type="number"
              min={5}
              max={480}
              className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
              value={defApptLen}
              onChange={(e) => setDefApptLen(e.target.value)}
              required
            />
          </label>
          <label className="text-sm">
            Email reminder lead (min)
            <input type="number" min={0} max={10080} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" value={remEmailLead} onChange={(e) => setRemEmailLead(e.target.value)} required />
          </label>
          <label className="text-sm">
            SMS reminder lead (min)
            <input type="number" min={0} max={10080} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" value={remSmsLead} onChange={(e) => setRemSmsLead(e.target.value)} required />
          </label>
          <label className="text-sm md:col-span-2">
            Business hours
            <textarea rows={4} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" value={businessHours} onChange={(e) => setBusinessHours(e.target.value)} />
          </label>
          <div className="md:col-span-2">
            <button
              type="submit"
              disabled={profileBusy}
              className="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
            >
              {profileBusy ? "Saving…" : "Save clinic profile"}
            </button>
          </div>
        </form>
        {profileMsg ? <p className="mt-2 text-xs text-emerald-700">{profileMsg}</p> : null}
      </section>

      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-lg font-semibold text-slate-900">Email / SMS templates</h2>
        <p className="mt-1 text-xs text-slate-500">From address, toggles, and core templates. Use placeholders as in Laravel (e.g. {"{{customer_name}}"}).</p>
        <form className="mt-4 space-y-3" onSubmit={saveMessaging}>
          <div className="grid gap-3 md:grid-cols-2">
            <label className="text-sm">
              From email
              <input type="email" className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" value={emailFrom} onChange={(e) => setEmailFrom(e.target.value)} />
            </label>
            <label className="text-sm">
              From name
              <input className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" value={emailFromName} onChange={(e) => setEmailFromName(e.target.value)} />
            </label>
          </div>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={emailTpl} onChange={(e) => setEmailTpl(e.target.checked)} />
            Email templates enabled
          </label>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={smsTpl} onChange={(e) => setSmsTpl(e.target.checked)} />
            SMS templates enabled
          </label>
          <details className="rounded-md border border-slate-200 p-3">
            <summary className="cursor-pointer text-sm font-medium text-slate-800">Reminder templates</summary>
            <div className="mt-3 space-y-2">
              <input className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={remSubj} onChange={(e) => setRemSubj(e.target.value)} placeholder="Reminder subject" />
              <textarea rows={4} className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={remBody} onChange={(e) => setRemBody(e.target.value)} placeholder="Reminder email body" />
              <textarea rows={2} className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={remSms} onChange={(e) => setRemSms(e.target.value)} placeholder="Reminder SMS" />
            </div>
          </details>
          <details className="rounded-md border border-slate-200 p-3">
            <summary className="cursor-pointer text-sm font-medium text-slate-800">Follow-up templates</summary>
            <div className="mt-3 space-y-2">
              <input className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={fuSubj} onChange={(e) => setFuSubj(e.target.value)} />
              <textarea rows={3} className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={fuBody} onChange={(e) => setFuBody(e.target.value)} />
              <textarea rows={2} className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={fuSms} onChange={(e) => setFuSms(e.target.value)} />
            </div>
          </details>
          <details className="rounded-md border border-slate-200 p-3">
            <summary className="cursor-pointer text-sm font-medium text-slate-800">No-show templates</summary>
            <div className="mt-3 space-y-2">
              <input className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={nsSubj} onChange={(e) => setNsSubj(e.target.value)} />
              <textarea rows={3} className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={nsBody} onChange={(e) => setNsBody(e.target.value)} />
              <textarea rows={2} className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={nsSms} onChange={(e) => setNsSms(e.target.value)} />
            </div>
          </details>
          <button type="submit" disabled={msgBusy} className="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50">
            {msgBusy ? "Saving…" : "Save messaging settings"}
          </button>
        </form>
        <div className="mt-4 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-4">
          <label className="text-sm">
            Test email
            <input type="email" className="mt-1 block w-56 rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={testEmail} onChange={(e) => setTestEmail(e.target.value)} />
          </label>
          <button type="button" onClick={() => void sendTest()} disabled={msgBusy} className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 disabled:opacity-50">
            Send test
          </button>
        </div>
        {msgOk ? <p className="mt-2 text-xs text-emerald-700">{msgOk}</p> : null}
      </section>

      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-lg font-semibold text-slate-900">Add user</h2>
        <form className="mt-4 grid gap-3 md:grid-cols-2" onSubmit={createUser}>
          <label className="text-sm">
            Name
            <input className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" value={newName} onChange={(e) => setNewName(e.target.value)} required />
          </label>
          <label className="text-sm">
            Email
            <input type="email" className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" value={newEmail} onChange={(e) => setNewEmail(e.target.value)} required />
          </label>
          <label className="text-sm">
            Password
            <input type="password" className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" value={newPass} onChange={(e) => setNewPass(e.target.value)} required />
          </label>
          <label className="text-sm">
            Confirm password
            <input type="password" className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" value={newPass2} onChange={(e) => setNewPass2(e.target.value)} required />
          </label>
          <label className="text-sm md:col-span-2">
            Role template
            <select className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" value={newRoleTpl} onChange={(e) => setNewRoleTpl(e.target.value)}>
              {roleTemplateKeys.map((k) => (
                <option key={k} value={k}>
                  {roleTemplateLabels[k] ?? k}
                </option>
              ))}
            </select>
          </label>
          <div className="md:col-span-2">
            <p className="mb-2 text-xs font-semibold uppercase text-slate-500">Permissions (when template is Custom)</p>
            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
              {permissionOptions.map((p) => (
                <label key={p} className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={Boolean(newPerms[p])}
                    onChange={(e) => setNewPerms((prev) => ({ ...prev, [p]: e.target.checked }))}
                  />
                  <span>{p.replace(/_/g, " ")}</span>
                </label>
              ))}
            </div>
          </div>
          {isAdmin ? (
            <label className="flex items-center gap-2 text-sm md:col-span-2">
              <input type="checkbox" checked={newIsAdmin} onChange={(e) => setNewIsAdmin(e.target.checked)} />
              Administrator
            </label>
          ) : (
            <p className="text-xs text-slate-500 md:col-span-2">Only full administrators can create other administrator accounts.</p>
          )}
          <div className="md:col-span-2">
            <button type="submit" disabled={createBusy} className="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-50">
              {createBusy ? "Creating…" : "Create user"}
            </button>
          </div>
        </form>
      </section>

      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-lg font-semibold text-slate-900">Users &amp; access</h2>
        <p className="mt-1 text-xs text-slate-500">Deactivate/restore requires administrator or manage users permission.</p>
        <div className="mt-4 space-y-6">
          {users.map((u) => {
            const id = Number(u.id);
            const trashed = Boolean(u.deleted_at);
            const name = asStr(u.name);
            const email = asStr(u.email);
            const uIsAdmin = asBool(u.is_admin);
            const perms = permList(u);
            return (
              <UserAccessCard
                key={id}
                userId={id}
                name={name}
                email={email}
                trashed={trashed}
                isAdmin={uIsAdmin}
                permissions={perms}
                permissionOptions={permissionOptions}
                applyRoleTemplateLabels={applyRoleTemplateLabels}
                canManageUsers={canManageUsers}
                isActorAdmin={isAdmin}
                currentUserId={currentUserId}
                onSave={(body) => void patchUserAccess(id, body)}
                onDeactivate={() => void deactivateUser(id, name)}
                onRestore={() => void restoreUser(id)}
              />
            );
          })}
        </div>
      </section>

      <PromotionFormsSection
        services={services}
        memberships={memberships}
        promotions={promotions}
        promoBusyId={promoBusyId}
        onTogglePromotionActive={onTogglePromotionActive}
        reload={reload}
        setInlineErr={setInlineErr}
        busy={promoBusy}
        setBusy={setPromoBusy}
      />

      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-lg font-semibold text-slate-900">Data export &amp; GDPR</h2>
        <p className="mt-1 text-xs text-slate-500">Full backup JSON and per-customer export match the Laravel control board.</p>
        <div className="mt-4 flex flex-wrap gap-3">
          <button type="button" disabled={dataBusy} onClick={() => void downloadBackup()} className="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50">
            Download full backup (JSON)
          </button>
        </div>
        <div className="mt-6 grid gap-4 md:grid-cols-2">
          <div>
            <label className="text-sm font-medium text-slate-800">Export customer JSON</label>
            <select className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" value={exportCustId} onChange={(e) => setExportCustId(e.target.value)}>
              <option value="">Select customer…</option>
              {customersForRetention.map((c) => (
                <option key={c.id} value={String(c.id)}>
                  {[c.first_name, c.last_name].filter(Boolean).join(" ")} · {c.email ?? c.id}
                </option>
              ))}
            </select>
            <button type="button" disabled={dataBusy} className="mt-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 disabled:opacity-50" onClick={() => void downloadCustomerExport()}>
              Download export
            </button>
          </div>
          <div>
            <label className="text-sm font-medium text-rose-900">GDPR delete (soft-delete + anonymize)</label>
            <select className="mt-1 w-full rounded-md border border-rose-200 px-3 py-2 text-sm" value={gdprCustId} onChange={(e) => setGdprCustId(e.target.value)}>
              <option value="">Select customer…</option>
              {customersForRetention.map((c) => (
                <option key={c.id} value={String(c.id)}>
                  {[c.first_name, c.last_name].filter(Boolean).join(" ")} · {c.email ?? c.id}
                </option>
              ))}
            </select>
            <label className="mt-2 flex items-center gap-2 text-sm text-rose-900">
              <input type="checkbox" checked={gdprConfirm} onChange={(e) => setGdprConfirm(e.target.checked)} />I understand this is irreversible for PII
            </label>
            <button type="button" disabled={dataBusy} className="mt-2 rounded-md bg-rose-700 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-800 disabled:opacity-50" onClick={() => void gdprDelete()}>
              Run GDPR delete
            </button>
          </div>
        </div>
      </section>
    </div>
  );
}

function UserAccessCard({
  userId,
  name,
  email,
  trashed,
  isAdmin,
  permissions,
  permissionOptions,
  applyRoleTemplateLabels,
  canManageUsers,
  isActorAdmin,
  currentUserId,
  onSave,
  onDeactivate,
  onRestore,
}: {
  userId: number;
  name: string;
  email: string;
  trashed: boolean;
  isAdmin: boolean;
  permissions: string[];
  permissionOptions: string[];
  applyRoleTemplateLabels: Record<string, string>;
  canManageUsers: boolean;
  isActorAdmin: boolean;
  currentUserId: number;
  onSave: (body: Record<string, unknown>) => void;
  onDeactivate: () => void;
  onRestore: () => void;
}) {
  const [localAdmin, setLocalAdmin] = useState(isAdmin);
  const [localPerms, setLocalPerms] = useState<Record<string, boolean>>(() => {
    const o: Record<string, boolean> = {};
    for (const p of permissionOptions) {
      o[p] = permissions.includes(p);
    }
    return o;
  });
  const [applyTpl, setApplyTpl] = useState("");

  useEffect(() => {
    setLocalAdmin(isAdmin);
    const o: Record<string, boolean> = {};
    for (const p of permissionOptions) {
      o[p] = permissions.includes(p);
    }
    setLocalPerms(o);
  }, [isAdmin, permissionOptions, permissions]);

  const applyKeys = Object.keys(applyRoleTemplateLabels);

  return (
    <div className={`rounded-lg border p-4 ${trashed ? "border-slate-200 bg-slate-50" : "border-slate-200"}`}>
      <div className="mb-3 flex flex-wrap items-start justify-between gap-2">
        <div>
          <p className="font-medium text-slate-900">{name}</p>
          <p className="text-xs text-slate-600">{email}</p>
          {trashed ? <span className="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">Deactivated</span> : null}
        </div>
        <div className="flex flex-wrap gap-2">
          {!trashed && canManageUsers && userId !== currentUserId ? (
            <button type="button" onClick={onDeactivate} className="rounded-md border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-800 hover:bg-rose-50">
              Deactivate
            </button>
          ) : null}
          {trashed && canManageUsers ? (
            <button type="button" onClick={onRestore} className="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-900 hover:bg-emerald-100">
              Restore
            </button>
          ) : null}
        </div>
      </div>
      {!trashed ? (
        <form
          className="space-y-3"
          onSubmit={(e) => {
            e.preventDefault();
            const permArr = Object.entries(localPerms)
              .filter(([, v]) => v)
              .map(([k]) => k);
            onSave({
              is_admin: isActorAdmin ? localAdmin : isAdmin,
              permissions: permArr,
              apply_role_template: applyTpl,
            });
          }}
        >
          {isActorAdmin ? (
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={localAdmin} onChange={(e) => setLocalAdmin(e.target.checked)} />
              Administrator
            </label>
          ) : (
            <p className="text-sm text-slate-600">
              Administrator: <span className="font-medium">{isAdmin ? "Yes" : "No"}</span>
              <span className="mt-1 block text-xs text-slate-500">Only full administrators can change this flag.</span>
            </p>
          )}
          <label className="block text-sm">
            Apply preset (optional)
            <select className="mt-1 w-full max-w-md rounded-md border border-slate-300 px-3 py-2 text-sm" value={applyTpl} onChange={(e) => setApplyTpl(e.target.value)}>
              <option value="">Use checkboxes below</option>
              {applyKeys.map((k) => (
                <option key={k} value={k}>
                  {applyRoleTemplateLabels[k] ?? k}
                </option>
              ))}
            </select>
          </label>
          <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            {permissionOptions.map((p) => (
              <label key={p} className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={Boolean(localPerms[p])} onChange={(e) => setLocalPerms((prev) => ({ ...prev, [p]: e.target.checked }))} />
                <span>{p.replace(/_/g, " ")}</span>
              </label>
            ))}
          </div>
          {isActorAdmin || canManageUsers ? (
            <button type="submit" className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">
              Save access
            </button>
          ) : null}
        </form>
      ) : (
        <p className="text-sm text-slate-600">Restore this user to edit access.</p>
      )}
    </div>
  );
}

function PromotionFormsSection({
  services,
  memberships,
  promotions,
  promoBusyId,
  onTogglePromotionActive,
  reload,
  setInlineErr,
  busy,
  setBusy,
}: {
  services: ServiceRow[];
  memberships: MembershipRow[];
  promotions: UnknownRec[];
  promoBusyId: number | null;
  onTogglePromotionActive: (p: UnknownRec, next: boolean) => void;
  reload: ReloadBoard;
  setInlineErr: (s: string | null) => void;
  busy: boolean;
  setBusy: (b: boolean) => void;
}) {
  const emptyCreate = (): PromotionCreateDraft => ({
    name: "",
    description: "",
    discount_type: "percent",
    discount_value: "",
    applies_to: "all",
    starts_on: "",
    ends_on: "",
    stackable: false,
    max_discount_cap: "",
    minimum_purchase: "",
    is_active: true,
    service_ids: {},
    membership_ids: {},
  });
  const [create, setCreate] = useState(emptyCreate);

  const buildPromotionBody = (state: PromotionCreateDraft) => {
    const service_ids = Object.entries(state.service_ids)
      .filter(([, v]) => v)
      .map(([k]) => Number(k));
    const membership_ids = Object.entries(state.membership_ids)
      .filter(([, v]) => v)
      .map(([k]) => Number(k));
    return {
      name: state.name,
      description: state.description || null,
      discount_type: state.discount_type,
      discount_value: Number(state.discount_value),
      applies_to: state.applies_to,
      starts_on: state.starts_on || null,
      ends_on: state.ends_on || null,
      stackable: state.stackable,
      max_discount_cap: state.max_discount_cap === "" ? null : Number(state.max_discount_cap),
      minimum_purchase: state.minimum_purchase === "" ? null : Number(state.minimum_purchase),
      is_active: state.is_active,
      service_ids,
      membership_ids,
    };
  };

  const submitCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setInlineErr(null);
    setBusy(true);
    try {
      const res = await spaFetch("/admin/promotions", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildPromotionBody(create)),
      });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setInlineErr(firstErrorMessage(b, "Could not create promotion."));
        return;
      }
      setCreate(emptyCreate());
      await reload();
    } catch {
      setInlineErr("Could not reach the server.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 className="text-lg font-semibold text-slate-900">Promotions &amp; discounts</h2>
      <p className="mt-1 text-xs text-slate-500">Toggle active status, create new promotions, or expand a row to edit rules.</p>
      <ul className="mt-4 divide-y divide-slate-100 border-b border-slate-100 pb-4">
        {promotions.length ? (
          promotions.map((p) => (
            <li key={String(p.id)} className="flex flex-wrap items-center justify-between gap-3 py-3">
              <div>
                <p className="font-medium text-slate-900">{asStr(p.name)}</p>
                <p className="text-xs text-slate-500">ID {String(p.id)}</p>
              </div>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={asBool(p.is_active)}
                  disabled={promoBusyId === Number(p.id)}
                  onChange={(ev) => onTogglePromotionActive(p, ev.target.checked)}
                />
                Active
              </label>
            </li>
          ))
        ) : (
          <li className="py-4 text-slate-500">No promotions yet.</li>
        )}
      </ul>
      <form className="mt-4 space-y-3" onSubmit={submitCreate}>
        <div className="grid gap-3 md:grid-cols-3">
          <label className="text-sm">
            Name
            <input className="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={create.name} onChange={(e) => setCreate((c) => ({ ...c, name: e.target.value }))} required />
          </label>
          <label className="text-sm">
            Type
            <select className="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={create.discount_type} onChange={(e) => setCreate((c) => ({ ...c, discount_type: e.target.value as "percent" | "fixed" }))}>
              <option value="percent">Percent</option>
              <option value="fixed">Fixed</option>
            </select>
          </label>
          <label className="text-sm">
            Value
            <input type="number" step="0.01" min={0} className="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={create.discount_value} onChange={(e) => setCreate((c) => ({ ...c, discount_value: e.target.value }))} required />
          </label>
          <label className="text-sm">
            Applies to
            <select className="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={create.applies_to} onChange={(e) => setCreate((c) => ({ ...c, applies_to: e.target.value as "all" | "services" | "memberships" }))}>
              <option value="all">All</option>
              <option value="services">Services</option>
              <option value="memberships">Memberships</option>
            </select>
          </label>
          <label className="text-sm">
            Starts on
            <input type="date" className="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={create.starts_on} onChange={(e) => setCreate((c) => ({ ...c, starts_on: e.target.value }))} />
          </label>
          <label className="text-sm">
            Ends on
            <input type="date" className="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={create.ends_on} onChange={(e) => setCreate((c) => ({ ...c, ends_on: e.target.value }))} />
          </label>
        </div>
        <label className="text-sm">
          Description
          <textarea rows={2} className="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={create.description} onChange={(e) => setCreate((c) => ({ ...c, description: e.target.value }))} />
        </label>
        <div className="grid gap-4 md:grid-cols-2">
          <div>
            <p className="mb-1 text-xs font-semibold uppercase text-slate-500">Target services</p>
            <div className="max-h-40 space-y-1 overflow-y-auto rounded border border-slate-200 p-2 text-sm">
              {services.map((s) => (
                <label key={s.id} className="flex items-center gap-2">
                  <input type="checkbox" checked={Boolean(create.service_ids[s.id])} onChange={(e) => setCreate((c) => ({ ...c, service_ids: { ...c.service_ids, [s.id]: e.target.checked } }))} />
                  {s.name}
                </label>
              ))}
            </div>
          </div>
          <div>
            <p className="mb-1 text-xs font-semibold uppercase text-slate-500">Target memberships</p>
            <div className="max-h-40 space-y-1 overflow-y-auto rounded border border-slate-200 p-2 text-sm">
              {memberships.map((m) => (
                <label key={m.id} className="flex items-center gap-2">
                  <input type="checkbox" checked={Boolean(create.membership_ids[m.id])} onChange={(e) => setCreate((c) => ({ ...c, membership_ids: { ...c.membership_ids, [m.id]: e.target.checked } }))} />
                  {m.name}
                </label>
              ))}
            </div>
          </div>
        </div>
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={create.stackable} onChange={(e) => setCreate((c) => ({ ...c, stackable: e.target.checked }))} />
          Stackable
        </label>
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={create.is_active} onChange={(e) => setCreate((c) => ({ ...c, is_active: e.target.checked }))} />
          Active
        </label>
        <button type="submit" disabled={busy} className="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700 disabled:opacity-50">
          {busy ? "Saving…" : "Add promotion"}
        </button>
      </form>

      <div className="mt-8 space-y-4">
        {promotions.map((p) => (
          <PromotionEditBlock key={String(p.id)} promotion={p} services={services} memberships={memberships} reload={reload} setInlineErr={setInlineErr} setBusy={setBusy} busy={busy} />
        ))}
      </div>
    </section>
  );
}

function PromotionEditBlock({
  promotion,
  services,
  memberships,
  reload,
  setInlineErr,
  busy,
  setBusy,
}: {
  promotion: UnknownRec;
  services: ServiceRow[];
  memberships: MembershipRow[];
  reload: ReloadBoard;
  setInlineErr: (s: string | null) => void;
  busy: boolean;
  setBusy: (b: boolean) => void;
}) {
  const id = Number(promotion.id);
  const [open, setOpen] = useState(false);
  const [name, setName] = useState(asStr(promotion.name));
  const [description, setDescription] = useState(asStr(promotion.description));
  const [discountType, setDiscountType] = useState(asStr(promotion.discount_type) === "fixed" ? "fixed" : "percent");
  const [discountValue, setDiscountValue] = useState(asStr(promotion.discount_value));
  const [appliesTo, setAppliesTo] = useState(asStr(promotion.applies_to) || "all");
  const [startsOn, setStartsOn] = useState(asStr(promotion.starts_on).slice(0, 10));
  const [endsOn, setEndsOn] = useState(asStr(promotion.ends_on).slice(0, 10));
  const [stackable, setStackable] = useState(asBool(promotion.stackable));
  const [maxCap, setMaxCap] = useState(promotion.max_discount_cap != null ? asStr(promotion.max_discount_cap) : "");
  const [minPurchase, setMinPurchase] = useState(promotion.minimum_purchase != null ? asStr(promotion.minimum_purchase) : "");
  const [isActive, setIsActive] = useState(asBool(promotion.is_active));
  const [svc, setSvc] = useState<Record<number, boolean>>({});
  const [mem, setMem] = useState<Record<number, boolean>>({});

  useEffect(() => {
    setName(asStr(promotion.name));
    setDescription(asStr(promotion.description));
    setDiscountType(asStr(promotion.discount_type) === "fixed" ? "fixed" : "percent");
    setDiscountValue(asStr(promotion.discount_value));
    setAppliesTo(asStr(promotion.applies_to) || "all");
    const so = promotion.starts_on;
    const eo = promotion.ends_on;
    setStartsOn(so != null && so !== "" ? String(so).slice(0, 10) : "");
    setEndsOn(eo != null && eo !== "" ? String(eo).slice(0, 10) : "");
    setStackable(asBool(promotion.stackable));
    setMaxCap(promotion.max_discount_cap != null ? asStr(promotion.max_discount_cap) : "");
    setMinPurchase(promotion.minimum_purchase != null ? asStr(promotion.minimum_purchase) : "");
    setIsActive(asBool(promotion.is_active));
    const sids = new Set(targetedIds(promotion, "targeted_services"));
    const mids = new Set(targetedIds(promotion, "targeted_memberships"));
    const s: Record<number, boolean> = {};
    const m: Record<number, boolean> = {};
    for (const x of services) {
      s[x.id] = sids.has(x.id);
    }
    for (const x of memberships) {
      m[x.id] = mids.has(x.id);
    }
    setSvc(s);
    setMem(m);
  }, [promotion, services, memberships]);

  const save = async (e: React.FormEvent) => {
    e.preventDefault();
    setInlineErr(null);
    setBusy(true);
    try {
      const service_ids = Object.entries(svc)
        .filter(([, v]) => v)
        .map(([k]) => Number(k));
      const membership_ids = Object.entries(mem)
        .filter(([, v]) => v)
        .map(([k]) => Number(k));
      const res = await spaFetch(`/admin/promotions/${id}`, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name,
          description: description || null,
          discount_type: discountType,
          discount_value: Number(discountValue),
          applies_to: appliesTo,
          starts_on: startsOn || null,
          ends_on: endsOn || null,
          stackable,
          max_discount_cap: maxCap === "" ? null : Number(maxCap),
          minimum_purchase: minPurchase === "" ? null : Number(minPurchase),
          is_active: isActive,
          service_ids,
          membership_ids,
        }),
      });
      const b = await res.json().catch(() => ({}));
      if (!res.ok) {
        setInlineErr(firstErrorMessage(b, "Could not update promotion."));
        return;
      }
      await reload();
    } catch {
      setInlineErr("Could not reach the server.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <details className="rounded-lg border border-slate-200 p-3" open={open} onToggle={(e) => setOpen((e.target as HTMLDetailsElement).open)}>
      <summary className="cursor-pointer text-sm font-semibold text-slate-900">
        Edit: {asStr(promotion.name)} (ID {id})
      </summary>
      <form className="mt-3 space-y-3" onSubmit={save}>
        <div className="grid gap-2 md:grid-cols-2">
          <label className="text-sm">
            Name
            <input className="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={name} onChange={(e) => setName(e.target.value)} required />
          </label>
          <label className="text-sm">
            Type
            <select className="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={discountType} onChange={(e) => setDiscountType(e.target.value)}>
              <option value="percent">Percent</option>
              <option value="fixed">Fixed</option>
            </select>
          </label>
          <label className="text-sm">
            Value
            <input type="number" step="0.01" min={0} className="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={discountValue} onChange={(e) => setDiscountValue(e.target.value)} required />
          </label>
          <label className="text-sm">
            Applies to
            <select className="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={appliesTo} onChange={(e) => setAppliesTo(e.target.value)}>
              <option value="all">All</option>
              <option value="services">Services</option>
              <option value="memberships">Memberships</option>
            </select>
          </label>
        </div>
        <textarea rows={2} className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" value={description} onChange={(e) => setDescription(e.target.value)} placeholder="Description" />
        <div className="grid gap-4 md:grid-cols-2">
          <div className="max-h-36 space-y-1 overflow-y-auto rounded border p-2 text-sm">
            {services.map((s) => (
              <label key={s.id} className="flex gap-2">
                <input type="checkbox" checked={Boolean(svc[s.id])} onChange={(e) => setSvc((prev) => ({ ...prev, [s.id]: e.target.checked }))} />
                {s.name}
              </label>
            ))}
          </div>
          <div className="max-h-36 space-y-1 overflow-y-auto rounded border p-2 text-sm">
            {memberships.map((m) => (
              <label key={m.id} className="flex gap-2">
                <input type="checkbox" checked={Boolean(mem[m.id])} onChange={(e) => setMem((prev) => ({ ...prev, [m.id]: e.target.checked }))} />
                {m.name}
              </label>
            ))}
          </div>
        </div>
        <label className="flex gap-2 text-sm">
          <input type="checkbox" checked={stackable} onChange={(e) => setStackable(e.target.checked)} />
          Stackable
        </label>
        <label className="flex gap-2 text-sm">
          <input type="checkbox" checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />
          Active
        </label>
        <button type="submit" disabled={busy} className="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-900 disabled:opacity-50">
          Save rules
        </button>
      </form>
    </details>
  );
}
