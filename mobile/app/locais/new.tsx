/**
 * NovoLocalScreen — cadastro de local físico (Bloco 3E item 3.32).
 *
 * Nome, tipo (chips), capacidade (stepper -/+), observação.
 */
import { useState } from "react";
import { useRouter } from "expo-router";
import { Pressable, ScrollView, Text, TextInput, View } from "react-native";

import {
  OiDetailHeader,
  OiIcon,
  OiScreen,
  OiSection,
  useToast,
} from "@/components/oi";
import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useManutencao } from "@/lib/use-manutencao";
import type { LocalTipo } from "@/lib/manutencao-mock";

const TIPOS: LocalTipo[] = ["Rampa", "Elevador", "Box", "Pátio", "Externo"];

export default function NovoLocalScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const toast = useToast();
  const { addLocal } = useManutencao();

  const [nome, setNome] = useState("");
  const [tipo, setTipo] = useState<LocalTipo>("Box");
  const [capacidade, setCapacidade] = useState(1);
  const [observacao, setObservacao] = useState("");

  const canSave = nome.trim().length > 0 && capacidade > 0;

  const submit = () => {
    if (!canSave) return;
    addLocal({ nome: nome.trim(), tipo, capacidade, observacao: observacao.trim() || undefined });
    toast.show("Local cadastrado", "ok");
    router.back();
  };

  return (
    <OiScreen edges={["top"]}>
      <OiDetailHeader title="Novo local" eyebrow="Cadastro" onBack={() => router.back()} />
      <ScrollView contentContainerStyle={{ paddingBottom: 32 }}>
        <View style={{ padding: 16, gap: 14 }}>
          <View>
            <Label>Nome *</Label>
            <Input value={nome} onChangeText={setNome} placeholder="ex: Box 3" />
          </View>
          <View>
            <Label>Tipo</Label>
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
              {TIPOS.map((t) => {
                const on = tipo === t;
                return (
                  <Pressable
                    key={t}
                    onPress={() => setTipo(t)}
                    style={{
                      paddingHorizontal: 12,
                      paddingVertical: 8,
                      borderRadius: radius.pill,
                      borderWidth: 1,
                      borderColor: on ? palette.accent : palette.border,
                      backgroundColor: on ? palette.accentSoft : palette.surface,
                    }}
                  >
                    <Text
                      style={{
                        fontSize: 12.5,
                        color: on ? palette.accent : palette.text,
                        fontFamily: fonts.sansSemibold,
                      }}
                    >
                      {t}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          </View>
          <View>
            <Label>Capacidade</Label>
            <View style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
              <Pressable
                onPress={() => setCapacidade((c) => Math.max(1, c - 1))}
                style={{
                  width: 40,
                  height: 40,
                  borderRadius: radius.md,
                  borderWidth: 1,
                  borderColor: palette.border,
                  alignItems: "center",
                  justifyContent: "center",
                }}
              >
                <OiIcon name="x" size={16} color={palette.text} />
              </Pressable>
              <Text
                style={{
                  fontFamily: fonts.sansBold,
                  fontSize: 22,
                  color: palette.text,
                  minWidth: 40,
                  textAlign: "center",
                }}
              >
                {capacidade}
              </Text>
              <Pressable
                onPress={() => setCapacidade((c) => c + 1)}
                style={{
                  width: 40,
                  height: 40,
                  borderRadius: radius.md,
                  borderWidth: 1,
                  borderColor: palette.border,
                  alignItems: "center",
                  justifyContent: "center",
                }}
              >
                <OiIcon name="plus" size={16} color={palette.text} />
              </Pressable>
            </View>
          </View>
          <View>
            <Label>Observação</Label>
            <TextInput
              value={observacao}
              onChangeText={setObservacao}
              placeholder="(opcional)"
              placeholderTextColor={palette.textMute}
              style={inputStyle(palette)}
            />
          </View>
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
          <Text style={{ color: "#fff", fontFamily: fonts.sansSemibold, fontSize: 14 }}>Salvar local</Text>
        </Pressable>
      </View>
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
}: {
  value: string;
  onChangeText: (v: string) => void;
  placeholder?: string;
}) {
  const { palette } = useOiTheme();
  return (
    <TextInput
      value={value}
      onChangeText={onChangeText}
      placeholder={placeholder}
      placeholderTextColor={palette.textMute}
      style={inputStyle(palette)}
    />
  );
}

function inputStyle(palette: { text: string; border: string; bg2: string }) {
  return {
    height: 44,
    paddingHorizontal: 12,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: palette.border,
    backgroundColor: palette.bg2,
    color: palette.text,
    fontFamily: fonts.sans,
    fontSize: 14,
  };
}
