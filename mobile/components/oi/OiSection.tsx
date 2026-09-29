import type { ReactNode } from "react";
import { Pressable, Text, View } from "react-native";

import { fonts } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";

export type OiSectionHeaderProps = {
  title: string;
  more?: { label: string; onPress?: () => void };
};

export function OiSectionHeader({
  title,
  more,
}: OiSectionHeaderProps) {
  const { palette } = useOiTheme();
  return (
    <View
      style={{
        flexDirection: "row",
        alignItems: "center",
        gap: 6,
        marginBottom: 8,
      }}
    >
      <Text
        style={{
          flex: 1,
          fontSize: 10.5,
          fontFamily: fonts.sansBold,
          letterSpacing: 1.2,
          textTransform: "uppercase",
          color: palette.textMute,
        }}
      >
        {title}
      </Text>
      {more ? (
        <Pressable onPress={more.onPress}>
          <Text
            style={{
              color: palette.accent,
              fontSize: 11.5,
              fontFamily: fonts.sansSemibold,
            }}
          >
            {more.label}
          </Text>
        </Pressable>
      ) : null}
    </View>
  );
}

export type OiSectionProps = {
  title?: string;
  more?: OiSectionHeaderProps["more"];
  children: ReactNode;
  /** Skip default 14/16 padding. */
  noPad?: boolean;
};

export function OiSection({
  title,
  more,
  children,
  noPad,
}: OiSectionProps) {
  return (
    <View
      style={{
        paddingHorizontal: noPad ? 0 : 16,
        paddingTop: noPad ? 0 : 14,
        paddingBottom: noPad ? 0 : 6,
      }}
    >
      {title ? <OiSectionHeader title={title} more={more} /> : null}
      {children}
    </View>
  );
}

export default OiSection;
