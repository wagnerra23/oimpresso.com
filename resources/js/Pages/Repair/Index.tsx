// @memcofre tela=/repair/repair module=Repair
// Sprint 2 / MWART-0001 — port 1:1 da listagem Blade legacy (DataTables)
// para Inertia/React. Não muda UX; troca só o motor de renderização.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router, Link } from '@inertiajs/react';
import { useState, useMemo, type ReactNode } from 'react';
import { Wrench, Plus, ChevronLeft, ChevronRight, Search, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import PageHeader from '@/Components/shared/PageHeader';
import KpiCard from '@/Components/shared/KpiCard';
import EmptyState from '@/Components/shared/EmptyState';

interface RepairRow {
  id: number;
  invoice_no: string;
  transaction_date: string | null;
  repair_due_date: string | null;
  repair_due_human: string | null;
  is_overdue: boolean;
  serial_no: string | null;
  defects: string | null;
  final_total: number;
  final_total_formatted: string | null;
  payment_status: string;
  contact: { id: number | null; name: string | null };
  service_staff: { id: number; name: string } | null;
  location: { id: number | null; name: string | null };
  status: { id: number | null; name: string | null; color: string | null };
  warranty_name: string | null;
  device_model_name: string | null;
  created_by: number | null;
}

interface PaginatorMeta {
  current_page: number;
  last_page: number;
  from: number | null;
  to: number | null;
  total: number;
  per_page: number;
}

interface Paginator {
  data: RepairRow[];
  meta: PaginatorMeta;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface FiltersState {
  q?: string | null;
  repair_status_id?: number[] | null;
  contact_id?: number | null;
  location_id?: number | null;
  service_staff_id?: number | null;
  start_date?: string | null;
  end_date?: string | null;
  due_start?: string | null;
  due_end?: string | null;
  sort?: string | null;
  dir?: 'asc' | 'desc' | null;
  per_page?: 25 | 50 | 100 | null;
  is_completed?: '0' | '1' | null;
}

interface Props {
  repairs: Paginator;
  filters: FiltersState;
  meta: {
    totals: { em_andamento: number; completas: number };
    repair_statuses: Record<number, string>;
    service_staff: Record<number, string>;
    business_locations: Record<number, string>;
    currency_symbol: string;
  };
  permissions: {
    create: boolean;
    update: boolean;
    delete: boolean;
    status_update: boolean;
    view_all: boolean;
  };
}

const ROUTE = '/repair/repair';

function applyFilter(current: FiltersState, patch: Partial<FiltersState>) {
  router.get(
    ROUTE,
    { ...current, ...patch, page: 1 } as Record<string, unknown>,
    {
      preserveState: true,
      preserveScroll: true,
      only: ['repairs', 'filters', 'meta'],
      replace: true,
    }
  );
}

function toggleStatus(filters: FiltersState, statusId: number) {
  const current = filters.repair_status_id ?? [];
  const next = current.includes(statusId)
    ? current.filter((id) => id !== statusId)
    : [...current, statusId];
  applyFilter(filters, { repair_status_id: next.length ? next : null });
}

function fmtDate(iso: string | null): string {
  if (!iso) return '—';
  const d = new Date(iso);
  return d.toLocaleDateString('pt-BR');
}

function StatusPill({ status }: { status: RepairRow['status'] }) {
  if (!status?.name) return <span className="text-muted-foreground">—</span>;
  const bg = status.color ?? '#6b7280';
  return (
    <span
      className="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium text-white"
      style={{ backgroundColor: bg }}
    >
      {status.name}
    </span>
  );
}

function PaymentPill({ value }: { value: string }) {
  const map: Record<string, string> = {
    paid: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/30 dark:text-emerald-200',
    partial: 'bg-amber-100 text-amber-900 dark:bg-amber-900/30 dark:text-amber-200',
    due: 'bg-rose-100 text-rose-900 dark:bg-rose-900/30 dark:text-rose-200',
  };
  const label: Record<string, string> = {
    paid: 'Pago',
    partial: 'Parcial',
    due: 'Devendo',
  };
  return (
    <span
      className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${map[value] ?? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-slate-200'}`}
    >
      {label[value] ?? value}
    </span>
  );
}

function Index({ repairs, filters, meta, permissions }: Props) {
  const [search, setSearch] = useState<string>(filters.q ?? '');

  const activeStatusIds = useMemo(
    () => new Set(filters.repair_status_id ?? []),
    [filters.repair_status_id]
  );

  const submitSearch = () => {
    applyFilter(filters, { q: search.trim() || null });
  };

  const clearAll = () => {
    setSearch('');
    // D-14: partial reload — limpar filtro só re-busca o que muda (repairs/filters/meta;
    // meta.totals depende do filtro). Estado local já é resetado via setSearch acima.
    router.get(ROUTE, {}, {
      preserveState: true,
      preserveScroll: true,
      only: ['repairs', 'filters', 'meta'],
      replace: true,
    });
  };

  const hasActiveFilters =
    !!filters.q ||
    !!(filters.repair_status_id && filters.repair_status_id.length) ||
    !!filters.contact_id ||
    !!filters.location_id ||
    !!filters.service_staff_id ||
    !!filters.start_date ||
    !!filters.end_date ||
    !!filters.due_start ||
    !!filters.due_end ||
    !!filters.is_completed;

  const statusOptions = Object.entries(meta.repair_statuses).map(([id, name]) => ({
    id: Number(id),
    name: String(name),
  }));

  return (
    <div className="space-y-6 p-6">
      <PageHeader
        icon="wrench"
        title="Ordens de Serviço"
        description="Listagem MWART (Sprint 2). Port 1:1 da tela Blade — mesmos filtros, mesmos dados."
        action={
          permissions.create ? (
            <Button asChild>
              <a href="/sells/create?sub_type=repair">
                <Plus className="mr-2 h-4 w-4" /> Nova OS
              </a>
            </Button>
          ) : null
        }
      />

      {/* KPI strip */}
      <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
        <KpiCard
          label="Em andamento"
          value={meta.totals.em_andamento}
          icon="wrench"
          tone={filters.is_completed === '0' ? 'info' : 'default'}
          compact
          onClick={() => applyFilter(filters, { is_completed: filters.is_completed === '0' ? null : '0' })}
        />
        <KpiCard
          label="Concluídas"
          value={meta.totals.completas}
          icon="check"
          tone={filters.is_completed === '1' ? 'success' : 'default'}
          compact
          onClick={() => applyFilter(filters, { is_completed: filters.is_completed === '1' ? null : '1' })}
        />
        <KpiCard
          label="Total exibido"
          value={repairs.meta.total}
          icon="list"
          tone="default"
          compact
        />
      </div>

      {/* Filtros */}
      <div className="rounded-lg border bg-card p-4 space-y-3">
        <div className="flex flex-wrap items-center gap-2">
          <div className="relative flex-1 min-w-[280px]">
            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              type="text"
              placeholder="Nº OS, cliente ou nº de série"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') submitSearch();
                if (e.key === 'Escape') {
                  setSearch('');
                  applyFilter(filters, { q: null });
                }
              }}
              onBlur={submitSearch}
              className="pl-8"
            />
          </div>

          <Select
            value={String(filters.location_id ?? '__none__')}
            onValueChange={(v) =>
              applyFilter(filters, { location_id: v === '__none__' ? null : Number(v) })
            }
          >
            <SelectTrigger variant="shadcn" aria-label="Local" className="text-sm">
              <SelectValue placeholder="Todos os locais" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__none__">Todos os locais</SelectItem>
              {Object.entries(meta.business_locations).map(([id, name]) => (
                <SelectItem key={id} value={id}>
                  {String(name)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          <Select
            value={String(filters.service_staff_id ?? '__none__')}
            onValueChange={(v) =>
              applyFilter(filters, {
                service_staff_id: v === '__none__' ? null : Number(v),
              })
            }
          >
            <SelectTrigger variant="shadcn" aria-label="Responsável" className="text-sm">
              <SelectValue placeholder="Todos os responsáveis" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__none__">Todos os responsáveis</SelectItem>
              {Object.entries(meta.service_staff).map(([id, name]) => (
                <SelectItem key={id} value={id}>
                  {String(name)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          {hasActiveFilters && (
            <Button variant="ghost" size="sm" onClick={clearAll}>
              <X className="mr-1 h-4 w-4" /> Limpar
            </Button>
          )}
        </div>

        {/* Status chips */}
        {statusOptions.length > 0 && (
          <div className="flex flex-wrap gap-1.5">
            {statusOptions.map((s) => {
              const active = activeStatusIds.has(s.id);
              return (
                <button
                  key={s.id}
                  type="button"
                  onClick={() => toggleStatus(filters, s.id)}
                  className={`rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ${
                    active
                      ? 'bg-primary text-primary-foreground ring-primary'
                      : 'bg-muted text-muted-foreground ring-border hover:bg-muted/70'
                  }`}
                >
                  {s.name}
                </button>
              );
            })}
          </div>
        )}
      </div>

      {/* Tabela */}
      <div className="rounded-md border overflow-x-auto">
        {repairs.data.length === 0 ? (
          <EmptyState
            icon="wrench"
            title={hasActiveFilters ? 'Nenhuma OS no filtro' : 'Sem ordens de serviço'}
            description={
              hasActiveFilters
                ? 'Ajuste ou limpe os filtros pra ver mais resultados.'
                : permissions.create
                  ? 'Crie a primeira OS pelo botão "Nova OS".'
                  : 'Quando houver OS aberta, ela aparece aqui.'
            }
          />
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-muted/50">
              <tr className="text-left">
                <th className="px-3 py-2 font-medium">OS</th>
                <th className="px-3 py-2 font-medium">Status</th>
                <th className="px-3 py-2 font-medium">Cliente</th>
                <th className="px-3 py-2 font-medium">Aparelho</th>
                <th className="px-3 py-2 font-medium">Série</th>
                <th className="px-3 py-2 font-medium">Resp.</th>
                <th className="px-3 py-2 font-medium">Aberta</th>
                <th className="px-3 py-2 font-medium">Prazo</th>
                <th className="px-3 py-2 font-medium text-right">Total</th>
                <th className="px-3 py-2 font-medium">Pgto</th>
              </tr>
            </thead>
            <tbody>
              {repairs.data.map((row) => (
                <tr
                  key={row.id}
                  className="border-t hover:bg-muted/30 transition-colors"
                >
                  <td className="px-3 py-2 font-mono">
                    <Link href={`/repair/repair/${row.id}`} className="text-primary hover:underline">
                      {row.invoice_no}
                    </Link>
                  </td>
                  <td className="px-3 py-2">
                    <StatusPill status={row.status} />
                  </td>
                  <td className="px-3 py-2 max-w-[220px] truncate" title={row.contact.name ?? ''}>
                    {row.contact.name ?? '—'}
                  </td>
                  <td className="px-3 py-2 max-w-[160px] truncate" title={row.device_model_name ?? ''}>
                    {row.device_model_name ?? '—'}
                  </td>
                  <td className="px-3 py-2 font-mono text-xs">{row.serial_no ?? '—'}</td>
                  <td className="px-3 py-2">{row.service_staff?.name ?? '—'}</td>
                  <td className="px-3 py-2">{fmtDate(row.transaction_date)}</td>
                  <td className="px-3 py-2">
                    <div className="flex items-center gap-2">
                      <span>{fmtDate(row.repair_due_date)}</span>
                      {row.is_overdue && (
                        <span className="rounded-full bg-rose-100 text-rose-900 dark:bg-rose-900/30 dark:text-rose-200 px-1.5 py-0.5 text-[10px] font-semibold">
                          atrasada
                        </span>
                      )}
                    </div>
                  </td>
                  <td className="px-3 py-2 text-right font-medium">
                    {row.final_total_formatted ?? `${meta.currency_symbol} ${row.final_total.toFixed(2)}`}
                  </td>
                  <td className="px-3 py-2">
                    <PaymentPill value={row.payment_status} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Paginação */}
      {repairs.meta.total > 0 && (
        <div className="flex items-center justify-between text-sm text-muted-foreground">
          <div>
            Mostrando {repairs.meta.from ?? 0}–{repairs.meta.to ?? 0} de {repairs.meta.total}
          </div>
          <div className="flex items-center gap-1">
            <Button
              variant="outline"
              size="sm"
              disabled={repairs.meta.current_page <= 1}
              onClick={() =>
                router.get(
                  ROUTE,
                  { ...filters, page: repairs.meta.current_page - 1 } as Record<string, unknown>,
                  { preserveState: true, preserveScroll: true, only: ['repairs', 'filters'] }
                )
              }
            >
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <span className="px-2">
              {repairs.meta.current_page} / {repairs.meta.last_page}
            </span>
            <Button
              variant="outline"
              size="sm"
              disabled={repairs.meta.current_page >= repairs.meta.last_page}
              onClick={() =>
                router.get(
                  ROUTE,
                  { ...filters, page: repairs.meta.current_page + 1 } as Record<string, unknown>,
                  { preserveState: true, preserveScroll: true, only: ['repairs', 'filters'] }
                )
              }
            >
              <ChevronRight className="h-4 w-4" />
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}

Index.layout = (page: ReactNode) => (
  <AppShellV2 title="Ordens de Serviço · Repair" breadcrumbItems={[{ label: 'Repair' }, { label: 'OS' }]}>
    {page}
  </AppShellV2>
);
export default Index;
