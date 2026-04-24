export function ymd(raw: string): string {
  return raw.length >= 10 ? raw.slice(0, 10) : raw;
}

export function ymFromMonthBase(raw: string): string {
  return ymd(raw).slice(0, 7);
}

/** e.g. "2026-04" → "April 2026" (locale month name, 4-digit year). */
export function formatMonthYearLabel(ym: string): string {
  const parts = ym.split("-");
  if (parts.length < 2) {
    return ym;
  }
  const y = Number(parts[0]);
  const m = Number(parts[1]);
  if (!Number.isFinite(y) || !Number.isFinite(m) || m < 1 || m > 12) {
    return ym;
  }
  const d = new Date(y, m - 1, 1);
  return d.toLocaleDateString(undefined, { month: "long", year: "numeric" });
}

export function shiftMonth(ym: string, delta: number): string {
  const [y, m] = ym.split("-").map(Number);
  const d = new Date(y, m - 1 + delta, 1);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
}

export function formatTimeRange(startIso: string | null | undefined, endIso: string | null | undefined): string {
  if (!startIso) {
    return "—";
  }
  try {
    const s = new Date(startIso);
    const start = s.toLocaleTimeString(undefined, { hour: "numeric", minute: "2-digit" });
    if (!endIso) {
      return `${start} – TBD`;
    }
    const e = new Date(endIso);
    return `${start} – ${e.toLocaleTimeString(undefined, { hour: "numeric", minute: "2-digit" })}`;
  } catch {
    return startIso;
  }
}

export function toDatetimeLocalInput(d: Date = new Date()): string {
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function statusBadgeClass(status: string): string {
  switch (status) {
    case "completed":
      return "bg-emerald-100 text-emerald-800";
    case "booked":
      return "bg-blue-100 text-blue-800";
    case "cancelled":
      return "bg-rose-100 text-rose-800";
    case "no_show":
      return "bg-amber-100 text-amber-800";
    default:
      return "bg-slate-100 text-slate-700";
  }
}
