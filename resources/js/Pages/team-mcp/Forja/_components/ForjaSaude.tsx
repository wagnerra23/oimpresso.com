// Forja — aba Saúde (semáforo do loop). Tela fiel ao protótipo aprovado
// (memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md §Saúde).
//
// Projeta o payload de ForjaSaudeService (ForjaController@saude via Inertia::defer):
//   kpis      → 4 KpiCards (Não-verificados · Bloqueados · P0 abertos · Gates verdes)
//   wip       → gráfico de barras "Fluxo · WIP por fase" (F0→F3.5)
//   automacao → 3 toggles read-only do contrato de automação do loop
//   entregas  → throughput (issues concluídas)
// SEM dado fantasma: tudo deriva de mcp_tasks project=FORJA + Scorecard Facts.
//
// Reuso: KpiGrid/KpiCard (espelha Scorecard/Index.tsx) + DS v6 do ForjaTriage.tsx
// (tokens semânticos, tabular-nums, layout via inline-flex/inline-grid, locators).

import { CheckCircle2, MinusCircle } from 'lucide-react';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import { cn } from '@/Lib/utils';

// Tom semântico do KPI (espelha o enum do KpiCard). 'destructive' do contrato
// mapeia pro 'danger' do KpiCard (mesmo papel: vermelho de falta/erro).
type KpiTone = 'default' | 'success' | 'warning' | 'destructive';

interface SaudeKpi {
  label: string;
  value: string;
  meta: string;
  tone: KpiTone;
}

interface SaudeWip {
  fase: string;
  count: number;
}

interface SaudeToggle {
  label: string;
  detail: string;
  on: boolean;
}

export interface SaudeData {
  kpis: SaudeKpi[];
  wip: SaudeWip[];
  automacao: SaudeToggle[];
  entregas: number;
}

// Default-guard: `saude` chega via Inertia::defer (ForjaController@saude) →
// undefined no 1º paint. Espelha o padrão de ForjaTriage/Scorecard.
const EMPTY: SaudeData = { kpis: [], wip: [], automacao: [], entregas: 0 };

// KPI tone (contrato) → KpiCard tone. 'destructive' → 'danger'.
const KPI_TONE: Record<KpiTone, 'default' | 'success' | 'warning' | 'danger'> = {
  default: 'default',
  success: 'success',
  warning: 'warning',
  destructive: 'danger',
};

// Ícone por KPI (derivado do label — só visual, sem dado novo).
function kpiIcon(label: string): string {
  if (label.startsWith('Não-verificados')) return 'search-check';
  if (label.startsWith('Bloqueados')) return 'ban';
  if (label.startsWith('P0')) return 'flame';
  if (label.startsWith('Gates')) return 'shield-check';
  return 'activity';
}

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v ?? 0);

export default function ForjaSaude({ saude }: { saude?: SaudeData }) {
  const { kpis, wip, automacao, entregas } = saude ?? EMPTY;
  const maxWip = Math.max(1, ...wip.map((w) => w.count));

  return (
    <div data-testid="forja-saude">
      {/* Texto-âncora (contrato pixel) */}
      <p className="mt-1 max-w-3xl text-xs leading-relaxed text-muted-foreground">
        <strong className="text-foreground">Semáforo do loop.</strong>{' '}
        Cada métrica deriva de <code className="font-mono">mcp_tasks</code> project=FORJA — sem dado
        fantasma. Verde = saudável; âmbar/vermelho = pede ação.
      </p>

      {/* 4 KPIs do semáforo */}
      <KpiGrid cols={4} className="mt-4">
        {kpis.map((k) => (
          <KpiCard
            key={k.label}
            label={k.label}
            value={k.value}
            description={k.meta}
            icon={kpiIcon(k.label)}
            tone={KPI_TONE[k.tone] ?? 'default'}
          />
        ))}
      </KpiGrid>

      {/* Fluxo · WIP por fase — gráfico de barras simples (altura proporcional) */}
      <section className="mt-6" data-testid="forja-saude-wip">
        <div className="inline-flex w-full items-baseline justify-between gap-2">
          <h2 className="text-sm font-semibold text-foreground">
            Fluxo <span className="font-normal text-muted-foreground">· WIP por fase</span>
          </h2>
          <span className="text-[11px] text-muted-foreground">
            <span className="font-semibold tabular-nums text-foreground">{num(entregas)}</span> entregues
          </span>
        </div>

        <div className="mt-3 inline-grid w-full grid-cols-6 gap-2 rounded-lg border bg-card p-4">
          {wip.map((w) => {
            const pct = Math.round((w.count / maxWip) * 100);
            const active = w.count > 0;
            return (
              <div
                key={w.fase}
                className="inline-flex flex-col items-center justify-end gap-1.5"
                data-testid="forja-saude-bar"
              >
                {/* Trilho da barra (altura fixa) — barra cresce de baixo p/ cima */}
                <div className="inline-flex h-24 w-full items-end justify-center">
                  <div
                    className={cn(
                      'w-7 rounded-md transition-[height]',
                      active ? 'bg-primary' : 'bg-muted',
                    )}
                    style={{ height: `${Math.max(active ? 8 : 4, pct)}%` }}
                    aria-hidden="true"
                  />
                </div>
                <span className="text-sm font-semibold tabular-nums text-foreground">{num(w.count)}</span>
                <span className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                  {w.fase}
                </span>
              </div>
            );
          })}
        </div>
      </section>

      {/* Automação — toggles read-only do contrato do loop */}
      <section className="mt-6" data-testid="forja-saude-automacao">
        <h2 className="text-sm font-semibold text-foreground">
          Automação <span className="font-normal text-muted-foreground">· regras do loop</span>
        </h2>

        <ul className="mt-3 divide-y rounded-lg border bg-card">
          {automacao.map((a) => (
            <li key={a.label} className="inline-flex w-full items-center gap-3 px-4 py-3">
              <div className="min-w-0 flex-1">
                <div className="truncate text-sm font-medium text-foreground">{a.label}</div>
                <div className="truncate text-xs text-muted-foreground">{a.detail}</div>
              </div>

              {/* Indicador on/off read-only (success quando ligado · muted quando não). */}
              <span
                className={cn(
                  'inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold',
                  a.on ? 'bg-success/15 text-success-fg' : 'bg-muted text-muted-foreground',
                )}
                data-testid="forja-saude-toggle"
                data-on={a.on}
              >
                {a.on ? <CheckCircle2 size={11} /> : <MinusCircle size={11} />}
                {a.on ? 'on' : 'off'}
              </span>
            </li>
          ))}
        </ul>

        <p className="mt-3 text-[11px] text-muted-foreground">
          Toggles são read-only — o enforce real é dos workflows de CI. Aqui é só o reflexo do
          contrato de automação do loop.
        </p>
      </section>
    </div>
  );
}
