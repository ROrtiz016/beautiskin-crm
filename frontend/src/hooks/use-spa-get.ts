"use client";

import { spaFetch } from "@/lib/spa-fetch";
import { useCallback, useEffect, useState } from "react";

export function useSpaGet<T>(path: string): { data: T | null; error: string | null; loading: boolean; reload: () => void } {
  const [data, setData] = useState<T | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await spaFetch(path);
      if (!res.ok) {
        setError(`${res.status} ${res.statusText}`);
        setData(null);
        return;
      }
      setData((await res.json()) as T);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Request failed");
      setData(null);
    } finally {
      setLoading(false);
    }
  }, [path]);

  useEffect(() => {
    void load();
  }, [load]);

  return { data, error, loading, reload: load };
}
