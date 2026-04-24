"use client";

import { SpaPageFrame } from "@/components/spa-page-frame";
import { useSpaGet } from "@/hooks/use-spa-get";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { FormEvent, useMemo, useTransition } from "react";

type Row = {
  id: number;
  first_name: string;
  last_name: string;
  email: string | null;
  phone: string | null;
  date_of_birth?: string | null;
  appointments_count?: number;
};

type PaginatorSlice = {
  data: Row[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

type CustomersIndexPayload = {
  customers: PaginatorSlice;
  search: string;
  sort: string;
  direction: string;
};

const SORT_COLUMNS = ["name", "email", "phone", "date_of_birth", "appointments_count", "created_at"] as const;
type SortColumn = (typeof SORT_COLUMNS)[number];

function isSortColumn(v: string): v is SortColumn {
  return (SORT_COLUMNS as readonly string[]).includes(v);
}

function spaCustomersPath(search: string, sort: string, direction: string, page: string): string {
  const qs = new URLSearchParams();
  if (search.trim()) {
    qs.set("search", search.trim());
  }
  qs.set("sort", sort);
  qs.set("direction", direction);
  if (page !== "1" && page !== "") {
    qs.set("page", page);
  }
  const q = qs.toString();
  return `/spa/customers${q ? `?${q}` : ""}`;
}

function customersPageHref(overrides: {
  search?: string;
  sort?: string;
  direction?: string;
  page?: string;
}): string {
  const search = overrides.search ?? "";
  const sort = overrides.sort ?? "created_at";
  const direction = overrides.direction ?? "desc";
  const page = overrides.page ?? "1";
  const qs = new URLSearchParams();
  if (search.trim()) {
    qs.set("search", search.trim());
  }
  qs.set("sort", sort);
  qs.set("direction", direction);
  if (page !== "1") {
    qs.set("page", page);
  }
  const q = qs.toString();
  return `/customers${q ? `?${q}` : ""}`;
}

function sortColumnHref(search: string, sort: string, direction: string, column: SortColumn): string {
  const nextDir = sort === column && direction === "asc" ? "desc" : "asc";
  return customersPageHref({ search, sort: column, direction: nextDir, page: "1" });
}

function sortArrow(sort: string, direction: string, column: SortColumn): string {
  return sort === column ? (direction === "asc" ? " ↑" : " ↓") : "";
}

const sortHeaderButtonClass =
  "cursor-pointer rounded text-left font-medium text-slate-600 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-400";

export function CustomersListClient() {
  const router = useRouter();
  const sp = useSearchParams();
  const [isNavPending, startTransition] = useTransition();
  const search = sp.get("search") ?? "";
  const sortRaw = sp.get("sort") ?? "created_at";
  const sort = isSortColumn(sortRaw) ? sortRaw : "created_at";
  const direction = sp.get("direction") === "asc" ? "asc" : "desc";
  const page = sp.get("page") ?? "1";

  const apiPath = useMemo(() => spaCustomersPath(search, sort, direction, page), [search, sort, direction, page]);

  const { data, error, loading, isRefreshing } = useSpaGet<CustomersIndexPayload>(apiPath);

  const pushListUrl = (href: string) => {
    startTransition(() => {
      router.push(href, { scroll: false });
    });
  };

  const customers = data?.customers;
  const rows = customers?.data ?? [];
  const resolvedSearch = data?.search ?? search;

  const onSearchSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    const q = String(fd.get("search") ?? "").trim();
    pushListUrl(customersPageHref({ search: q, sort, direction, page: "1" }));
  };

  return (
    <SpaPageFrame
      title="Customers"
      subtitle={customers ? `${customers.total} total` : undefined}
      loading={loading}
      error={error}
    >
      <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <p className="max-w-xl text-sm leading-relaxed text-slate-600">
          Search and sort the directory, then open a profile for full history and booking.
        </p>
        <Link
          href="/customers/new"
          className="inline-flex shrink-0 rounded-md bg-pink-600 px-3 py-2 text-sm font-semibold text-white hover:bg-pink-700"
        >
          + New customer
        </Link>
      </div>

      <section
        className={`relative rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-opacity ${isRefreshing || isNavPending ? "opacity-60" : "opacity-100"}`}
        aria-busy={isRefreshing || isNavPending}
      >
        <form className="mb-4 flex flex-wrap gap-2" onSubmit={onSearchSubmit}>
          <input
            name="search"
            defaultValue={resolvedSearch}
            placeholder="Search by name, email, or phone"
            className="min-w-[200px] flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm"
          />
          <button type="submit" className="rounded-md border border-slate-300 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100">
            Search
          </button>
        </form>

        {customers ? (
          <>
            <div className="overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead className="border-b border-slate-200 bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500">
                  <tr>
                    <th className="px-4 py-3">
                      <button
                        type="button"
                        onClick={() => pushListUrl(sortColumnHref(resolvedSearch, sort, direction, "name"))}
                        className={sortHeaderButtonClass}
                      >
                        Name{sortArrow(sort, direction, "name")}
                      </button>
                    </th>
                    <th className="px-4 py-3">
                      <button
                        type="button"
                        onClick={() => pushListUrl(sortColumnHref(resolvedSearch, sort, direction, "email"))}
                        className={sortHeaderButtonClass}
                      >
                        Email{sortArrow(sort, direction, "email")}
                      </button>
                    </th>
                    <th className="px-4 py-3">
                      <button
                        type="button"
                        onClick={() => pushListUrl(sortColumnHref(resolvedSearch, sort, direction, "phone"))}
                        className={sortHeaderButtonClass}
                      >
                        Phone{sortArrow(sort, direction, "phone")}
                      </button>
                    </th>
                    <th className="px-4 py-3">
                      <button
                        type="button"
                        onClick={() => pushListUrl(sortColumnHref(resolvedSearch, sort, direction, "date_of_birth"))}
                        className={sortHeaderButtonClass}
                      >
                        DOB{sortArrow(sort, direction, "date_of_birth")}
                      </button>
                    </th>
                    <th className="px-4 py-3">
                      <button
                        type="button"
                        onClick={() => pushListUrl(sortColumnHref(resolvedSearch, sort, direction, "appointments_count"))}
                        className={sortHeaderButtonClass}
                      >
                        Appointments{sortArrow(sort, direction, "appointments_count")}
                      </button>
                    </th>
                    <th className="px-4 py-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {rows.length ? (
                    rows.map((c) => (
                      <tr key={c.id} className="hover:bg-slate-50/80">
                        <td className="px-4 py-3">
                          <Link href={`/customers/${c.id}`} className="font-medium text-pink-700 hover:underline">
                            {c.first_name} {c.last_name}
                          </Link>
                        </td>
                        <td className="px-4 py-3 text-slate-600">{c.email ?? "—"}</td>
                        <td className="px-4 py-3 text-slate-600">{c.phone ?? "—"}</td>
                        <td className="px-4 py-3 text-slate-600">{c.date_of_birth ? String(c.date_of_birth).slice(0, 10) : "—"}</td>
                        <td className="px-4 py-3 text-slate-600">{c.appointments_count ?? "—"}</td>
                        <td className="px-4 py-3 text-right text-sm">
                          <Link href={`/customers/${c.id}`} className="mr-3 font-medium text-slate-700 hover:text-slate-900">
                            Profile
                          </Link>
                          <Link href={`/customers/${c.id}/edit`} className="font-medium text-slate-700 hover:text-slate-900">
                            Edit
                          </Link>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                        No customers found.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>

            {customers.last_page > 1 ? (
              <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 text-sm text-slate-600">
                <p>
                  Page {customers.current_page} of {customers.last_page}
                  {customers.per_page ? ` · ${customers.per_page} per page` : ""}
                </p>
                <div className="flex gap-2">
                  {customers.current_page > 1 ? (
                    <button
                      type="button"
                      onClick={() =>
                        pushListUrl(
                          customersPageHref({
                            search: resolvedSearch,
                            sort,
                            direction,
                            page: String(customers.current_page - 1),
                          }),
                        )
                      }
                      className="rounded-md border border-slate-300 px-3 py-1.5 font-semibold text-slate-800 hover:bg-slate-50"
                    >
                      Previous
                    </button>
                  ) : (
                    <span className="rounded-md border border-slate-100 px-3 py-1.5 text-slate-400">Previous</span>
                  )}
                  {customers.current_page < customers.last_page ? (
                    <button
                      type="button"
                      onClick={() =>
                        pushListUrl(
                          customersPageHref({
                            search: resolvedSearch,
                            sort,
                            direction,
                            page: String(customers.current_page + 1),
                          }),
                        )
                      }
                      className="rounded-md border border-slate-300 px-3 py-1.5 font-semibold text-slate-800 hover:bg-slate-50"
                    >
                      Next
                    </button>
                  ) : (
                    <span className="rounded-md border border-slate-100 px-3 py-1.5 text-slate-400">Next</span>
                  )}
                </div>
              </div>
            ) : null}
          </>
        ) : null}
      </section>
    </SpaPageFrame>
  );
}
