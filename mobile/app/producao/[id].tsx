/**
 * OP — detalhe de job de produção com stepper de estações.
 *
 * Espelha `ProducaoJobDetalheScreen` do design v3 (`screens-clientes-producao.jsx`).
 *
 * Mapeamento status do schema → etapa visual:
 *   fila       → "Em fila"
 *   andamento  → "Imprimindo"
 *   revisao    → "Acabamento"
 *   concluido  → "Pronto"
 */
import { useMemo } from "react";
import { ActivityIndicator, Pressable, ScrollView, Text, View } from "react-native";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";

import {
  OiBtn,
  OiCard,
  OiDetailHeader,
  OiDl,
  OiDlRow,
  OiIcon,
  OiMiniPipeline,
  OiOrigin,
  OiScreen,
  OiSection,
  OiStatus,
  OiTimeline,
} from "@/components/oi";
import { buildTimeline } from "@/lib/format";
import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useERP, type OP } from "@/lib/erp-context";
import { useOps, useUpdateOP } from "@/lib/erp-queries";

const STATIONS: { status: OP["status"]; label: string }[] = [
  { status: "fila", label: "Em fila" },
  { status: "andamento", label: "Imprimindo" },
  { status: "revisao", label: "Acabamento" },
  { status: "concluido", label: "Pronto" },
];

const NEXT_STATUS: Record<OP["status"], OP["status"]> = {
  fila: "andamento",
  andamento: "revisao",
  revisao: "concluido",
  concluido: "concluido",
};

export default function ProducaoJobDetalheScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const { addToast } = useERP();
  const params = useLocalSearchParams<{ id: string }>();
  const id = params.id ?? "";

  const opsQ = useOps();
  const updateOP = useUpdateOP();

  const op = useMemo(() => opsQ.data?.find((o) => o.id === id), [opsQ.data, id]);

  if (opsQ.isLoading || !op) {
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

  const sIdx = STATIONS.findIndex((s) => s.status === op.status);
  const currentStation = STATIONS[sIdx];
  const isDone = op.status === "concluido";
  const isProgress = op.status === "andamento" || op.status === "revisao";

  const handleAdvance = async () => {
    const next = NEXT_STATUS[op.status];
    if (next === op.status) return;
    try {
      await updateOP.mutateAsync({ ...op, status: next });
      addToast(
        "sucesso",
        `OP avançada para ${STATIONS.find((s) => s.status === next)?.label ?? next}`,
      );
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao avançar OP");
    }
  };

  const handleExpedicao = () => {
    addToast("info", "Enviado para expedição (placeholder)");
    router.back();
  };

  const shortId = op.id.slice(0, 8).toUpperCase();

  return (
    <OiScreen edges={["top"]}>
      <Stack.Screen options={{ headerShown: false }} />
      <OiDetailHeader
        title={shortId}
        eyebrow={`MFG · ${op.cliente}`}
        onBack={() => router.back()}
      />

      <ScrollView
        contentContainerStyle={{ paddingBottom: 24 }}
        showsVerticalScrollIndicator={false}
      >
        {/* Hero */}
        <OiSection>
          <OiCard variant="pad">
            <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
              <OiOrigin kind="MFG" size="lg" />
              <OiStatus
                label={currentStation.label}
                variant={isDone ? "ok" : isProgress ? "accent" : "warn"}
              />
              <View style={{ flex: 1 }} />
              <Text
                style={{
                  fontFamily: fonts.mono,
                  fontSize: 12,
                  color: palette.textMute,
                }}
              >
                #{shortId.slice(0, 4)}
              </Text>
            </View>
            <Text
              style={{
                marginTop: 8,
                fontFamily: fonts.sansSemibold,
                fontSize: 16,
                color: palette.text,
                lineHeight: 22,
              }}
            >
              {op.produto}
            </Text>
            <Text
              style={{
                fontFamily: fonts.sans,
                fontSize: 12.5,
                color: palette.textDim,
                marginTop: 2,
              }}
            >
              {op.cliente} · pedido {op.pedidoId.slice(0, 6).toUpperCase()}
            </Text>
          </OiCard>
        </OiSection>

        {/* MiniPipeline (Bloco 5.3) + Timeline vertical (Bloco 5.6) */}
        <OiSection title="Etapas e histórico">
          <OiCard variant="pad" style={{ gap: 14 }}>
            <OiMiniPipeline total={STATIONS.length} currentIndex={sIdx} />
            <OiTimeline
              events={buildTimeline({
                pipeline: STATIONS.map((s) => s.label),
                currentStage: currentStation.label,
                openedAt: new Date(Date.now() - 5 * 3600_000).toISOString(),
                operatorPerStage: {
                  Imprimindo: "André",
                  Acabamento: "Bruno",
                },
                spacingMinutes: 60,
              })}
            />
          </OiCard>
        </OiSection>

        {/* Detalhes */}
        <OiSection title="Detalhes">
          <OiCard variant="pad">
            <OiDl>
              <OiDlRow label="Estação">Geral</OiDlRow>
              <OiDlRow label="Operador">—</OiDlRow>
              <OiDlRow label="Prioridade">Normal</OiDlRow>
              <OiDlRow label="OS vinculada">
                <Text
                  style={{
                    fontFamily: fonts.mono,
                    fontSize: 13,
                    color: palette.text,
                  }}
                >
                  {op.pedidoId.slice(0, 8).toUpperCase()}
                </Text>
              </OiDlRow>
            </OiDl>
          </OiCard>
        </OiSection>

        <View style={{ height: 16 }} />
      </ScrollView>

      {/* Action bar */}
      <View
        style={{
          paddingHorizontal: 12,
          paddingVertical: 10,
          backgroundColor: palette.surface,
          borderTopColor: palette.border,
          borderTopWidth: 1,
          flexDirection: "row",
          gap: 8,
        }}
      >
        {isDone ? (
          <View style={{ flex: 1 }}>
            <OiBtn
              label="Enviar para expedição"
              variant="primary"
              leftIcon="truck"
              block
              onPress={handleExpedicao}
            />
          </View>
        ) : isProgress ? (
          <>
            <Pressable
              hitSlop={6}
              style={{
                width: 44,
                height: 44,
                alignItems: "center",
                justifyContent: "center",
                borderRadius: radius.md,
                borderWidth: 1,
                borderColor: palette.border,
                backgroundColor: palette.surface,
              }}
            >
              <OiIcon name="alert" size={18} color={palette.warn} />
            </Pressable>
            <View style={{ flex: 1 }}>
              <OiBtn
                label={`Concluir ${currentStation.label}`}
                variant="primary"
                leftIcon="check-circle"
                block
                loading={updateOP.isPending}
                onPress={handleAdvance}
              />
            </View>
          </>
        ) : (
          <>
            <Pressable
              hitSlop={6}
              style={{
                width: 44,
                height: 44,
                alignItems: "center",
                justifyContent: "center",
                borderRadius: radius.md,
                borderWidth: 1,
                borderColor: palette.border,
                backgroundColor: palette.surface,
              }}
            >
              <OiIcon name="user" size={18} color={palette.textDim} />
            </Pressable>
            <View style={{ flex: 1 }}>
              <OiBtn
                label={`Iniciar ${STATIONS[sIdx + 1]?.label ?? "etapa"}`}
                variant="primary"
                leftIcon="zap"
                block
                loading={updateOP.isPending}
                onPress={handleAdvance}
              />
            </View>
          </>
        )}
      </View>
    </OiScreen>
  );
}
