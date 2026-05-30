// @memcofre
//   tela: /admin/screen-review/dashboard
//   module: Admin
//   stories: ONDA 7 Wave 31 W31-D (split Wagner pediu 2026-05-17)
//   charter: resources/js/Pages/Admin/ScreenReviewDashboard.charter.md
//   adrs: 0104, 0122, 0160
//
// Landing leve com 5 KPIs PDCA do Screen Review. Wagner pediu split:
// KPIs sairam da tri-pane operacional (`/admin/screen-review`) e viraram
// tela dedicada com nav `Screen Review` pro retorno.

import * as React from 'react';
import { Head, router, Deferred } from '@inertiajs/react';
import { Clock } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import PageHeaderActions from '@/Components/shared/PageHeaderActions';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import { Skeleton } from '@/Components/ui/skeleton';

import type { ScreenReviewMeta } from './ScreenReview';

// ── Maturidade método 9.75 (baseline no git · ADR 0239 R1) ──────────────
interface GradeRow {
  screen: string;
  nota: number;
  nivel: string;
  persona: string;
  gap: string;
}
interface ModuleAvg {
  module: string;
  n: number;
  media: number;
}
export interface GradeSummary {
  available: boolean;
  total: number;
  media: number;
  dist: Record<string, number>;
  priorities: GradeRow[];
  goldens: GradeRow[];
  by_module: ModuleAvg[];
}

const LV_ORDER = ['Champion', 'Leader', 'Advanced', 'Developing', 'Beginner'];
const LV_COLOR: Record<string, string> = {
  Champion: 'oklch(0.72 0.15 85)',
  Leader: 'oklch(0.62 0.14 150)',
  Advanced: 'oklch(0.58 0.13 250)',
  Developing: 'oklch(0.72 0.14 70)',
  Beginner: 'oklch(0.62 0.2 25)',
};

interface Props {
  meta: ScreenReviewMeta;
  grades?: GradeSummary;
}

function ScreenReviewDashboard({ meta, grades }: Props) {
  return (
    <>
      <Head title="Screen Review · Dashboard" />

      <div className="flex flex-col gap-3 px-4 py-3">
        <PageHeader
          icon="layout-dashboard"
          title="Screen Review · Dashboard"
          description={`Visão PDCA · ${meta.total_telas} telas · gerado ${new Date(meta.generated_at).toLocaleString('pt-BR')}`}
          action={
            <PageHeaderActions
              items={[
                { label: 'Dashboard', icon: 'layout-dashboard', href: '/admin/screen-review/dashboard', active: true },
                { label: 'Triagem', icon: 'list-checks', href: '/admin/screen-review', count: meta.pending_count },
                { label: 'Reload', icon: 'refresh-cw', onClick: () => router.reload(), variant: 'ghost' },
              ]}
            />
          }
        />

        <KpiGrid cols={5}>
          <KpiCard
            label="Total telas"
            value={meta.total_telas}
            description=".tsx em Pages/"
            icon="layers"
            tone="default"
          />
          <KpiCard
            label="Pendentes Wagner"
            value={meta.pending_count}
            description="sem .review.md ou último round=pending"
            icon="clock"
            tone={meta.pending_count > 0 ? 'warning' : 'success'}
          />
          <KpiCard
            label="Aprovadas"
            value={meta.approved_count}
            description="último round=approved"
            icon="check"
            tone={meta.approved_count > 0 ? 'success' : 'default'}
          />
          <KpiCard
            label="Em iteração"
            value={meta.iterate_count}
            description="loop F1.5 ativo"
            icon="refresh-cw"
            tone={meta.iterate_count > 0 ? 'info' : 'default'}
          />
          <KpiCard
            label="Rejeitadas"
            value={meta.rejected_count}
            description="último round=rejected"
            icon="x"
            tone={meta.rejected_count > 0 ? 'danger' : 'default'}
          />
        </KpiGrid>

        {meta.pending_over_7d > 0 && (
          <div
            role="alert"
            className="flex items-center gap-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-[12px] text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200"
          >
            <Clock size={14} className="shrink-0" />
            <span>
              <strong>{meta.pending_over_7d}</strong> tela
              {meta.pending_over_7d === 1 ? '' : 's'} pendente
              {meta.pending_over_7d === 1 ? '' : 's'} há &gt; 7 dias — Wagner
              precisa revisar.
            </span>
          </div>
        )}

        <Deferred data="grades" fallback={<Skeleton className="h-56 w-full rounded-md" />}>
          <MaturitySection grades={grades} />
        </Deferred>
      </div>
    </>
  );
}

// ── Seção de maturidade (método 9.75) ────────────────────────────────────
function MaturitySection({ grades }: { grades?: GradeSummary }) {
  if (!grades || !grades.available || grades.total === 0) {
    return null; // sem baseline → degrada silencioso
  }
  const total = grades.total;
  return (
    <section className="rounded-md border border-border bg-card p-4">
      <div className="mb-3 flex flex-wrap items-baseline gap-x-3 gap-y-1">
        <h2 className="text-[13px] font-bold uppercase tracking-wide text-muted-foreground">
          Maturidade de design · método 9.75
        </h2>
        <span className="text-[11px] text-muted-foreground">{total} telas graduadas</span>
        <span
          className="ml-auto text-2xl font-bold tabular-nums"
          style={{ color: 'var(--primary)' }}
        >
          {grades.media}
          <span className="text-sm text-muted-foreground">/100</span>
        </span>
      </div>

      <div className="mb-2 flex h-3.5 overflow-hidden rounded-full border border-border">
        {LV_ORDER.map((lv) => {
          const c = grades.dist[lv] ?? 0;
          if (!c) return null;
          return (
            <span
              key={lv}
              title={`${lv}: ${c}`}
              style={{ width: `${(c / total) * 100}%`, background: LV_COLOR[lv] }}
            />
          );
        })}
      </div>
      <div className="mb-4 flex flex-wrap gap-x-4 gap-y-1 text-[11px]">
        {LV_ORDER.map((lv) => (
          <span key={lv} className="flex items-center gap-1.5">
            <i className="h-2 w-2 rounded-full" style={{ background: LV_COLOR[lv] }} />
            {lv} <b className="tabular-nums">{grades.dist[lv] ?? 0}</b>
          </span>
        ))}
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <div>
          <h3 className="mb-2 text-[12px] font-semibold text-rose-700 dark:text-rose-300">
            🔴 Prioridades — menor nota
          </h3>
          <ul className="space-y-1">
            {grades.priorities.map((p) => (
              <GradeLi key={p.screen} row={p} />
            ))}
          </ul>
        </div>
        <div>
          <h3 className="mb-2 text-[12px] font-semibold text-emerald-700 dark:text-emerald-300">
            🏅 Goldens — referência
          </h3>
          <ul className="space-y-1">
            {grades.goldens.map((p) => (
              <GradeLi key={p.screen} row={p} />
            ))}
          </ul>
        </div>
      </div>

      <div className="mt-4">
        <h3 className="mb-2 text-[12px] font-semibold text-muted-foreground">
          Média por módulo — mais fracos
        </h3>
        <div className="grid gap-x-6 gap-y-1.5 sm:grid-cols-2">
          {grades.by_module.map((m) => (
            <div key={m.module} className="flex items-center gap-2 text-[12px]">
              <span className="w-28 shrink-0 truncate font-mono">{m.module}</span>
              <span className="h-2 flex-1 overflow-hidden rounded bg-muted">
                <i
                  className="block h-full"
                  style={{
                    width: `${m.media}%`,
                    background:
                      m.media >= 85 ? LV_COLOR.Leader : m.media >= 70 ? LV_COLOR.Advanced : LV_COLOR.Developing,
                  }}
                />
              </span>
              <span className="w-7 text-right font-bold tabular-nums">{m.media}</span>
            </div>
          ))}
        </div>
      </div>

      <p className="mt-3 text-[11px] text-muted-foreground">
        Baseline no git (ADR 0239 R1) · board completo em{' '}
        <code className="rounded bg-muted px-1">memory/governance/scorecards/screen-grade-board.html</code> ·
        grade estático · nota só sobe (ratchet · ADR 0236).
      </p>
    </section>
  );
}

function GradeLi({ row }: { row: GradeRow }) {
  return (
    <li className="flex items-center gap-2 text-[12px]">
      <span
        className="w-9 shrink-0 text-right font-bold tabular-nums"
        style={{ color: LV_COLOR[row.nivel] ?? 'var(--foreground)' }}
      >
        {row.nota}
      </span>
      <span className="flex-1 truncate font-mono">{row.screen}</span>
      {row.gap && (
        <span className="hidden shrink-0 text-[10px] text-muted-foreground sm:inline">{row.gap}</span>
      )}
    </li>
  );
}

ScreenReviewDashboard.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Screen Review · Dashboard">{page}</AppShellV2>
);

export default ScreenReviewDashboard;
