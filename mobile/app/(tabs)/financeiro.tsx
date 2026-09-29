/**
 * Financeiro (Fase 3) — caixa · a receber · a pagar · extrato.
 *
 * Consome `useFinanceiro` (modelo título/parcela em centavos). Reflete o que
 * foi faturado (OS/Pedido → Faturar). Layout espelha `screens-financeiro.jsx`:
 * hero de saldo + cartões de conta + segmented (Receber/Pagar/Extrato) + lista.
 */
import { useMemo, useState } from "react";
import { Pressable, ScrollView, Text, View } from "react-native";

import {
  OiCard,
  OiEmpty,
  OiHeader,
  OiIcon,
  OiScreen,
  OiSection,
  type OiIconName,
} from "@/components/oi";
import { fonts, hexAlpha, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { formatBRL } from "@/lib/money";
import { useFinanceiro, type Lancamento } from "@/lib/use-financeiro";

type Aba = "receber" | "pagar" | "extrato";

export default function FinanceiroScreen() {
  const { palette } = useOiTheme();
  const { lancamentos, contas, resumo } = useFinanceiro();
  const [aba, setAba] = useState<Aba>("receber");

  const saldoMesCents = resumo.recebidoMesCents - resumo.pagoMesCents;

  const lista = useMemo(() => {
    if (aba === "receber") return lancamentos.filter((l) => l.tipo === "receber" && l.status !== "liquidado");
    if (aba === "pagar") return lancamentos.filter((l) => l.tipo === "pagar" && l.status !== "liquidado");
    return lancamentos.filter((l) => l.status === "liquidado");
  }, [lancamentos, aba]);

  return (
    <OiScreen edges={["top"]}>
      <OiHeader title="Financeiro" eyebrow="Caixa · a receber · a pagar" />
      <ScrollView contentContainerStyle={{ paddingBottom: 32 }} showsVerticalScrollIndicator={false}>
        {/* Hero saldo do mês */}
        <OiSection>
          <OiCard variant="pad" style={{ gap: 10 }}>
            <Text style={{ fontFamily: fonts.sansBold, fontSize: 10.5, letterSpacing: 0.8, textTransform: "uppercase", color: palette.textMute }}>
              Saldo do mês
            </Text>
            <Text
              style={{
                fontFamily: fonts.monoSemibold,
                fontSize: 30,
                color: saldoMesCents >= 0 ? palette.ok : palette.danger,
              }}
            >
              {formatBRL(saldoMesCents)}
            </Text>
            <View style={{ flexDirection: "row", gap: 16 }}>
              <Mini label="Recebido" cents={resumo.recebidoMesCents} tone={palette.ok} />
              <Mini label="Pago" cents={resumo.pagoMesCents} tone={palette.danger} />
            </View>
            <View style={{ height: 1, backgroundColor: palette.border, marginVertical: 2 }} />
            <View style={{ flexDirection: "row", gap: 16 }}>
              <Mini label="A receber" cents={resumo.aReceberCents} tone={palette.accent} />
              <Mini label="Vencido" cents={resumo.vencidoReceberCents} tone={palette.warn} />
              <Mini label="A pagar" cents={resumo.aPagarCents} tone={palette.textDim} />
            </View>
          </OiCard>
        </OiSection>

        {/* Contas */}
        <OiSection title="Contas">
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
            {contas.map((c) => (
              <View
                key={c.id}
                style={{
                  width: 150,
                  padding: 12,
                  borderRadius: radius.md,
                  borderWidth: 1,
                  borderColor: palette.border,
                  backgroundColor: palette.surface,
                  gap: 6,
                }}
              >
                <View
                  style={{
                    width: 30,
                    height: 30,
                    borderRadius: 8,
                    backgroundColor: hexAlpha(palette.accent, 0.14),
                    alignItems: "center",
                    justifyContent: "center",
                  }}
                >
                  <OiIcon name={(c.ic as OiIconName) ?? "dollar"} size={16} color={palette.accent} />
                </View>
                <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 13, color: palette.text }} numberOfLines={1}>
                  {c.nome}
                </Text>
                <Text style={{ fontFamily: fonts.mono, fontSize: 10.5, color: palette.textMute }} numberOfLines={1}>
                  {c.banco}
                </Text>
                <Text style={{ fontFamily: fonts.monoSemibold, fontSize: 14, color: palette.text }}>
                  {formatBRL(c.saldoCents)}
                </Text>
              </View>
            ))}
          </ScrollView>
        </OiSection>

        {/* Segmented */}
        <View style={{ paddingHorizontal: 16, paddingTop: 6 }}>
          <View style={{ flexDirection: "row", backgroundColor: palette.bg2, borderRadius: radius.md, padding: 3 }}>
            {([
              { id: "receber", label: "A receber" },
              { id: "pagar", label: "A pagar" },
              { id: "extrato", label: "Extrato" },
            ] as const).map((o) => {
              const on = aba === o.id;
              return (
                <Pressable
                  key={o.id}
                  onPress={() => setAba(o.id)}
                  style={{
                    flex: 1,
                    paddingVertical: 8,
                    alignItems: "center",
                    borderRadius: radius.sm,
                    backgroundColor: on ? palette.surface : "transparent",
                  }}
                >
                  <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 12, color: on ? palette.text : palette.textMute }}>
                    {o.label}
                  </Text>
                </Pressable>
              );
            })}
          </View>
        </View>

        {/* Lista */}
        <OiSection>
          {lista.length === 0 ? (
            <OiEmpty icon="dollar" title="Nada aqui" subtitle="Sem lançamentos nesta aba." />
          ) : (
            <View style={{ gap: 8 }}>
              {lista.map((l) => (
                <LancRow key={l.id} l={l} />
              ))}
            </View>
          )}
        </OiSection>
      </ScrollView>
    </OiScreen>
  );
}

function Mini({ label, cents, tone }: { label: string; cents: number; tone: string }) {
  const { palette } = useOiTheme();
  return (
    <View>
      <Text style={{ fontFamily: fonts.sans, fontSize: 10.5, color: palette.textMute }}>{label}</Text>
      <Text style={{ fontFamily: fonts.monoMedium, fontSize: 13.5, color: tone }}>{formatBRL(cents)}</Text>
    </View>
  );
}

function LancRow({ l }: { l: Lancamento }) {
  const { palette } = useOiTheme();
  const tone =
    l.status === "vencido" ? palette.danger : l.status === "liquidado" ? palette.ok : palette.textDim;
  const sign = l.tipo === "receber" ? "+" : "−";
  const valColor = l.tipo === "receber" ? palette.ok : palette.danger;
  return (
    <View
      style={{
        flexDirection: "row",
        alignItems: "center",
        gap: 10,
        padding: 12,
        borderRadius: radius.md,
        borderWidth: 1,
        borderColor: palette.border,
        backgroundColor: palette.surface,
      }}
    >
      <View style={{ flex: 1, minWidth: 0 }}>
        <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 13.5, color: palette.text }} numberOfLines={1}>
          {l.desc}
        </Text>
        <Text style={{ fontFamily: fonts.sans, fontSize: 11.5, color: palette.textMute }} numberOfLines={1}>
          {l.parte} · {l.liqLabel ?? l.vencLabel}
          {l.emitidoPor === "faturamento" ? " · faturado" : ""}
        </Text>
      </View>
      <View style={{ alignItems: "flex-end", gap: 2 }}>
        <Text style={{ fontFamily: fonts.monoSemibold, fontSize: 14, color: valColor }}>
          {sign} {formatBRL(l.valorCents)}
        </Text>
        <View style={{ paddingHorizontal: 7, paddingVertical: 2, borderRadius: radius.pill, backgroundColor: hexAlpha(tone, 0.16) }}>
          <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 9.5, color: tone, textTransform: "uppercase", letterSpacing: 0.4 }}>
            {l.status}
          </Text>
        </View>
      </View>
    </View>
  );
}
