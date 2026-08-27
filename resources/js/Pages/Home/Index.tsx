// @memcofre tela=/dashboard-legacy module=Dashboard
// Visão geral — Rewrite Cockpit V2 (US-DASH-004).
// Âncora de design: prototipo-ui/cowork/dash-legacy-page.jsx (rota `dash-legacy`, atalho
// "Visão geral" em data.jsx:19). Substitui o F6 Soft wrapper de 2026-05-22, que o [W]
// declarou tentativa descartada em 2026-08-27.
//
// Fora desta onda, por motivo declarado:
//   · gráficos (US-DASH-002) — não há lib de chart no package.json; entra com ADR própria
//   · abas de grade e Pendências (US-DASH-005) — consomem os 4 endpoints AJAX existentes
//   · "Papel simulado" / "Simular falha" — instrumentos do protótipo. Em produção quem
//     decide é a permissão real e a resposta real do endpoint.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Icon } from '@/Components/Icon';
import { PeriodBar, type Period } from '@/Components/shared/PeriodBar';
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
  period: Period;
  legacy_url: string;
  endpoints: {
    totals: string;
    stock_alert: string;
    purchase_dues: string;
    sales_dues: string;
  };
}

const brl = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const brlCurto = (v: number) => {
  const abs = Math.abs(v);
  if (abs >= 1000) {
    return `${v < 0 ? '−' : ''}R$ ${(abs / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}k`;
  }
  return brl(v);
};

/** KPI em destaque — o número que responde "como foi o período". */
function KpiHero({ label, value, description }: { label: string; value: number; description?: string }) {
  return (
    <div className="rounded-lg border border-border bg-card p-5 shadow-sm ring-1 ring-primary/15">
      <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">{label}</p>
      <p className="mt-1 font-mono text-3xl font-semibold tracking-tight text-foreground">{brl(value)}</p>
      {description && <p className="mt-1 text-[12px] text-muted-foreground">{description}</p>}
    </div>
  );
}

function Kpi({
  label,
  value,
  icon,
  description,
}: {
  label: string;
  value: number;
  icon: string;
  description?: string;
}) {
  return (
    <div className="rounded-lg border border-border bg-card p-5 shadow-sm transition-shadow hover:shadow-md">
      <div className="flex items-start gap-3">
        <span
          aria-hidden="true"
          className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-accent text-muted-foreground"
        >
          <Icon name={icon} size={17} strokeWidth={1.8} />
        </span>
        <div className="min-w-0 flex-1">
          <p className="text-[12px] font-medium text-muted-foreground">{label}</p>
          <p className="mt-0.5 font-mono text-xl font-semibold tracking-tight text-foreground">{brl(value)}</p>
          {description && <p className="mt-0.5 text-[11.5px] text-muted-foreground">{description}</p>}
        </div>
      </div>
    </div>
  );
}

/** Contrapartidas — os 4 números do outro lado do caixa, sem gastar 4 cards. */
function Contrapartidas({ totals }: { totals: Totals }) {
  const itens: Array<[string, number, string]> = [
    ['Compras', totals.total_purchase, 'incluindo impostos'],
    ['A pagar', totals.purchase_due, 'líquido de descontos'],
    ['Devolução de venda', totals.total_sell_return, 'no período'],
    ['Devolução de compra', totals.total_purchase_return, 'devido ao fornecedor'],
  ];

  return (
    <section aria-label="Contrapartidas" className="rounded-lg border border-border bg-card p-5 shadow-sm">
      <div className="mb-4 flex items-baseline justify-between gap-3">
        <h2 className="text-[13.5px] font-semibold text-foreground">Contrapartidas</h2>
        <span className="font-mono text-[10.5px] text-muted-foreground">mesmo período</span>
      </div>
      <div className="grid grid-cols-2 gap-x-4 gap-y-4 lg:grid-cols-4">
        {itens.map(([label, valor, sub]) => (
          <div key={label} className="flex min-w-0 flex-col gap-1">
            <span className="text-[9.5px] font-semibold uppercase tracking-wider text-muted-foreground">{label}</span>
            <span className="font-mono text-[15.5px] font-semibold tabular-nums text-foreground">{brlCurto(valor)}</span>
            <span className="text-[11.5px] text-muted-foreground">{sub}</span>
          </div>
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
  period,
  legacy_url,
}: Props) {
  const lojas = Object.entries(all_locations);
  const mostraLoja = is_admin && lojas.length > 1;

  const trocaLoja = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const id = e.target.value;
    router.visit(window.location.pathname, {
      data: { ...(id ? { location_id: id } : {}), from: period.from, to: period.to },
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ['totals', 'period'],
    });
  };

  return (
    <div className="mx-auto max-w-7xl space-y-5 p-6">
      <header className="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">Visão geral</h1>
          {totals && (
            <p className="mt-1 font-mono text-[12.5px] text-muted-foreground">
              {brlCurto(totals.total_sell)} vendas · {brlCurto(totals.invoice_due)} a receber ·{' '}
              {brlCurto(totals.total_expense)} despesas
            </p>
          )}
        </div>
        <p className="text-[12.5px] text-muted-foreground">Bem-vindo{user_name ? `, ${user_name}` : ''}</p>
      </header>

      <div className="flex flex-wrap items-end justify-between gap-4">
        <PeriodBar period={period} />
        {mostraLoja && (
          <label className="flex flex-col gap-1.5">
            <span className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Loja</span>
            <select
              id="dashboard_location"
              onChange={trocaLoja}
              defaultValue=""
              className="h-9 rounded-lg border border-border bg-card px-3 text-[13px] text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
            >
              <option value="">Todas as lojas</option>
              {lojas.map(([id, nome]) => (
                <option key={id} value={id}>
                  {nome}
                </option>
              ))}
            </select>
          </label>
        )}
      </div>

      {can_dashboard_data && totals ? (
        <div className="space-y-4">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <KpiHero label="Líquido no período" value={totals.net} description="Vendas − A receber − Despesas" />
            <Kpi label="Vendas" value={totals.total_sell} icon="trending-up" description="incluindo impostos" />
            <Kpi
              label="A receber"
              value={totals.invoice_due}
              icon="hourglass"
              description="líquido de descontos de razão"
            />
            <Kpi label="Despesas" value={totals.total_expense} icon="receipt" description="lançadas no período" />
          </div>
          <Contrapartidas totals={totals} />
        </div>
      ) : (
        <section className="rounded-lg border border-border bg-card px-6 py-10 text-center">
          <p className="text-sm text-muted-foreground">
            Você não tem acesso aos dados do painel. A permissão <code className="font-mono">dashboard.data</code> não
            está atribuída ao seu papel — a tela abre sem erro, nenhum indicador é carregado.
          </p>
        </section>
      )}

      <div className="flex items-start gap-3 rounded-lg border border-border bg-accent px-4 py-3 text-sm text-muted-foreground">
        <Icon name="info" size={16} className="mt-0.5 shrink-0" />
        <span className="leading-relaxed">
          Precisa dos gráficos de vendas, alertas de estoque ou widgets de outros módulos?{' '}
          <a href={legacy_url} className="font-medium text-primary underline-offset-2 hover:underline">
            Abrir versão completa
          </a>
          .
        </span>
      </div>
    </div>
  );
}

HomeIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Visão geral" breadcrumbItems={[{ label: 'Visão geral' }]}>
    {page}
  </AppShellV2>
);

export default HomeIndex;
