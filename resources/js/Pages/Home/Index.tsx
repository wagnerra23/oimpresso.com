// @memcofre tela=/home module=Dashboard
// Wagner 2026-05-21 F6 Soft wrapper Inertia (US-DASH-001).
// Wagner 2026-05-22 charter v2 — 8 KPI cards (Vendas + Compras) — fix contraste header.
// Landing pós-login. Charts + widgets pluggable preservados em /home?legacy=1.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Icon } from '@/Components/Icon';
import { router } from '@inertiajs/react';
import { ReactNode } from 'react';

interface Totals {
  total_sell: number;
  net: number;
  invoice_due: number;
  total_expense: number;
  total_purchase: number;
  purchase_due: number;
  total_sell_return: number;
  total_purchase_return: number;
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

type Accent = 'sky' | 'emerald' | 'amber' | 'rose' | 'violet' | 'orange' | 'teal' | 'stone';

interface KpiSpec {
  label: string;
  value: number;
  accent: Accent;
  icon: string;
  hint?: string;
}

// Classes Tailwind estáticas (não dinâmicas) — JIT precisa ver literalmente
// em build time. Map fechado garante purge correto.
const ACCENT_CLASSES: Record<Accent, { tile: string; ring: string; value: string }> = {
  sky:     { tile: 'bg-sky-50 text-sky-600',         ring: 'ring-sky-100/60',     value: 'text-stone-900' },
  emerald: { tile: 'bg-emerald-50 text-emerald-600', ring: 'ring-emerald-100/60', value: 'text-emerald-700' },
  amber:   { tile: 'bg-amber-50 text-amber-600',     ring: 'ring-amber-100/60',   value: 'text-amber-700' },
  rose:    { tile: 'bg-rose-50 text-rose-600',       ring: 'ring-rose-100/60',    value: 'text-rose-700' },
  violet:  { tile: 'bg-violet-50 text-violet-600',   ring: 'ring-violet-100/60',  value: 'text-stone-900' },
  orange:  { tile: 'bg-orange-50 text-orange-600',   ring: 'ring-orange-100/60',  value: 'text-orange-700' },
  teal:    { tile: 'bg-teal-50 text-teal-600',       ring: 'ring-teal-100/60',    value: 'text-teal-700' },
  stone:   { tile: 'bg-stone-100 text-stone-600',    ring: 'ring-stone-200/60',   value: 'text-stone-900' },
};

function KpiCard({ label, value, accent, icon, hint }: KpiSpec) {
  const c = ACCENT_CLASSES[accent];
  return (
    <div
      className={`group relative rounded-lg border border-stone-200 bg-white p-5 shadow-sm ring-1 ${c.ring} transition-all hover:shadow-md hover:-translate-y-0.5 hover:border-stone-300`}
      title={hint}
    >
      <div className="flex items-center gap-4">
        <div
          aria-hidden="true"
          className={`inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg ${c.tile}`}
        >
          <Icon name={icon} size={20} strokeWidth={1.8} />
        </div>
        <div className="min-w-0 flex-1">
          <p className="text-[13px] font-medium text-stone-500 truncate">{label}</p>
          <p className={`mt-0.5 text-xl font-semibold font-mono tracking-tight truncate ${c.value}`}>
            {fmtMoney(value)}
          </p>
        </div>
      </div>
    </div>
  );
}

function KpiGroup({ label, kpis }: { label: string; kpis: KpiSpec[] }) {
  return (
    <section aria-label={`Indicadores ${label}`} className="space-y-3">
      <div className="flex items-center gap-3">
        <h2 className="text-xs font-semibold uppercase tracking-wider text-stone-500">{label}</h2>
        <div className="h-px flex-1 bg-stone-200" />
      </div>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 sm:gap-5">
        {kpis.map((k) => (
          <KpiCard key={k.label} {...k} />
        ))}
      </div>
    </section>
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
    // D-14: partial reload — só re-busca o que muda com filtro (totals).
    // all_locations é closure por business no controller — pula no partial.
    router.visit('/home', {
      data: locId ? { location_id: locId } : {},
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ['totals'],
    });
  };

  const salesKpis: KpiSpec[] = totals
    ? [
        { label: 'Total Vendas',     value: totals.total_sell,         accent: 'sky',     icon: 'trending-up',     hint: 'Total faturado no exercício fiscal atual' },
        { label: 'Líquido',          value: totals.net,                accent: 'emerald', icon: 'wallet',          hint: 'Vendas − A Receber − Despesas' },
        { label: 'A Receber',        value: totals.invoice_due,        accent: 'amber',   icon: 'hourglass',       hint: 'Faturas em aberto de clientes' },
        { label: 'Devoluções Venda', value: totals.total_sell_return,  accent: 'rose',    icon: 'undo-2',          hint: 'Retorno de vendas (trocas / cancelamentos)' },
      ]
    : [];

  const purchaseKpis: KpiSpec[] = totals
    ? [
        { label: 'Total Compras',    value: totals.total_purchase,        accent: 'violet', icon: 'shopping-cart',  hint: 'Total adquirido no exercício fiscal atual' },
        { label: 'A Pagar',          value: totals.purchase_due,          accent: 'orange', icon: 'alert-circle',   hint: 'Compras em aberto com fornecedores' },
        { label: 'Reembolso Compra', value: totals.total_purchase_return, accent: 'teal',   icon: 'rotate-ccw',     hint: 'Devoluções a fornecedores' },
        { label: 'Despesas',         value: totals.total_expense,         accent: 'stone',  icon: 'receipt',        hint: 'Despesas operacionais no exercício' },
      ]
    : [];

  return (
    <div className="mx-auto max-w-7xl space-y-6 p-6">
      {/* Welcome banner — superfície clara, contraste WCAG AA. ADR 0180 PageHeader canon style. */}
      <header className="flex flex-col gap-4 rounded-lg border border-stone-200 bg-white px-6 py-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-start gap-4 min-w-0">
          <div
            aria-hidden="true"
            className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
          >
            <Icon name="layout-dashboard" size={22} strokeWidth={1.8} />
          </div>
          <div className="min-w-0">
            <p className="text-xs font-medium uppercase tracking-wider text-stone-500">Início</p>
            <h1 className="mt-0.5 text-2xl font-semibold tracking-tight text-stone-900 md:text-3xl">
              Bem-vindo{user_name ? `, ${user_name}` : ''}
            </h1>
          </div>
        </div>

        {showLocationFilter && (
          <div className="sm:w-72 sm:shrink-0">
            <label htmlFor="dashboard_location" className="sr-only">
              Filtrar por loja
            </label>
            <select
              id="dashboard_location"
              onChange={handleLocationChange}
              className="w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
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
      </header>

      {/* KPI cards — agrupados por fluxo (Vendas / Compras) */}
      {can_dashboard_data && totals ? (
        <div className="space-y-6">
          <KpiGroup label="Vendas" kpis={salesKpis} />
          <KpiGroup label="Compras & Custos" kpis={purchaseKpis} />
        </div>
      ) : (
        <section className="rounded-lg border border-stone-200 bg-white px-6 py-10 text-center">
          <p className="text-sm text-stone-600">
            Você não tem permissão para visualizar os indicadores do dashboard. Fale com o
            administrador da empresa.
          </p>
        </section>
      )}

      {/* Banner legacy fallback */}
      <div className="flex items-start gap-3 rounded-lg border border-stone-200 bg-stone-50 px-4 py-3 text-sm text-stone-700">
        <Icon name="info" size={16} className="mt-0.5 shrink-0 text-stone-500" />
        <span className="leading-relaxed">
          Precisa dos gráficos de vendas, alertas de estoque ou widgets de outros módulos?{' '}
          <a
            href={legacy_url}
            className="font-medium text-primary underline-offset-2 hover:underline"
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
