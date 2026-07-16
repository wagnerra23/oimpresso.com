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
//
// PAR `dark:` OBRIGATÓRIO (2026-07-16) — o step 50/40 é claro e NÃO tem par automático:
// no dark o fundo continuava pastel-claro enquanto o título da coluna usa `text-foreground`
// (claro no dark) → texto claro sobre fundo claro. MEDIDO no render do gate: "Aguardando
// aprovação" 1.56:1 e "Aguardando peças" 1.76:1 (WCAG AA exige 4.5:1) = ilegível.
// O tom escuro espelha o claro (mesmo hue, step 950/900) preservando a distinção âmbar ×
// violeta que o charter exige como lei (§UX targets). Não dá pra usar `--color-*-soft`
// (que já é dark-aware): existe warning/info/success/destructive — NÃO existe violeta, e
// token novo é soberania [W] Tier 0. `dark:` explícito é o padrão já usado em Clientes.
// [W] aprovou este caminho em 2026-07-16.
export type ColumnEmphasis = 'aprovacao' | 'pecas' | null;

export function emphasisClass(emphasis: ColumnEmphasis): string {
  if (emphasis === 'aprovacao') return 'bg-amber-50/40 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800';
  if (emphasis === 'pecas') return 'bg-violet-50/30 dark:bg-violet-950/25 border-violet-200 dark:border-violet-800';
  return 'bg-card border-border';
}

// Tons dos KPIs do board (label/value/wrapper). default usa tokens neutros;
// status usam paleta (em .ts, não flagada por R1) pra manter a cor exata aprovada.
//
// PAR `dark:` OBRIGATÓRIO (2026-07-16) — o step 50 é claro e NÃO tem par automático. No
// dark os 6 tons coloridos renderizavam CAIXAS CLARAS, e a `sub` do card (que usa
// `text-muted-foreground` FIXO em BoardKpiCard.tsx:31 — claro no dark) sumia em cima delas:
//   MEDIDO na baseline do gate: sub "0 boxes/elevadores" 1.92:1 · "0 aguardam OK do
//   cliente" 2.04:1 (WCAG AA exige 4.5:1). Controle: no tom `default` (bg-card, escuro) a
//   MESMA sub mede 5.27:1 — a prova de que o defeito é o FUNDO claro, não a sub.
// Mesmo defeito de classe dos `bg-white` (#4367) e das colunas (`emphasisClass`), no último
// disfarce: cor clara do palette, escondida em `.ts` (o `ds/no-raw-palette-color` só olha
// `className` em JSX) e sem par dark. Atenção: com o fundo escuro, `text-<hue>-700` (escuro)
// também inverte — por isso label/value ganham par `dark:300`/`dark:100`.
// O LIGHT fica INTACTO (variante `dark:` não o afeta) — preserva a "cor exata aprovada".
// Fundo no dark é `bg-card` OPACO (a faixa de KPIs, Board.tsx) → sem o vazamento que limita
// as colunas (aquelas ficam sobre `bg-muted/40` translúcido — task própria).
// Contraste calculado (tokens + palette Tailwind 4 OKLCH) — sub / label / value:
//   amber 6.32 / 10.03 / 13.02 · rose 6.70 / 8.03 / 12.83 · emerald 6.30 / 9.51 / 12.74
//   violet 6.46 / 8.00 / 12.49 · blue 6.21 / 7.86 / 11.68 · indigo 6.43 / 7.35 / 11.99
export type KpiTone = 'default' | 'amber' | 'rose' | 'emerald' | 'violet' | 'blue' | 'indigo';

export interface KpiToneClasses { wrapper: string; label: string; value: string }

const KPI_TONE: Record<KpiTone, KpiToneClasses> = {
  default: { wrapper: 'bg-card border-border',        label: 'text-muted-foreground', value: 'text-foreground' },
  amber:   { wrapper: 'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-900', label: 'text-amber-700 dark:text-amber-300', value: 'text-amber-900 dark:text-amber-100' },
  rose:    { wrapper: 'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-900', label: 'text-rose-700 dark:text-rose-300', value: 'text-rose-900 dark:text-rose-100' },
  emerald: { wrapper: 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-900', label: 'text-emerald-700 dark:text-emerald-300', value: 'text-emerald-900 dark:text-emerald-100' },
  violet:  { wrapper: 'bg-violet-50 dark:bg-violet-950/40 border-violet-200 dark:border-violet-900', label: 'text-violet-700 dark:text-violet-300', value: 'text-violet-900 dark:text-violet-100' },
  blue:    { wrapper: 'bg-blue-50 dark:bg-blue-950/40 border-blue-200 dark:border-blue-900', label: 'text-blue-700 dark:text-blue-300', value: 'text-blue-900 dark:text-blue-100' },
  indigo:  { wrapper: 'bg-indigo-50 dark:bg-indigo-950/40 border-indigo-200 dark:border-indigo-900', label: 'text-indigo-700 dark:text-indigo-300', value: 'text-indigo-900 dark:text-indigo-100' },
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
