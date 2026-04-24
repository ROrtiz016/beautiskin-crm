"use client";

import { useAuth } from "@/context/auth-context";
import Link from "next/link";
import { usePathname } from "next/navigation";

const dailyNav = [
  { href: "/customers", label: "Customers" },
  { href: "/tasks", label: "Tasks" },
  { href: "/activity", label: "Activity" },
  { href: "/appointments", label: "Appointments" },
  { href: "/leads", label: "Leads" },
  { href: "/sales/pipeline", label: "Pipeline" },
  { href: "/services", label: "Services" },
  { href: "/inventory", label: "Inventory" },
  { href: "/packages", label: "Packages" },
  { href: "/quotes", label: "Quotes" },
  { href: "/memberships", label: "Memberships" },
] as const;

function navClass(active: boolean): string {
  return active
    ? "rounded-md bg-pink-600 px-2 py-1.5 text-white shadow-sm ring-1 ring-pink-700/20"
    : "rounded-md px-2 py-1.5 text-slate-600 hover:bg-slate-100 hover:text-slate-900";
}

export function CrmShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const { user, logout } = useAuth();

  return (
    <div className="min-h-screen bg-gradient-to-b from-slate-200 via-slate-100 to-slate-200 text-slate-900">
      <header className="sticky top-0 z-40 border-b border-slate-300/90 bg-white/95 shadow-sm shadow-slate-900/5 backdrop-blur-md">
        <div className="mx-auto flex max-w-6xl flex-col gap-2 px-3 py-2 sm:px-5 lg:flex-row lg:items-center lg:justify-between lg:gap-4 lg:py-2.5">
          <div className="flex items-center gap-2">
            <Link
              href="/"
              className="text-base font-bold tracking-tight text-slate-900 hover:text-pink-700"
            >
              BeautiSkin CRM
            </Link>
            <span className="hidden h-4 w-px bg-slate-300 sm:block" aria-hidden />
            <span className="hidden text-[11px] font-medium uppercase tracking-wide text-slate-500 sm:block">
              Clinic workspace
            </span>
          </div>
          <div className="flex flex-col gap-2 lg:flex-row lg:items-center lg:gap-5">
            <nav
              className="flex flex-col gap-1.5 text-xs font-medium lg:min-w-0 lg:flex-row lg:flex-wrap lg:items-center lg:gap-1"
              aria-label="Main navigation"
            >
              <div className="flex flex-wrap items-center gap-1">
                <span className="mr-0.5 hidden text-[10px] font-bold uppercase tracking-wider text-slate-400 lg:inline">
                  Daily
                </span>
                <div className="ml-1 flex flex-wrap items-center gap-1 lg:ml-0">
                  {dailyNav.map((item) => (
                    <Link
                      key={item.href}
                      href={item.href}
                      className={navClass(
                        pathname === item.href || pathname.startsWith(`${item.href}/`),
                      )}
                    >
                      {item.label}
                    </Link>
                  ))}
                  {user?.can.view_sales ? (
                    <Link href="/sales" className={navClass(pathname.startsWith("/sales"))}>
                      Sales
                    </Link>
                  ) : null}
                </div>
              </div>
              {user?.can.access_admin_board ? (
                <div className="flex flex-wrap items-center gap-1 border-t border-slate-200 pt-2 lg:border-l lg:border-t-0 lg:pl-4 lg:pt-0">
                  <span className="mr-0.5 hidden text-[10px] font-bold uppercase tracking-wider text-slate-400 lg:inline">
                    Admin
                  </span>
                  <div className="ml-1 flex flex-wrap items-center gap-1 lg:ml-0">
                    <Link
                      href="/admin/operations"
                      className={navClass(pathname.startsWith("/admin/operations"))}
                    >
                      Operations
                    </Link>
                    <Link href="/admin/reports" className={navClass(pathname.startsWith("/admin/reports"))}>
                      Reports
                    </Link>
                    <Link
                      href="/admin/control-board"
                      className={navClass(pathname.startsWith("/admin/control-board"))}
                    >
                      Control board
                    </Link>
                  </div>
                </div>
              ) : null}
            </nav>
            <div className="flex w-full min-w-0 flex-col gap-1.5 border-t border-slate-200 pt-2 text-left sm:max-w-[15rem] sm:self-end sm:text-right lg:w-auto lg:items-end lg:border-l lg:border-t-0 lg:pl-4 lg:pt-0 lg:text-right">
              {user ? (
                <>
                  <div className="ml-1 min-w-0 lg:ml-0">
                    <p className="truncate text-xs font-semibold text-slate-900" title={user.name}>
                      {user.name}
                    </p>
                    <p className="truncate text-[11px] text-slate-500" title={user.email}>
                      {user.email}
                    </p>
                  </div>
                  <button
                    type="button"
                    onClick={() => void logout()}
                    className="ml-1 inline-block shrink-0 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-left text-[11px] font-semibold text-slate-800 shadow-sm transition hover:border-pink-300 hover:bg-pink-50 hover:text-pink-900 sm:self-end lg:self-end"
                  >
                    Logout
                  </button>
                </>
              ) : null}
            </div>
          </div>
        </div>
      </header>
      {user?.can.view_experimental_ui ? (
        <div className="border-b border-violet-300/80 bg-violet-100 px-6 py-2.5 text-center text-xs font-medium text-violet-950 shadow-sm">
          Experimental UI is on for administrators — you may see additional panels and tools that are not final.
        </div>
      ) : null}
      <main className="mx-auto max-w-6xl px-4 py-8 sm:px-6">{children}</main>
    </div>
  );
}
