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

// Mapeia enum DB pra label PT-BR — espelha labels do OficinaAuto canon.
const STATUS_LABELS: Record<string, { text: string; tone: 'emerald' | 'blue' | 'amber' | 'rose' | 'slate' }> = {
  active: { text: 'Ativo', tone: 'emerald' },
  in_service: { text: 'Em serviço', tone: 'blue' },
  awaiting_parts: { text: 'Aguardando peças', tone: 'amber' },
  inactive: { text: 'Inativo', tone: 'slate' },
  written_off: { text: 'Baixado', tone: 'rose' },
};

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
                const status = STATUS_LABELS[v.current_status ?? ''] ?? { text: v.current_status ?? '—', tone: 'slate' as const };
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
                      <StatusBadge tone={status.tone}>{status.text}</StatusBadge>
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

function StatusBadge({ tone, children }: { tone: 'emerald' | 'blue' | 'amber' | 'rose' | 'slate'; children: React.ReactNode }) {
  const classes: Record<typeof tone, string> = {
    emerald: 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300',
    blue: 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-950/30 dark:text-blue-300',
    amber: 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-300',
    rose: 'border-rose-300 bg-rose-50 text-rose-700 dark:border-rose-700 dark:bg-rose-950/30 dark:text-rose-300',
    slate: 'border-slate-300 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-950/30 dark:text-slate-300',
  };
  return (
    <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] uppercase tracking-wider ${classes[tone]}`}>
      {children}
    </span>
  );
}
