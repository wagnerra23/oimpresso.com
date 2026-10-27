// Coluna e card do funil de vendas (PT-05 Kanban · US-CRM-092).
// Drag canon: useDraggable/useDroppable (@dnd-kit) com o KanbanDndProvider compartilhado;
// data {subjectId, currentColumn, subject} e {columnStatus} — o contrato do provider.

import { memo } from 'react';
import { useDraggable, useDroppable } from '@dnd-kit/core';
import { Badge } from '@/Components/ui/badge';

export interface Etapa {
  id: string;
  rotulo: string;
  probabilidade: number;
  terminal: boolean;
}

export interface Negocio {
  id: number;
  titulo: string;
  etapa: string;
  valor: number;
  fechamento_previsto: string | null;
  atualizado_em: string;
  contato_id: number | null;
  contato: string | null;
  responsavel: string | null;
  /** US-CRM-096 — 'formulario' (lead do site) ou 'manual'. */
  origem: string;
  /** Resumo dos UTM ("google / cpc · outubro"); null sem UTM. */
  utm: string | null;
}

export const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });

/** Tom da borda superior por etapa — tokens semânticos, sem cor crua. */
const TOM: Record<string, string> = {
  lead: 'border-t-muted-foreground/40',
  qualificacao: 'border-t-primary/60',
  proposta: 'border-t-warning',
  negociacao: 'border-t-info',
  ganho: 'border-t-success',
  perdido: 'border-t-destructive',
};

function NegocioCardImpl({ negocio, podeArrastar, onAbrir }: { negocio: Negocio; podeArrastar: boolean; onAbrir: (n: Negocio) => void }) {
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: `negocio-${negocio.id}`,
    disabled: !podeArrastar,
    data: { subjectId: negocio.id, currentColumn: negocio.etapa, subject: negocio },
  });
  const estilo = transform ? { transform: `translate3d(${transform.x}px, ${transform.y}px, 0)` } : undefined;

  return (
    <button
      type="button"
      ref={setNodeRef}
      style={estilo}
      {...attributes}
      {...listeners}
      onClick={() => onAbrir(negocio)}
      className={
        'flex min-h-11 w-full flex-col gap-1 rounded-md border bg-card p-3 text-left shadow-sm transition-shadow hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ' +
        (isDragging ? 'opacity-50' : '')
      }
      data-testid={`funil-card-${negocio.id}`}
    >
      <span className="text-sm font-medium leading-snug">{negocio.titulo}</span>
      <span className="truncate text-xs text-muted-foreground">{negocio.contato ?? 'Sem cliente'}</span>
      <span className="font-mono text-sm tabular-nums">{BRL.format(negocio.valor)}</span>
      <span className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
        <span className="truncate">{negocio.responsavel ?? ''}</span>
        {negocio.fechamento_previsto && <Badge variant="outline" className="shrink-0 font-normal">fecha {negocio.fechamento_previsto}</Badge>}
      </span>
      <span>
        <Badge
          variant={negocio.origem === 'formulario' ? 'secondary' : 'outline'}
          className="max-w-full truncate font-normal"
          data-testid={`funil-origem-${negocio.id}`}
        >
          {negocio.origem === 'formulario' ? ['Formulário', negocio.utm].filter(Boolean).join(' · ') : 'Manual'}
        </Badge>
      </span>
    </button>
  );
}

export const NegocioCard = memo(NegocioCardImpl);

export function FunilColuna({
  etapa, negocios, podeEditar, onAbrir,
}: { etapa: Etapa; negocios: Negocio[]; podeEditar: boolean; onAbrir: (n: Negocio) => void }) {
  const { setNodeRef, isOver } = useDroppable({ id: `coluna-${etapa.id}`, data: { columnStatus: etapa.id } });
  const total = negocios.reduce((s, n) => s + n.valor, 0);

  return (
    <section
      ref={setNodeRef}
      aria-label={`Etapa ${etapa.rotulo}`}
      className={'flex min-h-48 flex-col rounded-lg border border-t-2 bg-muted/30 ' + (TOM[etapa.id] ?? '') + (isOver ? ' ring-2 ring-primary/60' : '')}
      data-testid={`funil-coluna-${etapa.id}`}
    >
      <header className="flex items-center gap-2 border-b px-3 py-2">
        <b className="text-sm">{etapa.rotulo}</b>
        <span className="rounded-full bg-muted px-2 text-xs tabular-nums">{negocios.length}</span>
        <span className="ml-auto font-mono text-xs tabular-nums text-muted-foreground">{BRL.format(total)}</span>
      </header>
      <div className="flex flex-col gap-2 p-2">
        {negocios.length === 0 && <p className="py-6 text-center text-xs text-muted-foreground">Nenhum negócio nesta etapa</p>}
        {negocios.map((n) => (
          <NegocioCard key={n.id} negocio={n} podeArrastar={podeEditar && !etapa.terminal} onAbrir={onAbrir} />
        ))}
      </div>
    </section>
  );
}
