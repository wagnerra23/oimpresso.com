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
import { Checkbox } from '@/Components/ui/checkbox';
import { Inline } from '@/Components/layout/inline';
import PageHeader from '@/Components/shared/PageHeader';
import KpiCard from '@/Components/shared/KpiCard';
import EmptyState from '@/Components/shared/EmptyState';
import StatusBadge from '@/Components/shared/StatusBadge';

interface Production {
  id: number;
  ref_no: string | null;
  /** Já formatada `dd/mm/aaaa` pelo Service (enrichProductionRows). */
  transaction_date: string | null;
  location_name: string | null;
  /** `transactions.final_total` — valor GRAVADO na criação, não recalculado. */
  final_total: number;
  mfg_is_final: number;
  // US-MANU-004 (§4.5) — as 3 colunas novas + o que a coluna Produto mostra na 2ª linha.
  produto: string;
  unidade: string;
  n_ingredientes: number;
  criado_por: string;
  quantidade: number;
  /** `final_total / quantidade`, com guard de divisão por zero no Service. */
  custo_unitario: number;
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

/** Quantidade produzida com 2 casas — §4.5 mostra `num(op.qtd, 2)`. */
function formatQuantity(value: number): string {
  return new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value ?? 0);
}

function Index({ productions = [], summary, business_locations = {}, filters = {} }: Props) {
  const [start, setStart] = useState<string>(filters.start_date ?? '');
  const [end, setEnd] = useState<string>(filters.end_date ?? '');

  const locationEntries = Object.entries(business_locations);
  const hasLocations = locationEntries.length > 0;

  // §4.5 — soma dos `final_total` das ordens LISTADAS (o mesmo conjunto que a tabela mostra).
  const custoDoPeriodo = productions.reduce((s, p) => s + (p.final_total ?? 0), 0);

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

          {/* §4.5 — "Só finalizadas" como checkbox. O KPI "Finalizadas" continua clicável
              (os 4 KPIs não mudam nesta onda); os dois governam o MESMO filtro. */}
          {/* `Inline asChild` em vez de layout solto no próprio label: layout é composição de
              primitivos (ADR 0253). O ratchet pegou o caso na primeira tentativa — e depois
              pegou o COMENTÁRIO que citava o anti-padrão, porque o guard casa texto. */}
          <Inline gap={2} align="center" asChild>
            <label className="text-sm text-muted-foreground" htmlFor="mfg-op-so-finalizadas">
              <Checkbox
                id="mfg-op-so-finalizadas"
                checked={!!filters.is_final}
                onCheckedChange={(v) => applyFilter(filters, { is_final: v === true ? true : null })}
              />
              Só finalizadas
            </label>
          </Inline>

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
            {/* §4.5 — 8 colunas, na ordem do protótipo (MfgProducaoView). */}
            <thead className="bg-muted/50">
              <tr className="text-left">
                <th className="px-3 py-2 font-medium text-muted-foreground">Data</th>
                <th className="px-3 py-2 font-medium text-muted-foreground">Referência</th>
                <th className="px-3 py-2 font-medium text-muted-foreground">Local</th>
                <th className="px-3 py-2 font-medium text-muted-foreground">Produto</th>
                <th className="px-3 py-2 font-medium text-muted-foreground text-right">Qtd</th>
                <th className="px-3 py-2 font-medium text-muted-foreground text-right">Custo total</th>
                <th className="px-3 py-2 font-medium text-muted-foreground text-right">Custo unit.</th>
                <th className="px-3 py-2 font-medium text-muted-foreground">Situação</th>
              </tr>
            </thead>
            <tbody>
              {productions.map((p) => (
                <tr key={p.id} className="border-t border-border hover:bg-muted/30 transition-colors">
                  <td className="px-3 py-2 text-muted-foreground tabular-nums">
                    {p.transaction_date ?? '—'}
                  </td>
                  <td className="px-3 py-2 font-mono text-foreground">{p.ref_no ?? '—'}</td>
                  <td className="px-3 py-2 max-w-[180px] truncate text-foreground" title={p.location_name ?? ''}>
                    {p.location_name ?? '—'}
                  </td>
                  <td className="px-3 py-2 max-w-[260px]">
                    <span className="block truncate font-medium text-foreground" title={p.produto}>
                      {p.produto}
                    </span>
                    <span className="block truncate text-xs text-muted-foreground">
                      {p.n_ingredientes} ingrediente{p.n_ingredientes === 1 ? '' : 's'}
                      {p.criado_por ? ` · ${p.criado_por}` : ''}
                    </span>
                  </td>
                  <td className="px-3 py-2 text-right text-foreground tabular-nums">
                    {formatQuantity(p.quantidade)}
                    {p.unidade ? <span className="ml-1 text-xs text-muted-foreground">{p.unidade}</span> : null}
                  </td>
                  <td className="px-3 py-2 text-right font-medium text-foreground tabular-nums">
                    {formatCurrency(p.final_total)}
                    {/* R-21 — o `fix` marca que, na ordem finalizada, este é o custo
                        congelado na data da produção (não recalculado pelo preço de hoje). */}
                    {p.mfg_is_final ? (
                      <span
                        className="ml-1 text-xs text-muted-foreground"
                        title="custo congelado na data da produção"
                      >
                        fix
                      </span>
                    ) : null}
                  </td>
                  <td className="px-3 py-2 text-right text-muted-foreground tabular-nums">
                    {formatCurrency(p.custo_unitario)}
                  </td>
                  <td className="px-3 py-2">
                    <StatusBadge kind="producao" value={p.mfg_is_final ? 'finalizada' : 'rascunho'} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* §4.5 — rodapé verbatim do protótipo. O custo somado é o GRAVADO (`final_total`),
          não o recalculado do Relatório (US-MANU-002) — ver RUNBOOK-producao.md §1. */}
      {productions.length > 0 && (
        <p className="text-xs text-muted-foreground tabular-nums">
          {productions.length} ordens · custo do período{' '}
          <span className="font-medium text-foreground">{formatCurrency(custoDoPeriodo)}</span> ·
          ordens finalizadas mostram o custo congelado na data
        </p>
      )}
    </div>
  );
}

// US-MANU-004 — o `StatusPill` local saiu daqui: a situação agora vem do `StatusBadge`
// canônico (`kind="producao"`, domínio adicionado no componente compartilhado). Era
// exatamente o tipo de duplicata que o `reuse-gate` existe pra impedir.

Index.layout = (page: ReactNode) => (
  <AppShellV2
    title="Produção · Manufacturing"
    breadcrumbItems={[{ label: 'Manufacturing' }, { label: 'Produção' }]}
  >
    {page}
  </AppShellV2>
);

export default Index;
