// ServiceOrderItemRow — card per-item lançado na OS (Wave 5 US-OFICINA-005-bis).
//
// Espelha visual da seção "PEÇAS & MÃO DE OBRA" do drawer ServiceOrderRichSheet.tsx
// (PR #1624 Wave 2.3) mas com hover actions Editar/Excluir pra UI de gestão
// inline em Show.tsx + Edit.tsx (não read-only mais).
//
// Multi-tenant Tier 0 [ADR 0093]: callbacks `onEdit`/`onDelete` apenas notificam
// pai — quem chama Controller (que tem global scope + Policy update). NUNCA
// envia business_id no payload.
//
// CRÍTICO React 19 — handlers descendentes do pai precisam ser useCallback estável.

import { Package, Pencil, Trash2, UserCog, Wrench } from 'lucide-react';

export type ItemTipo = 'peca' | 'mao_obra' | 'servico_terceiro';

export interface ServiceOrderItemDto {
  id: number;
  tipo: ItemTipo;
  descricao: string;
  quantidade: number | string;
  valor_unitario: number | string;
  valor_total: number | string;
  product_id: number | null;
  notes: string | null;
}

interface Props {
  item: ServiceOrderItemDto;
  onEdit: (item: ServiceOrderItemDto) => void;
  onDelete: (item: ServiceOrderItemDto) => void;
  /** Desabilita actions (durante save/delete em flight). */
  busy?: boolean;
}

const TIPO_LABEL: Record<ItemTipo, string> = {
  peca: 'Peça',
  mao_obra: 'Mão de obra',
  servico_terceiro: 'Serviço terceiro',
};

const TIPO_ICON: Record<ItemTipo, typeof Wrench> = {
  peca: Package,
  mao_obra: Wrench,
  servico_terceiro: UserCog,
};

function toFloat(value: number | string | null | undefined): number {
  if (value === null || value === undefined || value === '') return 0;
  const n = typeof value === 'string' ? parseFloat(value) : value;
  return Number.isNaN(n) ? 0 : n;
}

function formatBRL(value: number | string | null | undefined): string {
  const num = toFloat(value);
  return num.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export default function ServiceOrderItemRow({ item, onEdit, onDelete, busy = false }: Props) {
  const Icon = TIPO_ICON[item.tipo];
  const qtd = toFloat(item.quantidade);
  const vu = toFloat(item.valor_unitario);
  const total = toFloat(item.valor_total);

  return (
    <li className="group px-3 py-2 grid grid-cols-[auto_1fr_auto_auto] gap-2 items-center text-[11.5px] hover:bg-slate-50/80 transition-colors">
      <Icon size={14} className="text-muted-foreground shrink-0" aria-hidden />
      <div className="min-w-0">
        <div className="text-foreground font-medium truncate">{item.descricao}</div>
        <div className="text-muted-foreground text-[10.5px]">
          {TIPO_LABEL[item.tipo]} ·{' '}
          {qtd.toLocaleString('pt-BR', { maximumFractionDigits: 3 })} × {formatBRL(vu)}
        </div>
      </div>
      <div className="text-foreground tabular-nums font-medium whitespace-nowrap">
        {formatBRL(total)}
      </div>
      {/* Hover actions — visíveis sempre em mobile (group-hover requer hover capable device).
          Acessibilidade: focus visible via :focus-visible nativo, opacity-100 ao focus. */}
      <div className="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity">
        <button
          type="button"
          onClick={() => onEdit(item)}
          disabled={busy}
          className="inline-flex items-center justify-center h-6 w-6 rounded hover:bg-slate-200 text-muted-foreground hover:text-foreground disabled:opacity-40 disabled:cursor-not-allowed"
          title="Editar item"
          aria-label="Editar item"
        >
          <Pencil size={11} />
        </button>
        <button
          type="button"
          onClick={() => onDelete(item)}
          disabled={busy}
          className="inline-flex items-center justify-center h-6 w-6 rounded hover:bg-rose-100 text-muted-foreground hover:text-rose-700 disabled:opacity-40 disabled:cursor-not-allowed"
          title="Excluir item"
          aria-label="Excluir item"
        >
          <Trash2 size={11} />
        </button>
      </div>
    </li>
  );
}
