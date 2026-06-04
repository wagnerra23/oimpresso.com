// FinBaixaSheet — 2026-06-03 — diálogo de baixa (Receber/Pagar).
//
// Antes: o botão "Recebi"/"Paguei" fazia POST vazio → baixa instantânea com a
// 1ª conta, valor cheio e meio fixo "transferência" (sem escolha). Pedido Wagner:
// ao receber/pagar, ESCOLHER valor (parcial), conta de destino, forma de
// pagamento e plano de contas.
//
// POST /financeiro/unificado/{id}/baixar (mesmo endpoint, agora aceita os campos;
// body vazio = comportamento legacy preservado).

import { useForm } from '@inertiajs/react';
import { useEffect, type FormEvent } from 'react';
import { ArrowDownCircle, ArrowUpCircle } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Sheet, SheetContent, SheetTitle, SheetDescription } from '@/Components/ui/sheet';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select';
import { PlanoContaCombobox, type PlanoConta } from './PlanoContaCombobox';
import { FORMA_PAGAMENTO_OPCOES } from '../_lib/forma-pagamento';

interface BaixaLancamento {
  id: number;
  kind: 'receivable' | 'payable';
  contraparte: string;
  valor: number;
  valor_aberto: number;
  plano_conta_id: number | null;
}

interface ContaOpt {
  id: number;
  nome: string;
}

interface FinBaixaSheetProps {
  open: boolean;
  onClose: () => void;
  lancamento: BaixaLancamento;
  contas: ContaOpt[];
  planos: PlanoConta[];
}

const hojeIso = () => new Date().toISOString().slice(0, 10);

function brl(n: number): string {
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 2 });
}

export function FinBaixaSheet({ open, onClose, lancamento, contas, planos }: FinBaixaSheetProps) {
  const receber = lancamento.kind === 'receivable';
  const aberto = lancamento.valor_aberto > 0 ? lancamento.valor_aberto : lancamento.valor;

  const form = useForm({
    valor_baixa: aberto,
    conta_bancaria_id: (contas[0]?.id ?? '') as number | '',
    meio_pagamento: '' as string,
    plano_conta_id: lancamento.plano_conta_id as number | null,
    data_baixa: hojeIso(),
  });

  // Reset ao trocar de lançamento.
  useEffect(() => {
    form.setData({
      valor_baixa: aberto,
      conta_bancaria_id: (contas[0]?.id ?? '') as number | '',
      meio_pagamento: '' as string,
      plano_conta_id: lancamento.plano_conta_id as number | null,
      data_baixa: hojeIso(),
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [lancamento.id, open]);

  const parcial = form.data.valor_baixa > 0 && form.data.valor_baixa < aberto - 0.001;

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const data: Record<string, unknown> = {
      valor_baixa: form.data.valor_baixa,
      conta_bancaria_id: form.data.conta_bancaria_id === '' ? null : form.data.conta_bancaria_id,
      meio_pagamento: form.data.meio_pagamento || null,
      plano_conta_id: form.data.plano_conta_id ?? null,
      data_baixa: form.data.data_baixa,
    };
    form.post(`/financeiro/unificado/${lancamento.id}/baixar`, {
      ...({ data } as Record<string, unknown>),
      preserveScroll: true,
      onSuccess: () => onClose(),
    });
  };

  const titulo = receber ? 'Receber lançamento' : 'Pagar lançamento';
  const Icon = receber ? ArrowDownCircle : ArrowUpCircle;
  const accentHue = receber ? 145 : 25;

  return (
    <Sheet open={open} onOpenChange={(o) => !o && onClose()}>
      <SheetContent side="right" className="w-[460px] sm:max-w-[460px] flex flex-col p-0">
        <header className="os-drawer-head">
          <div className="os-drawer-head-l">
            <SheetTitle asChild>
              <h2 className="m-0 flex items-center gap-2">
                <Icon size={16} style={{ color: `oklch(0.55 0.15 ${accentHue})` }} />
                {titulo}
              </h2>
            </SheetTitle>
            <SheetDescription asChild>
              <p>{lancamento.contraparte} · aberto {brl(aberto)}</p>
            </SheetDescription>
          </div>
        </header>

        <form onSubmit={submit} className="os-drawer-body p-5 space-y-4 text-[13px] flex-1 overflow-y-auto">
          <div className="space-y-1.5">
            <label htmlFor="bx-valor" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
              Valor {receber ? 'a receber' : 'a pagar'}
            </label>
            {/* Input formatado como moeda BRL: usuário digita dígitos, vira R$ [redacted Tier 0] */}
            <Input
              id="bx-valor"
              type="text"
              inputMode="decimal"
              value={brl(form.data.valor_baixa)}
              onChange={(e) => {
                const digits = e.target.value.replace(/\D/g, '');
                const cents = digits === '' ? 0 : parseInt(digits, 10);
                form.setData('valor_baixa', cents / 100);
              }}
            />
            <div className="flex items-center gap-2 text-[11px]">
              <button type="button" className="underline text-muted-foreground hover:text-foreground" onClick={() => form.setData('valor_baixa', aberto)}>
                valor cheio ({brl(aberto)})
              </button>
              {parcial && (
                <span className="text-amber-700">baixa parcial · resta {brl(Math.max(0, aberto - form.data.valor_baixa))}</span>
              )}
            </div>
            {form.errors.valor_baixa && <p className="text-[11px] text-destructive">{form.errors.valor_baixa}</p>}
          </div>

          <div className="space-y-1.5">
            <label htmlFor="bx-conta" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
              Conta {receber ? 'de destino' : 'de origem'}
            </label>
            <Select
              value={form.data.conta_bancaria_id === '' ? '' : String(form.data.conta_bancaria_id)}
              onValueChange={(v) => form.setData('conta_bancaria_id', (v === '' ? '' : Number(v)) as number | '')}
            >
              <SelectTrigger id="bx-conta" aria-label="Conta bancária">
                <SelectValue placeholder="Selecione a conta" />
              </SelectTrigger>
              <SelectContent>
                {contas.map((c) => (
                  <SelectItem key={c.id} value={String(c.id)}>{c.nome}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            {contas.length === 0 && (
              <p className="text-[11px] text-destructive">Sem conta cadastrada. Cadastre em /financeiro/contas-bancarias.</p>
            )}
            {form.errors.conta_bancaria_id && <p className="text-[11px] text-destructive">{form.errors.conta_bancaria_id}</p>}
          </div>

          <div className="space-y-1.5">
            <label htmlFor="bx-forma" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
              Forma de pagamento
            </label>
            <Select
              value={form.data.meio_pagamento || '__none__'}
              onValueChange={(v) => form.setData('meio_pagamento', v === '__none__' ? '' : v)}
            >
              <SelectTrigger id="bx-forma" aria-label="Forma de pagamento">
                <SelectValue placeholder="— escolha —" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none__">— escolha —</SelectItem>
                {FORMA_PAGAMENTO_OPCOES.map((o) => (
                  <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            {form.errors.meio_pagamento && <p className="text-[11px] text-destructive">{form.errors.meio_pagamento}</p>}
          </div>

          <div className="space-y-1.5">
            <label htmlFor="bx-plano" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
              Plano de contas
            </label>
            <PlanoContaCombobox
              id="bx-plano"
              planos={planos}
              kind={lancamento.kind}
              value={form.data.plano_conta_id}
              onChange={(idp) => form.setData('plano_conta_id', idp)}
              placeholder={receber ? 'Receita ou ativo' : 'Despesa, custo ou passivo'}
            />
            {form.errors.plano_conta_id && <p className="text-[11px] text-destructive">{form.errors.plano_conta_id}</p>}
          </div>

          <div className="space-y-1.5">
            <label htmlFor="bx-data" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
              Data da baixa
            </label>
            <Input
              id="bx-data"
              type="date"
              value={form.data.data_baixa}
              onChange={(e) => form.setData('data_baixa', e.target.value)}
            />
            {form.errors.data_baixa && <p className="text-[11px] text-destructive">{form.errors.data_baixa}</p>}
          </div>

          <div className="flex gap-2 pt-3 border-t border-border mt-auto">
            <Button
              type="submit"
              disabled={form.processing || contas.length === 0}
              style={{ backgroundColor: `oklch(0.55 0.15 ${accentHue})`, color: 'oklch(0.99 0 0)' }}
            >
              {form.processing ? 'Salvando…' : (receber ? '✓ Confirmar recebimento' : '✓ Confirmar pagamento')}
            </Button>
            <Button type="button" variant="outline" onClick={onClose} disabled={form.processing}>
              Cancelar
            </Button>
          </div>
        </form>
      </SheetContent>
    </Sheet>
  );
}

export default FinBaixaSheet;
