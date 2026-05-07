// @memcofre tela=/repair/dashboard module=Repair
// Sprint 2.5 / MWART-0002 — port Dashboard Repair Blade → Inertia/React.
// 5 painéis: KPIs + status breakdown + service staff + trending brands/devices/models.

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import KpiCard from '@/Components/shared/KpiCard';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Icon } from '@/Components/Icon';
import type { ReactNode } from 'react';

interface ChartDataPoint {
  [key: string]: string | number;
}

interface PageProps {
  kpis: {
    total_repairs: number;
    service_staff_count: number;
  };
  job_sheets_by_status: ChartDataPoint[];
  job_sheets_by_service_staff: ChartDataPoint[];
  trending_brand_chart: ChartDataPoint[] | null;
  trending_devices_chart: ChartDataPoint[] | null;
  trending_dm_chart: ChartDataPoint[] | null;
}

export default function DashboardIndex(props: PageProps) {
  const {
    kpis,
    job_sheets_by_status,
    job_sheets_by_service_staff,
    trending_brand_chart,
    trending_devices_chart,
    trending_dm_chart,
  } = props;

  return (
    <div className="container mx-auto p-4">
      <PageHeader
        icon="wrench"
        title="Dashboard Repair"
        description="Visão geral de OS, status, equipe e tendências (Repair)"
      />

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <KpiCard
          label="Status únicos"
          value={kpis.total_repairs}
          icon="wrench"
        />
        <KpiCard
          label="Service staff"
          value={kpis.service_staff_count}
          icon="users"
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <SimpleListCard
          title="OS por status"
          items={job_sheets_by_status}
          labelKey="status"
          valueKey="count"
          emptyMsg="Sem dados de status"
        />
        <SimpleListCard
          title="OS por service staff"
          items={job_sheets_by_service_staff}
          labelKey="staff"
          valueKey="count"
          emptyMsg="Sem dados de equipe"
        />
        <SimpleListCard
          title="Top marcas (trending)"
          items={trending_brand_chart ?? []}
          labelKey="brand"
          valueKey="count"
          emptyMsg="Sem dados de marcas"
          iconName="trending-up"
        />
        <SimpleListCard
          title="Top modelos (trending)"
          items={trending_dm_chart ?? []}
          labelKey="model"
          valueKey="count"
          emptyMsg="Sem dados de modelos"
          iconName="trending-up"
        />
      </div>
    </div>
  );
}

interface SimpleListCardProps {
  title: string;
  items: ChartDataPoint[];
  labelKey: string;
  valueKey: string;
  emptyMsg: string;
  iconName?: string;
}

function SimpleListCard({ title, items, labelKey, valueKey, emptyMsg, iconName }: SimpleListCardProps) {
  const list = Array.isArray(items) ? items : [];
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-sm font-medium flex items-center gap-2">
          {iconName && <Icon name={iconName} className="h-4 w-4" />}
          {title}
        </CardTitle>
      </CardHeader>
      <CardContent>
        {list.length === 0 ? (
          <p className="text-sm text-slate-500">{emptyMsg}</p>
        ) : (
          <ul className="space-y-1 text-sm">
            {list.slice(0, 10).map((item, i) => (
              <li key={i} className="flex justify-between border-b last:border-0 py-1">
                <span>{String(item[labelKey] ?? Object.values(item)[0] ?? '—')}</span>
                <span className="font-medium tabular-nums">
                  {String(item[valueKey] ?? Object.values(item)[1] ?? 0)}
                </span>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}

DashboardIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
