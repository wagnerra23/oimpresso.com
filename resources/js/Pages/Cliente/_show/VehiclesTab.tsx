// Onda 1 PR D 2026-05-26 — Tab Veículos do cliente (frota Martinho).
//
// Daniela (cliente piloto Martinho biz=?) pediu placas do cliente direto no cadastro
// pra evitar ter que abrir OficinaAuto separado. Reutiliza schema `vehicles` (ADR 0137)
// via paginator backend `buildClienteVehiclesPaginator`.
//
// Gate: só renderiza quando props.modules.oficinaauto_enabled === true (Show.tsx
// condiciona a aparição da tab).
//
// Multi-tenant Tier 0 (ADR 0093): backend já scope business_id, frontend trusta.

import { useState, useCallback, useMemo } from 'react';
import { router } from '@inertiajs/react';
import { Car, Search } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import StatusBadge from '@/Components/shared/StatusBadge';

export interface VehicleItem {
  id: number;
  plate: string;
  secondary_plate?: string | null;
  chassis?: string | null;
  manufacture_year?: number | null;
  model_year?: number | null;
  renavam?: string | null;
  vehicle_type?: string;
  current_status?: string;
  color?: string | null;
  fuel_type?: string | null;
  mileage_at_entry?: number | null;
  notes?: string | null;
}

export interface VehiclesPaginator {
  data: VehicleItem[];
  total: number;
  current_page: number;
  last_page: number;
  from: number | null;
  to: number | null;
}

export interface VehiclesTabProps {
  contactId: number;
  vehicles?: VehiclesPaginator | null;
  /** URL base pra Inertia partial reload (default `/contacts/{id}`). */
  endpoint?: string;
}

// Status labels delegados pro StatusBadge canon (kind='vehicle').
// Ver: resources/js/Components/shared/StatusBadge.tsx mapping 'vehicle'.

const TYPE_LABELS: Record<string, string> = {
  car: 'Carro',
  truck: 'Caminhão',
  caminhao_basculante: 'Caminhão basculante',
  motorcycle: 'Moto',
  bus: 'Ônibus',
  van: 'Van',
  trailer: 'Reboque',
  cacamba: 'Caçamba',
};

const FUEL_LABELS: Record<string, string> = {
  gasoline: 'Gasolina',
  ethanol: 'Etanol',
  flex: 'Flex',
  diesel: 'Diesel',
  electric: 'Elétrico',
  hybrid: 'Híbrido',
  cng: 'GNV',
};

export default function VehiclesTab(props: VehiclesTabProps) {
  const { contactId, vehicles } = props;

  const [query, setQuery] = useState<string>('');

  // Debounce manual via timeout (mesmo pattern de SalesTab).
  const reloadWithFilters = useCallback(
    (q: string, page: number) => {
      router.reload({
        only: ['vehicles'],
        data: { vehicles_q: q, vehicles_page: page, tab: 'vehicles' },
      });
    },
    []
  );

  // contactId apenas pra label/log futuro — silencia TS unused.
  void contactId;

  const handleSearchChange = (value: string) => {
    setQuery(value);
    // 300ms debounce.
    if ((window as unknown as { __vehiclesSearchTimeout?: number }).__vehiclesSearchTimeout) {
      window.clearTimeout((window as unknown as { __vehiclesSearchTimeout?: number }).__vehiclesSearchTimeout);
    }
    (window as unknown as { __vehiclesSearchTimeout?: number }).__vehiclesSearchTimeout = window.setTimeout(() => {
      reloadWithFilters(value.trim(), 1);
    }, 300);
  };

  const items = useMemo(() => vehicles?.data ?? [], [vehicles]);

  if (!vehicles) {
    return (
      <div className="p-8 text-center text-xs text-muted-foreground" data-testid="vehicles-tab-skeleton">
        Carregando veículos…
      </div>
    );
  }

  return (
    <div className="p-5">
      <div className="mb-4 flex items-center justify-between gap-4">
        <h3 className="text-sm font-semibold text-foreground inline-flex items-center gap-2">
          <Car size={16} className="text-muted-foreground" />
          Veículos do cliente
          <span className="text-xs font-normal text-muted-foreground">
            ({vehicles.total} {vehicles.total === 1 ? 'veículo' : 'veículos'})
          </span>
        </h3>
        <div className="relative w-64">
          <Search size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <Input
            type="search"
            placeholder="Buscar por placa, chassi…"
            value={query}
            onChange={(e) => handleSearchChange(e.target.value)}
            className="pl-8 h-9 text-sm"
          />
        </div>
      </div>

      {items.length === 0 ? (
        <div className="rounded-md border border-dashed border-border bg-muted/20 p-8 text-center text-sm text-muted-foreground">
          <Car size={20} className="mx-auto mb-2 text-muted-foreground/50" />
          {query
            ? `Nenhum veículo encontrado pra "${query}".`
            : 'Nenhum veículo cadastrado pra este cliente.'}
        </div>
      ) : (
        <div className="overflow-x-auto rounded-md border border-border">
          <table className="w-full text-sm">
            <thead className="bg-muted/40">
              <tr className="text-xs text-muted-foreground">
                <th className="px-3 py-2 text-left font-medium">Placa</th>
                <th className="px-3 py-2 text-left font-medium">Tipo</th>
                <th className="px-3 py-2 text-left font-medium">Ano</th>
                <th className="px-3 py-2 text-left font-medium">Chassi</th>
                <th className="px-3 py-2 text-left font-medium">Combustível</th>
                <th className="px-3 py-2 text-left font-medium">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {items.map((v) => {
                const typeLabel = TYPE_LABELS[v.vehicle_type ?? ''] ?? (v.vehicle_type ?? '—');
                const fuelLabel = v.fuel_type ? (FUEL_LABELS[v.fuel_type] ?? v.fuel_type) : '—';
                const yearLabel = [v.manufacture_year, v.model_year].filter(Boolean).join('/') || '—';

                return (
                  <tr key={v.id} className="hover:bg-muted/20">
                    <td className="px-3 py-2 font-mono font-medium">
                      {v.plate}
                      {v.secondary_plate && (
                        <span className="ml-2 text-xs text-muted-foreground">
                          {v.secondary_plate}
                        </span>
                      )}
                    </td>
                    <td className="px-3 py-2 text-foreground">{typeLabel}</td>
                    <td className="px-3 py-2 text-muted-foreground">{yearLabel}</td>
                    <td className="px-3 py-2 font-mono text-xs text-muted-foreground">{v.chassis ?? '—'}</td>
                    <td className="px-3 py-2 text-muted-foreground">{fuelLabel}</td>
                    <td className="px-3 py-2">
                      <StatusBadge kind="vehicle" value={v.current_status ?? ''} />
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {vehicles.last_page > 1 && (
        <div className="mt-4 flex items-center justify-between text-xs text-muted-foreground">
          <span>
            Mostrando {vehicles.from ?? 0}–{vehicles.to ?? 0} de {vehicles.total}
          </span>
          <div className="flex items-center gap-2">
            <button
              type="button"
              disabled={vehicles.current_page <= 1}
              onClick={() => reloadWithFilters(query.trim(), vehicles.current_page - 1)}
              className="rounded-md border border-border bg-background px-3 py-1.5 text-xs font-medium disabled:opacity-40 disabled:cursor-not-allowed hover:bg-muted/40"
            >
              Anterior
            </button>
            <span>
              {vehicles.current_page} / {vehicles.last_page}
            </span>
            <button
              type="button"
              disabled={vehicles.current_page >= vehicles.last_page}
              onClick={() => reloadWithFilters(query.trim(), vehicles.current_page + 1)}
              className="rounded-md border border-border bg-background px-3 py-1.5 text-xs font-medium disabled:opacity-40 disabled:cursor-not-allowed hover:bg-muted/40"
            >
              Próxima
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

// StatusBadge helper local removido — substitído por <StatusBadge kind="vehicle"> canon
// (resources/js/Components/shared/StatusBadge.tsx). PR D 2026-05-26 atende UI Lint ratchet.
