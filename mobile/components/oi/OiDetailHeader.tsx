import type { ReactNode } from "react";
import { Pressable, Text, View } from "react-native";

import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { OiIcon } from "./OiIcon";

export type OiDetailHeaderProps = {
  title: string;
  /** Optional eyebrow rendered above the title (small uppercase). */
  eyebrow?: string;
  onBack?: () => void;
  /** Right-side slot (action icons, dots menu). */
  right?: ReactNode;
};

/**
 * Sub-screen header — chevron back + title (with optional eyebrow) + optional trailing slot.
 */
export function OiDetailHeader({
  title,
  eyebrow,
  onBack,
  right,
}: OiDetailHeaderProps) {
  const { palette } = useOiTheme();
  return (
    <View
      style={{
        flexDirection: "row",
        alignItems: "center",
        gap: 8,
        paddingTop: 4,
        paddingRight: 8,
        paddingLeft: 8,
        paddingBottom: 10,
        borderBottomColor: palette.border,
        borderBottomWidth: 1,
        backgroundColor: palette.bg,
      }}
    >
      <Pressable
        onPress={onBack}
        hitSlop={6}
        style={{
          width: 40,
          height: 40,
          alignItems: "center",
          justifyContent: "center",
          borderRadius: radius.sm,
        }}
      >
        <OiIcon name="chev-l" size={22} color={palette.accent} />
      </Pressable>
      <View style={{ flex: 1, minWidth: 0 }}>
        {eyebrow ? (
          <Text
            style={{
              fontSize: 10.5,
              fontFamily: fonts.sansBold,
              letterSpacing: 1,
              textTransform: "uppercase",
              color: palette.textMute,
              marginBottom: 1,
            }}
            numberOfLines={1}
          >
            {eyebrow}
          </Text>
        ) : null}
        <Text
          style={{
            fontFamily: fonts.sansSemibold,
            fontSize: 17,
            color: palette.text,
            letterSpacing: -0.2,
          }}
          numberOfLines={1}
        >
          {title}
        </Text>
      </View>
      {right}
    </View>
  );
}

export default OiDetailHeader;
