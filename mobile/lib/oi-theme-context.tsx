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
  darkPalette,
  getPalette,
  lightPalette,
  rowHeight,
  type OiDensity,
  type OiPalette,
  type OiThemeName,
} from "@/lib/oi-theme";

const THEME_KEY = "oi-theme";
const DENSITY_KEY = "oi-density";

export type OiThemeContextValue = {
  theme: OiThemeName;
  palette: OiPalette;
  density: OiDensity;
  rowH: number;
  setTheme: (next: OiThemeName) => void;
  setDensity: (next: OiDensity) => void;
  ready: boolean;
};

const defaultValue: OiThemeContextValue = {
  theme: "dark",
  palette: darkPalette,
  density: "normal",
  rowH: rowHeight.normal,
  setTheme: () => undefined,
  setDensity: () => undefined,
  ready: false,
};

const OiThemeContext = createContext<OiThemeContextValue>(defaultValue);

export type OiThemeProviderProps = {
  children: ReactNode;
  /** Initial theme used until persisted value is hydrated. Defaults to `dark`. */
  defaultTheme?: OiThemeName;
  defaultDensity?: OiDensity;
};

export function OiThemeProvider({
  children,
  defaultTheme = "dark",
  defaultDensity = "normal",
}: OiThemeProviderProps) {
  const [theme, setThemeState] = useState<OiThemeName>(defaultTheme);
  const [density, setDensityState] = useState<OiDensity>(defaultDensity);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const [t, d] = await Promise.all([
          AsyncStorage.getItem(THEME_KEY),
          AsyncStorage.getItem(DENSITY_KEY),
        ]);
        if (cancelled) return;
        if (t === "light" || t === "dark") setThemeState(t);
        if (d === "compact" || d === "normal" || d === "comfy") {
          setDensityState(d);
        }
      } catch {
        // ignore — fall back to defaults
      } finally {
        if (!cancelled) setReady(true);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const setTheme = useCallback((next: OiThemeName) => {
    setThemeState(next);
    void AsyncStorage.setItem(THEME_KEY, next).catch(() => undefined);
  }, []);

  const setDensity = useCallback((next: OiDensity) => {
    setDensityState(next);
    void AsyncStorage.setItem(DENSITY_KEY, next).catch(() => undefined);
  }, []);

  const value = useMemo<OiThemeContextValue>(
    () => ({
      theme,
      palette: theme === "dark" ? darkPalette : lightPalette,
      density,
      rowH: rowHeight[density],
      setTheme,
      setDensity,
      ready,
    }),
    [theme, density, setTheme, setDensity, ready],
  );

  return (
    <OiThemeContext.Provider value={value}>{children}</OiThemeContext.Provider>
  );
}

export function useOiTheme(): OiThemeContextValue {
  return useContext(OiThemeContext);
}

/** Convenience: pull palette only without re-rendering on density changes. */
export function useOiPalette(): OiPalette {
  return useContext(OiThemeContext).palette;
}

export { getPalette };
