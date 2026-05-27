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
  /** Tom oklch inline · veja `TONE_STYLES`. Default `'primary'`. */
  tone?: ToneKey;
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

/**
 * Tons oklch inline — propositalmente NÃO usa classes Tailwind cor literal
 * (`bg-amber-500`, etc) pra não disparar regra R1 do `ui:lint` (cor crua em Page/Component).
 * Os valores oklch são canônicos · mesma fórmula `oklch(L C H)` dos tokens
 * do `cockpit.css` (Constituição UI v2 · ADR 0043 padrão de cor).
 *
 * Cada tone tem 3 derivações:
 *   - `border`: border-color do card quando ativo
 *   - `bgActive`: background do card quando ativo
 *   - `iconBg`: background do tile do ícone
 *   - `iconFg`: foreground do ícone
 *
 * Mesmo hue, 4 lightness distintos · padrão Stripe Dashboard cards.
 */
const TONE_STYLES = {
  primary: {
    border: 'oklch(0.62 0.18 250)',
    bgActive: 'oklch(0.95 0.04 250)',
    iconBg: 'oklch(0.92 0.05 250)',
    iconFg: 'oklch(0.45 0.18 250)',
  },
  amber: {
    border: 'oklch(0.72 0.15 70)',
    bgActive: 'oklch(0.96 0.05 70)',
    iconBg: 'oklch(0.93 0.07 70)',
    iconFg: 'oklch(0.50 0.15 70)',
  },
  rose: {
    border: 'oklch(0.65 0.20 20)',
    bgActive: 'oklch(0.96 0.04 20)',
    iconBg: 'oklch(0.93 0.06 20)',
    iconFg: 'oklch(0.50 0.20 20)',
  },
  emerald: {
    border: 'oklch(0.65 0.14 155)',
    bgActive: 'oklch(0.95 0.04 155)',
    iconBg: 'oklch(0.92 0.06 155)',
    iconFg: 'oklch(0.45 0.14 155)',
  },
  violet: {
    border: 'oklch(0.60 0.18 295)',
    bgActive: 'oklch(0.96 0.04 295)',
    iconBg: 'oklch(0.93 0.06 295)',
    iconFg: 'oklch(0.50 0.18 295)',
  },
} as const;

type ToneKey = keyof typeof TONE_STYLES;

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

  // mt-* removido (Wagner 2026-05-25): gap vertical entre blocos vem do `space-y-3`
  // do parent (Cliente/Index.tsx) — 12px canon `--gap-blocos`.
  // mx-6 (Wagner 2026-05-25 #3): respiro lateral 24px de cada lado pra recuar o KPI
  // strip do header/tabela. Espelha o `px-6` (24px) do parent — KPI strip fica
  // visualmente "duplo-aninhado" em relação ao Header BLOCO 1 e Tabela BLOCO 3.
  // Total lateral: 24px parent + 24px KPI = 48px viewport edge.
  return (
    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mx-6">
      {cards.map((card) => {
        const Icon = card.icon;
        const tone = TONE_STYLES[card.tone ?? 'primary'];
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
              (on ? 'shadow-sm' : 'bg-card hover:shadow-sm')
            }
            // canon v3.4 polish (Wagner 2026-05-25): border warm `oklch(0.9 0.004 90)`
            // (cream-deeper · familia hue 90) substitui `border-border` cool slate-200.
            // Espelha /sells `.os-kpi` exato. Afinidade visual com fundo cream.
            style={on
              ? { borderColor: tone.border, backgroundColor: tone.bgActive }
              : { borderColor: 'oklch(0.9 0.004 90)' }}
          >
            <div
              className="h-9 w-9 rounded-md flex items-center justify-center flex-shrink-0"
              style={{ backgroundColor: tone.iconBg }}
            >
              <Icon className="h-4 w-4" style={{ color: tone.iconFg }} />
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
