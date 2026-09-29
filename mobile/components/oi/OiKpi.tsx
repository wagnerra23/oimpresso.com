import { Text, View, type ViewStyle } from "react-native";

import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";

export type OiKpiProps = {
  label: string;
  value: string;
  /** Trend string (`+12%`, `-3%`). Color picked from sign. */
  trend?: string;
  /** Emphasis variant — `warn` paints the value with the danger color. */
  variant?: "default" | "warn";
  style?: ViewStyle;
};

export function OiKpi({
  label,
  value,
  trend,
  variant = "default",
  style,
}: OiKpiProps) {
  const { palette } = useOiTheme();
  const valueColor = variant === "warn" ? palette.danger : palette.text;
  const trendDown = trend?.startsWith("-");
  return (
    <View
      style={[
        {
          flex: 1,
          backgroundColor: palette.surface,
          borderColor: palette.border,
          borderWidth: 1,
          borderRadius: radius.md,
          padding: 12,
          gap: 2,
        },
        style,
      ]}
    >
      <Text
        style={{
          fontSize: 10,
          fontFamily: fonts.sansBold,
          letterSpacing: 0.8,
          textTransform: "uppercase",
          color: palette.textMute,
        }}
        numberOfLines={1}
      >
        {label}
      </Text>
      <Text
        style={{
          fontSize: 22,
          fontFamily: fonts.monoMedium,
          fontVariant: ["tabular-nums"],
          letterSpacing: -0.4,
          color: valueColor,
          lineHeight: 24,
        }}
        numberOfLines={1}
      >
        {value}
      </Text>
      {trend ? (
        <Text
          style={{
            fontSize: 11,
            color: trendDown ? palette.danger : palette.ok,
            fontFamily: fonts.sansMedium,
            marginTop: 2,
          }}
        >
          {trend}
        </Text>
      ) : null}
    </View>
  );
}

export type OiKpisProps = {
  children: React.ReactNode;
};

export function OiKpis({ children }: OiKpisProps) {
  return (
    <View style={{ flexDirection: "row", gap: 8 }}>{children}</View>
  );
}

export default OiKpi;
