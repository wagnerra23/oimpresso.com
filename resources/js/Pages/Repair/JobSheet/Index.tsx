// @memcofre tela=/repair/job-sheet module=Repair
// Sprint 2.5 / MWART-0002 — port Job Sheet Repair Blade → Inertia/React.
// Score-up 2026-05-31 (board 52 "stub: placeholder DataTables + cards só contam"):
//   placeholder → tabela real. Mantém o contrato REAL do controller
//   (filters/flags/datatable_url) e busca a lista no MESMO endpoint DataTables
//   AJAX (request()->ajax() em JobSheetController@index) — sem inventar prop.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Link } from '@inertiajs/react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Button } from '@/Components/ui/button';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

interface DropdownOption {
  [key: string]: string;
}

interface PageProps {
  filters: {
    business_locations: DropdownOption;
    customers: DropdownOption;
    status_dropdown: DropdownOption;
    service_staffs: DropdownOption;
  };
  flags: {
    is_user_service_staff: boolean;
    show_serial_no: boolean;
    enable_brand_in_job_sheet: boolean;
  };
  datatable_url: string;
}

// Shape de cada linha do endpoint DataTables (subset do SELECT do controller).
interface JobSheetRow {
  id: number;
  job_sheet_no: string | null;
  customer: string | null;
  device: string | null;
  device_model: string | null;
  brand: string | null;
  status: string | null;
  status_color: string | null;
  technecian: string | null;
  location: string | null;
  created_at: string | null;
}

const ALL = '__all__';

export default function JobSheetIndex({ filters, flags, datatable_url }: PageProps) {
  const [rows, setRows] = useState<JobSheetRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [loc, setLoc] = useState<string>(ALL);
  const [status, setStatus] = useState<string>(ALL);
  const [customer, setCustomer] = useState<string>(ALL);

  useEffect(() => {
    let alive = true;
    setLoading(true);
    setError(false);
    const params = new URLSearchParams({ draw: '1', start: '0', length: '200' });
    if (loc !== ALL) params.set('location_id', loc);
    if (status !== ALL) params.set('status_id', status);
    if (customer !== ALL) params.set('contact_id', customer);

    fetch(`${datatable_url}?${params.toString()}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
      .then((json) => {
        if (!alive) return;
        setRows(Array.isArray(json?.data) ? json.data : []);
      })
      .catch(() => alive && setError(true))
      .finally(() => alive && setLoading(false));

    return () => {
      alive = false;
    };
  }, [datatable_url, loc, status, customer]);

  const hasFilters = loc !== ALL || status !== ALL || customer !== ALL;
  const locOpts = useMemo(() => Object.entries(filters.business_locations), [filters]);
  const statusOpts = useMemo(() => Object.entries(filters.status_dropdown), [filters]);
  const customerOpts = useMemo(() => Object.entries(filters.customers), [filters]);

  return (
    <div className="container mx-auto space-y-5 p-4">
      <PageHeader
        icon="clipboard-list"
        title="Ordens de serviço"
        description="Gestão de OS de reparo por status, cliente, equipe e local."
        action={
          <Button asChild>
            <Link href="/repair/job-sheet/create">Nova OS</Link>
          </Button>
        }
      />

      {/* Filtros — usam os dropdowns reais que o controller já manda */}
      <div className="flex flex-wrap items-center gap-2 rounded-lg border border-border bg-card p-3">
        <FilterSelect label="Local" value={loc} onChange={setLoc} options={locOpts} allLabel="Todos os locais" />
        <FilterSelect label="Status" value={status} onChange={setStatus} options={statusOpts} allLabel="Todos os status" />
        <FilterSelect label="Cliente" value={customer} onChange={setCustomer} options={customerOpts} allLabel="Todos os clientes" />
        {hasFilters && (
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              setLoc(ALL);
              setStatus(ALL);
              setCustomer(ALL);
            }}
          >
            Limpar
          </Button>
        )}
        {flags.is_user_service_staff && (
          <span className="ml-auto text-xs text-muted-foreground">
            Você vê apenas as OS atribuídas a você.
          </span>
        )}
      </div>

      <div className="overflow-hidden rounded-lg border border-border">
        {loading ? (
          <div className="space-y-2 p-4" aria-busy="true">
            {Array.from({ length: 6 }).map((_, i) => (
              <div key={i} className="h-9 animate-pulse rounded bg-muted/60" />
            ))}
          </div>
        ) : error ? (
          <EmptyState
            icon="alert-circle"
            variant="error"
            title="Não foi possível carregar as OS"
            description="Tente novamente em instantes."
          />
        ) : rows.length === 0 ? (
          <EmptyState
            icon="wrench"
            variant={hasFilters ? 'search' : 'default'}
            title={hasFilters ? 'Nenhuma OS no filtro' : 'Nenhuma ordem de serviço'}
            description={
              hasFilters
                ? 'Ajuste os filtros para ver mais resultados.'
                : 'As OS de reparo criadas aparecerão aqui.'
            }
          />
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
              <tr>
                <th className="px-4 py-3">OS</th>
                <th className="px-4 py-3">Cliente</th>
                <th className="px-4 py-3">Aparelho</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3">Técnico</th>
                <th className="px-4 py-3">Local</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => {
                const device = r.device || [r.brand, r.device_model].filter(Boolean).join(' ') || '—';
                return (
                  <tr key={r.id} className="border-t border-border hover:bg-muted/30">
                    <td className="px-4 py-3 font-mono text-xs">
                      <Link href={`/repair/job-sheet/${r.id}`} className="text-primary hover:underline">
                        {r.job_sheet_no ?? `#${r.id}`}
                      </Link>
                    </td>
                    <td className="px-4 py-3">{r.customer ?? '—'}</td>
                    <td className="px-4 py-3">{device}</td>
                    <td className="px-4 py-3">
                      {r.status ? (
                        <span className="inline-flex items-center gap-1.5">
                          <span className="h-1.5 w-1.5 rounded-full bg-primary" aria-hidden />
                          {r.status}
                        </span>
                      ) : (
                        '—'
                      )}
                    </td>
                    <td className="px-4 py-3 text-muted-foreground">{r.technecian?.trim() || '—'}</td>
                    <td className="px-4 py-3 text-muted-foreground">{r.location ?? '—'}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </div>

      {!loading && !error && rows.length > 0 && (
        <p className="text-xs text-muted-foreground">{rows.length} OS exibida(s).</p>
      )}
    </div>
  );
}

function FilterSelect({
  label,
  value,
  onChange,
  options,
  allLabel,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  options: Array<[string, string]>;
  allLabel: string;
}) {
  if (options.length === 0) return null;
  return (
    <Select value={value} onValueChange={onChange}>
      <SelectTrigger className="h-9 w-[180px]" aria-label={label}>
        <SelectValue placeholder={allLabel} />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={ALL}>{allLabel}</SelectItem>
        {options.map(([id, name]) => (
          <SelectItem key={id} value={id}>
            {name}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}

JobSheetIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
