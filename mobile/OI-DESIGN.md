# Oimpresso Mobile — Design System (Phase 1: Foundation)

This document covers the primitives layer built in Phase 1. Existing screens
under `app/(tabs)/*.tsx` are **not** migrated yet — they keep working with
`erp-ui.tsx` and `ScreenContainer`. Phase 2 will refactor them.

## Philosophy

- **Dark-first.** The reference design is dark; light is a recolor of the same
  layout. We honor the user's `oi-theme` preference (persisted in
  AsyncStorage), defaulting to dark on first launch.
- **IBM Plex everywhere.** Sans for prose, Mono for numbers (KPI values,
  money, origin codes). Fonts ship via `@expo-google-fonts/ibm-plex-*` and
  are loaded in `app/_layout.tsx` before any screen renders.
- **OKLCH → hex.** React Native cannot parse `oklch()`. Every color in
  `ref/design/oimpresso-tokens.css` was converted with `culori`
  (`formatHex(oklch(l, c, h))`) and pasted into `lib/oi-theme.ts`. To
  re-derive a token after a design edit:
  ```js
  const { formatHex } = require("culori");
  formatHex({ mode: "oklch", l: 0.58, c: 0.09, h: 220 });
  // → "#2d869f"
  ```
- **Style prop over Tailwind classes** for color. NativeWind 4 can theme
  via CSS variables, but mixing that with our color tokens caused races in
  earlier prototypes. So: classes for layout (`flex`, padding, margins),
  inline `style={{ color: palette.x }}` for color. Both work side by side.

## How to use

```tsx
import {
  OiScreen, OiHeader, OiSection, OiCard, OiKpi, OiKpis,
  OiList, OiListRow, OiBtn, OiSearch, OiChip, OiChips,
  OiOrigin, OiStatus, OiEmpty, OiFab, OiSheet, OiTabbar,
  OiAvatar, OiDl, OiDlRow, OiProgress, OiMoney, OiTenantPill,
  OiDetailHeader, OiScanline, OiIcon,
} from "@/components/oi";
import { useOiTheme } from "@/lib/oi-theme-context";
```

Minimal screen:

```tsx
function Example() {
  const { palette, setTheme, theme } = useOiTheme();
  return (
    <OiScreen>
      <OiHeader
        eyebrow="Painel"
        title="Hoje"
        actions={[
          { icon: "bell", badge: 3 },
          { icon: "dots-v" },
        ]}
        tenant={<OiTenantPill name="Gráfica Oimpresso" />}
      />
      <OiSection title="Resumo" more={{ label: "Ver tudo" }}>
        <OiKpis>
          <OiKpi label="OS abertas" value="12" trend="+2" />
          <OiKpi label="A faturar" value="R$ 8.4k" />
          <OiKpi label="Atrasadas" value="3" variant="warn" />
        </OiKpis>
      </OiSection>
    </OiScreen>
  );
}
```

## Theme switching

```tsx
const { theme, setTheme } = useOiTheme();
setTheme(theme === "dark" ? "light" : "dark");
```

The hook also exposes `density` (`compact` | `normal` | `comfy`) and
`setDensity`. Row primitives (`OiListRow`) read `rowH` from the context, so
toggling density updates list heights everywhere instantly.

## Wiring (already done in `_layout.tsx`)

```tsx
<OiThemeProvider>
  <AuthGate>...</AuthGate>
</OiThemeProvider>
```

The provider hydrates the persisted preference asynchronously; until it
returns, components render with the `defaultTheme` (dark). Fonts are loaded
before the provider tree renders via `useFonts`; `SplashScreen.hideAsync()`
fires only after fonts resolve.

## Files created

```
lib/oi-theme.ts                 # tokens, palettes, radius, fonts, helpers
lib/oi-theme-context.tsx        # provider + useOiTheme hook
components/oi/
  index.ts                      # barrel
  OiAvatar.tsx
  OiBtn.tsx                     # + OiBtnRow
  OiCard.tsx
  OiChips.tsx                   # + OiChip
  OiDetailHeader.tsx
  OiDl.tsx                      # + OiDlRow, OiDt, OiDd
  OiEmpty.tsx
  OiFab.tsx
  OiHeader.tsx
  OiIcon.tsx                    # name map → MaterialIcons
  OiKpi.tsx                     # + OiKpis
  OiList.tsx                    # + OiListRow
  OiMoney.tsx
  OiOrigin.tsx
  OiProgress.tsx
  OiScanline.tsx                # reanimated `oi-scan` loop
  OiScreen.tsx
  OiSearch.tsx
  OiSection.tsx                 # + OiSectionHeader
  OiSheet.tsx                   # Modal-based bottom sheet
  OiStatus.tsx
  OiTabbar.tsx                  # + OiTab
  OiTenantPill.tsx
OI-DESIGN.md                    # this file
```

## Migrating an old screen (Phase 2 cheat-sheet)

| Old                            | New                                            |
| ------------------------------ | ---------------------------------------------- |
| `ScreenContainer`              | `OiScreen`                                     |
| Custom `<View>` header         | `OiHeader`                                     |
| `Card` from `erp-ui.tsx`       | `OiCard`                                       |
| `FormInput` (search use)       | `OiSearch`                                     |
| `Button` (primary CTA)         | `<OiBtn variant="primary" />`                  |
| Stat tiles                     | `OiKpi` inside `OiKpis`                        |
| Hand-rolled list rows          | `OiList` + `OiListRow`                         |
| `Modal` confirms               | `OiSheet`                                      |
| Currency rendering (`R$ …`)    | `OiMoney`                                      |

Refactor one tab at a time so review diffs stay small.

## What's deliberately NOT in Phase 1

- Screen refactors. Foundation only.
- Replacement of `erp-ui.tsx` / `screen-container.tsx`. Both coexist.
- `@gorhom/bottom-sheet`. `OiSheet` is a plain `Modal` for now — good enough
  for confirms; swap if we add pull-to-dismiss.
- Linear gradients for avatars. Single-stop fallback; if the design diff
  bites, layer `expo-linear-gradient` later.
- A custom phosphor icon set. `OiIcon` maps each design name to MaterialIcons.
