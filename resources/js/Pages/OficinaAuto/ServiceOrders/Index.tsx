// @memcofre tela=/oficina-auto/ordens-servico module=OficinaAuto
// V0.5 dashboard de OS (locação + manutenção combinadas) — pré-reunião Martinho 13/maio.
// Espelha layout Vehicles Index (Wave 5-B) + mockup demo-martinho-2026-05-13/mockup.html.
// Schema flags do Backend permitem renderizar mesmo antes da Wave 5-A migrar.
// RUNBOOK: memory/requisitos/OficinaAuto/RUNBOOK-index.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Wrench, Plus, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import ServiceOrderStatusBadge from './_components/ServiceOrderStatusBadge';
import ServiceOrderSheet from './_components/ServiceOrderSheet';
import { cn } from '@/Lib/utils';

// ──────── Types ────────
type OrderType = 'locacao' | 'manutencao' | null;

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
  { key: 'locacao_ativa', label: 'Locações ativas' },
  { key: 'manutencao_ativa', label: 'Em manutenção' },
  { key: 'concluida_mes', label: 'Concluídas mês' },
  { key: 'atrasada', label: 'Atrasadas' },
];

const TYPE_PILLS: Array<{ key: string; label: string }> = [
  { key: 'all', label: 'Todos os tipos' },
  { key: 'locacao', label: 'Locação' },
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
  if (value === null || value === undefined || value === '') return 'R$ 0,00';
  const num = typeof value === 'string' ? parseFloat(value) : value;
  if (Number.isNaN(num)) return 'R$ 0,00';
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

  const subtitle = `${kpis.locacoes_ativas} locações ativas · ${kpis.manutencao_ativas} manutenções · ${kpis.atrasadas} atrasadas`;

  return (
    <AppShellV2>
      <Head title="Ordens de Serviço · Oficina Auto" />
      <div className="px-4 py-6 max-w-7xl mx-auto space-y-6">
        <PageHeader
          title="Ordens de Serviço"
          subtitle={subtitle}
          icon={<Wrench className="size-5" />}
          actions={
            <Link href="/oficina-auto/ordens-servico/create">
              <Button>
                <Plus className="size-4 mr-1" />
                Nova OS
              </Button>
            </Link>
          }
        />

        {/* Aviso pré-Wave 5-A (some quando schema migrar) */}
        {!schemaFlags.has_order_type && (
          <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            Schema Wave 5-A pendente — distinção locação/manutenção só ativa após migração de
            <code className="mx-1 px-1 rounded bg-amber-100">order_type</code>.
          </div>
        )}

        {/* KPIs */}
        <KpiGrid cols={4}>
          <KpiCard
            label="Locações ativas"
            value={kpis.locacoes_ativas}
            tone="info"
            icon="truck"
          />
          <KpiCard
            label="Em manutenção"
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
        ) : (
          <div className="rounded-lg border bg-card overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="border-b bg-muted/50 text-xs uppercase text-muted-foreground">
                  <tr>
                    <th className="px-3 py-2 text-left w-8">
                      <input type="checkbox" disabled aria-label="Selecionar todos" />
                    </th>
                    <th className="px-3 py-2 text-left">Nº OS</th>
                    <th className="px-3 py-2 text-left">Tipo</th>
                    <th className="px-3 py-2 text-left">Caçamba</th>
                    <th className="px-3 py-2 text-left">Cliente</th>
                    <th className="px-3 py-2 text-left">Endereço</th>
                    <th className="px-3 py-2 text-left">Início</th>
                    <th className="px-3 py-2 text-left">Prazo</th>
                    <th className="px-3 py-2 text-right">Diárias</th>
                    <th className="px-3 py-2 text-right">A receber</th>
                    <th className="px-3 py-2 text-left">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {orders.data.map((o) => {
                    const overdue = isOverdueClient(o, schemaFlags);
                    const startedAt = o.started_at ?? o.entered_at;
                    const prazo = schemaFlags.has_return_date ? o.expected_return_date : o.expected_completion;
                    const isLocacao = o.order_type === 'locacao';

                    return (
                      <tr
                        key={o.id}
                        className={cn(
                          'border-b last:border-0 transition-colors cursor-pointer',
                          overdue ? 'bg-rose-50/50 hover:bg-rose-50' : 'hover:bg-muted/30',
                        )}
                        onClick={() => setOpenOsId(o.id)}
                      >
                        <td className="px-3 py-2.5" onClick={(e) => e.stopPropagation()}>
                          <input type="checkbox" disabled aria-label={`Selecionar OS ${o.id}`} />
                        </td>
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
                          {o.order_type === 'locacao' ? (
                            <span className="inline-block px-2 py-0.5 text-xs rounded bg-blue-50 text-blue-700 border border-blue-200">
                              Locação
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
                        <td className="px-3 py-2.5 text-xs text-muted-foreground max-w-[200px]">
                          <div className="truncate" title={o.delivery_address ?? ''}>
                            {o.delivery_address || '—'}
                          </div>
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
                            overdue ? 'text-rose-700 font-medium' : '',
                          )}
                        >
                          {isLocacao && o.dias_locacao != null ? (
                            <>
                              {o.dias_locacao}d{overdue && ' ⚠'}
                            </>
                          ) : (
                            <span className="text-muted-foreground">—</span>
                          )}
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
