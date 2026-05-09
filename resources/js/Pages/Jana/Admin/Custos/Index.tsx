// @memcofre
//   tela: /copiloto/admin/custos
//   module: Copiloto
//   stories: US-COPI-070
//   adrs: arq/0003 (Onda 1 — ROI direto), 0029 (padrão Inertia/React UltimatePOS)
//   tests: Modules/Copiloto/Tests/Feature/Admin/CustosControllerTest
//   status: implementada
//   permissao: copiloto.admin.custos.view

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';

type Preset = 'mes_atual' | 'mes_anterior' | '90d' | 'custom';

interface Kpis {
  custo_brl: number;
  mensagens: number;
  tokens: number;
  usuarios_ativos: number;
}

interface UsuarioRow {
  user_id: number;
  nome: string;
  conversas: number;
  mensagens: number;
  tokens: number;
  custo_brl: number;
}

interface DiaRow {
  data: string;
  custo_brl: number;
  tokens: number;
  mensagens: number;
}

interface Periodo {
  inicio: string;
  fim: string;
  label: string;
}

interface Filters {
  preset: Preset;
  de: string | null;
  ate: string | null;
}

interface Pricing {
  modelo_default: string;
  cambio_brl_usd: number;
}

interface Props {
  kpis: Kpis;
  por_usuario: UsuarioRow[];
  serie_diaria: DiaRow[];
  periodo: Periodo;
  filters: Filters;
  pricing: Pricing;
}

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v ?? 0);

function formatDataCurta(iso: string): string {
  const d = new Date(iso + 'T00:00:00');
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}

/**
 * Gráfico de área SVG inline — sem dependência externa.
 * Mesmo padrão do Sparkline em Pages/Copiloto/Dashboard.tsx.
 */
function GastoDiarioChart({ dados }: { dados: DiaRow[] }) {
  const w = 800;
  const h = 220;
  const pad = { top: 16, right: 16, bottom: 28, left: 56 };
  const innerW = w - pad.left - pad.right;
  const innerH = h - pad.top - pad.bottom;

  const valores = dados.map((d) => d.custo_brl);
  const max = Math.max(0.01, ...valores);
  const n = dados.length;

  if (n === 0) {
    return (
      <div className="text-center text-sm text-muted-foreground py-12">
        Sem dados no período.
      </div>
    );
  }

  const xAt = (i: number) => pad.left + (n === 1 ? innerW / 2 : (i / (n - 1)) * innerW);
  const yAt = (v: number) => pad.top + innerH - (v / max) * innerH;

  const linePts = dados.map((d, i) => `${xAt(i)},${yAt(d.custo_brl)}`).join(' ');

  const areaPts = [
    `${xAt(0)},${pad.top + innerH}`,
    ...dados.map((d, i) => `${xAt(i)},${yAt(d.custo_brl)}`),
    `${xAt(n - 1)},${pad.top + innerH}`,
  ].join(' ');

  const ticks = 4;
  const yTicks = Array.from({ length: ticks + 1 }, (_, i) => (max * i) / ticks);

  // X labels esparsas — máximo ~8 rótulos
  const stepX = Math.max(1, Math.ceil(n / 8));
  const xLabels = dados
    .map((d, i) => ({ d, i }))
    .filter(({ i }) => i % stepX === 0 || i === n - 1);

  return (
    <div className="w-full overflow-x-auto">
      <svg
        viewBox={`0 0 ${w} ${h}`}
        className="w-full h-auto text-primary"
        role="img"
        aria-label="Gasto de IA por dia"
      >
        {/* Grid horizontal + eixo Y */}
        {yTicks.map((t, i) => (
          <g key={`y-${i}`}>
            <line
              x1={pad.left}
              x2={pad.left + innerW}
              y1={yAt(t)}
              y2={yAt(t)}
              className="stroke-border"
              strokeDasharray="2 4"
            />
            <text
              x={pad.left - 6}
              y={yAt(t)}
              textAnchor="end"
              dominantBaseline="middle"
              className="fill-muted-foreground text-[10px]"
            >
              {brl(t)}
            </text>
          </g>
        ))}

        {/* Área */}
        <polygon points={areaPts} className="fill-primary/15" />
        {/* Linha */}
        <polyline
          points={linePts}
          fill="none"
          className="stroke-primary"
          strokeWidth={2}
          strokeLinecap="round"
          strokeLinejoin="round"
        />

        {/* X labels */}
        {xLabels.map(({ d, i }) => (
          <text
            key={`x-${i}`}
            x={xAt(i)}
            y={h - 8}
            textAnchor="middle"
            className="fill-muted-foreground text-[10px]"
          >
            {formatDataCurta(d.data)}
          </text>
        ))}
      </svg>
    </div>
  );
}

function CustosIaIndex(props: Props) {
  const { kpis, por_usuario, serie_diaria, periodo, filters, pricing } = props;

  const [de, setDe] = useState(filters.de ?? '');
  const [ate, setAte] = useState(filters.ate ?? '');

  const totalDoChart = useMemo(
    () => serie_diaria.reduce((acc, d) => acc + d.custo_brl, 0),
    [serie_diaria],
  );

  const aplicar = (patch: Partial<Filters>) => {
    router.get('/copiloto/admin/custos', { ...filters, ...patch }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

  const aplicarCustom = (e: React.FormEvent) => {
    e.preventDefault();
    aplicar({ preset: 'custom', de, ate });
  };

  return (
    <>
      <PageHeader
        icon="coins"
        title="Custos de IA"
        description={`Visão de consumo do Copiloto — ${periodo.label}`}
        action={
          <div className="text-xs text-muted-foreground text-right">
            <div>
              Modelo base: <span className="font-mono">{pricing.modelo_default}</span>
            </div>
            <div>Câmbio: R$ {pricing.cambio_brl_usd.toFixed(2)} / US$</div>
          </div>
        }
      />

      <KpiGrid cols={4} className="mt-6">
        <KpiCard
          icon="dollar-sign"
          tone="success"
          label="Esse período (R$)"
          value={brl(kpis.custo_brl)}
          description={`${num(kpis.tokens)} tokens consumidos`}
        />
        <KpiCard
          icon="message-square"
          tone="info"
          label="Mensagens"
          value={num(kpis.mensagens)}
          description={`${num(kpis.usuarios_ativos)} usuários ativos`}
        />
        <KpiCard
          icon="cpu"
          tone="default"
          label="Tokens consumidos"
          value={num(kpis.tokens)}
          description="Soma de input + output"
        />
        <KpiCard
          icon="users"
          tone="default"
          label="Usuários ativos"
          value={num(kpis.usuarios_ativos)}
          description="Que enviaram ou receberam mensagem"
        />
      </KpiGrid>

      {/* Filtro de período */}
      <Card className="mt-6 mb-4">
        <CardContent className="pt-6 flex flex-col md:flex-row gap-3 md:items-end">
          <div className="flex-1 min-w-[160px]">
            <label className="text-xs font-medium text-muted-foreground block mb-1">
              Período
            </label>
            <Select
              value={filters.preset}
              onValueChange={(v) => aplicar({ preset: v as Preset, de: null, ate: null })}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="mes_atual">Mês atual</SelectItem>
                <SelectItem value="mes_anterior">Mês anterior</SelectItem>
                <SelectItem value="90d">Últimos 90 dias</SelectItem>
                <SelectItem value="custom">Customizado</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {filters.preset === 'custom' && (
            <form onSubmit={aplicarCustom} className="flex-[2] flex gap-2 items-end">
              <div className="flex-1">
                <label className="text-xs font-medium text-muted-foreground block mb-1">De</label>
                <Input
                  type="date"
                  value={de}
                  onChange={(e) => setDe(e.target.value)}
                  required
                />
              </div>
              <div className="flex-1">
                <label className="text-xs font-medium text-muted-foreground block mb-1">Até</label>
                <Input
                  type="date"
                  value={ate}
                  onChange={(e) => setAte(e.target.value)}
                  required
                />
              </div>
              <Button type="submit" size="sm">
                Aplicar
              </Button>
            </form>
          )}
        </CardContent>
      </Card>

      {/* Gráfico de área */}
      <Card className="mb-4">
        <CardHeader>
          <CardTitle>Gasto diário</CardTitle>
          <CardDescription>
            {serie_diaria.length} {serie_diaria.length === 1 ? 'dia' : 'dias'} no período · total{' '}
            <span className="font-semibold text-foreground">{brl(totalDoChart)}</span>
          </CardDescription>
        </CardHeader>
        <CardContent>
          <GastoDiarioChart dados={serie_diaria} />
        </CardContent>
      </Card>

      {/* Tabela por usuário */}
      <Card>
        <CardHeader>
          <CardTitle>Por usuário</CardTitle>
          <CardDescription>
            {por_usuario.length} {por_usuario.length === 1 ? 'usuário' : 'usuários'} ativos no
            período
          </CardDescription>
        </CardHeader>
        <CardContent>
          {por_usuario.length === 0 ? (
            <div className="text-center py-12 text-muted-foreground">
              Nenhum consumo de IA no período.
              <br />
              <span className="text-xs mt-2 block">
                Quando alguém usar o Copiloto, o gasto aparece aqui automaticamente.
              </span>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b">
                    <th className="text-left py-2 px-2 font-medium">Usuário</th>
                    <th className="text-right py-2 px-2 font-medium">Conversas</th>
                    <th className="text-right py-2 px-2 font-medium">Mensagens</th>
                    <th className="text-right py-2 px-2 font-medium">Tokens</th>
                    <th className="text-right py-2 px-2 font-medium">R$ aprox.</th>
                  </tr>
                </thead>
                <tbody>
                  {por_usuario.map((u) => (
                    <tr key={u.user_id} className="border-b hover:bg-muted/40">
                      <td className="py-2 px-2">{u.nome}</td>
                      <td className="text-right py-2 px-2 font-mono">{num(u.conversas)}</td>
                      <td className="text-right py-2 px-2 font-mono">{num(u.mensagens)}</td>
                      <td className="text-right py-2 px-2 font-mono">{num(u.tokens)}</td>
                      <td className="text-right py-2 px-2 font-mono font-semibold">
                        {brl(u.custo_brl)}
                      </td>
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr className="border-t-2 font-semibold bg-muted/30">
                    <td className="py-2 px-2">Total</td>
                    <td className="text-right py-2 px-2 font-mono">
                      {num(por_usuario.reduce((a, u) => a + u.conversas, 0))}
                    </td>
                    <td className="text-right py-2 px-2 font-mono">{num(kpis.mensagens)}</td>
                    <td className="text-right py-2 px-2 font-mono">{num(kpis.tokens)}</td>
                    <td className="text-right py-2 px-2 font-mono">{brl(kpis.custo_brl)}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          )}
        </CardContent>
      </Card>
    </>
  );
}

CustosIaIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Copiloto — Custos de IA" breadcrumbItems={[{ label: 'Copiloto' }, { label: 'Custos de IA' }]}>
    {page}
  </AppShellV2>
);

export default CustosIaIndex;
