// @memcofre tela=/repair/device-models module=Repair
// Blade T1 Migration C (2026-05-17) — port Device Models (Repair) Blade → Inertia/React.
// Expansão sprint 2.5: KPIs + filtros brand_id/device_id + Inertia::defer (RUNBOOK-inertia-defer-pattern).
// Charter: Index.charter.md. RUNBOOK: memory/requisitos/Repair/RUNBOOK-device-models.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { Link, router, Deferred } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Icon } from '@/Components/Icon';

interface ModelRow {
  id: number;
  name: string;
  device_id: number | null;
  brand_id: number | null;
  device_name: string | null;
  brand_name: string | null;
  has_checklist: boolean;
}

interface Kpis {
  total: number;
  brands: number;
  categories: number;
}

interface Filters {
  brand_id: number | null;
  device_id: number | null;
}

interface DropdownMap {
  [key: string]: string;
}

interface PageProps {
  filters: Filters;
  models?: ModelRow[];
  kpis?: Kpis;
  brands: DropdownMap;
  devices: DropdownMap;
}

const LS_PREFIX = 'oimpresso.repair.device_models.index';

function KpiCard({ label, value }: { label: string; value: number | string }) {
  return (
    <div className="rounded-lg border border-border bg-card p-4">
      <div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div>
      <div className="mt-1 text-2xl font-semibold text-foreground">{value}</div>
    </div>
  );
}

function KpiSkeleton() {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
      {[0, 1, 2].map((i) => (
        <div key={i} className="rounded-lg border border-border bg-card p-4">
          <div className="h-3 w-20 bg-muted rounded animate-pulse" />
          <div className="h-7 w-12 mt-2 bg-muted rounded animate-pulse" />
        </div>
      ))}
    </div>
  );
}

function TableSkeleton() {
  return (
    <div className="rounded-lg border border-border bg-card p-6">
      <div className="space-y-2">
        {[0, 1, 2, 3].map((i) => (
          <div key={i} className="h-8 w-full bg-muted rounded animate-pulse" />
        ))}
      </div>
    </div>
  );
}

export default function DeviceModelsIndex({ filters, models, kpis, brands, devices }: PageProps) {
  const [brandFilter, setBrandFilter] = useState<string>(filters?.brand_id ? String(filters.brand_id) : '');
  const [deviceFilter, setDeviceFilter] = useState<string>(filters?.device_id ? String(filters.device_id) : '');

  // Hidrata filtros do localStorage só se ainda não houver querystring.
  useEffect(() => {
    if (filters?.brand_id || filters?.device_id) return;
    try {
      const b = localStorage.getItem(`${LS_PREFIX}.brand_id`);
      const d = localStorage.getItem(`${LS_PREFIX}.device_id`);
      if (b) setBrandFilter(b);
      if (d) setDeviceFilter(d);
    } catch {
      /* ignore */
    }
  }, [filters?.brand_id, filters?.device_id]);

  const applyFilters = () => {
    try {
      localStorage.setItem(`${LS_PREFIX}.brand_id`, brandFilter);
      localStorage.setItem(`${LS_PREFIX}.device_id`, deviceFilter);
    } catch {
      /* ignore */
    }
    // D-14: partial reload — só re-busca o que muda com filtro (models/filters).
    // brands/devices/kpis são por-business (closures/defer no controller) e nem rodam.
    router.get(
      '/repair/device-models',
      {
        brand_id: brandFilter || undefined,
        device_id: deviceFilter || undefined,
      },
      { preserveScroll: true, preserveState: true, replace: true, only: ['models', 'filters'] },
    );
  };

  const clearFilters = () => {
    setBrandFilter('');
    setDeviceFilter('');
    try {
      localStorage.removeItem(`${LS_PREFIX}.brand_id`);
      localStorage.removeItem(`${LS_PREFIX}.device_id`);
    } catch {
      /* ignore */
    }
    // D-14: partial reload — limpar filtro só re-busca models/filters.
    router.get('/repair/device-models', {}, { preserveScroll: true, preserveState: true, replace: true, only: ['models', 'filters'] });
  };

  const hasActiveFilters = Boolean(brandFilter || deviceFilter);

  return (
    <div className="container mx-auto p-4">
      <PageHeader
        icon="smartphone"
        title="Modelos de Dispositivo (Repair)"
        description="Catálogo compartilhado de modelos atendidos pela oficina — marca, categoria e checklist"
        action={
          <Button asChild>
            <Link href="/repair/device-models/create">
              <Icon name="plus" className="mr-2 h-4 w-4" />
              Novo modelo
            </Link>
          </Button>
        }
      />

      <Deferred data="kpis" fallback={<KpiSkeleton />}>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
          <KpiCard label="Total de modelos" value={kpis?.total ?? 0} />
          <KpiCard label="Marcas ativas" value={kpis?.brands ?? 0} />
          <KpiCard label="Categorias" value={kpis?.categories ?? 0} />
        </div>
      </Deferred>

      <div className="rounded-lg border border-border bg-card p-3 mb-4">
        <div className="flex flex-wrap gap-2 items-end">
          <div className="flex flex-col">
            <label htmlFor="filter-brand" className="text-xs text-muted-foreground mb-1">
              Marca
            </label>
            <Select
              value={brandFilter || '__none__'}
              onValueChange={(v) => setBrandFilter(v === '__none__' ? '' : v)}
            >
              <SelectTrigger id="filter-brand" variant="shadcn" size="sm" aria-label="Marca" className="min-w-40 text-sm">
                <SelectValue placeholder="Todas as marcas" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none__">Todas as marcas</SelectItem>
                {Object.entries(brands ?? {}).map(([id, name]) => (
                  <SelectItem key={id} value={id}>
                    {name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="flex flex-col">
            <label htmlFor="filter-device" className="text-xs text-muted-foreground mb-1">
              Categoria
            </label>
            <Select
              value={deviceFilter || '__none__'}
              onValueChange={(v) => setDeviceFilter(v === '__none__' ? '' : v)}
            >
              <SelectTrigger id="filter-device" variant="shadcn" size="sm" aria-label="Categoria" className="min-w-40 text-sm">
                <SelectValue placeholder="Todas as categorias" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none__">Todas as categorias</SelectItem>
                {Object.entries(devices ?? {}).map(([id, name]) => (
                  <SelectItem key={id} value={id}>
                    {name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <Button type="button" size="sm" onClick={applyFilters}>
            <Icon name="filter" className="mr-1 h-3 w-3" />
            Filtrar
          </Button>
          {hasActiveFilters && (
            <Button type="button" size="sm" variant="outline" onClick={clearFilters}>
              Limpar
            </Button>
          )}
        </div>
      </div>

      <Deferred data="models" fallback={<TableSkeleton />}>
        {(models ?? []).length === 0 ? (
          <EmptyState
            icon="smartphone"
            title="Nenhum modelo cadastrado"
            description={
              hasActiveFilters
                ? 'Nenhum modelo bate com os filtros aplicados.'
                : 'Cadastre os modelos de dispositivos que sua oficina atende.'
            }
          />
        ) : (
          <div className="rounded-lg border border-border bg-card overflow-hidden">
            <table className="w-full">
              <thead className="bg-muted/50 text-left text-sm">
                <tr>
                  <th className="px-4 py-3 font-medium text-foreground">Modelo</th>
                  <th className="px-4 py-3 font-medium text-foreground">Marca</th>
                  <th className="px-4 py-3 font-medium text-foreground">Categoria</th>
                  <th className="px-4 py-3 font-medium text-center text-foreground">Checklist</th>
                  <th className="px-4 py-3 font-medium w-24"></th>
                </tr>
              </thead>
              <tbody className="text-sm">
                {(models ?? []).map((m) => (
                  <tr
                    key={m.id}
                    className="border-t border-border hover:bg-accent/50 transition-colors focus-within:bg-accent/50"
                  >
                    <td className="px-4 py-3">
                      <div className="font-medium text-foreground">{m.name}</div>
                    </td>
                    <td className="px-4 py-3 text-foreground">
                      {m.brand_name ?? <span className="text-muted-foreground">—</span>}
                    </td>
                    <td className="px-4 py-3 text-foreground">
                      {m.device_name ?? <span className="text-muted-foreground">—</span>}
                    </td>
                    <td className="px-4 py-3 text-center">
                      {m.has_checklist ? (
                        <Badge variant="secondary" className="gap-1">
                          <Icon name="list-checks" className="h-3 w-3" />
                          Sim
                        </Badge>
                      ) : (
                        <span className="text-muted-foreground" aria-label="Sem checklist">
                          —
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-right">
                      <Button variant="ghost" size="sm" asChild>
                        <Link href={`/repair/device-models/${m.id}/edit`}>Editar</Link>
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Deferred>
    </div>
  );
}

DeviceModelsIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
