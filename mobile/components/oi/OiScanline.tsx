import { useEffect } from "react";
import { View } from "react-native";
import Animated, {
  Easing,
  useAnimatedStyle,
  useSharedValue,
  withRepeat,
  withTiming,
} from "react-native-reanimated";

import { useOiTheme } from "@/lib/oi-theme-context";

export type OiScanlineProps = {
  /** Height of the viewfinder window the line travels inside. */
  height: number;
  /** Animation duration in ms. Default 2400 (matches CSS keyframes). */
  duration?: number;
};

/**
 * Vertical scanline animation for the "Venda rápida" viewfinder.
 *
 * Reproduces `@keyframes oi-scan` — the line glides from top to bottom and
 * back, with opacity easing 0.4 → 1 → 0.4. RN can't replicate the CSS
 * `color-mix` glow exactly, so we use a tall accent line with native shadows.
 */
export function OiScanline({
  height,
  duration = 2400,
}: OiScanlineProps) {
  const { palette } = useOiTheme();
  const progress = useSharedValue(0);

  useEffect(() => {
    progress.value = 0;
    progress.value = withRepeat(
      withTiming(1, { duration, easing: Easing.inOut(Easing.ease) }),
      -1,
      true,
    );
  }, [duration, progress]);

  const animatedStyle = useAnimatedStyle(() => ({
    transform: [{ translateY: progress.value * (height - 2) }],
    opacity: 0.4 + progress.value * 0.6,
  }));

  return (
    <View
      pointerEvents="none"
      style={{
        position: "absolute",
        left: 0,
        right: 0,
        top: 0,
        height,
        overflow: "hidden",
      }}
    >
      <Animated.View
        style={[
          {
            position: "absolute",
            left: 0,
            right: 0,
            height: 2,
            borderRadius: 2,
            backgroundColor: palette.accent,
            shadowColor: palette.accent,
            shadowOpacity: 0.8,
            shadowRadius: 16,
            shadowOffset: { width: 0, height: 0 },
            elevation: 4,
          },
          animatedStyle,
        ]}
      />
    </View>
  );
}

export default OiScanline;
