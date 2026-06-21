// Onda Final.E — Tab Reward Points (pontos fidelidade).
// Condicional business.enable_rp. Mostra saldo + histórico ganhos/resgates.

import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { Gift, ExternalLink } from 'lucide-react';

export interface RewardSummary {
  total_earned: number;
  total_used: number;
  total_expired: number;
  balance: number;
}

export interface RewardHistoryItem {
  id: number;
  invoice_no: string;
  transaction_date: string | null;
  final_total: number;
  rp_earned: number;
  rp_redeemed: number;
  rp_redeemed_amount: number;
}

export interface RewardPointsPayload {
  enabled: boolean;
  rp_name: string;
  summary: RewardSummary | null;
  history: RewardHistoryItem[];
}

export interface RewardPointsTabProps {
  /** Modo Inertia (Show.tsx full-page): payload vindo via Inertia::defer. */
  reward_points?: RewardPointsPayload;
  /** Modo self-fetch (drawer Cliente/Index → OssTab): busca via /cliente/{id}/rewards-json. */
  contactId?: number;
}

const formatBRL = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);

const formatDate = (iso: string | null) => {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(d);
};

export default function RewardPointsTab({ reward_points: rewardProp, contactId }: RewardPointsTabProps) {
  const [data, setData] = useState<RewardPointsPayload | null>(rewardProp ?? null);
  const [loading, setLoading] = useState(rewardProp === undefined);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    // Modo Inertia (Show.tsx): payload veio via prop, não busca.
    if (rewardProp !== undefined) {
      setData(rewardProp);
      setLoading(false);
      return;
    }
    // Modo self-fetch (drawer): sem prop → busca o endpoint JSON.
    if (!contactId) return;
    let cancelled = false;
    setLoading(true);
    setError(null);
    fetch(`/cliente/${contactId}/rewards-json`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then((r) => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      })
      .then((j: RewardPointsPayload) => {
        if (cancelled) return;
        setData(j);
        setLoading(false);
      })
      .catch(() => {
        if (cancelled) return;
        setError('Não foi possível carregar os pontos.');
        setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [contactId, rewardProp]);

  if (loading) {
    return (
      <div className="p-8 text-center text-xs text-muted-foreground" data-testid="rewards-tab-skeleton">
        Carregando pontos…
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-8 text-center text-xs text-muted-foreground" data-testid="rewards-tab-error">
        {error}
      </div>
    );
  }

  const reward_points = data;
  if (!reward_points) {
    return (
      <div className="p-8 text-center text-xs text-muted-foreground" data-testid="rewards-tab-skeleton">
        Carregando pontos…
      </div>
    );
  }

  if (!reward_points.enabled) {
    return (
      <div className="p-8 text-center text-xs text-muted-foreground flex flex-col items-center gap-2" data-testid="rewards-tab-disabled">
        <Gift size={24} className="text-muted-foreground/50" />
        <div>Programa de pontos não habilitado neste negócio.</div>
      </div>
    );
  }

  const { rp_name, summary, history } = reward_points;
  const label = rp_name || 'Pontos';

  return (
    <div className="p-4" data-testid="rewards-tab-root">
      {summary && (
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
          <SummaryCard label={`${label} acumulados`} value={summary.total_earned} accent="default" />
          <SummaryCard label="Resgatados" value={summary.total_used} accent="default" />
          <SummaryCard label="Expirados" value={summary.total_expired} accent="muted" />
          <SummaryCard label="Saldo disponível" value={summary.balance} accent={summary.balance > 0 ? 'success' : 'default'} />
        </div>
      )}

      {history.length === 0 ? (
        <div className="p-8 text-center text-xs text-muted-foreground" data-testid="rewards-tab-empty">
          Nenhum histórico de {label.toLowerCase()} ainda.
        </div>
      ) : (
        <div className="rounded-md border border-border overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-muted/50">
              <tr className="border-b border-border">
                <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Data</th>
                <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Fatura</th>
                <th className="text-right px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Total</th>
                <th className="text-right px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Ganhos</th>
                <th className="text-right px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Resgates</th>
                <th className="text-right px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Desconto</th>
                <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Ação</th>
              </tr>
            </thead>
            <tbody>
              {history.map((h) => (
                <tr key={h.id} className="border-b border-border hover:bg-muted/40">
                  <td className="px-4 py-3 text-xs text-muted-foreground tabular-nums whitespace-nowrap">
                    {formatDate(h.transaction_date)}
                  </td>
                  <td className="px-4 py-3 text-xs font-medium text-foreground">{h.invoice_no || '—'}</td>
                  <td className="px-4 py-3 text-xs text-right tabular-nums text-foreground">{formatBRL(h.final_total)}</td>
                  <td className="px-4 py-3 text-xs text-right tabular-nums text-success-fg">
                    {h.rp_earned > 0 ? `+${h.rp_earned}` : '—'}
                  </td>
                  <td className="px-4 py-3 text-xs text-right tabular-nums text-warning-fg">
                    {h.rp_redeemed > 0 ? `-${h.rp_redeemed}` : '—'}
                  </td>
                  <td className="px-4 py-3 text-xs text-right tabular-nums text-muted-foreground">
                    {h.rp_redeemed_amount > 0 ? formatBRL(h.rp_redeemed_amount) : '—'}
                  </td>
                  <td className="px-4 py-3 text-xs">
                    <Link href={`/sells/${h.id}`} className="inline-flex items-center gap-1 text-primary hover:underline">
                      Ver <ExternalLink size={11} aria-hidden />
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

function SummaryCard({
  label,
  value,
  accent,
}: {
  label: string;
  value: number;
  accent: 'default' | 'muted' | 'success';
}) {
  const tone = accent === 'success' ? 'text-success-fg' : accent === 'muted' ? 'text-muted-foreground' : 'text-foreground';
  return (
    <div className="rounded-md border border-border bg-background p-3">
      <div className="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">{label}</div>
      <div className={'text-lg font-semibold tabular-nums mt-1 ' + tone}>
        {new Intl.NumberFormat('pt-BR').format(value)}
      </div>
    </div>
  );
}
