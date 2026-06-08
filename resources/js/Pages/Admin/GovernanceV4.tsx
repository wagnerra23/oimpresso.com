// @memcofre
//   tela: /admin/governance/v4
//   module: Admin
//   stories: ONDA 7 Governance v4 — Wave 29 W29-C
//   charter: resources/js/Pages/Admin/GovernanceV4.charter.md
//   adrs: 0160, 0161, 0162, 0163, 0039, 0104, 0110, 0093, 0094
//
// W29-C — tri-pane copy do blueprint validado `kb/Index.v2.tsx`.
// Gate visual F1.5 SKIP (charter §mwart_pattern_reuse: blueprint kb_v2 já
// aprovado Wagner ONDA 2). Divergência semântica:
//   - sidebar = Buckets canon (4 fixos ADR 0160) + Wave history
//   - lista   = Módulos do bucket selecionado + filter chips pills
//   - leitor  = ModuleReader (header KPIs + sparkline + 9 dimensões +
//               initiatives + AI panel)
//
// Backend pendente W29-B (Controller `indexV2` apontando Inertia::render
// 'Admin/GovernanceV4'). Quando props pesadas (modules/scorecards/drifts/
// initiatives/aiSuggestions/healthSnapshot) virem ausentes, página entra em
// modo MOCK degradado mas funcional.
//
// Coexiste com `Admin/GovernanceV4Dashboard.tsx` (Wave 27 polish) — não
// substitui até cutover ONDA 7 via flag `meta.v4_enabled`.

import * as React from 'react';
import { Head, useForm, Deferred } from '@inertiajs/react';
import { toast } from 'sonner';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import PageHeaderActions from '@/Components/shared/PageHeaderActions';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import { Skeleton } from '@/Components/ui/skeleton';

import BucketSidebar from './_components/BucketSidebar';
import ModuleList, { type ModuleListFilters } from './_components/ModuleList';
import ModuleReader from './_components/ModuleReader';
import DriftAlertBanner from './_components/DriftAlertBanner';
import CommandPaletteV4 from './_components/CommandPaletteV4';
import HealthPanelV4 from './_components/HealthPanelV4';
import {
  BUCKET_LABEL_FALLBACK,
  BUCKET_ORDER,
  type BucketDef,
  type BucketKey,
  type GovernanceV4PageProps,
  type ModuleRow,
} from './_components/governanceV4Types';

import '../../../css/kb.css';

const LS_PREFIX = 'oimpresso.governance.v4.';
const LS_BUCKET = `${LS_PREFIX}bucket`;
const LS_MODULE = `${LS_PREFIX}module`;
const LS_FILTERS = `${LS_PREFIX}filters`;
const LS_WAVE_EXPANDED = `${LS_PREFIX}waveExpanded`;

function loadJson<T>(key: string, fallback: T): T {
  if (typeof window === 'undefined') return fallback;
  try {
    const raw = window.localStorage.getItem(key);
    return raw ? (JSON.parse(raw) as T) : fallback;
  } catch {
    return fallback;
  }
}

function saveJson(key: string, value: unknown): void {
  if (typeof window === 'undefined') return;
  try {
    window.localStorage.setItem(key, JSON.stringify(value));
  } catch {
    /* quota — ignore */
  }
}

const DEFAULT_FILTERS: ModuleListFilters = {
  q: '',
  metaOnly: false,
  driftOnly: false,
  scoreRange: 'all',
  statusOnly: null,
};

function GovernanceV4(props: GovernanceV4PageProps) {
  const meta = props.meta;
  const can = props.can ?? {
    create_initiative: true,
    override_bucket: true,
    refresh_now: true,
  };

  // ── persistência localStorage (charter §UX Targets) ────────────────
  const [selectedBucket, setSelectedBucketState] = React.useState<BucketKey | 'all'>(
    () => loadJson<BucketKey | 'all'>(LS_BUCKET, 'all'),
  );
  const [selectedSlug, setSelectedSlugState] = React.useState<string | null>(() =>
    loadJson<string | null>(LS_MODULE, null),
  );
  const [filters, setFiltersState] = React.useState<ModuleListFilters>(() =>
    loadJson<ModuleListFilters>(LS_FILTERS, DEFAULT_FILTERS),
  );
  const [waveExpanded, setWaveExpandedState] = React.useState<boolean>(() =>
    loadJson<boolean>(LS_WAVE_EXPANDED, false),
  );

  const setSelectedBucket = (b: BucketKey | 'all') => {
    setSelectedBucketState(b);
    saveJson(LS_BUCKET, b);
  };
  const setSelectedSlug = (s: string | null) => {
    setSelectedSlugState(s);
    saveJson(LS_MODULE, s);
  };
  const setFilters = (f: ModuleListFilters) => {
    setFiltersState(f);
    saveJson(LS_FILTERS, f);
  };
  const setWaveExpanded = (v: boolean) => {
    setWaveExpandedState(v);
    saveJson(LS_WAVE_EXPANDED, v);
  };

  // ── overlays state ────────────────────────────────────────────────
  const [paletteOpen, setPaletteOpen] = React.useState(false);
  const [healthOpen, setHealthOpen] = React.useState(false);

  // ── derived buckets (sempre 4 — preenchidos via prop ou vazios) ───
  const bucketsDef: BucketDef[] = React.useMemo(() => {
    return BUCKET_ORDER.map((key) => {
      const bucketMeta = meta.buckets?.[key];
      const list = props.modules?.[key] ?? [];
      return {
        key,
        label: bucketMeta?.label ?? BUCKET_LABEL_FALLBACK[key],
        meta: bucketMeta?.meta ?? 80,
        count: list.length,
      };
    });
  }, [meta.buckets, props.modules]);

  // ── flat modules list pra command palette + 'all' view ────────────
  const allModules: ModuleRow[] = React.useMemo(() => {
    if (!props.modules) return [];
    return BUCKET_ORDER.flatMap((k) =>
      (props.modules![k] ?? []).map((m) => ({ ...m, bucket: k })),
    );
  }, [props.modules]);

  // ── módulos visíveis no painel central ────────────────────────────
  const bucketModules: ModuleRow[] =
    selectedBucket === 'all'
      ? allModules
      : props.modules?.[selectedBucket] ?? [];

  const bucketLabel =
    selectedBucket === 'all'
      ? 'Todos os módulos'
      : meta.buckets?.[selectedBucket]?.label ?? BUCKET_LABEL_FALLBACK[selectedBucket];
  const bucketMeta =
    selectedBucket === 'all' ? 0 : meta.buckets?.[selectedBucket]?.meta ?? 80;

  // ── drifts set pra cruzamento rápido ─────────────────────────────
  const driftedSlugs = React.useMemo(
    () => new Set((props.drifts ?? []).map((d) => d.module)),
    [props.drifts],
  );

  // ── seleção módulo / navegação prev/next ─────────────────────────
  const selectedModule: ModuleRow | null =
    (selectedSlug && bucketModules.find((m) => m.slug === selectedSlug)) ||
    (selectedSlug && allModules.find((m) => m.slug === selectedSlug)) ||
    null;

  const currentIdx = selectedModule
    ? bucketModules.findIndex((m) => m.slug === selectedModule.slug)
    : -1;
  const prevModule = currentIdx > 0 ? bucketModules[currentIdx - 1] : null;
  const nextModule =
    currentIdx >= 0 && currentIdx < bucketModules.length - 1
      ? bucketModules[currentIdx + 1]
      : null;

  const selectedScorecard =
    selectedModule && props.scorecards
      ? props.scorecards[selectedModule.slug] ?? null
      : null;

  // ── KPIs header ────────────────────────────────────────────────────
  const headerKpis = React.useMemo(() => {
    if (allModules.length === 0) {
      return {
        avg: 0,
        excelente: 0,
        drifts: (props.drifts ?? []).length,
        initiativesOpen: 0,
        target: 97.75, // meta global histórica, charter §Mission
      };
    }
    const sum = allModules.reduce((acc, m) => acc + m.score, 0);
    const avg = Math.round((sum / allModules.length) * 100) / 100;
    const excelente = allModules.filter((m) => m.score >= 90).length;
    const initiativesOpen = (props.initiatives ?? []).filter(
      (i) => i.status === 'open' || i.status === 'in_progress',
    ).length;
    return {
      avg,
      excelente,
      drifts: (props.drifts ?? []).length,
      initiativesOpen,
      target: 97.75,
    };
  }, [allModules, props.drifts, props.initiatives]);

  // ── keyboard ⌘K / Ctrl+K ─────────────────────────────────────────
  React.useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        setPaletteOpen((v) => !v);
      } else if (e.key === 'Escape') {
        if (paletteOpen) setPaletteOpen(false);
        else if (healthOpen) setHealthOpen(false);
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [paletteOpen, healthOpen]);

  // ── Inertia useForm — POST initiative + override ─────────────────
  const initiativeForm = useForm<{
    module: string;
    title: string;
    deadline_at?: string;
    notes?: string;
  }>({
    module: '',
    title: '',
    deadline_at: undefined,
    notes: undefined,
  });

  const overrideForm = useForm<{ module: string; bucket: BucketKey; reason: string }>({
    module: '',
    bucket: 'vertical_client_facing',
    reason: '',
  });

  const openInitiativeNew = (moduleSlug: string) => {
    initiativeForm.setData('module', moduleSlug);
    // ONDA 7+ — sub-component InitiativeComposerDialog (W29-D pendente).
    // Atalho v1: prompt nativo até dialog estar pronta (mock visível pra Wagner).
    if (typeof window === 'undefined') return;
    const title = window.prompt(
      `Initiative pra ${moduleSlug}\n\nTítulo curto da iniciativa:`,
    );
    if (!title) return;
    initiativeForm.setData('title', title);
    initiativeForm.post('/admin/governance/v4/initiative', {
      preserveScroll: true,
      onSuccess: () => toast.success(`Initiative criada pra ${moduleSlug}`),
      onError: () =>
        toast.error('Falha ao criar Initiative — verifique payload + permissão'),
    });
  };

  const openOverride = (moduleSlug: string) => {
    overrideForm.setData('module', moduleSlug);
    if (typeof window === 'undefined') return;
    const reason = window.prompt(
      `Override bucket de ${moduleSlug}\n\nMotivo (vai virar label PR bucket-change-approved):`,
    );
    if (!reason) return;
    overrideForm.setData('reason', reason);
    overrideForm.post('/admin/governance/v4/override-bucket', {
      preserveScroll: true,
      onSuccess: () =>
        toast.success(`Override solicitado pra ${moduleSlug} — PR pendente`),
      onError: () => toast.error('Falha ao disparar override workflow'),
    });
  };

  const refreshNow = (moduleSlug?: string) => {
    toast.info(
      moduleSlug
        ? `Disparando refresh módulo ${moduleSlug}…`
        : 'Disparando refresh global module:grade-v4…',
    );
    // ONDA 7+: GET /admin/governance/v4/refresh?module=...
    // Por ora apenas reload Inertia (controller recomputa sob demanda quando indexV2 lá)
    if (typeof window !== 'undefined') {
      window.setTimeout(() => window.location.reload(), 600);
    }
  };

  return (
    <>
      <Head title="Governance v4" />

      <div className="flex flex-col gap-3 px-4 py-3 min-h-0 h-[calc(100vh-3.5rem)]">
        <PageHeader
          icon="bar-chart-3"
          title="Governance v4"
          description={
            meta.v4_enabled
              ? `Tri-pane · ${allModules.length} módulos × ${bucketsDef.length} buckets canon · gerado ${new Date(meta.generated_at).toLocaleString('pt-BR')}`
              : 'Tri-pane MOCK — v4_enabled=false (rollout ONDA 7+)'
          }
          action={
            <PageHeaderActions
              items={[
                { label: 'Buscar (⌘K)', icon: 'search', onClick: () => setPaletteOpen(true), variant: 'ghost' },
                { label: 'Screen Review', icon: 'camera', href: '/admin/screen-review' },
                { label: 'Saúde v4', icon: 'heart-pulse', onClick: () => setHealthOpen(true), variant: 'ghost' },
                ...(can.refresh_now ? [{ label: 'Refresh now', icon: 'refresh-cw', onClick: () => refreshNow(), variant: 'ghost' as const }] : []),
                ...(can.override_bucket && selectedModule
                  ? [{ label: 'Override bucket', icon: 'settings-2', onClick: () => openOverride(selectedModule.slug), variant: 'ghost' as const }]
                  : []),
                ...(can.create_initiative
                  ? [{
                      label: 'Initiative manual',
                      icon: 'plus',
                      onClick: () => openInitiativeNew(selectedModule?.slug ?? 'oimpresso'),
                      pinned: true,
                      active: true,
                    }]
                  : []),
              ]}
              maxVisible={3}
            />
          }
        />

        {/* KPIs header — overview macro */}
        <KpiGrid cols={5}>
          <KpiCard
            label="Média atual"
            value={headerKpis.avg || '—'}
            description={`${allModules.length} módulos avaliados`}
            icon="bar-chart-3"
            tone={
              headerKpis.avg >= headerKpis.target
                ? 'success'
                : headerKpis.avg >= headerKpis.target - 5
                  ? 'warning'
                  : 'default'
            }
            size="compact"
          />
          <KpiCard
            label="Meta global"
            value={headerKpis.target}
            description="ADR 0160 · teto natural"
            icon="target"
            tone="info"
            size="compact"
          />
          <KpiCard
            label="Excelente (≥90)"
            value={headerKpis.excelente}
            description="módulos passando meta"
            icon="trophy"
            tone={headerKpis.excelente > 0 ? 'success' : 'default'}
            size="compact"
          />
          <KpiCard
            label="Drifts ativos"
            value={headerKpis.drifts}
            description={`Δ > ±${meta.drift_threshold_pts}pts/7d`}
            icon="alert-triangle"
            tone={headerKpis.drifts > 0 ? 'danger' : 'success'}
            size="compact"
          />
          <KpiCard
            label="Initiatives abertas"
            value={headerKpis.initiativesOpen}
            description="open + in_progress"
            icon="clipboard-list"
            tone={headerKpis.initiativesOpen > 0 ? 'warning' : 'default'}
            size="compact"
          />
        </KpiGrid>

        {/* Drift banner persistente (não dismissable se >0) */}
        <Deferred data="drifts" fallback={<Skeleton className="h-10 w-full" />}>
          <DriftAlertBanner
            drifts={props.drifts ?? []}
            thresholdPts={meta.drift_threshold_pts}
            onPickModule={(slug) => {
              const m = allModules.find((x) => x.slug === slug);
              if (m?.bucket) setSelectedBucket(m.bucket);
              setSelectedSlug(slug);
            }}
            onViewAll={() => {
              setFilters({ ...filters, driftOnly: true });
              setSelectedBucket('all');
              toast.info('Filtro "Drift" aplicado.');
            }}
          />
        </Deferred>

        {/* Tri-pane */}
        <div
          className="kb-tri rounded-md border border-border overflow-hidden flex-1 min-h-0"
          data-mobile-view="list"
        >
          <Deferred
            data="modules"
            fallback={<SidebarSkeleton />}
          >
            <BucketSidebar
              buckets={bucketsDef}
              selectedBucket={selectedBucket}
              onSelect={(b) => {
                setSelectedBucket(b);
                setSelectedSlug(null);
              }}
              waveHistory={props.waveHistory ?? []}
              waveExpanded={waveExpanded}
              onToggleWaveHistory={() => setWaveExpanded(!waveExpanded)}
            />
          </Deferred>

          <Deferred data="modules" fallback={<ListSkeleton />}>
            <ModuleList
              modules={bucketModules}
              bucketLabel={bucketLabel}
              bucketMeta={bucketMeta}
              filters={filters}
              onChangeFilters={setFilters}
              driftedSlugs={driftedSlugs}
              selectedSlug={selectedSlug}
              onSelectModule={setSelectedSlug}
            />
          </Deferred>

          <Deferred data={['scorecards', 'initiatives', 'aiSuggestions']} fallback={<ReaderSkeleton />}>
            <ModuleReader
              module={selectedModule}
              scorecard={selectedScorecard}
              initiatives={props.initiatives ?? []}
              aiSuggestions={props.aiSuggestions ?? []}
              prev={prevModule}
              next={nextModule}
              onPickPrev={() => prevModule && setSelectedSlug(prevModule.slug)}
              onPickNext={() => nextModule && setSelectedSlug(nextModule.slug)}
              onOpenInitiativeNew={
                can.create_initiative ? openInitiativeNew : undefined
              }
              onOpenOverride={can.override_bucket ? openOverride : undefined}
              onRefresh={can.refresh_now ? refreshNow : undefined}
            />
          </Deferred>
        </div>

        {/* Overlays */}
        <CommandPaletteV4
          open={paletteOpen}
          onOpenChange={setPaletteOpen}
          modules={allModules}
          initiatives={props.initiatives ?? []}
          waves={props.waveHistory ?? []}
          onPickModule={(slug) => {
            const m = allModules.find((x) => x.slug === slug);
            if (m?.bucket) setSelectedBucket(m.bucket);
            setSelectedSlug(slug);
          }}
          onPickInitiative={(id) => {
            const ini = (props.initiatives ?? []).find((i) => i.id === id);
            if (ini) {
              const m = allModules.find((x) => x.slug === ini.module);
              if (m?.bucket) setSelectedBucket(m.bucket);
              setSelectedSlug(ini.module);
            }
          }}
          onPickWave={(waveId) => {
            toast.info(`Wave ${waveId} — drawer histórico em ONDA 7+`);
            setWaveExpanded(true);
          }}
          onPickAdr={(adr) => {
            toast.info(`Abrindo ADR ${adr.number}…`);
            // ONDA 7+: navega pra /memoria?q=adr+{number} ou abre dialog dedicado
          }}
        />

        <HealthPanelV4
          open={healthOpen}
          onOpenChange={setHealthOpen}
          snapshot={props.healthSnapshot ?? null}
        />
      </div>
    </>
  );
}

// ── Skeletons defer fallback ──────────────────────────────────────────

function SidebarSkeleton() {
  return (
    <aside className="flex flex-col gap-2 border-r border-border bg-card p-3">
      <Skeleton className="h-3 w-24" />
      {[0, 1, 2, 3].map((i) => (
        <Skeleton key={i} className="h-9 w-full" />
      ))}
    </aside>
  );
}

function ListSkeleton() {
  return (
    <div className="flex flex-col gap-2 border-r border-border bg-background p-3">
      <Skeleton className="h-6 w-32" />
      <Skeleton className="h-7 w-full" />
      {[0, 1, 2, 3, 4, 5].map((i) => (
        <Skeleton key={i} className="h-14 w-full" />
      ))}
    </div>
  );
}

function ReaderSkeleton() {
  return (
    <div className="flex flex-col gap-3 bg-background p-4">
      <Skeleton className="h-6 w-48" />
      <Skeleton className="h-3 w-32" />
      <div className="grid grid-cols-4 gap-2">
        {[0, 1, 2, 3].map((i) => (
          <Skeleton key={i} className="h-14 w-full" />
        ))}
      </div>
      <Skeleton className="h-20 w-full" />
      <div className="space-y-2">
        {[0, 1, 2, 3, 4].map((i) => (
          <Skeleton key={i} className="h-6 w-full" />
        ))}
      </div>
    </div>
  );
}

GovernanceV4.layout = (page: React.ReactNode) => (
  <AppShellV2
    title="Governance v4"
    breadcrumbItems={[{ label: 'Admin' }, { label: 'Governance v4' }]}
  >
    {page}
  </AppShellV2>
);

export default GovernanceV4;

// Tipos consumíveis pelo backend W29-B:
//   import type { GovernanceV4PageProps } from '@/Pages/Admin/_components/governanceV4Types'
