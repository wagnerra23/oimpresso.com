// @memcofre tela=/payments/v2/{id} module=TransactionPayment
// MWART Wave Blade T1 Migration B (2026-05-17) — full page Show (vs modal Blade)
// Charter: ./Show.charter.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { router, Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { ArrowLeft, Printer, Download, Pencil } from 'lucide-react';

interface PaymentLine {
  id: number;
  amount: string | number;
  method: string;
  paid_on: string;
  payment_ref_no: string | null;
  note: string | null;
  document_path: string | null;
  document_name: string | null;
  card_holder_name: string | null;
  card_number: string | null;
  card_transaction_number: string | null;
  cheque_number: string | null;
  transaction_no: string | null;
}

interface Transaction {
  id: number;
  ref_no: string | null;
  type: string;
  payment_status: 'paid' | 'partial' | 'due' | null;
  contact?: {
    name: string;
    supplier_business_name: string | null;
    contact_address: string | null;
    tax_number: string | null;
    mobile: string | null;
    email: string | null;
    type: string;
  };
  location?: { name: string };
  business?: { name: string };
}

interface Props {
  single_payment_line: PaymentLine;
  transaction: Transaction;
  payment_types: Record<string, string>;
}

const STATUS_LABELS: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' }> = {
  paid: { label: 'Pago', variant: 'default' },
  partial: { label: 'Parcial', variant: 'secondary' },
  due: { label: 'Em aberto', variant: 'destructive' },
};

function formatBRL(v: string | number) {
  const n = typeof v === 'string' ? parseFloat(v) : v;
  return Number.isNaN(n) ? 'R$ [redacted Tier 0]' : n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatDateTime(v: string | null) {
  if (!v) return '—';
  const d = new Date(v);
  if (Number.isNaN(d.getTime())) return v;
  return d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function Show({ single_payment_line, transaction, payment_types }: Props) {
  const status = transaction.payment_status && STATUS_LABELS[transaction.payment_status];
  const methodLabel = payment_types[single_payment_line.method] ?? single_payment_line.method;

  return (
    <div className="p-6 max-w-5xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between flex-wrap gap-3 no-print">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">
            Pagamento {single_payment_line.payment_ref_no ?? `#${single_payment_line.id}`}
          </h1>
          <div className="flex items-center gap-2 mt-1">
            {status && <Badge variant={status.variant}>{status.label}</Badge>}
            <span className="text-sm text-muted-foreground">
              Transação {transaction.ref_no ?? `#${transaction.id}`}
            </span>
          </div>
        </div>
        <div className="flex gap-2">
          <Link href="/payments/v2">
            <Button variant="outline" size="sm">
              <ArrowLeft className="h-4 w-4 mr-1" /> Voltar
            </Button>
          </Link>
          <Button variant="outline" size="sm" onClick={() => window.print()}>
            <Printer className="h-4 w-4 mr-1" /> Imprimir
          </Button>
          <Button size="sm" onClick={() => router.visit(`/payments/v2/${single_payment_line.id}/edit`)}>
            <Pencil className="h-4 w-4 mr-1" /> Editar
          </Button>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {/* Card 1: Contato */}
        {transaction.contact && (
          <Card>
            <CardContent className="pt-6">
              <div className="text-xs uppercase text-muted-foreground mb-2">
                {transaction.contact.type === 'supplier' ? 'Fornecedor' : 'Cliente'}
              </div>
              <div className="font-semibold">{transaction.contact.name}</div>
              {transaction.contact.supplier_business_name && (
                <div className="text-sm text-muted-foreground">{transaction.contact.supplier_business_name}</div>
              )}
              {transaction.contact.contact_address && (
                <div
                  className="text-sm mt-2"
                  dangerouslySetInnerHTML={{ __html: transaction.contact.contact_address }}
                />
              )}
              <div className="text-sm mt-2 space-y-1">
                {transaction.contact.tax_number && (
                  <div><strong>CPF/CNPJ:</strong> {transaction.contact.tax_number}</div>
                )}
                {transaction.contact.mobile && (
                  <div><strong>Telefone:</strong> {transaction.contact.mobile}</div>
                )}
                {transaction.contact.email && (
                  <div><strong>Email:</strong> {transaction.contact.email}</div>
                )}
              </div>
            </CardContent>
          </Card>
        )}

        {/* Card 2: Pagamento detail */}
        <Card>
          <CardContent className="pt-6">
            <div className="text-xs uppercase text-muted-foreground mb-2">Pagamento</div>
            <div className="text-3xl font-bold tracking-tight">{formatBRL(single_payment_line.amount)}</div>
            <div className="text-sm mt-4 space-y-2">
              <div className="flex justify-between"><strong>Método:</strong> <span>{methodLabel}</span></div>
              <div className="flex justify-between"><strong>Data:</strong> <span>{formatDateTime(single_payment_line.paid_on)}</span></div>
              {single_payment_line.payment_ref_no && (
                <div className="flex justify-between"><strong>Referência:</strong> <span>{single_payment_line.payment_ref_no}</span></div>
              )}

              {/* Conditional fields */}
              {single_payment_line.method === 'card' && (
                <>
                  {single_payment_line.card_holder_name && (
                    <div className="flex justify-between"><strong>Portador:</strong> <span>{single_payment_line.card_holder_name}</span></div>
                  )}
                  {single_payment_line.card_number && (
                    <div className="flex justify-between"><strong>Cartão nº:</strong> <span>{single_payment_line.card_number}</span></div>
                  )}
                  {single_payment_line.card_transaction_number && (
                    <div className="flex justify-between"><strong>Trans. cartão:</strong> <span>{single_payment_line.card_transaction_number}</span></div>
                  )}
                </>
              )}
              {single_payment_line.method === 'cheque' && single_payment_line.cheque_number && (
                <div className="flex justify-between"><strong>Nº cheque:</strong> <span>{single_payment_line.cheque_number}</span></div>
              )}
              {['custom_pay_1', 'custom_pay_2', 'custom_pay_3'].includes(single_payment_line.method) && single_payment_line.transaction_no && (
                <div className="flex justify-between"><strong>Nº transação:</strong> <span>{single_payment_line.transaction_no}</span></div>
              )}

              {single_payment_line.note && (
                <div className="pt-2 border-t mt-2">
                  <strong>Observação:</strong>
                  <p className="text-muted-foreground mt-1">{single_payment_line.note}</p>
                </div>
              )}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Card 3: Documento anexo */}
      {single_payment_line.document_path && (
        <Card className="no-print">
          <CardContent className="pt-6 flex items-center justify-between">
            <div className="text-sm">
              <div className="font-medium">Documento anexo</div>
              <div className="text-xs text-muted-foreground">{single_payment_line.document_name ?? 'arquivo'}</div>
            </div>
            <a href={single_payment_line.document_path} download={single_payment_line.document_name ?? undefined}>
              <Button variant="outline" size="sm">
                <Download className="h-4 w-4 mr-1" /> Baixar
              </Button>
            </a>
          </CardContent>
        </Card>
      )}

      {/* Trail audit placeholder — futuro v2.1 (LogsActivity) */}
      <Card className="no-print">
        <CardContent className="pt-6 text-xs text-muted-foreground">
          Auditoria detalhada (`LogsActivity`) será incorporada em v2.1. Por ora, ver Blade legacy `/payments` para histórico completo se necessário.
        </CardContent>
      </Card>
    </div>
  );
}

Show.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Pagamento" breadcrumbItems={[{ label: 'Financeiro' }, { label: 'Pagamentos', href: '/payments/v2' }, { label: 'Detalhes' }]}>
    {page}
  </AppShellV2>
);

export default Show;
