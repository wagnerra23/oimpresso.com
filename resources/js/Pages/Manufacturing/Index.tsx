// @memcofre tela=/manufacturing/v2/production module=Manufacturing
// MWART Wave J → board 2026-05-30 uplift (50 Developing → ≥70).
// Lista de produções (production_purchase) em Inertia/React no padrão PT-01
// Lista (AppShellV2 + PageHeader + KpiCard + tabela tokenizada + EmptyState).
// Coexiste com Blade legacy /manufacturing/production (Tier 0: preservado).
//
// Backend: ProductionController@indexV2 → ProductionService::listProductions/summary
// (scoped por business_id — Tier 0 ADR 0093). Filtros (location/data/finalizadas)
// via Inertia partial reload. CTA aponta pra rota legacy de create existente.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Plus, Search, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/shared/PageHeader';
import KpiCard from '@/Components/shared/KpiCard';
import EmptyState from '@/Components/shared/EmptyState';

interface Production {
  id: number;
  ref_no: string | null;
  /** Já formatada `dd/mm/aaaa` pelo Controller (indexV2). */
  transaction_date: string | null;
  location_name: string | null;
  final_total: number;
  mfg_is_final: number;
}

interface Summary {
  total_count: number;
  final_count: number;
  pending_count: number;
  total_value: number;
}

interface FiltersState {
  location_id?: number | null;
  start_date?: string | null;
  end_date?: string | null;
  is_final?: boolean | null;
}

interface Props {
  productions: Production[];
  summary: Summary;
  /** id → nome. Pode não vir em versões antigas do payload. */
  business_locations?: Record<number, string>;
  filters?: FiltersState;
}

const ROUTE = '/manufacturing/v2/production';
const CREATE_ROUTE = '/manufacturing/production/create';

function applyFilter(current: FiltersState, patch: Partial<FiltersState>) {
  // Merge current+patch, depois serializa explicitamente em string|number|undefined
  // (RequestPayload do Inertia não aceita `unknown`). is_final é flag de presença
  // no backend (request()->has('is_final')) — só envia quando true.
  const merged = { ...current, ...patch };
  const next: Record<string, string | number | undefined> = {
    location_id: merged.location_id ?? undefined,
    start_date: merged.start_date ?? undefined,
    end_date: merged.end_date ?? undefined,
    is_final: merged.is_final ? 1 : undefined,
  };
  router.get(ROUTE, next, {
    preserveState: true,
    preserveScroll: true,
    only: ['productions', 'summary', 'filters'],
    replace: true,
  });
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(value ?? 0);
}

function Index({ productions = [], summary, business_locations = {}, filters = {} }: Props) {
  const [start, setStart] = useState<string>(filters.start_date ?? '');
  const [end, setEnd] = useState<string>(filters.end_date ?? '');

  const locationEntries = Object.entries(business_locations);
  const hasLocations = locationEntries.length > 0;

  const hasActiveFilters =
    !!filters.location_id ||
    !!filters.start_date ||
    !!filters.end_date ||
    !!filters.is_final;

  const clearAll = () => {
    setStart('');
    setEnd('');
    // D-14: partial reload — limpar filtros re-busca só o que muda (espelha applyFilter).
    router.get(ROUTE, {}, {
      preserveState: true,
      preserveScroll: true,
      only: ['productions', 'summary', 'filters'],
      replace: true,
    });
  };

  const applyDateRange = () => {
    if (start && end) {
      applyFilter(filters, { start_date: start, end_date: end });
    } else if (!start && !end) {
      applyFilter(filters, { start_date: null, end_date: null });
    }
  };

  return (
    <div className="space-y-6 p-6">
      {/* Slot 1 — PageHeader com CTA habilitado (rota legacy de create existe) */}
      <PageHeader
        icon="factory"
        title="Produção"
        description="Ordens de produção (Manufacturing). Lista MWART em coexistência com a tela legacy."
        action={
          <Button asChild>
            <a href={CREATE_ROUTE}>
              <Plus className="mr-2 h-4 w-4" /> Nova produção
            </a>
          </Button>
        }
      />

      {/* KPI strip — "Finalizadas" e "Pendentes" filtram a lista */}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <KpiCard
          label="Total"
          value={summary?.total_count ?? 0}
          icon="layers"
          size="compact"
        />
        <KpiCard
          label="Finalizadas"
          value={summary?.final_count ?? 0}
          icon="check-circle-2"
          tone={filters.is_final ? 'success' : 'default'}
          size="compact"
          onClick={() => applyFilter(filters, { is_final: filters.is_final ? null : true })}
          selected={!!filters.is_final}
        />
        <KpiCard
          label="Pendentes"
          value={summary?.pending_count ?? 0}
          icon="clock"
          size="compact"
        />
        <KpiCard
          label="Valor total"
          value={formatCurrency(summary?.total_value ?? 0)}
          icon="dollar-sign"
          size="compact"
        />
      </div>

      {/* Slot 3 — Toolbar de filtros (local + intervalo de data) */}
      <div className="rounded-lg border border-border bg-card p-4 space-y-3">
        <div className="flex flex-wrap items-center gap-2">
          {hasLocations && (
            // eslint-disable-next-line no-restricted-syntax -- select nativo: filtro simples de local, estilizado com tokens DS
            <select
              className="h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground"
              value={filters.location_id ?? ''}
              onChange={(e) =>
                applyFilter(filters, {
                  location_id: e.target.value ? Number(e.target.value) : null,
                })
              }
              aria-label="Filtrar por local"
            >
              <option value="">Todos os locais</option>
              {locationEntries.map(([id, name]) => (
                <option key={id} value={id}>
                  {String(name)}
                </option>
              ))}
            </select>
          )}

          <div className="flex items-center gap-1.5">
            <Input
              type="date"
              value={start}
              onChange={(e) => setStart(e.target.value)}
              onBlur={applyDateRange}
              className="h-9 w-[150px]"
              aria-label="Data inicial"
            />
            <span className="text-sm text-muted-foreground">até</span>
            <Input
              type="date"
              value={end}
              onChange={(e) => setEnd(e.target.value)}
              onBlur={applyDateRange}
              className="h-9 w-[150px]"
              aria-label="Data final"
            />
            <Button variant="outline" size="sm" onClick={applyDateRange}>
              <Search className="h-4 w-4" />
            </Button>
          </div>

          {hasActiveFilters && (
            <Button variant="ghost" size="sm" onClick={clearAll}>
              <X className="mr-1 h-4 w-4" /> Limpar
            </Button>
          )}
        </div>
      </div>

      {/* Slot 5 — Tabela tokenizada */}
      <div className="rounded-lg border border-border bg-card overflow-x-auto">
        {productions.length === 0 ? (
          <EmptyState
            icon="factory"
            variant={hasActiveFilters ? 'search' : 'default'}
            title={hasActiveFilters ? 'Nenhuma produção no filtro' : 'Sem produções cadastradas'}
            description={
              hasActiveFilters
                ? 'Ajuste ou limpe os filtros pra ver mais resultados.'
                : 'Crie a primeira ordem de produção pelo botão "Nova produção".'
            }
            action={
              hasActiveFilters ? (
                <Button variant="outline" size="sm" onClick={clearAll}>
                  <X className="mr-1 h-4 w-4" /> Limpar filtros
                </Button>
              ) : (
                <Button asChild size="sm">
                  <a href={CREATE_ROUTE}>
                    <Plus className="mr-2 h-4 w-4" /> Nova produção
                  </a>
                </Button>
              )
            }
          />
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-muted/50">
              <tr className="text-left">
                <th className="px-3 py-2 font-medium text-muted-foreground">Ref</th>
                <th className="px-3 py-2 font-medium text-muted-foreground">Data</th>
                <th className="px-3 py-2 font-medium text-muted-foreground">Local</th>
                <th className="px-3 py-2 font-medium text-muted-foreground text-right">Total</th>
                <th className="px-3 py-2 font-medium text-muted-foreground">Status</th>
              </tr>
            </thead>
            <tbody>
              {productions.map((p) => (
                <tr key={p.id} className="border-t border-border hover:bg-muted/30 transition-colors">
                  <td className="px-3 py-2 font-mono text-foreground">{p.ref_no ?? '—'}</td>
                  <td className="px-3 py-2 text-muted-foreground tabular-nums">
                    {p.transaction_date ?? '—'}
                  </td>
                  <td className="px-3 py-2 max-w-[220px] truncate text-foreground" title={p.location_name ?? ''}>
                    {p.location_name ?? '—'}
                  </td>
                  <td className="px-3 py-2 text-right font-medium text-foreground tabular-nums">
                    {formatCurrency(p.final_total)}
                  </td>
                  <td className="px-3 py-2">
                    <StatusPill isFinal={p.mfg_is_final} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {productions.length > 0 && (
        <p className="text-xs text-muted-foreground tabular-nums">
          {productions.length} produç{productions.length === 1 ? 'ão' : 'ões'} exibida
          {productions.length === 1 ? '' : 's'}.
        </p>
      )}
    </div>
  );
}

// Status dot-style (Stripe-like) com tokens semânticos — sem bg-fill cru (PT-01).
function StatusPill({ isFinal }: { isFinal: number }) {
  if (isFinal) {
    return (
      <span className="inline-flex items-center gap-1.5 text-xs font-medium text-success-fg">
        <span className="h-1.5 w-1.5 rounded-full bg-success" aria-hidden />
        Finalizada
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1.5 text-xs font-medium text-warning-fg">
      <span className="h-1.5 w-1.5 rounded-full bg-warning" aria-hidden />
      Pendente
    </span>
  );
}

Index.layout = (page: ReactNode) => (
  <AppShellV2
    title="Produção · Manufacturing"
    breadcrumbItems={[{ label: 'Manufacturing' }, { label: 'Produção' }]}
  >
    {page}
  </AppShellV2>
);

export default Index;
