/**
 * useManutencao — store local da Oficina (Bloco 3).
 *
 * Estado in-memory persistido via AsyncStorage (igual ao MenuProfile). Tabelas
 * canônicas do backend (item 11.4 do checklist) ficam pra próxima etapa —
 * substituir o storage por queries tRPC quando o Connector definir os endpoints.
 */
import AsyncStorage from "@react-native-async-storage/async-storage";
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

import {
  CATALOGO_SEED,
  LOCAIS_SEED,
  MANUT_OS_SEED,
  MECANICOS_SEED,
  nextStage,
  pipelineIndex,
  type CatalogoItem,
  type ManutLocal,
  type ManutOs,
  type ManutStage,
  type Mecanico,
  type OsItem,
  type OsItemStatus,
} from "@/lib/manutencao-mock";

const OS_KEY = "oi-manut-os";
const LOCAIS_KEY = "oi-manut-locais";
const MECANICOS_KEY = "oi-manut-mecanicos";

type ManutCtx = {
  ready: boolean;
  oss: ManutOs[];
  locais: ManutLocal[];
  mecanicos: Mecanico[];
  catalogo: CatalogoItem[];
  byId: (id: string) => ManutOs | undefined;

  // mutações
  advanceStage: (osId: string) => void;
  toggleChecklist: (osId: string, itemId: string) => void;
  addChecklistItem: (osId: string, label: string) => void;
  addOsItem: (
    osId: string,
    payload: Omit<OsItem, "id" | "status"> & { status?: OsItemStatus },
  ) => void;
  setItemStatus: (osId: string, itemId: string, status: OsItemStatus) => void;
  approveAll: (osId: string) => void;
  setLocal: (osId: string, novoLocalId: string) => void;
  setMecanico: (osId: string, mecanicoId: string | null) => void;
  createOs: (input: Omit<ManutOs, "id" | "status" | "checklist" | "itens"> & {
    checklist?: ManutOs["checklist"];
    itens?: ManutOs["itens"];
  }) => ManutOs;
  addLocal: (input: Omit<ManutLocal, "id" | "ocupadoPor">) => ManutLocal;
  removeLocal: (id: string) => void;
};

const fallback: ManutCtx = {
  ready: false,
  oss: MANUT_OS_SEED,
  locais: LOCAIS_SEED,
  mecanicos: MECANICOS_SEED,
  catalogo: CATALOGO_SEED,
  byId: (id) => MANUT_OS_SEED.find((o) => o.id === id),
  advanceStage: () => undefined,
  toggleChecklist: () => undefined,
  addChecklistItem: () => undefined,
  addOsItem: () => undefined,
  setItemStatus: () => undefined,
  approveAll: () => undefined,
  setLocal: () => undefined,
  setMecanico: () => undefined,
  createOs: () => MANUT_OS_SEED[0]!,
  addLocal: () => LOCAIS_SEED[0]!,
  removeLocal: () => undefined,
};

const ManutContext = createContext<ManutCtx>(fallback);

function randomId(prefix: string) {
  return `${prefix}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 6)}`;
}

export function ManutencaoProvider({ children }: { children: ReactNode }) {
  const [oss, setOss] = useState<ManutOs[]>(MANUT_OS_SEED);
  const [locais, setLocais] = useState<ManutLocal[]>(LOCAIS_SEED);
  const [mecanicos, setMecanicos] = useState<Mecanico[]>(MECANICOS_SEED);
  const [ready, setReady] = useState(false);

  // Hidratar
  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const [a, b, c] = await Promise.all([
          AsyncStorage.getItem(OS_KEY),
          AsyncStorage.getItem(LOCAIS_KEY),
          AsyncStorage.getItem(MECANICOS_KEY),
        ]);
        if (cancelled) return;
        if (a) {
          try {
            const parsed = JSON.parse(a);
            if (Array.isArray(parsed) && parsed.length > 0) setOss(parsed);
          } catch {
            /* ignore */
          }
        }
        if (b) {
          try {
            const parsed = JSON.parse(b);
            if (Array.isArray(parsed) && parsed.length > 0) setLocais(parsed);
          } catch {
            /* ignore */
          }
        }
        if (c) {
          try {
            const parsed = JSON.parse(c);
            if (Array.isArray(parsed) && parsed.length > 0) setMecanicos(parsed);
          } catch {
            /* ignore */
          }
        }
      } finally {
        if (!cancelled) setReady(true);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const persistOss = useCallback((next: ManutOs[]) => {
    void AsyncStorage.setItem(OS_KEY, JSON.stringify(next)).catch(() => undefined);
  }, []);
  const persistLocais = useCallback((next: ManutLocal[]) => {
    void AsyncStorage.setItem(LOCAIS_KEY, JSON.stringify(next)).catch(() => undefined);
  }, []);
  const persistMecs = useCallback((next: Mecanico[]) => {
    void AsyncStorage.setItem(MECANICOS_KEY, JSON.stringify(next)).catch(() => undefined);
  }, []);

  const mutate = useCallback(
    (osId: string, fn: (os: ManutOs) => ManutOs) => {
      setOss((curr) => {
        const next = curr.map((o) => (o.id === osId ? fn(o) : o));
        persistOss(next);
        return next;
      });
    },
    [persistOss],
  );

  const byId = useCallback(
    (id: string) => oss.find((o) => o.id === id),
    [oss],
  );

  const advanceStage = useCallback(
    (osId: string) => {
      mutate(osId, (os) => {
        const cur = os.status;
        const baseStage =
          cur === "Aguardando aprovação"
            ? "Aprovação"
            : cur === "Aguardando peça"
              ? "Execução"
              : (cur as ManutStage);
        const next = nextStage(baseStage);
        if (!next) return os;
        return { ...os, status: next };
      });
    },
    [mutate],
  );

  const toggleChecklist = useCallback(
    (osId: string, itemId: string) => {
      mutate(osId, (os) => ({
        ...os,
        checklist: os.checklist.map((c) =>
          c.id === itemId ? { ...c, done: !c.done } : c,
        ),
      }));
    },
    [mutate],
  );

  const addChecklistItem = useCallback(
    (osId: string, label: string) => {
      mutate(osId, (os) => ({
        ...os,
        checklist: [
          ...os.checklist,
          { id: randomId("c"), label, done: false },
        ],
      }));
    },
    [mutate],
  );

  const addOsItem = useCallback<ManutCtx["addOsItem"]>(
    (osId, payload) => {
      mutate(osId, (os) => ({
        ...os,
        itens: [
          ...os.itens,
          {
            ...payload,
            id: randomId("i"),
            status: payload.status ?? "aguardando",
          },
        ],
      }));
    },
    [mutate],
  );

  const setItemStatus = useCallback<ManutCtx["setItemStatus"]>(
    (osId, itemId, status) => {
      mutate(osId, (os) => ({
        ...os,
        itens: os.itens.map((it) =>
          it.id === itemId ? { ...it, status } : it,
        ),
      }));
    },
    [mutate],
  );

  const approveAll = useCallback(
    (osId: string) => {
      mutate(osId, (os) => ({
        ...os,
        itens: os.itens.map((it) =>
          it.status === "aguardando" ? { ...it, status: "aprovado" } : it,
        ),
      }));
    },
    [mutate],
  );

  const setLocal = useCallback<ManutCtx["setLocal"]>(
    (osId, novoLocalId) => {
      setOss((curr) => {
        const target = curr.find((o) => o.id === osId);
        if (!target) return curr;
        const next = curr.map((o) =>
          o.id === osId ? { ...o, localId: novoLocalId } : o,
        );
        persistOss(next);
        // Atualiza ocupação dos locais
        setLocais((ls) => {
          const result = ls.map((l) => {
            const filtered = l.ocupadoPor.filter((id) => id !== osId);
            if (l.id === novoLocalId) return { ...l, ocupadoPor: [...filtered, osId] };
            return { ...l, ocupadoPor: filtered };
          });
          persistLocais(result);
          return result;
        });
        return next;
      });
    },
    [persistOss, persistLocais],
  );

  const setMecanico = useCallback<ManutCtx["setMecanico"]>(
    (osId, mecanicoId) => {
      setOss((curr) => {
        const next = curr.map((o) =>
          o.id === osId ? { ...o, mecanicoId } : o,
        );
        persistOss(next);
        setMecanicos((ms) => {
          const result = ms.map((m) => {
            const filtered = m.ativasIds.filter((id) => id !== osId);
            if (m.id === mecanicoId) return { ...m, ativasIds: [...filtered, osId] };
            return { ...m, ativasIds: filtered };
          });
          persistMecs(result);
          return result;
        });
        return next;
      });
    },
    [persistOss, persistMecs],
  );

  const createOs = useCallback<ManutCtx["createOs"]>(
    (input) => {
      const novo: ManutOs = {
        ...input,
        id: `OS-${String(Math.floor(1043 + Math.random() * 900))}`,
        status: "Triagem",
        checklist: input.checklist ?? [],
        itens: input.itens ?? [],
      };
      setOss((curr) => {
        const next = [novo, ...curr];
        persistOss(next);
        return next;
      });
      if (novo.localId) {
        setLocais((ls) => {
          const result = ls.map((l) =>
            l.id === novo.localId
              ? { ...l, ocupadoPor: [...l.ocupadoPor, novo.id] }
              : l,
          );
          persistLocais(result);
          return result;
        });
      }
      return novo;
    },
    [persistOss, persistLocais],
  );

  const addLocal = useCallback<ManutCtx["addLocal"]>(
    (input) => {
      const novo: ManutLocal = {
        ...input,
        id: randomId("L"),
        ocupadoPor: [],
      };
      setLocais((ls) => {
        const next = [...ls, novo];
        persistLocais(next);
        return next;
      });
      return novo;
    },
    [persistLocais],
  );

  const removeLocal = useCallback(
    (id: string) => {
      setLocais((ls) => {
        const target = ls.find((l) => l.id === id);
        if (!target || target.ocupadoPor.length > 0) return ls;
        const next = ls.filter((l) => l.id !== id);
        persistLocais(next);
        return next;
      });
    },
    [persistLocais],
  );

  const value = useMemo<ManutCtx>(
    () => ({
      ready,
      oss,
      locais,
      mecanicos,
      catalogo: CATALOGO_SEED,
      byId,
      advanceStage,
      toggleChecklist,
      addChecklistItem,
      addOsItem,
      setItemStatus,
      approveAll,
      setLocal,
      setMecanico,
      createOs,
      addLocal,
      removeLocal,
    }),
    [
      ready,
      oss,
      locais,
      mecanicos,
      byId,
      advanceStage,
      toggleChecklist,
      addChecklistItem,
      addOsItem,
      setItemStatus,
      approveAll,
      setLocal,
      setMecanico,
      createOs,
      addLocal,
      removeLocal,
    ],
  );

  return (
    <ManutContext.Provider value={value}>{children}</ManutContext.Provider>
  );
}

export function useManutencao() {
  return useContext(ManutContext);
}

export { pipelineIndex };
