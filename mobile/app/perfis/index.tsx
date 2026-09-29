/**
 * PerfisScreen — lista de perfis de menu (Bloco 1, item 1.6).
 *
 * Origem do design: `ref/design/screens-perfis.jsx` → `PerfisScreen`.
 */
import { useRouter } from "expo-router";
import { Pressable, ScrollView, Text, View } from "react-native";

import {
  OiBarPreview,
  OiBtn,
  OiCard,
  OiDetailHeader,
  OiIcon,
  OiScreen,
  OiSection,
  OiStatus,
} from "@/components/oi";
import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useMenuProfile } from "@/lib/menu-profile-context";

export default function PerfisScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const { profiles, activeId, applyProfile } = useMenuProfile();

  return (
    <OiScreen edges={["top"]}>
      <OiDetailHeader
        title="Perfis de menu"
        eyebrow="Barra personalizada por função"
        onBack={() => router.back()}
      />
      <ScrollView
        contentContainerStyle={{ paddingBottom: 32 }}
        showsVerticalScrollIndicator={false}
      >
        <OiSection>
          <Text
            style={{
              color: palette.textDim,
              fontFamily: fonts.sans,
              fontSize: 12.5,
              lineHeight: 18,
            }}
          >
            Monte a barra inferior de cada função. O administrador escolhe
            quais módulos aparecem — ex.: a oficina troca{" "}
            <Text style={{ fontFamily: fonts.sansSemibold }}>Produção</Text>{" "}
            por <Text style={{ fontFamily: fonts.sansSemibold }}>Oficina</Text>;
            o faturamento prioriza{" "}
            <Text style={{ fontFamily: fonts.sansSemibold }}>Vendas</Text>,{" "}
            <Text style={{ fontFamily: fonts.sansSemibold }}>Financeiro</Text>{" "}
            e{" "}
            <Text style={{ fontFamily: fonts.sansSemibold }}>Relatórios</Text>.{" "}
            <Text style={{ fontFamily: fonts.sansSemibold }}>Início</Text> e{" "}
            <Text style={{ fontFamily: fonts.sansSemibold }}>Mais</Text> são
            sempre fixos.
          </Text>
        </OiSection>

        <View style={{ paddingHorizontal: 16, paddingTop: 0, paddingBottom: 6, gap: 10 }}>
          {profiles.map((p) => {
            const on = p.id === activeId;
            return (
              <OiCard
                key={p.id}
                style={{
                  gap: 12,
                  padding: 14,
                  borderColor: on ? palette.accent : palette.border,
                  borderWidth: on ? 1.5 : 1,
                }}
              >
                <View
                  style={{
                    flexDirection: "row",
                    alignItems: "flex-start",
                    gap: 8,
                  }}
                >
                  <View style={{ flex: 1, minWidth: 0 }}>
                    <View
                      style={{
                        flexDirection: "row",
                        alignItems: "center",
                        gap: 8,
                        flexWrap: "wrap",
                      }}
                    >
                      <Text
                        style={{
                          fontSize: 15,
                          fontFamily: fonts.sansSemibold,
                          color: palette.text,
                        }}
                      >
                        {p.nome}
                      </Text>
                      {on ? (
                        <OiStatus variant="accent" label="Ativo" />
                      ) : null}
                      {p.sys ? (
                        <OiStatus variant="ok" label="Sistema" />
                      ) : null}
                    </View>
                    <Text
                      style={{
                        marginTop: 2,
                        color: palette.textMute,
                        fontFamily: fonts.sans,
                        fontSize: 11.5,
                      }}
                    >
                      {p.funcao}
                    </Text>
                  </View>
                  <Pressable
                    onPress={() =>
                      // expo-router typed routes ainda não conhecem este
                      // segmento — cast pra `any` é seguro porque o arquivo
                      // existe em `app/perfis/[id]/edit.tsx`.
                      router.push(`/perfis/${p.id}/edit` as never)
                    }
                    hitSlop={6}
                    style={{
                      width: 34,
                      height: 34,
                      alignItems: "center",
                      justifyContent: "center",
                      borderRadius: radius.sm,
                      backgroundColor: palette.bg2,
                      borderColor: palette.border,
                      borderWidth: 1,
                    }}
                  >
                    <OiIcon name="edit" size={16} color={palette.textDim} />
                  </Pressable>
                </View>

                <OiBarPreview mods={p.mods} />

                {!on ? (
                  <OiBtn
                    label="Aplicar este perfil"
                    variant="primary"
                    block
                    leftIcon="check"
                    onPress={() => applyProfile(p.id)}
                  />
                ) : null}
              </OiCard>
            );
          })}

          {/* Botão "Novo perfil" estilizado como ação positiva (verde),
              equivalente a `.oi-btn.action.block` do design. */}
          <Pressable
            onPress={() => router.push(`/perfis/new/edit` as never)}
            style={({ pressed }) => ({
              marginTop: 4,
              height: 44,
              borderRadius: radius.md,
              alignItems: "center",
              justifyContent: "center",
              flexDirection: "row",
              gap: 8,
              backgroundColor: palette.ok,
              opacity: pressed ? 0.85 : 1,
            })}
          >
            <OiIcon name="plus" size={18} color="#fff" />
            <Text
              style={{
                color: "#fff",
                fontFamily: fonts.sansSemibold,
                fontSize: 14.5,
                letterSpacing: 0.2,
              }}
            >
              Novo perfil de menu
            </Text>
          </Pressable>
        </View>
      </ScrollView>
    </OiScreen>
  );
}
