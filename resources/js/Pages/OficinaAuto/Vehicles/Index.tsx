// @memcofre tela=/oficina-auto/veiculos module=OficinaAuto
// Dashboard de Caçambas (US-OFICINA-001 evoluída pra demo Martinho 13/maio).
// Refs: ADR 0137 (OficinaAuto qualificada), ADR 0110 (Cockpit V2),
//       Mockup memory/requisitos/OficinaAuto/demo-martinho-2026-05-13/mockup.html
//       Pattern Pages/Sells/Index.tsx (KPI cards + filter pills + toggle Lista/Grade)
// RUNBOOK: memory/requisitos/OficinaAuto/RUNBOOK-index.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import {
  AlertTriangle,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Layers,
  LayoutList,
  Plus,
  Table2,
  Truck,
  Wrench,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import VehicleStatusBadge, { type VehicleStatus } from './_components/VehicleStatusBadge';

// ─── Types ───────────────────────────────────────────────────────────────────

interface CurrentRental {
  id: number;
  vehicle_id: number;
  contact_id: number | null;
  started_at: string | null;
  delivery_address: string | null;
  expected_return_date: string | null;
  // Accessors expostos pelo Agent A em ServiceOrder model:
  dias_locacao?: number | null;
  valor_receber?: number | null;
  is_overdue?: boolean;
  contact?: {
    id: number;
    name: string;
  } | null;
}

interface VehicleRow {
  id: number;
  plate: string;
  secondary_plate: string | null;
  vehicle_number: string | null;
  vehicle_type: string;
  capacity_m3: number | null;
  current_status: VehicleStatus;
  status_badge_color?: string | null; // accessor Agent A (informativo)
  current_rental_id: number | null;
  current_rental?: CurrentRental | null;
  // Snake → camelCase via Inertia: relations vêm em snake_case por convenção
  // Eloquent default; mantemos snake aqui pra evitar reescrita de toArray.
}

interface Kpis {
  disponivel: number;
  locada: number;
  manutencao: number;
  atrasada: number;
  total: number;
}

interface PaginatorMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

interface PaginatorLinks {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
}

interface Paginator<T> {
  data: T[];
  meta?: PaginatorMeta;
  links?: PaginatorLinks;
  // Inertia v3 LengthAwarePaginator inline shape
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
  from?: number | null;
  to?: number | null;
}

type StatusFilter = 'all' | 'disponivel' | 'locada' | 'manutencao' | 'atrasada';

interface Props {
  vehicles: Paginator<VehicleRow>;
  kpis: Kpis;
  filters: {
    q: string;
    status: StatusFilter;
  };
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const formatBRL = (value: number | null | undefined) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value ?? 0));

function formatDateBR(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: '2-digit',
  }).format(d);
}

function truncate(s: string | null | undefined, max = 42): string {
  if (!s) return '—';
  return s.length > max ? s.slice(0, max - 1) + '…' : s;
}

// Resolve meta de paginação aceitando ambos shapes (Inertia v3 inline ou {meta:})
function resolveMeta(p: Paginator<unknown>): PaginatorMeta {
  if (p.meta) return p.meta;
  return {
    current_page: p.current_page ?? 1,
    last_page: p.last_page ?? 1,
    per_page: p.per_page ?? 25,
    total: p.total ?? 0,
    from: p.from ?? null,
    to: p.to ?? null,
  };
}

// ─── Component ───────────────────────────────────────────────────────────────

export default function VehiclesIndex({ vehicles, kpis, filters }: Props) {
  const meta = resolveMeta(vehicles);

  // Search input com debounce 300ms (visit Inertia preservando state)
  const [searchInput, setSearchInput] = useState(filters.q ?? '');
  useEffect(() => {
    if (searchInput === (filters.q ?? '')) return;
    const t = setTimeout(() => {
      router.get(
        '/oficina-auto/veiculos',
        { q: searchInput || undefined, status: filters.status === 'all' ? undefined : filters.status },
        { preserveState: true, preserveScroll: true, replace: true },
      );
    }, 300);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchInput]);

  // viewMode (Lista | Grade Avançada) — persistido em localStorage.
  // Default Grade Avançada conforme briefing (mockup é Grade).
  const [viewMode, setViewMode] = useState<'lista' | 'grade'>(() => {
    if (typeof window === 'undefined') return 'grade';
    try {
      const v = window.localStorage.getItem('oimpresso.oficinaauto.veiculos.viewMode');
      if (v === 'lista' || v === 'grade') return v;
    } catch (_) { /* localStorage indisponível */ }
    return 'grade';
  });
  useEffect(() => {
    try {
      window.localStorage.setItem('oimpresso.oficinaauto.veiculos.viewMode', viewMode);
    } catch (_) { /* localStorage indisponível */ }
  }, [viewMode]);

  // Pills de status (deep-link via query string)
  const pills: Array<{ key: StatusFilter; label: string; count: number; danger?: boolean }> = [
    { key: 'all', label: 'Todas', count: kpis.total },
    { key: 'disponivel', label: 'Disponíveis', count: kpis.disponivel },
    { key: 'locada', label: 'Locadas', count: kpis.locada },
    { key: 'manutencao', label: 'Em manutenção', count: kpis.manutencao },
    { key: 'atrasada', label: 'Atrasadas', count: kpis.atrasada, danger: true },
  ];

  // Totais rodapé (rodapé tabela soma página atual — não totalizador global)
  const pageTotals = useMemo(() => {
    let dias = 0;
    let receber = 0;
    for (const v of vehicles.data) {
      const r = v.current_rental;
      if (r?.dias_locacao) dias += Number(r.dias_locacao) || 0;
      if (r?.valor_receber) receber += Number(r.valor_receber) || 0;
    }
    return { dias, receber, qtd: vehicles.data.length };
  }, [vehicles.data]);

  const subtitle = `${kpis.total} cadastradas · ${kpis.locada} locadas no momento · ${kpis.manutencao} em manutenção`;

  return (
    <>
      <Head title="Caçambas · Oficina Auto" />
      <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)]">
        {/* Header — Cockpit V2 canon (h1 + subtitle + KPIs + filter pills) */}
        <div className="border-b border-border bg-background">
          <div className="container mx-auto px-8 pt-6 pb-4 max-w-7xl">
            <div className="flex items-start gap-4">
              <div className="flex-1 min-w-0">
                <h1 className="text-2xl font-semibold tracking-tight text-foreground">Caçambas</h1>
                <p className="text-sm text-muted-foreground mt-1 leading-relaxed">{subtitle}</p>
              </div>
              <div className="flex-shrink-0 flex items-center gap-2">
                <Button
                  variant="outline"
                  disabled
                  title="P1 — em breve (importer Firebird Office Impresso)"
                >
                  Importar do Office Impresso
                </Button>
                <Button asChild>
                  <Link href="/oficina-auto/veiculos/create">
                    <Plus className="mr-1.5 h-4 w-4" />
                    Nova caçamba
                  </Link>
                </Button>
              </div>
            </div>

            {/* 4 KPI cards conforme mockup */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
              <KpiCard label="Disponíveis" value={kpis.disponivel} tone="emerald" icon={CheckCircle2} />
              <KpiCard label="Locadas" value={kpis.locada} tone="blue" icon={Truck} />
              <KpiCard label="Em manutenção" value={kpis.manutencao} tone="amber" icon={Wrench} />
              <KpiCard label="Atrasada" value={kpis.atrasada} tone="rose" icon={AlertTriangle} />
            </div>
          </div>
        </div>

        {/* Toolbar + Tabela */}
        <div className="container mx-auto px-8 py-6 max-w-7xl">
          <div className="flex items-center gap-3 mb-3 flex-wrap">
            {/* Filter pills (segmented) */}
            <nav
              className="inline-flex rounded-md border border-border overflow-hidden bg-background text-sm"
              aria-label="Filtro de status de caçamba"
            >
              {pills.map((p) => {
                const isActive = filters.status === p.key;
                const danger = p.danger && p.count > 0;
                return (
                  <Link
                    key={p.key}
                    href={`/oficina-auto/veiculos?${new URLSearchParams({
                      ...(p.key !== 'all' ? { status: p.key } : {}),
                      ...(searchInput ? { q: searchInput } : {}),
                    }).toString()}`}
                    preserveScroll
                    preserveState
                    className={
                      'px-3 py-1.5 text-xs font-medium transition-colors border-r border-border last:border-r-0 ' +
                      (isActive
                        ? danger
                          ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-200'
                          : 'bg-muted text-foreground'
                        : danger
                          ? 'text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/30'
                          : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground')
                    }
                    aria-current={isActive ? 'true' : undefined}
                  >
                    {p.label}
                    {p.count > 0 && (
                      <span className="ml-1.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-background/60 px-1 text-[10px] tabular-nums">
                        {p.count}
                      </span>
                    )}
                  </Link>
                );
              })}
            </nav>

            <Input
              type="search"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Buscar caçamba ou cliente…"
              className="h-8 w-64"
              aria-label="Buscar caçamba ou cliente"
            />

            {/* Toggle Lista | Grade Avançada (à direita) */}
            <div
              role="group"
              aria-label="Modo de visualização"
              className="ml-auto inline-flex rounded-md border border-border overflow-hidden bg-background text-xs"
            >
              <button
                type="button"
                onClick={() => setViewMode('lista')}
                aria-pressed={viewMode === 'lista'}
                className={
                  'inline-flex items-center gap-1.5 px-3 py-1.5 font-medium transition-colors ' +
                  (viewMode === 'lista'
                    ? 'bg-muted text-foreground'
                    : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground')
                }
              >
                <LayoutList size={13} />
                Lista
              </button>
              <button
                type="button"
                onClick={() => setViewMode('grade')}
                aria-pressed={viewMode === 'grade'}
                className={
                  'inline-flex items-center gap-1.5 px-3 py-1.5 font-medium transition-colors border-l border-border ' +
                  (viewMode === 'grade'
                    ? 'bg-muted text-foreground'
                    : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground')
                }
              >
                <Table2 size={13} />
                Grade Avançada
              </button>
            </div>
          </div>

          {/* Tabela */}
          <div className="rounded-lg border border-border bg-background overflow-hidden">
            {vehicles.data.length === 0 ? (
              <EmptyVehicles status={filters.status} />
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-muted/50 text-xs uppercase tracking-wider text-muted-foreground">
                    <tr className="border-b border-border">
                      <th className="px-3 py-2.5 text-left w-8">
                        {/* P2: multiseleção; placeholder visual */}
                        <input type="checkbox" disabled aria-label="Selecionar todas (P2)" />
                      </th>
                      <th className="px-3 py-2.5 text-left">Caçamba</th>
                      <th className="px-3 py-2.5 text-left">Capacidade</th>
                      <th className="px-3 py-2.5 text-left">Cliente atual</th>
                      <th className="px-3 py-2.5 text-left">Endereço</th>
                      <th className="px-3 py-2.5 text-left">Início</th>
                      <th className="px-3 py-2.5 text-right">Diárias</th>
                      <th className="px-3 py-2.5 text-right">A receber</th>
                      <th className="px-3 py-2.5 text-left">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {vehicles.data.map((v) => {
                      const rental = v.current_rental;
                      const isOverdue = Boolean(rental?.is_overdue);
                      const rowBg = isOverdue
                        ? 'bg-rose-50/60 hover:bg-rose-50 dark:bg-rose-950/30 dark:hover:bg-rose-950/40'
                        : v.current_status === 'manutencao'
                          ? 'bg-amber-50/30 hover:bg-amber-50/50 dark:bg-amber-950/20 dark:hover:bg-amber-950/30'
                          : 'hover:bg-muted/40';
                      return (
                        <tr
                          key={v.id}
                          className={'border-b border-border last:border-0 cursor-pointer transition-colors ' + rowBg}
                          onClick={() => router.visit(`/oficina-auto/veiculos/${v.id}`)}
                        >
                          <td className="px-3 py-2.5" onClick={(e) => e.stopPropagation()}>
                            <input type="checkbox" disabled aria-label={`Selecionar ${v.vehicle_number ?? v.plate}`} />
                          </td>
                          <td className="px-3 py-2.5 font-medium text-foreground whitespace-nowrap">
                            {isOverdue && (
                              <span
                                className="inline-block w-2 h-2 rounded-full bg-rose-500 mr-1.5 align-middle"
                                title="Atrasada"
                                aria-label="Caçamba com locação atrasada"
                              />
                            )}
                            {v.vehicle_number ?? v.plate}
                            {v.vehicle_number && v.plate && (
                              <span className="ml-1 text-xs text-muted-foreground/70 font-normal">
                                ({v.plate})
                              </span>
                            )}
                          </td>
                          <td className="px-3 py-2.5 text-xs text-muted-foreground">
                            {v.capacity_m3 != null ? `${Number(v.capacity_m3)}m³` : '—'}
                          </td>
                          <td className="px-3 py-2.5">
                            {rental?.contact?.name ?? (
                              v.current_status === 'manutencao' ? (
                                <span className="italic text-muted-foreground">manutenção</span>
                              ) : (
                                <span className="text-muted-foreground">—</span>
                              )
                            )}
                          </td>
                          <td className="px-3 py-2.5 text-xs text-muted-foreground">
                            {rental?.delivery_address
                              ? truncate(rental.delivery_address, 42)
                              : v.current_status === 'manutencao'
                                ? <span className="text-muted-foreground/70">— oficina</span>
                                : v.current_status === 'disponivel'
                                  ? <span className="text-muted-foreground/70">— pátio matriz</span>
                                  : '—'}
                          </td>
                          <td className="px-3 py-2.5 text-xs tabular-nums text-muted-foreground whitespace-nowrap">
                            {formatDateBR(rental?.started_at)}
                          </td>
                          <td className={
                            'px-3 py-2.5 text-right tabular-nums whitespace-nowrap ' +
                            (isOverdue ? 'text-rose-700 font-medium' : 'text-foreground')
                          }>
                            {rental?.dias_locacao != null ? (
                              <>
                                {rental.dias_locacao}
                                {isOverdue && <span className="ml-0.5">⚠</span>}
                              </>
                            ) : (
                              <span className="text-muted-foreground/70">—</span>
                            )}
                          </td>
                          <td className={
                            'px-3 py-2.5 text-right tabular-nums whitespace-nowrap ' +
                            (isOverdue
                              ? 'text-rose-700 font-medium'
                              : Number(rental?.valor_receber ?? 0) > 0
                                ? 'text-amber-700 dark:text-amber-400'
                                : 'text-emerald-700 dark:text-emerald-400')
                          }>
                            {formatBRL(rental?.valor_receber ?? 0)}
                          </td>
                          <td className="px-3 py-2.5">
                            <VehicleStatusBadge
                              status={v.current_status}
                              isOverdue={isOverdue}
                            />
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                  {/* Totalizador rodapé (página atual — pattern Sells) */}
                  <tfoot className="bg-muted/40 border-t border-border text-xs">
                    <tr>
                      <td colSpan={6} className="px-3 py-2.5 text-muted-foreground">
                        <strong className="text-foreground">QTD:</strong> {pageTotals.qtd} caçambas
                        {meta.total > pageTotals.qtd && (
                          <span className="text-muted-foreground/70"> (de {meta.total})</span>
                        )}
                      </td>
                      <td className="px-3 py-2.5 text-right tabular-nums font-medium text-foreground">
                        {pageTotals.dias} {pageTotals.dias === 1 ? 'diária' : 'diárias'}
                      </td>
                      <td className="px-3 py-2.5 text-right tabular-nums font-bold text-amber-700 dark:text-amber-400">
                        {formatBRL(pageTotals.receber)}
                      </td>
                      <td className="px-3 py-2.5 text-xs text-muted-foreground">
                        filtro: {pillLabel(filters.status)}
                      </td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            )}
          </div>

          {/* Paginação */}
          {meta.total > 0 && meta.last_page > 1 && (
            <Pagination meta={meta} />
          )}
        </div>
      </div>
    </>
  );
}

VehiclesIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

// ─── Subcomponents ───────────────────────────────────────────────────────────

function pillLabel(status: StatusFilter): string {
  switch (status) {
    case 'disponivel': return 'disponíveis';
    case 'locada': return 'locadas';
    case 'manutencao': return 'em manutenção';
    case 'atrasada': return 'atrasadas';
    default: return 'todas';
  }
}

function KpiCard({
  label,
  value,
  tone,
  icon: IconComp,
}: {
  label: string;
  value: number;
  tone: 'emerald' | 'blue' | 'amber' | 'rose';
  icon: typeof Layers;
}) {
  // Conforme mockup: Disponíveis/Locadas têm bg-white; Em manutenção/Atrasada
  // têm bg colorido leve (chama atenção). Mantemos esse contraste semantic.
  const styles = {
    emerald: {
      card: 'border-border bg-background',
      label: 'text-muted-foreground',
      value: 'text-emerald-700 dark:text-emerald-400',
      icon: 'text-emerald-300 dark:text-emerald-600',
    },
    blue: {
      card: 'border-border bg-background',
      label: 'text-muted-foreground',
      value: 'text-blue-700 dark:text-blue-400',
      icon: 'text-blue-300 dark:text-blue-600',
    },
    amber: {
      card: 'border-amber-200 bg-amber-50/70 dark:border-amber-900/40 dark:bg-amber-950/30',
      label: 'text-amber-700 dark:text-amber-400',
      value: 'text-amber-700 dark:text-amber-300',
      icon: 'text-amber-400 dark:text-amber-600',
    },
    rose: {
      card: 'border-rose-200 bg-rose-50/70 dark:border-rose-900/40 dark:bg-rose-950/30',
      label: 'text-rose-700 dark:text-rose-400',
      value: 'text-rose-700 dark:text-rose-300',
      icon: 'text-rose-400 dark:text-rose-600',
    },
  }[tone];

  return (
    <div className={'rounded-lg border p-4 shadow-sm ' + styles.card}>
      <div className={'text-[11px] font-semibold uppercase tracking-wider ' + styles.label}>{label}</div>
      <div className="flex items-end justify-between mt-2">
        <div className={'text-3xl font-bold tabular-nums ' + styles.value}>{value}</div>
        <IconComp size={26} className={styles.icon} strokeWidth={1.5} />
      </div>
    </div>
  );
}

function EmptyVehicles({ status }: { status: StatusFilter }) {
  if (status !== 'all') {
    return (
      <div className="text-center py-16 px-6">
        <Truck className="mx-auto h-10 w-10 text-muted-foreground/40" />
        <h3 className="mt-3 text-sm font-semibold text-foreground">
          Nenhuma caçamba {pillLabel(status)}
        </h3>
        <p className="mt-1 text-xs text-muted-foreground">
          Tente outro filtro acima ou veja todas as caçambas.
        </p>
        <div className="mt-4">
          <Button asChild variant="outline" size="sm">
            <Link href="/oficina-auto/veiculos">Ver todas</Link>
          </Button>
        </div>
      </div>
    );
  }
  return (
    <div className="text-center py-16 px-6">
      <Truck className="mx-auto h-10 w-10 text-muted-foreground/40" />
      <h3 className="mt-3 text-sm font-semibold text-foreground">Nenhuma caçamba cadastrada</h3>
      <p className="mt-1 text-xs text-muted-foreground">
        Clique em <strong className="text-foreground">+ Nova caçamba</strong> pra começar
        — ou aguarde o importer Office Impresso (em breve).
      </p>
      <div className="mt-4">
        <Button asChild>
          <Link href="/oficina-auto/veiculos/create">
            <Plus className="mr-1.5 h-4 w-4" />
            Nova caçamba
          </Link>
        </Button>
      </div>
    </div>
  );
}

function Pagination({ meta }: { meta: PaginatorMeta }) {
  const { current_page, last_page, total, from, to } = meta;
  const canPrev = current_page > 1;
  const canNext = current_page < last_page;
  const goTo = (page: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('page', String(page));
    router.visit(url.pathname + url.search, { preserveScroll: true, preserveState: true });
  };
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 mt-3 px-1">
      <div className="text-xs text-muted-foreground">
        {total === 0
          ? 'Nenhuma caçamba'
          : `Mostrando ${from ?? 0}–${to ?? 0} de ${total.toLocaleString('pt-BR')}`}
      </div>
      <div className="flex items-center gap-1">
        <PageBtn onClick={() => goTo(1)} disabled={!canPrev} aria-label="Primeira página">
          <ChevronsLeft size={14} />
        </PageBtn>
        <PageBtn onClick={() => goTo(current_page - 1)} disabled={!canPrev} aria-label="Página anterior">
          <ChevronLeft size={14} />
        </PageBtn>
        <span className="px-2 text-xs tabular-nums text-foreground">
          {current_page} <span className="text-muted-foreground">/ {last_page}</span>
        </span>
        <PageBtn onClick={() => goTo(current_page + 1)} disabled={!canNext} aria-label="Próxima página">
          <ChevronRight size={14} />
        </PageBtn>
        <PageBtn onClick={() => goTo(last_page)} disabled={!canNext} aria-label="Última página">
          <ChevronsRight size={14} />
        </PageBtn>
      </div>
    </div>
  );
}

function PageBtn({
  children,
  onClick,
  disabled,
  ...rest
}: React.ButtonHTMLAttributes<HTMLButtonElement>) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      {...rest}
      className="inline-flex h-7 w-7 items-center justify-center rounded border border-border bg-background text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-background"
    >
      {children}
    </button>
  );
}
