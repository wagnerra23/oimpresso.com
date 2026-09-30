import React, { createContext, useCallback, useContext, useMemo, useReducer } from "react";

// ─── Domain types ───────────────────────────────────────────────────────────
// These types remain the source of truth for the client. They mirror the
// shapes returned by the tRPC routers (see server/routers/*.ts) so screens
// can keep importing them from here.

export interface Produto {
  id: string;
  nome: string;
  categoria: string;
  preco: number;
}

export interface Pedido {
  id: string;
  cliente: string;
  produto: string;
  valor: number;
  status: "novo" | "aprovado" | "execucao" | "entregue";
  /** ISO 8601 timestamp (e.g. "2026-05-15T13:00:00.000Z"). */
  data: string;
  tipo: string;
}

export interface OP {
  id: string;
  pedidoId: string;
  cliente: string;
  produto: string;
  status: "fila" | "andamento" | "revisao" | "concluido";
}

export interface Transacao {
  id: string;
  tipo: "receita" | "despesa";
  descricao: string;
  valor: number;
  /** ISO 8601 timestamp. */
  data: string;
  categoria: string;
}

export interface Toast {
  id: string;
  tipo: "sucesso" | "erro" | "info";
  mensagem: string;
  timestamp: number;
}

// ─── UI state reducer ───────────────────────────────────────────────────────
// After the F1-02 migration the Context owns *only* UI state: toasts and a
// global loading flag. All entity data (produtos/pedidos/ops/transacoes)
// now lives in the TanStack Query cache and is accessed through the hooks
// in `lib/erp-queries.ts`.

interface UIState {
  toasts: Toast[];
  loading: boolean;
}

type UIAction =
  | { type: "ADD_TOAST"; payload: Toast }
  | { type: "REMOVE_TOAST"; payload: string }
  | { type: "SET_LOADING"; payload: boolean };

const initialUIState: UIState = { toasts: [], loading: false };

function uiReducer(state: UIState, action: UIAction): UIState {
  switch (action.type) {
    case "ADD_TOAST":
      return { ...state, toasts: [...state.toasts, action.payload] };
    case "REMOVE_TOAST":
      return { ...state, toasts: state.toasts.filter((t) => t.id !== action.payload) };
    case "SET_LOADING":
      return { ...state, loading: action.payload };
    default:
      return state;
  }
}

// ─── Context ────────────────────────────────────────────────────────────────

interface ERPContextType {
  /** UI-only state (toasts + loading). Entity data lives in React Query. */
  ui: UIState;
  addToast: (tipo: Toast["tipo"], mensagem: string) => void;
  removeToast: (id: string) => void;
  setLoading: (loading: boolean) => void;
}

const ERPContext = createContext<ERPContextType | undefined>(undefined);

export function ERPProvider({ children }: { children: React.ReactNode }) {
  const [ui, dispatch] = useReducer(uiReducer, initialUIState);

  const addToast = useCallback((tipo: Toast["tipo"], mensagem: string) => {
    const id =
      typeof globalThis.crypto?.randomUUID === "function"
        ? globalThis.crypto.randomUUID()
        : `toast-${Date.now()}-${Math.random().toString(36).slice(2)}`;
    dispatch({
      type: "ADD_TOAST",
      payload: { id, tipo, mensagem, timestamp: Date.now() },
    });
    setTimeout(() => {
      dispatch({ type: "REMOVE_TOAST", payload: id });
    }, 5000);
  }, []);

  const removeToast = useCallback((id: string) => {
    dispatch({ type: "REMOVE_TOAST", payload: id });
  }, []);

  const setLoading = useCallback((loading: boolean) => {
    dispatch({ type: "SET_LOADING", payload: loading });
  }, []);

  const value = useMemo<ERPContextType>(
    () => ({ ui, addToast, removeToast, setLoading }),
    [ui, addToast, removeToast, setLoading],
  );

  return <ERPContext.Provider value={value}>{children}</ERPContext.Provider>;
}

export function useERP() {
  const context = useContext(ERPContext);
  if (!context) {
    throw new Error("useERP deve ser usado dentro de ERPProvider");
  }
  return context;
}
