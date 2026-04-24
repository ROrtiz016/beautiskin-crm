"use client";

import { useMemo, useRef, useState, useEffect } from "react";

export type ComboboxCustomer = {
  id: number;
  first_name: string;
  last_name: string;
  email?: string | null;
  phone?: string | null;
};

function label(c: ComboboxCustomer): string {
  return `${c.first_name} ${c.last_name}`.trim();
}

export function CustomerFilterCombobox({
  customers,
  value,
  onValueChange,
}: {
  customers: ComboboxCustomer[];
  value: string;
  onValueChange: (customerId: string) => void;
}) {
  const [query, setQuery] = useState("");
  const [open, setOpen] = useState(false);
  const [changeMode, setChangeMode] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);

  const selected = useMemo(
    () => customers.find((c) => String(c.id) === value),
    [customers, value],
  );

  useEffect(() => {
    if (!value) {
      setQuery("");
      setChangeMode(false);
    }
  }, [value]);

  const filtered = useMemo(() => {
    const t = query.trim().toLowerCase();
    if (!t) {
      return customers.slice(0, 25);
    }
    return customers
      .filter((c) => {
        const name = label(c).toLowerCase();
        const email = (c.email ?? "").toLowerCase();
        const phone = c.phone ?? "";
        return (
          name.includes(t) ||
          email.includes(t) ||
          phone.includes(t) ||
          String(c.id) === t
        );
      })
      .slice(0, 50);
  }, [customers, query]);

  useEffect(() => {
    function onDocMouseDown(ev: MouseEvent) {
      if (!rootRef.current?.contains(ev.target as Node)) {
        setOpen(false);
      }
    }
    document.addEventListener("mousedown", onDocMouseDown);
    return () => document.removeEventListener("mousedown", onDocMouseDown);
  }, []);

  const showPicker = !value || changeMode;

  return (
    <div ref={rootRef} className="relative">
      <label className="text-xs font-medium text-slate-700">Customer</label>
      {value && !changeMode ? (
        <div className="mt-1 flex flex-wrap items-center gap-2">
          <input
            type="text"
            readOnly
            className="min-w-0 flex-1 rounded border border-slate-200 bg-slate-50 px-2 py-1 text-sm text-slate-900"
            value={selected ? label(selected) : `Customer #${value} (not in quick list)`}
          />
          <button
            type="button"
            className="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50"
            onClick={() => {
              setChangeMode(true);
              setQuery("");
              setOpen(true);
            }}
          >
            Change
          </button>
          <button
            type="button"
            className="rounded border border-slate-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50"
            onClick={() => {
              onValueChange("");
              setChangeMode(false);
              setQuery("");
            }}
          >
            Clear
          </button>
        </div>
      ) : (
        <>
          <div className="mt-1 flex gap-2">
            <input
              type="text"
              autoComplete="off"
              placeholder="Search name, email, phone, or id…"
              className="block min-w-0 flex-1 rounded border border-slate-300 px-2 py-1 text-sm"
              value={query}
              onChange={(ev) => {
                setQuery(ev.target.value);
                setOpen(true);
              }}
              onFocus={() => setOpen(true)}
            />
            {changeMode ? (
              <button
                type="button"
                className="shrink-0 rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50"
                onClick={() => {
                  setChangeMode(false);
                  setQuery("");
                  setOpen(false);
                }}
              >
                Cancel
              </button>
            ) : null}
          </div>
          {open && showPicker && filtered.length > 0 ? (
            <ul className="absolute z-30 mt-1 max-h-48 w-full overflow-auto rounded-md border border-slate-200 bg-white py-1 text-sm shadow-lg">
              {filtered.map((c) => (
                <li key={c.id}>
                  <button
                    type="button"
                    className="block w-full px-3 py-2 text-left hover:bg-pink-50"
                    onMouseDown={(ev) => ev.preventDefault()}
                    onClick={() => {
                      onValueChange(String(c.id));
                      setQuery(label(c));
                      setOpen(false);
                      setChangeMode(false);
                    }}
                  >
                    <span className="font-medium text-slate-900">{label(c)}</span>
                    <span className="mt-0.5 block text-xs text-slate-500">
                      {c.email ?? "—"}
                      {c.phone ? ` · ${c.phone}` : ""}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          ) : null}
        </>
      )}
    </div>
  );
}
