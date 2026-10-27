// Estado de arrasto do Kanban Produção · Oficina (D-01 — feedback preditivo).
//
// Mora num módulo SEM componente (só context + hook + tipos) pra o KanbanDndProvider
// continuar exportando só o componente default — Fast Refresh feliz
// (react-refresh/only-export-components). Consumido por ServiceOrderKanbanColumn.

import { createContext, useContext } from 'react';

// Veredito preditivo do drop, calculado durante o drag (feedback Linear/Stripe):
//   'advance' → gate ok, solta e avança · 'confirm' → avança mas pede confirmação
//   (transição crítica ADR 0143) · 'blocked' → não avança, abre o drawer no que falta.
export type DropVerdict = 'advance' | 'confirm' | 'blocked';

// Colunas tipadas como `string` pra manter o provider genérico — consumidores
// concretos (stage keys do FSM) são subtipos de string. Consumidores que não usam
// o feedback (ex.: ServiceOrders/Board) ignoram o context (verdictFor → null).
export interface KanbanDragState {
  /** Coluna de origem do card em arrasto (null quando nada arrastando). */
  activeFromColumn: string | null;
  /** Coluna sob o cursor agora (null fora de coluna). */
  overColumn: string | null;
  /** Veredito preditivo de soltar o card ativo na coluna `to` (null se origem=destino ou sem arrasto/evaluateDrop). */
  verdictFor: (to: string) => DropVerdict | null;
}

export const KanbanDragContext = createContext<KanbanDragState>({
  activeFromColumn: null,
  overColumn: null,
  verdictFor: () => null,
});

/** Hook pras colunas lerem o estado de arrasto e pintarem o feedback preditivo (D-01). */
export function useKanbanDragState(): KanbanDragState {
  return useContext(KanbanDragContext);
}
