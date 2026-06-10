// Drag-and-drop provider pro Kanban da Oficina (Quadro de OS — ADR 0265).
//
// Wrapper @dnd-kit/core (DndContext + sensors + DragOverlay):
//   - PointerSensor com activationConstraint distance:8 → evita drag acidental
//     em clique-pra-abrir-drawer (handleCardClick continua funcionando)
//   - KeyboardSensor → acessibilidade (Tab navega, Space pega, Arrow move)
//   - DragOverlay flutuante renderiza preview do card sendo arrastado
//
// Callback contract:
//   onMove(subjectId, fromColumn, toColumn, subject)
//     - Consumidor decide se mapping é permitido (STAGE_TRANSITIONS)
//     - Se sim, abre DragConfirmDialog
//     - Se não, mostra toast warning
//
// Genérico subject-neutral (2026-06-10 · unificação ADR 0265): o card de caçamba
// (CacambaCardData) morreu junto com o kanban de locação — o provider agora exige
// `renderPreview` do consumidor (Board passa o dele) e não conhece o shape do card.
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
// D-01 — context/hook/tipos do feedback preditivo moram em módulo sem componente
// (Fast Refresh feliz: este arquivo exporta só o componente default).
import {
  KanbanDragContext,
  type DropVerdict,
  type KanbanDragState,
} from './kanbanDrag';

interface DraggedData<T, C extends string> {
  subjectId: number;
  currentColumn: C;
  subject: T;
}

interface KanbanDndProviderProps<T, C extends string> {
  children: ReactNode;
  onMove: (
    subjectId: number,
    fromColumn: C,
    toColumn: C,
    subject: T,
  ) => void;
  /** Preview flutuante durante o drag (obrigatório — provider não conhece o card). */
  renderPreview: (subject: T) => ReactNode;
  /**
   * D-01 — avalia (puro/síncrono) o desfecho de soltar `subject` de `from` em `to`.
   * Reusa a máquina de mapping do consumidor (ex.: STAGE_TRANSITIONS do Board) pra
   * pintar verde/âmbar nas colunas. Opcional: sem ele o feedback preditivo desliga.
   */
  evaluateDrop?: (from: C, to: C, subject: T) => DropVerdict;
}

export default function KanbanDndProvider<T, C extends string = string>({
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
    if (data && typeof data.subjectId === 'number') {
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
        dragData.subjectId,
        dragData.currentColumn,
        overData.columnStatus,
        dragData.subject,
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
        return evaluateDrop(activeData.currentColumn, to as C, activeData.subject);
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
        {activeData ? renderPreview(activeData.subject) : null}
      </DragOverlay>
    </DndContext>
  );
}
