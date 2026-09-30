import type { ReactNode } from "react";
import { View, type ViewProps } from "react-native";

import { radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";

export type OiCardVariant = "default" | "tight" | "pad" | "hi";

export type OiCardProps = ViewProps & {
  variant?: OiCardVariant;
  children: ReactNode;
};

/**
 * Surface card matching `.oi-card` (+ `.tight`, `.pad`, `.hi`).
 *
 * - default: 14px padding, 10px gap
 * - tight:   12px padding, 6px gap
 * - pad:     16px padding
 * - hi:      tinted accent-soft background, no border (no gradient — RN flat)
 */
export function OiCard({
  variant = "default",
  style,
  children,
  ...rest
}: OiCardProps) {
  const { palette } = useOiTheme();

  const base = {
    backgroundColor: palette.surface,
    borderColor: palette.border,
    borderWidth: 1,
    borderRadius: radius.md,
  } as const;

  const variantStyle =
    variant === "tight"
      ? { padding: 12, gap: 6 }
      : variant === "pad"
        ? { padding: 16, gap: 10 }
        : variant === "hi"
          ? {
              padding: 14,
              gap: 10,
              backgroundColor: palette.accentSoft,
              borderColor: "transparent",
            }
          : { padding: 14, gap: 10 };

  return (
    <View style={[base, variantStyle, style]} {...rest}>
      {children}
    </View>
  );
}

export default OiCard;
