// @memcofre tela=/oficina-auto/ordens-servico module=OficinaAuto
// V0.6 dashboard de OS — Martinho LIVE prod biz=164 (sub-vertical 4 ADR 0194).
// 2026-06-09 sweep ADR 0265 (avaliação [CC]): locação erradicada do front —
// types/pills/colunas/copy agora são de REPARO (mecanica|manutencao). Colunas
// de locação (Endereço/Diárias) removidas; "Caçamba"→"Veículo"; formatBRL
// null→"—" (antes vazava "R$ [redacted Tier 0]" literal na UI).
// Espelha layout Vehicles Index (Wave 5-B).
// RUNBOOK: memory/requisitos/OficinaAuto/RUNBOOK-index.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Wrench, Plus, Search, LayoutGrid, List, PanelLeft } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import ServiceOrderStatusBadge from './_components/ServiceOrderStatusBadge';
import ServiceOrderSheet from './_components/ServiceOrderSheet';
import ServiceOrderFila from './_components/ServiceOrderFila';
import { cn } from '@/Lib/utils';

// View in-page da listagem: tabela densa ou fila master-detail (handoff Cowork
// 2026-06-03). Reflete em `?view=` (querystring, canon do charter — sem sessionStorage).
type ListView = 'lista' | 'fila';

function initialView(): ListView {
  if (typeof window === 'undefined') return 'lista';
  return new URLSearchParams(window.location.search).get('view') === 'fila' ? 'fila' : 'lista';
}

// ──────── Types ────────
// ADR 0265: order_type ∈ {manutencao, mecanica} — 'locacao' não existe mais no enum.
type OrderType = 'manutencao' | 'mecanica' | null;

interface VehicleRel {
  id: number;
  plate: string;
  vehicle_type: string;
  vehicle_number?: string | null;
  capacity_m3?: number | string | null;
}

interface ContactRel {
  id: number;
  name: string;
}

interface ServiceOrder {
  id: number;
  number?: string | null;
  status: string;
  order_type?: OrderType;
  delivery_address?: string | null;
  expected_return_date?: string | null;
  expected_completion?: string | null;
  daily_rate?: number | string | null;
  entered_at?: string | null;
  started_at?: string | null;
  completed_at?: string | null;
  vehicle?: VehicleRel | null;
  contact?: ContactRel | null;
  // accessors Wave 5-A (vêm via $appends ou load explicit):
  is_overdue?: boolean;
  dias_locacao?: number | null;
  valor_receber?: number | string | null;
}

interface PaginatedOrders {
  data: ServiceOrder[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Filters {
  status: string;
  type: string;
  stage: string;
  q: string;
}

interface Kpis {
  locacoes_ativas: number;
  manutencao_ativas: number;
  concluidas_mes: number;
  atrasadas: number;
}

// Gap #3 estado-da-arte FSM screen — chips por stage estilo Linear (Wave 7-D).
interface Stage {
  key: string;
  name: string;
  color: string | null;
  count: number;
  is_terminal: boolean;
  process_key: string;
}

interface SchemaFlags {
  has_order_type: boolean;
  has_return_date: boolean;
  has_delivery_address: boolean;
  has_daily_rate: boolean;
  has_number: boolean;
  has_started_at: boolean;
  has_contact: boolean;
  has_current_stage: boolean;
  has_vehicle_number: boolean;
  has_capacity_m3: boolean;
}

interface Props {
  orders: PaginatedOrders;
  filters: Filters;
  // kpis vem via Inertia::defer (Wave 26 D6) — undefined no primeiro render.
  // Default value no destructuring evita crash até segundo request chegar.
  kpis?: Kpis;
  // stages vem via Inertia::defer (Gap #3 Wave 7-D) — undefined antes do segundo request.
  stages?: Stage[];
  schemaFlags: SchemaFlags;
}

const EMPTY_KPIS: Kpis = {
  locacoes_ativas: 0,
  manutencao_ativas: 0,
  concluidas_mes: 0,
  atrasadas: 0,
};

// ──────── Helpers ────────
const STATUS_PILLS: Array<{ key: string; label: string }> = [
  { key: 'all', label: 'Todas' },
  { key: 'manutencao_ativa', label: 'Em andamento' },
  { key: 'concluida_mes', label: 'Concluídas mês' },
  { key: 'atrasada', label: 'Atrasadas' },
];

const TYPE_PILLS: Array<{ key: string; label: string }> = [
  { key: 'all', label: 'Todos os tipos' },
  { key: 'mecanica', label: 'Mecânica' },
  { key: 'manutencao', label: 'Manutenção' },
];

function formatBRDate(value?: string | null): string {
  if (!value) return '—';
  // Aceita "YYYY-MM-DD" ou ISO datetime; evita problema de fuso interpretando como UTC.
  const datePart = value.length >= 10 ? value.slice(0, 10) : value;
  const [y, m, d] = datePart.split('-');
  if (!y || !m || !d) return value;
  return `${d}/${m}/${y}`;
}

function formatBRL(value: number | string | null | undefined): string {
  if (value === null || value === undefined || value === '') return '—';
  const num = typeof value === 'string' ? parseFloat(value) : value;
  if (Number.isNaN(num)) return '—';
  return num.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function isOverdueClient(o: ServiceOrder, flags: SchemaFlags): boolean {
  if (typeof o.is_overdue === 'boolean') return o.is_overdue;
  if (['concluida', 'cancelada'].includes(o.status)) return false;
  const dateField = flags.has_return_date ? o.expected_return_date : o.expected_completion;
  if (!dateField) return false;
  const target = new Date(dateField.length >= 10 ? dateField.slice(0, 10) : dateField);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return target < today;
}

// Gap #3 — paleta de cores Tailwind por stage.color (vinda do seeder OficinaAutoFsmSeeder).
const STAGE_CHIP_FALLBACK = {
  idle: 'border-gray-200 text-gray-700 hover:bg-gray-50',
  active: 'border-gray-400 bg-gray-100 text-gray-900',
};
const STAGE_CHIP_COLOR_MAP: Record<string, { idle: string; active: string }> = {
  gray:    { idle: 'border-gray-200 text-gray-700 hover:bg-gray-50',         active: 'border-gray-400 bg-gray-100 text-gray-900' },
  blue:    { idle: 'border-blue-200 text-blue-700 hover:bg-blue-50',         active: 'border-blue-400 bg-blue-100 text-blue-900' },
  cyan:    { idle: 'border-cyan-200 text-cyan-700 hover:bg-cyan-50',         active: 'border-cyan-400 bg-cyan-100 text-cyan-900' },
  amber:   { idle: 'border-amber-200 text-amber-700 hover:bg-amber-50',      active: 'border-amber-400 bg-amber-100 text-amber-900' },
  yellow:  { idle: 'border-yellow-200 text-yellow-700 hover:bg-yellow-50',   active: 'border-yellow-400 bg-yellow-100 text-yellow-900' },
  violet:  { idle: 'border-violet-200 text-violet-700 hover:bg-violet-50',   active: 'border-violet-400 bg-violet-100 text-violet-900' },
  indigo:  { idle: 'border-indigo-200 text-indigo-700 hover:bg-indigo-50',   active: 'border-indigo-400 bg-indigo-100 text-indigo-900' },
  emerald: { idle: 'border-emerald-200 text-emerald-700 hover:bg-emerald-50', active: 'border-emerald-400 bg-emerald-100 text-emerald-900' },
  green:   { idle: 'border-green-200 text-green-700 hover:bg-green-50',      active: 'border-green-400 bg-green-100 text-green-900' },
  red:     { idle: 'border-red-200 text-red-700 hover:bg-red-50',            active: 'border-red-400 bg-red-100 text-red-900' },
  rose:    { idle: 'border-rose-200 text-rose-700 hover:bg-rose-50',         active: 'border-rose-400 bg-rose-100 text-rose-900' },
  slate:   { idle: 'border-slate-200 text-slate-700 hover:bg-slate-50',      active: 'border-slate-400 bg-slate-100 text-slate-900' },
};

// ──────── Component ────────
export default function ServiceOrdersIndex({ orders, filters, kpis = EMPTY_KPIS, stages, schemaFlags }: Props) {
  const [q, setQ] = useState(filters.q ?? '');
  const [view, setView] = useState<ListView>(initialView);

  // Troca de view reflete em `?view=` sem roundtrip ao servidor (shareable; canon
  // do charter = querystring, não sessionStorage). History API só altera a search
  // string da mesma página, sem desincronizar o Inertia.
  const changeView = useCallback((next: ListView) => {
    setView(next);
    if (typeof window === 'undefined') return;
    const url = new URL(window.location.href);
    if (next === 'fila') url.searchParams.set('view', 'fila');
    else url.searchParams.delete('view');
    window.history.replaceState(window.history.state, '', url.toString());
  }, []);

  // Drawer ServiceOrder state — clicar row abre OS no drawer (US-OFICINA-OS-DRAWER).
  const [openOsId, setOpenOsId] = useState<number | null>(null);
  const handleSheetOpenChange = useCallback((open: boolean) => {
    if (!open) setOpenOsId(null);
  }, []);
  const handleOrderChanged = useCallback(() => {
    // Refresh listagem + contadores stages após transição FSM (status/stage/valor mudam)
    router.reload({ only: ['orders', 'kpis', 'stages'], preserveScroll: true, preserveState: true });
  }, []);

  // Live search com debounce 300ms
  useEffect(() => {
    if (q === (filters.q ?? '')) return;
    const id = setTimeout(() => {
      router.get(
        '/oficina-auto/ordens-servico',
        { ...currentFiltersExceptQ(filters), q },
        { preserveState: true, preserveScroll: true, replace: true },
      );
    }, 300);
    return () => clearTimeout(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [q]);

  function applyFilter(patch: Partial<Filters>) {
    const next = { ...filters, ...patch, q };
    Object.keys(next).forEach((k) => {
      const v = (next as Record<string, string>)[k];
      if (!v || v === 'all' || v === '') delete (next as Record<string, string>)[k];
    });
    router.get('/oficina-auto/ordens-servico', next, { preserveState: true, preserveScroll: true });
  }

  function handleSearchSubmit(e: FormEvent) {
    e.preventDefault();
    applyFilter({});
  }

  const subtitle = `${kpis.manutencao_ativas} em andamento · ${kpis.concluidas_mes} concluídas no mês · ${kpis.atrasadas} atrasadas`;

  return (
    <AppShellV2>
      <Head title="Ordens de Serviço · Oficina Auto" />
      <div className="px-4 py-6 max-w-7xl mx-auto space-y-6">
        <PageHeader
          title="Ordens de Serviço"
          subtitle={subtitle}
          icon={<Wrench className="size-5" />}
          actions={
            <div className="flex items-center gap-2">
              {/* Quadro (Kanban) das OS de mecânica — fluxo real do carro ([W] 2026-06-02) */}
              <Link href="/oficina-auto/ordens-servico/board">
                <Button variant="outline">
                  <LayoutGrid className="size-4 mr-1" />
                  Quadro
                </Button>
              </Link>
              <Link href="/oficina-auto/ordens-servico/create">
                <Button>
                  <Plus className="size-4 mr-1" />
                  Nova OS
                </Button>
              </Link>
            </div>
          }
        />

        {/* Aviso pré-Wave 5-A (some quando schema migrar) */}
        {!schemaFlags.has_order_type && (
          <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            Schema Wave 5-A pendente — distinção mecânica/manutenção só ativa após migração de
            <code className="mx-1 px-1 rounded bg-amber-100">order_type</code>.
          </div>
        )}

        {/* KPIs — reparo (ADR 0265: KPI de locação morreu com o domínio) */}
        <KpiGrid cols={3}>
          <KpiCard
            label="Em andamento"
            value={kpis.manutencao_ativas}
            tone="warning"
            icon="wrench"
          />
          <KpiCard
            label="Concluídas este mês"
            value={kpis.concluidas_mes}
            tone="success"
            icon="check-circle-2"
          />
          <KpiCard
            label="Atrasadas"
            value={kpis.atrasadas}
            tone="danger"
            icon="alert-triangle"
            description={kpis.atrasadas > 0 ? 'Cobrar imediatamente' : 'Tudo no prazo'}
          />
        </KpiGrid>

        {/* Toolbar: filter pills + tipo + search */}
        <div className="flex flex-wrap items-center gap-3">
          <div className="inline-flex rounded-md border border-border overflow-hidden">
            {STATUS_PILLS.map((pill) => {
              const active = (filters.status || 'all') === pill.key;
              return (
                <button
                  key={pill.key}
                  type="button"
                  onClick={() => applyFilter({ status: pill.key })}
                  className={cn(
                    'px-3 py-1.5 text-sm transition-colors',
                    active
                      ? 'bg-muted text-foreground font-medium'
                      : 'bg-background text-muted-foreground hover:bg-muted/50',
                  )}
                >
                  {pill.label}
                </button>
              );
            })}
          </div>

          {schemaFlags.has_order_type && (
            <div className="inline-flex rounded-md border border-border overflow-hidden">
              {TYPE_PILLS.map((pill) => {
                const active = (filters.type || 'all') === pill.key;
                return (
                  <button
                    key={pill.key}
                    type="button"
                    onClick={() => applyFilter({ type: pill.key })}
                    className={cn(
                      'px-3 py-1.5 text-sm transition-colors',
                      active
                        ? 'bg-muted text-foreground font-medium'
                        : 'bg-background text-muted-foreground hover:bg-muted/50',
                    )}
                  >
                    {pill.label}
                  </button>
                );
              })}
            </div>
          )}

          <form onSubmit={handleSearchSubmit} className="ml-auto flex gap-2">
            <Input
              placeholder="Buscar OS, cliente ou placa…"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              className="w-64"
            />
            <Button type="submit" variant="outline" size="sm">
              <Search className="size-4" />
            </Button>
          </form>

          {/* Toggle de view in-page: Lista (tabela densa) ↔ Fila (master-detail) */}
          <div className="inline-flex overflow-hidden rounded-md border border-border" role="group" aria-label="Modo de visualização">
            <button
              type="button"
              onClick={() => changeView('lista')}
              aria-pressed={view === 'lista'}
              className={cn(
                'inline-flex items-center gap-1.5 px-3 py-1.5 text-sm transition-colors',
                view === 'lista' ? 'bg-muted font-medium text-foreground' : 'bg-background text-muted-foreground hover:bg-muted/50',
              )}
            >
              <List className="size-4" />
              Lista
            </button>
            <button
              type="button"
              onClick={() => changeView('fila')}
              aria-pressed={view === 'fila'}
              className={cn(
                'inline-flex items-center gap-1.5 border-l border-border px-3 py-1.5 text-sm transition-colors',
                view === 'fila' ? 'bg-muted font-medium text-foreground' : 'bg-background text-muted-foreground hover:bg-muted/50',
              )}
            >
              <PanelLeft className="size-4" />
              Fila
            </button>
          </div>
        </div>

        {/* Gap #3 — chips de stage FSM estilo Linear (Wave 7-D). */}
        {schemaFlags.has_current_stage && stages && stages.length > 0 && (
          <div className="flex flex-wrap items-center gap-1.5">
            <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground mr-1">
              Estágio FSM:
            </span>
            <button
              type="button"
              onClick={() => applyFilter({ stage: 'all' })}
              className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs transition-colors',
                (filters.stage || 'all') === 'all'
                  ? 'border-foreground bg-foreground text-background'
                  : 'border-border text-muted-foreground hover:bg-muted/50',
              )}
            >
              Todos
            </button>
            {stages.map((stage) => {
              const active = filters.stage === stage.key;
              const colors = STAGE_CHIP_COLOR_MAP[stage.color ?? 'gray'] ?? STAGE_CHIP_FALLBACK;
              return (
                <button
                  key={`${stage.process_key}-${stage.key}`}
                  type="button"
                  onClick={() => applyFilter({ stage: stage.key })}
                  title={stage.is_terminal ? `${stage.name} (terminal)` : stage.name}
                  className={cn(
                    'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs transition-colors',
                    active ? colors.active : colors.idle,
                    stage.is_terminal && !active && 'opacity-70',
                  )}
                >
                  <span>{stage.name}</span>
                  <span
                    className={cn(
                      'inline-flex min-w-[18px] justify-center rounded-full px-1 text-[10px] font-semibold tabular-nums',
                      active ? 'bg-background/40' : 'bg-background',
                    )}
                  >
                    {stage.count}
                  </span>
                </button>
              );
            })}
          </div>
        )}

        {/* Tabela / Empty state */}
        {orders.data.length === 0 ? (
          <EmptyState
            icon={<Wrench className="size-12" />}
            title="Nenhuma OS encontrada"
            description={
              filters.status || filters.type || filters.stage || filters.q
                ? 'Ajuste os filtros ou crie uma nova ordem de serviço.'
                : 'Crie a primeira ordem de serviço para acompanhar o fluxo da oficina.'
            }
            action={
              <Link href="/oficina-auto/ordens-servico/create">
                <Button>
                  <Plus className="size-4 mr-1" />
                  Criar OS
                </Button>
              </Link>
            }
          />
        ) : view === 'fila' ? (
          <ServiceOrderFila
            orders={orders.data}
            isOverdue={(o) => isOverdueClient(o, schemaFlags)}
            formatBRDate={formatBRDate}
            formatBRL={formatBRL}
            hasReturnDate={schemaFlags.has_return_date}
            onOpenFull={setOpenOsId}
          />
        ) : (
          <div className="rounded-lg border bg-card overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="border-b bg-muted/50 text-xs uppercase text-muted-foreground">
                  <tr>
                    <th className="px-3 py-2 text-left">Nº OS</th>
                    <th className="px-3 py-2 text-left">Tipo</th>
                    <th className="px-3 py-2 text-left">Veículo</th>
                    <th className="px-3 py-2 text-left">Cliente</th>
                    <th className="px-3 py-2 text-left">Entrada</th>
                    <th className="px-3 py-2 text-left">Previsão</th>
                    <th className="px-3 py-2 text-right">Valor</th>
                    <th className="px-3 py-2 text-left">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {orders.data.map((o) => {
                    const overdue = isOverdueClient(o, schemaFlags);
                    const startedAt = o.started_at ?? o.entered_at;
                    const prazo = schemaFlags.has_return_date ? o.expected_return_date : o.expected_completion;

                    return (
                      <tr
                        key={o.id}
                        className={cn(
                          'border-b last:border-0 transition-colors cursor-pointer',
                          overdue ? 'bg-rose-50/50 hover:bg-rose-50' : 'hover:bg-muted/30',
                        )}
                        onClick={() => setOpenOsId(o.id)}
                      >
                        <td className="px-3 py-2.5 font-mono font-semibold">
                          {overdue && (
                            <span
                              className="inline-block w-2 h-2 rounded-full bg-rose-500 mr-1.5 align-middle"
                              title="Atrasada"
                            />
                          )}
                          {o.number ?? `#${o.id}`}
                        </td>
                        <td className="px-3 py-2.5">
                          {o.order_type === 'mecanica' ? (
                            <span className="inline-block px-2 py-0.5 text-xs rounded bg-violet-50 text-violet-700 border border-violet-200">
                              Mecânica
                            </span>
                          ) : o.order_type === 'manutencao' ? (
                            <span className="inline-block px-2 py-0.5 text-xs rounded bg-amber-50 text-amber-700 border border-amber-200">
                              Manutenção
                            </span>
                          ) : (
                            <span className="text-xs text-muted-foreground">—</span>
                          )}
                        </td>
                        <td className="px-3 py-2.5 font-mono text-xs">
                          {o.vehicle ? (
                            <>
                              <span className="font-semibold">
                                {o.vehicle.vehicle_number ?? o.vehicle.plate}
                              </span>
                              {o.vehicle.vehicle_number && (
                                <span className="text-muted-foreground ml-1">
                                  · {o.vehicle.plate}
                                </span>
                              )}
                              {o.vehicle.capacity_m3 && (
                                <div className="text-[11px] text-muted-foreground font-sans">
                                  {o.vehicle.capacity_m3}m³
                                </div>
                              )}
                            </>
                          ) : (
                            '—'
                          )}
                        </td>
                        <td className="px-3 py-2.5">
                          {o.contact?.name ?? <span className="text-muted-foreground">—</span>}
                        </td>
                        <td className="px-3 py-2.5 text-xs tabular-nums text-muted-foreground">
                          {formatBRDate(startedAt)}
                        </td>
                        <td
                          className={cn(
                            'px-3 py-2.5 text-xs tabular-nums',
                            overdue ? 'text-rose-700 font-medium' : 'text-muted-foreground',
                          )}
                        >
                          {formatBRDate(prazo)}
                        </td>
                        <td
                          className={cn(
                            'px-3 py-2.5 text-right tabular-nums',
                            overdue
                              ? 'text-rose-700 font-medium'
                              : Number(o.valor_receber ?? 0) > 0
                                ? 'text-amber-700'
                                : 'text-muted-foreground',
                          )}
                        >
                          {formatBRL(o.valor_receber)}
                        </td>
                        <td className="px-3 py-2.5">
                          <ServiceOrderStatusBadge
                            status={o.status}
                            orderType={o.order_type}
                            isOverdue={overdue}
                          />
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>

            {/* Paginação */}
            {orders.last_page > 1 && (
              <div className="flex items-center justify-between gap-2 px-3 py-3 border-t bg-muted/20 text-xs">
                <div className="text-muted-foreground">
                  {orders.from ?? 0}–{orders.to ?? 0} de {orders.total}
                </div>
                <div className="flex flex-wrap gap-1">
                  {orders.links.map((link, i) => (
                    <button
                      key={i}
                      type="button"
                      disabled={!link.url}
                      onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
                      className={cn(
                        'px-2.5 py-1 rounded border text-xs transition-colors',
                        link.active
                          ? 'bg-primary text-primary-foreground border-primary'
                          : link.url
                            ? 'bg-background border-border hover:bg-muted'
                            : 'bg-muted/30 text-muted-foreground border-transparent cursor-not-allowed',
                      )}
                      dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                  ))}
                </div>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Drawer ServiceOrder — abre ao clicar em qualquer linha da listagem */}
      <ServiceOrderSheet
        serviceOrderId={openOsId}
        open={openOsId !== null}
        onOpenChange={handleSheetOpenChange}
        onOrderChanged={handleOrderChanged}
      />
    </AppShellV2>
  );
}

// Helpers fora do componente (evitam re-criação a cada render).
function currentFiltersExceptQ(filters: Filters): Record<string, string> {
  const out: Record<string, string> = {};
  if (filters.status && filters.status !== 'all') out.status = filters.status;
  if (filters.type && filters.type !== 'all') out.type = filters.type;
  if (filters.stage && filters.stage !== 'all') out.stage = filters.stage;
  return out;
}
