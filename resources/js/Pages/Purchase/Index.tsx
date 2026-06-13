// @memcofre
//   tela: /purchases
//   module: Purchase (raiz UltimatePOS, não Modules/)
//   tipo: LIST (MWART migracao-blade-react piloto)
//   stories: piloto skill migracao-blade-react v0.1.0
//   rules: R-PUR-001 (multi-tenant business_id), R-PUR-002 (permitted_locations)
//   adrs: 0141 (skill migracao), 0104 (MWART), 0093 (Tier 0), 0110 (Cockpit V2)
//
// Origem: protótipo Cowork "Compras" (prototipo-ui/prototipos/compras/visual-source.html).
// Snapshot paridade: memory/mwart-inventory/purchase/index.snapshot.md
// Persona: Wagner/Maiara — compras escritório, lista densa, ações rápidas inline.
// Tokens: stone (neutro), emerald (paid/received), rose (overdue/cancel), amber (partial/pending), accent (status badges).

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { router } from '@inertiajs/react';
import { useState, useMemo, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Plus, Eye, Edit, Trash2, Printer, RotateCcw, FileText } from 'lucide-react';

// ---------- Tipos ----------

type PurchaseStatus = 'received' | 'pending' | 'ordered';
type PaymentStatus = 'paid' | 'due' | 'partial' | 'overdue';

interface PurchaseRow {
  id: number;
  ref_no: string;
  transaction_date: string; // ISO yyyy-mm-dd HH:mm:ss
  location_name: string;
  supplier_name: string;
  supplier_business_name: string | null;
  status: PurchaseStatus;
  payment_status: PaymentStatus;
  final_total: number;
  amount_paid: number;
  payment_due: number;
  added_by_name: string;
  return_exists: boolean;
  return_due: number;
  document: string | null;
}

interface Filters {
  location_id: string;
  supplier_id: string;
  status: string;
  payment_status: string;
  start_date: string;
  end_date: string;
}

interface Option {
  id: number | string;
  label: string;
}

interface Permissions {
  view: boolean;
  create: boolean;
  update: boolean;
  delete: boolean;
  update_status: boolean;
  payments: boolean;
}

interface Props {
  rows: PurchaseRow[];
  filters: Filters;
  business_locations: Option[];
  suppliers: Option[];
  order_statuses: Option[];
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

const STATUS_PILL: Record<PurchaseStatus, string> = {
  received: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  pending: 'bg-amber-50 text-amber-800 border-amber-200',
  ordered: 'bg-blue-50 text-blue-700 border-blue-200',
};

const STATUS_LABEL: Record<PurchaseStatus, string> = {
  received: 'Recebido',
  pending: 'Pendente',
  ordered: 'Solicitado',
};

const PAYMENT_PILL: Record<PaymentStatus, string> = {
  paid: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  due: 'bg-stone-50 text-stone-700 border-stone-200',
  partial: 'bg-amber-50 text-amber-800 border-amber-200',
  overdue: 'bg-rose-50 text-rose-700 border-rose-200',
};

const PAYMENT_LABEL: Record<PaymentStatus, string> = {
  paid: 'Pago',
  due: 'Em aberto',
  partial: 'Parcial',
  overdue: 'Vencido',
};

// ---------- Componentes ----------

function StatusPill({ s }: { s: PurchaseStatus }) {
  return (
    <span className={`inline-flex items-center px-1.5 py-0.5 rounded border text-[11px] font-medium ${STATUS_PILL[s]}`}>
      {STATUS_LABEL[s]}
    </span>
  );
}

function PaymentPill({ s }: { s: PaymentStatus }) {
  return (
    <span className={`inline-flex items-center px-1.5 py-0.5 rounded border text-[11px] font-medium ${PAYMENT_PILL[s]}`}>
      {PAYMENT_LABEL[s]}
    </span>
  );
}

// ---------- Página principal ----------

function PurchaseIndex({ rows, filters, business_locations, suppliers, order_statuses, permissions }: Props) {
  const [busca, setBusca] = useState('');

  const aplicar = (patch: Partial<Filters>) => {
    router.get('/purchases', { ...filters, ...patch }, {
      preserveState: true, preserveScroll: true, replace: true,
    });
  };

  const rowsFiltradas = useMemo(() => {
    if (!busca) return rows;
    const q = busca.toLowerCase();
    return rows.filter(r =>
      r.ref_no.toLowerCase().includes(q) ||
      r.supplier_name.toLowerCase().includes(q) ||
      (r.supplier_business_name ?? '').toLowerCase().includes(q)
    );
  }, [rows, busca]);

  const onView = (id: number) => router.visit(`/purchases/${id}`);
  const onEdit = (id: number) => router.visit(`/purchases/${id}/edit`);
  const onDelete = (id: number) => {
    if (!confirm('Confirma exclusão desta compra?')) return;
    router.delete(`/purchases/${id}`, { preserveScroll: true });
  };

  return (
    <>
      <PageHeader
        icon="shopping-cart"
        title="Compras"
        description={`${rowsFiltradas.length} ${rowsFiltradas.length === 1 ? 'compra' : 'compras'}`}
        action={permissions.create && (
          <Button size="sm" onClick={() => router.visit('/purchases/create')}>
            <Plus className="h-4 w-4 mr-1" /> Nova compra
          </Button>
        )}
      />

      {/* Filtros sticky */}
      <Card className="mt-4 sticky top-14 z-10">
        <CardContent className="p-3 flex flex-wrap items-center gap-2">
          <Select value={filters.location_id || '__none__'}
                  onValueChange={(v) => aplicar({ location_id: v === '__none__' ? '' : v })}>
            <SelectTrigger variant="shadcn" size="sm" aria-label="Filial" className="text-[12.5px]">
              <SelectValue placeholder="Todas as filiais" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__none__">Todas as filiais</SelectItem>
              {business_locations.map(l => <SelectItem key={l.id} value={String(l.id)}>{l.label}</SelectItem>)}
            </SelectContent>
          </Select>

          <Select value={filters.supplier_id || '__none__'}
                  onValueChange={(v) => aplicar({ supplier_id: v === '__none__' ? '' : v })}>
            <SelectTrigger variant="shadcn" size="sm" aria-label="Fornecedor" className="text-[12.5px]">
              <SelectValue placeholder="Todos os fornecedores" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__none__">Todos os fornecedores</SelectItem>
              {suppliers.map(s => <SelectItem key={s.id} value={String(s.id)}>{s.label}</SelectItem>)}
            </SelectContent>
          </Select>

          <Select value={filters.status || '__none__'}
                  onValueChange={(v) => aplicar({ status: v === '__none__' ? '' : v })}>
            <SelectTrigger variant="shadcn" size="sm" aria-label="Status" className="text-[12.5px]">
              <SelectValue placeholder="Todos os status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__none__">Todos os status</SelectItem>
              {order_statuses.map(s => <SelectItem key={s.id} value={String(s.id)}>{s.label}</SelectItem>)}
            </SelectContent>
          </Select>

          <Select value={filters.payment_status || '__none__'}
                  onValueChange={(v) => aplicar({ payment_status: v === '__none__' ? '' : v })}>
            <SelectTrigger variant="shadcn" size="sm" aria-label="Pagamento" className="text-[12.5px]">
              <SelectValue placeholder="Pagamento (todos)" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__none__">Pagamento (todos)</SelectItem>
              <SelectItem value="paid">Pago</SelectItem>
              <SelectItem value="due">Em aberto</SelectItem>
              <SelectItem value="partial">Parcial</SelectItem>
              <SelectItem value="overdue">Vencido</SelectItem>
            </SelectContent>
          </Select>

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
            placeholder="Buscar ref / fornecedor…"
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
            className="h-8 w-[220px] text-[12.5px] ml-auto"
          />
        </CardContent>
      </Card>

      {/* Tabela */}
      <Card className="mt-3">
        <CardContent className="p-0">
          <table className="w-full border-collapse">
            <thead>
              <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/40">
                <th className="pl-4 pr-2 py-2 text-left font-medium">Data</th>
                <th className="px-2 py-2 text-left font-medium">Ref. Nº</th>
                <th className="px-2 py-2 text-left font-medium">Filial</th>
                <th className="px-2 py-2 text-left font-medium">Fornecedor</th>
                <th className="px-2 py-2 text-left font-medium">Status</th>
                <th className="px-2 py-2 text-left font-medium">Pagamento</th>
                <th className="px-2 py-2 text-right font-medium">Total</th>
                <th className="px-2 py-2 text-right font-medium">A pagar</th>
                <th className="pl-2 pr-4 py-2 w-[140px] text-right font-medium">Ações</th>
              </tr>
            </thead>
            <tbody>
              {rowsFiltradas.map(r => (
                <tr key={r.id} className="h-11 text-[13px] border-b border-stone-100 hover:bg-stone-50/60">
                  <td className="pl-4 pr-2 tabular-nums">{formatDateTime(r.transaction_date)}</td>
                  <td className="px-2 font-medium text-stone-900">
                    {r.ref_no}
                    {r.return_exists && <span className="ml-1 text-[10px] text-rose-700">↶ devolução</span>}
                  </td>
                  <td className="px-2 text-stone-700">{r.location_name}</td>
                  <td className="px-2 text-stone-700">
                    {r.supplier_business_name && <span className="block text-[11px] text-stone-500">{r.supplier_business_name}</span>}
                    {r.supplier_name}
                  </td>
                  <td className="px-2"><StatusPill s={r.status} /></td>
                  <td className="px-2"><PaymentPill s={r.payment_status} /></td>
                  <td className="px-2 text-right tabular-nums font-medium">{brl(r.final_total)}</td>
                  <td className="px-2 text-right tabular-nums">
                    <span className={r.payment_due > 0 ? 'text-destructive-fg font-medium' : 'text-stone-400'}>
                      {brl(r.payment_due)}
                    </span>
                  </td>
                  <td className="pl-2 pr-4 text-right">
                    <div className="inline-flex gap-1">
                      {permissions.view && (
                        <Button size="sm" variant="ghost" className="h-7 w-7 p-0" onClick={() => onView(r.id)} title="Ver">
                          <Eye className="h-3.5 w-3.5" />
                        </Button>
                      )}
                      {permissions.view && (
                        <Button size="sm" variant="ghost" className="h-7 w-7 p-0" onClick={() => window.open(`/purchases/print/${r.id}`, '_blank')} title="Imprimir">
                          <Printer className="h-3.5 w-3.5" />
                        </Button>
                      )}
                      {permissions.update && (
                        <Button size="sm" variant="ghost" className="h-7 w-7 p-0" onClick={() => onEdit(r.id)} title="Editar">
                          <Edit className="h-3.5 w-3.5" />
                        </Button>
                      )}
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
                  <td colSpan={9} className="py-16">
                    <div className="flex flex-col items-center gap-3 text-center">
                      <FileText className="h-10 w-10 text-stone-300" />
                      <div className="text-sm text-stone-600">
                        {busca || filters.location_id || filters.supplier_id || filters.status || filters.payment_status
                          ? 'Nenhuma compra com os filtros atuais.'
                          : 'Ainda não há compras registradas.'}
                      </div>
                      {permissions.create && !busca && !filters.location_id && (
                        <Button size="sm" onClick={() => router.visit('/purchases/create')}>
                          + Registrar primeira compra
                        </Button>
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

PurchaseIndex.layout = (page: ReactNode) => (
  <AppShellV2
    title="Compras"
    breadcrumbItems={[{ label: 'Compras' }]}
  >
    {page}
  </AppShellV2>
);

export default PurchaseIndex;
