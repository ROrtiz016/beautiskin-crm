import countriesRaw from "@/data/countries-raw.json";

export type GeoOption = { code: string; name: string };

/** US states + DC; values are USPS 2-letter codes. */
export const US_STATE_OPTIONS: GeoOption[] = [
  { code: "AL", name: "Alabama" },
  { code: "AK", name: "Alaska" },
  { code: "AZ", name: "Arizona" },
  { code: "AR", name: "Arkansas" },
  { code: "CA", name: "California" },
  { code: "CO", name: "Colorado" },
  { code: "CT", name: "Connecticut" },
  { code: "DE", name: "Delaware" },
  { code: "DC", name: "District of Columbia" },
  { code: "FL", name: "Florida" },
  { code: "GA", name: "Georgia" },
  { code: "HI", name: "Hawaii" },
  { code: "ID", name: "Idaho" },
  { code: "IL", name: "Illinois" },
  { code: "IN", name: "Indiana" },
  { code: "IA", name: "Iowa" },
  { code: "KS", name: "Kansas" },
  { code: "KY", name: "Kentucky" },
  { code: "LA", name: "Louisiana" },
  { code: "ME", name: "Maine" },
  { code: "MD", name: "Maryland" },
  { code: "MA", name: "Massachusetts" },
  { code: "MI", name: "Michigan" },
  { code: "MN", name: "Minnesota" },
  { code: "MS", name: "Mississippi" },
  { code: "MO", name: "Missouri" },
  { code: "MT", name: "Montana" },
  { code: "NE", name: "Nebraska" },
  { code: "NV", name: "Nevada" },
  { code: "NH", name: "New Hampshire" },
  { code: "NJ", name: "New Jersey" },
  { code: "NM", name: "New Mexico" },
  { code: "NY", name: "New York" },
  { code: "NC", name: "North Carolina" },
  { code: "ND", name: "North Dakota" },
  { code: "OH", name: "Ohio" },
  { code: "OK", name: "Oklahoma" },
  { code: "OR", name: "Oregon" },
  { code: "PA", name: "Pennsylvania" },
  { code: "RI", name: "Rhode Island" },
  { code: "SC", name: "South Carolina" },
  { code: "SD", name: "South Dakota" },
  { code: "TN", name: "Tennessee" },
  { code: "TX", name: "Texas" },
  { code: "UT", name: "Utah" },
  { code: "VT", name: "Vermont" },
  { code: "VA", name: "Virginia" },
  { code: "WA", name: "Washington" },
  { code: "WV", name: "West Virginia" },
  { code: "WI", name: "Wisconsin" },
  { code: "WY", name: "Wyoming" },
].sort((a, b) => a.name.localeCompare(b.name));

type RawCountry = { name: string; "alpha-2": string };

/** ISO 3166-1 alpha-2 codes; sorted by display name. */
export const COUNTRY_OPTIONS: GeoOption[] = (countriesRaw as RawCountry[])
  .map((r) => ({
    code: r["alpha-2"],
    name: r["alpha-2"] === "US" ? "United States" : r.name,
  }))
  .sort((a, b) => a.name.localeCompare(b.name));

export const DEFAULT_COUNTRY_CODE = "US";

const stateNameByCode = Object.fromEntries(US_STATE_OPTIONS.map((s) => [s.code, s.name]));
const countryNameByCode = Object.fromEntries(COUNTRY_OPTIONS.map((c) => [c.code, c.name]));
const countryCodes = new Set(COUNTRY_OPTIONS.map((c) => c.code));
const stateCodes = new Set(US_STATE_OPTIONS.map((s) => s.code));

export function isKnownUsStateCode(code: string): boolean {
  return stateCodes.has(code);
}

export function isKnownCountryCode(code: string): boolean {
  return countryCodes.has(code.toUpperCase());
}

/**
 * Map legacy / alias country values to ISO 3166-1 alpha-2 for selects and storage.
 * e.g. "USA", "US", "United States" → "US". Unknown multi-char strings are returned as-is (orphan).
 */
export function normalizeCountryToIso2(input: string | null | undefined): string {
  const s = (input ?? "").trim();
  if (!s) {
    return "";
  }
  const u = s.toUpperCase();
  if (u === "USA" || u === "US") {
    return "US";
  }
  const collapsed = s.replace(/\s+/g, " ").trim();
  if (/^united states(\s+of\s+america)?$/i.test(collapsed)) {
    return "US";
  }
  if (u.length === 2 && countryCodes.has(u)) {
    return u;
  }
  return s;
}

/** Resolve USPS code to full state name, or return legacy free-text value. */
export function formatUsStateLabel(value: string | null | undefined): string {
  const v = (value ?? "").trim();
  if (!v) {
    return "";
  }
  return stateNameByCode[v] ?? v;
}

/** Resolve ISO alpha-2 (or common aliases like USA) to display name, or return legacy free-text value. */
export function formatCountryLabel(value: string | null | undefined): string {
  const v = (value ?? "").trim();
  if (!v) {
    return "";
  }
  const iso = normalizeCountryToIso2(v);
  if (iso.length === 2 && countryNameByCode[iso]) {
    return countryNameByCode[iso];
  }
  return v;
}
