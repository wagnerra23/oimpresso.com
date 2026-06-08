// @memcofre
//   tela: /stock-transfers
//   module: Inventory / StockTransfer (raiz UltimatePOS)
//   tipo: LIST (MWART Wave2 B5)
//   rules: R-XFER-001 (Tier 0), R-XFER-002 (ownership), R-XFER-003 (status), R-XFER-004 (origem ≠ destino)
//   adrs: 0104, 0093, 0114, 0149
//
// Runbook: memory/requisitos/Inventory/RUNBOOK-stock-transfer-index.md
// Charter: ./Index.charter.md

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { router } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Plus, Eye, Trash2, Printer, ArrowRight, Truck, FileText } from 'lucide-react';

// ---------- Tipos ----------

type TransferStatus = 'pending' | 'in_transit' | 'completed' | 'final';

interface TransferRow {
  id: number;
  ref_no: string;
  transaction_date: string;
  location_from: string;
  location_to: string;
  status: TransferStatus;
  shipping_charges: number;
  final_total: number;
  additional_notes: string | null;
}

interface Filters {
  location_id: string;
  status: string;
  start_date: string;
  end_date: string;
}

interface Permissions {
  view: boolean;
  create: boolean;
  update: boolean;
  delete: boolean;
  view_purchase_price: boolean;
}

interface Option {
  id: number | string;
  label: string;
}

interface Props {
  rows: TransferRow[];
  filters: Filters;
  business_locations: Option[];
  statuses: Record<string, string>;
  permissions: Permissions;
}

// ---------- Helpers ----------

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

const formatDateTime = (v: string) => {
  if (!v) return '—';
  const d = new Date(v.replace(' ', 'T'));
  return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
};

const STATUS_PILL: Record<TransferStatus, string> = {
  pending: 'bg-rose-50 text-rose-700 border-rose-200',
  in_transit: 'bg-amber-50 text-amber-800 border-amber-200',
  completed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  final: 'bg-emerald-50 text-emerald-700 border-emerald-200',
};

const STATUS_LABEL_DEFAULT: Record<TransferStatus, string> = {
  pending: 'Pendente',
  in_transit: 'Em trânsito',
  completed: 'Concluída',
  final: 'Concluída',
};

// ---------- Componente ----------

function StockTransferIndex({ rows, filters, business_locations, statuses, permissions }: Props) {
  const [busca, setBusca] = useState('');

  const aplicar = (patch: Partial<Filters>) => {
    router.get('/stock-transfers', { ...filters, ...patch }, {
      preserveState: true, preserveScroll: true, replace: true,
    });
  };

  const rowsFiltradas = useMemo(() => {
    if (!busca) return rows;
    const q = busca.toLowerCase();
    return rows.filter(r =>
      r.ref_no.toLowerCase().includes(q) ||
      r.location_from.toLowerCase().includes(q) ||
      r.location_to.toLowerCase().includes(q)
    );
  }, [rows, busca]);

  const labelStatus = (s: TransferStatus) => statuses[s] ?? STATUS_LABEL_DEFAULT[s];

  const onDelete = (id: number) => {
    if (!confirm('Confirma exclusão desta transferência?')) return;
    router.delete(`/stock-transfers/${id}`, { preserveScroll: true });
  };

  return (
    <>
      <PageHeader
        icon="truck"
        title="Transferências de estoque"
        description={`${rowsFiltradas.length} ${rowsFiltradas.length === 1 ? 'transferência' : 'transferências'}`}
        action={permissions.create && (
          <Button size="sm" onClick={() => router.visit('/stock-transfers/create')}>
            <Plus className="h-4 w-4 mr-1" /> Nova transferência
          </Button>
        )}
      />

      <Card className="mt-4 sticky top-14 z-10">
        <CardContent className="p-3 flex flex-wrap items-center gap-2">
          <select
            className="h-8 px-2 rounded-md border border-stone-200 text-[12.5px]"
            value={filters.location_id}
            onChange={(e) => aplicar({ location_id: e.target.value })}
          >
            <option value="">Todas as filiais</option>
            {business_locations.map(l => <option key={l.id} value={l.id}>{l.label}</option>)}
          </select>

          <select
            className="h-8 px-2 rounded-md border border-stone-200 text-[12.5px]"
            value={filters.status}
            onChange={(e) => aplicar({ status: e.target.value })}
          >
            <option value="">Todos os status</option>
            {Object.entries(statuses).map(([key, label]) => (
              <option key={key} value={key}>{label}</option>
            ))}
          </select>

          <Input
            type="date"
            value={filters.start_date}
            onChange={(e) => aplicar({ start_date: e.target.value })}
            className="h-8 w-[140px] text-[12.5px]"
            placeholder="De"
          />
          <Input
            type="date"
            value={filters.end_date}
            onChange={(e) => aplicar({ end_date: e.target.value })}
            className="h-8 w-[140px] text-[12.5px]"
            placeholder="Até"
          />

          <Input
            placeholder="Buscar ref / filial…"
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
            className="h-8 w-[220px] text-[12.5px] ml-auto"
          />
        </CardContent>
      </Card>

      <Card className="mt-3">
        <CardContent className="p-0">
          <table className="w-full border-collapse">
            <thead>
              <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/40">
                <th className="pl-4 pr-2 py-2 text-left font-medium">Data</th>
                <th className="px-2 py-2 text-left font-medium">Ref. Nº</th>
                <th className="px-2 py-2 text-left font-medium">Origem → Destino</th>
                <th className="px-2 py-2 text-left font-medium">Status</th>
                <th className="px-2 py-2 text-right font-medium">Frete</th>
                <th className="px-2 py-2 text-right font-medium">Total</th>
                <th className="pl-2 pr-4 py-2 w-[140px] text-right font-medium">Ações</th>
              </tr>
            </thead>
            <tbody>
              {rowsFiltradas.map(r => (
                <tr key={r.id} className="h-11 text-[13px] border-b border-stone-100 hover:bg-stone-50/60">
                  <td className="pl-4 pr-2 tabular-nums">{formatDateTime(r.transaction_date)}</td>
                  <td className="px-2 font-medium text-stone-900">{r.ref_no}</td>
                  <td className="px-2 text-stone-700">
                    <span className="text-[12px] text-stone-600">{r.location_from}</span>
                    <ArrowRight className="inline h-3 w-3 mx-1 text-stone-400" />
                    <span className="text-[12px]">{r.location_to}</span>
                  </td>
                  <td className="px-2">
                    <span className={`inline-flex items-center px-1.5 py-0.5 rounded border text-[11px] font-medium ${STATUS_PILL[r.status] ?? STATUS_PILL.pending}`}>
                      {labelStatus(r.status)}
                    </span>
                  </td>
                  <td className="px-2 text-right tabular-nums text-stone-600">
                    {permissions.view_purchase_price ? brl(r.shipping_charges) : '—'}
                  </td>
                  <td className="px-2 text-right tabular-nums font-medium">
                    {permissions.view_purchase_price ? brl(r.final_total) : '—'}
                  </td>
                  <td className="pl-2 pr-4 text-right">
                    <div className="inline-flex gap-1">
                      <Button size="sm" variant="ghost" className="h-7 w-7 p-0" onClick={() => router.visit(`/stock-transfers/${r.id}`)} title="Ver">
                        <Eye className="h-3.5 w-3.5" />
                      </Button>
                      <Button size="sm" variant="ghost" className="h-7 w-7 p-0" onClick={() => window.open(`/stock-transfers/${r.id}/print`, '_blank')} title="Imprimir">
                        <Printer className="h-3.5 w-3.5" />
                      </Button>
                      {permissions.delete && (
                        <Button size="sm" variant="ghost" className="h-7 w-7 p-0 text-rose-600" onClick={() => onDelete(r.id)} title="Excluir">
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
              {rowsFiltradas.length === 0 && (
                <tr>
                  <td colSpan={7} className="py-16">
                    <div className="flex flex-col items-center gap-3 text-center">
                      {busca || filters.location_id || filters.status ? (
                        <>
                          <FileText className="h-10 w-10 text-stone-300" />
                          <div className="text-sm text-stone-600">Nenhuma transferência com os filtros atuais.</div>
                        </>
                      ) : (
                        <>
                          <Truck className="h-10 w-10 text-stone-300" />
                          <div className="text-sm text-stone-600">Nenhuma transferência registrada.</div>
                          {permissions.create && (
                            <Button size="sm" onClick={() => router.visit('/stock-transfers/create')}>
                              + Registrar primeira transferência
                            </Button>
                          )}
                        </>
                      )}
                    </div>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </CardContent>
      </Card>
    </>
  );
}

StockTransferIndex.layout = (page: ReactNode) => (
  <AppShellV2
    title="Transferências de estoque"
    breadcrumbItems={[{ label: 'Estoque', href: '#' }, { label: 'Transferências' }]}
  >
    {page}
  </AppShellV2>
);

export default StockTransferIndex;
