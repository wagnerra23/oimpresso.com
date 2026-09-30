import type { ReactNode } from "react";
import { View, type ViewProps, type ViewStyle } from "react-native";
import { SafeAreaView, type Edge } from "react-native-safe-area-context";

import { useOiTheme } from "@/lib/oi-theme-context";

export type OiScreenProps = ViewProps & {
  children: ReactNode;
  /** Safe area edges to inset. Defaults to top + bottom. */
  edges?: readonly Edge[];
  /** Skip the SafeAreaView wrapper (useful if the parent already handles it). */
  noSafeArea?: boolean;
  contentStyle?: ViewStyle;
};

/**
 * Root container for an Oimpresso screen. Paints the design `--bg` so headers
 * and lists sit on top of a uniform surface, and applies safe-area insets so
 * status bar / home indicator never overlap content.
 */
export function OiScreen({
  children,
  edges = ["top", "bottom"],
  noSafeArea,
  style,
  contentStyle,
  ...rest
}: OiScreenProps) {
  const { palette } = useOiTheme();
  const bg = { backgroundColor: palette.bg, flex: 1 } as const;

  if (noSafeArea) {
    return (
      <View style={[bg, style]} {...rest}>
        <View style={[{ flex: 1 }, contentStyle]}>{children}</View>
      </View>
    );
  }

  return (
    <SafeAreaView edges={edges} style={[bg, style]}>
      <View style={[{ flex: 1 }, contentStyle]} {...rest}>
        {children}
      </View>
    </SafeAreaView>
  );
}

export default OiScreen;
