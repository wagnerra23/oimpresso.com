/**
 * Home/Dashboard — Oimpresso design.
 *
 * Real KPI data via reports router (DRE + estoque). Falls back to placeholders
 * while loading so the screen never goes blank.
 */
import { useMemo } from "react";
import { Pressable, ScrollView, Text, View } from "react-native";
import { useRouter } from "expo-router";
import { LinearGradient } from "expo-linear-gradient";

import {
  OiBtn,
  OiCard,
  OiIcon,
  OiKpi,
  OiKpis,
  OiMoney,
  OiScreen,
  OiSection,
} from "@/components/oi";
import { OiHeader } from "@/components/oi";
import { shadows } from "@/lib/oi-theme";
import { formatBRLcompact, formatBRL } from "@/lib/money";
import { CompanySwitcher } from "@/components/company-switcher";
import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useAuthContext } from "@/lib/auth-context";
import {
  useDre,
  useLowStockAlert,
  useReportProducao,
  useReportVendas,
} from "@/lib/erp-queries";

function greeting(): string {
  const h = new Date().getHours();
  if (h < 12) return "Bom dia";
  if (h < 18) return "Boa tarde";
  return "Boa noite";
}

function todayLabel(): string {
  return new Date().toLocaleDateString("pt-BR", {
    day: "2-digit",
    month: "short",
  });
}

export default function HomeScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const { user, logout } = useAuthContext();

  // Last 30 days window for the dashboard KPIs
  const range = useMemo(() => {
    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - 30);
    return { from: from.toISOString(), to: to.toISOString() };
  }, []);

  const dre = useDre(range);
  const vendas = useReportVendas({ ...range, groupBy: "dia" });
  const producao = useReportProducao(range);
  const lowStock = useLowStockAlert();

  const firstName =
    (user?.name ?? user?.email ?? "").split(" ")[0] ?? "usuário";

  const totalReceitas = dre.data?.totalReceitas ?? 0;
  const meta = Math.max(totalReceitas * 1.2, 1000); // mock meta: 120% do realizado
  const pctMeta = Math.min(1, totalReceitas / meta);

  const lowStockCount = lowStock.data?.length ?? 0;
  const pedidosAbertos =
    (vendas.data?.countPedidos?.novo ?? 0) +
    (vendas.data?.countPedidos?.aprovado ?? 0) +
    (vendas.data?.countPedidos?.execucao ?? 0);

  const opsAndamento =
    (producao.data?.countOPsPorStatus?.fila ?? 0) +
    (producao.data?.countOPsPorStatus?.andamento ?? 0) +
    (producao.data?.countOPsPorStatus?.revisao ?? 0);

  return (
    <OiScreen edges={["top"]} contentStyle={{ paddingBottom: 0 }}>
      <OiHeader
        eyebrow={`Início · Hoje, ${todayLabel()}`}
        title={`${greeting()}, ${firstName}`}
        actions={[
          { icon: "bell", onPress: () => {} },
          { icon: "user", onPress: () => {} },
        ]}
      />
      <ScrollView
        contentContainerStyle={{ paddingBottom: 24 }}
        showsVerticalScrollIndicator={false}
      >
        <OiSection>
          <View style={{ marginBottom: 12 }}>
            <CompanySwitcher />
          </View>

          {/* Faturamento hoje — KPI hero (gradiente magenta→roxo, modelo aprovado) */}
          <LinearGradient
            colors={[palette.brand.deep, palette.accent, palette.accent2]}
            locations={[0, 0.7, 1]}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 1 }}
            style={{
              borderRadius: radius.lg,
              padding: 18,
              overflow: "hidden",
              ...shadows.pop,
            }}
          >
            {/* Marca d'água */}
            <View
              style={{ position: "absolute", right: -10, bottom: -16, opacity: 0.1 }}
              pointerEvents="none"
            >
              <OiIcon name="box" size={132} color="#fff" />
            </View>
            <View style={{ flexDirection: "row", alignItems: "baseline", gap: 8 }}>
              <Text
                style={{
                  fontSize: 10.5,
                  fontFamily: fonts.sansBold,
                  letterSpacing: 1,
                  textTransform: "uppercase",
                  color: "#fff",
                  opacity: 0.85,
                }}
              >
                Faturamento hoje
              </Text>
              <Text
                style={{
                  marginLeft: "auto",
                  fontSize: 11,
                  color: "#fff",
                  opacity: 0.85,
                  fontFamily: fonts.sans,
                }}
              >
                meta {formatBRLcompact(Math.round(meta * 100))}
              </Text>
            </View>
            <Text
              style={{
                fontFamily: fonts.monoSemibold,
                fontSize: 32,
                color: "#fff",
                letterSpacing: -0.6,
                lineHeight: 36,
                marginTop: 2,
              }}
            >
              {formatBRL(Math.round(totalReceitas * 100))}
            </Text>
            {/* Progress branco sobre trilho translúcido */}
            <View
              style={{
                height: 6,
                borderRadius: 99,
                backgroundColor: "rgba(255,255,255,0.25)",
                overflow: "hidden",
                marginTop: 10,
                marginBottom: 8,
              }}
            >
              <View
                style={{
                  width: `${Math.round(pctMeta * 100)}%`,
                  height: "100%",
                  backgroundColor: "#fff",
                }}
              />
            </View>
            <View style={{ flexDirection: "row", alignItems: "center" }}>
              <Text style={{ fontSize: 11.5, color: "#fff", opacity: 0.92, fontFamily: fonts.sans }}>
                {(pctMeta * 100).toFixed(0)}% da meta
              </Text>
              <View
                style={{
                  marginLeft: "auto",
                  flexDirection: "row",
                  alignItems: "center",
                  gap: 4,
                }}
              >
                <OiIcon name="trending-up" size={12} color="#fff" />
                <Text style={{ fontSize: 11.5, color: "#fff", fontFamily: fonts.sansSemibold }}>
                  +{(dre.data?.margemPercent ?? 0).toFixed(0)}% margem
                </Text>
              </View>
            </View>
          </LinearGradient>
        </OiSection>

        {/* KPIs */}
        <OiSection>
          <OiKpis>
            <OiKpi
              label="Pedidos abertos"
              value={String(pedidosAbertos)}
              trend={pedidosAbertos > 0 ? `+${pedidosAbertos}` : undefined}
            />
            <OiKpi
              label="OPs em curso"
              value={String(opsAndamento)}
            />
            <OiKpi
              label="Estoque baixo"
              value={String(lowStockCount)}
              variant={lowStockCount > 0 ? "warn" : "default"}
            />
          </OiKpis>
        </OiSection>

        {/* Atalhos */}
        <OiSection title="Atalhos">
          <View
            style={{
              flexDirection: "row",
              flexWrap: "wrap",
              gap: 8,
            }}
          >
            {(
              [
                { ic: "+", label: "Novo pedido", to: "/(tabs)/vendas" },
                { ic: "OS", label: "Nova OS", to: "/oss/new" },
                { ic: "$", label: "Pagamento", to: "/(tabs)/pagamentos" },
                { ic: "R$", label: "Financeiro", to: "/(tabs)/financeiro" },
              ] as const
            ).map((a, i) => (
              <Pressable
                key={i}
                onPress={() => router.push(a.to as never)}
                style={({ pressed }) => ({
                  flex: 1,
                  minWidth: "22%",
                  backgroundColor: palette.surface,
                  borderColor: palette.border,
                  borderWidth: 1,
                  borderRadius: radius.md,
                  paddingVertical: 12,
                  paddingHorizontal: 6,
                  alignItems: "center",
                  gap: 6,
                  opacity: pressed ? 0.8 : 1,
                })}
              >
                <View
                  style={{
                    width: 36,
                    height: 36,
                    borderRadius: 10,
                    backgroundColor: palette.accentSoft,
                    alignItems: "center",
                    justifyContent: "center",
                  }}
                >
                  <Text
                    style={{
                      color: palette.accent,
                      fontFamily: fonts.monoSemibold,
                      fontSize: 13,
                    }}
                  >
                    {a.ic}
                  </Text>
                </View>
                <Text
                  style={{
                    fontSize: 11,
                    color: palette.textDim,
                    fontFamily: fonts.sansMedium,
                    textAlign: "center",
                  }}
                >
                  {a.label}
                </Text>
              </Pressable>
            ))}
          </View>
        </OiSection>

        {/* Financeiro mini-cards */}
        <OiSection
          title="Financeiro"
          more={{
            label: "Detalhes",
            onPress: () => router.push("/(tabs)/financeiro" as never),
          }}
        >
          <View style={{ flexDirection: "row", gap: 8 }}>
            <OiCard variant="tight" style={{ flex: 1 }}>
              <Text
                style={{
                  fontSize: 10,
                  fontFamily: fonts.sansBold,
                  letterSpacing: 0.6,
                  textTransform: "uppercase",
                  color: palette.textMute,
                }}
              >
                Receitas
              </Text>
              <OiMoney
                value={dre.data?.totalReceitas ?? 0}
                size={17}
                weight="semibold"
                color={palette.ok}
              />
              <Text
                style={{
                  fontSize: 11,
                  color: palette.textDim,
                  fontFamily: fonts.sans,
                }}
              >
                30 dias
              </Text>
            </OiCard>
            <OiCard variant="tight" style={{ flex: 1 }}>
              <Text
                style={{
                  fontSize: 10,
                  fontFamily: fonts.sansBold,
                  letterSpacing: 0.6,
                  textTransform: "uppercase",
                  color: palette.textMute,
                }}
              >
                Despesas
              </Text>
              <OiMoney
                value={dre.data?.totalDespesas ?? 0}
                size={17}
                weight="semibold"
                color={palette.danger}
              />
              <Text
                style={{
                  fontSize: 11,
                  color: palette.textDim,
                  fontFamily: fonts.sans,
                }}
              >
                30 dias
              </Text>
            </OiCard>
          </View>
        </OiSection>

        {/* Logout */}
        <OiSection>
          <OiBtn variant="ghost" block onPress={logout} label="Sair da conta" />
        </OiSection>
      </ScrollView>
    </OiScreen>
  );
}
