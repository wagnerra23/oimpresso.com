// @memcofre tela=/admin/rag-quality module=Admin
// Wave 28 §G3 — RAG Quality Dashboard (KB + Jana pipeline observability).
//
// Charter ao lado: RagQualityDashboard.charter.md (status draft — Wave 28 §G3).
// Backend: Modules/Admin/Http/Controllers/RagQualityDashboardController.php
//
// 3 sparklines (retrieve/rerank/generate p99) + nDCG@5/recall@5 trend
// + top 10 queries lentas + fallback rate BGE.
//
// Cores: tokens semânticos (success/warning/destructive/primary) — sem cru (Constituição UI v2).
// Thresholds: const P99_THRESHOLDS / FALLBACK_THRESHOLDS (não hardcoded inline).
// Inertia::defer DEFAULT em props caras (D-14 pattern — RUNBOOK-inertia-defer-pattern.md).

import React from 'react';
import { Head, Deferred } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Icon } from '@/Components/Icon';

type Bucket = 'retrieve' | 'rerank' | 'generate';

interface DailyP99 {
  date: string;
  p99_ms: number;
  count: number;
}

interface LatencyBucket {
  spans: string[];
  daily_p99: DailyP99[];
}

interface NdcgPoint {
  date: string;
  ndcg_at_5: number;
  source: string;
}

interface RecallPoint {
  date: string;
  recall_at_5: number;
  source: string;
}

interface SlowQuery {
  query_hash: string;
  span_name: string;
  max_duration_ms: number;
  count: number;
}

interface FallbackRate {
  rerank_total: number;
  fallback_count: number;
  fallback_pct: number;
  window_days: number;
}

interface Meta {
  subdomain: string;
  environment: string;
  bypass_local: boolean;
  generated_at: string;
  window_days: number;
  bge_enabled: boolean;
  bge_endpoint: string;
  buckets: Bucket[];
}

interface PageProps {
  meta: Meta;
  latency_buckets?: Record<Bucket, LatencyBucket>;
  ndcg_trend?: NdcgPoint[];
  recall_trend?: RecallPoint[];
  top_slow_queries?: SlowQuery[];
  fallback_rate?: FallbackRate;
}

const BUCKET_LABEL: Record<Bucket, string> = {
  retrieve: 'Retrieve (Meilisearch hybrid)',
  rerank: 'Rerank (BGE-v2-m3)',
  generate: 'Generate (LLM call)',
};

const BUCKET_ICON: Record<Bucket, string> = {
  retrieve: 'search',
  rerank: 'arrow-up-down',
  generate: 'sparkles',
};

const BUCKET_ORDER: Bucket[] = ['retrieve', 'rerank', 'generate'];

// Thresholds centralizados (eram hardcoded inline — Wave 28 §G3 polish).
// p99 em ms; fallback em %. Espelham metas RAGAS/CT-100 BGE.
const P99_THRESHOLDS = { good: 300, warn: 800 } as const;
const FALLBACK_THRESHOLDS = { good: 5, warn: 15 } as const;

// Sparkline SVG inline (zero JS lib — pattern reusado de GovernanceV4Dashboard).
// Tooltip nativo por ponto (<title>) + marcador no último ponto (estado-da-arte observability).
function Sparkline({
  values,
  width = 120,
  height = 32,
  unit = 'ms',
  formatValue,
}: {
  values: number[];
  width?: number;
  height?: number;
  unit?: string;
  formatValue?: (v: number) => string;
}) {
  if (!values || values.length === 0) return <span className="text-xs text-zinc-400">sem dados</span>;
  const max = Math.max(...values, 1);
  const min = Math.min(...values, 0);
  const range = Math.max(max - min, 1);
  const step = values.length > 1 ? width / (values.length - 1) : width;
  const fmt = formatValue ?? ((v: number) => `${v}${unit}`);
  const coords = values.map((v, i) => ({
    x: i * step,
    y: height - ((v - min) / range) * height,
    v,
  }));
  const pts = coords.map((c) => `${c.x.toFixed(1)},${c.y.toFixed(1)}`).join(' ');
  // coords não-vazio (guard acima garante values.length > 0); fallback satisfaz noUncheckedIndexedAccess.
  const lastPoint = coords[coords.length - 1] ?? { x: 0, y: height, v: 0 };
  return (
    <svg
      width={width}
      height={height}
      className="inline-block overflow-visible"
      role="img"
      aria-label={`tendência ${values.length} pontos · último ${fmt(lastPoint.v)}`}
    >
      <polyline fill="none" stroke="currentColor" strokeWidth="1.5" points={pts} />
      {/* hover targets invisíveis com tooltip nativo por ponto */}
      {coords.map((c, i) => (
        <circle key={i} cx={c.x} cy={c.y} r={Math.max(step / 2, 3)} fill="transparent">
          <title>{fmt(c.v)}</title>
        </circle>
      ))}
      {/* marcador do último ponto (valor atual) */}
      <circle cx={lastPoint.x} cy={lastPoint.y} r="2.5" fill="currentColor">
        <title>{`atual: ${fmt(lastPoint.v)}`}</title>
      </circle>
    </svg>
  );
}

function p99Color(ms: number): string {
  if (ms <= P99_THRESHOLDS.good) return 'text-success';
  if (ms <= P99_THRESHOLDS.warn) return 'text-warning';
  return 'text-destructive';
}

function LatencyBucketCard({ bucket, data }: { bucket: Bucket; data?: LatencyBucket }) {
  const values = (data?.daily_p99 ?? []).map((d) => d.p99_ms);
  const last = values.length > 0 ? values[values.length - 1] ?? 0 : 0;
  const max = values.length > 0 ? Math.max(...values) : 0;
  const totalCount = (data?.daily_p99 ?? []).reduce((s, d) => s + d.count, 0);
  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center justify-between gap-2 text-base">
          <span className="flex items-center gap-2">
            <Icon name={BUCKET_ICON[bucket]} /> {BUCKET_LABEL[bucket]}
          </span>
          <span className={`text-xl font-bold ${p99Color(last)}`}>{last}ms</span>
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className={`mb-2 ${p99Color(last)}`}>
          <Sparkline values={values} unit="ms" />
        </div>
        <div className="text-xs text-zinc-500 grid grid-cols-2 gap-2">
          <div>último p99: <strong>{last}ms</strong></div>
          <div>pico janela: <strong>{max}ms</strong></div>
          <div>contagem: <strong>{totalCount.toLocaleString('pt-BR')}</strong></div>
          <div>spans: <code className="text-[10px]">{(data?.spans ?? []).length}</code></div>
        </div>
      </CardContent>
    </Card>
  );
}

function NdcgTrendCard({ points }: { points?: NdcgPoint[] }) {
  if (!points || points.length === 0) {
    return (
      <div className="text-sm text-zinc-500">
        Tabela <code className="text-xs bg-zinc-100 px-1 rounded">mcp_rag_evals</code> ainda não
        materializada (Wave 29 escopo). Trend nDCG@5 vai aparecer aqui após primeira execução de{' '}
        <code className="text-xs bg-zinc-100 px-1 rounded">kb:ragas-eval-snapshot</code>.
      </div>
    );
  }
  const values = points.map((p) => p.ndcg_at_5);
  const last = values[values.length - 1] ?? 0;
  const avg = values.length > 0 ? values.reduce((s, v) => s + v, 0) / values.length : 0;
  return (
    <div className="space-y-2">
      <div className="flex items-baseline gap-4">
        <div className="text-2xl font-bold text-success">{last.toFixed(3)}</div>
        <div className="text-xs text-zinc-500">média {avg.toFixed(3)} · n={points.length}d</div>
      </div>
      <div className="text-success">
        <Sparkline values={values} width={240} height={40} formatValue={(v) => v.toFixed(3)} />
      </div>
    </div>
  );
}

function RecallTrendCard({ points }: { points?: RecallPoint[] }) {
  if (!points || points.length === 0) {
    return (
      <div className="text-sm text-zinc-500">
        Recall@5 trend pendente (mesma fonte mcp_rag_evals).
      </div>
    );
  }
  const values = points.map((p) => p.recall_at_5);
  const last = values[values.length - 1] ?? 0;
  const avg = values.length > 0 ? values.reduce((s, v) => s + v, 0) / values.length : 0;
  return (
    <div className="space-y-2">
      <div className="flex items-baseline gap-4">
        <div className="text-2xl font-bold text-primary">{(last * 100).toFixed(1)}%</div>
        <div className="text-xs text-zinc-500">média {(avg * 100).toFixed(1)}% · n={points.length}d</div>
      </div>
      <div className="text-primary">
        <Sparkline values={values} width={240} height={40} formatValue={(v) => `${(v * 100).toFixed(1)}%`} />
      </div>
    </div>
  );
}

function TopSlowQueriesTable({ rows }: { rows?: SlowQuery[] }) {
  if (!rows || rows.length === 0) {
    return (
      <div className="text-sm text-zinc-500">
        Nenhuma query lenta detectada (mcp_observability_spans pode estar vazia em dev).
      </div>
    );
  }
  return (
    <table className="w-full text-sm">
      <thead>
        <tr className="text-left text-xs text-zinc-500 border-b">
          <th className="py-2">#</th>
          <th>span</th>
          <th>query_hash</th>
          <th className="text-right">max p99</th>
          <th className="text-right">count</th>
        </tr>
      </thead>
      <tbody>
        {rows.map((r, i) => (
          <tr key={`${r.span_name}-${r.query_hash}`} className="border-b last:border-b-0">
            <td className="py-1.5 text-zinc-400">{i + 1}</td>
            <td>
              <code className="text-xs bg-zinc-100 px-1 rounded">{r.span_name}</code>
            </td>
            <td>
              <code className="text-xs text-zinc-600">{r.query_hash}</code>
            </td>
            <td className={`text-right font-bold ${p99Color(r.max_duration_ms)}`}>
              {r.max_duration_ms}ms
            </td>
            <td className="text-right text-zinc-500">{r.count}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function FallbackRateCard({ data }: { data?: FallbackRate }) {
  if (!data) {
    return <div className="text-sm text-zinc-500">Carregando rate fallback…</div>;
  }
  const pctColor =
    data.fallback_pct <= FALLBACK_THRESHOLDS.good
      ? 'text-success'
      : data.fallback_pct <= FALLBACK_THRESHOLDS.warn
        ? 'text-warning'
        : 'text-destructive';
  return (
    <div className="space-y-2">
      <div className="flex items-baseline gap-4">
        <div className={`text-3xl font-bold ${pctColor}`}>{data.fallback_pct.toFixed(2)}%</div>
        <div className="text-xs text-zinc-500">
          {data.fallback_count.toLocaleString('pt-BR')} de{' '}
          {data.rerank_total.toLocaleString('pt-BR')} reranks
        </div>
      </div>
      <div className="text-xs text-zinc-600">
        {data.fallback_pct <= FALLBACK_THRESHOLDS.good ? (
          <span className="text-success">
            ✓ CT 100 BGE saudável (&lt;{FALLBACK_THRESHOLDS.good}%).
          </span>
        ) : data.fallback_pct <= FALLBACK_THRESHOLDS.warn ? (
          <span className="text-warning">
            ⚠ Acima da meta — investigar logs do container bge-reranker.
          </span>
        ) : (
          <span className="text-destructive">
            ⚠⚠ Drift crítico — container BGE down ou timeout. Conferir Tailscale + Docker.
          </span>
        )}
      </div>
    </div>
  );
}

export default function RagQualityDashboard({
  meta,
  latency_buckets,
  ndcg_trend,
  recall_trend,
  top_slow_queries,
  fallback_rate,
}: PageProps) {
  return (
    <>
      <Head title="RAG Quality · Observability" />
      <div className="container mx-auto p-4 space-y-4">
        <PageHeader
          icon="activity"
          title="RAG Quality — Observability"
          description={`Pipeline KB + Jana · janela ${meta.window_days}d · ${meta.subdomain} · ${meta.environment}${
            meta.bypass_local ? ' (BYPASS LOCAL)' : ''
          }`}
        />

        {/* Top-bar status BGE */}
        <div
          className={`border rounded px-4 py-2 text-sm flex items-center gap-2 ${
            meta.bge_enabled
              ? 'bg-success/10 border-success/30 text-success-foreground'
              : 'bg-warning/10 border-warning/30 text-warning-foreground'
          }`}
        >
          <Icon name={meta.bge_enabled ? 'check-circle' : 'alert-circle'} />
          <span>
            <strong>BGE Reranker v2-m3:</strong>{' '}
            {meta.bge_enabled ? 'ATIVO' : 'desabilitado'} ·{' '}
            <code className="text-xs">{meta.bge_endpoint || 'sem endpoint configurado'}</code>
          </span>
        </div>

        {/* 3 sparklines latency p99 */}
        <Deferred
          data="latency_buckets"
          fallback={
            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
              {BUCKET_ORDER.map((bk) => (
                <Card key={bk}>
                  <CardHeader>
                    <CardTitle className="text-base">{BUCKET_LABEL[bk]}</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="text-sm text-zinc-500">Carregando agregados…</div>
                  </CardContent>
                </Card>
              ))}
            </div>
          }
        >
          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            {BUCKET_ORDER.map((bk) => (
              <LatencyBucketCard
                key={bk}
                bucket={bk}
                data={(latency_buckets && latency_buckets[bk]) || undefined}
              />
            ))}
          </div>
        </Deferred>

        {/* nDCG@5 + Recall@5 side-by-side */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <Icon name="trending-up" /> nDCG@5 trend ({meta.window_days}d)
              </CardTitle>
            </CardHeader>
            <CardContent>
              <Deferred
                data="ndcg_trend"
                fallback={<div className="text-sm text-zinc-500">Carregando RAGAS evals…</div>}
              >
                <NdcgTrendCard points={ndcg_trend} />
              </Deferred>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <Icon name="target" /> Recall@5 trend ({meta.window_days}d)
              </CardTitle>
            </CardHeader>
            <CardContent>
              <Deferred
                data="recall_trend"
                fallback={<div className="text-sm text-zinc-500">Carregando recall trend…</div>}
              >
                <RecallTrendCard points={recall_trend} />
              </Deferred>
            </CardContent>
          </Card>
        </div>

        {/* Fallback rate BGE + Top 10 queries lentas side-by-side */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <Icon name="shield-alert" /> Fallback rate BGE ({meta.window_days}d)
              </CardTitle>
            </CardHeader>
            <CardContent>
              <Deferred
                data="fallback_rate"
                fallback={<div className="text-sm text-zinc-500">Calculando…</div>}
              >
                <FallbackRateCard data={fallback_rate} />
              </Deferred>
            </CardContent>
          </Card>

          <div className="lg:col-span-2">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                  <Icon name="clock" /> Top 10 queries lentas (cap 1000 spans)
                </CardTitle>
              </CardHeader>
              <CardContent>
                <Deferred
                  data="top_slow_queries"
                  fallback={
                    <div className="text-sm text-zinc-500">Agregando mcp_observability_spans…</div>
                  }
                >
                  <TopSlowQueriesTable rows={top_slow_queries} />
                </Deferred>
              </CardContent>
            </Card>
          </div>
        </div>

        <div className="text-xs text-zinc-400 text-center pt-2">
          Wave 28 §G3 · gerado {new Date(meta.generated_at).toLocaleString('pt-BR')} · janela{' '}
          {meta.window_days} dias
        </div>
      </div>
    </>
  );
}

RagQualityDashboard.layout = (page: React.ReactNode) => <AppShellV2>{page}</AppShellV2>;
