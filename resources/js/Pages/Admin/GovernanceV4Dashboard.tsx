// @memcofre tela=/admin/governance-v4 module=Admin
// Wave 27 polish (expandido de Wave 24 Agent B) — Governance v4 Dashboard intra-bucket (ADR 0160).
// Drift visualization + p99 lat + status filtros + AI baseline READ-ONLY 30d (anti-Goodhart Jellyfish 2025).
//
// Charter ao lado: GovernanceV4Dashboard.charter.md
// Backend: Modules/Admin/Http/Controllers/GovernanceV4DashboardController.php

import React, { useMemo, useState } from 'react';
import { Head, Deferred } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Icon } from '@/Components/Icon';

type BucketKey =
  | 'vertical_client_facing'
  | 'cross_cutting_infra'
  | 'ai_central'
  | 'functional_horizontal';

type StatusKey = 'ok' | 'warn' | 'crit';

interface BucketMeta {
  label: string;
  meta: number;
}

interface ModuleRow {
  slug: string;
  name: string;
  score: number;
  meta: number;
  status: StatusKey;
  trend: number[];
  paired_count: number;
  p99_ms: number | null;
}

interface DriftAlert {
  module: string;
  delta: number;
  from: number;
  to: number;
  snapshot_date: string;
  direction: 'up' | 'down';
}

interface AiSuggestion {
  module: string;
  avg_delta: number;
  count: number;
  last_justificativa: string;
  last_confidence: number;
  last_at: string | null;
}

interface PairedViolation {
  module: string;
  rule: string;
  reason: string;
}

interface Meta {
  subdomain: string;
  environment: string;
  bypass_local: boolean;
  generated_at: string;
  drift_threshold_pts: number;
  buckets: Record<BucketKey, BucketMeta>;
}

interface PageProps {
  meta: Meta;
  modules?: Record<BucketKey, ModuleRow[]>;
  drift_alerts?: DriftAlert[];
  ai_suggestions?: AiSuggestion[];
  paired_violations?: PairedViolation[];
}

const BUCKET_ORDER: BucketKey[] = [
  'vertical_client_facing',
  'cross_cutting_infra',
  'ai_central',
  'functional_horizontal',
];

const STATUS_BADGE: Record<StatusKey, { label: string; cls: string; icon: string }> = {
  ok: { label: 'OK', cls: 'bg-emerald-100 text-emerald-800 border-emerald-300', icon: '✓' },
  warn: { label: 'Atenção', cls: 'bg-amber-100 text-amber-800 border-amber-300', icon: '⚠' },
  crit: { label: 'Crítico', cls: 'bg-red-100 text-red-800 border-red-300', icon: '✗' },
};

function scoreColor(status: StatusKey): string {
  return status === 'ok' ? 'text-emerald-700' : status === 'warn' ? 'text-amber-700' : 'text-red-700';
}

function progressBg(status: StatusKey): string {
  return status === 'ok' ? 'bg-emerald-500' : status === 'warn' ? 'bg-amber-500' : 'bg-red-500';
}

// Sparkline SVG inline (zero JS lib). 30 pontos max, viewBox normalizado.
function Sparkline({ values }: { values: number[] }) {
  if (!values || values.length === 0) return <span className="text-xs text-zinc-400">—</span>;
  const max = Math.max(...values, 1);
  const min = Math.min(...values, 0);
  const range = Math.max(max - min, 1);
  const w = 80;
  const h = 24;
  const step = values.length > 1 ? w / (values.length - 1) : w;
  const pts = values
    .map((v, i) => {
      const x = i * step;
      const y = h - ((v - min) / range) * h;
      return `${x.toFixed(1)},${y.toFixed(1)}`;
    })
    .join(' ');
  return (
    <svg width={w} height={h} className="inline-block" aria-label="trend 30 dias">
      <polyline fill="none" stroke="currentColor" strokeWidth="1.5" points={pts} />
    </svg>
  );
}

function StatusBadge({ status }: { status: StatusKey }) {
  const b = STATUS_BADGE[status];
  return (
    <span className={`text-xs px-2 py-0.5 rounded border ${b.cls}`}>
      {b.icon} {b.label}
    </span>
  );
}

function ModuleCard({ row }: { row: ModuleRow }) {
  const pct = Math.min(100, Math.max(0, (row.score / Math.max(row.meta, 1)) * 100));
  return (
    <div className="border rounded-lg p-3 bg-white space-y-2">
      <div className="flex items-center justify-between gap-2">
        <div className="font-medium text-sm truncate">{row.name}</div>
        <div className={`text-xl font-bold ${scoreColor(row.status)}`}>{row.score}</div>
      </div>
      <div className="flex items-center justify-between gap-2 text-xs">
        <StatusBadge status={row.status} />
        <span className="text-zinc-500">meta {row.meta}</span>
      </div>
      <div className="w-full bg-zinc-100 rounded-full h-1.5">
        <div
          className={`h-1.5 rounded-full ${progressBg(row.status)}`}
          style={{ width: `${pct}%` }}
        />
      </div>
      <div className="flex items-center justify-between gap-2">
        <div className={scoreColor(row.status)}>
          <Sparkline values={row.trend} />
        </div>
        <div className="text-xs text-zinc-400 text-right">
          {row.p99_ms !== null && (
            <div>p99 {row.p99_ms.toFixed(0)}ms</div>
          )}
          {row.paired_count > 0 && (
            <div className="text-red-700 font-semibold">⚠ {row.paired_count} paired</div>
          )}
        </div>
      </div>
    </div>
  );
}

function BucketSection({
  bucketKey,
  bucketMeta,
  rows,
}: {
  bucketKey: BucketKey;
  bucketMeta: BucketMeta;
  rows: ModuleRow[];
}) {
  const avg = rows.length
    ? Math.round(rows.reduce((s, r) => s + r.score, 0) / rows.length)
    : 0;
  const critCount = rows.filter((r) => r.status === 'crit').length;
  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center justify-between gap-2 text-base">
          <span className="flex items-center gap-2">
            <Icon name="layers" /> {bucketMeta.label}
          </span>
          <span className="text-sm font-normal text-zinc-500">
            meta ≥{bucketMeta.meta} · média {avg} · {rows.length} módulos
            {critCount > 0 && <span className="ml-2 text-red-700 font-semibold">· {critCount} crítico(s)</span>}
          </span>
        </CardTitle>
      </CardHeader>
      <CardContent>
        {rows.length === 0 ? (
          <div className="text-sm text-zinc-500">Nenhum módulo classificado neste bucket (após filtros).</div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
            {rows.map((r) => (
              <ModuleCard key={r.slug} row={r} />
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

function DriftAlertsBanner({ alerts, threshold }: { alerts: DriftAlert[]; threshold: number }) {
  if (!alerts || alerts.length === 0) {
    return (
      <div className="bg-emerald-50 border border-emerald-300 text-emerald-900 rounded px-4 py-2 text-sm flex items-center gap-2">
        <Icon name="check-circle" />
        <span>
          ✓ Nenhum drift detectado nos últimos 30d (threshold ±{threshold}pts).
        </span>
      </div>
    );
  }
  const downCount = alerts.filter((a) => a.direction === 'down').length;
  return (
    <Card className="border-red-300">
      <CardHeader className="pb-2">
        <CardTitle className="flex items-center gap-2 text-base text-red-800">
          <Icon name="trending-down" /> Drift Alerts ({alerts.length}) · {downCount} queda(s)
        </CardTitle>
      </CardHeader>
      <CardContent>
        <ul className="space-y-1 text-sm">
          {alerts.slice(0, 8).map((a, i) => (
            <li
              key={`${a.module}-${i}`}
              className="flex items-center justify-between gap-2 border-l-4 pl-3 py-1"
              style={{ borderLeftColor: a.direction === 'down' ? '#dc2626' : '#059669' }}
            >
              <div>
                <span className="font-medium">{a.module}</span>
                <span className="text-xs text-zinc-500 ml-2">
                  {new Date(a.snapshot_date).toLocaleDateString('pt-BR')}
                </span>
              </div>
              <div className={`font-bold ${a.direction === 'down' ? 'text-red-700' : 'text-emerald-700'}`}>
                {a.from} → {a.to} ({a.delta > 0 ? '+' : ''}
                {a.delta}pts)
              </div>
            </li>
          ))}
          {alerts.length > 8 && (
            <li className="text-xs text-zinc-500 italic">
              … e mais {alerts.length - 8} drift(s). Ver `mcp_alertas kind='scorecard_drift'`.
            </li>
          )}
        </ul>
      </CardContent>
    </Card>
  );
}

function AiSuggestionsList({ suggestions }: { suggestions: AiSuggestion[] }) {
  if (!suggestions || suggestions.length === 0) {
    return (
      <div className="text-sm text-zinc-500">
        Nenhuma sugestão AI ainda. Baseline começa após primeira execução de{' '}
        <code className="text-xs bg-zinc-100 px-1 rounded">governance:ai-baseline-run</code>.
      </div>
    );
  }
  return (
    <ul className="space-y-2 text-sm">
      {suggestions.map((s) => (
        <li key={s.module} className="border-l-4 border-amber-400 pl-3 py-1">
          <div className="flex items-center justify-between gap-2">
            <span className="font-medium">{s.module}</span>
            <span
              className={`font-bold ${s.avg_delta < 0 ? 'text-red-700' : 'text-emerald-700'}`}
            >
              {s.avg_delta > 0 ? '+' : ''}
              {s.avg_delta}
            </span>
          </div>
          <div className="text-xs text-zinc-600 italic">{s.last_justificativa}</div>
          <div className="text-xs text-zinc-400">
            n={s.count} · confiança {Math.round(s.last_confidence * 100)}%
          </div>
        </li>
      ))}
    </ul>
  );
}

function PairedViolationsList({ violations }: { violations: PairedViolation[] }) {
  if (!violations || violations.length === 0) {
    return (
      <div className="text-sm text-emerald-700">
        ✓ Nenhuma paired violation ativa (anti-Goodhart verde).
      </div>
    );
  }
  return (
    <ul className="space-y-1 text-sm">
      {violations.map((v, i) => (
        <li key={`${v.module}-${i}`} className="flex items-start gap-2">
          <span className="text-red-600 mt-0.5">⚠</span>
          <div>
            <span className="font-medium">{v.module}</span> ·{' '}
            <code className="text-xs bg-zinc-100 px-1 rounded">{v.rule}</code>
            {v.reason && <div className="text-xs text-zinc-500 italic">{v.reason}</div>}
          </div>
        </li>
      ))}
    </ul>
  );
}

export default function GovernanceV4Dashboard({
  meta,
  modules,
  drift_alerts,
  ai_suggestions,
  paired_violations,
}: PageProps) {
  const [activeBucket, setActiveBucket] = useState<BucketKey | 'all'>('all');
  const [statusFilter, setStatusFilter] = useState<StatusKey | 'all'>('all');
  const [driftOnly, setDriftOnly] = useState(false);

  // Slugs com drift pra filtro cruzado
  const driftSlugs = useMemo(() => {
    const set = new Set<string>();
    (drift_alerts || []).forEach((a) => set.add(a.module));
    return set;
  }, [drift_alerts]);

  // Filtragem cliente (Inertia::defer já trouxe payload completo)
  const filteredModules: Record<BucketKey, ModuleRow[]> = useMemo(() => {
    const out: Record<BucketKey, ModuleRow[]> = {
      vertical_client_facing: [],
      cross_cutting_infra: [],
      ai_central: [],
      functional_horizontal: [],
    };
    if (!modules) return out;
    BUCKET_ORDER.forEach((bk) => {
      out[bk] = (modules[bk] || []).filter((r) => {
        if (statusFilter !== 'all' && r.status !== statusFilter) return false;
        if (driftOnly && !driftSlugs.has(r.slug)) return false;
        return true;
      });
    });
    return out;
  }, [modules, statusFilter, driftOnly, driftSlugs]);

  return (
    <>
      <Head title="Governance v4 · Scoped Scorecards" />
      <div className="container mx-auto p-4 space-y-4">
        {/* Top-bar BASELINE READ-ONLY */}
        <div className="bg-amber-50 border border-amber-300 text-amber-900 rounded px-4 py-2 text-sm flex items-center gap-2">
          <Icon name="alert-circle" />
          <span>
            <strong>AI Baseline READ-ONLY 30d</strong> — sugestões observacionais não alteram
            score oficial (anti-Goodhart, Jellyfish 2025).
          </span>
        </div>

        <PageHeader
          icon="bar-chart-3"
          title="Governance v4 — Scoped Scorecards"
          description={`4 buckets canônicos (ADR 0160) · ${meta.subdomain} · ${meta.environment}${
            meta.bypass_local ? ' (BYPASS LOCAL)' : ''
          }`}
        />

        {meta.bypass_local && (
          <div className="bg-amber-100 border border-amber-300 text-amber-900 rounded px-3 py-2 text-sm">
            ⚠️ ADMIN_BYPASS_LOCAL ativo — middlewares Tailscale + IsWagner desabilitados.
          </div>
        )}

        {/* Drift alerts banner (Wave 27) */}
        <Deferred
          data="drift_alerts"
          fallback={<div className="text-sm text-zinc-500">Computando drift…</div>}
        >
          <DriftAlertsBanner
            alerts={drift_alerts || []}
            threshold={meta.drift_threshold_pts}
          />
        </Deferred>

        {/* Filtros (Wave 27) — bucket / status / drift */}
        <div className="flex flex-wrap items-center gap-2 text-sm">
          <span className="text-zinc-500 font-medium">Bucket:</span>
          <button
            onClick={() => setActiveBucket('all')}
            className={`px-3 py-1 rounded ${
              activeBucket === 'all'
                ? 'bg-zinc-900 text-white'
                : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200'
            }`}
          >
            Todos
          </button>
          {BUCKET_ORDER.map((bk) => (
            <button
              key={bk}
              onClick={() => setActiveBucket(bk)}
              className={`px-3 py-1 rounded ${
                activeBucket === bk
                  ? 'bg-zinc-900 text-white'
                  : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200'
              }`}
            >
              {meta.buckets[bk].label}
            </button>
          ))}

          <span className="text-zinc-500 font-medium ml-4">Status:</span>
          {(['all', 'ok', 'warn', 'crit'] as const).map((s) => (
            <button
              key={s}
              onClick={() => setStatusFilter(s)}
              className={`px-3 py-1 rounded ${
                statusFilter === s
                  ? 'bg-zinc-900 text-white'
                  : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200'
              }`}
            >
              {s === 'all' ? 'Todos' : STATUS_BADGE[s].label}
            </button>
          ))}

          <label className="flex items-center gap-1 ml-4 cursor-pointer">
            <input
              type="checkbox"
              checked={driftOnly}
              onChange={(e) => setDriftOnly(e.target.checked)}
              className="rounded"
            />
            <span className="text-zinc-700">Só com drift</span>
          </label>
        </div>

        {/* Buckets */}
        <Deferred
          data="modules"
          fallback={<div className="text-sm text-zinc-500">Carregando rubricas YAML…</div>}
        >
          <div className="space-y-4">
            {BUCKET_ORDER.filter((bk) => activeBucket === 'all' || activeBucket === bk).map(
              (bk) => (
                <BucketSection
                  key={bk}
                  bucketKey={bk}
                  bucketMeta={meta.buckets[bk]}
                  rows={filteredModules[bk] || []}
                />
              ),
            )}
          </div>
        </Deferred>

        {/* AI baseline + paired violations side-by-side */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <Icon name="sparkles" /> AI Suggestions (baseline 30d)
              </CardTitle>
            </CardHeader>
            <CardContent>
              <Deferred
                data="ai_suggestions"
                fallback={<div className="text-sm text-zinc-500">Carregando sugestões AI…</div>}
              >
                <AiSuggestionsList suggestions={ai_suggestions || []} />
              </Deferred>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <Icon name="shield-alert" /> Paired Violations (anti-Goodhart)
              </CardTitle>
            </CardHeader>
            <CardContent>
              <Deferred
                data="paired_violations"
                fallback={<div className="text-sm text-zinc-500">Carregando violations…</div>}
              >
                <PairedViolationsList violations={paired_violations || []} />
              </Deferred>
            </CardContent>
          </Card>
        </div>

        <div className="text-xs text-zinc-400 text-center pt-2">
          ADR 0160 · Wave 27 polish · gerado {new Date(meta.generated_at).toLocaleString('pt-BR')}
        </div>
      </div>
    </>
  );
}

GovernanceV4Dashboard.layout = (page: React.ReactNode) => <AppShellV2>{page}</AppShellV2>;
