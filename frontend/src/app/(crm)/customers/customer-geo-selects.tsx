"use client";

import {
  COUNTRY_OPTIONS,
  DEFAULT_COUNTRY_CODE,
  US_STATE_OPTIONS,
  isKnownCountryCode,
  isKnownUsStateCode,
  normalizeCountryToIso2,
} from "@/lib/geo-select-options";
import { useEffect, useRef } from "react";

export function UsStateSelect({
  id,
  value,
  onChange,
  error,
}: {
  id?: string;
  value: string | null;
  onChange: (next: string | null) => void;
  error?: string;
}) {
  const v = value ?? "";
  const orphan = Boolean(v) && !isKnownUsStateCode(v);

  return (
    <>
      <select
        id={id}
        value={v}
        onChange={(ev) => onChange(ev.target.value || null)}
        className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
        autoComplete="address-level1"
      >
        <option value="">—</option>
        {orphan ? (
          <option value={v}>
            {v}
          </option>
        ) : null}
        {US_STATE_OPTIONS.map((s) => (
          <option key={s.code} value={s.code}>
            {s.name}
          </option>
        ))}
      </select>
      {error ? <p className="mt-1 text-xs text-rose-600">{error}</p> : null}
    </>
  );
}

export function CountrySelect({
  id,
  value,
  onChange,
  error,
  /** When true, empty/null value displays as United States (ISO US). */
  defaultToUnitedStates = false,
}: {
  id?: string;
  value: string | null;
  onChange: (next: string | null) => void;
  error?: string;
  defaultToUnitedStates?: boolean;
}) {
  const raw = (value ?? "").trim();
  const canonical = normalizeCountryToIso2(value);
  const orphan = Boolean(canonical) && !isKnownCountryCode(canonical);
  const display = orphan ? canonical : canonical || (defaultToUnitedStates ? DEFAULT_COUNTRY_CODE : "");

  const lastAliasSyncRaw = useRef<string | null>(null);
  useEffect(() => {
    if (raw === "") {
      lastAliasSyncRaw.current = null;
      return;
    }
    if (canonical === raw) {
      lastAliasSyncRaw.current = null;
      return;
    }
    if (isKnownCountryCode(canonical) && lastAliasSyncRaw.current !== raw) {
      lastAliasSyncRaw.current = raw;
      onChange(canonical);
    }
  }, [raw, canonical, onChange]);

  return (
    <>
      <select
        id={id}
        value={display}
        onChange={(ev) => onChange(ev.target.value || null)}
        className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
        autoComplete="country-name"
      >
        <option value="">—</option>
        {orphan ? (
          <option value={canonical}>
            {canonical}
          </option>
        ) : null}
        {COUNTRY_OPTIONS.map((c) => (
          <option key={c.code} value={c.code}>
            {c.name}
          </option>
        ))}
      </select>
      {error ? <p className="mt-1 text-xs text-rose-600">{error}</p> : null}
    </>
  );
}
