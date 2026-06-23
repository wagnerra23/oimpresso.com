// FinConferidoToggle — Cowork KB-9.75 Financeiro Onda Edit (2026-05-18)
// (toggle "Conferido" persistido em DB per-user — substitui localStorage da Onda 5 R1).
//
// Fonte de verdade: `lancamento.conferido_by` (FK users.id) + `lancamento.conferido_at`.
// Toggle envia Inertia POST/DELETE /financeiro/unificado/{id}/conferir e a página
// re-render via preserveScroll com props atualizadas (back-end flash success/info).
//
// Migration de dados: Onda 5 R1 (localStorage) tinha volume baixo — backward-compat
// descartado conforme aprovação Wagner 2026-05-18. Toggles antigos viram inativos
// silenciosamente (localStorage cleanup opcional no future garbage-collect).

import { router } from '@inertiajs/react';
import { useCallback, useMemo } from 'react';
import { CircleCheck } from 'lucide-react';

interface ConferidoStateRow {
  id: number;
  conferido_by: number | null;
  conferido_at: string | null;
  conferido_user_nome: string | null;
}

export interface UseFinConferidoApi {
  has: (id: string | number) => boolean;
  toggle: (id: string | number) => void;
  count: number;
  userNome: (id: string | number) => string | null;
  conferidoAt: (id: string | number) => string | null;
}

export function useFinConferido(lancamentos: ConferidoStateRow[] = []): UseFinConferidoApi {
  const indexed = useMemo(() => {
    const m = new Map<string, ConferidoStateRow>();
    for (const l of lancamentos) m.set(String(l.id), l);
    return m;
  }, [lancamentos]);

  const has = useCallback((id: string | number) => {
    const row = indexed.get(String(id));
    return !!row && row.conferido_by !== null;
  }, [indexed]);

  const toggle = useCallback((id: string | number) => {
    const row = indexed.get(String(id));
    const isOn = !!row && row.conferido_by !== null;
    const url = `/financeiro/unificado/${id}/conferir`;
    if (isOn) {
      router.delete(url, { preserveScroll: true, preserveState: true });
    } else {
      router.post(url, {}, { preserveScroll: true, preserveState: true });
    }
  }, [indexed]);

  const userNome = useCallback((id: string | number) => {
    return indexed.get(String(id))?.conferido_user_nome ?? null;
  }, [indexed]);

  const conferidoAt = useCallback((id: string | number) => {
    return indexed.get(String(id))?.conferido_at ?? null;
  }, [indexed]);

  const count = useMemo(
    () => lancamentos.filter((l) => l.conferido_by !== null).length,
    [lancamentos]
  );

  return { has, toggle, count, userNome, conferidoAt };
}

interface FinConferidoToggleProps {
  rowId: string | number;
  conferido: UseFinConferidoApi;
}

export function FinConferidoToggle({ rowId, conferido }: FinConferidoToggleProps) {
  const isOn = conferido.has(rowId);
  const userNome = conferido.userNome(rowId);
  const titleOn = userNome ? `Conferido por ${userNome} · clique pra desmarcar` : 'Conferido · clique pra desmarcar';
  return (
    <button
      type="button"
      className={`fin-conferido-toggle ${isOn ? 'on' : ''}`}
      onClick={() => conferido.toggle(rowId)}
      title={isOn ? titleOn : 'Marcar como conferido (audit per-user)'}
      aria-pressed={isOn}
    >
      <span className="fin-conf-check">
        {isOn ? <CircleCheck size={14} strokeWidth={2.5} /> : null}
      </span>
      <span className="fin-conf-lbl">{isOn ? `Conferido${userNome ? ' · '+userNome : ''}` : 'Conferir'}</span>
      {!isOn && <small>Per-user audit</small>}
    </button>
  );
}

interface FinConferidoBadgeProps {
  rowId: string | number;
  conferido: UseFinConferidoApi;
}

/** Badge silencioso pra header da linha (mostra ✓ só quando conferido). */
export function FinConferidoBadge({ rowId, conferido }: FinConferidoBadgeProps) {
  if (!conferido.has(rowId)) return null;
  const userNome = conferido.userNome(rowId);
  return (
    <span className="fin-conferido-badge" title={userNome ? `Conferido por ${userNome}` : 'Conferido'}>
      <CircleCheck size={12} strokeWidth={2.5} />
    </span>
  );
}

export default FinConferidoToggle;
