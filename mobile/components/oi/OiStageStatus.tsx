/**
 * OiStageStatus — selo de etapa colorido com `.dot`.
 *
 * Bloco 9.5. Diferente de `OiStatus` (que é genérico): este é otimizado para
 * representar uma etapa de pipeline (texto curto, dot maior, tom uniforme).
 */
import { Text, View } from "react-native";

import { fonts, hexAlpha, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";

export type OiStageTone = "neutral" | "ok" | "warn" | "accent" | "danger";

export type OiStageStatusProps = {
  label: string;
  tone?: OiStageTone;
};

export function OiStageStatus({
  label,
  tone = "neutral",
}: OiStageStatusProps) {
  const { palette } = useOiTheme();
  const color = (() => {
    switch (tone) {
      case "ok":
        return palette.ok;
      case "warn":
        return palette.warn;
      case "danger":
        return palette.danger;
      case "accent":
        return palette.accent;
      default:
        return palette.textDim;
    }
  })();
  return (
    <View
      style={{
        flexDirection: "row",
        alignItems: "center",
        gap: 6,
        alignSelf: "flex-start",
        paddingHorizontal: 8,
        paddingVertical: 3,
        borderRadius: radius.pill,
        backgroundColor: hexAlpha(color, 0.16),
      }}
    >
      <View
        style={{
          width: 7,
          height: 7,
          borderRadius: 999,
          backgroundColor: color,
        }}
      />
      <Text
        style={{
          fontFamily: fonts.sansSemibold,
          fontSize: 11,
          color,
        }}
      >
        {label}
      </Text>
    </View>
  );
}

export default OiStageStatus;
