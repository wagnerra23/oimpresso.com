import { Image, Text, View, type ViewStyle } from "react-native";

import { fonts } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";

const SIZES = {
  sm: 24,
  md: 40,
  lg: 72,
} as const;

const WORDMARK_SIZE = {
  sm: 14,
  md: 20,
  lg: 30,
} as const;

export type OiBrandLogoSize = keyof typeof SIZES;

export type OiBrandLogoProps = {
  /** Mark size — `sm | md | lg`. */
  size?: OiBrandLogoSize;
  /** When true, renders the "Oimpresso" wordmark next to the mark. */
  wordmark?: boolean;
  /** Force wordmark colour (defaults to current foreground / accent). */
  wordmarkColor?: string;
  /** Stack mark on top of wordmark (default: row). */
  stacked?: boolean;
  style?: ViewStyle;
};

/**
 * Brand mark for Oimpresso. Wraps the CMYK PNG logo (assets/images/oimpresso-logo.png)
 * with size variants and an optional "Oimpresso" wordmark rendered in IBM Plex Mono.
 */
export function OiBrandLogo({
  size = "md",
  wordmark = false,
  wordmarkColor,
  stacked = false,
  style,
}: OiBrandLogoProps) {
  const { palette } = useOiTheme();
  const px = SIZES[size];
  const tsize = WORDMARK_SIZE[size];

  const mark = (
    <Image
      // require resolves to a bundled asset at build time.
      // eslint-disable-next-line @typescript-eslint/no-require-imports
      source={require("@/assets/images/oimpresso-logo.png")}
      style={{ width: px, height: px, resizeMode: "contain" }}
      accessibilityLabel="Oimpresso"
    />
  );

  if (!wordmark) {
    return <View style={style}>{mark}</View>;
  }

  return (
    <View
      style={[
        {
          flexDirection: stacked ? "column" : "row",
          alignItems: "center",
          gap: stacked ? 10 : 12,
        },
        style,
      ]}
    >
      {mark}
      <Text
        style={{
          fontFamily: fonts.monoSemibold,
          fontSize: tsize,
          letterSpacing: -0.6,
          color: wordmarkColor ?? palette.text,
        }}
      >
        Oimpresso
      </Text>
    </View>
  );
}

export default OiBrandLogo;
