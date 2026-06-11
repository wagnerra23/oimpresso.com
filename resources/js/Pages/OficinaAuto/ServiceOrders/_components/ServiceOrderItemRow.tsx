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

import { Box, Inline } from '@/Components/layout';

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

// Record<string> (não Record<ItemTipo>) + lookup com fallback: blinda contra um
// `tipo` fora do enum vindo do backend/dado legado. Sem isso, um tipo não-mapeado
// faz TIPO_ICON[tipo] = undefined → <Icon/> = React #130 = TELA BRANCA na OS
// inteira (incidente 2026-06-10 — item com tipo='servico' fora do enum).
const TIPO_LABEL: Record<string, string> = {
  peca: 'Peça',
  mao_obra: 'Mão de obra',
  servico: 'Serviço',
  servico_terceiro: 'Serviço terceiro',
};

const TIPO_ICON: Record<string, typeof Wrench> = {
  peca: Package,
  mao_obra: Wrench,
  servico: Wrench,
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
  const Icon = TIPO_ICON[item.tipo] ?? Wrench;
  const tipoLabel = TIPO_LABEL[item.tipo] ?? item.tipo;
  const qtd = toFloat(item.quantidade);
  const vu = toFloat(item.valor_unitario);
  const total = toFloat(item.valor_total);

  // Layout via primitivos (ADR 0253 · F3): o row é uma composição horizontal
  // (Inline) — adeus `grid grid-cols-[auto_1fr_auto_auto]` solto. `asChild` mantém
  // o elemento semântico <li>. A descrição cresce (flex-1), o resto é content-width
  // (shrink-0). Cluster de ações é outro Inline.
  return (
    <Inline asChild gap={2} align="center">
      <li className="group px-3 py-2 text-[11.5px] hover:bg-muted/50 transition-colors">
        <Icon size={14} className="text-muted-foreground shrink-0" aria-hidden />
        <Box className="min-w-0 flex-1">
          <div className="text-foreground font-medium truncate">{item.descricao}</div>
          <div className="text-muted-foreground text-[10.5px]">
            {tipoLabel} ·{' '}
            {qtd.toLocaleString('pt-BR', { maximumFractionDigits: 3 })} × {formatBRL(vu)}
          </div>
        </Box>
        <div className="shrink-0 text-foreground tabular-nums font-medium whitespace-nowrap">
          {formatBRL(total)}
        </div>
        {/* Hover actions — visíveis sempre em mobile (group-hover requer hover capable device).
            Acessibilidade: focus visible via :focus-visible nativo, opacity-100 ao focus. */}
        <Inline align="center" className="shrink-0 gap-0.5 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity">
          <button
            type="button"
            onClick={() => onEdit(item)}
            disabled={busy}
            className="inline-flex items-center justify-center h-6 w-6 rounded hover:bg-accent text-muted-foreground hover:text-accent-foreground disabled:opacity-40 disabled:cursor-not-allowed"
            title="Editar item"
            aria-label="Editar item"
          >
            <Pencil size={11} />
          </button>
          <button
            type="button"
            onClick={() => onDelete(item)}
            disabled={busy}
            className="inline-flex items-center justify-center h-6 w-6 rounded hover:bg-destructive/10 text-muted-foreground hover:text-destructive disabled:opacity-40 disabled:cursor-not-allowed"
            title="Excluir item"
            aria-label="Excluir item"
          >
            <Trash2 size={11} />
          </button>
        </Inline>
      </li>
    </Inline>
  );
}
