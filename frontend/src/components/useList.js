import { useCallback, useEffect, useState } from 'react';
import client from '../api/client';

/** Fetches a paginated Laravel resource list. */
export default function useList(endpoint, params = {}) {
  const [rows, setRows] = useState([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [reloadKey, setReloadKey] = useState(0);

  const key = JSON.stringify(params);

  const reload = useCallback(() => setReloadKey((value) => value + 1), []);

  useEffect(() => {
    let cancelled = false;
    const controller = new AbortController();
    setLoading(true);
    setError(null);
    client.get(endpoint, { params: { page, ...JSON.parse(key) }, signal: controller.signal })
      .then(({ data }) => {
        if (cancelled) return;
        setRows(data.data ?? data);
        setLastPage(data.last_page ?? 1);
      })
      .catch((requestError) => {
        if (!cancelled && requestError.code !== 'ERR_CANCELED' && requestError.name !== 'CanceledError') {
          setError(requestError);
        }
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
      controller.abort();
    };
  }, [endpoint, page, key, reloadKey]);

  return { rows, page, setPage, lastPage, loading, error, reload };
}
