// TituloEditSheet — Cowork KB-9.75 Financeiro Onda Edit (2026-05-18)
// Sheet drawer inline pra editar campos seguros do título financeiro.
//
// Campos editáveis (R-FIN-008):
//   - cliente_descricao (texto livre — pode renomear/anotar contraparte)
//   - observacoes (texto livre)
//   - categoria_id (dropdown de Categoria do business)
//   - vencimento (date)
//   - valor_total (number — SOMENTE se status aberto/parcial; bloqueado se quitado/cancelado)
//
// Imutabilidade (ADR fin-tech/0002): tipo, origem, status, emissao NUNCA editáveis aqui.
// PUT /financeiro/unificado/{id} via Inertia useForm — back() + flash success do controller.

import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from '@/Components/ui/sheet';

interface Categoria {
  id: number;
  nome: string;
}

interface Lancamento {
  id: number;
  descricao: string;
  contraparte: string;
  categoria: string;
  categoria_id: number | null;
  vencimento: string;
  valor: number;
  observacao: string | null;
  valor_mutavel: boolean;
  status: string;
}

interface TituloEditSheetProps {
  open: boolean;
  onClose: () => void;
  lancamento: Lancamento;
  categorias: Categoria[];
}

export function TituloEditSheet({ open, onClose, lancamento, categorias }: TituloEditSheetProps) {
  const form = useForm({
    cliente_descricao: lancamento.contraparte === '—' ? '' : lancamento.contraparte,
    observacoes: lancamento.observacao ?? '',
    categoria_id: lancamento.categoria_id ?? '',
    vencimento: lancamento.vencimento,
    valor_total: lancamento.valor,
  });

  // Reset form quando trocar de lançamento sem fechar.
  useEffect(() => {
    form.setData({
      cliente_descricao: lancamento.contraparte === '—' ? '' : lancamento.contraparte,
      observacoes: lancamento.observacao ?? '',
      categoria_id: lancamento.categoria_id ?? '',
      vencimento: lancamento.vencimento,
      valor_total: lancamento.valor,
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
      vencimento: form.data.vencimento,
    };
    if (lancamento.valor_mutavel) {
      data.valor_total = form.data.valor_total;
    }
    form.put(`/financeiro/unificado/${lancamento.id}`, {
      ...({ data } as any),
      preserveScroll: true,
      onSuccess: () => onClose(),
    });
  };

  return (
    <Sheet open={open} onOpenChange={(o) => !o && onClose()}>
      <SheetContent side="right" className="w-[460px] sm:max-w-[460px] flex flex-col">
        <SheetHeader>
          <SheetTitle className="text-[15px]">Editar lançamento</SheetTitle>
          <SheetDescription className="text-[12px] text-stone-500">
            #{lancamento.id} · {lancamento.status === 'quitado' ? 'Já quitado — valor imutável' : 'Campos seguros editáveis'}
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={submit} className="mt-4 space-y-4 text-[13px] flex-1 overflow-y-auto pr-1">
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
            <select
              id="ed-cat"
              value={form.data.categoria_id as string | number}
              onChange={(e) => form.setData('categoria_id', e.target.value as unknown as number | '')}
              className="w-full h-9 rounded-md border border-stone-300 bg-white px-3 text-[13px]"
            >
              <option value="">(Sem categoria)</option>
              {categorias.map((c) => (
                <option key={c.id} value={c.id}>{c.nome}</option>
              ))}
            </select>
            {form.errors.categoria_id && (
              <p className="text-[11px] text-rose-600">{form.errors.categoria_id}</p>
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
