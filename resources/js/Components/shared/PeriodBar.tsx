import { router } from '@inertiajs/react';
import { Inline, Stack } from '@/Components/layout';

/**
 * PeriodBar — janela do painel (US-DASH-004).
 *
 * Presets são janelas ROLANTES relativas a hoje, iguais às que o `resolvePeriod` do
 * HomeController aplica no servidor: dia = hoje..hoje · semana = hoje-6..hoje ·
 * mes = hoje-29..hoje. O default (`fy`) é o ano fiscal corrente — quem não toca no
 * filtro vê exatamente os mesmos números de antes.
 *
 * Quem decide a janela é o SERVIDOR: o componente só navega com o parâmetro e deixa
 * o Inertia repintar. Nada é calculado no browser, senão "hoje" viraria o do cliente
 * e a coluna do painel discordaria da query.
 *
 * Estado em query string, nunca em session — anti-hook do charter.
 */

export interface Period {
  from: string;
  to: string;
  preset: 'dia' | 'semana' | 'mes' | 'custom' | 'fy';
}

interface Props {
  period: Period;
  /** props que o partial reload deve rebuscar */
  only?: string[];
  className?: string;
}

const PRESETS: Array<{ key: Period['preset']; label: string }> = [
  { key: 'dia', label: 'Dia' },
  { key: 'semana', label: 'Semana' },
  { key: 'mes', label: 'Mês' },
];

const ROTULO = 'text-[10px] font-semibold uppercase tracking-wider text-muted-foreground';
const CAMPO =
  'h-9 rounded-lg border border-border bg-card px-3 font-mono text-[13px] text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30';

export function PeriodBar({ period, only = ['totals', 'period'], className }: Props) {
  const ir = (data: Record<string, string>) => {
    router.visit(window.location.pathname, {
      data,
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only,
    });
  };

  const trocaData = (campo: 'from' | 'to') => (e: React.ChangeEvent<HTMLInputElement>) => {
    const proximo = { from: period.from, to: period.to, [campo]: e.target.value };
    if (!proximo.from || !proximo.to) return;
    ir({ from: proximo.from, to: proximo.to });
  };

  return (
    <Inline gap={4} align="end" wrap className={className}>
      <Stack gap={2}>
        <span className={ROTULO}>Período</span>
        <Inline
          gap={0}
          role="group"
          aria-label="Período"
          className="rounded-lg border border-border bg-card p-0.5"
        >
          {PRESETS.map(({ key, label }) => {
            const ativo = period.preset === key;
            return (
              <button
                key={key}
                type="button"
                aria-pressed={ativo}
                onClick={() => ir({ preset: key })}
                className={`rounded-md px-3 py-1.5 text-[13px] font-medium transition-colors ${
                  ativo
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                }`}
              >
                {label}
              </button>
            );
          })}
        </Inline>
      </Stack>

      <Stack gap={2} asChild>
        <label>
          <span className={ROTULO}>De</span>
          <input type="date" value={period.from} onChange={trocaData('from')} className={CAMPO} />
        </label>
      </Stack>

      <Stack gap={2} asChild>
        <label>
          <span className={ROTULO}>Até</span>
          <input type="date" value={period.to} onChange={trocaData('to')} className={CAMPO} />
        </label>
      </Stack>
    </Inline>
  );
}

export default PeriodBar;
