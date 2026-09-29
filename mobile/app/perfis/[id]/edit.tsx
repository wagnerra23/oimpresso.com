/**
 * PerfilEditScreen — editor (criação + edição) de perfil de menu.
 *
 * Origem do design: `ref/design/screens-perfis.jsx` → `PerfilEditScreen`.
 *
 * Rota: `/perfis/[id]/edit`. Se `id === "new"`, opera em modo de criação.
 * Caso contrário, edita o perfil correspondente. Perfis com `sys: true` podem
 * ter os módulos reordenados, mas não podem ser excluídos.
 */
import { useLocalSearchParams, useRouter } from "expo-router";
import { useMemo, useState } from "react";
import { Pressable, ScrollView, Text, TextInput, View } from "react-native";

import {
  OiBarPreview,
  OiCard,
  OiDetailHeader,
  OiIcon,
  OiScreen,
  OiSection,
  OiSectionHeader,
} from "@/components/oi";
import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import {
  MAX_MENU_SLOTS,
  MENU_MODULES,
  getMenuModule,
  type MenuModuleId,
} from "@/lib/menu-modules";
import { useMenuProfile } from "@/lib/menu-profile-context";

export default function PerfilEditScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const { id: routeId } = useLocalSearchParams<{ id: string }>();
  const {
    profiles,
    createProfile,
    updateProfile,
    removeProfile,
    applyProfile,
  } = useMenuProfile();

  const editingId = routeId && routeId !== "new" ? routeId : null;
  const editing = useMemo(
    () => (editingId ? profiles.find((p) => p.id === editingId) ?? null : null),
    [editingId, profiles],
  );

  const [nome, setNome] = useState(editing?.nome ?? "");
  const [funcao, setFuncao] = useState(editing?.funcao ?? "");
  const [mods, setMods] = useState<MenuModuleId[]>(
    editing ? [...editing.mods] : [],
  );

  const disponiveis = useMemo(
    () => MENU_MODULES.filter((m) => !mods.includes(m.id)),
    [mods],
  );
  const cheio = mods.length >= MAX_MENU_SLOTS;

  const addMod = (mid: MenuModuleId) => {
    if (cheio) return;
    setMods((curr) => (curr.includes(mid) ? curr : [...curr, mid]));
  };
  const rmMod = (mid: MenuModuleId) =>
    setMods((curr) => curr.filter((x) => x !== mid));
  const move = (i: number, dir: -1 | 1) =>
    setMods((curr) => {
      const j = i + dir;
      if (j < 0 || j >= curr.length) return curr;
      const next = [...curr];
      const tmp = next[i]!;
      next[i] = next[j]!;
      next[j] = tmp;
      return next;
    });

  const canSave = nome.trim().length > 0;

  const salvar = () => {
    if (!canSave) return;
    if (editing) {
      updateProfile(editing.id, { nome, funcao, mods });
      applyProfile(editing.id);
    } else {
      createProfile({ nome, funcao, mods });
    }
    router.back();
  };
  const excluir = () => {
    if (!editing || editing.sys) return;
    removeProfile(editing.id);
    router.back();
  };

  return (
    <OiScreen edges={["top"]}>
      <OiDetailHeader
        title={editing ? "Editar perfil" : "Novo perfil"}
        eyebrow="Configuração da barra"
        onBack={() => router.back()}
      />

      <ScrollView
        contentContainerStyle={{ paddingBottom: 24 }}
        showsVerticalScrollIndicator={false}
        keyboardShouldPersistTaps="handled"
      >
        {/* ── Identificação ───────────────────────────────────── */}
        <OiSection>
          <View style={{ gap: 4 }}>
            <Text style={labelStyle(palette.textDim)}>
              Nome do perfil <Text style={{ color: palette.danger }}>*</Text>
            </Text>
            <TextInput
              value={nome}
              onChangeText={setNome}
              placeholder="Ex: Faturamento"
              placeholderTextColor={palette.textMute}
              style={inputStyle(palette)}
            />
          </View>
          <View style={{ gap: 4, marginTop: 12 }}>
            <Text style={labelStyle(palette.textDim)}>Função / setor</Text>
            <TextInput
              value={funcao}
              onChangeText={setFuncao}
              placeholder="Ex: Financeiro / fiscal"
              placeholderTextColor={palette.textMute}
              style={inputStyle(palette)}
            />
          </View>
        </OiSection>

        {/* ── Prévia ────────────────────────────────────────────── */}
        <View style={{ paddingHorizontal: 16, paddingBottom: 6 }}>
          <OiSectionHeader title="Prévia da barra" />
          <OiBarPreview mods={mods} />
        </View>

        {/* ── Módulos selecionados ──────────────────────────────── */}
        <OiSection>
          <View
            style={{
              flexDirection: "row",
              alignItems: "center",
              justifyContent: "space-between",
              marginBottom: 8,
            }}
          >
            <OiSectionHeader title="Módulos na barra" />
            <Text
              style={{
                fontFamily: fonts.sansSemibold,
                fontSize: 11.5,
                color: cheio ? palette.warn : palette.textMute,
              }}
            >
              {mods.length}/{MAX_MENU_SLOTS}
            </Text>
          </View>

          {mods.length === 0 ? (
            <OiCard
              style={{
                padding: 18,
                alignItems: "center",
              }}
            >
              <Text
                style={{
                  color: palette.textMute,
                  fontFamily: fonts.sans,
                  fontSize: 12.5,
                  textAlign: "center",
                  lineHeight: 18,
                }}
              >
                Nenhum módulo ainda — adicione abaixo (Início e Mais já são
                fixos).
              </Text>
            </OiCard>
          ) : (
            <OiCard style={{ padding: 0, overflow: "hidden" }}>
              {mods.map((mid, i) => {
                const m = getMenuModule(mid);
                if (!m) return null;
                const isFirst = i === 0;
                const isLast = i === mods.length - 1;
                return (
                  <View
                    key={mid}
                    style={{
                      flexDirection: "row",
                      alignItems: "center",
                      gap: 10,
                      paddingHorizontal: 12,
                      paddingVertical: 10,
                      borderTopWidth: i === 0 ? 0 : 1,
                      borderTopColor: palette.border,
                    }}
                  >
                    <View
                      style={{
                        width: 32,
                        height: 32,
                        borderRadius: 8,
                        alignItems: "center",
                        justifyContent: "center",
                        backgroundColor: palette.accentSoft,
                      }}
                    >
                      <OiIcon name={m.icon} size={16} color={palette.accent} />
                    </View>
                    <View style={{ flex: 1, minWidth: 0 }}>
                      <Text
                        style={{
                          fontSize: 13.5,
                          fontFamily: fonts.sansSemibold,
                          color: palette.text,
                        }}
                        numberOfLines={1}
                      >
                        {m.label}
                      </Text>
                      <Text
                        style={{
                          fontSize: 11,
                          color: palette.textMute,
                          fontFamily: fonts.sans,
                        }}
                        numberOfLines={1}
                      >
                        {m.desc}
                      </Text>
                    </View>
                    <View style={{ flexDirection: "row", gap: 0 }}>
                      <IconBtn
                        icon="chev-u"
                        disabled={isFirst}
                        onPress={() => move(i, -1)}
                      />
                      <IconBtn
                        icon="chev-d"
                        disabled={isLast}
                        onPress={() => move(i, 1)}
                      />
                      <IconBtn
                        icon="x"
                        tone="danger"
                        onPress={() => rmMod(mid)}
                      />
                    </View>
                  </View>
                );
              })}
            </OiCard>
          )}
        </OiSection>

        {/* ── Disponíveis ───────────────────────────────────────── */}
        {disponiveis.length > 0 ? (
          <OiSection>
            <View
              style={{
                flexDirection: "row",
                alignItems: "center",
                justifyContent: "space-between",
                gap: 8,
                marginBottom: 8,
              }}
            >
              <OiSectionHeader title="Adicionar módulo" />
              {cheio ? (
                <Text
                  style={{
                    fontFamily: fonts.sansSemibold,
                    fontSize: 10.5,
                    color: palette.warn,
                    flex: 1,
                    textAlign: "right",
                  }}
                  numberOfLines={1}
                >
                  Barra cheia — remova um para trocar
                </Text>
              ) : null}
            </View>
            <OiCard
              style={{
                padding: 0,
                overflow: "hidden",
                opacity: cheio ? 0.5 : 1,
              }}
            >
              {disponiveis.map((m, i) => (
                <Pressable
                  key={m.id}
                  onPress={() => addMod(m.id)}
                  disabled={cheio}
                  style={({ pressed }) => ({
                    flexDirection: "row",
                    alignItems: "center",
                    gap: 10,
                    paddingHorizontal: 12,
                    paddingVertical: 10,
                    borderTopWidth: i === 0 ? 0 : 1,
                    borderTopColor: palette.border,
                    backgroundColor: pressed ? palette.bg2 : "transparent",
                  })}
                >
                  <View
                    style={{
                      width: 32,
                      height: 32,
                      borderRadius: 8,
                      alignItems: "center",
                      justifyContent: "center",
                      backgroundColor: palette.bg2,
                      borderColor: palette.border,
                      borderWidth: 1,
                    }}
                  >
                    <OiIcon
                      name={m.icon}
                      size={16}
                      color={palette.textDim}
                    />
                  </View>
                  <View style={{ flex: 1, minWidth: 0 }}>
                    <Text
                      style={{
                        fontSize: 13.5,
                        fontFamily: fonts.sansSemibold,
                        color: palette.text,
                      }}
                      numberOfLines={1}
                    >
                      {m.label}
                    </Text>
                    <Text
                      style={{
                        fontSize: 11,
                        color: palette.textMute,
                        fontFamily: fonts.sans,
                      }}
                      numberOfLines={1}
                    >
                      {m.desc}
                    </Text>
                  </View>
                  <View
                    style={{
                      flexDirection: "row",
                      alignItems: "center",
                      gap: 4,
                    }}
                  >
                    <OiIcon name="plus" size={14} color={palette.ok} />
                    <Text
                      style={{
                        fontFamily: fonts.sansSemibold,
                        fontSize: 11.5,
                        color: palette.ok,
                      }}
                    >
                      Adicionar
                    </Text>
                  </View>
                </Pressable>
              ))}
            </OiCard>
          </OiSection>
        ) : null}

        {/* ── Excluir (apenas perfis não-sys) ────────────────────── */}
        {editing && !editing.sys ? (
          <OiSection>
            <Pressable
              onPress={excluir}
              style={({ pressed }) => ({
                height: 44,
                borderRadius: radius.md,
                alignItems: "center",
                justifyContent: "center",
                flexDirection: "row",
                gap: 8,
                backgroundColor: pressed ? palette.bg2 : "transparent",
                borderColor: palette.border,
                borderWidth: 1,
              })}
            >
              <OiIcon name="trash" size={16} color={palette.danger} />
              <Text
                style={{
                  color: palette.danger,
                  fontFamily: fonts.sansSemibold,
                  fontSize: 14,
                }}
              >
                Excluir perfil
              </Text>
            </Pressable>
          </OiSection>
        ) : null}
      </ScrollView>

      {/* ── Action bar ──────────────────────────────────────────── */}
      <View
        style={{
          padding: 12,
          backgroundColor: palette.surface,
          borderTopWidth: 1,
          borderTopColor: palette.border,
        }}
      >
        <Pressable
          onPress={salvar}
          disabled={!canSave}
          style={({ pressed }) => ({
            height: 48,
            borderRadius: radius.md,
            alignItems: "center",
            justifyContent: "center",
            flexDirection: "row",
            gap: 8,
            backgroundColor: palette.ok,
            opacity: !canSave ? 0.5 : pressed ? 0.85 : 1,
          })}
        >
          <OiIcon name="check" size={18} color="#fff" />
          <Text
            style={{
              color: "#fff",
              fontFamily: fonts.sansSemibold,
              fontSize: 15,
              letterSpacing: 0.2,
            }}
          >
            {editing ? "Salvar e aplicar" : "Criar e aplicar"}
          </Text>
        </Pressable>
      </View>
    </OiScreen>
  );
}

// ─── helpers ───────────────────────────────────────────────────────

function labelStyle(color: string) {
  return {
    fontFamily: fonts.sansSemibold,
    fontSize: 11,
    letterSpacing: 0.6,
    textTransform: "uppercase" as const,
    color,
  };
}

function inputStyle(palette: {
  text: string;
  border: string;
  bg2: string;
}) {
  return {
    height: 44,
    paddingHorizontal: 12,
    borderRadius: radius.md,
    borderColor: palette.border,
    borderWidth: 1,
    backgroundColor: palette.bg2,
    color: palette.text,
    fontFamily: fonts.sans,
    fontSize: 14,
  };
}

type IconBtnProps = {
  icon: "chev-u" | "chev-d" | "x";
  onPress: () => void;
  disabled?: boolean;
  tone?: "default" | "danger";
};

function IconBtn({ icon, onPress, disabled, tone = "default" }: IconBtnProps) {
  const { palette } = useOiTheme();
  const color = tone === "danger" ? palette.danger : palette.textDim;
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      hitSlop={4}
      style={({ pressed }) => ({
        width: 30,
        height: 30,
        alignItems: "center",
        justifyContent: "center",
        borderRadius: radius.sm,
        opacity: disabled ? 0.3 : pressed ? 0.7 : 1,
      })}
    >
      <OiIcon name={icon} size={16} color={color} />
    </Pressable>
  );
}
