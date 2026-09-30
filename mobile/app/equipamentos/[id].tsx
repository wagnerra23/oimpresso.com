/**
 * EquipamentoDetalheScreen — ficha do equipamento + histórico de OSs.
 *
 * Bloco 4.3-4.5. Hero placa(s), `OiDl` com dados, Proprietário (link → futura
 * ficha da pessoa), Histórico de OSs (tap → detalhe). Action bar:
 * "Abrir manutenção" → /manutencao/new?equip=<id>.
 */
import { useLocalSearchParams, useRouter } from "expo-router";
import { Pressable, ScrollView, Text, View } from "react-native";

import {
  OiCard,
  OiDetailHeader,
  OiDl,
  OiDlRow,
  OiIcon,
  OiPlaca,
  OiScreen,
  OiSection,
  OiStageStatus,
} from "@/components/oi";
import { fonts, hexAlpha, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useEquipamentos } from "@/lib/use-equipamentos";
import { useManutencao } from "@/lib/use-manutencao";
import { statusTone } from "@/lib/manutencao-mock";

export default function EquipamentoDetalheScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { byId, ownerOf } = useEquipamentos();
  const { oss } = useManutencao();
  const e = id ? byId(id) : undefined;

  if (!e) {
    return (
      <OiScreen edges={["top"]}>
        <OiDetailHeader title="Equipamento não encontrado" onBack={() => router.back()} />
      </OiScreen>
    );
  }

  const owner = ownerOf(e.id);
  const historico = oss.filter(
    (o) => e.placa && o.veiculo.placa.toUpperCase() === e.placa.toUpperCase(),
  );

  return (
    <OiScreen edges={["top"]}>
      <OiDetailHeader
        title={e.apelido ?? `${e.marca} ${e.modelo}`}
        eyebrow={e.tipo}
        onBack={() => router.back()}
      />
      <ScrollView contentContainerStyle={{ paddingBottom: 96 }}>
        {/* Hero */}
        <OiSection>
          <OiCard variant="pad" style={{ gap: 12 }}>
            <View style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
              <View
                style={{
                  width: 44,
                  height: 44,
                  borderRadius: 10,
                  backgroundColor: hexAlpha(palette.accent, 0.14),
                  alignItems: "center",
                  justifyContent: "center",
                }}
              >
                <OiIcon
                  name={e.kind === "veiculo" ? "truck" : "settings"}
                  size={26}
                  color={palette.accent}
                />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 16, color: palette.text }}>
                  {e.marca} {e.modelo}
                </Text>
                <Text style={{ fontSize: 12, color: palette.textMute }}>
                  {e.tipo} · {e.ano} {e.cor ? `· ${e.cor}` : ""}
                </Text>
              </View>
            </View>
            {(e.placa || e.placa2) ? (
              <View style={{ flexDirection: "row", gap: 8 }}>
                {e.placa ? <OiPlaca text={e.placa} /> : null}
                {e.placa2 ? <OiPlaca text={e.placa2} /> : null}
              </View>
            ) : null}
            <OiDl>
              {e.placa ? (
                <>
                  <OiDlRow label="Chassi">
                    <Text style={{ fontFamily: fonts.mono, fontSize: 12, color: palette.text }}>
                      {e.chassi ?? "—"}
                    </Text>
                  </OiDlRow>
                  {e.chassi2 ? (
                    <OiDlRow label="Chassi 2 (reboque)">
                      <Text style={{ fontFamily: fonts.mono, fontSize: 12, color: palette.text }}>
                        {e.chassi2}
                      </Text>
                    </OiDlRow>
                  ) : null}
                  <OiDlRow label="Renavam">
                    <Text style={{ fontFamily: fonts.mono, fontSize: 12, color: palette.text }}>
                      {e.renavam ?? "—"}
                    </Text>
                  </OiDlRow>
                  <OiDlRow label="Hodômetro">
                    <Text style={{ fontFamily: fonts.mono, fontSize: 12, color: palette.text }}>
                      {(e.hodometro ?? 0).toLocaleString("pt-BR")} km
                    </Text>
                  </OiDlRow>
                </>
              ) : (
                <>
                  <OiDlRow label="Nº de série">
                    <Text style={{ fontFamily: fonts.mono, fontSize: 12, color: palette.text }}>
                      {e.serie ?? "—"}
                    </Text>
                  </OiDlRow>
                  <OiDlRow label="Horímetro">
                    <Text style={{ fontFamily: fonts.mono, fontSize: 12, color: palette.text }}>
                      {(e.horimetro ?? 0).toLocaleString("pt-BR")} h
                    </Text>
                  </OiDlRow>
                </>
              )}
            </OiDl>
          </OiCard>
        </OiSection>

        {/* Proprietário */}
        <OiSection title="Proprietário">
          <Pressable
            onPress={() => owner && router.push(`/clientes/${owner.id}` as never)}
            style={({ pressed }) => ({
              padding: 12,
              borderRadius: radius.md,
              borderWidth: 1,
              borderColor: palette.border,
              backgroundColor: pressed ? palette.bg2 : palette.surface,
              flexDirection: "row",
              alignItems: "center",
              gap: 10,
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
              <Text style={{ color: palette.accent, fontFamily: fonts.sansBold, fontSize: 14 }}>
                {(owner?.nome ?? "?")
                  .split(/\s+/)
                  .slice(0, 2)
                  .map((w) => w[0]?.toUpperCase() ?? "")
                  .join("")}
              </Text>
            </View>
            <View style={{ flex: 1, minWidth: 0 }}>
              <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 14, color: palette.text }}>
                {owner?.nome ?? "—"}
              </Text>
              <Text style={{ fontFamily: fonts.mono, fontSize: 11.5, color: palette.textMute }}>
                {owner?.doc ?? ""}
              </Text>
            </View>
            <OiIcon name="chev-r" size={18} color={palette.textMute} />
          </Pressable>
        </OiSection>

        {/* Histórico */}
        <OiSection title="Histórico de manutenção">
          {historico.length === 0 ? (
            <OiCard variant="pad">
              <Text style={{ color: palette.textMute, fontSize: 12.5, textAlign: "center" }}>
                Sem registros de OSs ainda.
              </Text>
            </OiCard>
          ) : (
            <View style={{ gap: 8 }}>
              {historico.map((o) => (
                <Pressable
                  key={o.id}
                  onPress={() => router.push(`/manutencao/${o.id}` as never)}
                  style={({ pressed }) => ({
                    padding: 12,
                    borderRadius: radius.md,
                    borderWidth: 1,
                    borderColor: palette.border,
                    backgroundColor: pressed ? palette.bg2 : palette.surface,
                    flexDirection: "row",
                    alignItems: "center",
                    gap: 10,
                  })}
                >
                  <View style={{ flex: 1 }}>
                    <Text style={{ fontFamily: fonts.mono, fontSize: 12, color: palette.textMute }}>
                      {o.id}
                    </Text>
                    <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 13, color: palette.text }}>
                      {o.tipo} · {new Date(o.abertaEm).toLocaleDateString("pt-BR")}
                    </Text>
                  </View>
                  <OiStageStatus label={o.status} tone={statusTone(o.status)} />
                </Pressable>
              ))}
            </View>
          )}
        </OiSection>
      </ScrollView>

      {/* Action bar */}
      <View
        style={{
          padding: 12,
          flexDirection: "row",
          gap: 8,
          backgroundColor: palette.surface,
          borderTopWidth: 1,
          borderTopColor: palette.border,
        }}
      >
        <Pressable
          onPress={() => router.push(`/equipamentos/${e.id}/edit` as never)}
          style={{
            height: 48,
            paddingHorizontal: 16,
            borderRadius: radius.md,
            borderWidth: 1,
            borderColor: palette.border,
            alignItems: "center",
            justifyContent: "center",
            flexDirection: "row",
            gap: 6,
          }}
        >
          <OiIcon name="edit" size={16} color={palette.text} />
          <Text style={{ color: palette.text, fontFamily: fonts.sansSemibold, fontSize: 13 }}>Editar</Text>
        </Pressable>
        <Pressable
          onPress={() =>
            router.push({ pathname: `/manutencao/new`, params: { equip: e.id } } as never)
          }
          style={({ pressed }) => ({
            flex: 1,
            height: 48,
            borderRadius: radius.md,
            alignItems: "center",
            justifyContent: "center",
            flexDirection: "row",
            gap: 8,
            backgroundColor: palette.accent,
            opacity: pressed ? 0.85 : 1,
          })}
        >
          <OiIcon name="wrench" size={18} color="#fff" />
          <Text style={{ color: "#fff", fontFamily: fonts.sansSemibold, fontSize: 14 }}>
            Abrir manutenção
          </Text>
        </Pressable>
      </View>
    </OiScreen>
  );
}
