import type { ReactNode } from "react";
import { Pressable, Text, View, type ViewStyle } from "react-native";

import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { OiIcon, type OiIconName } from "./OiIcon";

export type OiBtnVariant = "default" | "primary" | "action" | "ghost" | "danger";
export type OiBtnSize = "default" | "sm";

export type OiBtnProps = {
  label: string;
  onPress?: () => void;
  variant?: OiBtnVariant;
  size?: OiBtnSize;
  /** Fill the parent — equivalent to `.oi-btn.block`. */
  block?: boolean;
  leftIcon?: OiIconName;
  rightIcon?: OiIconName;
  disabled?: boolean;
  loading?: boolean;
  style?: ViewStyle;
  testID?: string;
};

export function OiBtn({
  label,
  onPress,
  variant = "default",
  size = "default",
  block,
  leftIcon,
  rightIcon,
  disabled,
  loading,
  style,
  testID,
}: OiBtnProps) {
  const { palette } = useOiTheme();

  const variantStyle: ViewStyle = (() => {
    switch (variant) {
      case "primary":
        return {
          backgroundColor: palette.accent,
          borderColor: palette.accent,
        };
      case "action":
        return {
          backgroundColor: palette.action,
          borderColor: palette.action,
        };
      case "danger":
        return {
          backgroundColor: palette.danger,
          borderColor: palette.danger,
        };
      case "ghost":
        return { backgroundColor: "transparent", borderColor: "transparent" };
      default:
        return {
          backgroundColor: palette.surface,
          borderColor: palette.border,
        };
    }
  })();

  const fg =
    variant === "primary" || variant === "danger"
      ? "#fff"
      : variant === "action"
        ? palette.actionFg
        : variant === "ghost"
          ? palette.textDim
          : palette.text;

  const sizeStyle =
    size === "sm"
      ? {
          height: 32,
          paddingHorizontal: 12,
          borderRadius: radius.sm,
        }
      : { height: 44, paddingHorizontal: 16, borderRadius: radius.md };

  return (
    <Pressable
      onPress={disabled || loading ? undefined : onPress}
      testID={testID}
      style={({ pressed }) => [
        {
          borderWidth: 1,
          flexDirection: "row",
          alignItems: "center",
          justifyContent: "center",
          gap: 8,
          opacity: disabled || loading ? 0.55 : 1,
          width: block ? "100%" : undefined,
        },
        variantStyle,
        sizeStyle,
        pressed
          ? {
              backgroundColor:
                variant === "primary" ? palette.accent2 : palette.bg2,
            }
          : null,
        style,
      ]}
    >
      {leftIcon ? <OiIcon name={leftIcon} size={16} color={fg} /> : null}
      <Text
        style={{
          color: fg,
          fontSize: size === "sm" ? 12.5 : 14,
          fontFamily: size === "sm" ? fonts.sansMedium : fonts.sansSemibold,
        }}
      >
        {label}
      </Text>
      {rightIcon ? <OiIcon name={rightIcon} size={16} color={fg} /> : null}
    </Pressable>
  );
}

export type OiBtnRowProps = { children: ReactNode };

export function OiBtnRow({ children }: OiBtnRowProps) {
  return (
    <View style={{ flexDirection: "row", gap: 8 }}>
      {/* Force each child to flex:1, matching `.oi-btn-row > * { flex: 1 }` */}
      {Array.isArray(children)
        ? children.map((c, i) => (
            <View key={i} style={{ flex: 1, minWidth: 0 }}>
              {c}
            </View>
          ))
        : (
            <View style={{ flex: 1, minWidth: 0 }}>{children}</View>
          )}
    </View>
  );
}

export default OiBtn;
