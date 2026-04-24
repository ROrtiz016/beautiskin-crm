"use client";

export function SpaJsonBody({ value }: { value: unknown }) {
  return (
    <pre className="max-h-[70vh] overflow-auto rounded-lg border border-slate-200 bg-slate-900/90 p-4 text-xs text-slate-100">
      {JSON.stringify(value, null, 2)}
    </pre>
  );
}
