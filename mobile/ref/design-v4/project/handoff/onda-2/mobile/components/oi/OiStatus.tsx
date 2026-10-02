import { Text, View } from "react-native";

import { fonts, hexAlpha, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";

export type OiStatusVariant = "neutral" | "ok" | "warn" | "danger" | "accent" | "info";

export type OiStatusProps = {
  label: string;
  variant?: OiStatusVariant;
};

/**
 * Pill com ponto — DS StatusBadge: fundo tintado ~10% + borda 22% no tom, nunca pastel sólido (AP7).
 * Onda 2: + variante "info"; tint reduzido de 18–22% para 10%.
 */
export function OiStatus({ label, variant = "neutral" }: OiStatusProps) {
  const { palette } = useOiTheme();
  const c = (() => {
    switch (variant) {
      case "ok": return palette.ok;
      case "warn": return palette.warn;
      case "danger": return palette.danger;
      case "accent": return palette.accent;
      case "info": return palette.info;
      default: return null;
    }
  })();
  const bg = c ? hexAlpha(c, 0.1) : palette.bg2;
  const border = c ? hexAlpha(c, 0.22) : palette.border;
  const fg = c ?? palette.textDim;

  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: 5, paddingHorizontal: 8, paddingVertical: 2, backgroundColor: bg, borderColor: border, borderWidth: 1, borderRadius: radius.pill, alignSelf: "flex-start" }}>
      <View style={{ width: 6, height: 6, borderRadius: 3, backgroundColor: fg }} />
      <Text style={{ color: fg, fontFamily: fonts.sansMedium, fontSize: 11 }}>{label}</Text>
    </View>
  );
}

export default OiStatus;
