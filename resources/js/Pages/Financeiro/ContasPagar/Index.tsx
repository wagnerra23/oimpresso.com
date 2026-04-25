// @memcofre tela=/financeiro/contas-pagar module=Financeiro

import AppShell from '@/Layouts/AppShell';
import { Head, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { CreditCard, AlertTriangle, CheckCircle2, Circle, Hourglass } from 'lucide-react';
import { toast } from 'sonner';

interface Titulo {
  id: number;
  numero: string;
  cliente_descricao: string | null;
  valor_total: string;
  valor_aberto: string;
  vencimento: string | null;
  status: 'aberto' | 'parcial' | 'quitado' | 'cancelado';
  origem: string;
  origem_id: number | null;
}

interface Conta { id: number; nome: string; }

interface Props {
  titulos: Titulo[];
  contas_bancarias: Conta[];
  filtros: { status: string | null; vence_em: string | null };
}

const STATUS_LABELS: Record<string, { label: string; color: string; icon: typeof Circle }> = {
  aberto: { label: 'Aberto', color: 'bg-blue-100 text-blue-900 dark:bg-blue-900/30 dark:text-blue-200', icon: Circle },
  parcial: { label: 'Parcial', color: 'bg-amber-100 text-amber-900 dark:bg-amber-900/30 dark:text-amber-200', icon: Hourglass },
  quitado: { label: 'Pago', color: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/30 dark:text-emerald-200', icon: CheckCircle2 },
  cancelado: { label: 'Cancelado', color: 'bg-muted text-muted-foreground', icon: AlertTriangle },
};

function formatBRL(v: string | number) {
  const n = typeof v === 'string' ? parseFloat(v) : v;
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatDate(v: string | null) {
  if (!v) return '—';
  const [y, m, d] = v.split('-');
  return `${d}/${m}/${y}`;
}

function PagarSheet({ titulo, contas, onClose }: { titulo: Titulo; contas: Conta[]; onClose: () => void }) {
  const form = useForm({
    conta_bancaria_id: contas[0]?.id?.toString() ?? '',
    valor_baixa: titulo.valor_aberto,
    data_baixa: new Date().toISOString().slice(0, 10),
    meio_pagamento: 'pix',
    observacoes: '',
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post(`/financeiro/contas-pagar/${titulo.id}/pagar`, {
      preserveScroll: true,
      onSuccess: () => { toast.success('Baixa registrada'); onClose(); },
      onError: () => toast.error('Verifique os campos'),
    });
  };

  return (
    <Sheet open onOpenChange={(o) => !o && onClose()}>
      <SheetContent className="w-full sm:max-w-md">
        <SheetHeader>
          <SheetTitle>Pagar título #{titulo.numero}</SheetTitle>
        </SheetHeader>
        <form onSubmit={submit} className="space-y-4 mt-4">
          <div>
            <Label>Conta bancária *</Label>
            <Select
              value={form.data.conta_bancaria_id}
              onValueChange={(v) => form.setData('conta_bancaria_id', v)}
            >
              <SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger>
              <SelectContent>
                {contas.map((c) => <SelectItem key={c.id} value={c.id.toString()}>{c.nome}</SelectItem>)}
              </SelectContent>
            </Select>
            {form.errors.conta_bancaria_id && <p className="text-xs text-destructive mt-1">{form.errors.conta_bancaria_id}</p>}
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label>Valor pago *</Label>
              <Input
                type="number"
                step="0.01"
                value={form.data.valor_baixa}
                onChange={(e) => form.setData('valor_baixa', e.target.value)}
              />
              {form.errors.valor_baixa && <p className="text-xs text-destructive mt-1">{form.errors.valor_baixa}</p>}
            </div>
            <div>
              <Label>Data *</Label>
              <Input
                type="date"
                value={form.data.data_baixa}
                onChange={(e) => form.setData('data_baixa', e.target.value)}
              />
            </div>
          </div>

          <div>
            <Label>Meio de pagamento *</Label>
            <Select
              value={form.data.meio_pagamento}
              onValueChange={(v) => form.setData('meio_pagamento', v)}
            >
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="dinheiro">Dinheiro</SelectItem>
                <SelectItem value="pix">PIX</SelectItem>
                <SelectItem value="boleto">Boleto</SelectItem>
                <SelectItem value="transferencia">Transferência</SelectItem>
                <SelectItem value="cartao_credito">Cartão de crédito</SelectItem>
                <SelectItem value="cartao_debito">Cartão de débito</SelectItem>
                <SelectItem value="cheque">Cheque</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div>
            <Label>Observações</Label>
            <Textarea
              value={form.data.observacoes}
              onChange={(e) => form.setData('observacoes', e.target.value)}
              rows={2}
            />
          </div>

          <div className="flex justify-end gap-2 pt-2 border-t">
            <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
            <Button type="submit" disabled={form.processing}>
              {form.processing ? 'Salvando…' : 'Registrar pagamento'}
            </Button>
          </div>
        </form>
      </SheetContent>
    </Sheet>
  );
}

function Index({ titulos, contas_bancarias, filtros }: Props) {
  const [pagando, setPagando] = useState<Titulo | null>(null);

  const filtrar = (key: string, value: string | null) => {
    router.get('/financeiro/contas-pagar', { ...filtros, [key]: value }, { preserveScroll: true, preserveState: true });
  };

  return (
    <>
      <Head title="Contas a Pagar" />
      <div className="p-6 max-w-6xl mx-auto space-y-6">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Contas a Pagar</h1>
          <p className="text-sm text-muted-foreground mt-1">Títulos a pagar (compras, despesas, contratos).</p>
        </div>

        <div className="flex flex-wrap gap-2">
          <span className="text-xs text-muted-foreground self-center mr-2">Filtros:</span>
          {[
            { v: null, label: 'Todos' },
            { v: 'aberto', label: 'Abertos' },
            { v: 'parcial', label: 'Parciais' },
            { v: 'quitado', label: 'Pagos' },
          ].map((f) => (
            <Button key={`s-${f.v}`} size="sm" variant={filtros.status === f.v ? 'default' : 'outline'}
              onClick={() => filtrar('status', f.v)}>{f.label}</Button>
          ))}
          <span className="self-center mx-2 text-muted-foreground">·</span>
          {[
            { v: null, label: 'Qualquer venc.' },
            { v: 'hoje', label: 'Hoje' },
            { v: 'semana', label: 'Próx. 7 dias' },
            { v: 'atrasado', label: 'Atrasados' },
          ].map((f) => (
            <Button key={`v-${f.v}`} size="sm" variant={filtros.vence_em === f.v ? 'default' : 'outline'}
              onClick={() => filtrar('vence_em', f.v)}>{f.label}</Button>
          ))}
        </div>

        <div className="rounded-md border">
          <table className="w-full text-sm">
            <thead className="bg-muted/50">
              <tr className="text-left">
                <th className="px-4 py-2 font-medium">Nº</th>
                <th className="px-4 py-2 font-medium">Fornecedor</th>
                <th className="px-4 py-2 font-medium">Vencimento</th>
                <th className="px-4 py-2 font-medium text-right">Valor</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2 font-medium text-right">Ações</th>
              </tr>
            </thead>
            <tbody>
              {titulos.length === 0 && (
                <tr><td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">Nenhum título.</td></tr>
              )}
              {titulos.map((t) => {
                const cfg = STATUS_LABELS[t.status];
                const Icon = cfg.icon;
                return (
                  <tr key={t.id} className="border-t hover:bg-muted/30">
                    <td className="px-4 py-3 font-mono text-xs">{t.numero}</td>
                    <td className="px-4 py-3">
                      <div className="font-medium">{t.cliente_descricao ?? '(sem fornecedor)'}</div>
                      <div className="text-xs text-muted-foreground">{t.origem}</div>
                    </td>
                    <td className="px-4 py-3">{formatDate(t.vencimento)}</td>
                    <td className="px-4 py-3 text-right font-medium">{formatBRL(t.valor_aberto)}</td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex items-center gap-1 text-xs px-2 py-1 rounded ${cfg.color}`}>
                        <Icon className="h-3 w-3" /> {cfg.label}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-right">
                      {(t.status === 'aberto' || t.status === 'parcial') && (
                        <Button size="sm" variant="outline" onClick={() => setPagando(t)}>
                          <CreditCard className="h-4 w-4 mr-1" /> Pagar
                        </Button>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        <Card>
          <CardContent className="pt-6 text-xs text-muted-foreground">
            Mostrando até 100 títulos. Para conciliar pagamentos automaticamente, importe extrato OFX em /financeiro/conciliacao (em breve).
          </CardContent>
        </Card>
      </div>

      {pagando && <PagarSheet titulo={pagando} contas={contas_bancarias} onClose={() => setPagando(null)} />}
    </>
  );
}

Index.layout = (page: React.ReactNode) => <AppShell>{page}</AppShell>;
export default Index;
