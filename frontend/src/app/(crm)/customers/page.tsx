import { Suspense } from "react";
import { CustomersListClient } from "./customers-list-client";

export default function CustomersListPage() {
  return (
    <Suspense fallback={null}>
      <CustomersListClient />
    </Suspense>
  );
}
