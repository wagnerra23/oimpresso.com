import { Text, View } from "react-native";

import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { OiBtn } from "./OiBtn";
import { OiIcon, type OiIconName } from "./OiIcon";

export type OiEmptyProps = {
  icon?: OiIconName;
  title: string;
  subtitle?: string;
  /** Picks the OK tint background for the icon plate (used when state is good — "tudo em dia"). */
  ok?: boolean;
  action?: { label: string; onPress: () => void };
};

export function OiEmpty({
  icon = "inbox",
  title,
  subtitle,
  ok,
  action,
}: OiEmptyProps) {
  const { palette } = useOiTheme();
  return (
    <View
      style={{
        alignItems: "center",
        gap: 8,
        paddingHorizontal: 24,
        paddingVertical: 48,
      }}
    >
      <View
        style={{
          width: 56,
          height: 56,
          borderRadius: radius.lg + 2,
          alignItems: "center",
          justifyContent: "center",
          marginBottom: 4,
          backgroundColor: ok ? palette.accentSoft : palette.bg2,
          borderColor: ok ? "transparent" : palette.border,
          borderWidth: 1,
        }}
      >
        <OiIcon
          name={icon}
          size={28}
          color={ok ? palette.ok : palette.textMute}
        />
      </View>
      <Text
        style={{
          color: palette.text,
          fontFamily: fonts.sansSemibold,
          fontSize: 15,
          textAlign: "center",
        }}
      >
        {title}
      </Text>
      {subtitle ? (
        <Text
          style={{
            color: palette.textDim,
            fontFamily: fonts.sans,
            fontSize: 12.5,
            lineHeight: 18,
            maxWidth: 260,
            textAlign: "center",
          }}
        >
          {subtitle}
        </Text>
      ) : null}
      {action ? (
        <View style={{ marginTop: 8 }}>
          <OiBtn
            label={action.label}
            onPress={action.onPress}
            size="sm"
            variant="primary"
          />
        </View>
      ) : null}
    </View>
  );
}

export default OiEmpty;
