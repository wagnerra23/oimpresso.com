import React, { createContext, useCallback, useContext, useEffect, useMemo } from "react";
import { Platform } from "react-native";
import AsyncStorage from "@react-native-async-storage/async-storage";
import Constants from "expo-constants";

import { useAuth } from "@/hooks/use-auth";
import type { User } from "@/lib/_core/auth";
import { trpc } from "@/lib/trpc";
import { useResetERPCache } from "@/lib/erp-queries";
import { clearAllForCompany } from "@/lib/mutation-queue";

/**
 * F3-08: shape of a company exposed to the client. Matches the
 * `rowToCompany` mapper on the server.
 */
export type CurrentCompany = {
  id: string;
  ownerUserId: number;
  nome: string;
  vertical: "cv" | "mecanica" | "outro";
  ativa: boolean;
  createdAt: string;
  updatedAt: string;
};

/**
 * AuthContext exposes a single source of truth for the auth state across the app.
 *
 * `logout` here is an extended version of `useAuth.logout()` that, in addition to
 * clearing the session token (SecureStore) and cached user (SecureStore /
 * localStorage), also:
 *   1. Unregisters the device's Expo push token on the server (so the next user
 *      on this device doesn't inherit our notifications).
 *   2. Wipes the in-memory tRPC/React Query cache so stale data from this
 *      session doesn't leak to the next signed-in user.
 *   3. Removes any locally persisted ERP state from AsyncStorage —
 *      fulfilling the F1-01 acceptance criterion "Logout limpa todos os
 *      dados locais".
 */
type AuthContextValue = {
  user: User | null;
  loading: boolean;
  error: Error | null;
  isAuthenticated: boolean;
  refresh: () => Promise<void>;
  logout: () => Promise<void>;
  /** F3-08: the active company for this session (null while loading / none). */
  currentCompany: CurrentCompany | null;
  /** F3-08: switch the active company. Invalidates all tRPC caches. */
  switchCompany: (id: string) => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

// AsyncStorage keys that must be wiped on logout.
const LOCAL_STORAGE_KEYS = ["erpState", "currentCompanyId"];

/**
 * Best-effort wipe of every persisted React Query cache bucket
 * (`erp-query-cache-<companyId>` and `erp-query-cache-anon`). Used on logout
 * so the next signed-in user starts from a clean slate even though the cache
 * file lives in AsyncStorage independently of the in-memory QueryClient.
 */
async function clearPersistedQueryCaches(): Promise<void> {
  try {
    const keys = await AsyncStorage.getAllKeys();
    const matches = keys.filter((k) => k.startsWith("erp-query-cache-"));
    if (matches.length > 0) await AsyncStorage.multiRemove(matches);
  } catch {
    /* non-fatal */
  }
}

const isExpoGo = Constants.appOwnership === "expo";

/**
 * Best-effort lookup of the device's current Expo push token. Mirrors the lazy
 * import pattern in `hooks/use-push-registration.ts` so importing this module
 * in Expo Go (where remote push is unsupported) doesn't trigger a noisy crash.
 *
 * Returns `null` on web, in Expo Go, or whenever the token can't be obtained
 * — callers should treat any failure as "no token to unregister".
 */
async function getCurrentExpoPushToken(): Promise<string | null> {
  if (Platform.OS === "web" || isExpoGo) return null;
  try {
    const Notifications = await import("expo-notifications");
    const projectId =
      (Constants.expoConfig?.extra as { eas?: { projectId?: string } } | undefined)
        ?.eas?.projectId ??
      (Constants.easConfig as { projectId?: string } | undefined)?.projectId;
    const result = await Notifications.getExpoPushTokenAsync(
      projectId ? { projectId } : undefined,
    );
    return result.data ?? null;
  } catch {
    return null;
  }
}

export function AuthProvider({
  children,
  onCompanyIdChange,
}: {
  children: React.ReactNode;
  /** F3-04: notified whenever the active company id changes so the root
   * layout can re-scope the persisted query cache + mutation queue. */
  onCompanyIdChange?: (id: string | null) => void;
}) {
  const auth = useAuth();
  const unregisterToken = trpc.notifications.unregisterToken.useMutation();
  const resetERPCache = useResetERPCache();

  // F3-08: load the active company once the user is authenticated.
  const currentCompanyQuery = trpc.companies.current.useQuery(undefined, {
    enabled: auth.isAuthenticated,
    staleTime: 60_000,
  });
  const switchCompanyMutation = trpc.companies.switch.useMutation();
  const utils = trpc.useUtils();

  const switchCompany = useCallback(
    async (id: string) => {
      await switchCompanyMutation.mutateAsync({ id });
      // Tenant changed — drop ALL cached data so the new tenant doesn't see stale rows.
      await utils.invalidate();
      // Persist as fallback so a reload doesn't lose the choice if the cookie was cleared.
      try {
        await AsyncStorage.setItem("currentCompanyId", id);
      } catch {
        /* non-fatal */
      }
      // F3-04: re-scope persisted cache + mutation queue to the new tenant.
      onCompanyIdChange?.(id);
    },
    [switchCompanyMutation, utils, onCompanyIdChange],
  );

  // F3-04: when the loaded company changes, notify the root layout so cache
  // key / queue key get re-scoped.
  const loadedCompanyId =
    (currentCompanyQuery.data as CurrentCompany | null | undefined)?.id ?? null;
  useEffect(() => {
    onCompanyIdChange?.(loadedCompanyId);
  }, [loadedCompanyId, onCompanyIdChange]);

  const logout = useCallback(async () => {
    // Capture the active company id BEFORE we wipe state so we can clean its
    // mutation queue.
    const activeCompanyId =
      (currentCompanyQuery.data as CurrentCompany | null | undefined)?.id ?? null;
    try {
      // 1. Best-effort server-side push token cleanup BEFORE we drop auth, so
      //    the request still carries a valid session. Fire-and-forget — never
      //    block logout on this.
      try {
        const token = await getCurrentExpoPushToken();
        if (token) {
          unregisterToken.mutate({ token });
        }
      } catch (err) {
        if (__DEV__) console.warn("[AuthProvider] unregisterToken skipped:", err);
      }

      // 2. Wipe the tRPC/React Query cache so the next user doesn't see leaked data.
      try {
        resetERPCache();
      } catch (err) {
        if (__DEV__) console.warn("[AuthProvider] resetERPCache failed:", err);
      }

      // 3. Clear the session token / cached user from SecureStore / localStorage.
      await auth.logout();
    } finally {
      // 4. Always clear locally persisted ERP state, even if earlier steps threw.
      try {
        await AsyncStorage.multiRemove(LOCAL_STORAGE_KEYS);
      } catch (err) {
        console.error("[AuthProvider] Failed to clear local ERP state:", err);
      }
      // 5. F3-04: drop the persisted React Query caches + the pending mutation
      //    queue for both the active company and the anon bucket so nothing
      //    leaks to the next signed-in user.
      try {
        await clearPersistedQueryCaches();
        await clearAllForCompany(activeCompanyId);
        await clearAllForCompany(null);
      } catch (err) {
        if (__DEV__)
          console.warn("[AuthProvider] offline cleanup skipped:", err);
      }
      onCompanyIdChange?.(null);
    }
  }, [auth, unregisterToken, resetERPCache, currentCompanyQuery.data, onCompanyIdChange]);

  const value = useMemo<AuthContextValue>(
    () => ({
      user: auth.user,
      loading: auth.loading,
      error: auth.error,
      isAuthenticated: auth.isAuthenticated,
      refresh: auth.refresh,
      logout,
      currentCompany: (currentCompanyQuery.data as CurrentCompany | null | undefined) ?? null,
      switchCompany,
    }),
    [
      auth.user,
      auth.loading,
      auth.error,
      auth.isAuthenticated,
      auth.refresh,
      logout,
      currentCompanyQuery.data,
      switchCompany,
    ],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuthContext(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error("useAuthContext must be used within an AuthProvider");
  }
  return ctx;
}
