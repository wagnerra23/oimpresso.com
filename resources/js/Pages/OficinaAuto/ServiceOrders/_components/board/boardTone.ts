// Tons (cores) do Kanban de OS de mecânica. Centralizado aqui (módulo .ts,
// NÃO-componente) por 2 motivos:
//   1. evita warning react-refresh/only-export-components nos componentes;
//   2. cores de STATUS multi-hue (etapas/KPIs) que não têm token semântico 1:1
//      ficam num único ponto auditável (em vez de espalhadas nas Pages .tsx).
// Neutros (card/foreground/border/muted) usam tokens DS direto nas .tsx.

export interface StageTone {
  dot: string;
  topBorder: string;
  badge: string;
}

// token `color` do SaleProcessStage → classes (etapas do oficina_mecanica_os)
const TONE_BY_COLOR: Record<string, StageTone> = {
  gray:    { dot: 'bg-slate-400',   topBorder: 'border-t-slate-400',   badge: 'bg-slate-100 text-slate-600' },
  blue:    { dot: 'bg-blue-400',    topBorder: 'border-t-blue-400',    badge: 'bg-blue-100 text-blue-700' },
  amber:   { dot: 'bg-amber-400',   topBorder: 'border-t-amber-400',   badge: 'bg-amber-100 text-amber-800' },
  violet:  { dot: 'bg-violet-400',  topBorder: 'border-t-violet-400',  badge: 'bg-violet-100 text-violet-700' },
  indigo:  { dot: 'bg-indigo-400',  topBorder: 'border-t-indigo-400',  badge: 'bg-indigo-100 text-indigo-700' },
  emerald: { dot: 'bg-emerald-400', topBorder: 'border-t-emerald-400', badge: 'bg-emerald-100 text-emerald-700' },
  green:   { dot: 'bg-green-400',   topBorder: 'border-t-green-400',   badge: 'bg-green-100 text-green-700' },
  cyan:    { dot: 'bg-cyan-400',    topBorder: 'border-t-cyan-400',    badge: 'bg-cyan-100 text-cyan-700' },
  rose:    { dot: 'bg-rose-400',    topBorder: 'border-t-rose-400',    badge: 'bg-rose-100 text-rose-800' },
  orange:  { dot: 'bg-orange-400',  topBorder: 'border-t-orange-400',  badge: 'bg-orange-100 text-orange-700' },
};

const FALLBACK_TONE: StageTone = { dot: 'bg-slate-400', topBorder: 'border-t-slate-400', badge: 'bg-slate-100 text-slate-600' };

export function toneForColor(color: string | null | undefined): StageTone {
  return (color ? TONE_BY_COLOR[color] : undefined) ?? FALLBACK_TONE;
}

// Destaque das colunas de espera ([W] mod #4): aguardando-aprovação (âmbar · OK do
// cliente) × aguardando-peças (violeta · peça física). bg/border de coluna.
export type ColumnEmphasis = 'aprovacao' | 'pecas' | null;

export function emphasisClass(emphasis: ColumnEmphasis): string {
  if (emphasis === 'aprovacao') return 'bg-amber-50/40 border-amber-200';
  if (emphasis === 'pecas') return 'bg-violet-50/30 border-violet-200';
  return 'bg-card border-border';
}

// Tons dos KPIs do board (label/value/wrapper). default usa tokens neutros;
// status usam paleta (em .ts, não flagada por R1) pra manter a cor exata aprovada.
export type KpiTone = 'default' | 'amber' | 'rose' | 'emerald' | 'violet' | 'blue' | 'indigo';

export interface KpiToneClasses { wrapper: string; label: string; value: string }

const KPI_TONE: Record<KpiTone, KpiToneClasses> = {
  default: { wrapper: 'bg-card border-border',        label: 'text-muted-foreground', value: 'text-foreground' },
  amber:   { wrapper: 'bg-amber-50 border-amber-200', label: 'text-amber-700',        value: 'text-amber-900' },
  rose:    { wrapper: 'bg-rose-50 border-rose-200',   label: 'text-rose-700',         value: 'text-rose-900' },
  emerald: { wrapper: 'bg-emerald-50 border-emerald-200', label: 'text-emerald-700',  value: 'text-emerald-900' },
  violet:  { wrapper: 'bg-violet-50 border-violet-200', label: 'text-violet-700',     value: 'text-violet-900' },
  blue:    { wrapper: 'bg-blue-50 border-blue-200',   label: 'text-blue-700',         value: 'text-blue-900' },
  indigo:  { wrapper: 'bg-indigo-50 border-indigo-200', label: 'text-indigo-700',     value: 'text-indigo-900' },
};

export function kpiTone(tone: KpiTone): KpiToneClasses {
  return KPI_TONE[tone] ?? KPI_TONE.default;
}

// Glifo da marca da Grade (view veículo × etapa · Onda 2). Uma marca na célula da
// etapa atual da OS; a COR vem do tom da própria coluna (toneForColor) — data-driven,
// consistente com o header da coluna. Glifo semântico por etapa FSM conhecida do
// oficina_mecanica_os; fallback neutro pra processo customizado (canon .ofc-grade-mark).
const GRADE_GLYPH: Record<string, string> = {
  recepcao:             '·', // agendado / recém-recebido
  em_diagnostico:       '?', // em diagnóstico técnico
  aguardando_aprovacao: '◷', // aguardando OK do cliente
  aguardando_pecas:     '◦', // aguardando peça física
  em_execucao:          '●', // em execução
  pronto_retirada:      '✓', // pronto pra retirar
};

export function gradeGlyph(stageKey: string): string {
  return GRADE_GLYPH[stageKey] ?? '●';
}
