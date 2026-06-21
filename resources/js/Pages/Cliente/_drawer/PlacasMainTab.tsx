// Wagner 2026-05-27 -- Tab principal "Placas" do drawer 760 (read-only).
//
// Iteracao 1 (PR #1776) virou sub-tab dentro de OSs -- porem usuario nao
// pensava "vou em OSs ver Placas". Iteracao 2 (este PR) promove pra TAB
// PRINCIPAL acessado via BOTAO HEADER `[🚛 N placas]` ao lado de
// "Imprimir ficha"/"Falar com Copiloto" (Proposta F).
//
// Self-fetch via fetch /cliente/{id}/veiculos (drawer parent nao carrega
// vehicles no payload). Visibility: oficinaAutoEnabled gate ModuleUtil.
//
// Refs: ADR 0179 (drawer 760) · ADR 0137 (vehicles schema) · session 2026-05-27.

import { useCallback, useEffect, useState } from 'react';
import { Car, Search, Loader2 } from 'lucide-react';
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

interface VehiclesPayload {
  data: VehicleItem[];
  total: number;
  current_page: number;
  last_page: number;
  from: number | null;
  to: number | null;
}

export interface PlacasMainTabProps {
  contactId: number;
}

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

export default function PlacasMainTab({ contactId }: PlacasMainTabProps) {
  const [data, setData] = useState<VehiclesPayload | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [query, setQuery] = useState<string>('');
  const [page, setPage] = useState<number>(1);

  // ── Fetch ────────────────────────────────────────────────────────────
  const fetchVeiculos = useCallback(
    async (q: string, p: number) => {
      setLoading(true);
      setError(null);
      try {
        const url = new URL(`/cliente/${contactId}/veiculos`, window.location.origin);
        if (q.trim() !== '') url.searchParams.set('q', q.trim());
        if (p > 1) url.searchParams.set('page', String(p));
        const r = await fetch(url.toString(), {
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
        });
        if (!r.ok) {
          if (r.status === 403) {
            setError('Sem permissão pra ver veículos deste cliente.');
          } else if (r.status === 404) {
            setError('Cliente não encontrado.');
          } else {
            setError(`Erro ${r.status} ao carregar veículos.`);
          }
          return;
        }
        const j: VehiclesPayload = await r.json();
        setData(j);
      } catch {
        setError('Falha de rede. Tente novamente.');
      } finally {
        setLoading(false);
      }
    },
    [contactId]
  );

  // Initial fetch quando contactId muda.
  useEffect(() => {
    setQuery('');
    setPage(1);
    fetchVeiculos('', 1);
  }, [contactId, fetchVeiculos]);

  // Search debounce 300ms.
  const handleSearchChange = (value: string) => {
    setQuery(value);
    if ((window as unknown as { __placasSearchTimeout?: number }).__placasSearchTimeout) {
      window.clearTimeout((window as unknown as { __placasSearchTimeout?: number }).__placasSearchTimeout);
    }
    (window as unknown as { __placasSearchTimeout?: number }).__placasSearchTimeout = window.setTimeout(() => {
      setPage(1);
      fetchVeiculos(value, 1);
    }, 300);
  };

  const handlePageChange = (newPage: number) => {
    setPage(newPage);
    fetchVeiculos(query, newPage);
  };

  // ── Render ───────────────────────────────────────────────────────────
  if (loading && !data) {
    return (
      <div
        className="p-8 text-center text-xs text-muted-foreground inline-flex items-center justify-center w-full gap-2"
        data-testid="placas-sub-tab-skeleton"
      >
        <Loader2 size={14} className="animate-spin" aria-hidden />
        Carregando placas…
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-8 text-center text-sm text-destructive" role="alert">
        {error}
      </div>
    );
  }

  if (!data) return null;

  const items = data.data;

  return (
    <div className="p-5">
      <div className="mb-4 flex items-center justify-between gap-4">
        <h3 className="text-sm font-semibold text-foreground inline-flex items-center gap-2">
          <Car size={16} className="text-muted-foreground" />
          Placas do cliente
          <span className="text-xs font-normal text-muted-foreground">
            ({data.total} {data.total === 1 ? 'veículo' : 'veículos'})
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

      {data.last_page > 1 && (
        <div className="mt-4 flex items-center justify-between text-xs text-muted-foreground">
          <span>
            Página {data.current_page} de {data.last_page}
            {data.from !== null && data.to !== null && (
              <span className="ml-2">
                · mostrando {data.from}–{data.to} de {data.total}
              </span>
            )}
          </span>
          <div className="flex gap-2">
            <button
              type="button"
              disabled={page <= 1 || loading}
              onClick={() => handlePageChange(page - 1)}
              className="rounded border border-input bg-background px-3 py-1 text-xs disabled:opacity-50"
            >
              Anterior
            </button>
            <button
              type="button"
              disabled={page >= data.last_page || loading}
              onClick={() => handlePageChange(page + 1)}
              className="rounded border border-input bg-background px-3 py-1 text-xs disabled:opacity-50"
            >
              Próxima
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
