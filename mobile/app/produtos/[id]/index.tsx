/**
 * Produto — tela de detalhe (read-only).
 *
 * Replica `ProdutoDetalheScreen` do design v2:
 * - Imagem grande (ou placeholder se sem foto)
 * - Preço destacado + status pills
 * - Estoque card com barra de progresso + ações Entrada/Etiqueta
 * - Especificações (def list)
 * - Últimas movimentações
 * - Lápis no header → wizard de edição
 */
import { useMemo } from "react";
import { ActivityIndicator, Image, ScrollView, Text, View } from "react-native";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";

import {
  OiBtn,
  OiCard,
  OiDetailHeader,
  OiDl,
  OiDlRow,
  OiIcon,
  OiMoney,
  OiProgress,
  OiScreen,
  OiSection,
  OiStatus,
} from "@/components/oi";
import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import {
  useProduto,
  useInventoryByProduto,
  useInventoryMovements,
} from "@/lib/erp-queries";

const ORIGEM_LABEL: Record<string, string> = {
  "0": "Nacional",
  "1": "Importado direto",
  "2": "Importado adquirido",
  "3": "Nacional com importação >40%",
  "4": "Nacional (PB)",
  "5": "Nacional com importação ≤40%",
  "6": "Importado direto sem similar",
  "7": "Importado adquirido sem similar",
  "8": "Nacional com importação >70%",
};

const TIPO_ITEM_LABEL: Record<string, string> = {
  produto: "Produto",
  servico: "Serviço",
  insumo: "Insumo / matéria-prima",
};

function formatDateRel(iso: string | null | undefined): string {
  if (!iso) return "—";
  try {
    const d = new Date(iso);
    const now = new Date();
    const diffMs = now.getTime() - d.getTime();
    const dayMs = 24 * 60 * 60 * 1000;
    if (diffMs < 0) return d.toLocaleDateString("pt-BR");
    if (diffMs < dayMs) {
      return `Hoje ${d.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}`;
    }
    if (diffMs < 2 * dayMs) {
      return `Ontem ${d.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}`;
    }
    return d.toLocaleDateString("pt-BR");
  } catch {
    return iso;
  }
}

export default function ProdutoDetalheScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const { id } = useLocalSearchParams<{ id: string }>();

  const produtoQuery = useProduto(id);
  const produto = produtoQuery.data;

  // Try to find linked inventory item for movement history
  const inventoryQuery = useInventoryByProduto(id);
  const inventoryItem = inventoryQuery.data;

  const margemColor = useMemo(() => {
    if (!produto?.margemLucroPercent) return palette.textDim;
    return produto.margemLucroPercent < 20 ? palette.danger : palette.ok;
  }, [produto, palette]);

  if (produtoQuery.isLoading || !produto) {
    return (
      <OiScreen edges={["top"]}>
        <Stack.Screen options={{ headerShown: false }} />
        <OiDetailHeader title="Carregando..." onBack={() => router.back()} />
        <View style={{ paddingVertical: 48, alignItems: "center" }}>
          <ActivityIndicator color={palette.accent} />
        </View>
      </OiScreen>
    );
  }

  const estoqueAtual = inventoryItem?.quantidade ?? produto.estoqueAtual ?? 0;
  const estoqueMinimo = inventoryItem?.estoqueMinimo ?? produto.estoqueMinimo ?? 0;
  const baixo = estoqueMinimo > 0 && estoqueAtual < estoqueMinimo;
  const proximoMin =
    !baixo && estoqueMinimo > 0 && estoqueAtual < estoqueMinimo * 1.5;
  const pctEstoque =
    estoqueMinimo > 0
      ? Math.min(1, estoqueAtual / (estoqueMinimo * 2))
      : Math.min(1, estoqueAtual / 10);

  const tipoLabel = TIPO_ITEM_LABEL[produto.tipoItem ?? "produto"] ?? "Produto";

  return (
    <OiScreen edges={["top"]}>
      <Stack.Screen options={{ headerShown: false }} />
      <OiDetailHeader
        title={produto.nome}
        eyebrow={produto.categoria}
        onBack={() => router.back()}
        right={
          <View>
            <OiBtn
              label=""
              variant="ghost"
              size="sm"
              leftIcon="edit"
              onPress={() => router.push(`/produtos/${id}/edit` as never)}
            />
          </View>
        }
      />

      <ScrollView
        contentContainerStyle={{ paddingBottom: 96 }}
        showsVerticalScrollIndicator={false}
      >
        {/* Imagem */}
        <OiSection>
          <View
            style={{
              aspectRatio: 16 / 9,
              borderRadius: radius.lg,
              backgroundColor: palette.bg2,
              borderWidth: 1,
              borderColor: palette.border,
              alignItems: "center",
              justifyContent: "center",
              overflow: "hidden",
            }}
          >
            {produto.imagemPrincipal ? (
              <Image
                source={{ uri: produto.imagemPrincipal }}
                style={{ width: "100%", height: "100%" }}
                resizeMode="cover"
              />
            ) : (
              <OiIcon name="box" size={72} color={palette.textMute} />
            )}
          </View>
        </OiSection>

        {/* Preço + status */}
        <OiSection>
          <View
            style={{ flexDirection: "row", alignItems: "baseline", gap: 8 }}
          >
            <OiMoney
              value={produto.preco}
              size={30}
              weight="semibold"
              style={{ letterSpacing: -0.6, lineHeight: 32 }}
            />
            <Text
              style={{
                fontSize: 13,
                fontFamily: fonts.sans,
                color: palette.textMute,
              }}
            >
              /{produto.unidade ?? "UN"}
            </Text>
          </View>
          {produto.precoPromo && produto.precoPromo > 0 ? (
            <Text
              style={{
                fontFamily: fonts.sansMedium,
                fontSize: 12,
                color: palette.accent,
                marginTop: 4,
              }}
            >
              Promo:{" "}
              {new Intl.NumberFormat("pt-BR", {
                style: "currency",
                currency: "BRL",
              }).format(produto.precoPromo)}
            </Text>
          ) : null}
          <View style={{ flexDirection: "row", gap: 6, marginTop: 8, flexWrap: "wrap" }}>
            <OiStatus variant="accent" label={tipoLabel} />
            {baixo ? (
              <OiStatus variant="danger" label="Estoque baixo" />
            ) : null}
            {proximoMin ? (
              <OiStatus variant="warn" label="Próximo do mínimo" />
            ) : null}
          </View>
        </OiSection>

        {/* Estoque card */}
        {produto.controlaEstoque ? (
          <OiSection title="Estoque">
            <OiCard variant="pad" style={{ gap: 10 }}>
              <View
                style={{
                  flexDirection: "row",
                  alignItems: "flex-end",
                  justifyContent: "space-between",
                }}
              >
                <View>
                  <Text
                    style={{
                      fontSize: 11,
                      fontFamily: fonts.sansBold,
                      letterSpacing: 0.8,
                      textTransform: "uppercase",
                      color: palette.textMute,
                    }}
                  >
                    Em estoque
                  </Text>
                  <View style={{ flexDirection: "row", alignItems: "baseline", gap: 4 }}>
                    <Text
                      style={{
                        fontFamily: fonts.monoSemibold,
                        fontSize: 26,
                        color: baixo ? palette.danger : palette.text,
                        letterSpacing: -0.4,
                      }}
                    >
                      {estoqueAtual.toLocaleString("pt-BR")}
                    </Text>
                    <Text
                      style={{
                        fontFamily: fonts.sans,
                        fontSize: 13,
                        color: palette.textMute,
                      }}
                    >
                      {produto.unidade ?? "UN"}
                    </Text>
                  </View>
                </View>
                <View style={{ alignItems: "flex-end" }}>
                  <Text
                    style={{
                      fontSize: 11,
                      fontFamily: fonts.sans,
                      color: palette.textMute,
                    }}
                  >
                    Mínimo
                  </Text>
                  <Text
                    style={{
                      fontFamily: fonts.monoSemibold,
                      fontSize: 15,
                      color: palette.text,
                    }}
                  >
                    {estoqueMinimo.toLocaleString("pt-BR")}
                  </Text>
                </View>
              </View>
              <OiProgress
                value={pctEstoque}
                variant={baixo ? "danger" : proximoMin ? "warn" : "ok"}
              />
              <View style={{ flexDirection: "row", gap: 8 }}>
                <OiBtn
                  label="Entrada"
                  variant="default"
                  size="sm"
                  leftIcon="plus"
                  style={{ flex: 1 }}
                  onPress={() => {
                    if (inventoryItem) {
                      router.push(`/estoque/${inventoryItem.id}` as never);
                    } else {
                      router.push("/(tabs)/estoque" as never);
                    }
                  }}
                />
                <OiBtn
                  label="Etiqueta"
                  variant="default"
                  size="sm"
                  leftIcon="printer"
                  style={{ flex: 1 }}
                  onPress={() => undefined}
                />
              </View>
            </OiCard>
          </OiSection>
        ) : null}

        {/* Especificações */}
        <OiSection title="Especificações">
          <OiCard variant="pad">
            <OiDl>
              {produto.sku ? (
                <OiDlRow label="SKU">
                  <Text style={{ fontFamily: fonts.mono, fontSize: 13, color: palette.text }}>
                    {produto.sku}
                  </Text>
                </OiDlRow>
              ) : null}
              {produto.gtin ? (
                <OiDlRow label="Cód. barras">
                  <Text style={{ fontFamily: fonts.mono, fontSize: 13, color: palette.text }}>
                    {produto.gtin}
                  </Text>
                </OiDlRow>
              ) : null}
              <OiDlRow label="Categoria">{produto.categoria}</OiDlRow>
              <OiDlRow label="Unidade">{produto.unidade ?? "UN"}</OiDlRow>
              {produto.precoCusto && produto.precoCusto > 0 ? (
                <OiDlRow label="Custo">
                  <Text style={{ fontFamily: fonts.mono, fontSize: 13, color: palette.text }}>
                    {new Intl.NumberFormat("pt-BR", {
                      style: "currency",
                      currency: "BRL",
                    }).format(produto.precoCusto)}
                  </Text>
                </OiDlRow>
              ) : null}
              {produto.margemLucroPercent ? (
                <OiDlRow label="Margem">
                  <Text
                    style={{
                      fontFamily: fonts.sansSemibold,
                      fontSize: 13,
                      color: margemColor,
                    }}
                  >
                    {produto.margemLucroPercent.toFixed(1)}%
                  </Text>
                </OiDlRow>
              ) : null}
              {produto.gramatura ? (
                <OiDlRow label="Gramatura">{produto.gramatura}</OiDlRow>
              ) : null}
              {produto.acabamento ? (
                <OiDlRow label="Acabamento">{produto.acabamento}</OiDlRow>
              ) : null}
              {produto.localizacao ? (
                <OiDlRow label="Localização">{produto.localizacao}</OiDlRow>
              ) : null}
            </OiDl>
          </OiCard>
        </OiSection>

        {/* Fiscal */}
        {produto.ncm || produto.cfop || produto.origem ? (
          <OiSection title="Fiscal">
            <OiCard variant="pad">
              <OiDl>
                {produto.ncm ? (
                  <OiDlRow label="NCM">
                    <Text style={{ fontFamily: fonts.mono, fontSize: 13, color: palette.text }}>
                      {produto.ncm}
                    </Text>
                  </OiDlRow>
                ) : null}
                {produto.cfop ? (
                  <OiDlRow label="CFOP">
                    <Text style={{ fontFamily: fonts.mono, fontSize: 13, color: palette.text }}>
                      {produto.cfop}
                    </Text>
                  </OiDlRow>
                ) : null}
                {produto.cest ? (
                  <OiDlRow label="CEST">
                    <Text style={{ fontFamily: fonts.mono, fontSize: 13, color: palette.text }}>
                      {produto.cest}
                    </Text>
                  </OiDlRow>
                ) : null}
                {produto.origem ? (
                  <OiDlRow label="Origem">
                    {`${produto.origem} · ${ORIGEM_LABEL[produto.origem] ?? "—"}`}
                  </OiDlRow>
                ) : null}
              </OiDl>
            </OiCard>
          </OiSection>
        ) : null}

        {/* Descrição */}
        {produto.descricao ? (
          <OiSection title="Descrição">
            <OiCard variant="pad">
              <Text
                style={{
                  fontFamily: fonts.sans,
                  fontSize: 13.5,
                  color: palette.text,
                  lineHeight: 20,
                }}
              >
                {produto.descricao}
              </Text>
            </OiCard>
          </OiSection>
        ) : null}

        {/* Últimas movimentações */}
        {inventoryItem ? (
          <MovimentacoesSection
            inventoryId={inventoryItem.id}
            palette={palette}
          />
        ) : null}
      </ScrollView>

      {/* Bottom bar */}
      <View
        style={{
          padding: 12,
          backgroundColor: palette.surface,
          borderTopColor: palette.border,
          borderTopWidth: 1,
        }}
      >
        <OiBtn
          label="Editar produto"
          variant="primary"
          block
          leftIcon="edit"
          onPress={() => router.push(`/produtos/${id}/edit` as never)}
        />
      </View>
    </OiScreen>
  );
}

function MovimentacoesSection({
  inventoryId,
  palette,
}: {
  inventoryId: string;
  palette: ReturnType<typeof useOiTheme>["palette"];
}) {
  const query = useInventoryMovements(inventoryId);
  const movs = (query.data ?? []).slice(0, 5);

  if (movs.length === 0) return null;

  return (
    <OiSection title="Últimas movimentações">
      <OiCard style={{ padding: 0 }}>
        {movs.map((m, i, arr) => {
          const isEntrada = m.tipo === "entrada";
          const color = isEntrada ? palette.ok : palette.danger;
          const icon = isEntrada ? "arrow-up" : "arrow-down";
          return (
            <View
              key={m.id}
              style={{
                flexDirection: "row",
                alignItems: "center",
                gap: 12,
                paddingVertical: 12,
                paddingHorizontal: 14,
                borderBottomColor: palette.border2,
                borderBottomWidth: i < arr.length - 1 ? 1 : 0,
              }}
            >
              <OiIcon name={icon as never} size={16} color={color} />
              <View style={{ flex: 1 }}>
                <Text
                  style={{
                    fontFamily: fonts.sansMedium,
                    fontSize: 13,
                    color: palette.text,
                  }}
                >
                  {m.referenciaTipo
                    ? `${isEntrada ? "Entrada" : "Saída"} · ${m.referenciaTipo}${m.referenciaId ? ` ${m.referenciaId.slice(0, 8)}` : ""}`
                    : isEntrada
                      ? "Entrada"
                      : "Saída"}
                </Text>
                <Text
                  style={{
                    fontFamily: fonts.sans,
                    fontSize: 11,
                    color: palette.textMute,
                  }}
                >
                  {formatDateRel(m.createdAt)}
                </Text>
              </View>
              <Text
                style={{
                  fontFamily: fonts.monoSemibold,
                  fontSize: 13,
                  color,
                }}
              >
                {isEntrada ? "+" : "−"}
                {Math.abs(m.quantidade).toLocaleString("pt-BR")}
              </Text>
            </View>
          );
        })}
      </OiCard>
    </OiSection>
  );
}
