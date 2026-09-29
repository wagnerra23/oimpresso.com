/**
 * EquipeScreen — visão da equipe (Bloco 6).
 *
 * Card "Ponto de hoje" (presentes/total + Ver escala). Lista de mecânicos
 * (avatar, especialidade, status Disponível/N OS ativas). Lista admin/vendas
 * (do mock de Pessoas com papel=funcionario).
 */
import { useRouter } from "expo-router";
import { Pressable, ScrollView, Text, View } from "react-native";

import {
  OiCard,
  OiDetailHeader,
  OiIcon,
  OiScreen,
  OiSection,
  OiStageStatus,
  useToast,
} from "@/components/oi";
import { fonts, hexAlpha, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useManutencao } from "@/lib/use-manutencao";
import { useEquipamentos } from "@/lib/use-equipamentos";

export default function EquipeScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const toast = useToast();
  const { mecanicos } = useManutencao();
  const { pessoas } = useEquipamentos();
  const funcionarios = pessoas.filter((p) => p.papeis.includes("funcionario"));
  const presentes = Math.floor((mecanicos.length + funcionarios.length) * 0.75);
  const total = mecanicos.length + funcionarios.length;

  return (
    <OiScreen edges={["top"]}>
      <OiDetailHeader title="Equipe" eyebrow="Mecânicos · administrativo" onBack={() => router.back()} />
      <ScrollView contentContainerStyle={{ paddingBottom: 32 }}>
        {/* Ponto de hoje */}
        <OiSection>
          <OiCard variant="pad" style={{ gap: 12 }}>
            <View style={{ flexDirection: "row", alignItems: "center" }}>
              <View style={{ flex: 1 }}>
                <Text style={{ fontSize: 10.5, color: palette.textMute, fontFamily: fonts.sansBold, letterSpacing: 0.8, textTransform: "uppercase" }}>
                  Ponto de hoje
                </Text>
                <Text style={{ fontFamily: fonts.sansBold, fontSize: 26, color: palette.text, marginTop: 2 }}>
                  {presentes}
                  <Text style={{ fontSize: 16, color: palette.textMute, fontFamily: fonts.sans }}>
                    {" / "}{total}
                  </Text>
                </Text>
                <Text style={{ fontSize: 11.5, color: palette.ok, fontFamily: fonts.sansSemibold }}>
                  presentes agora
                </Text>
              </View>
              <Pressable
                onPress={() => toast.show("Abrindo escala…", "default")}
                style={({ pressed }) => ({
                  paddingHorizontal: 14,
                  paddingVertical: 8,
                  borderRadius: radius.md,
                  borderWidth: 1,
                  borderColor: palette.border,
                  flexDirection: "row",
                  alignItems: "center",
                  gap: 6,
                  opacity: pressed ? 0.85 : 1,
                })}
              >
                <OiIcon name="calendar" size={14} color={palette.text} />
                <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 12.5, color: palette.text }}>
                  Ver escala
                </Text>
              </Pressable>
            </View>
          </OiCard>
        </OiSection>

        {/* Mecânicos */}
        <OiSection title="Mecânicos">
          <View style={{ gap: 8 }}>
            {mecanicos.map((m) => (
              <Pressable
                key={m.id}
                onPress={() => toast.show(`${m.nome} · ${m.ativasIds.length} OS`, "default")}
                style={({ pressed }) => ({
                  flexDirection: "row",
                  alignItems: "center",
                  gap: 10,
                  padding: 12,
                  borderRadius: radius.md,
                  borderWidth: 1,
                  borderColor: palette.border,
                  backgroundColor: pressed ? palette.bg2 : palette.surface,
                })}
              >
                <View
                  style={{
                    width: 40,
                    height: 40,
                    borderRadius: 999,
                    backgroundColor: hexAlpha(palette.accent, 0.2),
                    alignItems: "center",
                    justifyContent: "center",
                  }}
                >
                  <Text style={{ color: palette.accent, fontFamily: fonts.sansBold, fontSize: 13 }}>
                    {m.nome
                      .split(/\s+/)
                      .slice(0, 2)
                      .map((w) => w[0]?.toUpperCase() ?? "")
                      .join("")}
                  </Text>
                </View>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 13.5, color: palette.text }}>
                    {m.nome}
                  </Text>
                  <Text style={{ fontSize: 11.5, color: palette.textMute }}>{m.especialidade}</Text>
                </View>
                <OiStageStatus
                  label={m.disponivel ? "Disponível" : `${m.ativasIds.length} OS`}
                  tone={m.disponivel ? "ok" : "warn"}
                />
              </Pressable>
            ))}
          </View>
        </OiSection>

        {/* Administrativo / vendas */}
        {funcionarios.length > 0 ? (
          <OiSection title="Administrativo / Vendas">
            <View style={{ gap: 8 }}>
              {funcionarios.map((p) => (
                <Pressable
                  key={p.id}
                  onPress={() => router.push(`/clientes/${p.id}` as never)}
                  style={({ pressed }) => ({
                    flexDirection: "row",
                    alignItems: "center",
                    gap: 10,
                    padding: 12,
                    borderRadius: radius.md,
                    borderWidth: 1,
                    borderColor: palette.border,
                    backgroundColor: pressed ? palette.bg2 : palette.surface,
                  })}
                >
                  <View
                    style={{
                      width: 40,
                      height: 40,
                      borderRadius: 999,
                      backgroundColor: hexAlpha(palette.warn, 0.18),
                      alignItems: "center",
                      justifyContent: "center",
                    }}
                  >
                    <OiIcon name="user" size={20} color={palette.warn} />
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 13.5, color: palette.text }}>
                      {p.nome}
                    </Text>
                    <Text style={{ fontFamily: fonts.mono, fontSize: 11, color: palette.textMute }}>
                      {p.doc}
                    </Text>
                  </View>
                  <OiIcon name="chev-r" size={16} color={palette.textMute} />
                </Pressable>
              ))}
            </View>
          </OiSection>
        ) : null}
      </ScrollView>
    </OiScreen>
  );
}
