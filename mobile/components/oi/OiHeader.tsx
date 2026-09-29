import type { ReactNode } from "react";
import { Pressable, Text, View } from "react-native";

import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { OiIcon, type OiIconName } from "./OiIcon";

export type OiHeaderAction = {
  icon: OiIconName;
  onPress?: () => void;
  badge?: number | string;
  testID?: string;
};

export type OiHeaderProps = {
  /** Small uppercase line above the title. */
  eyebrow?: string;
  title: string;
  /** Right-side icon buttons (bell, dots, etc). */
  actions?: OiHeaderAction[];
  /** Optional tenant pill or any node rendered above the title row. */
  tenant?: ReactNode;
  /** Optional content below the title (search bar, chips). */
  children?: ReactNode;
};

/**
 * Top-of-screen header — eyebrow + title + icon actions + optional slot.
 */
export function OiHeader({
  eyebrow,
  title,
  actions,
  tenant,
  children,
}: OiHeaderProps) {
  const { palette } = useOiTheme();
  return (
    <View
      style={{
        paddingHorizontal: 16,
        paddingTop: 8,
        paddingBottom: 12,
        backgroundColor: palette.bg,
        borderBottomColor: palette.border,
        borderBottomWidth: 1,
        gap: 8,
      }}
    >
      {tenant ? (
        <View style={{ flexDirection: "row", alignItems: "center" }}>
          {tenant}
        </View>
      ) : null}

      <View
        style={{
          flexDirection: "row",
          alignItems: "center",
          gap: 10,
          minHeight: 38,
        }}
      >
        <View style={{ flex: 1, minWidth: 0 }}>
          {eyebrow ? (
            <Text
              style={{
                fontSize: 10.5,
                fontFamily: fonts.sansBold,
                letterSpacing: 1.2,
                textTransform: "uppercase",
                color: palette.textMute,
                marginBottom: 2,
              }}
            >
              {eyebrow}
            </Text>
          ) : null}
          <Text
            style={{
              fontSize: 22,
              fontFamily: fonts.sansSemibold,
              letterSpacing: -0.4,
              lineHeight: 26,
              color: palette.text,
            }}
            numberOfLines={1}
          >
            {title}
          </Text>
        </View>

        {actions?.map((a, i) => (
          <Pressable
            key={`${a.icon}-${i}`}
            onPress={a.onPress}
            testID={a.testID}
            style={{
              width: 38,
              height: 38,
              alignItems: "center",
              justifyContent: "center",
              borderRadius: radius.sm,
            }}
          >
            <OiIcon name={a.icon} size={22} color={palette.textDim} />
            {a.badge != null ? (
              <View
                style={{
                  position: "absolute",
                  top: 6,
                  right: 6,
                  minWidth: 14,
                  height: 14,
                  paddingHorizontal: 3,
                  backgroundColor: palette.danger,
                  borderRadius: 999,
                  alignItems: "center",
                  justifyContent: "center",
                  borderWidth: 2,
                  borderColor: palette.bg,
                }}
              >
                <Text
                  style={{
                    color: "#fff",
                    fontSize: 9,
                    fontFamily: fonts.sansBold,
                  }}
                >
                  {a.badge}
                </Text>
              </View>
            ) : null}
          </Pressable>
        ))}
      </View>

      {children}
    </View>
  );
}

export default OiHeader;
