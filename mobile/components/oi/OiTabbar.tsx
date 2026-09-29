import { Platform, Pressable, Text, View } from "react-native";

import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { OiIcon, type OiIconName } from "./OiIcon";

export type OiTabItem = {
  key: string;
  label: string;
  icon: OiIconName;
  badge?: number | string;
};

export type OiTabbarProps = {
  items: OiTabItem[];
  activeKey: string;
  onChange: (key: string) => void;
};

/**
 * 5-tab bottom navigation. On Android, the active tab gets a pill-shaped
 * Material indicator behind its icon (per the design CSS).
 */
export function OiTabbar({
  items,
  activeKey,
  onChange,
}: OiTabbarProps) {
  const { palette } = useOiTheme();
  const isAndroid = Platform.OS === "android";
  return (
    <View
      style={{
        flexDirection: "row",
        backgroundColor: palette.surface,
        borderTopColor: palette.border,
        borderTopWidth: 1,
        paddingHorizontal: 4,
        paddingTop: isAndroid ? 8 : 6,
        paddingBottom: isAndroid ? 12 : 8,
      }}
    >
      {items.map((item) => (
        <OiTab
          key={item.key}
          item={item}
          active={item.key === activeKey}
          onPress={() => onChange(item.key)}
          color={palette.textMute}
          activeColor={palette.accent}
          accentSoft={palette.accentSoft}
          surface={palette.surface}
          danger={palette.danger}
          isAndroid={isAndroid}
        />
      ))}
    </View>
  );
}

type OiTabProps = {
  item: OiTabItem;
  active: boolean;
  onPress: () => void;
  color: string;
  activeColor: string;
  accentSoft: string;
  surface: string;
  danger: string;
  isAndroid: boolean;
};

export function OiTab({
  item,
  active,
  onPress,
  color,
  activeColor,
  accentSoft,
  surface,
  danger,
  isAndroid,
}: OiTabProps) {
  const fg = active ? activeColor : color;
  return (
    <Pressable
      onPress={onPress}
      style={{
        flex: 1,
        alignItems: "center",
        justifyContent: "center",
        paddingTop: 6,
        paddingBottom: 2,
        gap: 2,
        position: "relative",
      }}
    >
      <View
        style={
          isAndroid
            ? {
                width: 56,
                height: 28,
                borderRadius: radius.pill,
                alignItems: "center",
                justifyContent: "center",
                backgroundColor: active ? accentSoft : "transparent",
              }
            : null
        }
      >
        <OiIcon name={item.icon} size={22} color={fg} />
      </View>
      <Text
        style={{
          color: fg,
          fontFamily: fonts.sansMedium,
          fontSize: 10.5,
        }}
        numberOfLines={1}
      >
        {item.label}
      </Text>
      {item.badge != null ? (
        <View
          style={{
            position: "absolute",
            top: 4,
            left: "50%",
            marginLeft: 4,
            minWidth: 16,
            height: 16,
            paddingHorizontal: 4,
            backgroundColor: danger,
            borderRadius: 999,
            borderWidth: 2,
            borderColor: surface,
            alignItems: "center",
            justifyContent: "center",
          }}
        >
          <Text
            style={{
              color: "#fff",
              fontFamily: fonts.sansBold,
              fontSize: 9.5,
            }}
          >
            {item.badge}
          </Text>
        </View>
      ) : null}
    </Pressable>
  );
}

export default OiTabbar;
