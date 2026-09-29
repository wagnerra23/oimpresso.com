/**
 * Mais — hub de módulos secundários (Tab 5).
 *
 * Espelha `MaisScreen` do design (`ref/design/screens-clientes-producao.jsx`).
 * Top: card destacado "Venda rápida" (link para PDV/pagamentos).
 * Grid 2-col de módulos coloridos + lista de ferramentas + bloco "Conta".
 */
import { Pressable, ScrollView, Text, View } from "react-native";
import { useRouter } from "expo-router";

import {
  OiCard,
  OiHeader,
  OiIcon,
  OiList,
  OiListRow,
  OiScreen,
  OiSection,
  type OiIconName,
} from "@/components/oi";
import { fonts, hexAlpha, radius, shadows } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useAuthContext } from "@/lib/auth-context";

type ModuloItem = {
  id: string;
  label: string;
  icon: OiIconName;
  to: string;
  color: "accent" | "ok" | "warn" | "danger" | "purple" | "amber" | "neutral";
  desc?: string;
};

const MODULOS: ModuloItem[] = [
  { id: "manutencao", label: "Oficina", icon: "wrench", to: "/(tabs)/manutencao", color: "warn", desc: "Manutenção de frota" },
  { id: "equipamentos", label: "Equipamentos", icon: "truck", to: "/(tabs)/equipamentos", color: "neutral", desc: "Frota e máquinas" },
  { id: "produtos", label: "Produtos", icon: "box", to: "/(tabs)/produtos", color: "accent", desc: "Catálogo & SKUs" },
  { id: "clientes", label: "Pessoas", icon: "user", to: "/(tabs)/clientes", color: "purple", desc: "Clientes, fornecedores, equipe" },
  { id: "financeiro", label: "Financeiro", icon: "dollar", to: "/(tabs)/financeiro", color: "ok", desc: "Receita & despesas" },
  { id: "relatorios", label: "Relatórios", icon: "chart", to: "/(tabs)/relatorios", color: "purple", desc: "Vendas, margem, fluxo" },
  { id: "equipe", label: "Equipe", icon: "user", to: "/equipe", color: "amber", desc: "Mecânicos + admin" },
  { id: "orcamentos", label: "Orçamentos", icon: "file", to: "/(tabs)/orcamentos", color: "amber", desc: "Propostas" },
  { id: "estoque", label: "Estoque", icon: "layers", to: "/(tabs)/estoque", color: "warn", desc: "Inventário" },
  { id: "pagamentos", label: "Pagamentos", icon: "qr", to: "/(tabs)/pagamentos", color: "accent", desc: "PIX & links" },
  { id: "fiscal", label: "Fiscal", icon: "shield", to: "/(tabs)/fiscal", color: "ok", desc: "NFe/NFSe/NFCe" },
];

const FERRAMENTAS: { id: string; label: string; icon: OiIconName; to: string }[] = [
  { id: "chat", label: "WhatsApp", icon: "whatsapp", to: "/(tabs)/chat" },
  { id: "perfis", label: "Perfis de menu", icon: "layers", to: "/perfis" },
  { id: "empresas", label: "Empresas", icon: "shield", to: "/empresas" },
];

export default function MaisScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const { user, logout } = useAuthContext();

  const colorFor = (k: ModuloItem["color"]): string => {
    switch (k) {
      case "accent": return palette.accent;
      case "ok": return palette.ok;
      case "warn": return palette.warn;
      case "danger": return palette.danger;
      case "purple": return "#8e6bd1";
      case "amber": return "#d0892b";
      default: return palette.textDim;
    }
  };

  return (
    <OiScreen edges={["top"]}>
      <OiHeader
        title="Mais"
        eyebrow="Módulos · ferramentas · conta"
        actions={[
          { icon: "bell", onPress: () => {} },
          { icon: "user", onPress: () => {} },
        ]}
      />
      <ScrollView
        contentContainerStyle={{ paddingBottom: 32 }}
        showsVerticalScrollIndicator={false}
      >
        {/* Featured: Venda rápida (PDV móvel) */}
        <OiSection>
          <Pressable
            onPress={() => router.push("/venda-rapida" as never)}
            style={({ pressed }) => ({
              flexDirection: "row",
              alignItems: "center",
              gap: 14,
              padding: 16,
              borderRadius: radius.lg,
              backgroundColor: palette.accent,
              opacity: pressed ? 0.88 : 1,
              ...shadows.pop,
            })}
          >
            <View
              style={{
                width: 52, height: 52, borderRadius: 14,
                backgroundColor: "rgba(255,255,255,0.22)",
                alignItems: "center", justifyContent: "center",
              }}
            >
              <OiIcon name="scan" size={26} color="#fff" />
            </View>
            <View style={{ flex: 1, minWidth: 0 }}>
              <Text
                style={{
                  fontSize: 10.5,
                  fontFamily: fonts.sansBold,
                  letterSpacing: 1,
                  textTransform: "uppercase",
                  color: "rgba(255,255,255,0.8)",
                }}
              >
                PDV móvel
              </Text>
              <Text
                style={{
                  fontSize: 17,
                  fontFamily: fonts.sansSemibold,
                  color: "#fff",
                  letterSpacing: -0.2,
                  marginTop: 2,
                }}
              >
                Venda rápida
              </Text>
              <Text
                style={{
                  fontSize: 12,
                  fontFamily: fonts.sans,
                  color: "rgba(255,255,255,0.85)",
                  marginTop: 2,
                }}
              >
                Cobre PIX, cartão ou boleto em segundos
              </Text>
            </View>
            <OiIcon name="chev-r" size={20} color="rgba(255,255,255,0.7)" />
          </Pressable>
        </OiSection>

        {/* Módulos */}
        <OiSection title="Módulos">
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
            {MODULOS.map((m) => {
              const c = colorFor(m.color);
              return (
                <Pressable
                  key={m.id}
                  onPress={() => router.push(m.to as never)}
                  style={({ pressed }) => ({
                    flexBasis: "48%",
                    flexGrow: 1,
                    backgroundColor: palette.surface,
                    borderColor: palette.border,
                    borderWidth: 1,
                    borderRadius: radius.md,
                    padding: 12,
                    gap: 8,
                    opacity: pressed ? 0.8 : 1,
                  })}
                >
                  <View
                    style={{
                      width: 36, height: 36, borderRadius: 10,
                      backgroundColor: hexAlpha(c, 0.18),
                      alignItems: "center", justifyContent: "center",
                    }}
                  >
                    <OiIcon name={m.icon} size={18} color={c} />
                  </View>
                  <Text
                    style={{
                      fontSize: 13.5,
                      fontFamily: fonts.sansSemibold,
                      color: palette.text,
                      letterSpacing: -0.1,
                    }}
                  >
                    {m.label}
                  </Text>
                  {m.desc ? (
                    <Text
                      style={{
                        fontSize: 11,
                        color: palette.textMute,
                        fontFamily: fonts.sans,
                      }}
                    >
                      {m.desc}
                    </Text>
                  ) : null}
                </Pressable>
              );
            })}
          </View>
        </OiSection>

        {/* Ferramentas */}
        <OiSection title="Ferramentas">
          <OiCard style={{ padding: 0 }}>
            <OiList card>
              {FERRAMENTAS.map((f, i) => (
                <OiListRow
                  key={f.id}
                  title={f.label}
                  left={
                    <View
                      style={{
                        width: 32, height: 32, borderRadius: 8,
                        backgroundColor: palette.bg2,
                        borderColor: palette.border, borderWidth: 1,
                        alignItems: "center", justifyContent: "center",
                      }}
                    >
                      <OiIcon name={f.icon} size={16} color={palette.textDim} />
                    </View>
                  }
                  right={<OiIcon name="chev-r" size={16} color={palette.textMute} />}
                  onPress={() => router.push(f.to as never)}
                  last={i === FERRAMENTAS.length - 1}
                />
              ))}
            </OiList>
          </OiCard>
        </OiSection>

        {/* Conta */}
        <OiSection title="Conta">
          <OiCard style={{ padding: 0 }}>
            <OiList card>
              <OiListRow
                title={user?.name ?? user?.email ?? "Perfil"}
                subtitle={user?.email ?? undefined}
                left={
                  <View
                    style={{
                      width: 36, height: 36, borderRadius: 18,
                      backgroundColor: palette.accentSoft,
                      alignItems: "center", justifyContent: "center",
                    }}
                  >
                    <OiIcon name="user" size={18} color={palette.accent} />
                  </View>
                }
                right={<OiIcon name="chev-r" size={16} color={palette.textMute} />}
                onPress={() => router.push("/empresas" as never)}
              />
              <OiListRow
                title="Sair da conta"
                left={
                  <View
                    style={{
                      width: 36, height: 36, borderRadius: 18,
                      backgroundColor: hexAlpha(palette.danger, 0.14),
                      alignItems: "center", justifyContent: "center",
                    }}
                  >
                    <OiIcon name="logout" size={18} color={palette.danger} />
                  </View>
                }
                onPress={logout}
                last
              />
            </OiList>
          </OiCard>
        </OiSection>

        <View style={{ alignItems: "center", paddingVertical: 16 }}>
          <Text
            style={{
              fontSize: 11,
              color: palette.textMute,
              fontFamily: fonts.mono,
            }}
          >
            Oimpresso ERP Mobile · v0.4
          </Text>
        </View>
      </ScrollView>
    </OiScreen>
  );
}
