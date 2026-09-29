/**
 * LocaisScreen — lista de locais físicos da oficina (Bloco 3E).
 *
 * Cada local: ícone, nome, tipo·capacidade·obs, status livre/ocupado,
 * lixeira (só ativa se livre). FAB → /locais/new.
 */
import { useRouter } from "expo-router";
import { Pressable, ScrollView, Text, View } from "react-native";

import {
  OiCard,
  OiDetailHeader,
  OiFab,
  OiIcon,
  OiScreen,
  OiSection,
  OiStageStatus,
  useToast,
} from "@/components/oi";
import { fonts, hexAlpha, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useManutencao } from "@/lib/use-manutencao";
import type { LocalTipo } from "@/lib/manutencao-mock";

const ICON_FOR_TIPO: Record<LocalTipo, "wrench" | "layers" | "box" | "truck" | "location"> = {
  Rampa: "wrench",
  Elevador: "layers",
  Box: "box",
  Pátio: "truck",
  Externo: "location",
};

export default function LocaisScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const toast = useToast();
  const { locais, removeLocal } = useManutencao();

  return (
    <OiScreen edges={["top"]}>
      <OiDetailHeader title="Locais da oficina" eyebrow="Pátio · boxes · rampas" onBack={() => router.back()} />
      <ScrollView contentContainerStyle={{ paddingBottom: 96 }} showsVerticalScrollIndicator={false}>
        <OiSection>
          {locais.map((l) => {
            const ocupados = l.ocupadoPor.length;
            const livre = ocupados === 0;
            const cheio = ocupados >= l.capacidade;
            const tone = livre ? "ok" : cheio ? "danger" : "warn";
            return (
              <OiCard
                key={l.id}
                style={{ padding: 12, gap: 8, marginBottom: 10, flexDirection: "row", alignItems: "center" }}
              >
                <View
                  style={{
                    width: 40,
                    height: 40,
                    borderRadius: 10,
                    backgroundColor: hexAlpha(palette.accent, 0.14),
                    alignItems: "center",
                    justifyContent: "center",
                  }}
                >
                  <OiIcon name={ICON_FOR_TIPO[l.tipo]} size={20} color={palette.accent} />
                </View>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Text style={{ fontSize: 14.5, fontFamily: fonts.sansSemibold, color: palette.text }}>
                    {l.nome}
                  </Text>
                  <Text style={{ fontSize: 11.5, color: palette.textMute }} numberOfLines={1}>
                    {l.tipo} · capacidade {l.capacidade}
                    {l.observacao ? ` · ${l.observacao}` : ""}
                  </Text>
                </View>
                <View style={{ alignItems: "flex-end", gap: 6 }}>
                  <OiStageStatus
                    tone={tone}
                    label={livre ? "Livre" : `${ocupados}/${l.capacidade}`}
                  />
                  <Pressable
                    onPress={() => {
                      if (!livre) {
                        toast.show("Local ocupado — não dá pra excluir", "warn");
                        return;
                      }
                      removeLocal(l.id);
                      toast.show("Local removido", "ok");
                    }}
                    disabled={!livre}
                    hitSlop={6}
                    style={{
                      width: 30,
                      height: 30,
                      alignItems: "center",
                      justifyContent: "center",
                      borderRadius: radius.sm,
                      opacity: livre ? 1 : 0.35,
                    }}
                  >
                    <OiIcon name="trash" size={16} color={palette.danger} />
                  </Pressable>
                </View>
              </OiCard>
            );
          })}
        </OiSection>
      </ScrollView>
      <OiFab icon="plus" onPress={() => router.push("/locais/new" as never)} />
    </OiScreen>
  );
}
