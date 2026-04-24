import { Suspense } from "react";
import { CustomersListClient } from "./customers-list-client";

export default function CustomersListPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-slate-600">Loading customers…</div>}>
      <CustomersListClient />
    </Suspense>
  );
}
