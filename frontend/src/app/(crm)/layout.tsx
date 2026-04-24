import { CrmShell } from "@/components/crm-shell";
import { AuthProvider } from "@/context/auth-context";
import type { ReactNode } from "react";
import { Suspense } from "react";

export default function CrmRouteLayout({ children }: { children: ReactNode }) {
  return (
    <AuthProvider>
      <Suspense fallback={<div className="p-8 text-center text-sm text-slate-500">Loading…</div>}>
        <CrmShell>{children}</CrmShell>
      </Suspense>
    </AuthProvider>
  );
}
