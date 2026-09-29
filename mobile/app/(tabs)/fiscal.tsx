import React, { useMemo, useState } from "react";
import { ActivityIndicator, Linking, Pressable, ScrollView, Text, View } from "react-native";

import { OiHeader, OiScreen } from "@/components/oi";
import { Button, FormInput, ModalDialog, Select, Toast } from "@/components/erp-ui";
import { useERP } from "@/lib/erp-context";
import {
  useCompanySettings,
  useFiscalDocuments,
  useEmitFiscalDocument,
  useConsultFiscalStatus,
  useCancelFiscalDocument,
  useFiscalDownloadUrl,
  useUpsertCompanySettings,
} from "@/lib/erp-queries";

const STATUS_TABS = [
  { id: "todos" as const, label: "Todos" },
  { id: "rascunho" as const, label: "Rascunho" },
  { id: "processando" as const, label: "Processando" },
  { id: "autorizado" as const, label: "Autorizado" },
  { id: "cancelado" as const, label: "Cancelado" },
  { id: "rejeitado" as const, label: "Rejeitado" },
];

const REGIME_OPTIONS = [
  { label: "Simples Nacional", value: "simples_nacional" },
  { label: "Lucro Presumido", value: "lucro_presumido" },
  { label: "Lucro Real", value: "lucro_real" },
];

const TIPO_OPTIONS = [
  { label: "NFe (Mercadoria)", value: "NFe" },
  { label: "NFCe (Consumidor)", value: "NFCe" },
  { label: "NFSe (Serviço)", value: "NFSe" },
];

const REFERENCIA_OPTIONS = [
  { label: "Pedido", value: "pedido" },
  { label: "Ordem de Serviço", value: "os" },
  { label: "Orçamento", value: "quote" },
];

export default function FiscalScreen() {
  const { ui, addToast } = useERP();
  const [activeTab, setActiveTab] = useState<"todos" | "rascunho" | "processando" | "autorizado" | "cancelado" | "rejeitado">("todos");
  const [emitVisible, setEmitVisible] = useState(false);
  const [configVisible, setConfigVisible] = useState(false);

  const docsQuery = useFiscalDocuments(
    activeTab === "todos" ? undefined : { status: activeTab as any },
  );
  const companyQuery = useCompanySettings();
  const emitMut = useEmitFiscalDocument();
  const consultMut = useConsultFiscalStatus();
  const cancelMut = useCancelFiscalDocument();
  const upsertCompanyMut = useUpsertCompanySettings();

  // Emit form
  const [emitTipo, setEmitTipo] = useState("NFe");
  const [emitRefTipo, setEmitRefTipo] = useState("pedido");
  const [emitRefId, setEmitRefId] = useState("");

  // Company form
  const company = companyQuery.data;
  const [cnpj, setCnpj] = useState("");
  const [razao, setRazao] = useState("");
  const [regime, setRegime] = useState("simples_nacional");
  const [uf, setUf] = useState("");
  const [cidade, setCidade] = useState("");
  const [endereco, setEndereco] = useState("");

  React.useEffect(() => {
    if (company) {
      setCnpj(company.cnpj ?? "");
      setRazao(company.razaoSocial ?? "");
      setRegime(company.regimeTributario ?? "simples_nacional");
      setUf(company.uf ?? "");
      setCidade(company.cidade ?? "");
      setEndereco(company.endereco ?? "");
    }
  }, [company]);

  const docs = docsQuery.data ?? [];

  const handleEmit = async () => {
    try {
      await emitMut.mutateAsync({
        tipo: emitTipo as any,
        referenciaTipo: emitRefTipo as any,
        referenciaId: emitRefId.trim(),
      });
      addToast("sucesso", "Documento enviado para emissão");
      setEmitVisible(false);
      setEmitRefId("");
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao emitir");
    }
  };

  const handleConsult = async (id: string) => {
    try {
      await consultMut.mutateAsync({ id });
      addToast("info", "Status atualizado");
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao consultar");
    }
  };

  const handleCancel = async (id: string) => {
    try {
      await cancelMut.mutateAsync({ id, justificativa: "Cancelado pelo usuário" });
      addToast("sucesso", "Documento cancelado");
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao cancelar");
    }
  };

  const handleSaveCompany = async () => {
    try {
      await upsertCompanyMut.mutateAsync({
        cnpj: cnpj.trim() || null,
        razaoSocial: razao.trim() || null,
        regimeTributario: regime as any,
        uf: uf.trim().toUpperCase() || null,
        cidade: cidade.trim() || null,
        endereco: endereco.trim() || null,
      });
      addToast("sucesso", "Dados da empresa salvos");
      setConfigVisible(false);
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao salvar");
    }
  };

  const formatBRL = (v: number) =>
    new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(v);

  return (
    <OiScreen edges={["top"]}>
      <OiHeader
        title="Fiscal"
        eyebrow={`${docs.length} documento${docs.length === 1 ? "" : "s"}`}
        actions={[
          { icon: "settings", onPress: () => setConfigVisible(true) },
          { icon: "plus", onPress: () => setEmitVisible(true) },
        ]}
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
          {docsQuery.isLoading ? (
            <View className="py-12 items-center"><ActivityIndicator /></View>
          ) : docs.length === 0 ? (
            <View className="bg-surface rounded-lg p-6 items-center">
              <Text className="text-muted text-sm">Nenhum documento fiscal</Text>
            </View>
          ) : (
            docs.map((doc) => (
              <DocCard
                key={doc.id}
                doc={doc}
                onConsult={() => handleConsult(doc.id)}
                onCancel={() => handleCancel(doc.id)}
                addToast={addToast}
                formatBRL={formatBRL}
              />
            ))
          )}
        </View>
      </ScrollView>

      <ModalDialog
        visible={emitVisible}
        title="Emitir Documento"
        onClose={() => setEmitVisible(false)}
        onConfirm={handleEmit}
        confirmText="Emitir"
        cancelText="Cancelar"
      >
        <Select label="Tipo" options={TIPO_OPTIONS} value={emitTipo} onValueChange={setEmitTipo} required />
        <Select label="Referência" options={REFERENCIA_OPTIONS} value={emitRefTipo} onValueChange={setEmitRefTipo} required />
        <FormInput label="ID da referência (UUID)" placeholder="cole o id do pedido/OS/orçamento" value={emitRefId} onChangeText={setEmitRefId} required returnKeyType="done" />
        <Text className="text-xs text-muted mt-2">
          Provedor: Focus NFe. Quando não configurado (env), documento fica como rascunho.
        </Text>
      </ModalDialog>

      <ModalDialog
        visible={configVisible}
        title="Dados da Empresa"
        onClose={() => setConfigVisible(false)}
        onConfirm={handleSaveCompany}
        confirmText="Salvar"
        cancelText="Cancelar"
      >
        <FormInput label="Razão Social" placeholder="Empresa Ltda" value={razao} onChangeText={setRazao} returnKeyType="next" />
        <FormInput label="CNPJ" placeholder="00.000.000/0000-00" value={cnpj} onChangeText={setCnpj} keyboardType="numeric" returnKeyType="next" />
        <Select label="Regime Tributário" options={REGIME_OPTIONS} value={regime} onValueChange={setRegime} />
        <FormInput label="Cidade" placeholder="São Paulo" value={cidade} onChangeText={setCidade} returnKeyType="next" />
        <FormInput label="UF" placeholder="SP" value={uf} onChangeText={(v) => setUf(v.toUpperCase().slice(0, 2))} returnKeyType="next" />
        <FormInput label="Endereço" placeholder="Rua, número, bairro" value={endereco} onChangeText={setEndereco} returnKeyType="done" />
      </ModalDialog>
    </OiScreen>
  );
}

function DocCard({
  doc,
  onConsult,
  onCancel,
  addToast,
  formatBRL,
}: {
  doc: any;
  onConsult: () => void;
  onCancel: () => void;
  addToast: (t: any, m: string) => void;
  formatBRL: (v: number) => string;
}) {
  const downloadQuery = useFiscalDownloadUrl(undefined); // load lazily
  const statusColor = {
    rascunho: "#9CA3AF",
    processando: "#F59E0B",
    autorizado: "#22C55E",
    cancelado: "#6B7280",
    rejeitado: "#EF4444",
  }[doc.status as string] ?? "#9CA3AF";

  const handlePdf = async () => {
    if (!doc.pdfKey) {
      addToast("info", "PDF ainda não disponível");
      return;
    }
    Linking.openURL(`/manus-storage/${doc.pdfKey}`);
  };

  return (
    <View className="bg-surface rounded-lg p-3 mb-2 border border-border">
      <View className="flex-row justify-between items-start mb-2">
        <View className="flex-1">
          <Text className="font-semibold text-foreground text-sm">
            {doc.tipo} {doc.numero ? `#${doc.numero}` : ""}
          </Text>
          <Text className="text-xs text-muted mt-1">
            {doc.referenciaTipo} • {formatBRL(Number(doc.valor ?? 0))}
          </Text>
          {doc.chaveAcesso && (
            <Text className="text-[10px] text-muted mt-1" numberOfLines={1}>
              {doc.chaveAcesso}
            </Text>
          )}
          {doc.erroMensagem && (
            <Text className="text-xs text-error mt-1" numberOfLines={2}>
              {doc.erroMensagem}
            </Text>
          )}
        </View>
        <View style={{ backgroundColor: statusColor + "22" }} className="px-2 py-1 rounded">
          <Text style={{ color: statusColor }} className="text-xs font-bold">
            {doc.status}
          </Text>
        </View>
      </View>
      <View className="flex-row gap-2">
        {doc.pdfKey && (
          <Pressable onPress={handlePdf} className="flex-1 bg-primary/20 px-3 py-2 rounded-lg">
            <Text className="text-primary text-xs font-semibold text-center">📄 PDF</Text>
          </Pressable>
        )}
        {(doc.status === "processando" || doc.status === "rascunho") && (
          <Pressable onPress={onConsult} className="flex-1 bg-surface border border-border px-3 py-2 rounded-lg">
            <Text className="text-foreground text-xs font-semibold text-center">🔄 Atualizar</Text>
          </Pressable>
        )}
        {doc.status === "autorizado" && (
          <Pressable onPress={onCancel} className="flex-1 bg-error/20 px-3 py-2 rounded-lg">
            <Text className="text-error text-xs font-semibold text-center">× Cancelar</Text>
          </Pressable>
        )}
      </View>
    </View>
  );
}
