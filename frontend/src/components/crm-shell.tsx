"use client";

import { useAuth } from "@/context/auth-context";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useCallback, useEffect, useId, useState } from "react";

const SIDEBAR_COLLAPSED_KEY = "beautiskin-crm-sidebar-collapsed";

const dailyNav = [
  { href: "/customers", label: "Customers", abbreviated: "Cu" },
  { href: "/tasks", label: "Tasks", abbreviated: "Ta" },
  { href: "/appointments", label: "Appointments", abbreviated: "Ap" },
  { href: "/services", label: "Services", abbreviated: "Se" },
  { href: "/inventory", label: "Inventory", abbreviated: "In" },
  { href: "/packages", label: "Packages", abbreviated: "Pk" },
  { href: "/quotes", label: "Quotes", abbreviated: "Qu" },
  { href: "/memberships", label: "Memberships", abbreviated: "Me" },
] as const;

function navLinkClass(active: boolean): string {
  return active
    ? "bg-pink-600 text-white shadow-sm ring-1 ring-pink-700/20"
    : "text-slate-600 hover:bg-slate-100 hover:text-slate-900";
}

function ChevronLeftIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 20 20" fill="currentColor" aria-hidden>
      <path
        fillRule="evenodd"
        d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
        clipRule="evenodd"
      />
    </svg>
  );
}

function ChevronRightIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 20 20" fill="currentColor" aria-hidden>
      <path
        fillRule="evenodd"
        d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
        clipRule="evenodd"
      />
    </svg>
  );
}

function LogoutIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 20 20" fill="currentColor" aria-hidden>
      <path
        fillRule="evenodd"
        d="M3 4a1 1 0 011-1h6a1 1 0 110 2H5v10h4a1 1 0 110 2H4a1 1 0 01-1-1V4zm10.293.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L15.586 10l-2.293-2.293a1 1 0 010-1.414z"
        clipRule="evenodd"
      />
    </svg>
  );
}

export function CrmShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const { user, logout } = useAuth();
  const sidebarRegionId = useId();
  const [collapsed, setCollapsed] = useState(false);

  useEffect(() => {
    try {
      setCollapsed(localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === "1");
    } catch {
      /* ignore */
    }
  }, []);

  const setCollapsedPersist = useCallback((next: boolean) => {
    setCollapsed(next);
    try {
      localStorage.setItem(SIDEBAR_COLLAPSED_KEY, next ? "1" : "0");
    } catch {
      /* ignore */
    }
  }, []);

  const toggleCollapsed = useCallback(() => {
    setCollapsedPersist(!collapsed);
  }, [collapsed, setCollapsedPersist]);

  const expandedWidth = "w-48 sm:w-52 lg:w-56";
  const asideWidth = collapsed ? "w-14" : expandedWidth;

  return (
    <div className="min-h-screen bg-gradient-to-b from-slate-200 via-slate-100 to-slate-200 text-slate-900">
      <div className="flex min-h-screen">
        <aside
          id={sidebarRegionId}
          className={`sticky top-0 flex h-screen shrink-0 flex-col border-r border-slate-300/90 bg-white/95 shadow-sm shadow-slate-900/5 backdrop-blur-md transition-[width] duration-200 ease-out ${asideWidth}`}
          aria-label="App sidebar"
        >
          <div
            className={`flex shrink-0 items-start gap-1 border-b border-slate-200 py-3 ${collapsed ? "flex-col px-1.5" : "justify-between px-3 sm:px-4"}`}
          >
            {collapsed ? (
              <Link
                href="/"
                className="mx-auto flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-pink-600 text-xs font-bold text-white shadow-sm ring-1 ring-pink-700/25 hover:bg-pink-700"
                title="BeautiSkin CRM — Home"
              >
                B
              </Link>
            ) : (
              <div className="min-w-0 flex-1">
                <Link
                  href="/"
                  className="block text-base font-bold tracking-tight text-slate-900 hover:text-pink-700"
                >
                  BeautiSkin CRM
                </Link>
                <p className="mt-1.5 text-[10px] font-medium uppercase leading-snug tracking-wide text-slate-500">
                  Clinic workspace
                </p>
              </div>
            )}
            <button
              type="button"
              onClick={toggleCollapsed}
              className={`flex shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white p-1.5 text-slate-600 shadow-sm transition hover:border-pink-200 hover:bg-pink-50 hover:text-pink-800 ${collapsed ? "mx-auto mt-1" : ""}`}
              title={collapsed ? "Expand sidebar" : "Collapse sidebar"}
              aria-expanded={!collapsed}
              aria-controls={sidebarRegionId}
            >
              {collapsed ? (
                <>
                  <ChevronRightIcon className="h-4 w-4" />
                  <span className="sr-only">Expand sidebar</span>
                </>
              ) : (
                <>
                  <ChevronLeftIcon className="h-4 w-4" />
                  <span className="sr-only">Collapse sidebar</span>
                </>
              )}
            </button>
          </div>

          <nav
            className={`min-h-0 flex-1 overflow-y-auto overflow-x-hidden py-3 ${collapsed ? "px-1" : "px-2"}`}
            aria-label="Main navigation"
          >
            {!collapsed ? (
              <p className="mb-2 px-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Daily</p>
            ) : (
              <div className="mx-auto mb-2 h-px w-6 bg-slate-200" aria-hidden />
            )}
            <ul className="space-y-0.5">
              {dailyNav.map((item) => {
                const active = pathname === item.href || pathname.startsWith(`${item.href}/`);
                return (
                  <li key={item.href}>
                    <Link
                      href={item.href}
                      title={item.label}
                      className={`flex rounded-md font-medium transition-colors ${navLinkClass(active)} ${
                        collapsed
                          ? "mx-auto h-9 w-9 items-center justify-center px-0 py-0 text-[11px] leading-none"
                          : "block px-3 py-2 text-sm"
                      }`}
                    >
                      {collapsed ? item.abbreviated : item.label}
                    </Link>
                  </li>
                );
              })}
              <li className="mt-1 space-y-0.5">
                {!collapsed && !user?.can.view_sales ? (
                  <p className="mb-1 px-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Sales</p>
                ) : null}
                {collapsed ? <div className="mx-auto mb-1 mt-0.5 h-px w-6 bg-slate-200" aria-hidden /> : null}
                {user?.can.view_sales ? (
                  <Link
                    href="/sales"
                    title="Sales"
                    className={`flex rounded-md font-medium transition-colors ${navLinkClass(
                      pathname === "/sales" || pathname === "/sales/",
                    )} ${
                      collapsed
                        ? "mx-auto h-9 w-9 items-center justify-center px-0 py-0 text-[11px] leading-none"
                        : "block px-3 py-2 text-sm"
                    }`}
                  >
                    {collapsed ? "Sa" : "Sales"}
                  </Link>
                ) : null}
                <ul className={`space-y-0.5 ${!collapsed ? "ml-2 border-l border-slate-200 pl-2" : ""}`}>
                  <li>
                    <Link
                      href="/leads"
                      title="Leads"
                      className={`flex rounded-md font-medium transition-colors ${navLinkClass(
                        pathname === "/leads" || pathname.startsWith("/leads/"),
                      )} ${
                        collapsed
                          ? "mx-auto h-9 w-9 items-center justify-center px-0 py-0 text-[11px] leading-none"
                          : "block py-1.5 pl-1 pr-3 text-sm"
                      }`}
                    >
                      {collapsed ? "Le" : "Leads"}
                    </Link>
                  </li>
                  <li>
                    <Link
                      href="/sales/pipeline"
                      title="Pipeline"
                      className={`flex rounded-md font-medium transition-colors ${navLinkClass(
                        pathname.startsWith("/sales/pipeline"),
                      )} ${
                        collapsed
                          ? "mx-auto h-9 w-9 items-center justify-center px-0 py-0 text-[11px] leading-none"
                          : "block py-1.5 pl-1 pr-3 text-sm"
                      }`}
                    >
                      {collapsed ? "Pi" : "Pipeline"}
                    </Link>
                  </li>
                </ul>
              </li>
            </ul>

            {user?.can.access_admin_board ? (
              <>
                {!collapsed ? (
                  <p className="mb-2 mt-5 px-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Admin</p>
                ) : (
                  <div className="mx-auto mb-2 mt-4 h-px w-6 bg-slate-200" aria-hidden />
                )}
                <ul className="space-y-0.5">
                  <li>
                    <Link
                      href="/activity"
                      title="Activity"
                      className={`flex rounded-md font-medium transition-colors ${navLinkClass(pathname === "/activity" || pathname.startsWith("/activity/"))} ${
                        collapsed
                          ? "mx-auto h-9 w-9 items-center justify-center px-0 py-0 text-[11px] leading-none"
                          : "block px-3 py-2 text-sm"
                      }`}
                    >
                      {collapsed ? "Ac" : "Activity"}
                    </Link>
                  </li>
                  <li>
                    <Link
                      href="/admin/operations"
                      title="Operations"
                      className={`flex rounded-md font-medium transition-colors ${navLinkClass(pathname.startsWith("/admin/operations"))} ${
                        collapsed
                          ? "mx-auto h-9 w-9 items-center justify-center px-0 py-0 text-[11px] leading-none"
                          : "block px-3 py-2 text-sm"
                      }`}
                    >
                      {collapsed ? "Op" : "Operations"}
                    </Link>
                  </li>
                  <li>
                    <Link
                      href="/admin/reports"
                      title="Reports"
                      className={`flex rounded-md font-medium transition-colors ${navLinkClass(pathname.startsWith("/admin/reports"))} ${
                        collapsed
                          ? "mx-auto h-9 w-9 items-center justify-center px-0 py-0 text-[11px] leading-none"
                          : "block px-3 py-2 text-sm"
                      }`}
                    >
                      {collapsed ? "Re" : "Reports"}
                    </Link>
                  </li>
                  <li>
                    <Link
                      href="/admin/control-board"
                      title="Control board"
                      className={`flex rounded-md font-medium transition-colors ${navLinkClass(pathname.startsWith("/admin/control-board"))} ${
                        collapsed
                          ? "mx-auto h-9 w-9 items-center justify-center px-0 py-0 text-[10px] leading-none"
                          : "block px-3 py-2 text-sm"
                      }`}
                    >
                      {collapsed ? "Cb" : "Control board"}
                    </Link>
                  </li>
                </ul>
              </>
            ) : null}
          </nav>

          <div className={`shrink-0 border-t border-slate-200 ${collapsed ? "p-2" : "p-3"}`}>
            {user ? (
              <>
                {!collapsed ? (
                  <>
                    <p className="truncate text-xs font-semibold text-slate-900" title={user.name}>
                      {user.name}
                    </p>
                    <p className="mt-0.5 truncate text-[11px] text-slate-500" title={user.email}>
                      {user.email}
                    </p>
                  </>
                ) : (
                  <div
                    className="mx-auto flex h-9 w-9 items-center justify-center rounded-full bg-slate-200 text-xs font-bold text-slate-700"
                    title={`${user.name} (${user.email})`}
                  >
                    {user.name.trim().charAt(0).toUpperCase() || "?"}
                  </div>
                )}
                <button
                  type="button"
                  onClick={() => void logout()}
                  title="Logout"
                  className={`flex items-center justify-center rounded-md border border-slate-300 bg-white font-semibold text-slate-800 shadow-sm transition hover:border-pink-300 hover:bg-pink-50 hover:text-pink-900 ${
                    collapsed
                      ? "mx-auto mt-2 h-9 w-9 p-0"
                      : "mt-2 w-full px-2.5 py-2 text-left text-[11px]"
                  }`}
                >
                  {collapsed ? (
                    <>
                      <LogoutIcon className="h-4 w-4" />
                      <span className="sr-only">Logout</span>
                    </>
                  ) : (
                    "Logout"
                  )}
                </button>
              </>
            ) : null}
          </div>
        </aside>

        <div className="flex min-h-screen min-w-0 flex-1 flex-col">
          {user?.can.view_experimental_ui ? (
            <div className="border-b border-violet-300/80 bg-violet-100 px-6 py-2.5 text-center text-xs font-medium text-violet-950 shadow-sm">
              Experimental UI is on for administrators — you may see additional panels and tools that are not final.
            </div>
          ) : null}
          <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6">{children}</main>
        </div>
      </div>
    </div>
  );
}
