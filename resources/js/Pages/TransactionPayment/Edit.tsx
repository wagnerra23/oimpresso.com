// @memcofre tela=/payments/v2/{id}/edit module=TransactionPayment
// MWART Wave Blade T1 Migration B (2026-05-17) — full page Edit (vs modal Blade)
// Charter: ./Edit.charter.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { router, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Save, X } from 'lucide-react';
import { toast } from 'sonner';

interface PaymentLine {
  id: number;
  amount: string | number;
  method: string;
  paid_on: string;
  payment_ref_no: string | null;
  note: string | null;
  card_holder_name: string | null;
  card_number: string | null;
  card_transaction_number: string | null;
  cheque_number: string | null;
  bank_account_number: string | null;
  transaction_no: string | null;
  account_id: number | null;
}

interface Transaction {
  id: number;
  ref_no: string | null;
  type: string;
  final_total: string | number;
  payment_status: string;
  contact?: {
    id: number;
    name: string;
    supplier_business_name: string | null;
    type: string;
  };
}

interface Props {
  payment_line: PaymentLine;
  transaction: Transaction;
  payment_types: Record<string, string>;
  accounts: Record<number, string>;
}

function formatBRL(v: string | number) {
  const n = typeof v === 'string' ? parseFloat(v) : v;
  return Number.isNaN(n) ? 'R$ [redacted Tier 0]' : n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function Edit({ payment_line, transaction, payment_types, accounts }: Props) {
  const form = useForm({
    amount: String(payment_line.amount),
    method: payment_line.method,
    paid_on: payment_line.paid_on?.substring(0, 16) ?? '',
    note: payment_line.note ?? '',
    account_id: payment_line.account_id ? String(payment_line.account_id) : '',
    card_holder_name: payment_line.card_holder_name ?? '',
    card_number: payment_line.card_number ?? '',
    card_transaction_number: payment_line.card_transaction_number ?? '',
    cheque_number: payment_line.cheque_number ?? '',
    bank_account_number: payment_line.bank_account_number ?? '',
    transaction_no_1: payment_line.method === 'custom_pay_1' ? payment_line.transaction_no ?? '' : '',
    transaction_no_2: payment_line.method === 'custom_pay_2' ? payment_line.transaction_no ?? '' : '',
    transaction_no_3: payment_line.method === 'custom_pay_3' ? payment_line.transaction_no ?? '' : '',
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();

    if (parseFloat(form.data.amount) <= 0) {
      toast.error('Valor deve ser maior que zero.');
      return;
    }
    if (!form.data.method) {
      toast.error('Método de pagamento obrigatório.');
      return;
    }
    if (!form.data.paid_on) {
      toast.error('Data de pagamento obrigatória.');
      return;
    }

    // POST PUT reusa endpoint legacy update($id) — sem duplicar lógica
    form.put(`/payments/${payment_line.id}`, {
      preserveScroll: false,
      onSuccess: () => {
        toast.success('Pagamento atualizado.');
        router.visit('/payments/v2');
      },
      onError: (errs) => {
        const firstError = Object.values(errs)[0];
        toast.error(typeof firstError === 'string' ? firstError : 'Erro ao salvar pagamento.');
      },
    });
  };

  const renderConditionalFields = () => {
    if (form.data.method === 'card') {
      return (
        <>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <Label htmlFor="card_holder_name">Nome do portador</Label>
              <Input id="card_holder_name" value={form.data.card_holder_name} onChange={(e) => form.setData('card_holder_name', e.target.value)} />
            </div>
            <div>
              <Label htmlFor="card_number">Número do cartão</Label>
              <Input id="card_number" value={form.data.card_number} onChange={(e) => form.setData('card_number', e.target.value)} />
            </div>
            <div>
              <Label htmlFor="card_transaction_number">Nº transação cartão</Label>
              <Input id="card_transaction_number" value={form.data.card_transaction_number} onChange={(e) => form.setData('card_transaction_number', e.target.value)} />
            </div>
          </div>
        </>
      );
    }
    if (form.data.method === 'cheque') {
      return (
        <div>
          <Label htmlFor="cheque_number">Número do cheque</Label>
          <Input id="cheque_number" value={form.data.cheque_number} onChange={(e) => form.setData('cheque_number', e.target.value)} />
        </div>
      );
    }
    if (form.data.method === 'bank_transfer') {
      return (
        <div>
          <Label htmlFor="bank_account_number">Conta bancária</Label>
          <Input id="bank_account_number" value={form.data.bank_account_number} onChange={(e) => form.setData('bank_account_number', e.target.value)} />
        </div>
      );
    }
    if (['custom_pay_1', 'custom_pay_2', 'custom_pay_3'].includes(form.data.method)) {
      const idx = form.data.method.slice(-1);
      const fieldKey = `transaction_no_${idx}` as 'transaction_no_1' | 'transaction_no_2' | 'transaction_no_3';
      return (
        <div>
          <Label htmlFor={fieldKey}>Nº transação</Label>
          <Input id={fieldKey} value={form.data[fieldKey]} onChange={(e) => form.setData(fieldKey, e.target.value)} />
        </div>
      );
    }
    return null;
  };

  return (
    <div className="p-6 max-w-4xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Editar pagamento</h1>
        <p className="text-sm text-muted-foreground mt-1">Pagamento {payment_line.payment_ref_no ?? `#${payment_line.id}`}</p>
      </div>

      {/* Header: contexto da transação */}
      <Card>
        <CardContent className="pt-6 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
          {transaction.contact && (
            <div>
              <div className="text-xs text-muted-foreground">{transaction.contact.type === 'supplier' ? 'Fornecedor' : 'Cliente'}</div>
              <div className="font-medium">{transaction.contact.name}</div>
              {transaction.contact.supplier_business_name && (
                <div className="text-xs text-muted-foreground">{transaction.contact.supplier_business_name}</div>
              )}
            </div>
          )}
          <div>
            <div className="text-xs text-muted-foreground">Referência transação</div>
            <div className="font-medium">{transaction.ref_no ?? `Tx #${transaction.id}`}</div>
          </div>
          <div>
            <div className="text-xs text-muted-foreground">Total transação</div>
            <div className="font-medium">{formatBRL(transaction.final_total)}</div>
          </div>
        </CardContent>
      </Card>

      {/* Form */}
      <form onSubmit={submit} className="space-y-6">
        <Card>
          <CardContent className="pt-6 space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <Label htmlFor="method">Método *</Label>
                <Select value={form.data.method} onValueChange={(v) => form.setData('method', v)}>
                  <SelectTrigger id="method">
                    <SelectValue placeholder="Selecione" />
                  </SelectTrigger>
                  <SelectContent>
                    {Object.entries(payment_types).map(([k, label]) => (
                      <SelectItem key={k} value={k}>{label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {form.errors.method && <p className="text-xs text-red-600 mt-1">{form.errors.method}</p>}
              </div>
              <div>
                <Label htmlFor="paid_on">Data *</Label>
                <Input
                  id="paid_on"
                  type="datetime-local"
                  value={form.data.paid_on}
                  onChange={(e) => form.setData('paid_on', e.target.value)}
                  required
                />
                {form.errors.paid_on && <p className="text-xs text-red-600 mt-1">{form.errors.paid_on}</p>}
              </div>
              <div>
                <Label htmlFor="amount">Valor (R$) *</Label>
                <Input
                  id="amount"
                  type="number"
                  step="0.01"
                  min="0.01"
                  value={form.data.amount}
                  onChange={(e) => form.setData('amount', e.target.value)}
                  required
                />
                {form.errors.amount && <p className="text-xs text-red-600 mt-1">{form.errors.amount}</p>}
              </div>
            </div>

            {Object.keys(accounts).length > 0 && form.data.method !== 'advance' && (
              <div>
                <Label htmlFor="account_id">Conta bancária</Label>
                <Select value={form.data.account_id || 'none'} onValueChange={(v) => form.setData('account_id', v === 'none' ? '' : v)}>
                  <SelectTrigger id="account_id">
                    <SelectValue placeholder="Selecione" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">— Nenhuma —</SelectItem>
                    {Object.entries(accounts).map(([id, label]) => (
                      <SelectItem key={id} value={id}>{String(label)}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}

            {renderConditionalFields()}

            <div>
              <Label htmlFor="note">Observação</Label>
              <Textarea
                id="note"
                value={form.data.note}
                onChange={(e) => form.setData('note', e.target.value)}
                rows={3}
              />
            </div>
          </CardContent>
        </Card>

        <div className="flex gap-2 justify-end">
          <Button type="button" variant="outline" onClick={() => router.visit('/payments/v2')}>
            <X className="h-4 w-4 mr-1" />
            Cancelar
          </Button>
          <Button type="submit" disabled={form.processing}>
            <Save className="h-4 w-4 mr-1" />
            {form.processing ? 'Salvando...' : 'Salvar'}
          </Button>
        </div>
      </form>
    </div>
  );
}

Edit.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Editar pagamento" breadcrumbItems={[{ label: 'Financeiro' }, { label: 'Pagamentos', href: '/payments/v2' }, { label: 'Editar' }]}>
    {page}
  </AppShellV2>
);

export default Edit;
