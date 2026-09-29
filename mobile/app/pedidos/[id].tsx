/**
 * Pedido — detalhe com stepper interativo (5 etapas) + action bar contextual.
 *
 * Espelha `PedidoDetalheScreen` do design v3 (`screens-modules.jsx`).
 *
 * Mapeamento status do schema → etapa visual:
 *   novo       → "orc"     (aguardando aprovação)
 *   aprovado   → "prod"    (já foi aprovado, pronto pra produção)
 *   execucao   → "entrega" (em fabricação, próxima é saída)
 *   entregue   → "done"    (concluído)
 *
 * A etapa "aprov" (aprovação de arte) é exibida no stepper mas não tem
 * status correspondente nosso — visual only para indicar progressão.
 */
import { useMemo } from "react";
import { ActivityIndicator, Linking, Pressable, ScrollView, Text, View } from "react-native";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";

import {
  OiBtn,
  OiCard,
  OiDetailHeader,
  OiDl,
  OiDlRow,
  OiIcon,
  OiMoney,
  OiScreen,
  OiSection,
  OiStatus,
  OiFaturarSheet,
  useToast,
} from "@/components/oi";
import { useFinanceiro } from "@/lib/use-financeiro";
import { useState } from "react";
import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { usePedidos, useUpdatePedido, useCustomers } from "@/lib/erp-queries";
import { useERP, type Pedido } from "@/lib/erp-context";

type StepKey = "orc" | "aprov" | "prod" | "entrega" | "done";

type StepDef = {
  k: StepKey;
  label: string;
  cta: string;
  icCta: "check-circle" | "zap" | "truck" | "check";
  nextStatus: Pedido["status"];
};

const STEPS: StepDef[] = [
  { k: "orc", label: "Orçamento", cta: "Aprovar orçamento", icCta: "check-circle", nextStatus: "aprovado" },
  { k: "aprov", label: "Aprovação", cta: "Aprovar arte", icCta: "check-circle", nextStatus: "aprovado" },
  { k: "prod", label: "Produção", cta: "Liberar produção", icCta: "zap", nextStatus: "execucao" },
  { k: "entrega", label: "Entrega", cta: "Saiu para entrega", icCta: "truck", nextStatus: "entregue" },
  { k: "done", label: "Concluído", cta: "Confirmar entrega", icCta: "check", nextStatus: "entregue" },
];

function statusToStep(status: Pedido["status"]): StepKey {
  switch (status) {
    case "novo":
      return "orc";
    case "aprovado":
      return "prod";
    case "execucao":
      return "entrega";
    case "entregue":
      return "done";
  }
}

function formatDate(iso: string): string {
  try {
    return new Date(iso).toLocaleDateString("pt-BR", {
      day: "2-digit",
      month: "2-digit",
      year: "2-digit",
    });
  } catch {
    return iso;
  }
}

export default function PedidoDetalheScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const { addToast } = useERP();
  const { jaFaturado } = useFinanceiro();
  const [faturarOpen, setFaturarOpen] = useState(false);
  const params = useLocalSearchParams<{ id: string }>();
  const id = params.id ?? "";

  const pedidosQ = usePedidos();
  const customersQ = useCustomers();
  const updatePedido = useUpdatePedido();

  const pedido = useMemo(
    () => pedidosQ.data?.find((p) => p.id === id),
    [pedidosQ.data, id],
  );
  const customer = useMemo(
    () =>
      pedido
        ? customersQ.data?.find(
            (c) => c.id === (pedido as { customerId?: string | null }).customerId,
          )
        : undefined,
    [pedido, customersQ.data],
  );

  if (pedidosQ.isLoading || !pedido) {
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

  const stepKey = statusToStep(pedido.status);
  const stepIdx = STEPS.findIndex((s) => s.k === stepKey);
  const isDone = stepKey === "done";
  const currentStep = STEPS[stepIdx];

  const handleAdvance = async () => {
    if (isDone) return;
    try {
      await updatePedido.mutateAsync({
        ...pedido,
        status: currentStep.nextStatus,
      });
      addToast("sucesso", `Pedido avançado para ${currentStep.nextStatus}!`);
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao avançar");
    }
  };

  const handleReopen = async () => {
    try {
      await updatePedido.mutateAsync({ ...pedido, status: "execucao" });
      addToast("info", "Pedido reaberto");
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao reabrir");
    }
  };

  const handleWhatsapp = () => {
    const phone = customer?.telefone ?? customer?.whatsapp ?? "";
    if (!phone) {
      addToast("info", "Cliente sem telefone cadastrado");
      return;
    }
    const digits = phone.replace(/\D/g, "");
    const target = digits.startsWith("55") ? digits : `55${digits}`;
    Linking.openURL(`https://wa.me/${target}`).catch(() => undefined);
  };

  const shortId = pedido.id.slice(0, 8).toUpperCase();
  const showArtwork = stepKey === "aprov" || stepKey === "prod";

  return (
    <OiScreen edges={["top"]}>
      <Stack.Screen options={{ headerShown: false }} />
      <OiDetailHeader
        title={shortId}
        eyebrow={pedido.cliente}
        onBack={() => router.back()}
        right={
          <Pressable
            hitSlop={6}
            style={{
              width: 38,
              height: 38,
              alignItems: "center",
              justifyContent: "center",
              borderRadius: radius.sm,
            }}
          >
            <OiIcon name="dots-v" size={20} color={palette.textDim} />
          </Pressable>
        }
      />

      <ScrollView
        contentContainerStyle={{ paddingBottom: 24 }}
        showsVerticalScrollIndicator={false}
      >
        {/* Hero */}
        <OiSection>
          <OiCard variant="pad">
            <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
              <OiStatus
                label={currentStep.label}
                variant={isDone ? "ok" : stepKey === "orc" ? "accent" : "warn"}
              />
              <View style={{ flex: 1 }} />
              <Text
                style={{
                  fontFamily: fonts.mono,
                  fontSize: 11.5,
                  color: palette.textMute,
                }}
              >
                {formatDate(pedido.data)}
              </Text>
            </View>
            <Text
              style={{
                marginTop: 8,
                fontFamily: fonts.sansSemibold,
                fontSize: 15,
                color: palette.text,
                lineHeight: 20,
              }}
            >
              {pedido.produto}
            </Text>
            <OiMoney
              value={pedido.valor}
              size={28}
              weight="semibold"
              style={{ marginTop: 6 }}
            />
          </OiCard>
        </OiSection>

        {/* Stepper */}
        <OiSection title="Andamento">
          <OiCard>
            <Stepper stepIdx={stepIdx} />
          </OiCard>
        </OiSection>

        {/* Detalhes */}
        <OiSection title="Detalhes">
          <OiCard variant="pad">
            <OiDl>
              <OiDlRow label="Nº OS">
                <Text
                  style={{
                    fontFamily: fonts.mono,
                    fontSize: 13,
                    color: palette.text,
                  }}
                >
                  {shortId}
                </Text>
              </OiDlRow>
              <OiDlRow label="Cliente">{pedido.cliente}</OiDlRow>
              <OiDlRow label="Produto">{pedido.produto}</OiDlRow>
              <OiDlRow label="Valor">
                <OiMoney value={pedido.valor} size={13} weight="semibold" />
              </OiDlRow>
              <OiDlRow label="Tipo">{pedido.tipo}</OiDlRow>
              <OiDlRow label="Criado">{formatDate(pedido.data)}</OiDlRow>
            </OiDl>
          </OiCard>
        </OiSection>

        {/* Arte (placeholder visual quando faz sentido) */}
        {showArtwork ? (
          <OiSection title="Arte">
            <OiCard variant="pad">
              <View style={{ flexDirection: "row", alignItems: "center", gap: 14 }}>
                <View
                  style={{
                    width: 64,
                    height: 64,
                    borderRadius: 8,
                    backgroundColor: palette.bg2,
                    borderColor: palette.border,
                    borderWidth: 1,
                    alignItems: "center",
                    justifyContent: "center",
                  }}
                >
                  <OiIcon name="file" size={26} color={palette.textMute} />
                </View>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Text
                    style={{
                      fontFamily: fonts.sansSemibold,
                      fontSize: 13.5,
                      color: palette.text,
                    }}
                    numberOfLines={1}
                  >
                    arte_{shortId.toLowerCase()}_v3.pdf
                  </Text>
                  <Text
                    style={{
                      fontFamily: fonts.mono,
                      fontSize: 11,
                      color: palette.textMute,
                      marginTop: 2,
                    }}
                  >
                    2.4 MB · 300dpi · CMYK
                  </Text>
                </View>
                <OiBtn size="sm" label="" leftIcon="eye" />
              </View>
            </OiCard>
          </OiSection>
        ) : null}

        {/* Contato */}
        <OiSection title="Contato">
          <OiCard variant="pad" style={{ gap: 0 }}>
            <ContactRow
              icon="phone"
              label={customer?.telefone ?? "—"}
              onPress={
                customer?.telefone
                  ? () => Linking.openURL(`tel:${customer.telefone}`)
                  : undefined
              }
            />
            <ContactRow
              icon="whatsapp"
              label={customer?.whatsapp ?? customer?.telefone ?? "WhatsApp"}
              onPress={handleWhatsapp}
            />
            <ContactRow
              icon="mail"
              label={customer?.email ?? "—"}
              onPress={
                customer?.email
                  ? () => Linking.openURL(`mailto:${customer.email}`)
                  : undefined
              }
              last
            />
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
          <>
            <OiBtn label="" leftIcon="printer" />
            <View style={{ flex: 1 }}>
              {jaFaturado(pedido.id) ? (
                <OiBtn
                  label="Reabrir pedido"
                  leftIcon="refresh"
                  variant="primary"
                  block
                  onPress={handleReopen}
                />
              ) : (
                <OiBtn
                  label="Faturar"
                  leftIcon="dollar"
                  variant="action"
                  block
                  onPress={() => setFaturarOpen(true)}
                />
              )}
            </View>
          </>
        ) : (
          <>
            <OiBtn label="" leftIcon="whatsapp" onPress={handleWhatsapp} />
            <OiBtn label="" leftIcon="printer" />
            <View style={{ flex: 1 }}>
              <OiBtn
                label={currentStep.cta}
                leftIcon={currentStep.icCta}
                variant="primary"
                block
                loading={updatePedido.isPending}
                onPress={handleAdvance}
              />
            </View>
          </>
        )}
      </View>

      <OiFaturarSheet
        visible={faturarOpen}
        onClose={() => setFaturarOpen(false)}
        origemId={pedido.id}
        parte={pedido.cliente}
        desc={`Pedido ${shortId} · ${pedido.produto}`}
        valorCents={Math.round((pedido.valor ?? 0) * 100)}
        categoria="Venda de impressos"
      />
    </OiScreen>
  );
}

function Stepper({ stepIdx }: { stepIdx: number }) {
  const { palette } = useOiTheme();
  const total = STEPS.length;
  // Progress bar width as percentage of the inner span between first/last node centers.
  const progressPct = total > 1 ? (stepIdx / (total - 1)) * 100 : 0;

  return (
    <View style={{ paddingHorizontal: 4 }}>
      <View
        style={{
          flexDirection: "row",
          justifyContent: "space-between",
          position: "relative",
        }}
      >
        {/* Track */}
        <View
          style={{
            position: "absolute",
            top: 11,
            left: 11,
            right: 11,
            height: 2,
            backgroundColor: palette.border,
          }}
        />
        {/* Progress */}
        <View
          style={{
            position: "absolute",
            top: 11,
            left: 11,
            height: 2,
            width: `${progressPct}%`,
            backgroundColor: palette.accent,
          }}
        />
        {STEPS.map((s, i) => {
          const done = i < stepIdx;
          const cur = i === stepIdx;
          const filled = done || cur;
          return (
            <View
              key={s.k}
              style={{
                alignItems: "center",
                gap: 6,
                width: 60,
                zIndex: 1,
              }}
            >
              <View
                style={{
                  width: 22,
                  height: 22,
                  borderRadius: 11,
                  borderWidth: 2,
                  borderColor: filled ? palette.accent : palette.border,
                  backgroundColor: filled ? palette.accent : palette.surface,
                  alignItems: "center",
                  justifyContent: "center",
                }}
              >
                {done ? (
                  <OiIcon name="check" size={11} color="#fff" />
                ) : (
                  <Text
                    style={{
                      color: cur ? "#fff" : palette.textMute,
                      fontFamily: fonts.sansBold,
                      fontSize: 10,
                    }}
                  >
                    {i + 1}
                  </Text>
                )}
              </View>
              <Text
                style={{
                  fontSize: 10.5,
                  fontFamily: cur ? fonts.sansSemibold : fonts.sansMedium,
                  color: cur ? palette.text : palette.textMute,
                  textAlign: "center",
                }}
                numberOfLines={1}
              >
                {s.label}
              </Text>
            </View>
          );
        })}
      </View>
    </View>
  );
}

function ContactRow({
  icon,
  label,
  onPress,
  last,
}: {
  icon: "phone" | "mail" | "whatsapp";
  label: string;
  onPress?: () => void;
  last?: boolean;
}) {
  const { palette } = useOiTheme();
  return (
    <Pressable
      onPress={onPress}
      disabled={!onPress}
      style={{
        flexDirection: "row",
        alignItems: "center",
        gap: 12,
        paddingVertical: 10,
        borderBottomColor: palette.border2,
        borderBottomWidth: last ? 0 : 1,
      }}
    >
      <OiIcon name={icon} size={18} color={palette.textMute} />
      <Text
        style={{
          flex: 1,
          fontFamily: fonts.sans,
          fontSize: 13.5,
          color: palette.text,
        }}
        numberOfLines={1}
      >
        {label}
      </Text>
      {onPress ? <OiIcon name="chev-r" size={16} color={palette.textMute} /> : null}
    </Pressable>
  );
}
