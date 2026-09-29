import React, { useState } from "react";
import { ActivityIndicator, Pressable, ScrollView, Text, View } from "react-native";
import * as Clipboard from "expo-clipboard";

import { OiHeader, OiScreen } from "@/components/oi";
import { FormInput, ModalDialog, Select, Toast } from "@/components/erp-ui";
import { useERP } from "@/lib/erp-context";
import {
  useCancelPayment,
  useCreatePayment,
  usePayments,
  useRefreshPaymentStatus,
} from "@/lib/erp-queries";

const STATUS_TABS = [
  { id: "todos" as const, label: "Todos" },
  { id: "pendente" as const, label: "Pendente" },
  { id: "pago" as const, label: "Pago" },
  { id: "vencido" as const, label: "Vencido" },
  { id: "cancelado" as const, label: "Cancelado" },
];

const METODO_OPTIONS = [
  { label: "Qualquer", value: "qualquer" },
  { label: "PIX", value: "pix" },
  { label: "Boleto", value: "boleto" },
  { label: "Cartão", value: "cartao" },
];

const REFERENCIA_OPTIONS = [
  { label: "Pedido", value: "pedido" },
  { label: "Ordem de Serviço", value: "os" },
  { label: "Orçamento", value: "quote" },
];

export default function PagamentosScreen() {
  const { ui, addToast } = useERP();
  const [activeTab, setActiveTab] = useState<"todos" | "pendente" | "pago" | "vencido" | "cancelado">("todos");
  const [createVisible, setCreateVisible] = useState(false);

  const paymentsQuery = usePayments(
    activeTab === "todos" ? undefined : { status: activeTab as any },
  );
  const createMut = useCreatePayment();
  const cancelMut = useCancelPayment();
  const refreshMut = useRefreshPaymentStatus();

  const [refTipo, setRefTipo] = useState("pedido");
  const [refId, setRefId] = useState("");
  const [valor, setValor] = useState("");
  const [vencimento, setVencimento] = useState("");
  const [metodo, setMetodo] = useState("qualquer");
  const [descricao, setDescricao] = useState("");

  const payments = paymentsQuery.data ?? [];

  const handleCreate = async () => {
    if (!refId.trim() || !valor || parseFloat(valor) <= 0 || !vencimento.trim()) {
      addToast("erro", "Preencha referência, valor e vencimento");
      return;
    }
    try {
      await createMut.mutateAsync({
        referenciaTipo: refTipo as any,
        referenciaId: refId.trim(),
        valor: parseFloat(valor),
        vencimento: new Date(vencimento).toISOString().slice(0, 10),
        metodoPreferido: metodo as any,
        descricao: descricao.trim() || undefined,
      });
      addToast("sucesso", "Link de pagamento gerado");
      setCreateVisible(false);
      setRefId("");
      setValor("");
      setVencimento("");
      setDescricao("");
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao gerar link");
    }
  };

  const handleCopyLink = async (url: string | null) => {
    if (!url) {
      addToast("info", "Pagamento ainda sem link público");
      return;
    }
    await Clipboard.setStringAsync(url);
    addToast("sucesso", "Link copiado!");
  };

  const handleRefresh = async (id: string) => {
    try {
      await refreshMut.mutateAsync({ id });
      addToast("info", "Status atualizado");
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao consultar");
    }
  };

  const handleCancel = async (id: string) => {
    try {
      await cancelMut.mutateAsync({ id });
      addToast("sucesso", "Pagamento cancelado");
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao cancelar");
    }
  };

  const formatBRL = (v: number) =>
    new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(v);

  const statusColor = (s: string) =>
    ({
      pendente: "#F59E0B",
      pago: "#22C55E",
      vencido: "#EF4444",
      cancelado: "#6B7280",
      estornado: "#9CA3AF",
      falhou: "#EF4444",
    }[s] ?? "#9CA3AF");

  return (
    <OiScreen edges={["top"]}>
      <OiHeader
        title="Pagamentos"
        eyebrow={`${payments.length} link${payments.length === 1 ? "" : "s"}`}
        actions={[{ icon: "plus", onPress: () => setCreateVisible(true) }]}
      />
      <ScrollView className="flex-1" contentContainerStyle={{ paddingBottom: 20 }}>

        <View className="px-4 pt-4">
          {ui.toasts.map((t) => (
            <Toast key={t.id} tipo={t.tipo} mensagem={t.mensagem} />
          ))}
        </View>

        <View className="px-4 py-3 border-b border-border">
          <ScrollView horizontal showsHorizontalScrollIndicator={false} className="flex-row gap-2">
            {STATUS_TABS.map((tab) => {
              const active = activeTab === tab.id;
              return (
                <Pressable
                  key={tab.id}
                  onPress={() => setActiveTab(tab.id)}
                  className={`px-3 py-2 rounded-lg border ${
                    active ? "bg-primary border-primary" : "bg-surface border-border"
                  }`}
                >
                  <Text className={`text-xs font-semibold ${active ? "text-white" : "text-foreground"}`}>
                    {tab.label}
                  </Text>
                </Pressable>
              );
            })}
          </ScrollView>
        </View>

        <View className="px-4 py-4">
          {paymentsQuery.isLoading ? (
            <View className="py-12 items-center"><ActivityIndicator /></View>
          ) : payments.length === 0 ? (
            <View className="bg-surface rounded-lg p-6 items-center">
              <Text className="text-muted text-sm">Nenhum pagamento</Text>
              <Text className="text-muted text-xs mt-2">Provedor: Asaas</Text>
            </View>
          ) : (
            payments.map((p) => (
              <View key={p.id} className="bg-surface rounded-lg p-3 mb-2 border border-border">
                <View className="flex-row justify-between items-start mb-2">
                  <View className="flex-1">
                    <Text className="font-semibold text-foreground text-sm">
                      {p.descricao || `${p.referenciaTipo} ${p.referenciaId.slice(0, 8)}`}
                    </Text>
                    <Text className="text-xs text-muted mt-1">
                      {formatBRL(Number(p.valor))} • venc {p.vencimento ?? "-"}
                    </Text>
                    {p.pagoEm && (
                      <Text className="text-xs text-success mt-1">
                        Pago em {new Date(p.pagoEm).toLocaleDateString("pt-BR")}
                      </Text>
                    )}
                  </View>
                  <View style={{ backgroundColor: statusColor(p.status) + "22" }} className="px-2 py-1 rounded">
                    <Text style={{ color: statusColor(p.status) }} className="text-xs font-bold">
                      {p.status}
                    </Text>
                  </View>
                </View>
                <View className="flex-row gap-2">
                  {p.paymentUrl && (
                    <Pressable onPress={() => handleCopyLink(p.paymentUrl)} className="flex-1 bg-primary/20 px-3 py-2 rounded-lg">
                      <Text className="text-primary text-xs font-semibold text-center">🔗 Copiar link</Text>
                    </Pressable>
                  )}
                  {p.status === "pendente" && (
                    <Pressable onPress={() => handleRefresh(p.id)} className="flex-1 bg-surface border border-border px-3 py-2 rounded-lg">
                      <Text className="text-foreground text-xs font-semibold text-center">🔄 Atualizar</Text>
                    </Pressable>
                  )}
                  {(p.status === "pendente" || p.status === "vencido") && (
                    <Pressable onPress={() => handleCancel(p.id)} className="flex-1 bg-error/20 px-3 py-2 rounded-lg">
                      <Text className="text-error text-xs font-semibold text-center">× Cancelar</Text>
                    </Pressable>
                  )}
                </View>
              </View>
            ))
          )}
        </View>
      </ScrollView>

      <ModalDialog
        visible={createVisible}
        title="Novo Link de Pagamento"
        onClose={() => setCreateVisible(false)}
        onConfirm={handleCreate}
        confirmText="Gerar"
        cancelText="Cancelar"
      >
        <Select label="Referência" options={REFERENCIA_OPTIONS} value={refTipo} onValueChange={setRefTipo} required />
        <FormInput label="ID da referência (UUID)" placeholder="cole o id" value={refId} onChangeText={setRefId} required returnKeyType="next" />
        <FormInput label="Valor (R$)" placeholder="0.00" value={valor} onChangeText={setValor} keyboardType="numeric" required returnKeyType="next" />
        <FormInput label="Vencimento (AAAA-MM-DD)" placeholder="2026-12-31" value={vencimento} onChangeText={setVencimento} required returnKeyType="next" />
        <Select label="Método preferido" options={METODO_OPTIONS} value={metodo} onValueChange={setMetodo} />
        <FormInput label="Descrição" placeholder="Pagamento referente a..." value={descricao} onChangeText={setDescricao} returnKeyType="done" />
        <Text className="text-xs text-muted mt-2">
          Provedor: Asaas. Quando não configurado (env), link não é gerado.
        </Text>
      </ModalDialog>
    </OiScreen>
  );
}
