import type { ReactNode } from "react";
import { Children, Fragment } from "react";
import { Pressable, Text, View } from "react-native";

import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { OiIcon, type OiIconName } from "./OiIcon";

export type OiListProps = {
  children: ReactNode;
  /** Wraps the list in a bordered, rounded card (matches `.oi-list.card`). */
  card?: boolean;
};

export function OiList({ children, card }: OiListProps) {
  const { palette } = useOiTheme();
  const items = Children.toArray(children);
  const base = {
    backgroundColor: palette.surface,
  } as const;
  return (
    <View
      style={[
        base,
        card
          ? {
              borderWidth: 1,
              borderColor: palette.border,
              borderRadius: radius.md,
              overflow: "hidden",
            }
          : {
              borderTopWidth: 1,
              borderBottomWidth: 1,
              borderColor: palette.border,
            },
      ]}
    >
      {items.map((child, i) => (
        <Fragment key={i}>{child}</Fragment>
      ))}
    </View>
  );
}

export type OiListRowProps = {
  title: string;
  subtitle?: string;
  leftIcon?: OiIconName;
  /** Right-side accessory (badge, money, chevron — anything). */
  right?: ReactNode;
  /** Row leading slot (custom node) — overrides leftIcon. */
  left?: ReactNode;
  onPress?: () => void;
  /** Last row hides its bottom border to mimic the `:last-child` CSS rule. */
  last?: boolean;
};

export function OiListRow({
  title,
  subtitle,
  leftIcon,
  left,
  right,
  onPress,
  last,
}: OiListRowProps) {
  const { palette, rowH } = useOiTheme();
  return (
    <Pressable
      onPress={onPress}
      android_ripple={{ color: palette.bg2 }}
      style={({ pressed }) => [
        {
          flexDirection: "row",
          alignItems: "center",
          gap: 12,
          minHeight: rowH,
          paddingHorizontal: 16,
          paddingVertical: 8,
          borderBottomColor: palette.border2,
          borderBottomWidth: last ? 0 : 1,
        },
        pressed ? { backgroundColor: palette.bg2 } : null,
      ]}
    >
      {left ?? (leftIcon ? (
        <OiIcon name={leftIcon} size={20} color={palette.textDim} />
      ) : null)}
      <View style={{ flex: 1, minWidth: 0, gap: 2 }}>
        <Text
          numberOfLines={1}
          style={{
            color: palette.text,
            fontFamily: fonts.sansMedium,
            fontSize: 14.5,
          }}
        >
          {title}
        </Text>
        {subtitle ? (
          <Text
            numberOfLines={1}
            style={{
              color: palette.textDim,
              fontFamily: fonts.sans,
              fontSize: 12.5,
            }}
          >
            {subtitle}
          </Text>
        ) : null}
      </View>
      {right}
    </Pressable>
  );
}

export default OiList;
