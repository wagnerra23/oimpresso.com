// FinPeriodBar — barra de período fiel ao protótipo Cowork (fidelidade [W] 2026-06-29,
// ADR 0313). Substitui os dois campos dd/mm crus que ficavam soltos na toolbar por:
//   ‹ Mês Ano ›  ·  N lanç.  ·  [Dia][Semana][Mês][Ano][Tudo][Personalizado]
// "Personalizado" revela os campos dd/mm (que antes eram permanentes).
//
// FRONTEND-ONLY / NÃO-CÁLCULO: cada preset apenas POPULA data_inicio/data_fim — o
// MESMO filtro de intervalo que o usuário já podia digitar à mão, que o backend
// (UnificadoController) já aplica via whereBetween e que os KPIs já seguem. Nenhuma
// fórmula de valor muda. Default sem intervalo = mês atual no backend
// (periodo=mes_atual), então o preset "Mês" (mês corrente) reproduz EXATAMENTE o
// total default — é a âncora da dual-confirmação Tier 0.

import { useMemo } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

type Preset = 'dia' | 'semana' | 'mes' | 'ano' | 'tudo' | 'personalizado';

const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

/** YYYY-MM-DD em data LOCAL (browser BR) — evita o shift de UTC do toISOString. */
function ymd(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

/** Intervalo [ini, fim] de cada preset, ancorado numa data (default hoje). */
function rangeFor(preset: Preset, anchor: Date): { ini: string; fim: string } {
  const y = anchor.getFullYear();
  const m = anchor.getMonth();
  const d = anchor.getDate();
  switch (preset) {
    case 'dia':
      return { ini: ymd(anchor), fim: ymd(anchor) };
    case 'semana': {
      // Semana Dom–Sáb que contém a âncora (convenção BR).
      const dow = anchor.getDay(); // 0=Dom … 6=Sáb
      return { ini: ymd(new Date(y, m, d - dow)), fim: ymd(new Date(y, m, d - dow + 6)) };
    }
    case 'mes':
      return { ini: ymd(new Date(y, m, 1)), fim: ymd(new Date(y, m + 1, 0)) };
    case 'ano':
      return { ini: ymd(new Date(y, 0, 1)), fim: ymd(new Date(y, 11, 31)) };
    case 'tudo':
      // Janela aberta = todos os lançamentos (backend whereBetween inclusivo).
      return { ini: '2000-01-01', fim: '2099-12-31' };
    default:
      return { ini: '', fim: '' };
  }
}

interface Props {
  dataInicio: string;
  dataFim: string;
  /** Total de lançamentos do período (pagination.total ou contagem). */
  count: number;
  /** Aplica o intervalo (vazio,vazio = volta ao default mês atual do backend). */
  onChange: (dataInicio: string, dataFim: string) => void;
}

export default function FinPeriodBar({ dataInicio, dataFim, count, onChange }: Props) {
  // Detecta o preset ativo a partir do intervalo atual. Sem intervalo = mês atual
  // (espelha o default mes_atual do backend). Âncora do navegador = início do range
  // ou o mês corrente.
  const { active, anchor } = useMemo<{ active: Preset; anchor: Date }>(() => {
    if (!dataInicio && !dataFim) return { active: 'mes', anchor: new Date() };
    const a = new Date(`${dataInicio}T00:00:00`);
    const anchorDate = isNaN(a.getTime()) ? new Date() : a;
    for (const p of ['tudo', 'dia', 'semana', 'mes', 'ano'] as Preset[]) {
      const r = rangeFor(p, anchorDate);
      if (r.ini === dataInicio && r.fim === dataFim) return { active: p, anchor: anchorDate };
    }
    return { active: 'personalizado', anchor: anchorDate };
  }, [dataInicio, dataFim]);

  const apply = (p: Preset) => {
    if (p === 'personalizado') {
      // Revela os campos dd/mm sem mexer no intervalo atual; se não havia, parte do mês.
      if (!dataInicio && !dataFim) {
        const r = rangeFor('mes', new Date());
        onChange(r.ini, r.fim);
      }
      return;
    }
    if (p === 'mes') {
      const r = rangeFor('mes', anchor);
      onChange(r.ini, r.fim);
      return;
    }
    const r = rangeFor(p, new Date());
    onChange(r.ini, r.fim);
  };

  // Navegador ‹ Mês Ano › — desloca o mês-âncora e aplica como "Mês".
  const shiftMonth = (delta: number) => {
    const base = active === 'mes' ? anchor : new Date();
    const r = rangeFor('mes', new Date(base.getFullYear(), base.getMonth() + delta, 1));
    onChange(r.ini, r.fim);
  };
  const navLabel = `${MESES[anchor.getMonth()]} ${anchor.getFullYear()}`;

  const PRESETS: { id: Preset; label: string }[] = [
    { id: 'dia', label: 'Dia' },
    { id: 'semana', label: 'Semana' },
    { id: 'mes', label: 'Mês' },
    { id: 'ano', label: 'Ano' },
    { id: 'tudo', label: 'Tudo' },
    { id: 'personalizado', label: 'Personalizado' },
  ];

  return (
    <div className="fin-period-bar inline-flex items-center gap-2 flex-wrap" role="group" aria-label="Período">
      {/* Navegador de mês ‹ › */}
      <div className="inline-flex items-center gap-1">
        <button
          type="button"
          className="h-7 w-7 grid place-items-center rounded-md border border-border text-muted-foreground hover:bg-muted"
          onClick={() => shiftMonth(-1)}
          aria-label="Mês anterior"
          title="Mês anterior"
        >
          {/* SVG lucide (fidelidade protótipo [W] 2026-07-06 — proto usa I.ChevronLeft, não glyph ‹) */}
          <ChevronLeft size={15} />
        </button>
        <span className="text-[12px] font-medium text-foreground capitalize min-w-[96px] text-center tabular-nums">{navLabel}</span>
        <button
          type="button"
          className="h-7 w-7 grid place-items-center rounded-md border border-border text-muted-foreground hover:bg-muted"
          onClick={() => shiftMonth(1)}
          aria-label="Próximo mês"
          title="Próximo mês"
        >
          <ChevronRight size={15} />
        </button>
      </div>

      <span className="text-[11px] text-muted-foreground tabular-nums whitespace-nowrap">{count} lanç.</span>

      {/* Presets segmentados */}
      <div className="inline-flex items-center bg-muted rounded-md p-0.5 border border-border">
        {PRESETS.map((p) => (
          <button
            key={p.id}
            type="button"
            onClick={() => apply(p.id)}
            aria-pressed={active === p.id}
            className={
              'h-6 px-2 rounded text-[11.5px] transition ' +
              (active === p.id
                ? 'bg-background shadow-sm font-medium text-foreground'
                : 'text-muted-foreground hover:text-foreground')
            }
          >
            {p.label}
          </button>
        ))}
      </div>

      {/* Campos dd/mm — só no modo Personalizado (espelha o protótipo). */}
      {active === 'personalizado' && (
        <div className="inline-flex items-center gap-1">
          <input
            type="date"
            className="fin-date-input"
            value={dataInicio}
            max={dataFim || undefined}
            onChange={(e) => onChange(e.target.value, dataFim)}
            aria-label="Data inicial"
          />
          <span className="fin-date-sep" aria-hidden="true">–</span>
          <input
            type="date"
            className="fin-date-input"
            value={dataFim}
            min={dataInicio || undefined}
            onChange={(e) => onChange(dataInicio, e.target.value)}
            aria-label="Data final"
          />
          <button
            type="button"
            className="fin-date-clear"
            onClick={() => onChange('', '')}
            title="Limpar intervalo (volta ao mês atual)"
            aria-label="Limpar intervalo"
          >
            ×
          </button>
        </div>
      )}
    </div>
  );
}
