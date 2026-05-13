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
import { useCallback, useState, type ReactNode } from 'react';
import type { CacambaCardData, CacambaStatus } from './CacambaCard';

interface DraggedData {
  cacambaId: number;
  currentColumn: CacambaStatus;
  cacamba: CacambaCardData;
}

interface KanbanDndProviderProps {
  children: ReactNode;
  onMove: (
    cacambaId: number,
    fromColumn: CacambaStatus,
    toColumn: CacambaStatus,
    cacamba: CacambaCardData,
  ) => void;
}

/**
 * Renderiza preview leve do card durante drag — não duplica CacambaCard
 * (evita re-render hierarquia + custo). Mostra placa + cliente + capacidade.
 */
function CardDragPreview({ cacamba }: { cacamba: CacambaCardData }) {
  return (
    <div
      className="bg-white border-2 border-blue-500 rounded shadow-lg p-3 max-w-[260px] cursor-grabbing rotate-2 opacity-95"
      role="presentation"
      aria-hidden="true"
    >
      <div className="flex items-center gap-2">
        <span className="font-mono text-[11px] bg-slate-900 text-white px-1.5 py-0.5 rounded">
          {cacamba.plate}
        </span>
        <div className="flex flex-col min-w-0">
          <span className="text-[12.5px] font-medium text-slate-900 truncate">
            {cacamba.capacity_m3 != null
              ? `Caçamba ${Number(cacamba.capacity_m3)}m³`
              : 'Caçamba'}
          </span>
          {cacamba.cliente_nome ? (
            <span className="text-[10.5px] text-slate-500 truncate">
              {cacamba.cliente_nome}
            </span>
          ) : null}
        </div>
      </div>
    </div>
  );
}

export default function KanbanDndProvider({
  children,
  onMove,
}: KanbanDndProviderProps) {
  const [activeData, setActiveData] = useState<DraggedData | null>(null);

  // Sensors — PointerSensor com distance:8 evita drag acidental em click-pra-abrir
  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: { distance: 8 },
    }),
    useSensor(KeyboardSensor),
  );

  const handleDragStart = useCallback((event: DragStartEvent) => {
    const data = event.active.data.current as DraggedData | undefined;
    if (data && typeof data.cacambaId === 'number') {
      setActiveData(data);
    }
  }, []);

  const handleDragOver = useCallback((_event: DragOverEvent) => {
    // No-op por ora — visual feedback de "sobre coluna" fica em CacambaKanbanColumn
    // via useDroppable.isOver (já implementado lá)
  }, []);

  const handleDragEnd = useCallback(
    (event: DragEndEvent) => {
      const { active, over } = event;
      setActiveData(null);

      if (!over) return; // Drop fora de qualquer coluna

      const dragData = active.data.current as DraggedData | undefined;
      const overColumn = over.data.current as { columnStatus?: CacambaStatus } | undefined;

      if (!dragData || !overColumn?.columnStatus) return;

      // Drop na mesma coluna — no-op
      if (dragData.currentColumn === overColumn.columnStatus) return;

      onMove(
        dragData.cacambaId,
        dragData.currentColumn,
        overColumn.columnStatus,
        dragData.cacamba,
      );
    },
    [onMove],
  );

  const handleDragCancel = useCallback(() => {
    setActiveData(null);
  }, []);

  return (
    <DndContext
      sensors={sensors}
      onDragStart={handleDragStart}
      onDragOver={handleDragOver}
      onDragEnd={handleDragEnd}
      onDragCancel={handleDragCancel}
    >
      {children}
      <DragOverlay dropAnimation={null}>
        {activeData ? <CardDragPreview cacamba={activeData.cacamba} /> : null}
      </DragOverlay>
    </DndContext>
  );
}
