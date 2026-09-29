import { Text, type TextProps, type TextStyle } from "react-native";

import { fonts } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";

export type OiMoneyProps = Omit<TextProps, "children"> & {
  /** Value in BRL. Can be a number (cents-aware via `cents` prop) or string. */
  value: number;
  /** If true, `value` is interpreted as cents and divided by 100. */
  cents?: boolean;
  /** Hide currency prefix (`R$`). */
  bare?: boolean;
  /** Optional style override. */
  style?: TextStyle | TextStyle[];
  /** Color override (defaults to palette.text). */
  color?: string;
  /** Font size override. */
  size?: number;
  /** Use bold/semibold mono. */
  weight?: "regular" | "medium" | "semibold";
};

const BRL = new Intl.NumberFormat("pt-BR", {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

/**
 * Currency display — mono digits, tabular nums, pt-BR formatting.
 */
export function OiMoney({
  value,
  cents,
  bare,
  style,
  color,
  size = 14,
  weight = "medium",
  ...rest
}: OiMoneyProps) {
  const { palette } = useOiTheme();
  const n = cents ? value / 100 : value;
  const formatted = BRL.format(n);
  const family =
    weight === "semibold"
      ? fonts.monoSemibold
      : weight === "regular"
        ? fonts.mono
        : fonts.monoMedium;
  return (
    <Text
      {...rest}
      style={[
        {
          color: color ?? palette.text,
          fontFamily: family,
          fontSize: size,
          fontVariant: ["tabular-nums"],
          letterSpacing: -0.1,
        },
        style as TextStyle,
      ]}
    >
      {bare ? formatted : `R$ ${formatted}`}
    </Text>
  );
}

export default OiMoney;
