// @memcofre
//   modulo: NfeBrasil (useNfceStatus)
//   stories: US-NFE-002 fase 2C (polling status pós-emissão)
//   adrs: 0058 (Centrifugo CT 100), 0062 (Hostinger sem daemons)
//   nota: hook polling `/nfe-brasil/api/transactions/{tx}/nfe-status` a cada 2s
//         até receber estado terminal (autorizada/rejeitada/denegada). Quando
//         migrar pra broadcast Centrifugo no futuro, troca-se o transport
//         dentro do hook — componentes consumidores não mudam.

import { useEffect, useRef, useState } from 'react';

export type NfceStatus =
  | 'pendente'
  | 'autorizada'
  | 'rejeitada'
  | 'denegada'
  | null; // null = ainda não emitida

export interface NfceStatusPayload {
  transaction_id: number;
  emissao_id?: number;
  status: NfceStatus;
  modelo?: string;
  cstat?: string | null;
  chave_44?: string | null;
  numero?: number | null;
  serie?: string | null;
  motivo?: string | null;
  valor_total?: number;
  emitido_em?: string | null;
  is_terminal?: boolean;
  message?: string;
}

export interface UseNfceStatusOptions {
  /** Cadência do poll em ms. Default 2000ms (2s). */
  intervalMs?: number;
  /** Limite total de polls antes de desistir. Default 30 (= 1 min). */
  maxPolls?: number;
  /** Callback quando atinge estado terminal. Usado pra fechar modal/toast. */
  onTerminal?: (payload: NfceStatusPayload) => void;
}

export interface UseNfceStatusReturn {
  data: NfceStatusPayload | null;
  isPolling: boolean;
  hasGivenUp: boolean;
  /** Forçar refetch manual (útil em retry button). */
  refetch: () => void;
}

/**
 * Polling reativo de status NFC-e pós-venda.
 *
 * Comportamento:
 *   - Inicia poll quando `transactionId` muda; reinicia interval no remount
 *   - Para quando `is_terminal` (autorizada/rejeitada/denegada) OU maxPolls
 *   - Não repete fetch se requisição anterior ainda está pending
 *   - Limpa interval no unmount (useRef de timer ID)
 *   - Erro de rede / 4xx => trata como `null` (UI mostra mensagem genérica)
 *
 * Uso típico:
 * ```tsx
 * const { data, isPolling } = useNfceStatus(tx.id, {
 *   onTerminal: payload => toast.success(`NFC-e #${payload.numero} OK`)
 * });
 * ```
 */
export function useNfceStatus(
  transactionId: number | null,
  opts: UseNfceStatusOptions = {},
): UseNfceStatusReturn {
  const { intervalMs = 2000, maxPolls = 30, onTerminal } = opts;
  const [data, setData] = useState<NfceStatusPayload | null>(null);
  const [isPolling, setIsPolling] = useState(false);
  const [hasGivenUp, setHasGivenUp] = useState(false);

  const pollCountRef = useRef(0);
  const inFlightRef = useRef(false);
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const onTerminalRef = useRef(onTerminal);
  onTerminalRef.current = onTerminal; // sempre versão fresca sem reiniciar interval

  const fetchStatus = async (txId: number) => {
    if (inFlightRef.current) return;
    inFlightRef.current = true;
    try {
      const res = await fetch(`/nfe-brasil/api/transactions/${txId}/nfe-status`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      if (!res.ok) {
        // 401/403/400 → desiste silencioso; UI mostra mensagem default
        return;
      }
      const payload: NfceStatusPayload = await res.json();
      setData(payload);
      if (payload.is_terminal) {
        stopPolling();
        onTerminalRef.current?.(payload);
      }
    } catch {
      // network error — segue tentando; maxPolls limita
    } finally {
      inFlightRef.current = false;
    }
  };

  const stopPolling = () => {
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
      intervalRef.current = null;
    }
    setIsPolling(false);
  };

  const startPolling = (txId: number) => {
    pollCountRef.current = 0;
    setHasGivenUp(false);
    setIsPolling(true);
    fetchStatus(txId); // primeiro fetch imediato
    intervalRef.current = setInterval(() => {
      pollCountRef.current += 1;
      if (pollCountRef.current >= maxPolls) {
        stopPolling();
        setHasGivenUp(true);
        return;
      }
      fetchStatus(txId);
    }, intervalMs);
  };

  useEffect(() => {
    if (transactionId === null || transactionId <= 0) {
      stopPolling();
      setData(null);
      return;
    }
    startPolling(transactionId);
    return stopPolling;
    // intervalMs/maxPolls são opções estáveis — mudança intencional reinicia.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [transactionId, intervalMs, maxPolls]);

  const refetch = () => {
    if (transactionId !== null && transactionId > 0) {
      stopPolling();
      startPolling(transactionId);
    }
  };

  return { data, isPolling, hasGivenUp, refetch };
}
