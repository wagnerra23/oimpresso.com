// @memcofre
//   tela: /admin/screen-review
//   module: Admin
//   stories: ONDA 7 Wave 30 W30-B (Screen Review PDCA tri-pane)
//   charter: resources/js/Pages/Admin/ScreenReview.charter.md
//   adrs: 0104, 0107, 0114, 0122, 0160
//
// W30-B — tri-pane copy do blueprint validado `kb/Index.v2.tsx`.
// Gate visual F1.5 SKIP (charter §mwart_pattern_reuse). Divergência semântica:
//   - sidebar = Módulos top-level (contagem PDCA por status)
//   - lista   = Telas do módulo selecionado + filter chips
//   - leitor  = ReviewReader (screenshots 1440+1280 + charter excerpt + Wagner actions)
//
// Backend: ScreenReviewController@index (Inertia::defer `modules` + `screens`).
// Quando defer ainda não retornou, página entra em estado MOCK skeleton.

import * as React from 'react';
import { Head, Link, router, Deferred } from '@inertiajs/react';
import { toast } from 'sonner';
import { LayoutDashboard, Search as SearchIcon } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';

import ScreenReviewSidebar, {
  type ModuleStatusCount,
} from './_components/ScreenReviewSidebar';
import ScreenList, {
  type ScreenRow,
  type ScreenListFilters,
} from './_components/ScreenList';
import ReviewReader from './_components/ReviewReader';
import type { ReviewStatus } from './_components/RoundBadge';

import '../../../css/kb.css';

const LS_PREFIX = 'oimpresso.screen-review.';
const LS_MODULE = `${LS_PREFIX}module`;
const LS_SCREEN = `${LS_PREFIX}screen`;
const LS_FILTERS = `${LS_PREFIX}filters`;

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

const DEFAULT_FILTERS: ScreenListFilters = {
  q: '',
  status: 'all',
  roundRange: 'all',
};

export interface ScreenReviewMeta {
  generated_at: string;
  total_telas: number;
  pending_count: number;
  approved_count: number;
  rejected_count: number;
  iterate_count: number;
  pending_over_7d: number;
}

export interface ScreenReviewPageProps {
  meta: ScreenReviewMeta;
  modules?: ModuleStatusCount[];
  screens?: ScreenRow[];
}

function ScreenReview(props: ScreenReviewPageProps) {
  const meta = props.meta;

  // ── persistência localStorage ─────────────────────────────────────
  const [selectedModule, setSelectedModuleState] = React.useState<string | 'all'>(
    () => loadJson<string | 'all'>(LS_MODULE, 'all'),
  );
  const [selectedPath, setSelectedPathState] = React.useState<string | null>(() =>
    loadJson<string | null>(LS_SCREEN, null),
  );
  const [filters, setFiltersState] = React.useState<ScreenListFilters>(() =>
    loadJson<ScreenListFilters>(LS_FILTERS, DEFAULT_FILTERS),
  );

  const setSelectedModule = (m: string | 'all') => {
    setSelectedModuleState(m);
    saveJson(LS_MODULE, m);
  };
  const setSelectedPath = (p: string | null) => {
    setSelectedPathState(p);
    saveJson(LS_SCREEN, p);
  };
  const setFilters = (f: ScreenListFilters) => {
    setFiltersState(f);
    saveJson(LS_FILTERS, f);
  };

  // ── lista filtrada por módulo ─────────────────────────────────────
  const allScreens: ScreenRow[] = props.screens ?? [];
  const moduleScreens: ScreenRow[] =
    selectedModule === 'all'
      ? allScreens
      : allScreens.filter((s) => s.module === selectedModule);

  const moduleLabel =
    selectedModule === 'all' ? 'Todas as telas' : selectedModule;

  const selectedScreen: ScreenRow | null =
    (selectedPath && moduleScreens.find((s) => s.path === selectedPath)) ||
    (selectedPath && allScreens.find((s) => s.path === selectedPath)) ||
    null;

  // ── form post status ──────────────────────────────────────────────
  const handleAction = (
    action: ReviewStatus,
    opts?: { createInitiative?: boolean; notes?: string },
  ) => {
    if (!selectedScreen) {
      toast.error('Selecione uma tela primeiro.');
      return;
    }
    const screenPathEncoded = encodeURIComponent(selectedScreen.path);
    router.post(
      `/admin/screen-review/${screenPathEncoded}/status`,
      {
        status: action,
        notes: opts?.notes ?? '',
        create_initiative: opts?.createInitiative ?? false,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(
            `Round salvo · ${selectedScreen.name} · status=${action}`,
          );
        },
        onError: (errors) => {
          const msg = Object.values(errors).flat().join(' · ');
          toast.error(`Falha ao salvar round: ${msg || 'erro desconhecido'}`);
        },
      },
    );
  };

  const handleResmoke = () => {
    if (!selectedScreen) return;
    toast.info(
      `Re-smoke ${selectedScreen.name} — em ONDA 7+ dispara workflow GHA visual-comparison`,
    );
  };

  return (
    <>
      <Head title="Screen Review" />

      <div className="flex h-[calc(100vh-3.5rem)] min-h-0 flex-col gap-3 px-4 py-3">
        <PageHeader
          icon="camera"
          title="Screen Review"
          description={`PDCA Wagner-Claude loop · ${meta.total_telas} telas · gerado ${new Date(meta.generated_at).toLocaleString('pt-BR')}`}
          action={
            <div className="flex flex-wrap items-center gap-1.5">
              <Button
                variant="ghost"
                size="sm"
                className="h-8 text-xs"
                onClick={() => router.reload()}
              >
                <SearchIcon size={13} className="mr-1.5" />
                Reload
              </Button>
              <Button asChild variant="outline" size="sm" className="h-8 text-xs">
                <Link href="/admin/screen-review/dashboard">
                  <LayoutDashboard size={13} className="mr-1.5" />
                  Dashboard
                </Link>
              </Button>
            </div>
          }
        />

        {/* Tri-pane */}
        <div
          className="kb-tri flex-1 min-h-0 overflow-hidden rounded-md border border-border"
          data-mobile-view="list"
        >
          <Deferred data="modules" fallback={<SidebarSkeleton />}>
            <ScreenReviewSidebar
              modules={props.modules ?? []}
              selectedModule={selectedModule}
              onSelect={(m) => {
                setSelectedModule(m);
                setSelectedPath(null);
              }}
            />
          </Deferred>

          <Deferred data="screens" fallback={<ListSkeleton />}>
            <ScreenList
              screens={moduleScreens}
              moduleLabel={moduleLabel}
              filters={filters}
              onChangeFilters={setFilters}
              selectedPath={selectedPath}
              onSelectScreen={setSelectedPath}
            />
          </Deferred>

          <Deferred data="screens" fallback={<ReaderSkeleton />}>
            <ReviewReader
              screen={selectedScreen}
              onAction={handleAction}
              onResmoke={handleResmoke}
            />
          </Deferred>
        </div>
      </div>
    </>
  );
}

// ── Skeletons ────────────────────────────────────────────────────────

function SidebarSkeleton() {
  return (
    <aside className="flex flex-col gap-2 border-r border-border bg-card p-3">
      <Skeleton className="h-3 w-24" />
      {[0, 1, 2, 3, 4, 5].map((i) => (
        <Skeleton key={i} className="h-10 w-full" />
      ))}
    </aside>
  );
}

function ListSkeleton() {
  return (
    <div className="flex flex-col gap-2 border-r border-border bg-background p-3">
      <Skeleton className="h-6 w-32" />
      <Skeleton className="h-7 w-full" />
      {[0, 1, 2, 3, 4, 5, 6].map((i) => (
        <Skeleton key={i} className="h-14 w-full" />
      ))}
    </div>
  );
}

function ReaderSkeleton() {
  return (
    <div className="flex flex-col gap-3 bg-background p-4">
      <Skeleton className="h-7 w-64" />
      <Skeleton className="h-3 w-32" />
      <div className="grid grid-cols-1 gap-2 lg:grid-cols-2">
        <Skeleton className="h-48 w-full" />
        <Skeleton className="h-48 w-full" />
      </div>
      <Skeleton className="h-24 w-full" />
      <Skeleton className="h-20 w-full" />
    </div>
  );
}

ScreenReview.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Screen Review" hideTopbar>{page}</AppShellV2>
);

export default ScreenReview;
