import { Tabs } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { Platform, View } from "react-native";
import React, { useMemo } from "react";

import { HapticTab } from "@/components/haptic-tab";
import { OfflineBanner } from "@/components/offline-banner";
import { OiIcon, type OiIconName } from "@/components/oi";
import { fonts } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import {
  getMenuModule,
  type MenuModule,
  type TabRouteName,
} from "@/lib/menu-modules";
import { useActiveMenuProfile } from "@/lib/menu-profile-context";
import { useTarefas } from "@/lib/use-tarefas";

/**
 * Tabbar dinâmica governada pelo Perfil de Menu ativo (Bloco 1).
 *
 * Layout: **Início** (sempre 1º) + até 3 módulos do perfil + **Mais** (sempre
 * último). Demais rotas continuam registradas com `href: null` para que
 * `router.push("/(tabs)/clientes")` (vinda de Mais ou de deep-links) siga
 * funcionando. Quando o perfil muda, a barra se reconfigura sem remount do
 * `<Tabs>` — só as opções de cada `Tabs.Screen` mudam.
 *
 * Mapeamento `MenuModule.route → arquivo em (tabs)/`:
 *   - `tarefas`/`producao`/`clientes`/`produtos`/`relatorios`/`financeiro` → mesmas rotas
 *   - `pedidos` e `vendas` → ambos para `(tabs)/vendas` (split planejado em Bloco 8)
 *   - `manutencao` → `(tabs)/oss` até a vertical Oficina ficar pronta (Bloco 3)
 *   - `equipamentos` → `(tabs)/veiculos` até Bloco 4
 */

// IMPORTANT: tab icon components MUST be stable across renders, otherwise
// react-navigation re-mounts the Screen which can trigger an infinite render
// loop (useSyncState fires → parent re-renders → new icon refs → re-mount).
const HomeIcon = ({ color }: { color: string }) => <OiIcon name="home" size={22} color={color} />;
const DotsIcon = ({ color }: { color: string }) => <OiIcon name="dots" size={22} color={color} />;

// Ícones estáveis para cada nome usado pelos módulos. Definidos no nível de
// módulo (não dentro do componente) pra preservar identidade entre renders.
function makeIcon(name: OiIconName) {
  const Comp = ({ color }: { color: string }) => (
    <OiIcon name={name} size={22} color={color} />
  );
  Comp.displayName = `TabIcon(${name})`;
  return Comp;
}

const TAB_ICONS = {
  inbox: makeIcon("inbox"),
  file: makeIcon("file"),
  tag: makeIcon("tag"),
  printer: makeIcon("printer"),
  wrench: makeIcon("wrench"),
  truck: makeIcon("truck"),
  dollar: makeIcon("dollar"),
  chart: makeIcon("chart"),
  user: makeIcon("user"),
  box: makeIcon("box"),
} as const;

function iconForModule(m: MenuModule) {
  type IconCmp = ({ color }: { color: string }) => React.ReactElement;
  return (TAB_ICONS as Record<string, IconCmp>)[m.icon] ?? TAB_ICONS.box;
}

/** Rotas que devem permanecer registradas (todas as `(tabs)/<file>.tsx`). */
const HIDDEN_FALLBACK_ROUTES: readonly TabRouteName[] = [
  "tarefas",
  "vendas",
  "producao",
  "produtos",
  "clientes",
  "orcamentos",
  "financeiro",
  "chat",
  "estoque",
  "veiculos",
  "oss",
  "manutencao",
  "equipamentos",
  "relatorios",
  "pagamentos",
  "fiscal",
  "dashboard",
];

export default function TabLayout() {
  const { palette } = useOiTheme();
  const insets = useSafeAreaInsets();
  const activeProfile = useActiveMenuProfile();
  // Bloco 2.12 — badge de tarefas urgentes no tab Tarefas
  const tarefas = useTarefas();
  const urgenteCount = useMemo(
    () => (tarefas.tarefas ?? []).filter((t) => t.urgente).length,
    [tarefas.tarefas],
  );
  const bottomPadding = Platform.OS === "web" ? 12 : Math.max(insets.bottom, 8);
  const tabBarHeight = 60 + bottomPadding;

  // Memoize screenOptions so identity is stable across renders unless the
  // palette actually changes (e.g., theme switch). Without this the Tabs
  // subtree thrashes on every re-render of TabLayout.
  const screenOptions = useMemo(
    () => ({
      tabBarActiveTintColor: palette.accent,
      tabBarInactiveTintColor: palette.textMute,
      headerShown: false,
      tabBarButton: HapticTab,
      tabBarLabelStyle: {
        fontFamily: fonts.sansMedium,
        fontSize: 10,
        letterSpacing: 0.2,
      },
      tabBarStyle: {
        paddingTop: 6,
        paddingBottom: bottomPadding,
        height: tabBarHeight,
        backgroundColor: palette.surface,
        borderTopColor: palette.border,
        borderTopWidth: 1,
      },
    }),
    [
      palette.accent,
      palette.textMute,
      palette.surface,
      palette.border,
      bottomPadding,
      tabBarHeight,
    ],
  );

  /**
   * Reduz os módulos do perfil ativo a um mapa `route → MenuModule` (o primeiro
   * módulo vence em caso de empate de rota — ex.: `pedidos` ganha de `vendas`
   * se vier primeiro). A ordem do array é preservada via `visibleRoutes`.
   */
  const { visibleRouteSet, moduleByRoute, visibleRoutes } = useMemo(() => {
    const map = new Map<TabRouteName, MenuModule>();
    const order: TabRouteName[] = [];
    for (const modId of activeProfile.mods) {
      const mod = getMenuModule(modId);
      if (!mod) continue;
      if (!map.has(mod.route)) {
        map.set(mod.route, mod);
        order.push(mod.route);
      }
    }
    return {
      visibleRouteSet: new Set<TabRouteName>(order),
      moduleByRoute: map,
      visibleRoutes: order,
    };
  }, [activeProfile.mods]);

  /** Rotas que sobram pra ficar como `href: null` (em qualquer ordem). */
  const hiddenRoutes = useMemo(
    () => HIDDEN_FALLBACK_ROUTES.filter((r) => !visibleRouteSet.has(r)),
    [visibleRouteSet],
  );

  return (
    <View style={{ flex: 1, backgroundColor: palette.bg }}>
      <OfflineBanner />
      <Tabs screenOptions={screenOptions}>
        {/* 1º slot fixo */}
        <Tabs.Screen
          name="index"
          options={{ title: "Início", tabBarIcon: HomeIcon }}
        />

        {/* 2º–4º slots — módulos do perfil ativo (até 3, ordem preservada) */}
        {visibleRoutes.map((route) => {
          const mod = moduleByRoute.get(route)!;
          const badge =
            route === "tarefas" && urgenteCount > 0 ? urgenteCount : undefined;
          return (
            <Tabs.Screen
              key={route}
              name={route}
              options={{
                title: mod.label,
                tabBarIcon: iconForModule(mod),
                tabBarBadge: badge,
              }}
            />
          );
        })}

        {/* Último slot fixo */}
        <Tabs.Screen
          name="mais"
          options={{ title: "Mais", tabBarIcon: DotsIcon }}
        />

        {/* Demais rotas — registradas mas escondidas da tabbar */}
        {hiddenRoutes.map((route) => (
          <Tabs.Screen key={route} name={route} options={{ href: null }} />
        ))}
      </Tabs>
    </View>
  );
}
