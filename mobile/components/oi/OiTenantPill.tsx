import { Pressable, Text, View } from "react-native";

import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";

export type OiTenantPillProps = {
  name: string;
  /** Optional avatar color (defaults to the design accent). */
  color?: string;
  onPress?: () => void;
};

/**
 * Compact tenant chip used in the header — squared avatar + company name.
 */
export function OiTenantPill({
  name,
  color,
  onPress,
}: OiTenantPillProps) {
  const { palette } = useOiTheme();
  const initial = (name?.trim()?.[0] ?? "?").toUpperCase();
  const avBg = color ?? palette.accent;
  return (
    <Pressable
      onPress={onPress}
      style={{
        flexDirection: "row",
        alignItems: "center",
        gap: 8,
        backgroundColor: palette.bg2,
        borderColor: palette.border,
        borderWidth: 1,
        borderRadius: radius.pill,
        paddingVertical: 5,
        paddingLeft: 6,
        paddingRight: 10,
      }}
    >
      <View
        style={{
          width: 20,
          height: 20,
          borderRadius: 4,
          backgroundColor: avBg,
          alignItems: "center",
          justifyContent: "center",
        }}
      >
        <Text
          style={{
            color: "#fff",
            fontFamily: fonts.sansBold,
            fontSize: 10.5,
          }}
        >
          {initial}
        </Text>
      </View>
      <Text
        style={{
          color: palette.text,
          fontFamily: fonts.sansMedium,
          fontSize: 12,
        }}
        numberOfLines={1}
      >
        {name}
      </Text>
    </Pressable>
  );
}

export default OiTenantPill;
