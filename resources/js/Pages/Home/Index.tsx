// @memcofre tela=/dashboard-legacy module=Dashboard
// Visão geral — Rewrite Cockpit V2 (US-DASH-004).
// Âncora de design: prototipo-ui/cowork/dash-legacy-page.jsx (rota `dash-legacy`, atalho
// "Visão geral" em data.jsx:19). Substitui o F6 Soft wrapper de 2026-05-22, que o [W]
// declarou tentativa descartada em 2026-08-27.
//
// Layout por PRIMITIVOS (ADR 0253): Stack/Inline/Grid, nunca `flex`/`grid` solto.
//
// Fora desta onda, por motivo declarado:
//   · gráficos (US-DASH-002) — não há lib de chart no package.json; entra com ADR própria
//   · abas de grade e Pendências (US-DASH-005) — consomem os 4 endpoints AJAX existentes
//   · "Papel simulado" / "Simular falha" — instrumentos do protótipo. Em produção quem
//     decide é a permissão real e a resposta real do endpoint.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Icon } from '@/Components/Icon';
import { Grid, Inline, Stack } from '@/Components/layout';
import EmptyState from '@/Components/shared/EmptyState';
import { KpiCard } from '@/Components/shared/KpiCard';
import { KpiGrid } from '@/Components/shared/KpiGrid';
import PageHeader from '@/Components/shared/PageHeader';
import { PeriodBar, type Period } from '@/Components/shared/PeriodBar';
import { Alert, AlertDescription } from '@/Components/ui/alert';
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

const CARTAO = 'rounded-lg border border-border bg-card p-5 shadow-sm';
const ROTULO = 'text-[10px] font-semibold uppercase tracking-wider text-muted-foreground';

/** KPI em destaque — o número que responde "como foi o período". */
function KpiHero({ label, value, description }: { label: string; value: number; description?: string }) {
  return (
    <Stack gap={1} className={`${CARTAO} ring-1 ring-primary/15`}>
      <span className={ROTULO}>{label}</span>
      <span className="font-mono text-3xl font-semibold tracking-tight text-foreground">{brl(value)}</span>
      {description && <span className="text-[12px] text-muted-foreground">{description}</span>}
    </Stack>
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
    <Stack gap={4} asChild>
      <section aria-label="Contrapartidas" className={CARTAO}>
        <Inline gap={3} align="baseline" justify="between">
          <h2 className="text-[13.5px] font-semibold text-foreground">Contrapartidas</h2>
          <span className="font-mono text-[10.5px] text-muted-foreground">mesmo período</span>
        </Inline>
        <Grid cols={2} gap={4} className="lg:grid-cols-4">
          {itens.map(([label, valor, sub]) => (
            <Stack gap={1} key={label} className="min-w-0">
              <span className="text-[9.5px] font-semibold uppercase tracking-wider text-muted-foreground">
                {label}
              </span>
              <span className="font-mono text-[15.5px] font-semibold tabular-nums text-foreground">
                {brlCurto(valor)}
              </span>
              <span className="text-[11.5px] text-muted-foreground">{sub}</span>
            </Stack>
          ))}
        </Grid>
      </section>
    </Stack>
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
    <Stack gap={5} className="mx-auto max-w-7xl p-6">
      <PageHeader
        title="Visão geral"
        icon="layout-dashboard"
        description={
          totals
            ? `${brlCurto(totals.total_sell)} vendas · ${brlCurto(totals.invoice_due)} a receber · ${brlCurto(totals.total_expense)} despesas`
            : undefined
        }
        action={
          <span className="text-[12.5px] text-muted-foreground">
            Bem-vindo{user_name ? `, ${user_name}` : ''}
          </span>
        }
      />

      <Inline gap={4} align="end" justify="between" wrap>
        <PeriodBar period={period} />
        {mostraLoja && (
          <Stack gap={2} asChild>
            <label>
              <span className={ROTULO}>Loja</span>
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
          </Stack>
        )}
      </Inline>

      {can_dashboard_data && totals ? (
        <Stack gap={4}>
          <KpiGrid cols={4}>
            <KpiHero label="Líquido no período" value={totals.net} description="Vendas − A receber − Despesas" />
            <KpiCard
              label="Vendas"
              value={brl(totals.total_sell)}
              icon="trending-up"
              description="incluindo impostos"
            />
            <KpiCard
              label="A receber"
              value={brl(totals.invoice_due)}
              icon="hourglass"
              tone="warning"
              description="líquido de descontos de razão"
            />
            <KpiCard
              label="Despesas"
              value={brl(totals.total_expense)}
              icon="receipt"
              description="lançadas no período"
            />
          </KpiGrid>
          <Contrapartidas totals={totals} />
        </Stack>
      ) : (
        <EmptyState
          icon="lock"
          title="Você não tem acesso aos dados do painel"
          description="A permissão dashboard.data não está atribuída ao seu papel. A tela abre sem erro — nenhum indicador é carregado."
        />
      )}

      <Alert>
        <Icon name="info" size={16} />
        <AlertDescription>
          Precisa dos gráficos de vendas, alertas de estoque ou widgets de outros módulos?{' '}
          <a href={legacy_url} className="font-medium text-primary underline-offset-2 hover:underline">
            Abrir versão completa
          </a>
          .
        </AlertDescription>
      </Alert>
    </Stack>
  );
}

HomeIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Visão geral" breadcrumbItems={[{ label: 'Visão geral' }]}>
    {page}
  </AppShellV2>
);

export default HomeIndex;
