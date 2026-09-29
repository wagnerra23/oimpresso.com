import { Text, View } from "react-native";

import { fonts, hexAlpha, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";

export type OiStatusVariant = "neutral" | "ok" | "warn" | "danger" | "accent";

export type OiStatusProps = {
  label: string;
  variant?: OiStatusVariant;
};

/**
 * Pill with leading dot — matches `.oi-status` and `.ok/.warn/.danger/.accent`.
 */
export function OiStatus({
  label,
  variant = "neutral",
}: OiStatusProps) {
  const { palette } = useOiTheme();

  const tone = (() => {
    switch (variant) {
      case "ok":
        return { bg: hexAlpha(palette.ok, 0.18), fg: palette.ok };
      case "warn":
        return { bg: hexAlpha(palette.warn, 0.22), fg: palette.warn };
      case "danger":
        return { bg: hexAlpha(palette.danger, 0.18), fg: palette.danger };
      case "accent":
        return { bg: palette.accentSoft, fg: palette.accent };
      default:
        return { bg: palette.bg2, fg: palette.textDim };
    }
  })();

  return (
    <View
      style={{
        flexDirection: "row",
        alignItems: "center",
        gap: 5,
        paddingHorizontal: 8,
        paddingVertical: 2,
        backgroundColor: tone.bg,
        borderRadius: radius.pill,
        alignSelf: "flex-start",
      }}
    >
      <View
        style={{
          width: 6,
          height: 6,
          borderRadius: 3,
          backgroundColor: tone.fg,
        }}
      />
      <Text
        style={{
          color: tone.fg,
          fontFamily: fonts.sansMedium,
          fontSize: 11,
        }}
      >
        {label}
      </Text>
    </View>
  );
}

export default OiStatus;
