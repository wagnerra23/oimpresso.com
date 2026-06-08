// TituloEditSheet — Cowork KB-9.75 Financeiro Onda Edit (2026-05-18)
// Sheet drawer inline pra editar campos seguros do título financeiro.
//
// Campos editáveis (R-FIN-008):
//   - cliente_descricao (texto livre — pode renomear/anotar contraparte)
//   - observacoes (texto livre)
//   - categoria_id (dropdown de Categoria do business)
//   - plano_conta_id (combobox DCASP BR filtrado por kind — Onda 24 US-FIN-021)
//   - vencimento (date)
//   - valor_total (number — SOMENTE se status aberto/parcial; bloqueado se quitado/cancelado)
//
// Imutabilidade (ADR fin-tech/0002): tipo, origem, status, emissao NUNCA editáveis aqui.
// PUT /financeiro/unificado/{id} via Inertia useForm — back() + flash success do controller.

import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Sheet, SheetContent, SheetTitle, SheetDescription } from '@/Components/ui/sheet';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select';
import { PlanoContaCombobox, type PlanoConta } from './PlanoContaCombobox';
import { FORMA_PAGAMENTO_OPCOES, formaPagamentoLabel } from '../_lib/forma-pagamento';

interface Categoria {
  id: number;
  nome: string;
}

interface ContaOpt {
  id: number;
  nome: string;
}

interface Lancamento {
  id: number;
  kind: 'receivable' | 'payable';
  descricao: string;
  contraparte: string;
  categoria: string;
  categoria_id: number | null;
  plano_conta_id: number | null;
  plano_conta_codigo: string | null;
  plano_conta_nome: string | null;
  vencimento: string;
  valor: number;
  observacao: string | null;
  valor_mutavel: boolean;
  status: string;
  // 2026-06-03 — forma de pagamento. Editável só se não-realizada (sem baixa).
  forma_pagamento: string | null;
  forma_pagamento_realizada: boolean;
  // 2026-06-04 — conta bancária prevista (editável).
  conta_bancaria_id: number | null;
}

interface TituloEditSheetProps {
  open: boolean;
  onClose: () => void;
  lancamento: Lancamento;
  categorias: Categoria[];
  planos: PlanoConta[];
  contas: ContaOpt[];
}

export function TituloEditSheet({ open, onClose, lancamento, categorias, planos, contas }: TituloEditSheetProps) {
  const form = useForm({
    cliente_descricao: lancamento.contraparte === '—' ? '' : lancamento.contraparte,
    observacoes: lancamento.observacao ?? '',
    categoria_id: lancamento.categoria_id ?? '',
    plano_conta_id: lancamento.plano_conta_id as number | null,
    vencimento: lancamento.vencimento,
    valor_total: lancamento.valor,
    forma_pagamento: lancamento.forma_pagamento ?? '',
    conta_bancaria_id: (lancamento.conta_bancaria_id ?? '') as number | '',
  });

  // Reset form quando trocar de lançamento sem fechar.
  useEffect(() => {
    form.setData({
      cliente_descricao: lancamento.contraparte === '—' ? '' : lancamento.contraparte,
      observacoes: lancamento.observacao ?? '',
      categoria_id: lancamento.categoria_id ?? '',
      plano_conta_id: lancamento.plano_conta_id as number | null,
      vencimento: lancamento.vencimento,
      valor_total: lancamento.valor,
      forma_pagamento: lancamento.forma_pagamento ?? '',
      conta_bancaria_id: (lancamento.conta_bancaria_id ?? '') as number | '',
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [lancamento.id]);

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    // Remove valor_total do payload se imutável (back-end também rejeita por defesa).
    const data: Record<string, unknown> = {
      cliente_descricao: form.data.cliente_descricao || null,
      observacoes: form.data.observacoes || null,
      categoria_id: form.data.categoria_id === '' ? null : form.data.categoria_id,
      plano_conta_id: form.data.plano_conta_id ?? null,
      vencimento: form.data.vencimento,
    };
    if (lancamento.valor_mutavel) {
      data.valor_total = form.data.valor_total;
    }
    data.conta_bancaria_id = form.data.conta_bancaria_id === '' ? null : form.data.conta_bancaria_id;
    // Forma só editável quando NÃO-realizada (sem baixa). Realizada vem da baixa (read-only).
    if (!lancamento.forma_pagamento_realizada) {
      data.forma_pagamento = form.data.forma_pagamento || null;
    }
    form.put(`/financeiro/unificado/${lancamento.id}`, {
      ...({ data } as any),
      preserveScroll: true,
      onSuccess: () => onClose(),
    });
  };

  return (
    <Sheet open={open} onOpenChange={(o) => !o && onClose()}>
      <SheetContent side="right" className="w-[460px] sm:max-w-[460px] flex flex-col p-0">
        {/* Onda 13 (2026-05-19) — header canon os-drawer-head paridade canon REAL */}
        <header className="os-drawer-head">
          <div className="os-drawer-head-l">
            <SheetTitle asChild><h2 className="m-0">Editar lançamento</h2></SheetTitle>
            <SheetDescription asChild>
              <p>#{lancamento.id} · {lancamento.status === 'quitado' ? 'Já quitado — valor imutável' : 'Campos seguros editáveis'}</p>
            </SheetDescription>
          </div>
        </header>

        <form onSubmit={submit} className="os-drawer-body p-5 space-y-4 text-[13px] flex-1 overflow-y-auto">
          <div className="space-y-1.5">
            <label htmlFor="ed-desc" className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">
              Descrição / contraparte
            </label>
            <Input
              id="ed-desc"
              value={form.data.cliente_descricao}
              onChange={(e) => form.setData('cliente_descricao', e.target.value)}
              placeholder="Ex: Banner lona 4×1m · #V-7832"
              maxLength={255}
            />
            <p className="text-[11px] text-stone-500">
              Tokens <code>#V-</code> <code>#OS-</code> <code>#PC-</code> <code>#BL-</code> viram links clicáveis na lista.
            </p>
            {form.errors.cliente_descricao && (
              <p className="text-[11px] text-rose-600">{form.errors.cliente_descricao}</p>
            )}
          </div>

          <div className="space-y-1.5">
            <label htmlFor="ed-cat" className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">
              Categoria
            </label>
            <Select
              value={form.data.categoria_id === '' ? '__none__' : String(form.data.categoria_id)}
              onValueChange={(v) => form.setData('categoria_id', (v === '__none__' ? '' : v) as unknown as number | '')}
            >
              <SelectTrigger id="ed-cat" aria-label="Categoria">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none__">(Sem categoria)</SelectItem>
                {categorias.map((c) => (
                  <SelectItem key={c.id} value={String(c.id)}>{c.nome}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            {form.errors.categoria_id && (
              <p className="text-[11px] text-rose-600">{form.errors.categoria_id}</p>
            )}
          </div>

          {/* 2026-06-03 — Forma de pagamento. Editável só se não-realizada (sem baixa);
              quando pago, mostra read-only a forma que veio da baixa. */}
          <div className="space-y-1.5">
            <label htmlFor="ed-forma" className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">
              Forma de pagamento
              {lancamento.forma_pagamento_realizada && (
                <span className="ml-2 normal-case text-[10px] text-stone-500">· da baixa (read-only)</span>
              )}
            </label>
            {lancamento.forma_pagamento_realizada ? (
              <Input value={formaPagamentoLabel(lancamento.forma_pagamento)} disabled className="bg-stone-100 cursor-not-allowed" />
            ) : (
              <Select
                value={form.data.forma_pagamento || '__none__'}
                onValueChange={(v) => form.setData('forma_pagamento', v === '__none__' ? '' : v)}
              >
                <SelectTrigger id="ed-forma" aria-label="Forma de pagamento">
                  <SelectValue placeholder="— a definir —" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__none__">— a definir —</SelectItem>
                  {FORMA_PAGAMENTO_OPCOES.map((o) => (
                    <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
            {form.errors.forma_pagamento && (
              <p className="text-[11px] text-destructive">{form.errors.forma_pagamento}</p>
            )}
          </div>

          {/* 2026-06-04 — Conta bancária (prevista). Pedido Wagner: trocar conta no Editar. */}
          <div className="space-y-1.5">
            <label htmlFor="ed-conta" className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">
              Conta bancária
            </label>
            <Select
              value={form.data.conta_bancaria_id === '' ? '__none__' : String(form.data.conta_bancaria_id)}
              onValueChange={(v) => form.setData('conta_bancaria_id', (v === '__none__' ? '' : Number(v)) as number | '')}
            >
              <SelectTrigger id="ed-conta" aria-label="Conta bancária">
                <SelectValue placeholder="— escolha —" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none__">— escolha —</SelectItem>
                {contas.map((c) => (
                  <SelectItem key={c.id} value={String(c.id)}>{c.nome}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            {form.errors.conta_bancaria_id && (
              <p className="text-[11px] text-destructive">{form.errors.conta_bancaria_id}</p>
            )}
          </div>

          {/* Onda 24 (2026-05-25) US-FIN-021 — Plano de Contas BR DCASP filtrado por kind. */}
          <div className="space-y-1.5">
            <label htmlFor="ed-plano" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
              Plano de contas
            </label>
            <PlanoContaCombobox
              id="ed-plano"
              planos={planos}
              kind={lancamento.kind}
              value={form.data.plano_conta_id}
              onChange={(id) => form.setData('plano_conta_id', id)}
              placeholder={lancamento.kind === 'receivable' ? 'Receita ou ativo' : 'Despesa, custo ou passivo'}
            />
            <p className="text-[11px] text-muted-foreground">
              Filtrado por tipo do título (DRE entra direto na classificação certa).
            </p>
            {form.errors.plano_conta_id && (
              <p className="text-[11px] text-destructive">{form.errors.plano_conta_id}</p>
            )}
          </div>

          <div className="space-y-1.5">
            <label htmlFor="ed-venc" className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">
              Vencimento
            </label>
            <Input
              id="ed-venc"
              type="date"
              value={form.data.vencimento}
              onChange={(e) => form.setData('vencimento', e.target.value)}
            />
            {form.errors.vencimento && (
              <p className="text-[11px] text-rose-600">{form.errors.vencimento}</p>
            )}
          </div>

          <div className="space-y-1.5">
            <label htmlFor="ed-valor" className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">
              Valor
              {!lancamento.valor_mutavel && (
                <span className="ml-2 normal-case text-[10px] text-amber-700">🔒 imutável pós-baixa</span>
              )}
            </label>
            <Input
              id="ed-valor"
              type="number"
              step="0.01"
              min="0.01"
              value={form.data.valor_total}
              onChange={(e) => form.setData('valor_total', parseFloat(e.target.value) || 0)}
              disabled={!lancamento.valor_mutavel}
              className={!lancamento.valor_mutavel ? 'bg-stone-100 cursor-not-allowed' : ''}
            />
            {!lancamento.valor_mutavel && (
              <p className="text-[11px] text-stone-500">
                Valor de título quitado/cancelado preserva histórico contábil. Pra corrigir, cancele e crie novo.
              </p>
            )}
            {form.errors.valor_total && (
              <p className="text-[11px] text-rose-600">{form.errors.valor_total}</p>
            )}
          </div>

          <div className="space-y-1.5">
            <label htmlFor="ed-obs" className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">
              Observações
            </label>
            <textarea
              id="ed-obs"
              value={form.data.observacoes}
              onChange={(e) => form.setData('observacoes', e.target.value)}
              maxLength={2000}
              rows={4}
              className="w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-[13px] resize-none"
            />
            {form.errors.observacoes && (
              <p className="text-[11px] text-rose-600">{form.errors.observacoes}</p>
            )}
          </div>

          <div className="flex gap-2 pt-3 border-t border-stone-200 mt-auto">
            <Button type="submit" disabled={form.processing}>
              {form.processing ? 'Salvando…' : 'Salvar'}
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

export default TituloEditSheet;
