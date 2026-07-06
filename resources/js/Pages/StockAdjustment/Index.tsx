// @memcofre
//   tela: /stock-adjustments
//   module: Inventory / StockAdjustment (raiz UltimatePOS)
//   tipo: LIST (MWART Wave2 B5)
//   rules: R-ADJ-001 (Tier 0), R-ADJ-002 (type), R-ADJ-003 (recovered<=total), R-ADJ-004 (ownership)
//   adrs: 0104, 0093, 0114, 0149

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { router } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Plus, Eye, Trash2, FileText, PackageMinus } from 'lucide-react';

// ---------- Tipos ----------

type AdjustmentType = 'normal' | 'abnormal';

interface AdjustmentRow {
  id: number;
  ref_no: string;
  transaction_date: string;
  location_name: string;
  adjustment_type: AdjustmentType;
  final_total: number;
  total_amount_recovered: number;
  additional_notes: string | null;
  added_by: string;
}

interface Filters {
  location_id: string;
  start_date: string;
  end_date: string;
}

interface Permissions {
  view: boolean;
  create: boolean;
  delete: boolean;
  view_purchase_price: boolean;
}

interface Option {
  id: number | string;
  label: string;
}

interface Props {
  rows: AdjustmentRow[];
  filters: Filters;
  business_locations: Option[];
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

const TYPE_PILL: Record<AdjustmentType, string> = {
  normal: 'bg-stone-50 text-stone-700 border-stone-200',
  abnormal: 'bg-destructive-soft text-destructive-fg border-destructive/20',
};

const TYPE_LABEL: Record<AdjustmentType, string> = {
  normal: 'Normal',
  abnormal: 'Anormal (perda)',
};

// ---------- Componente ----------

function StockAdjustmentIndex({ rows, filters, business_locations, permissions }: Props) {
  const [busca, setBusca] = useState('');

  const aplicar = (patch: Partial<Filters>) => {
    // D-14: partial reload — só re-busca o que muda com filtro.
    // business_locations é closure no controller (por business) — pula no partial.
    router.get('/stock-adjustments', { ...filters, ...patch }, {
      preserveState: true, preserveScroll: true, replace: true,
      only: ['rows', 'filters'],
    });
  };

  const rowsFiltradas = useMemo(() => {
    if (!busca) return rows;
    const q = busca.toLowerCase();
    return rows.filter(r =>
      r.ref_no.toLowerCase().includes(q) ||
      r.location_name.toLowerCase().includes(q) ||
      (r.additional_notes ?? '').toLowerCase().includes(q)
    );
  }, [rows, busca]);

  const onDelete = (id: number) => {
    if (!confirm('Confirma exclusão deste ajuste de estoque?')) return;
    router.delete(`/stock-adjustments/${id}`, { preserveScroll: true });
  };

  return (
    <>
      <PageHeader
        icon="package"
        title="Ajustes de estoque"
        description={`${rowsFiltradas.length} ${rowsFiltradas.length === 1 ? 'ajuste' : 'ajustes'}`}
        action={permissions.create && (
          <Button size="sm" onClick={() => router.visit('/stock-adjustments/create')}>
            <Plus className="h-4 w-4 mr-1" /> Novo ajuste
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
            placeholder="Buscar ref / filial / motivo…"
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
            className="h-8 w-[260px] text-[12.5px] ml-auto"
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
                <th className="px-2 py-2 text-left font-medium">Filial</th>
                <th className="px-2 py-2 text-left font-medium">Tipo</th>
                <th className="px-2 py-2 text-right font-medium">Total ajustado</th>
                <th className="px-2 py-2 text-right font-medium">Recuperado</th>
                <th className="px-2 py-2 text-left font-medium">Autor</th>
                <th className="pl-2 pr-4 py-2 w-[100px] text-right font-medium">Ações</th>
              </tr>
            </thead>
            <tbody>
              {rowsFiltradas.map(r => (
                <tr key={r.id} className="h-11 text-[13px] border-b border-stone-100 hover:bg-stone-50/60">
                  <td className="pl-4 pr-2 tabular-nums">{formatDateTime(r.transaction_date)}</td>
                  <td className="px-2 font-medium text-stone-900">{r.ref_no}</td>
                  <td className="px-2 text-stone-700">{r.location_name}</td>
                  <td className="px-2">
                    <span className={`inline-flex items-center px-1.5 py-0.5 rounded border text-[11px] font-medium ${TYPE_PILL[r.adjustment_type] ?? TYPE_PILL.normal}`}>
                      {TYPE_LABEL[r.adjustment_type] ?? r.adjustment_type}
                    </span>
                  </td>
                  <td className="px-2 text-right tabular-nums font-medium">
                    {permissions.view_purchase_price ? brl(r.final_total) : '—'}
                  </td>
                  <td className="px-2 text-right tabular-nums">
                    <span className={r.total_amount_recovered > 0 ? 'text-success-fg' : 'text-stone-400'}>
                      {permissions.view_purchase_price ? brl(r.total_amount_recovered) : '—'}
                    </span>
                  </td>
                  <td className="px-2 text-stone-600 text-[12px]">{r.added_by || '—'}</td>
                  <td className="pl-2 pr-4 text-right">
                    <div className="inline-flex gap-1">
                      <Button size="sm" variant="ghost" className="h-7 w-7 p-0" onClick={() => router.visit(`/stock-adjustments/${r.id}`)} title="Ver">
                        <Eye className="h-3.5 w-3.5" />
                      </Button>
                      {permissions.delete && (
                        <Button size="sm" variant="ghost" className="h-7 w-7 p-0 text-destructive" onClick={() => onDelete(r.id)} title="Excluir">
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
              {rowsFiltradas.length === 0 && (
                <tr>
                  <td colSpan={8} className="py-16">
                    <div className="flex flex-col items-center gap-3 text-center">
                      {busca || filters.location_id ? (
                        <>
                          <FileText className="h-10 w-10 text-stone-300" />
                          <div className="text-sm text-stone-600">Nenhum ajuste com filtros atuais.</div>
                        </>
                      ) : (
                        <>
                          <PackageMinus className="h-10 w-10 text-stone-300" />
                          <div className="text-sm text-stone-600">Nenhum ajuste de estoque registrado.</div>
                          {permissions.create && (
                            <Button size="sm" onClick={() => router.visit('/stock-adjustments/create')}>
                              + Registrar primeiro ajuste
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

StockAdjustmentIndex.layout = (page: ReactNode) => (
  <AppShellV2
    title="Ajustes de estoque"
    breadcrumbItems={[{ label: 'Estoque', href: '#' }, { label: 'Ajustes' }]}
  >
    {page}
  </AppShellV2>
);

export default StockAdjustmentIndex;
