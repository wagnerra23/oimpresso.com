// @memcofre tela=/oficina-auto/ordens-servico module=OficinaAuto
// V0.6 dashboard de OS — Martinho LIVE prod biz=164 (sub-vertical 4 ADR 0194).
// 2026-06-09 sweep ADR 0265: locação erradicada (types/copy de REPARO).
// RUNBOOK: memory/requisitos/OficinaAuto/RUNBOOK-index.md
//
// Paridade TOTAL com o protótipo Cowork [CC] 2026-06-11 (pedido [W] "resultado
// esperado" = nível do Board): a Lista deixou de ser uma tabela densa simples e
// virou o canon Cowork da tela Oficina Auto —
//   1. 6 KPIs (Recepção · Em diagnóstico · Aguardando peças · Em execução ·
//      Urgentes · Valor em curso) no BoardKpiCard, CLICÁVEIS como filtro (etapa
//      via ?stage=, urgentes via ?status=atrasada; "Valor em curso" só-leitura);
//   2. abas de box/elevador (?box=) com contador, paridade .prod-equip-filters;
//   3. toolbar única (busca + limpar + contador + tipo + toggle Quadro·Lista·Fila);
//   4. TABELA RICA: OS · PLACA Mercosul · VEÍCULO+km · CLIENTE · ETAPA (dot+nome) ·
//      BOX · MECÂNICO · PRAZO · VALOR (items_total real). Sem dado fake — etapa/box/
//      mecânico/km vêm do backend (shapeListRow) com lastro real; "—" quando ausente.
// Backend: ServiceOrderController::index() enriquecido (buildStageMap/shapeListRow/
// buildListKpis/buildListBoxOptions). Fila segue na 2ª view (ServiceOrderFila).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Plus, Search, LayoutGrid, List as ListIcon, ListOrdered, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import MercosulPlate from '@/Components/shared/MercosulPlate';
import BoardKpiCard from './_components/board/BoardKpiCard';
import { toneForColor } from './_components/board/boardTone';
import ServiceOrderSheet from './_components/ServiceOrderSheet';
import ServiceOrderFila from './_components/ServiceOrderFila';
import { cn } from '@/Lib/utils';

// View in-page da listagem: tabela rica ou fila master-detail. Reflete em `?view=`.
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

// Etapa FSM atual da OS (resolvida no backend via buildStageMap).
interface StageRel {
  key: string;
  name: string;
  color: string | null;
}

interface ServiceOrder {
  id: number;
  number?: string | null;
  status: string;
  order_type?: OrderType;
  expected_completion?: string | null;
  entered_at?: string | null;
  started_at?: string | null;
  completed_at?: string | null;
  notes?: string | null;
  vehicle?: VehicleRel | null;
  contact?: ContactRel | null;
  is_overdue?: boolean;
  // Soma REAL dos itens da OS (withSum no index). NULL quando sem item lançado.
  items_total?: number | string | null;
  // Campos ricos (paridade Board): etapa, box, mecânico, km de entrada.
  stage?: StageRel | null;
  box?: string | null;
  mechanic_name?: string | null;
  km?: number | null;
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
  box: string;
  q: string;
}

// 6 KPIs do protótipo Cowork (paridade Board).
interface Kpis {
  recepcao: number;
  em_diagnostico: number;
  aguardando_pecas: number;
  em_execucao: number;
  atrasadas: number;
  valor_em_curso: number;
  boxes_total: number;
}

interface BoxOption {
  label: string;
  count: number;
}

interface SchemaFlags {
  has_order_type: boolean;
  has_number: boolean;
  has_started_at: boolean;
  has_contact: boolean;
  has_current_stage: boolean;
  has_vehicle_number: boolean;
  has_capacity_m3: boolean;
  has_resources: boolean;
}

interface Props {
  orders: PaginatedOrders;
  filters: Filters;
  // kpis/boxes vêm via Inertia::defer — undefined no primeiro render (default evita crash).
  kpis?: Kpis;
  boxes?: BoxOption[];
  schemaFlags: SchemaFlags;
}

const EMPTY_KPIS: Kpis = {
  recepcao: 0,
  em_diagnostico: 0,
  aguardando_pecas: 0,
  em_execucao: 0,
  atrasadas: 0,
  valor_em_curso: 0,
  boxes_total: 0,
};

// ──────── KPIs (6 cards do protótipo) ────────
// Cada KPI clicável aplica um filtro: os 4 de etapa via ?stage=<key>; "Urgentes"
// via ?status=atrasada; "Valor em curso" é só-leitura (faturamento previsto).
type KpiFilter = { stage: string } | { status: string } | null;

interface KpiDef {
  id: keyof Kpis;
  label: string;
  sub: string;
  tone: 'default' | 'blue' | 'violet' | 'indigo' | 'rose' | 'emerald';
  filter: KpiFilter;
  money?: boolean;
}

const KPI_CARDS: KpiDef[] = [
  { id: 'recepcao',         label: 'Recepção',         sub: 'aguardando triagem',     tone: 'default', filter: { stage: 'recepcao' } },
  { id: 'em_diagnostico',   label: 'Em diagnóstico',   sub: 'em análise técnica',     tone: 'blue',    filter: { stage: 'em_diagnostico' } },
  { id: 'aguardando_pecas', label: 'Aguardando peças', sub: 'peça a caminho',         tone: 'violet',  filter: { stage: 'aguardando_pecas' } },
  { id: 'em_execucao',      label: 'Em execução',      sub: 'boxes ocupados agora',   tone: 'indigo',  filter: { stage: 'em_execucao' } },
  { id: 'atrasadas',        label: 'Urgentes',         sub: 'prazo crítico',          tone: 'rose',    filter: { status: 'atrasada' } },
  { id: 'valor_em_curso',   label: 'Valor em curso',   sub: 'faturamento previsto',   tone: 'emerald', filter: null, money: true },
];

const STAGE_KEYS = ['recepcao', 'em_diagnostico', 'aguardando_pecas', 'em_execucao'] as const;

const TYPE_PILLS: Array<{ key: string; label: string }> = [
  { key: 'all', label: 'Todos os tipos' },
  { key: 'mecanica', label: 'Mecânica' },
  { key: 'manutencao', label: 'Manutenção' },
];

function formatBRDate(value?: string | null): string {
  if (!value) return '—';
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

function formatBRLshort(value: number): string {
  return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });
}

// Atraso de REPARO (ADR 0265): só expected_completion.
function isOverdueClient(o: ServiceOrder): boolean {
  if (typeof o.is_overdue === 'boolean') return o.is_overdue;
  if (['concluida', 'cancelada'].includes(o.status)) return false;
  const dateField = o.expected_completion;
  if (!dateField) return false;
  const target = new Date(dateField.length >= 10 ? dateField.slice(0, 10) : dateField);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return target < today;
}

function capitalize(s?: string | null): string {
  if (!s) return '';
  return s.charAt(0).toUpperCase() + s.slice(1).toLowerCase();
}

// ──────── Component ────────
export default function ServiceOrdersIndex({ orders, filters, kpis = EMPTY_KPIS, boxes, schemaFlags }: Props) {
  const [q, setQ] = useState(filters.q ?? '');
  const [view, setView] = useState<ListView>(initialView);

  const changeView = useCallback((next: ListView) => {
    setView(next);
    if (typeof window === 'undefined') return;
    const url = new URL(window.location.href);
    if (next === 'fila') url.searchParams.set('view', 'fila');
    else url.searchParams.delete('view');
    window.history.replaceState(window.history.state, '', url.toString());
  }, []);

  // Drawer ServiceOrder state — clicar row abre OS no drawer.
  const [openOsId, setOpenOsId] = useState<number | null>(null);
  const handleSheetOpenChange = useCallback((open: boolean) => {
    if (!open) setOpenOsId(null);
  }, []);
  const handleOrderChanged = useCallback(() => {
    router.reload({ only: ['orders', 'kpis', 'boxes'] });
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

  const applyFilter = useCallback((patch: Partial<Filters>) => {
    const next = { ...filters, ...patch, q };
    Object.keys(next).forEach((k) => {
      const v = (next as Record<string, string>)[k];
      if (!v || v === 'all' || v === '') delete (next as Record<string, string>)[k];
    });
    router.get('/oficina-auto/ordens-servico', next, { preserveState: true, preserveScroll: true });
  }, [filters, q]);

  function handleSearchSubmit(e: FormEvent) {
    e.preventDefault();
    applyFilter({});
  }

  const subtitle = 'Recepção, diagnóstico, peças, execução e entrega de veículos';

  // KPI ativo (deriva da querystring): etapa ∈ stage keys → esse id; status=atrasada → 'atrasadas'.
  const activeKpiId: keyof Kpis | null =
    filters.stage && (STAGE_KEYS as readonly string[]).includes(filters.stage)
      ? (filters.stage as keyof Kpis)
      : filters.status === 'atrasada'
        ? 'atrasadas'
        : null;

  const kpiClick = useCallback((kpi: KpiDef) => {
    if (!kpi.filter) return;
    if ('stage' in kpi.filter) {
      const isActive = filters.stage === kpi.filter.stage;
      applyFilter({ stage: isActive ? 'all' : kpi.filter.stage, status: 'all' });
    } else {
      const isActive = filters.status === kpi.filter.status;
      applyFilter({ status: isActive ? 'all' : kpi.filter.status, stage: 'all' });
    }
  }, [filters.stage, filters.status, applyFilter]);

  const activeKpiLabel = activeKpiId
    ? KPI_CARDS.find((k) => k.id === activeKpiId)?.label ?? null
    : null;

  return (
    <AppShellV2>
      <Head title="Ordens de Serviço · Oficina Auto" />
      <div className="px-4 py-6 max-w-[1400px] mx-auto space-y-5">
        {/* Header canon: título da tela + subtítulo descritivo (protótipo Cowork). */}
        <PageHeader
          title="Oficina Auto"
          description={subtitle}
          action={
            <Link href="/oficina-auto/ordens-servico/create">
              <Button>
                <Plus className="size-4 mr-1" />
                Nova OS
              </Button>
            </Link>
          }
        />

        {!schemaFlags.has_order_type && (
          <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            Schema Wave 5-A pendente — distinção mecânica/manutenção só ativa após migração de
            <code className="mx-1 px-1 rounded bg-amber-100">order_type</code>.
          </div>
        )}

        {/* 6 KPIs canon do Board (BoardKpiCard) — clicáveis como filtro. */}
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
          {KPI_CARDS.map((kpi) => {
            const active = activeKpiId === kpi.id;
            const value = kpi.money ? formatBRLshort(kpis.valor_em_curso) : String(kpis[kpi.id] ?? 0);
            return (
              <BoardKpiCard
                key={kpi.id}
                label={kpi.label}
                value={value}
                sub={kpi.sub}
                tone={kpi.tone}
                active={active}
                dimmed={activeKpiId !== null && !active && kpi.filter !== null}
                onClick={kpi.filter ? () => kpiClick(kpi) : undefined}
              />
            );
          })}
        </div>

        {/* Abas de box/elevador (paridade .prod-equip-filters) — filtro ?box=. */}
        {schemaFlags.has_resources && boxes && boxes.length > 0 && (
          <div className="flex flex-wrap items-center gap-1.5" role="group" aria-label="Filtrar por box">
            <button
              type="button"
              onClick={() => applyFilter({ box: 'all' })}
              aria-pressed={!filters.box || filters.box === 'all'}
              className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium whitespace-nowrap transition-colors',
                !filters.box || filters.box === 'all'
                  ? 'bg-primary text-white border-primary'
                  : 'bg-white text-foreground border-border hover:bg-muted',
              )}
            >
              Todos os boxes
              <span className={cn('tabular-nums rounded px-1', !filters.box || filters.box === 'all' ? 'bg-white/20' : 'bg-muted')}>
                {orders.total}
              </span>
            </button>
            {boxes.map((b) => {
              const active = filters.box === b.label;
              return (
                <button
                  key={b.label}
                  type="button"
                  onClick={() => applyFilter({ box: active ? 'all' : b.label })}
                  aria-pressed={active}
                  className={cn(
                    'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium whitespace-nowrap transition-colors',
                    active ? 'bg-primary text-white border-primary' : 'bg-white text-foreground border-border hover:bg-muted',
                  )}
                >
                  {b.label}
                  <span className={cn('tabular-nums rounded px-1', active ? 'bg-white/20' : 'bg-muted')}>{b.count}</span>
                </button>
              );
            })}
          </div>
        )}

        {/* Toolbar única: busca + limpar + chip KPI + contador · tipo · toggle de views. */}
        <div className="flex flex-wrap items-center gap-3">
          <form onSubmit={handleSearchSubmit} className="flex flex-1 min-w-[240px] items-center gap-2">
            <Search size={14} className="text-muted-foreground flex-shrink-0" />
            <div className="relative flex-1 max-w-md">
              <Input
                type="search"
                placeholder="placa · veículo · cliente · #OS"
                value={q}
                onChange={(e) => setQ(e.target.value)}
                className="h-8 border-border pr-7"
                aria-label="Buscar OS, placa ou cliente"
              />
              {q !== '' && (
                <button
                  type="button"
                  className="absolute right-1.5 top-1/2 -translate-y-1/2 p-0.5 rounded text-muted-foreground hover:text-foreground hover:bg-muted"
                  onClick={() => setQ('')}
                  aria-label="Limpar busca"
                >
                  <X size={12} />
                </button>
              )}
            </div>

            {activeKpiLabel && (
              <button
                type="button"
                className="inline-flex items-center gap-1 text-[11px] font-medium text-primary bg-primary/10 border border-primary/30 rounded-full px-2 py-0.5 hover:bg-primary/15 whitespace-nowrap"
                onClick={() => applyFilter({ stage: 'all', status: 'all' })}
              >
                <X size={10} /> limpar filtro: {activeKpiLabel}
              </button>
            )}

            <span className="ml-auto pl-2 text-sm text-muted-foreground whitespace-nowrap" aria-live="polite">
              <span className="font-medium text-foreground tabular-nums">{orders.total} OS</span>
              {kpis.atrasadas > 0 && (
                <>
                  <span className="mx-1.5">·</span>
                  <span className="font-medium text-destructive tabular-nums">
                    {kpis.atrasadas} atrasada{kpis.atrasadas === 1 ? '' : 's'}
                  </span>
                </>
              )}
            </span>
          </form>

          {schemaFlags.has_order_type && (
            <div className="inline-flex flex-shrink-0 rounded border border-border bg-white overflow-hidden" role="group" aria-label="Filtrar por tipo">
              {TYPE_PILLS.map((pill, i) => {
                const active = (filters.type || 'all') === pill.key;
                return (
                  <button
                    key={pill.key}
                    type="button"
                    onClick={() => applyFilter({ type: pill.key })}
                    aria-pressed={active}
                    className={cn(
                      'px-2.5 py-1 text-xs font-medium transition-colors',
                      i > 0 && 'border-l border-border',
                      active ? 'bg-muted text-foreground' : 'text-muted-foreground hover:bg-muted/50',
                    )}
                  >
                    {pill.label}
                  </button>
                );
              })}
            </div>
          )}

          {/* Toggle de views (simetria com o Board): Quadro navega pro kanban; Lista/Fila in-page. */}
          <div className="inline-flex flex-shrink-0 rounded border border-border bg-white overflow-hidden" role="group" aria-label="Visualização">
            <Link
              href="/oficina-auto/ordens-servico/board"
              className="px-2.5 py-1 text-xs font-medium inline-flex items-center gap-1 text-foreground hover:bg-muted transition-colors"
            >
              <LayoutGrid size={12} /> Quadro
            </Link>
            <button
              type="button"
              onClick={() => changeView('lista')}
              aria-pressed={view === 'lista'}
              className={cn(
                'px-2.5 py-1 text-xs font-medium inline-flex items-center gap-1 transition-colors border-l border-border',
                view === 'lista' ? 'bg-primary text-white' : 'text-foreground hover:bg-muted',
              )}
            >
              <ListIcon size={12} /> Lista
            </button>
            <button
              type="button"
              onClick={() => changeView('fila')}
              aria-pressed={view === 'fila'}
              className={cn(
                'px-2.5 py-1 text-xs font-medium inline-flex items-center gap-1 transition-colors border-l border-border',
                view === 'fila' ? 'bg-primary text-white' : 'text-foreground hover:bg-muted',
              )}
            >
              <ListOrdered size={12} /> Fila
            </button>
          </div>
        </div>

        {/* Tabela rica / Fila / Empty state */}
        {orders.data.length === 0 ? (
          <EmptyState
            icon="wrench"
            title="Nenhuma OS encontrada"
            description={
              filters.status || filters.type || filters.stage || filters.box || filters.q
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
            isOverdue={isOverdueClient}
            formatBRDate={formatBRDate}
            formatBRL={formatBRL}
            onOpenFull={setOpenOsId}
          />
        ) : (
          <div className="rounded-lg border bg-card overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="border-b bg-muted/50 text-[11px] uppercase tracking-wide text-muted-foreground">
                  <tr>
                    <th className="px-3 py-2.5 text-left font-semibold">OS</th>
                    <th className="px-3 py-2.5 text-left font-semibold">Placa</th>
                    <th className="px-3 py-2.5 text-left font-semibold">Veículo</th>
                    <th className="px-3 py-2.5 text-left font-semibold">Cliente</th>
                    <th className="px-3 py-2.5 text-left font-semibold">Etapa</th>
                    <th className="px-3 py-2.5 text-left font-semibold">Box</th>
                    <th className="px-3 py-2.5 text-left font-semibold">Mecânico</th>
                    <th className="px-3 py-2.5 text-left font-semibold">Prazo</th>
                    <th className="px-3 py-2.5 text-right font-semibold">Valor</th>
                  </tr>
                </thead>
                <tbody>
                  {orders.data.map((o) => {
                    const overdue = isOverdueClient(o);
                    const tone = toneForColor(o.stage?.color);
                    return (
                      <tr
                        key={o.id}
                        className={cn(
                          'border-b last:border-0 transition-colors cursor-pointer',
                          overdue ? 'bg-rose-50/40 hover:bg-rose-50' : 'hover:bg-muted/30',
                        )}
                        onClick={() => setOpenOsId(o.id)}
                      >
                        <td className="px-3 py-2.5 font-mono font-semibold whitespace-nowrap">
                          {o.number ?? `#${o.id}`}
                        </td>
                        <td className="px-3 py-2">
                          {o.vehicle?.plate ? (
                            <MercosulPlate plate={o.vehicle.plate} size="sm" />
                          ) : (
                            <span className="text-xs text-muted-foreground italic">sem placa</span>
                          )}
                        </td>
                        <td className="px-3 py-2.5">
                          <span className="font-medium text-foreground">
                            {capitalize(o.vehicle?.vehicle_type) || '—'}
                          </span>
                          {o.km != null && (
                            <span className="ml-1.5 text-[11px] text-muted-foreground tabular-nums">
                              {o.km.toLocaleString('pt-BR')} km
                            </span>
                          )}
                        </td>
                        <td className="px-3 py-2.5">
                          {o.contact?.name ?? <span className="text-muted-foreground">—</span>}
                        </td>
                        <td className="px-3 py-2.5 whitespace-nowrap">
                          {o.stage ? (
                            <span className="inline-flex items-center gap-1.5 text-xs">
                              <span className={cn('inline-block h-2 w-2 rounded-full', tone.dot)} />
                              {o.stage.name}
                            </span>
                          ) : (
                            <span className="text-xs text-muted-foreground">—</span>
                          )}
                        </td>
                        <td className="px-3 py-2.5 text-xs">
                          {o.box ?? <span className="text-muted-foreground">—</span>}
                        </td>
                        <td className="px-3 py-2.5 text-xs">
                          {o.mechanic_name ?? <span className="text-muted-foreground">—</span>}
                        </td>
                        <td
                          className={cn(
                            'px-3 py-2.5 text-xs tabular-nums whitespace-nowrap',
                            overdue ? 'text-rose-700 font-medium' : 'text-muted-foreground',
                          )}
                        >
                          {formatBRDate(o.expected_completion)}
                          {overdue && ' ⚠'}
                        </td>
                        <td
                          className={cn(
                            'px-3 py-2.5 text-right tabular-nums whitespace-nowrap',
                            Number(o.items_total ?? 0) > 0 ? 'text-foreground font-medium' : 'text-muted-foreground',
                          )}
                        >
                          {formatBRL(o.items_total)}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>

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

      {/* Drawer ServiceOrder — abre ao clicar em qualquer linha. */}
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
  if (filters.box && filters.box !== 'all') out.box = filters.box;
  return out;
}
