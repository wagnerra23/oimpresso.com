import "@/global.css";
import { MutationCache, QueryClient } from "@tanstack/react-query";
import { PersistQueryClientProvider } from "@tanstack/react-query-persist-client";
import { createAsyncStoragePersister } from "@tanstack/query-async-storage-persister";
import AsyncStorage from "@react-native-async-storage/async-storage";
import NetInfo from "@react-native-community/netinfo";
import Constants from "expo-constants";
import { Stack } from "expo-router";
import type { ErrorBoundaryProps } from "expo-router";
import { StatusBar } from "expo-status-bar";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { GestureHandlerRootView } from "react-native-gesture-handler";
import "react-native-reanimated";
import { Platform, Pressable, Text, View } from "react-native";
import "@/lib/_core/nativewind-pressable";
import { ThemeProvider } from "@/lib/theme-provider";
import { OiThemeProvider } from "@/lib/oi-theme-context";
import { MenuProfileProvider } from "@/lib/menu-profile-context";
import { ManutencaoProvider } from "@/lib/use-manutencao";
import { EquipamentosProvider } from "@/lib/use-equipamentos";
import { FinanceiroProvider } from "@/lib/use-financeiro";
import { OiToastHost, ToastProvider } from "@/components/oi";
import {
  useFonts,
  Inter_400Regular,
  Inter_500Medium,
  Inter_600SemiBold,
  Inter_700Bold,
  Inter_800ExtraBold,
} from "@expo-google-fonts/inter";
import {
  JetBrainsMono_400Regular,
  JetBrainsMono_500Medium,
  JetBrainsMono_600SemiBold,
} from "@expo-google-fonts/jetbrains-mono";
import * as SplashScreen from "expo-splash-screen";
import {
  SafeAreaFrameContext,
  SafeAreaInsetsContext,
  SafeAreaProvider,
  initialWindowMetrics,
} from "react-native-safe-area-context";
import type { EdgeInsets, Metrics, Rect } from "react-native-safe-area-context";

import { trpc, createTRPCClient } from "@/lib/trpc";
import { initManusRuntime, subscribeSafeAreaInsets } from "@/lib/_core/manus-runtime";
import { ERPProvider } from "@/lib/erp-context";
import { AuthProvider } from "@/lib/auth-context";
import { AuthGate } from "@/components/auth-gate";
import { usePushRegistration } from "@/hooks/use-push-registration";
import {
  enqueueMutation,
  isNetworkError,
  replayQueue,
} from "@/lib/mutation-queue";

/**
 * Mounted inside AuthProvider + ERPProvider so the push hook can read auth
 * state via context. Renders nothing — its only job is the side effect.
 */
function PushBootstrap() {
  usePushRegistration();
  return null;
}

const DEFAULT_WEB_INSETS: EdgeInsets = { top: 0, right: 0, bottom: 0, left: 0 };
const DEFAULT_WEB_FRAME: Rect = { x: 0, y: 0, width: 0, height: 0 };

export const unstable_settings = {
  anchor: "(tabs)",
};

export function ErrorBoundary({ error, retry }: ErrorBoundaryProps) {
  return (
    <View className="flex-1 items-center justify-center bg-background px-6">
      <Text className="text-2xl font-semibold text-foreground mb-3">
        Algo deu errado
      </Text>
      <Text className="text-sm text-muted-foreground text-center mb-6">
        {error.message}
      </Text>
      <Pressable
        onPress={() => retry()}
        className="bg-primary px-5 py-3 rounded-md active:opacity-80"
      >
        <Text className="text-primary-foreground font-medium">
          Tentar novamente
        </Text>
      </Pressable>
    </View>
  );
}

// Keep the splash visible while we load the IBM Plex fonts.
void SplashScreen.preventAutoHideAsync().catch(() => undefined);

export default function RootLayout() {
  const initialInsets = initialWindowMetrics?.insets ?? DEFAULT_WEB_INSETS;
  const initialFrame = initialWindowMetrics?.frame ?? DEFAULT_WEB_FRAME;

  const [fontsLoaded, fontError] = useFonts({
    Inter_400Regular,
    Inter_500Medium,
    Inter_600SemiBold,
    Inter_700Bold,
    Inter_800ExtraBold,
    JetBrainsMono_400Regular,
    JetBrainsMono_500Medium,
    JetBrainsMono_600SemiBold,
  });

  // Render once fonts load OR fail — nunca travar a tela branca por causa de
  // fonte (ex.: modo offline não resolve o asset). Sem fonte, o RN cai no
  // system font; melhor que app preso no splash.
  const fontsReady = fontsLoaded || fontError != null;

  useEffect(() => {
    if (fontsReady) {
      void SplashScreen.hideAsync().catch(() => undefined);
    }
  }, [fontsReady]);

  const [insets, setInsets] = useState<EdgeInsets>(initialInsets);
  const [frame, setFrame] = useState<Rect>(initialFrame);

  // Initialize Manus runtime for cookie injection from parent container
  useEffect(() => {
    initManusRuntime();
  }, []);

  const handleSafeAreaUpdate = useCallback((metrics: Metrics) => {
    setInsets(metrics.insets);
    setFrame(metrics.frame);
  }, []);

  useEffect(() => {
    if (Platform.OS !== "web") return;
    const unsubscribe = subscribeSafeAreaInsets(handleSafeAreaUpdate);
    return () => unsubscribe();
  }, [handleSafeAreaUpdate]);

  // F3-04 Offline-First: bootstrap company id once so we scope the persisted
  // cache and the mutation queue per tenant. Updated by the AuthProvider when
  // it loads / switches companies.
  const [companyId, setCompanyId] = useState<string | null>(null);
  useEffect(() => {
    let cancelled = false;
    AsyncStorage.getItem("currentCompanyId")
      .then((id) => {
        if (!cancelled) setCompanyId(id ?? null);
      })
      .catch(() => {
        if (!cancelled) setCompanyId(null);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  // tRPC client (single instance) — needed by the replay loop too.
  const [trpcClient] = useState(() => createTRPCClient());
  const trpcClientRef = useRef(trpcClient);
  trpcClientRef.current = trpcClient;
  // We keep a stable mutable ref to the *current* companyId so callbacks
  // closed over by MutationCache (constructed once) read the live value.
  const companyIdRef = useRef<string | null>(companyId);
  companyIdRef.current = companyId;

  // Build the QueryClient ONCE. Persist via @tanstack/react-query-persist-client.
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            refetchOnWindowFocus: false,
            retry: 1,
            // Cached data is usable for 30s without re-fetch, and lives in
            // memory + AsyncStorage for 24h so offline reloads still see it.
            staleTime: 30_000,
            gcTime: 24 * 60 * 60 * 1000,
            // While offline, never retry — fail fast so the global onError
            // hook can enqueue the mutation (queries just return stale data).
          },
        },
        mutationCache: new MutationCache({
          onError: (error, variables, _ctx, mutation) => {
            if (!isNetworkError(error)) return;
            // tRPC mutationKey shape: [["router","procedure"]] (single
            // element, an array of path segments).
            const key = mutation.options.mutationKey as unknown;
            if (!Array.isArray(key) || key.length === 0) return;
            const segs = key[0];
            if (!Array.isArray(segs) || segs.length === 0) return;
            const path = segs
              .map((s) => (typeof s === "string" ? s : String(s)))
              .join(".");
            // Fire-and-forget — never block the UI.
            void enqueueMutation(companyIdRef.current, {
              path,
              input: variables,
            });
          },
        }),
      }),
  );

  // AsyncStorage persister with a cache buster tied to app version so any
  // future schema change of the persisted shape invalidates the local cache.
  const appVersion = Constants.expoConfig?.version ?? "0.0.0";
  const persister = useMemo(
    () =>
      createAsyncStoragePersister({
        storage: AsyncStorage,
        key: `erp-query-cache-${companyId ?? "anon"}`,
        throttleTime: 1000,
      }),
    [companyId],
  );
  const persistOptions = useMemo(
    () => ({
      persister,
      maxAge: 7 * 24 * 60 * 60 * 1000, // 7 days
      buster: `v${appVersion}`,
    }),
    [persister, appVersion],
  );

  // F3-04 — replay queued mutations on reconnect and every 60s.
  useEffect(() => {
    const tick = () => {
      void replayQueue(companyIdRef.current, trpcClientRef.current);
    };
    const unsub = NetInfo.addEventListener((state) => {
      const reachable = state.isInternetReachable;
      const connected = state.isConnected;
      const online =
        reachable === null ? connected !== false : reachable !== false;
      if (online) tick();
    });
    const interval = setInterval(tick, 60_000);
    // First tick shortly after mount so any queued items from a previous
    // session start draining without waiting for the first net change.
    const kick = setTimeout(tick, 2_000);
    return () => {
      unsub();
      clearInterval(interval);
      clearTimeout(kick);
    };
  }, []);

  // Ensure minimum 8px padding for top and bottom on mobile
  const providerInitialMetrics = useMemo(() => {
    const metrics = initialWindowMetrics ?? { insets: initialInsets, frame: initialFrame };
    return {
      ...metrics,
      insets: {
        ...metrics.insets,
        top: Math.max(metrics.insets.top, 16),
        bottom: Math.max(metrics.insets.bottom, 12),
      },
    };
  }, [initialInsets, initialFrame]);

  const content = (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <trpc.Provider client={trpcClient} queryClient={queryClient}>
        <PersistQueryClientProvider
          client={queryClient}
          persistOptions={persistOptions}
        >
          <AuthProvider onCompanyIdChange={setCompanyId}>
            <ERPProvider>
              <OiThemeProvider>
              <MenuProfileProvider>
              <ManutencaoProvider>
              <EquipamentosProvider>
              <FinanceiroProvider>
              <ToastProvider>
              <PushBootstrap />
              <AuthGate>
                {/* Default to hiding native headers so raw route segments don't appear (e.g. "(tabs)", "products/[id]"). */}
                {/* If a screen needs the native header, explicitly enable it and set a human title via Stack.Screen options. */}
                {/* in order for ios apps tab switching to work properly, use presentation: "fullScreenModal" for login page, whenever you decide to use presentation: "modal*/}
                <Stack screenOptions={{ headerShown: false }}>
                  <Stack.Screen name="(tabs)" />
                  <Stack.Screen
                    name="login"
                    options={{ presentation: "fullScreenModal" }}
                  />
                  <Stack.Screen name="oauth/callback" />
                  <Stack.Screen name="estoque/[id]" />
                  <Stack.Screen name="oss/[id]" options={{ presentation: "card" }} />
                  <Stack.Screen name="empresas" options={{ presentation: "card" }} />
                  <Stack.Screen name="clientes/new" options={{ presentation: "card" }} />
                  <Stack.Screen name="clientes/[id]/index" options={{ presentation: "card" }} />
                  <Stack.Screen name="clientes/[id]/ficha" options={{ presentation: "card" }} />
                  <Stack.Screen name="clientes/[id]/edit" options={{ presentation: "card" }} />
                  <Stack.Screen name="produtos/new" options={{ presentation: "card" }} />
                  <Stack.Screen name="produtos/[id]/index" options={{ presentation: "card" }} />
                  <Stack.Screen name="produtos/[id]/edit" options={{ presentation: "card" }} />
                  <Stack.Screen name="tarefas/[id]" options={{ presentation: "card" }} />
                  <Stack.Screen name="pedidos/[id]" options={{ presentation: "card" }} />
                  <Stack.Screen name="producao/[id]" options={{ presentation: "card" }} />
                  <Stack.Screen name="venda-rapida" options={{ presentation: "card" }} />
                  <Stack.Screen name="perfis/index" options={{ presentation: "card" }} />
                  <Stack.Screen name="perfis/[id]/edit" options={{ presentation: "card" }} />
                  <Stack.Screen name="manutencao/[id]" options={{ presentation: "card" }} />
                  <Stack.Screen name="manutencao/new" options={{ presentation: "card" }} />
                  <Stack.Screen name="locais/index" options={{ presentation: "card" }} />
                  <Stack.Screen name="locais/new" options={{ presentation: "card" }} />
                  <Stack.Screen name="equipamentos/[id]" options={{ presentation: "card" }} />
                  <Stack.Screen name="equipamentos/new" options={{ presentation: "card" }} />
                  <Stack.Screen name="equipe/index" options={{ presentation: "card" }} />
                  <Stack.Screen name="notificacoes/index" options={{ presentation: "card" }} />
                </Stack>
                <OiToastHost />
                <StatusBar style="auto" />
              </AuthGate>
              </ToastProvider>
              </FinanceiroProvider>
              </EquipamentosProvider>
              </ManutencaoProvider>
              </MenuProfileProvider>
              </OiThemeProvider>
            </ERPProvider>
          </AuthProvider>
        </PersistQueryClientProvider>
      </trpc.Provider>
    </GestureHandlerRootView>
  );

  // Hold rendering until IBM Plex is on disk so the splash hides only when
  // text wouldn't reflow under the user. The splash screen above keeps the
  // launch image visible during this window.
  if (!fontsReady) return null;

  const shouldOverrideSafeArea = Platform.OS === "web";

  if (shouldOverrideSafeArea) {
    return (
      <ThemeProvider>
        <SafeAreaProvider initialMetrics={providerInitialMetrics}>
          <SafeAreaFrameContext.Provider value={frame}>
            <SafeAreaInsetsContext.Provider value={insets}>
              {content}
            </SafeAreaInsetsContext.Provider>
          </SafeAreaFrameContext.Provider>
        </SafeAreaProvider>
      </ThemeProvider>
    );
  }

  return (
    <ThemeProvider>
      <SafeAreaProvider initialMetrics={providerInitialMetrics}>{content}</SafeAreaProvider>
    </ThemeProvider>
  );
}
