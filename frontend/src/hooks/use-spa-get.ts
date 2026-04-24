"use client";

import { spaFetch } from "@/lib/spa-fetch";
import { useCallback, useEffect, useRef, useState } from "react";

export type UseSpaGetResult<T> = {
  data: T | null;
  error: string | null;
  /** True only while there is no data yet (initial load). Pagination/filter refetches keep showing previous data. */
  loading: boolean;
  /** True while a refetch is in flight and stale data is still shown. */
  isRefreshing: boolean;
  reload: () => void;
};

export function useSpaGet<T>(path: string): UseSpaGetResult<T> {
  const [data, setData] = useState<T | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isFetching, setIsFetching] = useState(true);
  const [reloadTick, setReloadTick] = useState(0);
  const fetchGeneration = useRef(0);

  useEffect(() => {
    const ac = new AbortController();
    const myGen = ++fetchGeneration.current;
    let cancelled = false;

    setIsFetching(true);
    setError(null);

    const finishFetching = () => {
      if (fetchGeneration.current === myGen) {
        setIsFetching(false);
      }
    };

    (async () => {
      try {
        const res = await spaFetch(path, { signal: ac.signal });
        if (cancelled) {
          return;
        }
        if (!res.ok) {
          setError(`${res.status} ${res.statusText}`);
          setData(null);
          return;
        }
        const json = (await res.json()) as T;
        if (cancelled) {
          return;
        }
        setData(json);
      } catch (e) {
        if (cancelled) {
          return;
        }
        if (e instanceof DOMException && e.name === "AbortError") {
          return;
        }
        setError(e instanceof Error ? e.message : "Request failed");
        setData(null);
      } finally {
        finishFetching();
      }
    })();

    return () => {
      cancelled = true;
      ac.abort();
    };
  }, [path, reloadTick]);

  const reload = useCallback(() => {
    setReloadTick((n) => n + 1);
  }, []);

  const loading = data === null && error === null && isFetching;
  const isRefreshing = isFetching && data !== null;

  return { data, error, loading, isRefreshing, reload };
}
