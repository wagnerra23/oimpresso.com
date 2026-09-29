/**
 * MenuProfileProvider — estado global dos perfis de menu (Bloco 1).
 *
 * Responsabilidades:
 *  - Hidratar a lista de perfis (`MenuPerfil[]`) do AsyncStorage; se vazia ou
 *    estiver faltando algum perfil `sys`, mescla com `SEED_PERFIS`.
 *  - Manter o `activeId` (qual perfil está aplicado) persistido por usuário.
 *  - Expor CRUD básico: `createProfile`, `updateProfile`, `removeProfile`,
 *    `applyProfile`, `reorderMods`.
 *
 * Persistência local-only por enquanto — a migração para `menu_profiles` no
 * backend é o item 11.1 do checklist. O contrato deste hook foi pensado pra
 * ser compatível: quando o tRPC `menuProfiles` existir, basta trocar o storage
 * por queries/mutations e a UI continua igual.
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
  MAX_MENU_SLOTS,
  type MenuModuleId,
} from "@/lib/menu-modules";
import {
  DEFAULT_PERFIL_ID,
  SEED_PERFIS,
  type MenuPerfil,
} from "@/lib/menu-perfis";

const PROFILES_KEY = "oi-menu-profiles";
const ACTIVE_KEY = "oi-menu-profile-active";

export type MenuProfileContextValue = {
  ready: boolean;
  profiles: MenuPerfil[];
  activeId: string;
  activeProfile: MenuPerfil;
  applyProfile: (id: string) => void;
  createProfile: (input: Omit<MenuPerfil, "id" | "sys">) => MenuPerfil;
  updateProfile: (id: string, patch: Partial<Omit<MenuPerfil, "id" | "sys">>) => void;
  removeProfile: (id: string) => void;
};

const fallbackContext: MenuProfileContextValue = {
  ready: false,
  profiles: [...SEED_PERFIS],
  activeId: DEFAULT_PERFIL_ID,
  activeProfile: SEED_PERFIS[0]!,
  applyProfile: () => undefined,
  createProfile: () => SEED_PERFIS[0]!,
  updateProfile: () => undefined,
  removeProfile: () => undefined,
};

const MenuProfileContext =
  createContext<MenuProfileContextValue>(fallbackContext);

/** Mescla o que veio do storage com `SEED_PERFIS`, garantindo todos os `sys`. */
function reconcileSeed(stored: MenuPerfil[] | null): MenuPerfil[] {
  if (!stored || stored.length === 0) return [...SEED_PERFIS];
  const byId = new Map(stored.map((p) => [p.id, p]));
  for (const seed of SEED_PERFIS) {
    if (!byId.has(seed.id)) byId.set(seed.id, seed);
  }
  // Mantém ordem original do storage + acrescenta sys que faltavam no fim.
  const merged: MenuPerfil[] = [];
  const seenIds = new Set<string>();
  for (const p of stored) {
    const fresh = byId.get(p.id)!;
    merged.push(fresh);
    seenIds.add(p.id);
  }
  for (const seed of SEED_PERFIS) {
    if (!seenIds.has(seed.id)) merged.push(seed);
  }
  return merged;
}

function clampMods(mods: MenuModuleId[]): MenuModuleId[] {
  // Dedup preservando ordem + cap em MAX_MENU_SLOTS.
  const seen = new Set<MenuModuleId>();
  const out: MenuModuleId[] = [];
  for (const m of mods) {
    if (seen.has(m)) continue;
    seen.add(m);
    out.push(m);
    if (out.length >= MAX_MENU_SLOTS) break;
  }
  return out;
}

export function MenuProfileProvider({ children }: { children: ReactNode }) {
  const [profiles, setProfiles] = useState<MenuPerfil[]>(() => [...SEED_PERFIS]);
  const [activeId, setActiveId] = useState<string>(DEFAULT_PERFIL_ID);
  const [ready, setReady] = useState(false);

  // Hidratar do AsyncStorage uma vez na montagem.
  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const [profilesRaw, activeRaw] = await Promise.all([
          AsyncStorage.getItem(PROFILES_KEY),
          AsyncStorage.getItem(ACTIVE_KEY),
        ]);
        if (cancelled) return;
        let parsed: MenuPerfil[] | null = null;
        if (profilesRaw) {
          try {
            const candidate = JSON.parse(profilesRaw);
            if (Array.isArray(candidate)) parsed = candidate as MenuPerfil[];
          } catch {
            parsed = null;
          }
        }
        const reconciled = reconcileSeed(parsed);
        setProfiles(reconciled);
        const fallbackId =
          reconciled.find((p) => p.id === DEFAULT_PERFIL_ID)?.id ??
          reconciled[0]?.id ??
          DEFAULT_PERFIL_ID;
        const candidateActive =
          activeRaw && reconciled.some((p) => p.id === activeRaw)
            ? activeRaw
            : fallbackId;
        setActiveId(candidateActive);
      } catch {
        // ignore — fica nos defaults
      } finally {
        if (!cancelled) setReady(true);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const persistProfiles = useCallback((next: MenuPerfil[]) => {
    void AsyncStorage.setItem(PROFILES_KEY, JSON.stringify(next)).catch(
      () => undefined,
    );
  }, []);

  const persistActive = useCallback((id: string) => {
    void AsyncStorage.setItem(ACTIVE_KEY, id).catch(() => undefined);
  }, []);

  const applyProfile = useCallback(
    (id: string) => {
      setActiveId((prev) => {
        if (prev === id) return prev;
        persistActive(id);
        return id;
      });
    },
    [persistActive],
  );

  const createProfile = useCallback<
    MenuProfileContextValue["createProfile"]
  >(
    (input) => {
      const novo: MenuPerfil = {
        id: `perf-${Date.now().toString(36)}`,
        nome: input.nome.trim() || "Novo perfil",
        funcao: input.funcao.trim() || "—",
        mods: clampMods(input.mods),
        sys: false,
      };
      setProfiles((curr) => {
        const next = [...curr, novo];
        persistProfiles(next);
        return next;
      });
      // Ao criar, já aplica.
      setActiveId(novo.id);
      persistActive(novo.id);
      return novo;
    },
    [persistProfiles, persistActive],
  );

  const updateProfile = useCallback<
    MenuProfileContextValue["updateProfile"]
  >(
    (id, patch) => {
      setProfiles((curr) => {
        const next = curr.map((p) =>
          p.id === id
            ? {
                ...p,
                nome:
                  patch.nome !== undefined
                    ? patch.nome.trim() || p.nome
                    : p.nome,
                funcao:
                  patch.funcao !== undefined
                    ? patch.funcao.trim() || p.funcao
                    : p.funcao,
                mods:
                  patch.mods !== undefined ? clampMods(patch.mods) : p.mods,
              }
            : p,
        );
        persistProfiles(next);
        return next;
      });
    },
    [persistProfiles],
  );

  const removeProfile = useCallback<
    MenuProfileContextValue["removeProfile"]
  >(
    (id) => {
      setProfiles((curr) => {
        const target = curr.find((p) => p.id === id);
        if (!target || target.sys) return curr;
        const next = curr.filter((p) => p.id !== id);
        persistProfiles(next);
        // Se removeu o ativo, cai pro primeiro disponível.
        setActiveId((prevActive) => {
          if (prevActive !== id) return prevActive;
          const fallback =
            next.find((p) => p.id === DEFAULT_PERFIL_ID)?.id ??
            next[0]?.id ??
            DEFAULT_PERFIL_ID;
          persistActive(fallback);
          return fallback;
        });
        return next;
      });
    },
    [persistProfiles, persistActive],
  );

  const activeProfile = useMemo<MenuPerfil>(() => {
    return (
      profiles.find((p) => p.id === activeId) ??
      profiles[0] ??
      SEED_PERFIS[0]!
    );
  }, [profiles, activeId]);

  const value = useMemo<MenuProfileContextValue>(
    () => ({
      ready,
      profiles,
      activeId,
      activeProfile,
      applyProfile,
      createProfile,
      updateProfile,
      removeProfile,
    }),
    [
      ready,
      profiles,
      activeId,
      activeProfile,
      applyProfile,
      createProfile,
      updateProfile,
      removeProfile,
    ],
  );

  return (
    <MenuProfileContext.Provider value={value}>
      {children}
    </MenuProfileContext.Provider>
  );
}

export function useMenuProfile(): MenuProfileContextValue {
  return useContext(MenuProfileContext);
}

/** Conveniência: apenas o perfil ativo (componentes da tabbar usam isso). */
export function useActiveMenuProfile(): MenuPerfil {
  return useContext(MenuProfileContext).activeProfile;
}
