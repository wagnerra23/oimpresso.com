import React, { useMemo, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  Text,
  View,
} from "react-native";
import { router } from "expo-router";
import * as Clipboard from "expo-clipboard";

import { OiHeader, OiScreen } from "@/components/oi";
import { Toast } from "@/components/erp-ui";
import { useERP } from "@/lib/erp-context";
import {
  useAdvanceServiceOrderStatus,
  useGenerateOSLink,
  useServiceOrders,
  type ServiceOrderStatus,
} from "@/lib/erp-queries";

const STATUS_ORDER: ServiceOrderStatus[] = [
  "recepcao",
  "diagnostico",
  "orcamento",
  "aguardando_aprovacao",
  "aguardando_pecas",
  "em_execucao",
  "revisao",
  "pronto",
  "entregue",
];

const STATUS_LABELS: Record<ServiceOrderStatus, string> = {
  recepcao: "Recepção",
  diagnostico: "Diagnóstico",
  orcamento: "Orçamento",
  aguardando_aprovacao: "Aguard. Aprovação",
  aguardando_pecas: "Aguard. Peças",
  em_execucao: "Em Execução",
  revisao: "Revisão",
  pronto: "Pronto",
  entregue: "Entregue",
};

const STATUS_COLORS: Record<ServiceOrderStatus, { bg: string; text: string }> = {
  recepcao: { bg: "#E6F1FB", text: "#0C447C" },
  diagnostico: { bg: "#EEEDFE", text: "#3C3489" },
  orcamento: { bg: "#FAEEDA", text: "#633806" },
  aguardando_aprovacao: { bg: "#FCEBEB", text: "#791F1F" },
  aguardando_pecas: { bg: "#FCEBEB", text: "#791F1F" },
  em_execucao: { bg: "#FAEEDA", text: "#633806" },
  revisao: { bg: "#EEEDFE", text: "#3C3489" },
  pronto: { bg: "#EAF3DE", text: "#27500A" },
  entregue: { bg: "#EAF3DE", text: "#27500A" },
};

const brl = (n: number) =>
  new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(n);

function nextStatus(s: ServiceOrderStatus): ServiceOrderStatus | null {
  const idx = STATUS_ORDER.indexOf(s);
  if (idx < 0 || idx >= STATUS_ORDER.length - 1) return null;
  return STATUS_ORDER[idx + 1];
}

export default function OSsScreen() {
  const { ui, addToast } = useERP();
  const [statusFilter, setStatusFilter] = useState<ServiceOrderStatus | null>(null);
  const ossQuery = useServiceOrders(statusFilter ? { status: statusFilter } : undefined);
  const advanceStatus = useAdvanceServiceOrderStatus();
  const generateLink = useGenerateOSLink();

  const oss = ossQuery.data ?? [];

  const filtered = useMemo(() => oss, [oss]);

  const handleAdvance = async (id: string, current: ServiceOrderStatus) => {
    const next = nextStatus(current);
    if (!next) {
      addToast("info", "Já está no último status (Entregue)");
      return;
    }
    try {
      await advanceStatus.mutateAsync({ id, status: next });
      addToast("sucesso", `Avançado para ${STATUS_LABELS[next]}`);
    } catch (err) {
      addToast(
        "erro",
        err instanceof Error ? err.message : "Erro ao avançar status",
      );
    }
  };

  const handleCopyLink = async (id: string, numero: number) => {
    try {
      const result = await generateLink.mutateAsync({ id, expiresInDays: 30 });
      await Clipboard.setStringAsync(result.url);
      addToast("sucesso", `Link OS #${numero} copiado!`);
    } catch (err) {
      addToast(
        "erro",
        err instanceof Error ? err.message : "Erro ao gerar link",
      );
    }
  };

  return (
    <OiScreen edges={["top"]}>
      <OiHeader
        title="Ordens de Serviço"
        eyebrow={`${oss.length} no total`}
        actions={[{ icon: "plus", onPress: () => router.push("/oss/new" as never) }]}
      />
      <ScrollView className="flex-1" contentContainerStyle={{ paddingBottom: 20 }}>

        <View className="px-4 pt-4">
          {ui.toasts.map((toast) => (
            <Toast key={toast.id} tipo={toast.tipo} mensagem={toast.mensagem} />
          ))}
        </View>

        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          className="px-4 py-3"
        >
          <Pressable
            onPress={() => setStatusFilter(null)}
            className={`px-3 py-2 rounded-full mr-2 ${
              statusFilter === null ? "bg-primary" : "bg-surface border border-border"
            }`}
          >
            <Text
              className={`text-xs font-semibold ${
                statusFilter === null ? "text-white" : "text-foreground"
              }`}
            >
              Todas
            </Text>
          </Pressable>
          {STATUS_ORDER.map((s) => {
            const active = statusFilter === s;
            return (
              <Pressable
                key={s}
                onPress={() => setStatusFilter(s)}
                className={`px-3 py-2 rounded-full mr-2 ${
                  active ? "bg-primary" : "bg-surface border border-border"
                }`}
              >
                <Text
                  className={`text-xs font-semibold ${
                    active ? "text-white" : "text-foreground"
                  }`}
                >
                  {STATUS_LABELS[s]}
                </Text>
              </Pressable>
            );
          })}
        </ScrollView>

        <View className="px-4 py-2">
          {ossQuery.isLoading ? (
            <View className="py-12 items-center">
              <ActivityIndicator />
            </View>
          ) : ossQuery.isError ? (
            <View className="bg-error/10 rounded-lg p-4">
              <Text className="text-error text-sm">
                {ossQuery.error?.message ?? "Erro ao carregar OSs"}
              </Text>
            </View>
          ) : filtered.length > 0 ? (
            filtered.map((os) => {
              const pill = STATUS_COLORS[os.status as ServiceOrderStatus];
              return (
                <View
                  key={os.id}
                  className="bg-surface rounded-lg p-3 mb-2 border border-border"
                >
                  <View className="flex-row justify-between items-start">
                    <View className="flex-1">
                      <Text className="text-lg font-bold text-foreground">
                        OS #{os.numero}
                      </Text>
                      {os.customer ? (
                        <Text className="text-sm text-foreground mt-1">
                          {os.customer.nome}
                        </Text>
                      ) : null}
                      {os.vehicle ? (
                        <Text className="text-xs text-muted mt-1">
                          {os.vehicle.placa} — {os.vehicle.marca} {os.vehicle.modelo}
                        </Text>
                      ) : null}
                    </View>
                    <View className="items-end">
                      <Text className="text-base font-bold text-foreground">
                        {brl(os.valorTotal)}
                      </Text>
                      {pill ? (
                        <View
                          style={{ backgroundColor: pill.bg }}
                          className="px-2 py-1 rounded mt-1"
                        >
                          <Text
                            style={{ color: pill.text }}
                            className="text-xs font-medium"
                          >
                            {STATUS_LABELS[os.status as ServiceOrderStatus]}
                          </Text>
                        </View>
                      ) : null}
                    </View>
                  </View>
                  <View className="flex-row gap-2 mt-3 flex-wrap">
                    <Pressable
                      onPress={() => router.push(`/oss/${os.id}` as any)}
                      className="bg-primary/10 px-3 py-1.5 rounded"
                    >
                      <Text className="text-primary text-xs font-semibold">
                        Editar
                      </Text>
                    </Pressable>
                    {nextStatus(os.status as ServiceOrderStatus) ? (
                      <Pressable
                        onPress={() =>
                          handleAdvance(os.id, os.status as ServiceOrderStatus)
                        }
                        className="bg-surface border border-border px-3 py-1.5 rounded"
                      >
                        <Text className="text-foreground text-xs font-semibold">
                          Avançar status
                        </Text>
                      </Pressable>
                    ) : null}
                    <Pressable
                      onPress={() => handleCopyLink(os.id, os.numero)}
                      className="bg-surface border border-border px-3 py-1.5 rounded"
                    >
                      <Text className="text-foreground text-xs font-semibold">
                        🔗 Copiar link
                      </Text>
                    </Pressable>
                  </View>
                </View>
              );
            })
          ) : (
            <View className="bg-surface rounded-lg p-6 items-center">
              <Text className="text-muted text-sm">
                {statusFilter
                  ? `Nenhuma OS em ${STATUS_LABELS[statusFilter]}`
                  : "Nenhuma OS cadastrada"}
              </Text>
            </View>
          )}
        </View>
      </ScrollView>
    </OiScreen>
  );
}
