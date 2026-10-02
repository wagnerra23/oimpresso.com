import React, { useEffect, useMemo, useState } from "react";
import { ActivityIndicator, Image, Pressable, ScrollView, Text, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import * as Clipboard from "expo-clipboard";
import * as ImagePicker from "expo-image-picker";

import {
  OiBtn, OiBtnRow, OiCard, OiDetailHeader, OiIcon, OiMoney, OiPlaca, OiScreen, OiSection, OiStatus,
} from "@/components/oi";
import { OiFormInput, OiSelectField } from "@/components/oi/OiForm";
import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useNotify } from "@/lib/notify";
import { OS_STATUS } from "@/lib/os-status";
import {
  useAddOSPhoto, useCreateServiceOrder, useCustomers, useGenerateOSLink, useRemoveOSPhoto,
  useServiceOrder, useSuggestDiagnosis, useUpdateServiceOrder, useVehicles, type ServiceOrderStatus,
} from "@/lib/erp-queries";

type DiagnosisSuggestion = {
  diagnosticoSugerido: string;
  pecasProvaveis: string[];
  servicosProvaveis: string[];
  tempoEstimadoHoras: number;
  confianca: "baixa" | "media" | "alta";
};

type ItemTipo = "peca" | "servico";

interface ItemForm {
  tipo: ItemTipo;
  codigo: string;
  descricao: string;
  quantidade: string;
  valorUnit: string;
  fornecedor: string;
  tempoEstimadoHoras: string;
  mecanicoResponsavel: string;
}

function emptyItem(tipo: ItemTipo): ItemForm {
  return { tipo, codigo: "", descricao: "", quantidade: "1", valorUnit: "0", fornecedor: "", tempoEstimadoHoras: "", mecanicoResponsavel: "" };
}

function parseNumber(v: string): number {
  const n = parseFloat(v.replace(",", "."));
  return Number.isFinite(n) ? n : 0;
}

const CONF = {
  alta: { label: "Alta confiança", variant: "ok" as const },
  media: { label: "Média confiança", variant: "warn" as const },
  baixa: { label: "Baixa confiança", variant: "danger" as const },
};

export default function OSDetailScreen() {
  const { id: rawId } = useLocalSearchParams<{ id: string }>();
  const isNew = rawId === "new" || !rawId;
  const id = isNew ? undefined : rawId;

  const { palette } = useOiTheme();
  const notify = useNotify();
  const osQuery = useServiceOrder(id);
  const customersQuery = useCustomers();
  const vehiclesQuery = useVehicles();
  const createOS = useCreateServiceOrder();
  const updateOS = useUpdateServiceOrder();
  const addPhoto = useAddOSPhoto();
  const removePhoto = useRemoveOSPhoto();
  const generateLink = useGenerateOSLink();
  const suggestDiagnosis = useSuggestDiagnosis();
  const [aiSuggestion, setAiSuggestion] = useState<DiagnosisSuggestion | null>(null);

  const customers = customersQuery.data ?? [];
  const vehicles = vehiclesQuery.data ?? [];

  const [customerId, setCustomerId] = useState("");
  const [vehicleId, setVehicleId] = useState("");
  const [kmEntrada, setKmEntrada] = useState("");
  const [queixaCliente, setQueixaCliente] = useState("");
  const [diagnostico, setDiagnostico] = useState("");
  const [items, setItems] = useState<ItemForm[]>([]);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    if (isNew) { setLoaded(true); return; }
    if (!osQuery.data) return;
    const os = osQuery.data;
    setCustomerId(os.customerId);
    setVehicleId(os.vehicleId);
    setKmEntrada(os.kmEntrada != null ? String(os.kmEntrada) : "");
    setQueixaCliente(os.queixaCliente ?? "");
    setDiagnostico(os.diagnostico ?? "");
    setItems(os.items.map((i) => ({
      tipo: i.tipo,
      codigo: i.codigo ?? "",
      descricao: i.descricao,
      quantidade: String(i.quantidade),
      valorUnit: String(i.valorUnit),
      fornecedor: i.fornecedor ?? "",
      tempoEstimadoHoras: i.tempoEstimadoHoras != null ? String(i.tempoEstimadoHoras) : "",
      mecanicoResponsavel: i.mecanicoResponsavel ?? "",
    })));
    setLoaded(true);
  }, [osQuery.data, isNew]);

  const customerOptions = useMemo(() => customers.map((c) => ({ label: c.nome, value: c.id })), [customers]);
  const vehicleOptions = useMemo(() => {
    const filtered = customerId ? vehicles.filter((v) => v.customerId === customerId) : vehicles;
    return filtered.map((v) => ({ label: `${v.placa} — ${v.marca} ${v.modelo}`, value: v.id, hint: v.ano ? String(v.ano) : undefined }));
  }, [vehicles, customerId]);

  const totals = useMemo(() => {
    let pecas = 0, mo = 0;
    for (const it of items) {
      const total = parseNumber(it.quantidade) * parseNumber(it.valorUnit);
      if (it.tipo === "peca") pecas += total; else mo += total;
    }
    return { pecas, mo, total: pecas + mo };
  }, [items]);

  const updateItem = <K extends keyof ItemForm>(index: number, key: K, value: ItemForm[K]) =>
    setItems((prev) => prev.map((it, i) => (i === index ? { ...it, [key]: value } : it)));
  const removeItemAt = (index: number) => setItems((prev) => prev.filter((_, i) => i !== index));

  const selectedVehicle = useMemo(() => vehicles.find((v) => v.id === vehicleId), [vehicles, vehicleId]);

  const handleSuggestDiagnosis = async () => {
    if (queixaCliente.trim().length < 5) return notify("erro", "Descreva a queixa com pelo menos 5 caracteres");
    try {
      const result = await suggestDiagnosis.mutateAsync({
        queixaCliente: queixaCliente.trim(),
        vehicleMarca: selectedVehicle?.marca ?? undefined,
        vehicleModelo: selectedVehicle?.modelo ?? undefined,
        vehicleAno: selectedVehicle?.ano ?? undefined,
        kmAtual: kmEntrada.trim() ? parseInt(kmEntrada, 10) : undefined,
      });
      setAiSuggestion(result as DiagnosisSuggestion);
    } catch (err) {
      notify("erro", err instanceof Error ? err.message : "Erro ao consultar IA");
    }
  };

  const applyAiDiagnostico = () => {
    if (!aiSuggestion) return;
    setDiagnostico(aiSuggestion.diagnosticoSugerido);
    notify("sucesso", "Diagnóstico aplicado");
  };

  const applyAiItems = () => {
    if (!aiSuggestion) return;
    const novos: ItemForm[] = [
      ...aiSuggestion.servicosProvaveis.map((s) => ({ ...emptyItem("servico"), descricao: s })),
      ...aiSuggestion.pecasProvaveis.map((p) => ({ ...emptyItem("peca"), descricao: p })),
    ];
    if (aiSuggestion.tempoEstimadoHoras && novos.length > 0) {
      const firstServ = novos.find((n) => n.tipo === "servico");
      if (firstServ) firstServ.tempoEstimadoHoras = String(aiSuggestion.tempoEstimadoHoras);
    }
    setItems((prev) => [...prev, ...novos]);
    notify("sucesso", `${novos.length} ${novos.length === 1 ? "item adicionado" : "itens adicionados"}`);
  };

  const handleSave = async () => {
    if (!customerId) return notify("erro", "Selecione um cliente");
    if (!vehicleId) return notify("erro", "Selecione um veículo");
    const payloadItems = items.filter((it) => it.descricao.trim().length > 0).map((it) => ({
      tipo: it.tipo,
      codigo: it.codigo.trim() || undefined,
      descricao: it.descricao.trim(),
      quantidade: parseNumber(it.quantidade) || 1,
      valorUnit: parseNumber(it.valorUnit) || 0,
      fornecedor: it.fornecedor.trim() || undefined,
      tempoEstimadoHoras: it.tempoEstimadoHoras.trim() ? parseNumber(it.tempoEstimadoHoras) : undefined,
      mecanicoResponsavel: it.mecanicoResponsavel.trim() || undefined,
    }));
    try {
      if (isNew) {
        const created = await createOS.mutateAsync({
          customerId, vehicleId,
          kmEntrada: kmEntrada.trim() ? parseInt(kmEntrada, 10) : undefined,
          queixaCliente: queixaCliente.trim() || undefined,
          items: payloadItems,
        });
        notify("sucesso", `OS #${created.numero} criada`);
        router.replace(`/oss/${created.id}` as any);
      } else if (id) {
        await updateOS.mutateAsync({
          id, customerId, vehicleId,
          kmEntrada: kmEntrada.trim() ? parseInt(kmEntrada, 10) : null,
          queixaCliente: queixaCliente.trim() || null,
          diagnostico: diagnostico.trim() || null,
          items: payloadItems,
        });
        notify("sucesso", "OS salva");
      }
    } catch (err) {
      notify("erro", err instanceof Error ? err.message : "Erro ao salvar OS");
    }
  };

  const handlePickPhotos = async () => {
    if (isNew || !id) return notify("info", "Salve a OS primeiro para anexar fotos");
    try {
      const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!perm.granted) return notify("erro", "Permissão para galeria negada");
      const result = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ImagePicker.MediaTypeOptions.Images, allowsMultipleSelection: true, quality: 0.7 });
      if (result.canceled || result.assets.length === 0) return;
      for (const asset of result.assets) {
        await addPhoto.mutateAsync({ serviceOrderId: id, fileKey: asset.uri, tipo: "entrada", descricao: asset.fileName ?? null });
      }
      notify("sucesso", `${result.assets.length} foto(s) adicionada(s)`);
    } catch (err) {
      notify("erro", err instanceof Error ? err.message : "Erro na seleção");
    }
  };

  const handleRemovePhoto = async (photoId: string) => {
    try {
      await removePhoto.mutateAsync({ photoId });
      notify("sucesso", "Foto removida");
    } catch (err) {
      notify("erro", err instanceof Error ? err.message : "Erro ao remover foto");
    }
  };

  const handleCopyLink = async () => {
    if (isNew || !id) return notify("info", "Salve a OS primeiro para gerar link");
    try {
      const result = await generateLink.mutateAsync({ id, expiresInDays: 30 });
      await Clipboard.setStringAsync(result.url);
      notify("sucesso", "Link de aprovação copiado");
    } catch (err) {
      notify("erro", err instanceof Error ? err.message : "Erro ao gerar link");
    }
  };

  if (!loaded && !isNew) {
    return (
      <OiScreen>
        <OiDetailHeader title="OS" onBack={() => router.back()} />
        <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}><ActivityIndicator color={palette.accent} /></View>
      </OiScreen>
    );
  }

  const photos = osQuery.data?.photos ?? [];
  const st = osQuery.data ? OS_STATUS[osQuery.data.status as ServiceOrderStatus] : null;
  const saving = createOS.isPending || updateOS.isPending;

  const renderItems = (tipo: ItemTipo) =>
    items.map((it, idx) => ({ it, idx })).filter(({ it }) => it.tipo === tipo).map(({ it, idx }) => (
      <ItemRow key={`${tipo}-${idx}`} item={it} onChange={(k, v) => updateItem(idx, k, v)} onRemove={() => removeItemAt(idx)} />
    ));

  return (
    <OiScreen>
      <OiDetailHeader
        eyebrow={isNew ? "Recepção" : selectedVehicle ? `${selectedVehicle.marca} ${selectedVehicle.modelo}` : undefined}
        title={isNew ? "Nova OS" : `OS #${osQuery.data?.numero ?? ""}`}
        onBack={() => router.back()}
        right={st ? <OiStatus label={st.label} variant={st.tone} /> : null}
      />
      <ScrollView style={{ flex: 1 }} contentContainerStyle={{ paddingBottom: 24 }}>
        {selectedVehicle ? (
          <View style={{ paddingHorizontal: 16, paddingTop: 14, flexDirection: "row", alignItems: "center", gap: 10 }}>
            <OiPlaca text={selectedVehicle.placa} />
            <Text style={{ flex: 1, fontFamily: fonts.sans, fontSize: 13, color: palette.textDim }}>
              {kmEntrada ? `${parseInt(kmEntrada, 10).toLocaleString("pt-BR")} km na entrada` : "KM de entrada não informado"}
            </Text>
          </View>
        ) : null}

        <OiSection title="Veículo">
          <OiCard>
            <OiSelectField label="Cliente" required options={customerOptions} value={customerId} onValueChange={(v) => { setCustomerId(v); setVehicleId(""); }} />
            <OiSelectField label="Veículo" required options={vehicleOptions} value={vehicleId} onValueChange={setVehicleId} placeholder={customerId ? "Selecionar veículo" : "Escolha o cliente primeiro"} />
            <OiFormInput label="KM de entrada" mono placeholder="0" value={kmEntrada} onChangeText={setKmEntrada} keyboardType="numeric" />
          </OiCard>
        </OiSection>

        <OiSection title="Diagnóstico">
          <OiCard>
            <OiFormInput label="Queixa do cliente" multiline placeholder="O que o cliente relatou…" value={queixaCliente} onChangeText={setQueixaCliente} />
            <OiBtn variant="default" leftIcon="zap" label={suggestDiagnosis.isPending ? "Consultando IA…" : "Sugerir diagnóstico com IA"} loading={suggestDiagnosis.isPending} onPress={handleSuggestDiagnosis} />
            {aiSuggestion ? (
              <AiSuggestionCard suggestion={aiSuggestion} onApplyDiagnostico={applyAiDiagnostico} onApplyItems={applyAiItems} onDismiss={() => setAiSuggestion(null)} />
            ) : null}
            <OiFormInput label="Diagnóstico técnico" multiline placeholder="Avaliação do mecânico…" value={diagnostico} onChangeText={setDiagnostico} />
          </OiCard>
        </OiSection>

        <OiSection title="Serviços (mão de obra)">
          <View style={{ gap: 8 }}>
            {renderItems("servico")}
            <OiBtn leftIcon="plus" label="Adicionar serviço" onPress={() => setItems((p) => [...p, emptyItem("servico")])} />
          </View>
        </OiSection>

        <OiSection title="Peças">
          <View style={{ gap: 8 }}>
            {renderItems("peca")}
            <OiBtn leftIcon="plus" label="Adicionar peça" onPress={() => setItems((p) => [...p, emptyItem("peca")])} />
          </View>
        </OiSection>

        <OiSection title="Totais">
          <OiCard variant="tight">
            <TotalRow label="Peças" value={totals.pecas} />
            <TotalRow label="Mão de obra" value={totals.mo} />
            <View style={{ height: 1, backgroundColor: palette.border2, marginVertical: 2 }} />
            <TotalRow label="Total" value={totals.total} strong />
          </OiCard>
        </OiSection>

        {!isNew ? (
          <OiSection title={`Fotos de entrada · ${photos.length}`}>
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8, marginBottom: 8 }}>
              {photos.map((p) => (
                <View key={p.id}>
                  <Image source={{ uri: p.fileKey }} style={{ width: 80, height: 80, borderRadius: radius.md, borderWidth: 1, borderColor: palette.border }} />
                  <Pressable onPress={() => handleRemovePhoto(p.id)} hitSlop={8}
                    style={{ position: "absolute", top: 4, right: 4, width: 24, height: 24, borderRadius: 12, backgroundColor: palette.surface, borderWidth: 1, borderColor: palette.border, alignItems: "center", justifyContent: "center" }}>
                    <OiIcon name="x" size={14} color={palette.danger} />
                  </Pressable>
                </View>
              ))}
            </View>
            <OiBtn leftIcon="image" label="Adicionar fotos" onPress={handlePickPhotos} />
          </OiSection>
        ) : null}

        {!isNew ? (
          <OiSection title="Aprovação do cliente">
            <OiBtn leftIcon="paperclip" label="Copiar link de aprovação" onPress={handleCopyLink} />
          </OiSection>
        ) : null}
      </ScrollView>

      <View style={{ paddingHorizontal: 16, paddingTop: 12, paddingBottom: 12, borderTopWidth: 1, borderTopColor: palette.border, backgroundColor: palette.surface }}>
        <OiBtnRow>
          <OiBtn label="Voltar" onPress={() => router.back()} />
          <OiBtn variant="primary" label={saving ? "Salvando…" : "Salvar OS"} loading={saving} onPress={handleSave} />
        </OiBtnRow>
      </View>
    </OiScreen>
  );
}

function TotalRow({ label, value, strong }: { label: string; value: number; strong?: boolean }) {
  const { palette } = useOiTheme();
  return (
    <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between", minHeight: 28 }}>
      <Text style={{ fontFamily: strong ? fonts.sansSemibold : fonts.sans, fontSize: strong ? 15 : 13.5, color: strong ? palette.text : palette.textDim }}>{label}</Text>
      <OiMoney value={value} size={strong ? 17 : 14} weight={strong ? "semibold" : "medium"} />
    </View>
  );
}

function ItemRow({ item, onChange, onRemove }: {
  item: ItemForm;
  onChange: <K extends keyof ItemForm>(key: K, value: ItemForm[K]) => void;
  onRemove: () => void;
}) {
  const { palette } = useOiTheme();
  const isPeca = item.tipo === "peca";
  const subtotal = parseNumber(item.quantidade) * parseNumber(item.valorUnit);
  return (
    <OiCard variant="tight">
      <View style={{ flexDirection: "row", alignItems: "center", gap: 8, marginBottom: 4 }}>
        <Text style={{ fontFamily: fonts.monoSemibold, fontSize: 10.5, color: palette.textMute, paddingHorizontal: 6, paddingVertical: 1, borderRadius: 4, backgroundColor: palette.bg2 }}>{isPeca ? "PÇA" : "SRV"}</Text>
        <OiMoney value={subtotal} size={13} style={{ flex: 1 }} color={palette.textDim} />
        <Pressable onPress={onRemove} hitSlop={10} style={{ width: 36, height: 36, alignItems: "center", justifyContent: "center" }}>
          <OiIcon name="trash" size={18} color={palette.danger} />
        </Pressable>
      </View>
      <OiFormInput label="Descrição" required placeholder={isPeca ? "Ex.: Filtro de óleo" : "Ex.: Troca de embreagem"} value={item.descricao} onChangeText={(v) => onChange("descricao", v)} returnKeyType="next" />
      <View style={{ flexDirection: "row", gap: 8 }}>
        <View style={{ flex: 1 }}><OiFormInput label="Qtd" mono placeholder="1" value={item.quantidade} onChangeText={(v) => onChange("quantidade", v)} keyboardType="numeric" /></View>
        <View style={{ flex: 1.4 }}><OiFormInput label="Valor unit." mono placeholder="0,00" value={item.valorUnit} onChangeText={(v) => onChange("valorUnit", v)} keyboardType="numeric" /></View>
      </View>
      {isPeca ? (
        <View style={{ flexDirection: "row", gap: 8 }}>
          <View style={{ flex: 1 }}><OiFormInput label="Código" mono placeholder="Opcional" value={item.codigo} onChangeText={(v) => onChange("codigo", v)} /></View>
          <View style={{ flex: 1.4 }}><OiFormInput label="Fornecedor" placeholder="Opcional" value={item.fornecedor} onChangeText={(v) => onChange("fornecedor", v)} /></View>
        </View>
      ) : (
        <View style={{ flexDirection: "row", gap: 8 }}>
          <View style={{ flex: 1 }}><OiFormInput label="Tempo (h)" mono placeholder="2" value={item.tempoEstimadoHoras} onChangeText={(v) => onChange("tempoEstimadoHoras", v)} keyboardType="numeric" /></View>
          <View style={{ flex: 1.4 }}><OiFormInput label="Mecânico" placeholder="Responsável" value={item.mecanicoResponsavel} onChangeText={(v) => onChange("mecanicoResponsavel", v)} /></View>
        </View>
      )}
    </OiCard>
  );
}

function AiSuggestionCard({ suggestion, onApplyDiagnostico, onApplyItems, onDismiss }: {
  suggestion: DiagnosisSuggestion;
  onApplyDiagnostico: () => void;
  onApplyItems: () => void;
  onDismiss: () => void;
}) {
  const { palette } = useOiTheme();
  const conf = CONF[suggestion.confianca] ?? CONF.media;
  const Lbl = ({ t }: { t: string }) => (
    <Text style={{ fontSize: 10.5, fontFamily: fonts.sansBold, letterSpacing: 1, textTransform: "uppercase", color: palette.textMute, marginTop: 6 }}>{t}</Text>
  );
  return (
    <OiCard variant="hi" style={{ marginVertical: 12 }}>
      <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
        <OiIcon name="zap" size={18} color={palette.accent} />
        <Text style={{ flex: 1, fontFamily: fonts.sansSemibold, fontSize: 14, color: palette.accentText }}>Sugestão da IA</Text>
        <OiStatus label={conf.label} variant={conf.variant} />
        <Pressable onPress={onDismiss} hitSlop={10} style={{ width: 32, height: 32, alignItems: "center", justifyContent: "center" }}>
          <OiIcon name="x" size={18} color={palette.textDim} />
        </Pressable>
      </View>
      <Lbl t="Diagnóstico sugerido" />
      <Text style={{ fontFamily: fonts.sans, fontSize: 13.5, lineHeight: 19, color: palette.text }}>{suggestion.diagnosticoSugerido}</Text>
      {suggestion.servicosProvaveis.length > 0 ? (<><Lbl t="Serviços prováveis" />{suggestion.servicosProvaveis.map((s, i) => <Text key={`s${i}`} style={{ fontFamily: fonts.sans, fontSize: 13, color: palette.text }}>· {s}</Text>)}</>) : null}
      {suggestion.pecasProvaveis.length > 0 ? (<><Lbl t="Peças prováveis" />{suggestion.pecasProvaveis.map((p, i) => <Text key={`p${i}`} style={{ fontFamily: fonts.sans, fontSize: 13, color: palette.text }}>· {p}</Text>)}</>) : null}
      <Text style={{ fontFamily: fonts.mono, fontSize: 12, color: palette.textDim, marginTop: 6 }}>Tempo estimado: {suggestion.tempoEstimadoHoras} h</Text>
      <View style={{ marginTop: 8 }}>
        <OiBtnRow>
          <OiBtn variant="primary" size="sm" label="Usar diagnóstico" onPress={onApplyDiagnostico} />
          <OiBtn size="sm" label="Adicionar itens" onPress={onApplyItems} />
        </OiBtnRow>
      </View>
    </OiCard>
  );
}
