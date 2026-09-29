import { Pressable } from "react-native";

import { radius, shadows } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { OiIcon, type OiIconName } from "./OiIcon";

export type OiFabProps = {
  icon?: OiIconName;
  onPress?: () => void;
  /** Override the right offset (in px). Default 16. */
  right?: number;
  /** Override the bottom offset. Default 16. Pass a larger value when sitting above a tabbar. */
  bottom?: number;
};

export function OiFab({
  icon = "plus",
  onPress,
  right = 16,
  bottom = 16,
}: OiFabProps) {
  const { palette } = useOiTheme();
  return (
    <Pressable
      onPress={onPress}
      style={{
        position: "absolute",
        right,
        bottom,
        width: 56,
        height: 56,
        backgroundColor: palette.accent,
        borderRadius: radius.lg,
        alignItems: "center",
        justifyContent: "center",
        ...shadows.pop,
      }}
    >
      <OiIcon name={icon} size={24} color="#fff" />
    </Pressable>
  );
}

export default OiFab;
