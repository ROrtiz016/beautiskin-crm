import { Suspense } from "react";
import { AppointmentsClient } from "./appointments-client";

export default function AppointmentsPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-slate-600">Loading appointments…</div>}>
      <AppointmentsClient />
    </Suspense>
  );
}
