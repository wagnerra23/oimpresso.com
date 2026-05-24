// Components/clientes/KpiStripClickable.tsx
//
// PTDP Onda 2 do Cliente · KPI strip clicável (5 cards-filtro · Cowork chat1 ref).
// Substitui 4 KpiCard estáticos por 5 anchors filtráveis:
//
//   1. Ativos             (com OS aberta · status='active')
//   2. VIPs               (tag 'vip')
//   3. Com saldo          (saldo devedor)
//   4. Sem compra 90d     (stale='90')
//   5. Novos este mês     (created_at >= mês atual)
//
// Clique aplica filtro correspondente (substitutivo, igual SavedViews).
// Toggle: clique 2x na mesma desativa.
//
// Refs:
//   - prototipo-ui/prototipos/clientes/clientes-ptdp.jsx::KPIStrip (Cowork canon)
//   - HANDOFF_CLIENTES.md §PTDP KPI strip (5 cards-âncora · Bruna)
//   - Constituição UI v2 · ADR UI-0013 (camada 4-Módulo)
//   - PT-01 Slot 1 estende (não substitui PageHeader/KpiCard global)
//
// **Limitação atual (Onda 2):** counts de VIPs · Sem compra · Novos vêm
// estimados das `rows` da página atual (Onda 3 plug backend real). Counts
// de Ativos + Com saldo usam `kpis` reais já existentes.

import type { ComponentType } from 'react';
import { AlertCircle, Clock, Star, UserPlus, Users } from 'lucide-react';

export type KpiKey = 'ativos' | 'vips' | 'com-saldo' | 'sem-90' | 'novos';

export interface KpiFilters {
  statusFilter?: string;
  tagsFilter?: string[];
  staleFilter?: string;
  saldoFilter?: string;
  recentMonthFilter?: boolean;
}

export interface KpiCardDef {
  key: KpiKey;
  label: string;
  /** Subtítulo curto (ex: "prioridade", "risco churn"). */
  sub?: string;
  icon: ComponentType<{ className?: string }>;
  /** Token semântico do tema (`text-`/`bg-`). Default `text-primary`. */
  tone?: 'primary' | 'amber' | 'rose' | 'emerald' | 'violet';
  /** Filtros aplicados ao clicar. */
  filters: KpiFilters;
}

export interface KpiStripClickableProps {
  /** Total real de clientes ativos (kpis.com_os_aberta). */
  ativos: number;
  /** Total real com saldo aberto (kpis.com_atraso). */
  comSaldo: number;
  /** Estimado client-side (rows.filter vip). */
  vips: number;
  /** Estimado client-side (stale > 90d). */
  sem90: number;
  /** Estimado client-side (created_at >= mês atual). */
  novos: number;
  /** Card ativo (null = nenhum). */
  activeKey: KpiKey | null;
  /** Callback ao aplicar/desaplicar (passar null = desativa). */
  onApply: (card: KpiCardDef | null) => void;
}

const TONE_CLASSES: Record<NonNullable<KpiCardDef['tone']>, { active: string; iconBg: string; iconFg: string }> = {
  primary: {
    active: 'border-primary bg-accent shadow-sm',
    iconBg: 'bg-primary/10',
    iconFg: 'text-primary',
  },
  amber: {
    active: 'border-amber-500 bg-amber-50 dark:bg-amber-950/20 shadow-sm',
    iconBg: 'bg-amber-500/10',
    iconFg: 'text-amber-600 dark:text-amber-400',
  },
  rose: {
    active: 'border-rose-500 bg-rose-50 dark:bg-rose-950/20 shadow-sm',
    iconBg: 'bg-rose-500/10',
    iconFg: 'text-rose-600 dark:text-rose-400',
  },
  emerald: {
    active: 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/20 shadow-sm',
    iconBg: 'bg-emerald-500/10',
    iconFg: 'text-emerald-600 dark:text-emerald-400',
  },
  violet: {
    active: 'border-violet-500 bg-violet-50 dark:bg-violet-950/20 shadow-sm',
    iconBg: 'bg-violet-500/10',
    iconFg: 'text-violet-600 dark:text-violet-400',
  },
};

/**
 * 5 KPI cards clicáveis · clique aplica filtro · toggle 2x desativa.
 *
 * Tones por card (semântica visual · não viola "5 origens canon" porque
 * são tokens shadcn Tailwind, não badges de origin):
 *   - Ativos      → primary (azul)
 *   - VIPs        → amber (dourado)
 *   - Com saldo   → rose (alerta)
 *   - Sem 90d     → emerald (verde · neutro de churn)
 *   - Novos       → violet (novidade)
 */
export function KpiStripClickable({
  ativos,
  comSaldo,
  vips,
  sem90,
  novos,
  activeKey,
  onApply,
}: KpiStripClickableProps) {
  const cards: ReadonlyArray<KpiCardDef & { value: number }> = [
    {
      key: 'ativos',
      label: 'Clientes ativos',
      sub: 'com OS aberta',
      icon: Users,
      tone: 'primary',
      value: ativos,
      filters: { statusFilter: 'active' },
    },
    {
      key: 'vips',
      label: 'VIPs',
      sub: 'prioridade total',
      icon: Star,
      tone: 'amber',
      value: vips,
      filters: { tagsFilter: ['vip'] },
    },
    {
      key: 'com-saldo',
      label: 'Com saldo',
      sub: 'inadimplência',
      icon: AlertCircle,
      tone: 'rose',
      value: comSaldo,
      filters: { saldoFilter: 'devedor' },
    },
    {
      key: 'sem-90',
      label: 'Sem compra 90d',
      sub: 'risco churn',
      icon: Clock,
      tone: 'emerald',
      value: sem90,
      filters: { statusFilter: 'active', staleFilter: '90' },
    },
    {
      key: 'novos',
      label: 'Novos este mês',
      sub: 'desde dia 1',
      icon: UserPlus,
      tone: 'violet',
      value: novos,
      filters: { recentMonthFilter: true },
    },
  ];

  return (
    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mt-4">
      {cards.map((card) => {
        const Icon = card.icon;
        const tone = TONE_CLASSES[card.tone ?? 'primary'];
        const on = activeKey === card.key;
        return (
          <button
            key={card.key}
            type="button"
            onClick={() => onApply(on ? null : card)}
            title={card.sub ? `Filtrar por ${card.label} (${card.sub})` : `Filtrar por ${card.label}`}
            aria-pressed={on}
            className={
              'group flex items-center gap-3 p-3 rounded-md border text-left transition-all ' +
              (on
                ? tone.active
                : 'bg-card border-border hover:border-muted-foreground hover:shadow-sm')
            }
          >
            <div
              className={
                'h-9 w-9 rounded-md flex items-center justify-center flex-shrink-0 ' +
                tone.iconBg
              }
            >
              <Icon className={'h-4 w-4 ' + tone.iconFg} />
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-[10px] font-semibold tracking-wider uppercase text-muted-foreground truncate leading-none">
                {card.label}
              </p>
              <p className="text-lg font-semibold text-foreground tabular-nums leading-tight mt-1">
                {card.value.toLocaleString('pt-BR')}
              </p>
              {card.sub && (
                <p className="text-[10px] text-muted-foreground truncate leading-none mt-0.5">
                  {card.sub}
                </p>
              )}
            </div>
          </button>
        );
      })}
    </div>
  );
}

export default KpiStripClickable;
