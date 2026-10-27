// RegistrarPagamento — botão "+ Registrar pagamento" da seção Pagamentos do detalhe da compra.
// Âncora: prototipo-ui/cowork/Wagner/compras-page.jsx, aba "pagamentos" do DrawerView (o botão
// primário aparece só quando há saldo). O formulário não está desenhado no protótipo; segue os
// campos que o backend aceita (PurchaseController@registrarPagamento).
//
// O valor sai NUMÉRICO: o endpoint não passa por num_uf, de propósito — o parser pt-BR lê "."
// como milhar (incidente 2026-06-05). O campo é o NumericInputPtBR canônico, que já emite número.

import { useState, type FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { NumericInputPtBR } from '@/Components/ui/numeric-input-ptbr';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Components/ui/dialog';

export interface Opcao {
  value: string;
  label: string;
}

interface Props {
  compraId: number;
  refNo: string;
  saldo: number;
  formas: Opcao[];
  contas: Opcao[];
}

const brl = (v: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

const hoje = () => {
  const d = new Date();
  const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
};

const SEM_CONTA = '__sem_conta__';

export default function RegistrarPagamento({ compraId, refNo, saldo, formas, contas }: Props) {
  const [aberto, setAberto] = useState(false);
  const form = useForm({
    amount: 0,
    method: formas[0]?.value ?? '',
    paid_on: hoje(),
    note: '',
    account_id: '',
  });

  const abrir = () => {
    form.reset();
    form.clearErrors();
    form.setData('method', formas[0]?.value ?? '');
    form.setData('amount', Math.round(saldo * 100) / 100);
    setAberto(true);
  };

  const enviar = (e: FormEvent) => {
    e.preventDefault();
    const valor = Math.round((form.data.amount || 0) * 100) / 100;
    if (valor <= 0) {
      form.setError('amount', 'Informe um valor maior que zero.');
      return;
    }
    if (valor > Math.round(saldo * 100) / 100) {
      form.setError('amount', `O valor passa do saldo da compra (${brl(saldo)}).`);
      return;
    }
    form.transform((d) => ({ ...d, amount: valor }));
    form.post(`/purchases/${compraId}/pagamento`, {
      preserveScroll: true,
      onSuccess: () => setAberto(false),
    });
  };

  return (
    <>
      <Button type="button" size="sm" onClick={abrir} data-contract="registrar-pagamento">
        + Registrar pagamento
      </Button>
      <Dialog open={aberto} onOpenChange={setAberto}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Registrar pagamento</DialogTitle>
            <DialogDescription>
              Compra {refNo} · saldo {brl(saldo)}
            </DialogDescription>
          </DialogHeader>
          <form onSubmit={enviar} className="space-y-3 text-[13px]">
            <div>
              <Label htmlFor="pg-valor" className="text-[12px] text-muted-foreground">Valor (R$)</Label>
              <NumericInputPtBR
                id="pg-valor"
                value={form.data.amount}
                onChange={(n) => form.setData('amount', n)}
                aria-invalid={!!form.errors.amount}
                className="mt-1 tabular-nums"
              />
              {form.errors.amount && <p className="mt-1 text-[12px] text-destructive">{form.errors.amount}</p>}
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label className="text-[12px] text-muted-foreground">Forma de pagamento</Label>
                <Select value={form.data.method} onValueChange={(v) => form.setData('method', v)}>
                  <SelectTrigger aria-label="Forma de pagamento" className="mt-1 w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {formas.filter((f) => f.value).map((f) => (
                      <SelectItem key={f.value} value={f.value}>{f.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {form.errors.method && <p className="mt-1 text-[12px] text-destructive">{form.errors.method}</p>}
              </div>
              <div>
                <Label htmlFor="pg-data" className="text-[12px] text-muted-foreground">Data</Label>
                <Input
                  id="pg-data"
                  type="date"
                  value={form.data.paid_on}
                  onChange={(e) => form.setData('paid_on', e.target.value)}
                  className="mt-1"
                />
                {form.errors.paid_on && <p className="mt-1 text-[12px] text-destructive">{form.errors.paid_on}</p>}
              </div>
            </div>
            {contas.length > 0 && (
              <div>
                <Label className="text-[12px] text-muted-foreground">Conta</Label>
                <Select
                  value={form.data.account_id || SEM_CONTA}
                  onValueChange={(v) => form.setData('account_id', v === SEM_CONTA ? '' : v)}
                >
                  <SelectTrigger aria-label="Conta" className="mt-1 w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={SEM_CONTA}>Sem conta</SelectItem>
                    {contas.filter((c) => c.value).map((c) => (
                      <SelectItem key={c.value} value={c.value}>{c.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {form.errors.account_id && <p className="mt-1 text-[12px] text-destructive">{form.errors.account_id}</p>}
              </div>
            )}
            <div>
              <Label htmlFor="pg-obs" className="text-[12px] text-muted-foreground">Observação</Label>
              <Textarea
                id="pg-obs"
                rows={2}
                value={form.data.note}
                onChange={(e) => form.setData('note', e.target.value)}
                className="mt-1"
              />
            </div>
            <div className="flex justify-end gap-2 pt-1">
              <Button type="button" variant="outline" size="sm" onClick={() => setAberto(false)}>
                Cancelar
              </Button>
              <Button type="submit" size="sm" disabled={form.processing}>
                {form.processing ? 'Registrando…' : 'Registrar'}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}
