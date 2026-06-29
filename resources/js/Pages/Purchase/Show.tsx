// @memcofre
//   tela: /purchases/{id}
//   module: Purchase
//   tipo: DETAIL (MWART migracao-blade-react PR2 piloto)
//   rules: R-PUR-001 (business_id Tier 0), R-PUR-002 (permissions)
//   adrs: 0141 (skill), 0104 (MWART), 0093 (Tier 0), 0110 (Cockpit V2)
//
// Substitui Blade legacy show.blade.php + show_details.blade.php (430+ linhas).
// Mata bug 500 em prod (DNS1D::getBarcodePNG linha 430 quebrada).
// Snapshot paridade: memory/mwart-inventory/purchase/show.snapshot.md

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { router } from '@inertiajs/react';
import { type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader } from '@/Components/ui/card';
import { Edit, Trash2, Printer, ArrowLeft, FileText, Download } from 'lucide-react';

// ---------- Tipos ----------

type PurchaseStatus = 'received' | 'pending' | 'ordered';
type PaymentStatus = 'paid' | 'due' | 'partial' | 'overdue';

interface PurchaseLine {
  id: number;
  product_name: string;
  sku: string;
  variation_name: string | null;
  quantity: number;
  unit_name: string;
  pp_without_discount: number;
  discount_percent: number;
  purchase_price: number;
  item_tax: number;
  tax_name: string | null;
  purchase_price_inc_tax: number;
  subtotal: number;
  lot_number: string | null;
  mfg_date: string | null;
  exp_date: string | null;
}

interface PaymentLine {
  id: number;
  paid_on: string;
  payment_ref_no: string | null;
  amount: number;
  method_label: string;
  note: string | null;
}

interface PurchaseDetail {
  id: number;
  ref_no: string;
  transaction_date: string;
  type: 'purchase' | 'purchase_return' | 'purchase_order';
  status: PurchaseStatus;
  payment_status: PaymentStatus;
  additional_notes: string | null;
  // Supplier
  supplier_name: string;
  supplier_business_name: string | null;
  supplier_address: string | null;
  supplier_tax_number: string | null;
  supplier_mobile: string | null;
  supplier_email: string | null;
  // Business + location
  business_name: string;
  business_tax_label_1: string | null;
  business_tax_number_1: string | null;
  business_tax_label_2: string | null;
  business_tax_number_2: string | null;
  location_name: string;
  location_landmark: string | null;
  location_city_state: string | null;
  location_mobile: string | null;
  location_email: string | null;
  // Document
  document_path: string | null;
  document_name: string | null;
  // Items + totais
  purchase_lines: PurchaseLine[];
  payment_lines: PaymentLine[];
  net_total: number;
  discount_type: 'fixed' | 'percentage';
  discount_amount: number;
  discount_value: number;
  tax_breakdown: { name: string; amount: number }[];
  shipping_charges: number;
  final_total: number;
  amount_paid: number;
  payment_due: number;
}

interface Permissions {
  update: boolean;
  delete: boolean;
  payments: boolean;
}

interface Props {
  purchase: PurchaseDetail;
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

const STATUS_LABEL: Record<PurchaseStatus, string> = {
  received: 'Recebido',
  pending: 'Pendente',
  ordered: 'Solicitado',
};

const STATUS_PILL: Record<PurchaseStatus, string> = {
  received: 'bg-success-soft text-success-fg border-success/20',
  pending: 'bg-amber-50 text-amber-800 border-amber-200',
  ordered: 'bg-blue-50 text-blue-700 border-blue-200',
};

const PAYMENT_LABEL: Record<PaymentStatus, string> = {
  paid: 'Pago',
  due: 'Em aberto',
  partial: 'Parcial',
  overdue: 'Vencido',
};

const PAYMENT_PILL: Record<PaymentStatus, string> = {
  paid: 'bg-success-soft text-success-fg border-success/20',
  due: 'bg-stone-50 text-stone-700 border-stone-200',
  partial: 'bg-amber-50 text-amber-800 border-amber-200',
  overdue: 'bg-destructive-soft text-destructive-fg border-destructive/20',
};

// ---------- Página principal ----------

function PurchaseShow({ purchase, permissions }: Props) {
  const onDelete = () => {
    if (!confirm(`Confirma exclusão da compra ${purchase.ref_no}?`)) return;
    router.delete(`/purchases/${purchase.id}`);
  };

  return (
    <>
      <PageHeader
        icon="shopping-cart"
        title={`Compra ${purchase.ref_no || '#' + purchase.id}`}
        description={
          <div className="flex gap-2 items-center">
            <span className="text-[12px]">{formatDateTime(purchase.transaction_date)}</span>
            <span className={`inline-flex items-center px-1.5 py-0.5 rounded border text-[11px] font-medium ${STATUS_PILL[purchase.status]}`}>
              {STATUS_LABEL[purchase.status]}
            </span>
            <span className={`inline-flex items-center px-1.5 py-0.5 rounded border text-[11px] font-medium ${PAYMENT_PILL[purchase.payment_status]}`}>
              {PAYMENT_LABEL[purchase.payment_status]}
            </span>
          </div>
        }
        action={
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={() => router.visit('/purchases')}>
              <ArrowLeft className="h-4 w-4 mr-1" /> Voltar
            </Button>
            <Button variant="outline" size="sm" onClick={() => window.open(`/purchases/print/${purchase.id}`, '_blank')}>
              <Printer className="h-4 w-4 mr-1" /> Imprimir
            </Button>
            {permissions.update && (
              <Button variant="outline" size="sm" onClick={() => router.visit(`/purchases/${purchase.id}/edit`)}>
                <Edit className="h-4 w-4 mr-1" /> Editar
              </Button>
            )}
            {permissions.delete && (
              <Button variant="outline" size="sm" className="text-destructive" onClick={onDelete}>
                <Trash2 className="h-4 w-4 mr-1" /> Excluir
              </Button>
            )}
          </div>
        }
      />

      {/* 3 cards: Fornecedor / Empresa / Resumo */}
      <div className="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
        <Card>
          <CardHeader className="pb-2 pt-3 px-4">
            <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Fornecedor</h3>
          </CardHeader>
          <CardContent className="px-4 pb-4 text-[13px] space-y-1">
            {purchase.supplier_business_name && <div className="font-medium">{purchase.supplier_business_name}</div>}
            <div className={purchase.supplier_business_name ? 'text-stone-600' : 'font-medium'}>{purchase.supplier_name}</div>
            {purchase.supplier_address && <div className="text-stone-600 text-[12px]">{purchase.supplier_address}</div>}
            {purchase.supplier_tax_number && <div className="text-stone-600 text-[12px]">CNPJ/CPF: {purchase.supplier_tax_number}</div>}
            {purchase.supplier_mobile && <div className="text-stone-600 text-[12px]">Tel: {purchase.supplier_mobile}</div>}
            {purchase.supplier_email && <div className="text-stone-600 text-[12px]">{purchase.supplier_email}</div>}
            {purchase.document_path && (
              <a href={purchase.document_path} download={purchase.document_name ?? ''} className="inline-flex items-center text-primary hover:underline text-[12px] mt-2">
                <Download className="h-3 w-3 mr-1" /> Documento anexo
              </a>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2 pt-3 px-4">
            <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Empresa</h3>
          </CardHeader>
          <CardContent className="px-4 pb-4 text-[13px] space-y-1">
            <div className="font-medium">{purchase.business_name}</div>
            <div className="text-stone-600">{purchase.location_name}</div>
            {purchase.location_landmark && <div className="text-stone-600 text-[12px]">{purchase.location_landmark}</div>}
            {purchase.location_city_state && <div className="text-stone-600 text-[12px]">{purchase.location_city_state}</div>}
            {purchase.business_tax_label_1 && purchase.business_tax_number_1 && (
              <div className="text-stone-600 text-[12px]">{purchase.business_tax_label_1}: {purchase.business_tax_number_1}</div>
            )}
            {purchase.business_tax_label_2 && purchase.business_tax_number_2 && (
              <div className="text-stone-600 text-[12px]">{purchase.business_tax_label_2}: {purchase.business_tax_number_2}</div>
            )}
            {purchase.location_mobile && <div className="text-stone-600 text-[12px]">Tel: {purchase.location_mobile}</div>}
            {purchase.location_email && <div className="text-stone-600 text-[12px]">{purchase.location_email}</div>}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2 pt-3 px-4">
            <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Resumo</h3>
          </CardHeader>
          <CardContent className="px-4 pb-4 text-[13px] space-y-2">
            <div className="flex justify-between"><span className="text-stone-500">Ref. Nº</span><span className="font-medium tabular-nums">{purchase.ref_no || '—'}</span></div>
            <div className="flex justify-between"><span className="text-stone-500">Data</span><span className="tabular-nums">{formatDateTime(purchase.transaction_date)}</span></div>
            <div className="flex justify-between items-center"><span className="text-stone-500">Status</span><span className={`inline-flex items-center px-1.5 py-0.5 rounded border text-[11px] font-medium ${STATUS_PILL[purchase.status]}`}>{STATUS_LABEL[purchase.status]}</span></div>
            <div className="flex justify-between items-center"><span className="text-stone-500">Pagamento</span><span className={`inline-flex items-center px-1.5 py-0.5 rounded border text-[11px] font-medium ${PAYMENT_PILL[purchase.payment_status]}`}>{PAYMENT_LABEL[purchase.payment_status]}</span></div>
          </CardContent>
        </Card>
      </div>

      {/* Tabela items */}
      <Card className="mt-4">
        <CardContent className="p-0">
          <table className="w-full border-collapse">
            <thead>
              <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/40">
                <th className="pl-4 pr-2 py-2 w-8 text-left font-medium">#</th>
                <th className="px-2 py-2 text-left font-medium">Produto</th>
                <th className="px-2 py-2 text-left font-medium">SKU</th>
                <th className="px-2 py-2 text-right font-medium">Qtd</th>
                <th className="px-2 py-2 text-right font-medium">Custo unit.</th>
                <th className="px-2 py-2 text-right font-medium">Desc %</th>
                <th className="px-2 py-2 text-right font-medium">Imposto</th>
                <th className="pl-2 pr-4 py-2 text-right font-medium">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              {purchase.purchase_lines.map((line, idx) => (
                <tr key={line.id} className="h-11 text-[13px] border-b border-stone-100">
                  <td className="pl-4 pr-2 text-stone-500 tabular-nums">{idx + 1}</td>
                  <td className="px-2">
                    <div className="font-medium text-stone-900">{line.product_name}</div>
                    {line.variation_name && <div className="text-[11px] text-stone-500">{line.variation_name}</div>}
                  </td>
                  <td className="px-2 text-stone-600 tabular-nums text-[12px]">{line.sku}</td>
                  <td className="px-2 text-right tabular-nums">{line.quantity} <span className="text-stone-400 text-[11px]">{line.unit_name}</span></td>
                  <td className="px-2 text-right tabular-nums">{brl(line.pp_without_discount)}</td>
                  <td className="px-2 text-right tabular-nums text-stone-500">{line.discount_percent.toFixed(2)}%</td>
                  <td className="px-2 text-right tabular-nums text-stone-500">
                    {brl(line.item_tax)}
                    {line.tax_name && <div className="text-[10px] text-stone-400">({line.tax_name})</div>}
                  </td>
                  <td className="pl-2 pr-4 text-right tabular-nums font-medium">{brl(line.subtotal)}</td>
                </tr>
              ))}
              {purchase.purchase_lines.length === 0 && (
                <tr>
                  <td colSpan={8} className="py-8 text-center text-stone-500 text-sm">
                    <FileText className="h-8 w-8 mx-auto mb-2 text-stone-300" />
                    Esta compra não tem itens.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </CardContent>
      </Card>

      {/* Pagamentos + Totais */}
      <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
        <Card>
          <CardHeader className="pb-2 pt-3 px-4">
            <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Pagamentos</h3>
          </CardHeader>
          <CardContent className="px-4 pb-4 text-[13px]">
            {purchase.payment_lines.length === 0 ? (
              <div className="text-stone-500 py-4 text-center text-[12px]">Sem pagamentos registrados.</div>
            ) : (
              <table className="w-full text-[12px]">
                <thead>
                  <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200">
                    <th className="py-1.5 text-left font-medium">Data</th>
                    <th className="py-1.5 text-left font-medium">Método</th>
                    <th className="py-1.5 text-right font-medium">Valor</th>
                  </tr>
                </thead>
                <tbody>
                  {purchase.payment_lines.map((p) => (
                    <tr key={p.id} className="border-b border-stone-100">
                      <td className="py-1.5 tabular-nums">{formatDateTime(p.paid_on)}</td>
                      <td className="py-1.5 text-stone-700">{p.method_label}{p.note && <span className="text-stone-400 text-[11px]"> · {p.note}</span>}</td>
                      <td className="py-1.5 text-right tabular-nums">{brl(p.amount)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2 pt-3 px-4">
            <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Totais</h3>
          </CardHeader>
          <CardContent className="px-4 pb-4 text-[13px] space-y-1.5">
            <div className="flex justify-between"><span className="text-stone-500">Subtotal</span><span className="tabular-nums">{brl(purchase.net_total)}</span></div>
            <div className="flex justify-between"><span className="text-stone-500">Desconto {purchase.discount_type === 'percentage' && `(${purchase.discount_amount}%)`}</span><span className="tabular-nums text-destructive">- {brl(purchase.discount_value)}</span></div>
            {purchase.tax_breakdown.map((t) => (
              <div key={t.name} className="flex justify-between"><span className="text-stone-500">{t.name}</span><span className="tabular-nums">+ {brl(t.amount)}</span></div>
            ))}
            {purchase.shipping_charges > 0 && (
              <div className="flex justify-between"><span className="text-stone-500">Frete</span><span className="tabular-nums">+ {brl(purchase.shipping_charges)}</span></div>
            )}
            <div className="flex justify-between border-t border-stone-200 pt-1.5 mt-1.5">
              <span className="font-semibold">Total geral</span>
              <span className="tabular-nums font-semibold text-[15px]">{brl(purchase.final_total)}</span>
            </div>
            <div className="flex justify-between text-success-fg">
              <span>Pago</span><span className="tabular-nums">{brl(purchase.amount_paid)}</span>
            </div>
            <div className="flex justify-between">
              <span className={purchase.payment_due > 0 ? 'font-medium text-destructive' : 'text-stone-400'}>A pagar</span>
              <span className={`tabular-nums ${purchase.payment_due > 0 ? 'font-medium text-destructive' : 'text-stone-400'}`}>{brl(purchase.payment_due)}</span>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Notas adicionais */}
      {purchase.additional_notes && (
        <Card className="mt-4">
          <CardHeader className="pb-2 pt-3 px-4">
            <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Notas adicionais</h3>
          </CardHeader>
          <CardContent className="px-4 pb-4 text-[13px] text-stone-700 whitespace-pre-wrap">
            {purchase.additional_notes}
          </CardContent>
        </Card>
      )}
    </>
  );
}

PurchaseShow.layout = (page: ReactNode) => (
  <AppShellV2
    title="Detalhe da compra"
    breadcrumbItems={[
      { label: 'Compras', href: '/purchases' },
      { label: 'Detalhe' },
    ]}
  >
    {page}
  </AppShellV2>
);

export default PurchaseShow;
