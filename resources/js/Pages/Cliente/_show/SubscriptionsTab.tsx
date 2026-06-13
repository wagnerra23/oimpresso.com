// Onda Final.D — Tab Assinaturas (transactions is_recurring=1).
// Paridade com Blade sale_pos/partials/subscriptions_table.

import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { Recycle, ExternalLink } from 'lucide-react';

export interface SubscriptionItem {
  id: number;
  subscription_no: string;
  transaction_date: string | null;
  recur_interval: number;
  recur_interval_type: string;
  recur_repetitions: number;
  recur_stopped_on: string | null;
  location_name: string | null;
  generated_count: number;
}

export interface SubscriptionsTabProps {
  /** Modo Inertia (Show.tsx full-page): lista vinda via Inertia::defer. */
  subscriptions?: SubscriptionItem[];
  /** Modo self-fetch (drawer Cliente/Index → OssTab): busca via /cliente/{id}/subscriptions-json. */
  contactId?: number;
}

const INTERVAL_LABELS: Record<string, string> = {
  days: 'dia',
  months: 'mês',
  years: 'ano',
};

const formatDate = (iso: string | null) => {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(d);
};

const formatInterval = (n: number, type: string) => {
  if (!n) return '—';
  const unit = INTERVAL_LABELS[type] ?? type;
  return `a cada ${n} ${unit}${n === 1 ? '' : type === 'months' ? 'es' : 's'}`;
};

export default function SubscriptionsTab({ subscriptions: subsProp, contactId }: SubscriptionsTabProps) {
  const [data, setData] = useState<SubscriptionItem[] | null>(subsProp ?? null);
  const [loading, setLoading] = useState(subsProp === undefined);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    // Modo Inertia (Show.tsx): lista veio via prop, não busca.
    if (subsProp !== undefined) {
      setData(subsProp);
      setLoading(false);
      return;
    }
    // Modo self-fetch (drawer): sem prop → busca o endpoint JSON.
    if (!contactId) return;
    let cancelled = false;
    setLoading(true);
    setError(null);
    fetch(`/cliente/${contactId}/subscriptions-json`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then((r) => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      })
      .then((j: SubscriptionItem[]) => {
        if (cancelled) return;
        setData(Array.isArray(j) ? j : []);
        setLoading(false);
      })
      .catch(() => {
        if (cancelled) return;
        setError('Não foi possível carregar as assinaturas.');
        setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [contactId, subsProp]);

  if (loading) {
    return (
      <div className="p-8 text-center text-xs text-muted-foreground" data-testid="subscriptions-tab-skeleton">
        Carregando assinaturas…
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-8 text-center text-xs text-muted-foreground" data-testid="subscriptions-tab-error">
        {error}
      </div>
    );
  }

  const subscriptions = data;
  if (!subscriptions) {
    return (
      <div className="p-8 text-center text-xs text-muted-foreground" data-testid="subscriptions-tab-skeleton">
        Carregando assinaturas…
      </div>
    );
  }

  if (subscriptions.length === 0) {
    return (
      <div className="p-8 text-center text-xs text-muted-foreground flex flex-col items-center gap-2" data-testid="subscriptions-tab-empty">
        <Recycle size={24} className="text-muted-foreground/50" />
        <div>Nenhuma assinatura registrada.</div>
      </div>
    );
  }

  return (
    <div className="overflow-hidden" data-testid="subscriptions-tab-root">
      <table className="w-full text-sm">
        <thead className="bg-muted/50">
          <tr className="border-b border-border">
            <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Data</th>
            <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Nº Assinatura</th>
            <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Local</th>
            <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Intervalo</th>
            <th className="text-right px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Repetições</th>
            <th className="text-right px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Geradas</th>
            <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Status</th>
            <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Ação</th>
          </tr>
        </thead>
        <tbody>
          {subscriptions.map((s) => {
            const isStopped = !!s.recur_stopped_on;
            return (
              <tr key={s.id} className="border-b border-border hover:bg-muted/40">
                <td className="px-4 py-3 text-xs text-muted-foreground tabular-nums whitespace-nowrap">
                  {formatDate(s.transaction_date)}
                </td>
                <td className="px-4 py-3 text-xs font-medium text-foreground">{s.subscription_no || '—'}</td>
                <td className="px-4 py-3 text-xs text-muted-foreground">{s.location_name ?? '—'}</td>
                <td className="px-4 py-3 text-xs text-foreground">
                  {formatInterval(s.recur_interval, s.recur_interval_type)}
                </td>
                <td className="px-4 py-3 text-xs text-right tabular-nums text-foreground">
                  {s.recur_repetitions || '∞'}
                </td>
                <td className="px-4 py-3 text-xs text-right tabular-nums text-foreground">{s.generated_count}</td>
                <td className="px-4 py-3 text-xs">
                  {isStopped ? (
                    <span className="inline-flex items-center rounded-full border border-warning/20 bg-warning-soft px-2 py-0.5 text-[10px] uppercase tracking-wider text-warning-fg">
                      Pausada
                    </span>
                  ) : (
                    <span className="inline-flex items-center rounded-full border border-success/20 bg-success-soft px-2 py-0.5 text-[10px] uppercase tracking-wider text-success-fg">
                      Ativa
                    </span>
                  )}
                </td>
                <td className="px-4 py-3 text-xs">
                  <Link
                    href={`/sells/${s.id}`}
                    className="inline-flex items-center gap-1 text-primary hover:underline"
                  >
                    Ver
                    <ExternalLink size={11} aria-hidden />
                  </Link>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
