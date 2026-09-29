import type { ReactNode } from "react";
import { Text, View } from "react-native";

import { fonts } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";

export type OiDlProps = { children: ReactNode };

/** Definition list (label + value). Maps to `.oi-dl`. */
export function OiDl({ children }: OiDlProps) {
  return <View style={{ gap: 8 }}>{children}</View>;
}

export type OiDtDdProps = {
  /** Label rendered to the left, dim. */
  label: string;
  /** Value rendered to the right. String → styled; node → rendered as-is. */
  children: ReactNode;
};

/**
 * Pair row: dt label + dd value. Use as the only child type of OiDl.
 */
export function OiDlRow({ label, children }: OiDtDdProps) {
  const { palette } = useOiTheme();
  return (
    <View
      style={{ flexDirection: "row", alignItems: "flex-start", gap: 14 }}
    >
      <Text
        style={{
          minWidth: 96,
          color: palette.textMute,
          fontFamily: fonts.sansMedium,
          fontSize: 13,
        }}
      >
        {label}
      </Text>
      <View style={{ flex: 1, minWidth: 0 }}>
        {typeof children === "string" || typeof children === "number" ? (
          <Text
            style={{
              color: palette.text,
              fontFamily: fonts.sans,
              fontSize: 13,
            }}
          >
            {children}
          </Text>
        ) : (
          children
        )}
      </View>
    </View>
  );
}

// Named aliases for legibility — `<OiDt>` / `<OiDd>` aren't really needed since
// the row owns both, but we export friendly names so consumers can mirror the
// CSS structure when porting screens 1:1.
export const OiDt = ({ children }: { children: ReactNode }) => {
  const { palette } = useOiTheme();
  return (
    <Text
      style={{
        color: palette.textMute,
        fontFamily: fonts.sansMedium,
        fontSize: 13,
      }}
    >
      {children}
    </Text>
  );
};

export const OiDd = ({ children }: { children: ReactNode }) => {
  const { palette } = useOiTheme();
  return (
    <Text
      style={{ color: palette.text, fontFamily: fonts.sans, fontSize: 13 }}
    >
      {children}
    </Text>
  );
};

export default OiDl;
