import { Text, View } from "react-native";

import { avatarGradients, fonts } from "@/lib/oi-theme";

export type OiAvatarProps = {
  /** Initial or short label shown inside the avatar. */
  label: string;
  /** Pick a gradient slot 1..6. Falls back to a hash of `label`. */
  slot?: 1 | 2 | 3 | 4 | 5 | 6;
  size?: number;
};

function hashSlot(s: string): number {
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0;
  return (Math.abs(h) % 6) + 1;
}

/**
 * Square-rounded avatar. Phase 1 ships a single solid color from the gradient
 * pair (the second stop); a future pass can layer expo-linear-gradient if the
 * visual diff matters.
 */
export function OiAvatar({
  label,
  slot,
  size = 32,
}: OiAvatarProps) {
  const idx = (slot ?? hashSlot(label)) - 1;
  const [, end] = avatarGradients[idx]!;
  return (
    <View
      style={{
        width: size,
        height: size,
        borderRadius: Math.max(6, size * 0.22),
        backgroundColor: end,
        alignItems: "center",
        justifyContent: "center",
      }}
    >
      <Text
        style={{
          color: "#fff",
          fontFamily: fonts.sansSemibold,
          fontSize: size * 0.42,
        }}
      >
        {label.slice(0, 2).toUpperCase()}
      </Text>
    </View>
  );
}

export default OiAvatar;
