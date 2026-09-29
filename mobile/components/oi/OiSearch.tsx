import { TextInput, View, type ViewStyle } from "react-native";

import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { OiIcon } from "./OiIcon";

export type OiSearchProps = {
  value: string;
  onChangeText: (v: string) => void;
  placeholder?: string;
  onSubmitEditing?: () => void;
  autoFocus?: boolean;
  style?: ViewStyle;
};

export function OiSearch({
  value,
  onChangeText,
  placeholder = "Buscar…",
  onSubmitEditing,
  autoFocus,
  style,
}: OiSearchProps) {
  const { palette } = useOiTheme();
  return (
    <View
      style={[
        {
          flexDirection: "row",
          alignItems: "center",
          gap: 8,
          backgroundColor: palette.bg2,
          borderColor: palette.border,
          borderWidth: 1,
          borderRadius: radius.md,
          paddingHorizontal: 12,
          paddingVertical: 8,
        },
        style,
      ]}
    >
      <OiIcon name="search" size={18} color={palette.textMute} />
      <TextInput
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor={palette.textMute}
        onSubmitEditing={onSubmitEditing}
        autoFocus={autoFocus}
        returnKeyType="search"
        style={{
          flex: 1,
          minWidth: 0,
          color: palette.text,
          fontSize: 14,
          fontFamily: fonts.sans,
          padding: 0,
        }}
      />
    </View>
  );
}

export default OiSearch;
