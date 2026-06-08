// @memcofre
//   tela: /copiloto/admin/governanca
//   module: Copiloto
//   stories: MEM-MCP-1.e (ADR 0053)
//   adrs: 0053, 0039 (Chat Cockpit — portada 2026-05-05)
//   tests: Modules/Copiloto/Tests/Feature/Admin/GovernancaControllerTest
//   status: implementada
//   permissao: copiloto.mcp.usage.all (Wagner/superadmin)

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useState, useEffect, useRef, type ReactNode } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { JanaAreaHeader } from '@/Pages/Jana/components/JanaAreaHeader';
import { ShieldCheck } from 'lucide-react';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import StatusBadge from '@/Components/shared/StatusBadge';
import EmptyState from '@/Components/shared/EmptyState';
import SubNav from '@/Components/shared/SubNav';

const LS_PRESET_KEY = 'oimpresso.copiloto.governanca.preset';
const LS_SECAO_KEY  = 'oimpresso.copiloto.governanca.secao';

type Secao    = 'consumo' | 'acesso' | 'usuarios';
type ChartMode = 'calls' | 'custo';

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

function statusBarClass(status: string): string {
  switch (status) {
    case 'ok':             return 'bg-emerald-500 h-2';
    case 'denied':         return 'bg-amber-500 h-2';
    case 'quota_exceeded': return 'bg-orange-500 h-2';
    default:               return 'bg-rose-500 h-2';
  }
}

/** Gráfico de calls/dia (linha) — suporta mode calls | custo. */
function CallsDiariasChart({ dados, mode = 'calls' }: { dados: DiaRow[]; mode?: ChartMode }) {
  const w = 800;
  const h = 220;
  const pad = { top: 16, right: 16, bottom: 28, left: 56 };
  const innerW = w - pad.left - pad.right;
  const innerH = h - pad.top - pad.bottom;

  const valores = dados.map((d) => mode === 'custo' ? d.custo_brl : d.calls);
  const max = Math.max(1, ...valores);
  const n = dados.length;

  if (n === 0) {
    return (
      <EmptyState
        icon="bar-chart-2"
        title="Sem dados no período"
        description="Nenhuma chamada MCP registrada no intervalo selecionado."
        variant="search"
        className="py-8"
      />
    );
  }

  const xAt = (i: number) => pad.left + (n === 1 ? innerW / 2 : (i / (n - 1)) * innerW);
  const yAt = (v: number) => pad.top + innerH - (v / max) * innerH;

  const mainVal = (d: DiaRow) => mode === 'custo' ? d.custo_brl : d.calls;
  const linePts  = dados.map((d, i) => `${xAt(i)},${yAt(mainVal(d))}`).join(' ');
  const deniedPts = mode === 'calls'
    ? dados.map((d, i) => `${xAt(i)},${yAt(d.denied)}`).join(' ')
    : null;
  const areaPts = [
    `${xAt(0)},${pad.top + innerH}`,
    ...dados.map((d, i) => `${xAt(i)},${yAt(mainVal(d))}`),
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
              {mode === 'custo' ? `R$${t.toFixed(0)}` : num(t)}
            </text>
          </g>
        ))}

        <polygon points={areaPts} className="fill-primary/15" />
        <polyline points={linePts} fill="none" className="stroke-primary" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
        {deniedPts && (
          <polyline points={deniedPts} fill="none" className="stroke-amber-400 dark:stroke-amber-300" strokeWidth={1.5} strokeDasharray="4 2" strokeLinecap="round" />
        )}

        {xLabels.map(({ d, i }) => (
          <text key={`x-${i}`} x={xAt(i)} y={h - 8} textAnchor="middle" className="fill-muted-foreground text-[10px]">
            {formatDataCurta(d.data)}
          </text>
        ))}
      </svg>
      <div className="flex gap-4 text-xs text-muted-foreground mt-2 ml-14">
        <span className="flex items-center gap-1.5">
          <span className="inline-block w-3 h-0.5 bg-primary"></span>
          {mode === 'custo' ? 'custo (R$)' : 'calls'}
        </span>
        {mode === 'calls' && (
          <span className="flex items-center gap-1.5">
            <span className="inline-block w-3 h-0.5 bg-amber-400 dark:bg-amber-300" style={{ borderTop: '1px dashed' }}></span> denied
          </span>
        )}
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
  const [secao, setSecao] = useState<Secao>(
    () => (localStorage.getItem(LS_SECAO_KEY) as Secao | null) ?? 'consumo',
  );
  const [chartMode, setChartMode] = useState<ChartMode>('calls');
  const selectRef = useRef<HTMLButtonElement>(null);

  // Persiste preset no localStorage (ADR 0039 §4)
  useEffect(() => {
    if (filters.preset !== 'custom') {
      localStorage.setItem(LS_PRESET_KEY, filters.preset);
    }
  }, [filters.preset]);

  // Persiste seção ativa
  useEffect(() => {
    localStorage.setItem(LS_SECAO_KEY, secao);
  }, [secao]);

  // Atalho '/' → foca no seletor de período (DESIGN.md §13)
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return;
      if (e.key === '/') {
        e.preventDefault();
        selectRef.current?.focus();
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, []);

  const aplicar = (patch: Partial<Filters>) => {
    router.get('/ia/admin/governanca', { ...filters, ...patch }, {
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
      <JanaAreaHeader active="governanca-mcp" />

      {/* Title local da tela — preservado pós-migração JanaAreaHeader (Wagner 2026-05-25) */}
      <div className="px-6 pt-6 flex items-start justify-between gap-4">
        <div className="flex items-center gap-3">
          <ShieldCheck className="size-6 text-primary" />
          <div>
            <h1 className="text-xl font-semibold">Governança MCP</h1>
            <p className="text-sm text-muted-foreground">
              Consumo cross-team do MCP server — {periodo.label}
            </p>
          </div>
        </div>
        <div className="text-xs text-muted-foreground text-right shrink-0">
          <div>Endpoint: <span className="font-mono">mcp.oimpresso.com</span></div>
          <div>Audit: <span className="font-mono">mcp_audit_log</span> (append-only)</div>
        </div>
      </div>

      {/* Sub-navegação por seção — variante underline */}
      <SubNav
        className="mt-4"
        variant="underline"
        value={secao}
        onChange={(v) => setSecao(v as Secao)}
        items={[
          { value: 'consumo',  label: 'Consumo',       icon: 'bar-chart-2' },
          { value: 'acesso',   label: 'Acesso / RBAC', icon: 'shield-check',
            badge: denied_por_codigo.length > 0 ? denied_por_codigo.length : undefined },
          { value: 'usuarios', label: 'Usuários',      icon: 'users' },
        ]}
      />

      {/* ── Seção: Consumo ───────────────────────────────────────────── */}
      {secao === 'consumo' && (
        <>
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
              <label className="text-xs font-medium text-muted-foreground block mb-1">
                Período{' '}
                <kbd className="ml-1 text-[10px] border border-border rounded px-1 py-0.5 font-mono">/</kbd>
              </label>
              <Select
                value={filters.preset}
                onValueChange={(v) => aplicar({ preset: v as Preset, de: null, ate: null })}
              >
                <SelectTrigger ref={selectRef}><SelectValue /></SelectTrigger>
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

        {/* Gráfico de chamadas */}
        <Card className="mb-4">
          <CardHeader className="flex flex-row items-start justify-between gap-4">
            <div>
              <CardTitle>Chamadas por dia</CardTitle>
              <CardDescription>
                {serie_diaria.length} dias · linha sólida = total · linha tracejada = denied
              </CardDescription>
            </div>
            <SubNav
              variant="segmented"
              value={chartMode}
              onChange={(v) => setChartMode(v as ChartMode)}
              items={[
                { value: 'calls', label: 'Calls',  icon: 'activity' },
                { value: 'custo', label: 'Custo',  icon: 'dollar-sign' },
              ]}
            />
          </CardHeader>
          <CardContent>
            <CallsDiariasChart dados={serie_diaria} mode={chartMode} />
          </CardContent>
        </Card>
        </>
      )}

      {/* ── Seção: Acesso / RBAC ─────────────────────────────────────── */}
      {secao === 'acesso' && (
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <Card>
          <CardHeader>
            <CardTitle>Distribuição por status</CardTitle>
            <CardDescription>{kpis.total_calls > 0 ? `${num(kpis.total_calls)} calls totais` : 'Sem chamadas'}</CardDescription>
          </CardHeader>
          <CardContent>
            {por_status.length === 0 ? (
              <EmptyState
                icon="activity"
                title="Sem dados de status"
                description="Nenhuma chamada registrada no período."
                className="py-6"
              />
            ) : (
              <div className="space-y-2">
                {por_status.map((s) => (
                  <div key={s.status} className="flex items-center gap-3">
                    <StatusBadge kind="mcp_status" value={s.status} className="font-mono text-xs" />
                    <div className="flex-1 bg-muted rounded h-2 overflow-hidden">
                      <div className={statusBarClass(s.status)} style={{ width: `${s.pct}%` }} />
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
              <EmptyState
                icon="shield-check"
                title="Nenhum denied no período"
                description="Todas as chamadas passaram nas políticas de acesso."
                variant="success"
                className="py-6"
              />
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
      )}

      {/* ── Seção: Usuários ───────────────────────────────────────────── */}
      {secao === 'usuarios' && (
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Card>
          <CardHeader>
            <CardTitle>Top tools/resources</CardTitle>
            <CardDescription>Mais invocadas no período</CardDescription>
          </CardHeader>
          <CardContent>
            {top_tools.length === 0 ? (
              <EmptyState
                icon="wrench"
                title="Sem chamadas de tools"
                description="Nenhuma tool ou resource foi invocada no período."
                className="py-6"
              />
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
              <EmptyState
                icon="users"
                title="Sem dados de usuários"
                description="Nenhum usuário consumiu o MCP no período."
                className="py-6"
              />
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
      )}
    </>
  );
}

GovernancaIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Copiloto — Governança MCP" breadcrumbItems={[{ label: 'Copiloto' }, { label: 'Governança MCP' }]}>
    {page}
  </AppShellV2>
);

export default GovernancaIndex;
