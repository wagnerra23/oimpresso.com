import { Text, View } from "react-native";

import { fonts } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import type { OiOriginKey } from "@/lib/oi-theme";

export type OiOriginProps = {
  kind: OiOriginKey;
  size?: "default" | "lg";
};

/**
 * Origin code badge (OS / CRM / FIN / PNT / MFG) — mono, tinted background.
 */
export function OiOrigin({
  kind,
  size = "default",
}: OiOriginProps) {
  const { palette } = useOiTheme();
  const tones = palette.origin[kind];
  const lg = size === "lg";
  return (
    <View
      style={{
        backgroundColor: tones.bg,
        paddingHorizontal: lg ? 8 : 6,
        paddingVertical: lg ? 3 : 2,
        borderRadius: lg ? 5 : 4,
        alignSelf: "flex-start",
      }}
    >
      <Text
        style={{
          color: tones.fg,
          fontFamily: fonts.monoSemibold,
          fontSize: lg ? 11 : 9.5,
          letterSpacing: 0.4,
        }}
      >
        {kind}
      </Text>
    </View>
  );
}

export default OiOrigin;
