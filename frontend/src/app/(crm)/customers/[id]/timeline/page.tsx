import { Suspense } from "react";
import { CustomerTimelineClient } from "./timeline-client";

export default function CustomerTimelinePage() {
  return (
    <Suspense
      fallback={
        <div className="p-6 text-sm text-slate-600" role="status">
          Loading timeline…
        </div>
      }
    >
      <CustomerTimelineClient />
    </Suspense>
  );
}
