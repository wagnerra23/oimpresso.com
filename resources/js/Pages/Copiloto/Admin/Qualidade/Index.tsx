// @memcofre
//   tela: /copiloto/admin/qualidade
//   module: Copiloto
//   stories: MEM-MET-4 (ADR 0050)
//   permissao: copiloto.mcp.usage.all
//
// V1: KPIs por business (último valor) + gates verde/vermelho + tabela trend
// das 8 métricas obrigatórias + 3 RAGAS-aligned. Sem chart libs — sparklines
// SVG inline minimalistas (1 line por série).

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Label } from '@/Components/ui/label';
import { ScrollArea } from '@/Components/ui/scroll-area';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';

interface Ponto {
  data: string;
  recall_at_3: number | null;
  precision_at_3: number | null;
  mrr: number | null;
  latencia_p95_ms: number | null;
  tokens_medio: number | null;
  memory_bloat: number | null;
  taxa_contradicoes_pct: number | null;
  cross_tenant_violations: number;
  faithfulness: number | null;
  answer_relevancy: number | null;
  context_precision: number | null;
  total_interacoes_dia: number;
  total_memorias_ativas: number;
}

interface Serie {
  business_id: number | null;
  label: string;
  pontos: Ponto[];
}

interface Kpi {
  business_id: number | null;
  label: string;
  apurado_em: string;
  recall_at_3: number | null;
  precision_at_3: number | null;
  mrr: number | null;
  faithfulness: number | null;
  latencia_p95_ms: number | null;
  tokens_medio: number | null;
  taxa_contradicoes_pct: number | null;
  cross_tenant_violations: number;
  total_interacoes_dia: number;
}

interface Gate {
  op: '>=' | '<=' | '==';
  alvo: number;
  unit: string;
  label: string;
}

interface Props {
  series: Serie[];
  kpis: Kpi[];
  gates: Record<string, Gate>;
  filtros: { dias: number; business_id: number | null };
  gabarito_total: number;
  gabarito_por_categoria: Record<string, number>;
}

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v ?? 0);

function fmtPct(v: number | null, digits = 1): string {
  if (v === null || v === undefined) return '—';
  return (v * 100).toFixed(digits) + '%';
}
function fmtNum(v: number | null, digits = 3): string {
  if (v === null || v === undefined) return '—';
  return v.toFixed(digits);
}
function fmtMs(v: number | null): string {
  if (v === null || v === undefined) return '—';
  if (v >= 1000) return (v / 1000).toFixed(2) + 's';
  return v + 'ms';
}

function gateStatus(value: number | null, gate: Gate): { ok: boolean; emoji: string; color: string } | null {
  if (value === null) return null;
  let ok = false;
  if (gate.op === '>=') ok = value >= gate.alvo;
  if (gate.op === '<=') ok = value <= gate.alvo;
  if (gate.op === '==') ok = value === gate.alvo;
  return ok
    ? { ok: true, emoji: '✅', color: 'text-emerald-600 dark:text-emerald-400' }
    : { ok: false, emoji: '🔴', color: 'text-red-600 dark:text-red-400' };
}

/**
 * Sparkline SVG minimalista — sem dep externa. Width fixa 120, height 28.
 */
function Sparkline({ values, color = '#3b82f6' }: { values: (number | null)[]; color?: string }) {
  const w = 120, h = 28;
  const valid = values.filter((v): v is number => v !== null);
  if (valid.length < 2) {
    return <span className="text-[10px] text-muted-foreground">{valid.length} ponto{valid.length === 1 ? '' : 's'}</span>;
  }
  const min = Math.min(...valid);
  const max = Math.max(...valid);
  const range = max - min || 1;
  const points = values.map((v, i) => {
    if (v === null) return null;
    const x = (i / (values.length - 1)) * w;
    const y = h - ((v - min) / range) * h;
    return `${x.toFixed(1)},${y.toFixed(1)}`;
  }).filter(Boolean).join(' ');
  return (
    <svg width={w} height={h} className="inline-block">
      <polyline fill="none" stroke={color} strokeWidth="1.5" points={points} />
    </svg>
  );
}

function QualidadeIndex(props: Props) {
  const { series, kpis, gates, filtros, gabarito_total, gabarito_por_categoria } = props;
  const [businessFilter, setBusinessFilter] = useState<string>(
    filtros.business_id !== null ? String(filtros.business_id) : '__all__'
  );
  const [diasFilter, setDiasFilter] = useState<string>(String(filtros.dias));

  function applyFilter() {
    const params: Record<string, string | number> = { dias: Number(diasFilter) };
    if (businessFilter !== '__all__') params.business_id = Number(businessFilter);
    router.get('/copiloto/admin/qualidade', params, { preserveScroll: true, preserveState: true });
  }

  const allMetrics = [
    { key: 'recall_at_3', label: 'Recall@3', isPct: true, color: '#3b82f6', critical: true },
    { key: 'precision_at_3', label: 'Precision@3', isPct: true, color: '#10b981', critical: true },
    { key: 'mrr', label: 'MRR', isPct: false, color: '#8b5cf6', critical: true },
    { key: 'faithfulness', label: 'Faithfulness', isPct: true, color: '#f59e0b', critical: false },
    { key: 'latencia_p95_ms', label: 'Latência p95', isPct: false, color: '#ef4444', critical: true, isMs: true },
    { key: 'tokens_medio', label: 'Tokens médios', isPct: false, color: '#06b6d4', critical: false },
    { key: 'memory_bloat', label: 'Bloat ratio', isPct: true, color: '#84cc16', critical: false },
    { key: 'taxa_contradicoes_pct', label: 'Contradições %', isPct: false, color: '#ec4899', critical: false },
  ] as const;

  return (
    <>
      <PageHeader
        icon="trending-up"
        title="Qualidade IA"
        description={`Trend ${filtros.dias}d das 8 métricas obrigatórias + 3 RAGAS. Gates ADR 0049/0050. Eval contra gabarito ${gabarito_total} perguntas.`}
      />

      <Card className="mt-4">
        <CardContent className="py-3 flex flex-wrap items-end gap-3">
          <div className="w-32">
            <Label className="text-xs">Janela</Label>
            <Select value={diasFilter} onValueChange={setDiasFilter}>
              <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="7">7 dias</SelectItem>
                <SelectItem value="30">30 dias</SelectItem>
                <SelectItem value="60">60 dias</SelectItem>
                <SelectItem value="90">90 dias</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="w-40">
            <Label className="text-xs">Business</Label>
            <Select value={businessFilter} onValueChange={setBusinessFilter}>
              <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="__all__">Todos</SelectItem>
                <SelectItem value="0">Plataforma (NULL)</SelectItem>
                {series.filter(s => s.business_id !== null).map(s => (
                  <SelectItem key={s.business_id} value={String(s.business_id)}>{s.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <Button onClick={applyFilter} className="h-8 text-xs">Aplicar</Button>
          <span className="text-xs text-muted-foreground ml-auto">
            Gabarito: {gabarito_total} perguntas ·
            {Object.entries(gabarito_por_categoria).map(([k, v]) => ` ${k}=${v}`).join(' ·')}
          </span>
        </CardContent>
      </Card>

      {/* KPI cards por business — última leitura com gate status */}
      {kpis.map((kpi) => {
        const gateRecall = gateStatus(kpi.recall_at_3, gates.recall_at_3);
        const gatePrec = gateStatus(kpi.precision_at_3, gates.precision_at_3);
        const gateMrr = gateStatus(kpi.mrr, gates.mrr);
        const gateLat = gateStatus(kpi.latencia_p95_ms, gates.latencia_p95_ms);
        const gateTok = gateStatus(kpi.tokens_medio, gates.tokens_medio);
        const gateContra = gateStatus(kpi.taxa_contradicoes_pct, gates.taxa_contradicoes_pct);
        const gateXTenant = gateStatus(kpi.cross_tenant_violations, gates.cross_tenant_violations);

        return (
          <Card key={kpi.business_id ?? 'plataforma'} className="mt-4">
            <CardHeader className="pb-2">
              <div className="flex items-center justify-between">
                <CardTitle className="text-sm">
                  {kpi.label}
                  <span className="ml-2 text-xs text-muted-foreground font-normal">
                    última leitura {kpi.apurado_em} · {num(kpi.total_interacoes_dia)} interações no dia
                  </span>
                </CardTitle>
              </div>
            </CardHeader>
            <CardContent>
              <KpiGrid cols={4}>
                <KpiCard icon="target" tone={gateRecall?.ok ? 'success' : 'danger'}
                  label="Recall@3" value={fmtPct(kpi.recall_at_3)}
                  description={`gate ≥ ${fmtPct(gates.recall_at_3.alvo)} · ${gateRecall?.emoji ?? '—'}`} />
                <KpiCard icon="target" tone={gatePrec?.ok ? 'success' : 'warning'}
                  label="Precision@3" value={fmtPct(kpi.precision_at_3)}
                  description={`gate ≥ ${fmtPct(gates.precision_at_3.alvo)} · ${gatePrec?.emoji ?? '—'}`} />
                <KpiCard icon="trending-up" tone={gateMrr?.ok ? 'success' : 'warning'}
                  label="MRR" value={fmtNum(kpi.mrr, 3)}
                  description={`gate ≥ ${gates.mrr.alvo.toFixed(2)} · ${gateMrr?.emoji ?? '—'}`} />
                <KpiCard icon="shield-check" tone="default"
                  label="Faithfulness" value={fmtPct(kpi.faithfulness)}
                  description={`alvo ≥ ${fmtPct(gates.faithfulness.alvo)} (RAGAS)`} />
                <KpiCard icon="clock" tone={gateLat?.ok ? 'success' : 'danger'}
                  label="Latência p95" value={fmtMs(kpi.latencia_p95_ms)}
                  description={`gate ≤ ${fmtMs(gates.latencia_p95_ms.alvo)} · ${gateLat?.emoji ?? '—'}`} />
                <KpiCard icon="zap" tone={gateTok?.ok ? 'success' : 'warning'}
                  label="Tokens/interação" value={kpi.tokens_medio !== null ? num(kpi.tokens_medio) : '—'}
                  description={`gate ≤ ${num(gates.tokens_medio.alvo)} · ${gateTok?.emoji ?? '—'}`} />
                <KpiCard icon="alert-triangle" tone={gateContra?.ok ? 'success' : 'danger'}
                  label="Contradições" value={kpi.taxa_contradicoes_pct !== null ? kpi.taxa_contradicoes_pct.toFixed(2) + '%' : '—'}
                  description={`gate ≤ ${gates.taxa_contradicoes_pct.alvo}% · ${gateContra?.emoji ?? '—'}`} />
                <KpiCard icon="lock" tone={gateXTenant?.ok ? 'success' : 'danger'}
                  label="Cross-tenant" value={num(kpi.cross_tenant_violations)}
                  description={`gate = 0 · ${gateXTenant?.emoji ?? '—'}`} />
              </KpiGrid>
            </CardContent>
          </Card>
        );
      })}

      {/* Trend table com sparklines por série/métrica */}
      <Card className="mt-4">
        <CardHeader>
          <CardTitle className="text-sm">Trend {filtros.dias} dias</CardTitle>
          <CardDescription>Sparkline por business × métrica. Clique no business pra detalhe.</CardDescription>
        </CardHeader>
        <CardContent>
          <ScrollArea className="max-h-[600px]">
            <table className="w-full text-xs">
              <thead className="sticky top-0 bg-background z-10">
                <tr className="border-b">
                  <th className="text-left py-2 px-2 font-medium">Business</th>
                  <th className="text-center py-2 px-2 font-medium">N pontos</th>
                  {allMetrics.map(m => (
                    <th key={m.key} className="text-center py-2 px-2 font-medium" style={{ minWidth: 130 }}>
                      {m.label}
                      {m.critical && <span className="ml-1 text-red-500">*</span>}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {series.map((s) => (
                  <tr key={s.label} className="border-b">
                    <td className="py-2 px-2 font-medium">{s.label}</td>
                    <td className="text-center py-2 px-2">{s.pontos.length}</td>
                    {allMetrics.map(m => {
                      const values = s.pontos.map(p => (p as any)[m.key] as number | null);
                      const last = values[values.length - 1];
                      return (
                        <td key={m.key} className="text-center py-2 px-2">
                          <div className="flex flex-col items-center gap-0.5">
                            <Sparkline values={values} color={m.color} />
                            <span className="text-[10px] font-mono">
                              {m.isPct ? fmtPct(last) : m.isMs ? fmtMs(last) : last !== null ? num(last) : '—'}
                            </span>
                          </div>
                        </td>
                      );
                    })}
                  </tr>
                ))}
                {series.length === 0 && (
                  <tr>
                    <td colSpan={allMetrics.length + 2} className="text-center py-12 text-muted-foreground">
                      Sem dados de métricas. Rode <code className="font-mono">php artisan copiloto:metrics:apurar</code> ou
                      <code className="font-mono ml-1">copiloto:eval --persist</code> pra popular.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </ScrollArea>
          <div className="mt-3 text-[10px] text-muted-foreground">
            <span className="text-red-500">*</span> = gate ADR 0049 (bloqueante de evolução de camada se reprovado)
          </div>
        </CardContent>
      </Card>

      {/* Tabela tabular detalhada — última N runs */}
      <Card className="mt-4">
        <CardHeader>
          <CardTitle className="text-sm">Runs recentes (tabela detalhada)</CardTitle>
          <CardDescription>Últimas linhas em <code>copiloto_memoria_metricas</code> ordenadas por data desc.</CardDescription>
        </CardHeader>
        <CardContent className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b">
                <th className="text-left py-1 px-2 font-medium">Data</th>
                <th className="text-left py-1 px-2 font-medium">Business</th>
                <th className="text-right py-1 px-2 font-medium">Recall@3</th>
                <th className="text-right py-1 px-2 font-medium">Prec@3</th>
                <th className="text-right py-1 px-2 font-medium">MRR</th>
                <th className="text-right py-1 px-2 font-medium">Lat p95</th>
                <th className="text-right py-1 px-2 font-medium">Tok</th>
                <th className="text-right py-1 px-2 font-medium">Faith</th>
                <th className="text-right py-1 px-2 font-medium">Contra%</th>
                <th className="text-right py-1 px-2 font-medium">XT</th>
                <th className="text-right py-1 px-2 font-medium">Inter</th>
                <th className="text-right py-1 px-2 font-medium">Mems</th>
              </tr>
            </thead>
            <tbody>
              {series.flatMap(s => s.pontos.map(p => ({ s, p }))).sort((a, b) => (b.p.data ?? '').localeCompare(a.p.data ?? '')).slice(0, 30).map((row, i) => (
                <tr key={i} className="border-b hover:bg-muted/40">
                  <td className="py-1 px-2 font-mono text-[10px]">{row.p.data}</td>
                  <td className="py-1 px-2">{row.s.label}</td>
                  <td className="text-right py-1 px-2 font-mono">{fmtPct(row.p.recall_at_3)}</td>
                  <td className="text-right py-1 px-2 font-mono">{fmtPct(row.p.precision_at_3)}</td>
                  <td className="text-right py-1 px-2 font-mono">{fmtNum(row.p.mrr, 3)}</td>
                  <td className="text-right py-1 px-2 font-mono">{fmtMs(row.p.latencia_p95_ms)}</td>
                  <td className="text-right py-1 px-2 font-mono">{row.p.tokens_medio !== null ? num(row.p.tokens_medio) : '—'}</td>
                  <td className="text-right py-1 px-2 font-mono">{fmtPct(row.p.faithfulness)}</td>
                  <td className="text-right py-1 px-2 font-mono">{row.p.taxa_contradicoes_pct !== null ? row.p.taxa_contradicoes_pct.toFixed(1) : '—'}</td>
                  <td className="text-right py-1 px-2 font-mono">{row.p.cross_tenant_violations}</td>
                  <td className="text-right py-1 px-2 font-mono">{num(row.p.total_interacoes_dia)}</td>
                  <td className="text-right py-1 px-2 font-mono">{num(row.p.total_memorias_ativas)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </CardContent>
      </Card>

      <div className="mt-4 text-xs text-muted-foreground">
        <strong>Como atualizar:</strong> rodar <code className="font-mono">php artisan copiloto:metrics:apurar</code>
        (cron diário 23:55 já faz) ou <code className="font-mono">copiloto:eval --persist --business=4</code>
        (eval contra gabarito — popula Recall/Precision/MRR/Faithfulness).
        <br />
        <strong>Gates canônicos</strong> em ADR 0049 (Recall@3≥0.80 = bloqueante de evolução de camada) e ADR 0050.
        <br />
        <strong>HITL anotação</strong> ("essa resposta foi boa?") chega no V2 (Cycle 02).
      </div>
    </>
  );
}

QualidadeIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Qualidade IA — Métricas de memória" breadcrumbItems={[{ label: 'Copiloto' }, { label: 'Qualidade IA' }]}>
    {page}
  </AppShellV2>
);

export default QualidadeIndex;
