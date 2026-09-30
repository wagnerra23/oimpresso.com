/**
 * OiPlaca — placa veicular Mercosul (default) ou padrão antigo (3+4).
 *
 * Bloco 9.1. Pequeno, alta densidade de informação, fonte mono.
 * Origem visual: `Placa` em `ref/design/screens-oficina.jsx`.
 */
import { Text, View, type ViewStyle } from "react-native";

import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";

export type OiPlacaProps = {
  text: string;
  /** "mercosul" (faixa azul superior) ou "antiga" (preto sobre cinza). */
  variant?: "mercosul" | "antiga";
  size?: "sm" | "md";
  style?: ViewStyle;
};

export function OiPlaca({
  text,
  variant = "mercosul",
  size = "md",
  style,
}: OiPlacaProps) {
  const { palette } = useOiTheme();
  const isSm = size === "sm";
  const isAntiga = variant === "antiga";
  const padH = isSm ? 6 : 8;
  const padV = isSm ? 2 : 3;
  const fontSize = isSm ? 11 : 13;
  const bandH = isSm ? 4 : 5;

  const normalized = (text ?? "").toUpperCase().replace(/\s+/g, " ").trim();

  return (
    <View
      style={[
        {
          alignSelf: "flex-start",
          borderRadius: radius.sm,
          overflow: "hidden",
          borderWidth: 1,
          borderColor: isAntiga ? "#444" : "#1f3aa3",
          backgroundColor: isAntiga ? "#cecece" : "#f4f6fb",
        },
        style,
      ]}
    >
      {!isAntiga ? (
        <View
          style={{
            height: bandH,
            backgroundColor: "#1f3aa3",
          }}
        />
      ) : null}
      <Text
        style={{
          fontFamily: fonts.monoSemibold,
          fontSize,
          letterSpacing: 1.2,
          color: isAntiga ? "#111" : "#111",
          paddingHorizontal: padH,
          paddingVertical: padV,
          textAlign: "center",
        }}
        numberOfLines={1}
      >
        {normalized}
      </Text>
    </View>
  );
}

export default OiPlaca;
