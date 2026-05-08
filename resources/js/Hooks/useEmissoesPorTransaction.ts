// US-NFE-MANUAL · Hook React pra listar emissões fiscais (NFC-e 65 + NFe 55) de uma transaction.
// Substitui useNfceStatus (que só pegava modelo 65) quando precisa mostrar ambos modelos.
// Usado por: Pages/Sells/_components/FiscalSection.tsx + Pages/Sells/Index.tsx (badge linha).

import { useCallback, useEffect, useRef, useState } from 'react';

export type EmissaoStatus =
  | 'pendente'
  | 'autorizada'
  | 'rejeitada'
  | 'denegada'
  | 'cancelada'
  | 'inutilizada';

export interface Emissao {
  id: number;
  modelo: '55' | '65';
  modelo_label: 'NFC-e' | 'NFe';
  serie: string;
  numero: number;
  chave_44: string | null;
  status: EmissaoStatus;
  cstat: string | null;
  motivo: string | null;
  valor_total: number;
  emitido_em: string | null;
  is_terminal: boolean;
  is_cancelavel: boolean;
}

export interface UseEmissoesPorTransactionOptions {
  /** Auto-poll enquanto houver emissão pendente. Default 2000ms. */
  intervalMs?: number;
  /** Limite de polls antes de desistir. Default 30 (= 1min). */
  maxPolls?: number;
  /** Habilita o hook. Default true. Use false pra pausar (drawer fechado). */
  enabled?: boolean;
}

export interface UseEmissoesPorTransactionReturn {
  emissoes: Emissao[];
  loading: boolean;
  error: string | null;
  isPolling: boolean;
  /** Refetch manual (após disparar emissão manual ou reenviar email). */
  refetch: () => void;
}

export function useEmissoesPorTransaction(
  transactionId: number | null,
  opts: UseEmissoesPorTransactionOptions = {},
): UseEmissoesPorTransactionReturn {
  const { intervalMs = 2000, maxPolls = 30, enabled = true } = opts;

  const [emissoes, setEmissoes] = useState<Emissao[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [pollCount, setPollCount] = useState(0);

  const cancelRef = useRef<boolean>(false);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const fetchData = useCallback(async () => {
    if (!transactionId || !enabled) return;
    cancelRef.current = false;
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`/nfe-brasil/api/transactions/${transactionId}/emissoes`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const json = await res.json();
      if (cancelRef.current) return;
      setEmissoes(Array.isArray(json.emissoes) ? json.emissoes : []);
    } catch (e) {
      if (!cancelRef.current) setError(String((e as Error)?.message || e));
    } finally {
      if (!cancelRef.current) setLoading(false);
    }
  }, [transactionId, enabled]);

  // Effect: fetch inicial + auto-poll quando há emissão pendente.
  useEffect(() => {
    if (!transactionId || !enabled) {
      setEmissoes([]);
      setError(null);
      setPollCount(0);
      return;
    }
    cancelRef.current = false;
    fetchData();

    return () => {
      cancelRef.current = true;
      if (timerRef.current) clearTimeout(timerRef.current);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [transactionId, enabled]);

  // Effect: poll enquanto pendente + dentro do limite.
  useEffect(() => {
    if (!enabled || !transactionId) return;
    const hasPending = emissoes.some((e) => !e.is_terminal);
    if (!hasPending) return;
    if (pollCount >= maxPolls) return;

    timerRef.current = setTimeout(() => {
      setPollCount((c) => c + 1);
      fetchData();
    }, intervalMs);
    return () => {
      if (timerRef.current) clearTimeout(timerRef.current);
    };
  }, [emissoes, pollCount, enabled, transactionId, intervalMs, maxPolls, fetchData]);

  const isPolling = emissoes.some((e) => !e.is_terminal) && pollCount < maxPolls;

  return { emissoes, loading, error, isPolling, refetch: fetchData };
}
