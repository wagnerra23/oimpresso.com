// @memcofre tela=/repair/dashboard module=Repair
// Sprint 2.5 / MWART-0002 — port Dashboard Repair Blade → Inertia/React.
// W31-uplift: SimpleListCard texto → BarChart SVG a11y; trending_devices
// (US-REPAIR-DASH-1) un-voided; charts em Inertia::defer + skeleton (P7).

import { Deferred } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import KpiCard from '@/Components/shared/KpiCard';
import KpiGrid from '@/Components/shared/KpiGrid';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Skeleton } from '@/Components/ui/skeleton';
import { Icon } from '@/Components/Icon';
import { cn } from '@/Lib/utils';

/** Toda série de gráfico é normalizada pelo Controller pra {label,count}. */
interface ChartRow {
  label: string;
  count: number;
}

interface PageProps {
  kpis: {
    total_repairs: number;
    service_staff_count: number;
  };
  // Charts deferidos (Inertia::defer) — ausentes no first-paint, chegam em chunk.
  job_sheets_by_status?: ChartRow[];
  job_sheets_by_service_staff?: ChartRow[];
  trending_brand_chart?: ChartRow[];
  trending_dm_chart?: ChartRow[];
  trending_devices_chart?: ChartRow[];
}

export default function DashboardIndex(props: PageProps) {
  const { kpis } = props;

  return (
    <div className="container mx-auto space-y-6 p-4">
      <PageHeader
        icon="wrench"
        title="Dashboard Repair"
        description="Visão geral de OS, status, equipe e tendências (Repair)"
      />

      <KpiGrid cols={2}>
        <KpiCard label="Status únicos" value={kpis.total_repairs} icon="wrench" tone="info" />
        <KpiCard
          label="Service staff"
          value={kpis.service_staff_count}
          icon="users"
          tone="default"
        />
      </KpiGrid>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Deferred data="job_sheets_by_status" fallback={<ChartSkeleton title="OS por status" />}>
          <BarChartCard
            title="OS por status"
            rows={props.job_sheets_by_status ?? []}
            tone="info"
            unit="OS"
            emptyMsg="Sem dados de status"
          />
        </Deferred>

        <Deferred
          data="job_sheets_by_service_staff"
          fallback={<ChartSkeleton title="OS por service staff" />}
        >
          <BarChartCard
            title="OS por service staff"
            rows={props.job_sheets_by_service_staff ?? []}
            tone="default"
            unit="OS"
            emptyMsg="Sem dados de equipe"
          />
        </Deferred>

        <Deferred
          data="trending_brand_chart"
          fallback={<ChartSkeleton title="Top marcas (trending)" />}
        >
          <BarChartCard
            title="Top marcas (trending)"
            icon="trending-up"
            rows={props.trending_brand_chart ?? []}
            tone="success"
            unit="OS"
            emptyMsg="Sem dados de marcas"
          />
        </Deferred>

        <Deferred
          data="trending_dm_chart"
          fallback={<ChartSkeleton title="Top modelos (trending)" />}
        >
          <BarChartCard
            title="Top modelos (trending)"
            icon="trending-up"
            rows={props.trending_dm_chart ?? []}
            tone="success"
            unit="OS"
            emptyMsg="Sem dados de modelos"
          />
        </Deferred>

        {/* US-REPAIR-DASH-1 — FIXME resolvido: painel próprio pra trending_devices. */}
        <Deferred
          data="trending_devices_chart"
          fallback={<ChartSkeleton title="Top aparelhos (trending)" />}
        >
          <BarChartCard
            title="Top aparelhos (trending)"
            icon="trending-up"
            rows={props.trending_devices_chart ?? []}
            tone="success"
            unit="OS"
            emptyMsg="Sem dados de aparelhos"
          />
        </Deferred>
      </div>
    </div>
  );
}

function ChartSkeleton({ title }: { title: string }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-sm font-medium">{title}</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="flex flex-col gap-2">
          {Array.from({ length: 5 }).map((_, i) => (
            <div key={i} className="flex items-center gap-3">
              <Skeleton className="h-4 w-24 shrink-0" />
              <Skeleton className="h-3.5 flex-1" />
              <Skeleton className="h-4 w-8 shrink-0" />
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}

type BarTone = 'default' | 'info' | 'success';

// currentColor dirige o fill do SVG — só tokens, zero hex/oklch literal.
const barToneClass: Record<BarTone, string> = {
  default: 'text-muted-foreground',
  info: 'text-blue-600 dark:text-blue-400',
  success: 'text-emerald-600 dark:text-emerald-400',
};

interface BarChartCardProps {
  title: string;
  rows: ChartRow[];
  tone?: BarTone;
  unit?: string;
  emptyMsg: string;
  icon?: string;
}

/**
 * Sparkbar horizontal em SVG. Acessível:
 *   - role=img + aria-label por barra
 *   - <title> por barra (tooltip nativo + SR)
 *   - coluna textual de valor (o "eixo" lido por screen reader)
 *   - resumo sr-only consolidando a série
 */
function BarChartCard({ title, rows, tone = 'default', unit = '', emptyMsg, icon }: BarChartCardProps) {
  const list = Array.isArray(rows) ? rows.slice(0, 10) : [];
  const max = list.reduce((m, r) => Math.max(m, r.count), 0) || 1;
  const summary = list.map((r) => `${r.label}: ${r.count}`).join(', ');

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-sm font-medium">
          {icon && <Icon name={icon} className="h-4 w-4" />}
          {title}
        </CardTitle>
      </CardHeader>
      <CardContent>
        {list.length === 0 ? (
          <p className="text-sm text-muted-foreground">{emptyMsg}</p>
        ) : (
          <div className="flex flex-col gap-2">
            {list.map((r, i) => {
              const pct = Math.round((r.count / max) * 100);
              const aria = `${r.label}: ${r.count}${unit ? ' ' + unit : ''}`;
              return (
                <div key={`${r.label}-${i}`} className="flex items-center gap-3">
                  <span className="w-28 shrink-0 truncate text-sm text-foreground" title={r.label}>
                    {r.label}
                  </span>
                  <svg
                    className={cn('h-3.5 flex-1', barToneClass[tone])}
                    viewBox="0 0 100 14"
                    preserveAspectRatio="none"
                    role="img"
                    aria-label={aria}
                  >
                    <title>{aria}</title>
                    <rect x={0} y={0} width={100} height={14} rx={3} className="fill-muted" />
                    <rect x={0} y={0} width={pct} height={14} rx={3} fill="currentColor" />
                  </svg>
                  <span className="w-10 shrink-0 text-right text-sm font-medium tabular-nums text-foreground">
                    {r.count}
                  </span>
                </div>
              );
            })}
            <span className="sr-only">{`${title}. ${summary}.`}</span>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

DashboardIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
