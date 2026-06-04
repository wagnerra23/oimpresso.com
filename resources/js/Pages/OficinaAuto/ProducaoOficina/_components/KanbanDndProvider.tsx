// Drag-and-drop provider pro Kanban Produção · Oficina (Martinho 13/maio).
//
// Wrapper @dnd-kit/core (DndContext + sensors + DragOverlay):
//   - PointerSensor com activationConstraint distance:8 → evita drag acidental
//     em clique-pra-abrir-drawer (handleCardClick continua funcionando)
//   - KeyboardSensor → acessibilidade (Tab navega, Space pega, Arrow move)
//   - DragOverlay flutuante renderiza preview do card sendo arrastado
//
// Callback contract:
//   onMove(cacambaId, fromColumn, toColumn, dragData)
//     - Index decide se mapping é permitido (mappingTable)
//     - Se sim, abre DragConfirmDialog
//     - Se não, mostra toast warning
//
// CRÍTICO React 19 — useCallback nos handlers (lição PR #717).

import {
  DndContext,
  DragOverlay,
  PointerSensor,
  KeyboardSensor,
  useSensor,
  useSensors,
  type DragStartEvent,
  type DragEndEvent,
  type DragOverEvent,
} from '@dnd-kit/core';
import {
  useCallback,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import type { CacambaCardData, CacambaStatus } from './CacambaCard';
// D-01 — context/hook/tipos do feedback preditivo moram em módulo sem componente
// (Fast Refresh feliz: este arquivo exporta só o componente default).
import {
  KanbanDragContext,
  type DropVerdict,
  type KanbanDragState,
} from './kanbanDrag';

// Genérico (2026-06-02 · port Kanban do carro): o provider DnD canon é reusado por
// múltiplas verticais (caçamba ProducaoOficina + OS de mecânica ServiceOrders/Board).
// Generics com default = tipos da caçamba → call sites legados compilam SEM mudança.
// O ServiceOrders/Board passa `renderPreview` próprio (sem a palavra "Caçamba").
interface DraggedData<T, C extends string> {
  cacambaId: number;
  currentColumn: C;
  cacamba: T;
}

interface KanbanDndProviderProps<T, C extends string> {
  children: ReactNode;
  onMove: (
    cacambaId: number,
    fromColumn: C,
    toColumn: C,
    cacamba: T,
  ) => void;
  /** Preview flutuante durante o drag. Default = preview da caçamba (compat). */
  renderPreview?: (cacamba: T) => ReactNode;
  /**
   * D-01 — avalia (puro/síncrono) o desfecho de soltar `cacamba` de `from` em `to`.
   * Reusa a máquina de mapping do consumidor (ex.: resolveDragMapping do Index) pra
   * pintar verde/âmbar nas colunas. Opcional: sem ele o feedback preditivo desliga.
   */
  evaluateDrop?: (from: C, to: C, cacamba: T) => DropVerdict;
}

/**
 * Renderiza preview leve do card durante drag — não duplica CacambaCard
 * (evita re-render hierarquia + custo). Mostra placa + cliente + capacidade.
 */
function CardDragPreview({ cacamba }: { cacamba: CacambaCardData }) {
  return (
    <div
      className="bg-white border-2 border-primary rounded shadow-lg p-3 max-w-[260px] cursor-grabbing rotate-2 opacity-95"
      role="presentation"
      aria-hidden="true"
    >
      <div className="flex items-center gap-2">
        <span className="font-mono text-[11px] bg-foreground text-background px-1.5 py-0.5 rounded">
          {cacamba.plate}
        </span>
        <div className="flex flex-col min-w-0">
          <span className="text-[12.5px] font-medium text-foreground truncate">
            {cacamba.capacity_m3 != null
              ? `Caçamba ${Number(cacamba.capacity_m3)}m³`
              : 'Caçamba'}
          </span>
          {cacamba.cliente_nome ? (
            <span className="text-[10.5px] text-muted-foreground truncate">
              {cacamba.cliente_nome}
            </span>
          ) : null}
        </div>
      </div>
    </div>
  );
}

export default function KanbanDndProvider<
  T = CacambaCardData,
  C extends string = CacambaStatus,
>({
  children,
  onMove,
  renderPreview,
  evaluateDrop,
}: KanbanDndProviderProps<T, C>) {
  const [activeData, setActiveData] = useState<DraggedData<T, C> | null>(null);
  const [overColumn, setOverColumn] = useState<C | null>(null);

  // Sensors — PointerSensor com distance:8 evita drag acidental em click-pra-abrir
  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: { distance: 8 },
    }),
    useSensor(KeyboardSensor),
  );

  const handleDragStart = useCallback((event: DragStartEvent) => {
    const data = event.active.data.current as DraggedData<T, C> | undefined;
    if (data && typeof data.cacambaId === 'number') {
      setActiveData(data);
    }
  }, []);

  const handleDragOver = useCallback((event: DragOverEvent) => {
    // D-01 — rastreia coluna sob o cursor pro feedback preditivo das colunas.
    const over = event.over?.data.current as { columnStatus?: C } | undefined;
    setOverColumn(over?.columnStatus ?? null);
  }, []);

  const handleDragEnd = useCallback(
    (event: DragEndEvent) => {
      const { active, over } = event;
      setActiveData(null);
      setOverColumn(null);

      if (!over) return; // Drop fora de qualquer coluna

      const dragData = active.data.current as DraggedData<T, C> | undefined;
      const overData = over.data.current as { columnStatus?: C } | undefined;

      if (!dragData || !overData?.columnStatus) return;

      // Drop na mesma coluna — no-op
      if (dragData.currentColumn === overData.columnStatus) return;

      onMove(
        dragData.cacambaId,
        dragData.currentColumn,
        overData.columnStatus,
        dragData.cacamba,
      );
    },
    [onMove],
  );

  const handleDragCancel = useCallback(() => {
    setActiveData(null);
    setOverColumn(null);
  }, []);

  // D-01 — estado de arrasto exposto às colunas via context (feedback preditivo).
  const dragState = useMemo<KanbanDragState>(() => {
    const activeFromColumn = activeData?.currentColumn ?? null;
    return {
      activeFromColumn,
      overColumn,
      verdictFor: (to: string) => {
        if (!activeData || !evaluateDrop) return null;
        if (activeData.currentColumn === to) return null; // origem = destino
        return evaluateDrop(activeData.currentColumn, to as C, activeData.cacamba);
      },
    };
  }, [activeData, overColumn, evaluateDrop]);

  return (
    <DndContext
      sensors={sensors}
      onDragStart={handleDragStart}
      onDragOver={handleDragOver}
      onDragEnd={handleDragEnd}
      onDragCancel={handleDragCancel}
    >
      <KanbanDragContext.Provider value={dragState}>
        {children}
      </KanbanDragContext.Provider>
      <DragOverlay dropAnimation={null}>
        {activeData
          ? renderPreview
            ? renderPreview(activeData.cacamba)
            : <CardDragPreview cacamba={activeData.cacamba as unknown as CacambaCardData} />
          : null}
      </DragOverlay>
    </DndContext>
  );
}
