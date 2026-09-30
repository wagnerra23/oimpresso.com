/**
 * Equipamentos / frota — store local (Bloco 4).
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
  EQUIPAMENTOS_SEED,
  PESSOAS_SEED,
  type Equipamento,
  type Pessoa,
} from "@/lib/equipamentos-mock";

const KEY = "oi-equipamentos";

type Ctx = {
  ready: boolean;
  equipamentos: Equipamento[];
  pessoas: Pessoa[];
  byId: (id: string) => Equipamento | undefined;
  byPlaca: (placa: string) => Equipamento | undefined;
  ownerOf: (id: string) => Pessoa | undefined;
  create: (input: Omit<Equipamento, "id">) => Equipamento;
  update: (id: string, patch: Partial<Equipamento>) => void;
  remove: (id: string) => void;
};

const fallback: Ctx = {
  ready: false,
  equipamentos: EQUIPAMENTOS_SEED,
  pessoas: PESSOAS_SEED,
  byId: (id) => EQUIPAMENTOS_SEED.find((e) => e.id === id),
  byPlaca: (placa) =>
    EQUIPAMENTOS_SEED.find((e) => e.placa?.toUpperCase() === placa.toUpperCase()),
  ownerOf: (id) => {
    const e = EQUIPAMENTOS_SEED.find((x) => x.id === id);
    return e ? PESSOAS_SEED.find((p) => p.id === e.ownerId) : undefined;
  },
  create: () => EQUIPAMENTOS_SEED[0]!,
  update: () => undefined,
  remove: () => undefined,
};

const Ec = createContext<Ctx>(fallback);

export function EquipamentosProvider({ children }: { children: ReactNode }) {
  const [equipamentos, setEquipamentos] =
    useState<Equipamento[]>(EQUIPAMENTOS_SEED);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const raw = await AsyncStorage.getItem(KEY);
        if (cancelled) return;
        if (raw) {
          try {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed) && parsed.length > 0)
              setEquipamentos(parsed);
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

  const persist = useCallback((next: Equipamento[]) => {
    void AsyncStorage.setItem(KEY, JSON.stringify(next)).catch(() => undefined);
  }, []);

  const byId = useCallback(
    (id: string) => equipamentos.find((e) => e.id === id),
    [equipamentos],
  );
  const byPlaca = useCallback(
    (placa: string) =>
      equipamentos.find(
        (e) => e.placa?.toUpperCase() === placa.toUpperCase(),
      ),
    [equipamentos],
  );
  const ownerOf = useCallback(
    (id: string) => {
      const e = equipamentos.find((x) => x.id === id);
      return e ? PESSOAS_SEED.find((p) => p.id === e.ownerId) : undefined;
    },
    [equipamentos],
  );

  const create = useCallback<Ctx["create"]>(
    (input) => {
      const novo: Equipamento = {
        ...input,
        id: `EQ-${Date.now().toString(36)}`,
      };
      setEquipamentos((curr) => {
        const next = [novo, ...curr];
        persist(next);
        return next;
      });
      return novo;
    },
    [persist],
  );

  const update = useCallback<Ctx["update"]>(
    (id, patch) => {
      setEquipamentos((curr) => {
        const next = curr.map((e) => (e.id === id ? { ...e, ...patch } : e));
        persist(next);
        return next;
      });
    },
    [persist],
  );

  const remove = useCallback(
    (id: string) => {
      setEquipamentos((curr) => {
        const next = curr.filter((e) => e.id !== id);
        persist(next);
        return next;
      });
    },
    [persist],
  );

  const value = useMemo<Ctx>(
    () => ({
      ready,
      equipamentos,
      pessoas: PESSOAS_SEED,
      byId,
      byPlaca,
      ownerOf,
      create,
      update,
      remove,
    }),
    [ready, equipamentos, byId, byPlaca, ownerOf, create, update, remove],
  );

  return <Ec.Provider value={value}>{children}</Ec.Provider>;
}

export function useEquipamentos() {
  return useContext(Ec);
}
