// Tons (cores) das etapas do Kanban de OS de mecânica — derivados do token `color`
// do SaleProcessStage (oficina_mecanica_os). Módulo separado (não-componente) pra
// evitar warning react-refresh/only-export-components no arquivo da coluna.

export interface StageTone {
  dot: string;
  topBorder: string;
  badge: string;
}

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
