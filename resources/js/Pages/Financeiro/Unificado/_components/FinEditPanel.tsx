// FinEditPanel — Cowork KB-9.75 Financeiro Onda 10 (canon 100%)
// (panel inline de edição — substitui preview readOnly da V2.1).
//
// Refs:
//  - prototipo-ui-patch/vendas-financeiro-completo/financeiro-curation.jsx FinEditPanel (linha 138)
//  - TituloEditSheet.tsx (Sheet separado existente — preservado pra fallback)
//
// Form REAL com inputs editáveis + submit Inertia useForm.
// PUT /financeiro/unificado/{id} — mesmo endpoint do TituloEditSheet.
//
// valor_total imutável pós-baixa (ADR fin-tech/0002) — não envia campo se
// valor_mutavel=false. Diff visual "era X → agora Y" inline em pequenos hints.

import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select';
import { FORMA_PAGAMENTO_OPCOES, formaPagamentoLabel } from '../_lib/forma-pagamento';

interface Lancamento {
  id: number;
  contraparte: string;
  contraparte_doc: string | null;
  categoria: string;
  categoria_id: number | null;
  vencimento: string;
  vencimento_label: string;
  valor: number;
  descricao: string;
  observacao: string | null;
  valor_mutavel: boolean;
  // 2026-06-03 — forma de pagamento prevista (editável só se não-realizada).
  forma_pagamento: string | null;
  forma_pagamento_realizada: boolean;
}

interface Categoria {
  id: number;
  nome: string;
}

interface FinEditPanelProps {
  lancamento: Lancamento;
  categorias: Categoria[];
  onClose: () => void;
}

function brl(n: number): string {
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 2 });
}

export function FinEditPanel({ lancamento, categorias, onClose }: FinEditPanelProps) {
  const form = useForm({
    cliente_descricao: lancamento.contraparte === '—' ? '' : lancamento.contraparte,
    observacoes: lancamento.observacao ?? '',
    categoria_id: lancamento.categoria_id !== null ? String(lancamento.categoria_id) : '',
    vencimento: lancamento.vencimento,
    valor_total: lancamento.valor,
    forma_pagamento: lancamento.forma_pagamento ?? '',
  });

  // Reset on row change
  useEffect(() => {
    form.setData({
      cliente_descricao: lancamento.contraparte === '—' ? '' : lancamento.contraparte,
      observacoes: lancamento.observacao ?? '',
      categoria_id: lancamento.categoria_id !== null ? String(lancamento.categoria_id) : '',
      vencimento: lancamento.vencimento,
      valor_total: lancamento.valor,
      forma_pagamento: lancamento.forma_pagamento ?? '',
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [lancamento.id]);

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const data: Record<string, unknown> = {
      cliente_descricao: form.data.cliente_descricao || null,
      observacoes: form.data.observacoes || null,
      categoria_id: form.data.categoria_id === '' ? null : Number(form.data.categoria_id),
      vencimento: form.data.vencimento,
    };
    if (lancamento.valor_mutavel) {
      data.valor_total = form.data.valor_total;
    }
    // Forma de pagamento só é editável quando NÃO-realizada (sem baixa). Quando
    // realizada, a forma vem da baixa (read-only) e não vai no payload.
    if (!lancamento.forma_pagamento_realizada) {
      data.forma_pagamento = form.data.forma_pagamento || null;
    }
    form.put(`/financeiro/unificado/${lancamento.id}`, {
      ...({ data } as Record<string, unknown>),
      preserveScroll: true,
      onSuccess: () => onClose(),
    });
  };

  const hasEdits =
    form.data.cliente_descricao !== (lancamento.contraparte === '—' ? '' : lancamento.contraparte) ||
    form.data.observacoes !== (lancamento.observacao ?? '') ||
    form.data.categoria_id !== (lancamento.categoria_id !== null ? String(lancamento.categoria_id) : '') ||
    form.data.vencimento !== lancamento.vencimento ||
    (lancamento.valor_mutavel && form.data.valor_total !== lancamento.valor) ||
    (!lancamento.forma_pagamento_realizada && form.data.forma_pagamento !== (lancamento.forma_pagamento ?? ''));

  return (
    // Onda 15 (2026-05-19) — adiciona classes canon os-drawer-* sem remover fin-edit-*
    // (back-compat com CSS legacy). Visualmente herda do os-drawer-head canon agora.
    <form onSubmit={submit} className="fin-edit-panel os-drawer-body">
      <div className="fin-edit-h os-drawer-head">
        <div className="os-drawer-head-l">
          <h2 style={{ margin: 0, fontSize: 16 }}>✎ Editar lançamento</h2>
          <p style={{ margin: 0, fontSize: 12 }}>Campos editáveis · {lancamento.valor_mutavel ? 'valor mutável' : 'valor IMUTÁVEL (pós-baixa)'}</p>
        </div>
      </div>

      <div className="fin-edit-grid">
        <label>
          <span>Valor</span>
          <input
            type="number"
            step="0.01"
            min="0"
            value={form.data.valor_total}
            disabled={!lancamento.valor_mutavel}
            onChange={(e) => form.setData('valor_total', parseFloat(e.target.value) || 0)}
          />
          {!lancamento.valor_mutavel && (
            <small style={{ color: 'oklch(0.50 0 0)', fontSize: 10 }}>ADR fin-tech/0002</small>
          )}
          {lancamento.valor_mutavel && form.data.valor_total !== lancamento.valor && (
            <small style={{ color: 'oklch(0.50 0 0)', fontSize: 10 }}>
              era {brl(lancamento.valor)}
            </small>
          )}
        </label>

        <label>
          <span>Vencimento</span>
          <input
            type="date"
            value={form.data.vencimento}
            onChange={(e) => form.setData('vencimento', e.target.value)}
          />
          {form.data.vencimento !== lancamento.vencimento && (
            <small style={{ color: 'oklch(0.50 0 0)', fontSize: 10 }}>era {lancamento.vencimento_label}</small>
          )}
        </label>

        <label htmlFor="fin-edit-categoria">
          <span>Categoria</span>
          <Select
            value={form.data.categoria_id || '__none__'}
            onValueChange={(v) => form.setData('categoria_id', v === '__none__' ? '' : v)}
          >
            <SelectTrigger id="fin-edit-categoria" aria-label="Categoria">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__none__">— sem categoria —</SelectItem>
              {categorias.map((c) => (
                <SelectItem key={c.id} value={String(c.id)}>
                  {c.nome}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </label>

        {/* 2026-06-03 — Forma de pagamento. Editável só quando não-realizada;
            quando há baixa, mostra read-only o que realmente foi pago. */}
        <label htmlFor="fin-edit-forma">
          <span>Forma de pagamento</span>
          {lancamento.forma_pagamento_realizada ? (
            <input
              type="text"
              value={formaPagamentoLabel(lancamento.forma_pagamento)}
              disabled
              aria-label="Forma de pagamento (da baixa)"
            />
          ) : (
            <Select
              value={form.data.forma_pagamento || '__none__'}
              onValueChange={(v) => form.setData('forma_pagamento', v === '__none__' ? '' : v)}
            >
              <SelectTrigger id="fin-edit-forma" aria-label="Forma de pagamento">
                <SelectValue placeholder="— a definir —" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none__">— a definir —</SelectItem>
                {FORMA_PAGAMENTO_OPCOES.map((o) => (
                  <SelectItem key={o.value} value={o.value}>
                    {o.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
          {lancamento.forma_pagamento_realizada && (
            <small style={{ color: 'oklch(0.50 0 0)', fontSize: 10 }}>da baixa (read-only)</small>
          )}
        </label>

        <label className="fin-edit-wide">
          <span>Cliente / Fornecedor</span>
          <input
            type="text"
            value={form.data.cliente_descricao}
            onChange={(e) => form.setData('cliente_descricao', e.target.value)}
          />
        </label>

        <label className="fin-edit-wide">
          <span>Observações</span>
          <textarea
            rows={2}
            value={form.data.observacoes}
            onChange={(e) => form.setData('observacoes', e.target.value)}
          />
        </label>
      </div>

      {form.hasErrors && (
        <div className="rounded-md border border-destructive/30 bg-destructive-soft px-3 py-2 mt-3 text-[12px] text-destructive-fg">
          {Object.entries(form.errors).map(([key, msg]) => (
            <div key={key}>
              <b>{key}:</b> {msg as string}
            </div>
          ))}
        </div>
      )}

      <div className="fin-edit-footer">
        <button type="button" className="fin-edit-cancel" onClick={onClose}>
          Cancelar
        </button>
        <button
          type="submit"
          className="fin-edit-close"
          disabled={form.processing || !hasEdits}
        >
          {form.processing ? 'Salvando…' : '✓ Salvar alterações'}
        </button>
      </div>
    </form>
  );
}

export default FinEditPanel;
