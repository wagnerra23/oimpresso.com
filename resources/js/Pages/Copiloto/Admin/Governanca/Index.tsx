// @memcofre
//   tela: /copiloto/admin/governanca
//   module: Copiloto
//   stories: MEM-MCP-1.e (ADR 0053)
//   adrs: 0053 (MCP server governança como produto)
//   tests: Modules/Copiloto/Tests/Feature/Admin/GovernancaControllerTest
//   status: implementada
//   permissao: copiloto.mcp.usage.all (Wagner/superadmin)

import AppShell from '@/Layouts/AppShell';
import { Head, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';

type Preset = 'hoje' | 'ontem' | '7d' | '30d' | 'mes_anterior' | 'custom';

interface Kpis {
  total_calls: number;
  usuarios_ativos: number;
  custo_total: number;
  tokens_total: number;
  latency_avg_ms: number;
}

interface PorStatus { status: string; calls: number; pct: number; }
interface Latency { p50: number; p95: number; p99: number; max: number; }
interface TopTool { tool: string; calls: number; custo_brl: number; }
interface TopUser { user_id: number; nome: string; calls: number; custo_brl: number; }
interface DeniedPorCodigo { error_code: string; calls: number; }
interface DiaRow { data: string; calls: number; custo_brl: number; denied: number; }

interface Periodo { inicio: string; fim: string; label: string; }
interface Filters { preset: Preset; de: string | null; ate: string | null; }

interface Props {
  kpis: Kpis;
  por_status: PorStatus[];
  latency: Latency;
  top_tools: TopTool[];
  top_users: TopUser[];
  denied_por_codigo: DeniedPorCodigo[];
  serie_diaria: DiaRow[];
  periodo: Periodo;
  filters: Filters;
}

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v ?? 0);

function formatDataCurta(iso: string): string {
  const d = new Date(iso + 'T00:00:00');
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}

function statusBadgeClass(status: string): string {
  switch (status) {
    case 'ok':              return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300';
    case 'denied':          return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300';
    case 'error':           return 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300';
    case 'quota_exceeded':  return 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300';
    default:                return 'bg-muted text-muted-foreground';
  }
}

/** Gráfico de calls/dia (linha) — mesmo padrão SVG do CustosController. */
function CallsDiariasChart({ dados }: { dados: DiaRow[] }) {
  const w = 800;
  const h = 220;
  const pad = { top: 16, right: 16, bottom: 28, left: 56 };
  const innerW = w - pad.left - pad.right;
  const innerH = h - pad.top - pad.bottom;

  const valores = dados.map((d) => d.calls);
  const max = Math.max(1, ...valores);
  const n = dados.length;

  if (n === 0) {
    return <div className="text-center text-sm text-muted-foreground py-12">Sem dados no período.</div>;
  }

  const xAt = (i: number) => pad.left + (n === 1 ? innerW / 2 : (i / (n - 1)) * innerW);
  const yAt = (v: number) => pad.top + innerH - (v / max) * innerH;

  const linePts = dados.map((d, i) => `${xAt(i)},${yAt(d.calls)}`).join(' ');
  const deniedPts = dados.map((d, i) => `${xAt(i)},${yAt(d.denied)}`).join(' ');
  const areaPts = [
    `${xAt(0)},${pad.top + innerH}`,
    ...dados.map((d, i) => `${xAt(i)},${yAt(d.calls)}`),
    `${xAt(n - 1)},${pad.top + innerH}`,
  ].join(' ');

  const ticks = 4;
  const yTicks = Array.from({ length: ticks + 1 }, (_, i) => Math.round((max * i) / ticks));
  const stepX = Math.max(1, Math.ceil(n / 8));
  const xLabels = dados.map((d, i) => ({ d, i })).filter(({ i }) => i % stepX === 0 || i === n - 1);

  return (
    <div className="w-full overflow-x-auto">
      <svg viewBox={`0 0 ${w} ${h}`} className="w-full h-auto text-primary" role="img" aria-label="Calls MCP por dia">
        {yTicks.map((t, i) => (
          <g key={`y-${i}`}>
            <line x1={pad.left} x2={pad.left + innerW} y1={yAt(t)} y2={yAt(t)} className="stroke-border" strokeDasharray="2 4" />
            <text x={pad.left - 6} y={yAt(t)} textAnchor="end" dominantBaseline="middle" className="fill-muted-foreground text-[10px]">
              {num(t)}
            </text>
          </g>
        ))}

        <polygon points={areaPts} className="fill-primary/15" />
        <polyline points={linePts} fill="none" className="stroke-primary" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
        <polyline points={deniedPts} fill="none" className="stroke-yellow-500" strokeWidth={1.5} strokeDasharray="4 2" strokeLinecap="round" />

        {xLabels.map(({ d, i }) => (
          <text key={`x-${i}`} x={xAt(i)} y={h - 8} textAnchor="middle" className="fill-muted-foreground text-[10px]">
            {formatDataCurta(d.data)}
          </text>
        ))}
      </svg>
      <div className="flex gap-4 text-xs text-muted-foreground mt-2 ml-14">
        <span className="flex items-center gap-1.5">
          <span className="inline-block w-3 h-0.5 bg-primary"></span> calls
        </span>
        <span className="flex items-center gap-1.5">
          <span className="inline-block w-3 h-0.5 bg-yellow-500" style={{ borderTop: '1px dashed' }}></span> denied
        </span>
      </div>
    </div>
  );
}

function GovernancaIndex(props: Props) {
  const {
    kpis, por_status, latency, top_tools, top_users,
    denied_por_codigo, serie_diaria, periodo, filters,
  } = props;

  const [de, setDe] = useState(filters.de ?? '');
  const [ate, setAte] = useState(filters.ate ?? '');

  const aplicar = (patch: Partial<Filters>) => {
    router.get('/copiloto/admin/governanca', { ...filters, ...patch }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

  const aplicarCustom = (e: React.FormEvent) => {
    e.preventDefault();
    aplicar({ preset: 'custom', de, ate });
  };

  const okCount = por_status.find((s) => s.status === 'ok')?.calls ?? 0;
  const taxaSucesso = kpis.total_calls > 0 ? (okCount / kpis.total_calls) * 100 : 0;

  return (
    <>
      <Head title="Copiloto — Governança MCP" />

      <PageHeader
        icon="shield-check"
        title="Governança MCP"
        description={`Consumo cross-team do MCP server — ${periodo.label}`}
        action={
          <div className="text-xs text-muted-foreground text-right">
            <div>Endpoint: <span className="font-mono">mcp.oimpresso.com</span></div>
            <div>Audit: <span className="font-mono">mcp_audit_log</span> (append-only)</div>
          </div>
        }
      />

      {/* KPIs principais */}
      <KpiGrid cols={4} className="mt-6">
        <KpiCard
          icon="activity"
          tone="info"
          label="Total de chamadas"
          value={num(kpis.total_calls)}
          description={`${num(kpis.usuarios_ativos)} usuários ativos`}
        />
        <KpiCard
          icon="check-circle"
          tone={taxaSucesso >= 95 ? 'success' : taxaSucesso >= 80 ? 'default' : 'warning'}
          label="Taxa de sucesso"
          value={`${taxaSucesso.toFixed(1)}%`}
          description={`${num(okCount)} de ${num(kpis.total_calls)} ok`}
        />
        <KpiCard
          icon="zap"
          tone="default"
          label="Latency p95"
          value={`${num(latency.p95)} ms`}
          description={`p50 ${num(latency.p50)} · p99 ${num(latency.p99)} · max ${num(latency.max)}`}
        />
        <KpiCard
          icon="dollar-sign"
          tone="success"
          label="Custo MCP (R$)"
          value={brl(kpis.custo_total)}
          description={`${num(kpis.tokens_total)} tokens consumidos`}
        />
      </KpiGrid>

      {/* Filtro de período */}
      <Card className="mt-6 mb-4">
        <CardContent className="pt-6 flex flex-col md:flex-row gap-3 md:items-end">
          <div className="flex-1 min-w-[160px]">
            <label className="text-xs font-medium text-muted-foreground block mb-1">Período</label>
            <Select
              value={filters.preset}
              onValueChange={(v) => aplicar({ preset: v as Preset, de: null, ate: null })}
            >
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="hoje">Hoje</SelectItem>
                <SelectItem value="ontem">Ontem</SelectItem>
                <SelectItem value="7d">Últimos 7 dias</SelectItem>
                <SelectItem value="30d">Últimos 30 dias</SelectItem>
                <SelectItem value="mes_anterior">Mês anterior</SelectItem>
                <SelectItem value="custom">Customizado</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {filters.preset === 'custom' && (
            <form onSubmit={aplicarCustom} className="flex-[2] flex gap-2 items-end">
              <div className="flex-1">
                <label className="text-xs font-medium text-muted-foreground block mb-1">De</label>
                <Input type="date" value={de} onChange={(e) => setDe(e.target.value)} required />
              </div>
              <div className="flex-1">
                <label className="text-xs font-medium text-muted-foreground block mb-1">Até</label>
                <Input type="date" value={ate} onChange={(e) => setAte(e.target.value)} required />
              </div>
              <Button type="submit" size="sm">Aplicar</Button>
            </form>
          )}
        </CardContent>
      </Card>

      {/* Gráfico calls/dia */}
      <Card className="mb-4">
        <CardHeader>
          <CardTitle>Chamadas por dia</CardTitle>
          <CardDescription>
            {serie_diaria.length} dias · linha sólida = total · linha tracejada = denied
          </CardDescription>
        </CardHeader>
        <CardContent>
          <CallsDiariasChart dados={serie_diaria} />
        </CardContent>
      </Card>

      {/* Linha 2: Status + Denied por error_code */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <Card>
          <CardHeader>
            <CardTitle>Distribuição por status</CardTitle>
            <CardDescription>{kpis.total_calls > 0 ? `${kpis.total_calls} calls totais` : 'Sem chamadas'}</CardDescription>
          </CardHeader>
          <CardContent>
            {por_status.length === 0 ? (
              <div className="text-center py-6 text-muted-foreground text-sm">Sem dados.</div>
            ) : (
              <div className="space-y-2">
                {por_status.map((s) => (
                  <div key={s.status} className="flex items-center gap-3">
                    <span className={`px-2 py-0.5 rounded text-xs font-mono ${statusBadgeClass(s.status)}`}>
                      {s.status}
                    </span>
                    <div className="flex-1 bg-muted rounded h-2 overflow-hidden">
                      <div
                        className={s.status === 'ok' ? 'bg-green-500 h-2' : s.status === 'denied' ? 'bg-yellow-500 h-2' : 'bg-red-500 h-2'}
                        style={{ width: `${s.pct}%` }}
                      />
                    </div>
                    <span className="text-xs font-mono w-16 text-right">{num(s.calls)}</span>
                    <span className="text-xs text-muted-foreground w-12 text-right">{s.pct}%</span>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Denied por error_code</CardTitle>
            <CardDescription>Quem foi bloqueado e por quê (debug RBAC)</CardDescription>
          </CardHeader>
          <CardContent>
            {denied_por_codigo.length === 0 ? (
              <div className="text-center py-6 text-muted-foreground text-sm">
                Nenhum denied no período.
              </div>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-xs text-muted-foreground">
                    <th className="text-left py-2 font-medium">error_code</th>
                    <th className="text-right py-2 font-medium">calls</th>
                  </tr>
                </thead>
                <tbody>
                  {denied_por_codigo.map((d) => (
                    <tr key={d.error_code} className="border-b last:border-0">
                      <td className="py-1.5 font-mono text-xs">{d.error_code}</td>
                      <td className="text-right py-1.5 font-mono">{num(d.calls)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Linha 3: Top tools + Top users */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Card>
          <CardHeader>
            <CardTitle>Top tools/resources</CardTitle>
            <CardDescription>Mais invocadas no período</CardDescription>
          </CardHeader>
          <CardContent>
            {top_tools.length === 0 ? (
              <div className="text-center py-6 text-muted-foreground text-sm">Sem dados.</div>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-xs text-muted-foreground">
                    <th className="text-left py-2 font-medium">Tool / Resource</th>
                    <th className="text-right py-2 font-medium">Calls</th>
                    <th className="text-right py-2 font-medium">Custo</th>
                  </tr>
                </thead>
                <tbody>
                  {top_tools.map((t) => (
                    <tr key={t.tool} className="border-b last:border-0 hover:bg-muted/40">
                      <td className="py-1.5 font-mono text-xs truncate max-w-[200px]" title={t.tool}>{t.tool}</td>
                      <td className="text-right py-1.5 font-mono">{num(t.calls)}</td>
                      <td className="text-right py-1.5 font-mono text-xs">{brl(t.custo_brl)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Top users</CardTitle>
            <CardDescription>Maior consumo do MCP</CardDescription>
          </CardHeader>
          <CardContent>
            {top_users.length === 0 ? (
              <div className="text-center py-6 text-muted-foreground text-sm">Sem dados.</div>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-xs text-muted-foreground">
                    <th className="text-left py-2 font-medium">Usuário</th>
                    <th className="text-right py-2 font-medium">Calls</th>
                    <th className="text-right py-2 font-medium">Custo</th>
                  </tr>
                </thead>
                <tbody>
                  {top_users.map((u) => (
                    <tr key={u.user_id} className="border-b last:border-0 hover:bg-muted/40">
                      <td className="py-1.5">{u.nome}</td>
                      <td className="text-right py-1.5 font-mono">{num(u.calls)}</td>
                      <td className="text-right py-1.5 font-mono text-xs">{brl(u.custo_brl)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </CardContent>
        </Card>
      </div>
    </>
  );
}

GovernancaIndex.layout = (page: ReactNode) => <AppShell>{page}</AppShell>;

export default GovernancaIndex;
