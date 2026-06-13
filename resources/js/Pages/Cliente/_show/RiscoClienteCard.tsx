// RiscoClienteCard — Slice B KB-9.75 (paralelizacao 2026-05-21).
//
// Card DETERMINISTICO de score de risco do relacionamento (0-10).
// Score direto: 0 = saudavel, 10 = alto risco. Soma de 8 sinais com peso canonico.
//
// IMPORTANTE: zero chamada IA. Calculo local React em useMemo.
// LGPD: nao revela PII — exibe apenas metadados (status, saldo, contadores).
// Multi-tenant Tier 0: props ja vem filtradas por business_id no backend.
//
// Pattern reuse: prototipo Claude Design
//   `prototipo-ui/prototipos/clientes/clientes-tabs.jsx::RiscoCliente`
//   (adaptado pra inverter a escala — direto, nao 10-pesos).

import { useMemo } from 'react';
import { Activity, AlertTriangle, AlertCircle, CheckCircle2 } from 'lucide-react';

interface RiscoContact {
  type?: 'customer' | 'supplier' | 'both' | string;
  is_active?: boolean;
  email?: string | null;
  mobile?: string | null;
  landline?: string | null;
  city?: string | null;
  state?: string | null;
  inscricao_estadual?: string | null;
  contribuinte?: boolean;
  // Datas opcionais — backend pode entregar ISO string em waves futuras.
  last_purchase_at?: string | null;
  created_at?: string | null;
}

interface RiscoStats {
  total_invoice?: number;
  invoice_due?: number;
}

interface Props {
  contact: RiscoContact;
  stats?: RiscoStats;
}

interface RiscoSignal {
  key: string;
  label: string;
  weight: number;
  active: boolean;
}

const BRL = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);

function daysSince(iso: string | null | undefined): number | null {
  if (!iso) return null;
  const t = Date.parse(iso);
  if (Number.isNaN(t)) return null;
  return Math.floor((Date.now() - t) / 86_400_000);
}

export default function RiscoClienteCard({ contact, stats }: Props) {
  const { signals, score } = useMemo(() => {
    const invoiceDue = stats?.invoice_due ?? 0;
    const totalInvoice = stats?.total_invoice ?? 0;
    const daysLastPurchase = daysSince(contact.last_purchase_at);
    const daysSinceCreated = daysSince(contact.created_at);

    // Peso linear ate +3 acima de R$ [redacted Tier 0]k; +2 com saldo > 0; 0 senao.
    const saldoWeight = invoiceDue > 1000 ? 3 : invoiceDue > 0 ? 2 : 0;

    const signals: RiscoSignal[] = [
      {
        key: 'saldo',
        label: invoiceDue > 0 ? `Saldo a receber ${BRL(invoiceDue)}` : 'Saldo a receber > R$ [redacted Tier 0]',
        weight: saldoWeight,
        active: invoiceDue > 0,
      },
      {
        key: 'sem_compra_90',
        label: daysLastPurchase != null ? `Sem compra ha ${daysLastPurchase} dias` : 'Sem compra > 90d',
        weight: 2,
        active: daysLastPurchase != null && daysLastPurchase > 90 && daysLastPurchase <= 180,
      },
      {
        key: 'sem_compra_180',
        label: daysLastPurchase != null ? `Sem compra ha ${daysLastPurchase} dias (cliente esfriou)` : 'Sem compra > 180d',
        weight: 3,
        active: daysLastPurchase != null && daysLastPurchase > 180,
      },
      {
        key: 'inativo',
        label: 'Cliente inativo',
        weight: 2,
        active: contact.is_active === false,
      },
      {
        key: 'sem_contato',
        label: 'Sem email nem celular cadastrados',
        weight: 1,
        active: !contact.email && !contact.mobile && !contact.landline,
      },
      {
        key: 'pj_sem_ie',
        label: 'PJ contribuinte sem inscricao estadual',
        weight: 1,
        active:
          contact.type === 'customer' &&
          !contact.inscricao_estadual &&
          contact.contribuinte === true,
      },
      {
        key: 'sem_localidade',
        label: 'Sem cidade ou estado preenchido',
        weight: 0.5,
        active: !contact.city || !contact.state,
      },
      {
        key: 'cadastro_velho_sem_compra',
        label: 'Cadastrado ha > 365d e nunca comprou',
        weight: 1,
        active:
          daysSinceCreated != null &&
          daysSinceCreated > 365 &&
          totalInvoice === 0,
      },
    ];

    const raw = signals.reduce((acc, s) => acc + (s.active ? s.weight : 0), 0);
    const score = Math.min(10, Math.max(0, raw));

    return { signals, score };
  }, [contact, stats]);

  const activeSignals = signals.filter((s) => s.active);

  const tier: 'healthy' | 'warn' | 'high' =
    score <= 3 ? 'healthy' : score <= 6 ? 'warn' : 'high';

  // Ramp de risco = ESTADO semântico → tokens -soft/-fg (light+dark no token).
  const palette = {
    healthy: {
      bg: 'bg-success-soft',
      text: 'text-success-fg',
      border: 'border-success/20',
      Icon: CheckCircle2,
      label: 'Saudavel',
    },
    warn: {
      bg: 'bg-warning-soft',
      text: 'text-warning-fg',
      border: 'border-warning/20',
      Icon: AlertCircle,
      label: 'Atencao',
    },
    high: {
      bg: 'bg-destructive-soft',
      text: 'text-destructive-fg',
      border: 'border-destructive/20',
      Icon: AlertTriangle,
      label: 'Alto risco',
    },
  }[tier];

  const Icon = palette.Icon;

  return (
    <div
      className="rounded-lg border border-border bg-background p-5"
      data-testid="risco-cliente-card"
      data-risco-tier={tier}
      data-risco-score={score}
    >
      <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3 flex items-center gap-2">
        <Activity size={14} />
        Risco de relacionamento
      </h3>
      <div className={`flex items-center gap-3 rounded-md ${palette.bg} ${palette.border} border px-3 py-2`}>
        <Icon size={20} className={palette.text} strokeWidth={1.75} />
        <div className="flex-1 min-w-0">
          <div className={`text-xs font-semibold ${palette.text}`}>{palette.label}</div>
          <div className="text-2xl font-semibold tabular-nums text-foreground leading-tight">
            {score % 1 === 0 ? score : score.toFixed(1)}
            <span className="text-xs text-muted-foreground font-normal ml-0.5">/10</span>
          </div>
        </div>
      </div>

      {/* Meter visual — barra horizontal com fill proporcional ao score */}
      <div className="mt-3 h-1.5 rounded-full bg-muted overflow-hidden">
        <div
          className={
            'h-full rounded-full transition-all ' +
            (tier === 'healthy'
              ? 'bg-success'
              : tier === 'warn'
              ? 'bg-warning'
              : 'bg-destructive')
          }
          style={{ width: `${(score / 10) * 100}%` }}
          aria-hidden
        />
      </div>

      {activeSignals.length > 0 ? (
        <ul className="mt-3 space-y-1.5 text-xs" aria-label="Sinais de risco ativos">
          {activeSignals.map((s) => (
            <li key={s.key} className="flex items-start justify-between gap-2">
              <span className="text-muted-foreground flex-1 min-w-0 truncate" title={s.label}>
                {s.label}
              </span>
              <span className={`tabular-nums ${palette.text} font-medium flex-shrink-0`}>
                +{s.weight % 1 === 0 ? s.weight : s.weight.toFixed(1)}
              </span>
            </li>
          ))}
        </ul>
      ) : (
        <p className="mt-3 text-xs text-muted-foreground flex items-center gap-1.5">
          <CheckCircle2 size={12} className="text-success flex-shrink-0" />
          Nenhum sinal de risco detectado.
        </p>
      )}

      <p className="mt-3 pt-3 border-t border-border text-[10px] text-muted-foreground leading-relaxed">
        Score deterministico (sem IA). 8 sinais com pesos canonicos.
      </p>
    </div>
  );
}
