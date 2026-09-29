/**
 * OiToastHost + useToast — toast global (Bloco 9.7 + 12.1).
 *
 * Pilha de toasts que sobem do rodapé (~78px), animação fade+slide, somem em
 * 2.4s. Equivalente a `window.oiToast(msg, tone)` do design.
 *
 * Uso:
 *  ```tsx
 *  const { show } = useToast();
 *  show("Pedido salvo", "ok");
 *  ```
 *
 * Montar `<OiToastHost />` no topo (já feito em app/_layout.tsx). Sem isso,
 * `show()` é no-op.
 */
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { Animated, StyleSheet, Text, View } from "react-native";

import { fonts, radius, shadows } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";

export type ToastTone = "default" | "ok" | "danger" | "warn";

type ToastEntry = {
  id: string;
  msg: string;
  tone: ToastTone;
  /** Quanto tempo o toast fica (ms). */
  duration: number;
};

type ToastContextValue = {
  show: (msg: string, tone?: ToastTone, durationMs?: number) => void;
};

const ToastContext = createContext<ToastContextValue>({
  show: () => undefined,
});

/** Provider sem UI — apenas guarda o callback. O Host renderiza. */
export function ToastProvider({ children }: { children: ReactNode }) {
  const listenersRef = useRef(new Set<(t: ToastEntry) => void>());

  const show = useCallback(
    (msg: string, tone: ToastTone = "default", durationMs = 2400) => {
      const entry: ToastEntry = {
        id: `t-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
        msg,
        tone,
        duration: durationMs,
      };
      listenersRef.current.forEach((fn) => fn(entry));
    },
    [],
  );

  const value = useMemo<ToastContextValue & {
    __subscribe: (fn: (t: ToastEntry) => void) => () => void;
  }>(
    () => ({
      show,
      __subscribe: (fn) => {
        listenersRef.current.add(fn);
        return () => {
          listenersRef.current.delete(fn);
        };
      },
    }),
    [show],
  );

  return (
    <ToastContext.Provider value={value as ToastContextValue}>
      {children}
    </ToastContext.Provider>
  );
}

export function useToast(): ToastContextValue {
  return useContext(ToastContext);
}

/** Host visual — empilha toasts no rodapé. Coloque no topo da árvore. */
export function OiToastHost() {
  const { palette } = useOiTheme();
  const ctx = useContext(ToastContext) as ToastContextValue & {
    __subscribe?: (fn: (t: ToastEntry) => void) => () => void;
  };
  const [stack, setStack] = useState<ToastEntry[]>([]);

  useEffect(() => {
    if (!ctx.__subscribe) return;
    const unsub = ctx.__subscribe((entry) => {
      setStack((curr) => [...curr, entry]);
      setTimeout(() => {
        setStack((curr) => curr.filter((t) => t.id !== entry.id));
      }, entry.duration);
    });
    return unsub;
  }, [ctx]);

  if (stack.length === 0) return null;

  return (
    <View pointerEvents="none" style={styles.host}>
      {stack.map((t) => (
        <ToastPill key={t.id} entry={t} palette={palette} />
      ))}
    </View>
  );
}

function ToastPill({
  entry,
  palette,
}: {
  entry: ToastEntry;
  palette: ReturnType<typeof useOiTheme>["palette"];
}) {
  const opacity = useRef(new Animated.Value(0)).current;
  const translate = useRef(new Animated.Value(20)).current;

  useEffect(() => {
    Animated.parallel([
      Animated.timing(opacity, {
        toValue: 1,
        duration: 220,
        useNativeDriver: true,
      }),
      Animated.timing(translate, {
        toValue: 0,
        duration: 220,
        useNativeDriver: true,
      }),
    ]).start();
    const out = setTimeout(() => {
      Animated.parallel([
        Animated.timing(opacity, {
          toValue: 0,
          duration: 200,
          useNativeDriver: true,
        }),
        Animated.timing(translate, {
          toValue: 12,
          duration: 200,
          useNativeDriver: true,
        }),
      ]).start();
    }, entry.duration - 220);
    return () => clearTimeout(out);
  }, [opacity, translate, entry.duration]);

  const bg = (() => {
    switch (entry.tone) {
      case "ok":
        return palette.ok;
      case "danger":
        return palette.danger;
      case "warn":
        return palette.warn;
      default:
        return palette.surface2;
    }
  })();
  const fg = entry.tone === "default" ? palette.text : "#fff";

  return (
    <Animated.View
      style={{
        opacity,
        transform: [{ translateY: translate }],
        marginTop: 6,
        paddingHorizontal: 16,
        paddingVertical: 11,
        borderRadius: radius.lg,
        backgroundColor: bg,
        borderColor: entry.tone === "default" ? palette.border : "transparent",
        borderWidth: 1,
        ...shadows.pop,
        maxWidth: 380,
      }}
    >
      <Text
        style={{
          color: fg,
          fontFamily: fonts.sansSemibold,
          fontSize: 13,
        }}
        numberOfLines={3}
      >
        {entry.msg}
      </Text>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  host: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 78,
    alignItems: "center",
    zIndex: 9999,
    pointerEvents: "box-none",
  },
});

export default OiToastHost;
