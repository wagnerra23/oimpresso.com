import type { ReactNode } from "react";
import { Pressable, ScrollView, Text, View } from "react-native";

import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";

export type OiChipProps = {
  label: string;
  count?: number | string;
  active?: boolean;
  onPress?: () => void;
};

export function OiChip({
  label,
  count,
  active,
  onPress,
}: OiChipProps) {
  const { palette } = useOiTheme();
  const bg = active ? palette.text : palette.surface;
  const fg = active ? palette.bg : palette.textDim;
  const border = active ? palette.text : palette.border;
  return (
    <Pressable
      onPress={onPress}
      style={{
        flexDirection: "row",
        alignItems: "center",
        gap: 5,
        borderWidth: 1,
        borderColor: border,
        backgroundColor: bg,
        paddingHorizontal: 12,
        paddingVertical: 5,
        borderRadius: radius.pill,
      }}
    >
      <Text
        style={{
          color: fg,
          fontFamily: fonts.sansMedium,
          fontSize: 12,
        }}
      >
        {label}
      </Text>
      {count != null ? (
        <View
          style={{
            backgroundColor: active
              ? "rgba(255,255,255,0.18)"
              : palette.border2,
            paddingHorizontal: 5,
            borderRadius: 999,
          }}
        >
          <Text
            style={{
              color: active ? fg : palette.textMute,
              fontSize: 10,
              fontFamily: fonts.mono,
              fontVariant: ["tabular-nums"],
            }}
          >
            {count}
          </Text>
        </View>
      ) : null}
    </Pressable>
  );
}

export type OiChipsProps = { children: ReactNode };

export function OiChips({ children }: OiChipsProps) {
  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={{ gap: 6, paddingVertical: 2 }}
    >
      {children}
    </ScrollView>
  );
}

export default OiChips;
