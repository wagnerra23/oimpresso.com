import { View } from "react-native";

import { useOiTheme } from "@/lib/oi-theme-context";

export type OiProgressVariant = "accent" | "warn" | "danger" | "ok";

export type OiProgressProps = {
  /** 0..1 (clamped). */
  value: number;
  variant?: OiProgressVariant;
  height?: number;
};

export function OiProgress({
  value,
  variant = "accent",
  height = 6,
}: OiProgressProps) {
  const { palette } = useOiTheme();
  const pct = Math.max(0, Math.min(1, value));
  const fill =
    variant === "warn"
      ? palette.warn
      : variant === "danger"
        ? palette.danger
        : variant === "ok"
          ? palette.ok
          : palette.accent;
  return (
    <View
      style={{
        height,
        backgroundColor: palette.bg2,
        borderRadius: 99,
        overflow: "hidden",
      }}
    >
      <View
        style={{
          width: `${pct * 100}%`,
          height: "100%",
          backgroundColor: fill,
          borderRadius: 99,
        }}
      />
    </View>
  );
}

export default OiProgress;
