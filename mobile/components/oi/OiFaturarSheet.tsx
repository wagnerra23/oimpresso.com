/**
 * OiFaturarSheet — folha de faturamento (Fase 2).
 *
 * Espelha a UX do `OIFlow.faturar`: escolhe nº de parcelas + meio, mostra a
 * prévia das parcelas (sem perder centavo) e confirma. Idempotente (o store
 * bloqueia faturar duas vezes a mesma origem). Reusado em Pedido e OS.
 */
import { useState } from "react";
import { Pressable, Text, View } from "react-native";

import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { formatBRL, gerarParcelasCents, type Cents } from "@/lib/money";
import { useFinanceiro } from "@/lib/use-financeiro";

import { OiSheet } from "./OiSheet";
import { OiIcon } from "./OiIcon";
import { useToast } from "./OiToastHost";

const MEIOS = ["Boleto", "PIX", "Crédito", "Dinheiro"] as const;
const PARCELAS = [1, 2, 3, 6, 12];

export type OiFaturarSheetProps = {
  visible: boolean;
  onClose: () => void;
  origemId: string;
  parte: string;
  parteId?: string | null;
  desc: string;
  valorCents: Cents;
  categoria?: string;
  onDone?: () => void;
};

export function OiFaturarSheet({
  visible,
  onClose,
  origemId,
  parte,
  parteId,
  desc,
  valorCents,
  categoria,
  onDone,
}: OiFaturarSheetProps) {
  const { palette } = useOiTheme();
  const { faturar } = useFinanceiro();
  const toast = useToast();
  const [parcelas, setParcelas] = useState(1);
  const [meio, setMeio] = useState<(typeof MEIOS)[number]>("Boleto");

  const preview = gerarParcelasCents(valorCents, parcelas);

  const confirmar = () => {
    const r = faturar({ origemId, parte, parteId, desc, valorCents, parcelas, meio, categoria });
    if (r.ok) {
      toast.show(`Faturado · ${parcelas}x · ${formatBRL(valorCents)}`, "ok");
      onClose();
      onDone?.();
    } else {
      toast.show(r.motivo, "warn");
      onClose();
    }
  };

  return (
    <OiSheet visible={visible} onClose={onClose} title="Faturar">
      <View style={{ gap: 14 }}>
        <View style={{ alignItems: "center", paddingVertical: 4 }}>
          <Text style={{ fontFamily: fonts.sans, fontSize: 12, color: palette.textMute }}>
            {desc}
          </Text>
          <Text style={{ fontFamily: fonts.monoSemibold, fontSize: 26, color: palette.text, marginTop: 2 }}>
            {formatBRL(valorCents)}
          </Text>
          <Text style={{ fontFamily: fonts.sans, fontSize: 11.5, color: palette.textMute }}>
            {parte}
          </Text>
        </View>

        <View>
          <Label>Parcelas</Label>
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
            {PARCELAS.map((n) => (
              <Chip key={n} label={`${n}x`} on={parcelas === n} onPress={() => setParcelas(n)} />
            ))}
          </View>
        </View>

        <View>
          <Label>Meio</Label>
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
            {MEIOS.map((m) => (
              <Chip key={m} label={m} on={meio === m} onPress={() => setMeio(m)} />
            ))}
          </View>
        </View>

        {parcelas > 1 ? (
          <View style={{ gap: 4 }}>
            <Label>Prévia das parcelas</Label>
            {preview.map((c, i) => (
              <View
                key={i}
                style={{
                  flexDirection: "row",
                  justifyContent: "space-between",
                  paddingVertical: 4,
                }}
              >
                <Text style={{ fontFamily: fonts.sans, fontSize: 12.5, color: palette.textDim }}>
                  {i + 1}/{parcelas} · vence em {7 + i * 30} dias
                </Text>
                <Text style={{ fontFamily: fonts.monoMedium, fontSize: 12.5, color: palette.text }}>
                  {formatBRL(c)}
                </Text>
              </View>
            ))}
          </View>
        ) : null}

        <Pressable
          onPress={confirmar}
          style={({ pressed }) => ({
            height: 48,
            borderRadius: radius.md,
            alignItems: "center",
            justifyContent: "center",
            flexDirection: "row",
            gap: 8,
            backgroundColor: palette.action,
            opacity: pressed ? 0.85 : 1,
          })}
        >
          <OiIcon name="check" size={18} color={palette.actionFg} />
          <Text style={{ color: palette.actionFg, fontFamily: fonts.sansSemibold, fontSize: 15 }}>
            Confirmar faturamento
          </Text>
        </Pressable>
      </View>
    </OiSheet>
  );
}

function Label({ children }: { children: string }) {
  const { palette } = useOiTheme();
  return (
    <Text
      style={{
        fontFamily: fonts.sansBold,
        fontSize: 10.5,
        letterSpacing: 0.8,
        textTransform: "uppercase",
        color: palette.textMute,
        marginBottom: 6,
      }}
    >
      {children}
    </Text>
  );
}

function Chip({ label, on, onPress }: { label: string; on: boolean; onPress: () => void }) {
  const { palette } = useOiTheme();
  return (
    <Pressable
      onPress={onPress}
      style={{
        paddingHorizontal: 14,
        paddingVertical: 8,
        borderRadius: radius.pill,
        borderWidth: 1,
        borderColor: on ? palette.accent : palette.border,
        backgroundColor: on ? palette.accentSoft : palette.surface,
      }}
    >
      <Text
        style={{
          fontFamily: fonts.sansSemibold,
          fontSize: 12.5,
          color: on ? palette.accentText : palette.text,
        }}
      >
        {label}
      </Text>
    </Pressable>
  );
}

export default OiFaturarSheet;
