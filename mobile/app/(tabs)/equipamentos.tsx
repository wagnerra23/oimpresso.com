/**
 * EquipamentosScreen — frota e máquinas (Bloco 4.1).
 *
 * Busca + chips (Todos/Veículos/Equipamentos). Card com ícone, marca/modelo,
 * tipo/apelido, placa OU série, proprietário, uso (km/horas), selo
 * "Em manutenção" quando há OS ativa. FAB → /equipamentos/new.
 */
import { useMemo, useState } from "react";
import { Pressable, ScrollView, Text, View } from "react-native";
import { useRouter } from "expo-router";

import {
  OiCard,
  OiEmpty,
  OiFab,
  OiHeader,
  OiIcon,
  OiPlaca,
  OiScreen,
  OiSearch,
  OiSection,
  OiStageStatus,
} from "@/components/oi";
import { fonts, hexAlpha, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useEquipamentos } from "@/lib/use-equipamentos";
import { useManutencao } from "@/lib/use-manutencao";
import type { Equipamento } from "@/lib/equipamentos-mock";

type Filter = "todos" | "veiculo" | "equipamento";

export default function EquipamentosScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const { equipamentos, ownerOf } = useEquipamentos();
  const { oss } = useManutencao();
  const [q, setQ] = useState("");
  const [filter, setFilter] = useState<Filter>("todos");

  // Placas com OS ativa
  const placasAtivas = useMemo(() => {
    return new Set(
      oss
        .filter((o) => o.status !== "Pronto")
        .map((o) => o.veiculo.placa.toUpperCase()),
    );
  }, [oss]);

  const filtered = useMemo(() => {
    const term = q.trim().toLowerCase();
    return equipamentos.filter((e) => {
      if (filter !== "todos" && e.kind !== filter) return false;
      if (!term) return true;
      const hay = [e.marca, e.modelo, e.apelido, e.placa, e.serie, e.tipo]
        .filter(Boolean)
        .join(" ")
        .toLowerCase();
      return hay.includes(term);
    });
  }, [equipamentos, q, filter]);

  return (
    <OiScreen edges={["top"]}>
      <OiHeader title="Equipamentos" eyebrow="Frota e máquinas" />
      <View style={{ paddingHorizontal: 16, paddingTop: 4, gap: 10 }}>
        <OiSearch value={q} onChangeText={setQ} placeholder="Marca, modelo, placa, série…" />
        <View style={{ flexDirection: "row", gap: 6 }}>
          {([
            { id: "todos", label: "Todos" },
            { id: "veiculo", label: "Veículos" },
            { id: "equipamento", label: "Equipamentos" },
          ] as const).map((f) => {
            const on = filter === f.id;
            return (
              <Pressable
                key={f.id}
                onPress={() => setFilter(f.id)}
                style={{
                  paddingHorizontal: 12,
                  paddingVertical: 7,
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
                    color: on ? palette.accent : palette.text,
                  }}
                >
                  {f.label}
                </Text>
              </Pressable>
            );
          })}
        </View>
      </View>
      <ScrollView contentContainerStyle={{ paddingBottom: 96 }}>
        <OiSection>
          {filtered.length === 0 ? (
            <OiEmpty icon="truck" title="Nada por aqui" subtitle="Cadastre o primeiro equipamento." />
          ) : (
            filtered.map((e) => {
              const owner = ownerOf(e.id);
              const emManut = e.placa && placasAtivas.has(e.placa.toUpperCase());
              return (
                <Pressable
                  key={e.id}
                  onPress={() => router.push(`/equipamentos/${e.id}` as never)}
                  style={({ pressed }) => ({
                    flexDirection: "row",
                    alignItems: "center",
                    gap: 10,
                    padding: 12,
                    borderRadius: radius.md,
                    borderWidth: 1,
                    borderColor: palette.border,
                    backgroundColor: palette.surface,
                    marginBottom: 8,
                    opacity: pressed ? 0.85 : 1,
                  })}
                >
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
                      size={22}
                      color={palette.accent}
                    />
                  </View>
                  <View style={{ flex: 1, minWidth: 0 }}>
                    <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
                      <Text
                        style={{
                          fontFamily: fonts.sansSemibold,
                          fontSize: 14,
                          color: palette.text,
                        }}
                        numberOfLines={1}
                      >
                        {e.marca} {e.modelo}
                      </Text>
                      {e.apelido ? (
                        <Text
                          style={{
                            fontSize: 11.5,
                            color: palette.textMute,
                            fontFamily: fonts.sans,
                          }}
                        >
                          · {e.apelido}
                        </Text>
                      ) : null}
                    </View>
                    <Text
                      style={{ fontSize: 11.5, color: palette.textMute }}
                      numberOfLines={1}
                    >
                      {e.tipo} · {owner?.nome ?? "—"}
                    </Text>
                    <Text style={{ fontSize: 11, color: palette.textMute, fontFamily: fonts.mono }}>
                      {e.kind === "veiculo"
                        ? `${(e.hodometro ?? 0).toLocaleString("pt-BR")} km`
                        : `${(e.horimetro ?? 0).toLocaleString("pt-BR")} h`}
                    </Text>
                  </View>
                  <View style={{ alignItems: "flex-end", gap: 4 }}>
                    {e.placa ? <OiPlaca text={e.placa} size="sm" /> : null}
                    {emManut ? <OiStageStatus label="Em manutenção" tone="warn" /> : null}
                  </View>
                </Pressable>
              );
            })
          )}
        </OiSection>
      </ScrollView>
      <OiFab icon="plus" onPress={() => router.push("/equipamentos/new" as never)} />
    </OiScreen>
  );
}
