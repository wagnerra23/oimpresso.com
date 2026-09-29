/**
 * OiBarPreview — mini-prévia da barra inferior gerada por um perfil de menu.
 *
 * Mostra Início (fixo) + módulos do perfil + Mais (fixo). Usado na lista de
 * perfis (`PerfisScreen`) e no editor (`PerfilEditScreen`).
 *
 * Origem: `BarPreview` em `ref/design/screens-perfis.jsx`.
 */
import { Text, View } from "react-native";

import { fonts, hexAlpha, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import {
  getMenuModule,
  type MenuModuleId,
} from "@/lib/menu-modules";

import { OiIcon, type OiIconName } from "./OiIcon";

type Slot = {
  key: string;
  label: string;
  icon: OiIconName;
  fixed: boolean;
};

export type OiBarPreviewProps = {
  mods: readonly MenuModuleId[];
};

export function OiBarPreview({ mods }: OiBarPreviewProps) {
  const { palette } = useOiTheme();

  const slots: Slot[] = [
    { key: "__inicio", label: "Início", icon: "home", fixed: true },
    ...mods
      .map((id) => getMenuModule(id))
      .filter((m): m is NonNullable<typeof m> => Boolean(m))
      .map<Slot>((m) => ({
        key: m.id,
        label: m.label,
        icon: m.icon,
        fixed: false,
      })),
    { key: "__mais", label: "Mais", icon: "dots", fixed: true },
  ];

  return (
    <View
      style={{
        flexDirection: "row",
        backgroundColor: palette.surface,
        borderColor: palette.border,
        borderWidth: 1,
        borderRadius: radius.md,
        paddingVertical: 8,
        paddingHorizontal: 4,
      }}
    >
      {slots.map((s) => {
        const tint = s.fixed ? palette.textMute : palette.accent;
        return (
          <View
            key={s.key}
            style={{
              flex: 1,
              alignItems: "center",
              gap: 3,
              paddingVertical: 2,
            }}
          >
            <View
              style={{
                width: 36,
                height: 22,
                borderRadius: 999,
                alignItems: "center",
                justifyContent: "center",
                backgroundColor: s.fixed
                  ? "transparent"
                  : hexAlpha(palette.accent, 0.18),
              }}
            >
              <OiIcon name={s.icon} size={16} color={tint} />
            </View>
            <Text
              style={{
                fontFamily: fonts.sansSemibold,
                fontSize: 9,
                color: tint,
                maxWidth: "100%",
              }}
              numberOfLines={1}
            >
              {s.label}
            </Text>
            {s.fixed ? (
              <Text
                style={{
                  fontFamily: fonts.sansMedium,
                  fontSize: 7.5,
                  color: palette.textMute,
                  letterSpacing: 0.4,
                }}
              >
                FIXO
              </Text>
            ) : null}
          </View>
        );
      })}
    </View>
  );
}

export default OiBarPreview;
