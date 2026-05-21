// @memcofre tela=/home module=Dashboard
// Wagner 2026-05-21 F6 Soft wrapper Inertia (US-DASH-001).
// Landing pós-login. Charts + widgets pluggable preservados em /home?legacy=1.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { ReactNode } from 'react';

interface Totals {
  total_sell: number;
  net: number;
  invoice_due: number;
  total_expense: number;
}

interface Props {
  user_name: string;
  is_admin: boolean;
  can_dashboard_data: boolean;
  all_locations: Record<number, string>;
  totals: Totals | null;
  legacy_url: string;
  endpoints: {
    totals: string;
    stock_alert: string;
    purchase_dues: string;
    sales_dues: string;
  };
}

function fmtMoney(v: number): string {
  return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

interface KpiSpec {
  label: string;
  value: number;
  accent: 'sky' | 'emerald' | 'amber' | 'rose';
}

function kpiClasses(accent: KpiSpec['accent']): { dot: string; value: string } {
  switch (accent) {
    case 'sky':
      return { dot: 'bg-sky-100 text-sky-600', value: 'text-stone-900' };
    case 'emerald':
      return { dot: 'bg-emerald-100 text-emerald-700', value: 'text-emerald-700' };
    case 'amber':
      return { dot: 'bg-amber-100 text-amber-700', value: 'text-amber-700' };
    case 'rose':
      return { dot: 'bg-rose-100 text-rose-700', value: 'text-rose-700' };
  }
}

function KpiCard({ label, value, accent }: KpiSpec) {
  const c = kpiClasses(accent);
  return (
    <div className="rounded-xl border border-stone-200 bg-white p-5 shadow-sm transition-all hover:shadow-md hover:-translate-y-0.5">
      <div className="flex items-center gap-4">
        <div
          className={`inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full ${c.dot}`}
          aria-hidden="true"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            strokeWidth={1.8}
            stroke="currentColor"
            fill="none"
            className="h-6 w-6"
            strokeLinecap="round"
            strokeLinejoin="round"
          >
            <path d="M3 6h18M3 12h18M3 18h12" />
          </svg>
        </div>
        <div className="min-w-0 flex-1">
          <p className="text-sm font-medium text-stone-500 truncate">{label}</p>
          <p className={`mt-0.5 text-xl font-semibold font-mono tracking-tight truncate ${c.value}`}>
            {fmtMoney(value)}
          </p>
        </div>
      </div>
    </div>
  );
}

function HomeIndex({
  user_name,
  is_admin,
  can_dashboard_data,
  all_locations,
  totals,
  legacy_url,
}: Props) {
  const locationEntries = Object.entries(all_locations);
  const showLocationFilter = is_admin && locationEntries.length > 1;

  const handleLocationChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const locId = e.target.value;
    router.visit('/home', {
      data: locId ? { location_id: locId } : {},
      preserveScroll: true,
      replace: true,
    });
  };

  const kpis: KpiSpec[] = totals
    ? [
        { label: 'Total Vendas', value: totals.total_sell, accent: 'sky' },
        { label: 'Líquido', value: totals.net, accent: 'emerald' },
        { label: 'A Receber', value: totals.invoice_due, accent: 'amber' },
        { label: 'Despesas', value: totals.total_expense, accent: 'rose' },
      ]
    : [];

  return (
    <div className="mx-auto max-w-7xl space-y-6 p-6">
      {/* Welcome banner */}
      <header className="rounded-xl bg-gradient-to-r from-primary-800 to-primary-900 px-6 py-8 text-white shadow-sm">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <h1 className="text-2xl font-semibold tracking-tight md:text-3xl">
            Bem-vindo{user_name ? `, ${user_name}` : ''}
          </h1>

          {showLocationFilter && (
            <div className="sm:w-72">
              <label htmlFor="dashboard_location" className="sr-only">
                Filtrar por loja
              </label>
              <select
                id="dashboard_location"
                onChange={handleLocationChange}
                className="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-stone-900 shadow-sm focus:ring-2 focus:ring-primary-500"
                defaultValue=""
              >
                <option value="">Todas as lojas</option>
                {locationEntries.map(([id, name]) => (
                  <option key={id} value={id}>
                    {name}
                  </option>
                ))}
              </select>
            </div>
          )}
        </div>
      </header>

      {/* KPI cards */}
      {can_dashboard_data && totals ? (
        <section
          className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 sm:gap-5"
          aria-label="Indicadores de operação"
        >
          {kpis.map((k) => (
            <KpiCard key={k.label} {...k} />
          ))}
        </section>
      ) : (
        <section className="rounded-xl border border-stone-200 bg-white px-6 py-10 text-center">
          <p className="text-sm text-stone-600">
            Você não tem permissão para visualizar os indicadores do dashboard. Fale com o
            administrador da empresa.
          </p>
        </section>
      )}

      {/* Banner legacy fallback */}
      <div className="rounded-md border border-stone-200 bg-stone-50 px-4 py-3 text-sm text-stone-700">
        <span>
          Precisa dos gráficos de vendas, alertas de estoque ou widgets de outros módulos?{' '}
          <a
            href={legacy_url}
            className="font-medium text-primary-700 underline hover:text-primary-900"
          >
            Abrir versão completa
          </a>
          .
        </span>
      </div>
    </div>
  );
}

HomeIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Início" breadcrumbItems={[{ label: 'Início' }]}>
    {page}
  </AppShellV2>
);

export default HomeIndex;
