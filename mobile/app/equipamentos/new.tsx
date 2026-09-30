/**
 * NovoEquipamentoScreen — cadastro de veículo OU equipamento (Bloco 4.6-4.9).
 *
 * Segmento Veículo/Equipamento. Proprietário obrigatório via PessoaPickerSheet.
 * Campos condicionais à categoria. Bloco "Carroceria/reboque" (placa 2 +
 * chassi 2) só em veículos.
 */
import { useState } from "react";
import { useRouter } from "expo-router";
import { Pressable, ScrollView, Text, TextInput, View } from "react-native";

import {
  OiDetailHeader,
  OiIcon,
  OiScreen,
  OiSheet,
  useToast,
} from "@/components/oi";
import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useEquipamentos } from "@/lib/use-equipamentos";
import type { EquipKind, Pessoa } from "@/lib/equipamentos-mock";

export default function NovoEquipamentoScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const toast = useToast();
  const { create, pessoas } = useEquipamentos();

  const [kind, setKind] = useState<EquipKind>("veiculo");
  const [owner, setOwner] = useState<Pessoa | null>(null);
  const [marca, setMarca] = useState("");
  const [modelo, setModelo] = useState("");
  const [apelido, setApelido] = useState("");
  const [tipo, setTipo] = useState("");
  const [ano, setAno] = useState<string>(String(new Date().getFullYear()));
  const [cor, setCor] = useState("");
  const [placa, setPlaca] = useState("");
  const [placa2, setPlaca2] = useState("");
  const [chassi, setChassi] = useState("");
  const [chassi2, setChassi2] = useState("");
  const [renavam, setRenavam] = useState("");
  const [hodometro, setHodometro] = useState("");
  const [serie, setSerie] = useState("");
  const [horimetro, setHorimetro] = useState("");
  const [pickerOpen, setPickerOpen] = useState(false);

  const canSave =
    owner !== null &&
    marca.trim() &&
    modelo.trim() &&
    (kind === "veiculo" ? placa.trim().length > 0 : serie.trim().length > 0);

  const submit = () => {
    if (!canSave || !owner) return;
    create({
      kind,
      ownerId: owner.id,
      marca: marca.trim(),
      modelo: modelo.trim(),
      apelido: apelido.trim() || undefined,
      tipo: tipo.trim() || (kind === "veiculo" ? "Veículo" : "Equipamento"),
      ano: Number(ano.replace(/\D/g, "")) || new Date().getFullYear(),
      cor: cor.trim() || undefined,
      ...(kind === "veiculo"
        ? {
            placa: placa.trim().toUpperCase(),
            placa2: placa2.trim().toUpperCase() || undefined,
            chassi: chassi.trim().toUpperCase() || undefined,
            chassi2: chassi2.trim().toUpperCase() || undefined,
            renavam: renavam.trim() || undefined,
            hodometro: Number(hodometro.replace(/\D/g, "")) || 0,
          }
        : {
            serie: serie.trim() || undefined,
            horimetro: Number(horimetro.replace(/\D/g, "")) || 0,
          }),
    });
    toast.show("Equipamento cadastrado", "ok");
    router.back();
  };

  return (
    <OiScreen edges={["top"]}>
      <OiDetailHeader title="Novo equipamento" eyebrow="Frota" onBack={() => router.back()} />
      <ScrollView contentContainerStyle={{ paddingBottom: 32 }}>
        <View style={{ padding: 16, gap: 14 }}>
          {/* Kind toggle */}
          <View
            style={{
              flexDirection: "row",
              backgroundColor: palette.bg2,
              borderRadius: radius.md,
              padding: 3,
            }}
          >
            {(["veiculo", "equipamento"] as const).map((k) => {
              const on = kind === k;
              return (
                <Pressable
                  key={k}
                  onPress={() => setKind(k)}
                  style={{
                    flex: 1,
                    paddingVertical: 10,
                    borderRadius: radius.sm,
                    alignItems: "center",
                    backgroundColor: on ? palette.surface : "transparent",
                  }}
                >
                  <Text
                    style={{
                      fontFamily: fonts.sansSemibold,
                      fontSize: 13,
                      color: on ? palette.text : palette.textMute,
                    }}
                  >
                    {k === "veiculo" ? "Veículo" : "Equipamento"}
                  </Text>
                </Pressable>
              );
            })}
          </View>

          {/* Proprietário */}
          <Label>Proprietário *</Label>
          <Pressable
            onPress={() => setPickerOpen(true)}
            style={({ pressed }) => ({
              padding: 12,
              borderRadius: radius.md,
              borderWidth: 1,
              borderColor: owner ? palette.accent : palette.border,
              backgroundColor: pressed ? palette.bg2 : palette.surface,
              flexDirection: "row",
              alignItems: "center",
              gap: 10,
            })}
          >
            <OiIcon name="user" size={20} color={owner ? palette.accent : palette.textDim} />
            <View style={{ flex: 1 }}>
              <Text style={{ fontSize: 13.5, fontFamily: fonts.sansSemibold, color: palette.text }}>
                {owner?.nome ?? "Selecionar pessoa…"}
              </Text>
              {owner ? (
                <Text style={{ fontSize: 11.5, color: palette.textMute, fontFamily: fonts.mono }}>
                  {owner.doc}
                </Text>
              ) : null}
            </View>
            <OiIcon name="chev-r" size={18} color={palette.textMute} />
          </Pressable>

          {/* Comuns */}
          <View style={{ flexDirection: "row", gap: 8 }}>
            <View style={{ flex: 1 }}>
              <Label>Marca *</Label>
              <Input value={marca} onChangeText={setMarca} />
            </View>
            <View style={{ flex: 1 }}>
              <Label>Modelo *</Label>
              <Input value={modelo} onChangeText={setModelo} />
            </View>
          </View>
          <View>
            <Label>Apelido</Label>
            <Input value={apelido} onChangeText={setApelido} />
          </View>
          <View style={{ flexDirection: "row", gap: 8 }}>
            <View style={{ flex: 1 }}>
              <Label>Tipo</Label>
              <Input
                value={tipo}
                onChangeText={setTipo}
                placeholder={kind === "veiculo" ? "ex: Caminhão" : "ex: Compressor"}
              />
            </View>
            <View style={{ flex: 1 }}>
              <Label>Ano</Label>
              <Input value={ano} onChangeText={setAno} keyboardType="numeric" />
            </View>
          </View>

          {kind === "veiculo" ? (
            <>
              <View style={{ flexDirection: "row", gap: 8 }}>
                <View style={{ flex: 1 }}>
                  <Label>Placa *</Label>
                  <Input
                    value={placa}
                    onChangeText={(v) => setPlaca(v.toUpperCase())}
                    placeholder="AAA0A00"
                  />
                </View>
                <View style={{ flex: 1 }}>
                  <Label>Cor</Label>
                  <Input value={cor} onChangeText={setCor} />
                </View>
              </View>
              <Label>Chassi</Label>
              <Input value={chassi} onChangeText={(v) => setChassi(v.toUpperCase())} />
              <Label>Renavam</Label>
              <Input value={renavam} onChangeText={setRenavam} keyboardType="numeric" />
              <Label>Hodômetro (km)</Label>
              <Input value={hodometro} onChangeText={setHodometro} keyboardType="numeric" />

              {/* Reboque */}
              <Text
                style={{
                  marginTop: 10,
                  fontFamily: fonts.sansBold,
                  fontSize: 11,
                  letterSpacing: 1,
                  textTransform: "uppercase",
                  color: palette.textMute,
                }}
              >
                Carroceria / reboque (opcional)
              </Text>
              <View style={{ flexDirection: "row", gap: 8 }}>
                <View style={{ flex: 1 }}>
                  <Label>Placa 2</Label>
                  <Input
                    value={placa2}
                    onChangeText={(v) => setPlaca2(v.toUpperCase())}
                  />
                </View>
                <View style={{ flex: 1 }}>
                  <Label>Chassi 2</Label>
                  <Input
                    value={chassi2}
                    onChangeText={(v) => setChassi2(v.toUpperCase())}
                  />
                </View>
              </View>
            </>
          ) : (
            <>
              <Label>Nº de série *</Label>
              <Input value={serie} onChangeText={setSerie} />
              <Label>Horímetro (h)</Label>
              <Input value={horimetro} onChangeText={setHorimetro} keyboardType="numeric" />
            </>
          )}
        </View>
      </ScrollView>

      <View
        style={{
          padding: 12,
          backgroundColor: palette.surface,
          borderTopWidth: 1,
          borderTopColor: palette.border,
        }}
      >
        <Pressable
          onPress={submit}
          disabled={!canSave}
          style={({ pressed }) => ({
            height: 48,
            borderRadius: radius.md,
            alignItems: "center",
            justifyContent: "center",
            flexDirection: "row",
            gap: 8,
            backgroundColor: palette.ok,
            opacity: !canSave ? 0.4 : pressed ? 0.85 : 1,
          })}
        >
          <OiIcon name="check" size={18} color="#fff" />
          <Text style={{ color: "#fff", fontFamily: fonts.sansSemibold, fontSize: 14 }}>Salvar</Text>
        </Pressable>
      </View>

      {/* PessoaPickerSheet */}
      <OiSheet visible={pickerOpen} onClose={() => setPickerOpen(false)} title="Selecionar pessoa">
        <View style={{ gap: 6 }}>
          {pessoas.map((p) => {
            const on = owner?.id === p.id;
            return (
              <Pressable
                key={p.id}
                onPress={() => {
                  setOwner(p);
                  setPickerOpen(false);
                }}
                style={({ pressed }) => ({
                  padding: 12,
                  borderRadius: radius.md,
                  borderWidth: 1,
                  borderColor: on ? palette.accent : palette.border,
                  backgroundColor: pressed ? palette.bg2 : palette.surface,
                })}
              >
                <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 13.5, color: palette.text }}>
                  {p.nome}
                </Text>
                <Text style={{ fontFamily: fonts.mono, fontSize: 11, color: palette.textMute }}>
                  {p.doc} · {p.papeis.join(", ")}
                </Text>
              </Pressable>
            );
          })}
        </View>
      </OiSheet>
    </OiScreen>
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

function Input({
  value,
  onChangeText,
  placeholder,
  keyboardType,
}: {
  value: string;
  onChangeText: (v: string) => void;
  placeholder?: string;
  keyboardType?: "default" | "numeric";
}) {
  const { palette } = useOiTheme();
  return (
    <TextInput
      value={value}
      onChangeText={onChangeText}
      placeholder={placeholder}
      placeholderTextColor={palette.textMute}
      keyboardType={keyboardType}
      style={{
        height: 44,
        paddingHorizontal: 12,
        borderRadius: radius.md,
        borderWidth: 1,
        borderColor: palette.border,
        backgroundColor: palette.bg2,
        color: palette.text,
        fontFamily: fonts.sans,
        fontSize: 14,
      }}
    />
  );
}
