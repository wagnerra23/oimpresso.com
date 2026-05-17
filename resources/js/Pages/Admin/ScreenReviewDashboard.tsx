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
import { Head, router } from '@inertiajs/react';
import { Clock } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import PageHeaderActions from '@/Components/shared/PageHeaderActions';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';

import type { ScreenReviewMeta } from './ScreenReview';

interface Props {
  meta: ScreenReviewMeta;
}

function ScreenReviewDashboard({ meta }: Props) {
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
      </div>
    </>
  );
}

ScreenReviewDashboard.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Screen Review · Dashboard">{page}</AppShellV2>
);

export default ScreenReviewDashboard;
