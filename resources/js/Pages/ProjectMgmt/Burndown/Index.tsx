// @memcofre
//   tela: /project-mgmt/burndown
//   module: ProjectMgmt
//   stories: US-TR-206 (Burndown chart)
//   permissao: copiloto.mcp.usage.all

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useMemo, type ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import { Flame } from 'lucide-react';

interface SeriesPoint { date: string; ideal: number; real: number | null }

interface CycleHeader {
  id: number;
  key: string;
  name: string | null;
  goal: string | null;
  start_date: string;
  end_date: string;
  status: 'planning' | 'active' | 'closed';
  days_remaining: number;
}

interface CycleOption {
  id: number; key: string; name: string | null; status: string; is_active: boolean;
}

interface Kpis {
  total: number; done: number; remaining: number; percent_done: number;
  pace_per_day: number | null; forecast_days: number | null;
}

interface Props {
  project: { id: number; key: string; name: string } | null;
  cycle: CycleHeader | null;
  cycles: CycleOption[];
  series: SeriesPoint[];
  kpis: Kpis;
}

function fmtDay(iso: string): string {
  return new Date(iso + 'T00:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}

function BurndownChart({ series }: { series: SeriesPoint[] }) {
  const w = 800;
  const h = 280;
  const pad = { top: 16, right: 24, bottom: 32, left: 40 };
  const innerW = w - pad.left - pad.right;
  const innerH = h - pad.top - pad.bottom;

  const max = useMemo(() => {
    const allVals = series.flatMap((p) => [p.ideal, p.real ?? 0]);
    return Math.max(1, ...allVals);
  }, [series]);

  if (series.length < 2) {
    return <div className="text-center py-12 text-sm text-muted-foreground">Cycle muito curto pra plotar.</div>;
  }

  const xAt = (i: number) => pad.left + (i / (series.length - 1)) * innerW;
  const yAt = (v: number) => pad.top + innerH - (v / max) * innerH;

  const idealPts = series.map((p, i) => `${xAt(i)},${yAt(p.ideal)}`).join(' ');

  const realPts = series
    .map((p, i) => p.real !== null ? `${xAt(i)},${yAt(p.real)}` : null)
    .filter(Boolean)
    .join(' ');

  const ticksY = 4;
  const yTicks = Array.from({ length: ticksY + 1 }, (_, i) => Math.round((max * i) / ticksY));

  const stepX = Math.max(1, Math.ceil(series.length / 8));
  const xLabels = series
    .map((p, i) => ({ p, i }))
    .filter(({ i }) => i % stepX === 0 || i === series.length - 1);

  return (
    <div className="w-full overflow-x-auto">
      <svg viewBox={`0 0 ${w} ${h}`} className="w-full h-auto" role="img" aria-label="Burndown chart">
        {yTicks.map((t, i) => (
          <g key={`y-${i}`}>
            <line
              x1={pad.left} x2={pad.left + innerW}
              y1={yAt(t)} y2={yAt(t)}
              className="stroke-border" strokeDasharray="2 4"
            />
            <text x={pad.left - 6} y={yAt(t)} textAnchor="end" dominantBaseline="middle" className="fill-muted-foreground text-[10px]">
              {t}
            </text>
          </g>
        ))}

        <polyline points={idealPts} fill="none" className="stroke-muted-foreground/60" strokeWidth={1.5} strokeDasharray="4 4" />

        {realPts && (
          <polyline points={realPts} fill="none" className="stroke-blue-500" strokeWidth={2.5} strokeLinecap="round" strokeLinejoin="round" />
        )}

        {series.map((p, i) => p.real !== null ? (
          <circle key={`r-${i}`} cx={xAt(i)} cy={yAt(p.real)} r={2.5} className="fill-blue-500" />
        ) : null)}

        {xLabels.map(({ p, i }) => (
          <text key={`x-${i}`} x={xAt(i)} y={h - 8} textAnchor="middle" className="fill-muted-foreground text-[10px]">
            {fmtDay(p.date)}
          </text>
        ))}
      </svg>

      <div className="mt-3 flex items-center gap-4 text-xs text-muted-foreground justify-center">
        <span className="inline-flex items-center gap-2">
          <span className="w-6 h-0.5 bg-blue-500"></span>
          real (tasks abertas)
        </span>
        <span className="inline-flex items-center gap-2">
          <span className="w-6 border-t-2 border-dashed border-muted-foreground/60"></span>
          ideal
        </span>
      </div>
    </div>
  );
}

function BurndownIndex({ project, cycle, cycles, series, kpis }: Props) {
  if (!cycle) {
    return (
      <>
        <PageHeader
          icon="TrendingDown"
          title={project ? `${project.name} — Burndown` : 'Burndown'}
          description="Sem cycle ativo neste projeto"
        />
        <Card className="mt-6">
          <CardContent className="py-12 text-center text-muted-foreground">
            <Flame size={28} className="mx-auto mb-3 opacity-40" />
            <p className="text-sm">Nenhum cycle ativo. Use <code className="font-mono">cycles-create</code> via MCP pra começar.</p>
          </CardContent>
        </Card>
      </>
    );
  }

  return (
    <>
      <PageHeader
        icon="TrendingDown"
        title={project ? `${project.name} — Burndown` : 'Burndown'}
        description={`${cycle.key}${cycle.name ? ' — ' + cycle.name : ''} · ${cycle.start_date} → ${cycle.end_date} · ${cycle.days_remaining}d restantes`}
        action={
          cycles.length > 1 && (
            <Select
              value={String(cycle.id)}
              onValueChange={(v) => {
                // D-14: partial reload — só re-busca o que muda com a troca de cycle
                // (`cycles`, a lista do dropdown, é Inertia::defer → pula no partial).
                router.get('/project-mgmt/burndown', { cycle: v }, {
                  preserveScroll: true,
                  only: ['project', 'cycle', 'series', 'kpis', 'filters'],
                });
              }}
            >
              <SelectTrigger className="h-8 w-56 text-xs"><SelectValue /></SelectTrigger>
              <SelectContent>
                {cycles.map((c) => (
                  <SelectItem key={c.id} value={String(c.id)}>
                    {c.key}{c.name ? ' — ' + c.name : ''}{c.is_active ? ' (ativo)' : ''}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )
        }
      />

      {cycle.goal && (
        <Card className="mt-4 border-l-4 border-l-blue-500">
          <CardContent className="py-3 px-4">
            <p className="text-xs text-muted-foreground mb-0.5">Goal do cycle</p>
            <p className="text-sm">{cycle.goal}</p>
          </CardContent>
        </Card>
      )}

      <KpiGrid cols={5} className="mt-4">
        <KpiCard icon="List" tone="default" label="Total" value={String(kpis.total)} />
        <KpiCard icon="CircleCheck" tone="success" label="Concluídas" value={String(kpis.done)} />
        <KpiCard icon="Clock" tone="default" label="Restantes" value={String(kpis.remaining)} />
        <KpiCard
          icon="TrendingUp"
          tone={kpis.pace_per_day && kpis.pace_per_day > 0 ? 'success' : 'warning'}
          label="Pace/dia (7d)"
          value={kpis.pace_per_day !== null ? String(kpis.pace_per_day) : '—'}
        />
        <KpiCard
          icon="Calendar"
          tone={
            kpis.forecast_days === null ? 'default'
            : (kpis.forecast_days <= cycle.days_remaining ? 'success' : 'danger')
          }
          label="Previsão (dias)"
          value={kpis.forecast_days !== null ? String(kpis.forecast_days) : '—'}
          description={
            kpis.forecast_days !== null
              ? (kpis.forecast_days <= cycle.days_remaining
                  ? `cabe nos ${cycle.days_remaining}d restantes`
                  : `excede ${cycle.days_remaining}d restantes`)
              : 'pace ainda zero'
          }
        />
      </KpiGrid>

      <Card className="mt-4">
        <CardHeader>
          <CardTitle>Burndown — {cycle.key}</CardTitle>
          <CardDescription>
            {kpis.percent_done}% concluído ({kpis.done}/{kpis.total} tasks)
          </CardDescription>
        </CardHeader>
        <CardContent>
          <BurndownChart series={series} />
        </CardContent>
      </Card>

      <p className="mt-3 text-xs text-muted-foreground">
        Reconstrução via <code className="font-mono">mcp_task_events</code> (status_changed → done). Tasks
        movidas pra done sem evento contam no <code className="font-mono">kpis.done</code> mas não aparecem
        no histórico — aceita-se ruído pré-existente.
      </p>
    </>
  );
}

BurndownIndex.layout = (page: ReactNode) => (
  <AppShellV2
    title="Project Mgmt — Burndown"
    breadcrumbItems={[{ label: 'Project Mgmt' }, { label: 'Burndown' }]}
  >
    {page}
  </AppShellV2>
);

export default BurndownIndex;
