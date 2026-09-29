import React, { useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  Image,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  View,
} from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import * as Clipboard from "expo-clipboard";
import * as ImagePicker from "expo-image-picker";

import { ScreenContainer } from "@/components/screen-container";
import { FormInput, Select, Toast } from "@/components/erp-ui";
import { useERP } from "@/lib/erp-context";
import {
  useAddOSPhoto,
  useCreateServiceOrder,
  useCustomers,
  useGenerateOSLink,
  useRemoveOSPhoto,
  useServiceOrder,
  useSuggestDiagnosis,
  useUpdateServiceOrder,
  useVehicles,
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
  return {
    tipo,
    codigo: "",
    descricao: "",
    quantidade: "1",
    valorUnit: "0",
    fornecedor: "",
    tempoEstimadoHoras: "",
    mecanicoResponsavel: "",
  };
}

const brl = (n: number) =>
  new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(n);

function parseNumber(v: string): number {
  const cleaned = v.replace(",", ".");
  const n = parseFloat(cleaned);
  return Number.isFinite(n) ? n : 0;
}

export default function OSDetailScreen() {
  const { id: rawId } = useLocalSearchParams<{ id: string }>();
  const isNew = rawId === "new" || !rawId;
  const id = isNew ? undefined : rawId;

  const { addToast, ui } = useERP();
  const osQuery = useServiceOrder(id);
  const customersQuery = useCustomers();
  const vehiclesQuery = useVehicles();
  const createOS = useCreateServiceOrder();
  const updateOS = useUpdateServiceOrder();
  const addPhoto = useAddOSPhoto();
  const removePhoto = useRemoveOSPhoto();
  const generateLink = useGenerateOSLink();
  const suggestDiagnosis = useSuggestDiagnosis();
  const [aiSuggestion, setAiSuggestion] = useState<DiagnosisSuggestion | null>(
    null,
  );

  const customers = customersQuery.data ?? [];
  const vehicles = vehiclesQuery.data ?? [];

  // Form state
  const [customerId, setCustomerId] = useState("");
  const [vehicleId, setVehicleId] = useState("");
  const [kmEntrada, setKmEntrada] = useState("");
  const [queixaCliente, setQueixaCliente] = useState("");
  const [diagnostico, setDiagnostico] = useState("");
  const [items, setItems] = useState<ItemForm[]>([]);
  const [loaded, setLoaded] = useState(false);

  // Load existing OS into form
  useEffect(() => {
    if (isNew) {
      setLoaded(true);
      return;
    }
    if (!osQuery.data) return;
    const os = osQuery.data;
    setCustomerId(os.customerId);
    setVehicleId(os.vehicleId);
    setKmEntrada(os.kmEntrada != null ? String(os.kmEntrada) : "");
    setQueixaCliente(os.queixaCliente ?? "");
    setDiagnostico(os.diagnostico ?? "");
    setItems(
      os.items.map((i) => ({
        tipo: i.tipo,
        codigo: i.codigo ?? "",
        descricao: i.descricao,
        quantidade: String(i.quantidade),
        valorUnit: String(i.valorUnit),
        fornecedor: i.fornecedor ?? "",
        tempoEstimadoHoras:
          i.tempoEstimadoHoras != null ? String(i.tempoEstimadoHoras) : "",
        mecanicoResponsavel: i.mecanicoResponsavel ?? "",
      })),
    );
    setLoaded(true);
  }, [osQuery.data, isNew]);

  const customerOptions = useMemo(
    () => customers.map((c) => ({ label: c.nome, value: c.id })),
    [customers],
  );

  const vehicleOptions = useMemo(() => {
    const filtered = customerId
      ? vehicles.filter((v) => v.customerId === customerId)
      : vehicles;
    return filtered.map((v) => ({
      label: `${v.placa} — ${v.marca} ${v.modelo}`,
      value: v.id,
    }));
  }, [vehicles, customerId]);

  const totals = useMemo(() => {
    let pecas = 0;
    let mo = 0;
    for (const it of items) {
      const total = parseNumber(it.quantidade) * parseNumber(it.valorUnit);
      if (it.tipo === "peca") pecas += total;
      else mo += total;
    }
    return { pecas, mo, total: pecas + mo };
  }, [items]);

  const updateItem = <K extends keyof ItemForm>(
    index: number,
    key: K,
    value: ItemForm[K],
  ) => {
    setItems((prev) => prev.map((it, i) => (i === index ? { ...it, [key]: value } : it)));
  };

  const removeItemAt = (index: number) =>
    setItems((prev) => prev.filter((_, i) => i !== index));

  const selectedVehicle = useMemo(
    () => vehicles.find((v) => v.id === vehicleId),
    [vehicles, vehicleId],
  );

  const handleSuggestDiagnosis = async () => {
    if (!queixaCliente.trim() || queixaCliente.trim().length < 5) {
      addToast("erro", "Descreva a queixa com pelo menos 5 caracteres");
      return;
    }
    try {
      const result = await suggestDiagnosis.mutateAsync({
        queixaCliente: queixaCliente.trim(),
        vehicleMarca: selectedVehicle?.marca ?? undefined,
        vehicleModelo: selectedVehicle?.modelo ?? undefined,
        vehicleAno: selectedVehicle?.ano ?? undefined,
        kmAtual: kmEntrada.trim() ? parseInt(kmEntrada, 10) : undefined,
      });
      setAiSuggestion(result as DiagnosisSuggestion);
      addToast("sucesso", "Sugestão gerada");
    } catch (err) {
      addToast(
        "erro",
        err instanceof Error ? err.message : "Erro ao consultar IA",
      );
    }
  };

  const applyAiDiagnostico = () => {
    if (!aiSuggestion) return;
    setDiagnostico(aiSuggestion.diagnosticoSugerido);
    addToast("sucesso", "Diagnóstico aplicado");
  };

  const applyAiItems = () => {
    if (!aiSuggestion) return;
    const novos: ItemForm[] = [
      ...aiSuggestion.servicosProvaveis.map((s) => ({
        ...emptyItem("servico"),
        descricao: s,
      })),
      ...aiSuggestion.pecasProvaveis.map((p) => ({
        ...emptyItem("peca"),
        descricao: p,
      })),
    ];
    if (aiSuggestion.tempoEstimadoHoras && novos.length > 0) {
      const firstServ = novos.find((n) => n.tipo === "servico");
      if (firstServ)
        firstServ.tempoEstimadoHoras = String(
          aiSuggestion.tempoEstimadoHoras,
        );
    }
    setItems((prev) => [...prev, ...novos]);
    addToast("sucesso", `${novos.length} item(s) adicionado(s)`);
  };

  const handleSave = async () => {
    if (!customerId) {
      addToast("erro", "Selecione um cliente");
      return;
    }
    if (!vehicleId) {
      addToast("erro", "Selecione um veículo");
      return;
    }
    const payloadItems = items
      .filter((it) => it.descricao.trim().length > 0)
      .map((it) => ({
        tipo: it.tipo,
        codigo: it.codigo.trim() || undefined,
        descricao: it.descricao.trim(),
        quantidade: parseNumber(it.quantidade) || 1,
        valorUnit: parseNumber(it.valorUnit) || 0,
        fornecedor: it.fornecedor.trim() || undefined,
        tempoEstimadoHoras: it.tempoEstimadoHoras.trim()
          ? parseNumber(it.tempoEstimadoHoras)
          : undefined,
        mecanicoResponsavel: it.mecanicoResponsavel.trim() || undefined,
      }));

    try {
      if (isNew) {
        const created = await createOS.mutateAsync({
          customerId,
          vehicleId,
          kmEntrada: kmEntrada.trim() ? parseInt(kmEntrada, 10) : undefined,
          queixaCliente: queixaCliente.trim() || undefined,
          items: payloadItems,
        });
        addToast("sucesso", `OS #${created.numero} criada!`);
        router.replace(`/oss/${created.id}` as any);
      } else if (id) {
        await updateOS.mutateAsync({
          id,
          customerId,
          vehicleId,
          kmEntrada: kmEntrada.trim() ? parseInt(kmEntrada, 10) : null,
          queixaCliente: queixaCliente.trim() || null,
          diagnostico: diagnostico.trim() || null,
          items: payloadItems,
        });
        addToast("sucesso", "OS atualizada!");
      }
    } catch (err) {
      addToast(
        "erro",
        err instanceof Error ? err.message : "Erro ao salvar OS",
      );
    }
  };

  const handlePickPhotos = async () => {
    if (isNew || !id) {
      addToast("info", "Salve a OS primeiro para anexar fotos");
      return;
    }
    try {
      const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!perm.granted) {
        addToast("erro", "Permissão para galeria negada");
        return;
      }
      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ImagePicker.MediaTypeOptions.Images,
        allowsMultipleSelection: true,
        quality: 0.7,
      });
      if (result.canceled || result.assets.length === 0) return;
      for (const asset of result.assets) {
        await addPhoto.mutateAsync({
          serviceOrderId: id,
          fileKey: asset.uri,
          tipo: "entrada",
          descricao: asset.fileName ?? null,
        });
      }
      addToast("sucesso", `${result.assets.length} foto(s) adicionada(s)`);
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro na seleção");
    }
  };

  const handleRemovePhoto = async (photoId: string) => {
    try {
      await removePhoto.mutateAsync({ photoId });
      addToast("sucesso", "Foto removida");
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao remover foto");
    }
  };

  const handleCopyLink = async () => {
    if (isNew || !id) {
      addToast("info", "Salve a OS primeiro para gerar link");
      return;
    }
    try {
      const result = await generateLink.mutateAsync({ id, expiresInDays: 30 });
      await Clipboard.setStringAsync(result.url);
      addToast("sucesso", "Link de aprovação copiado!");
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao gerar link");
    }
  };

  if (!loaded && !isNew) {
    return (
      <ScreenContainer className="bg-background">
        <View className="flex-1 items-center justify-center">
          <ActivityIndicator />
        </View>
      </ScreenContainer>
    );
  }

  const photos = osQuery.data?.photos ?? [];

  return (
    <ScreenContainer className="bg-background">
      <ScrollView className="flex-1" contentContainerStyle={{ paddingBottom: 32 }}>
        <View className="px-4 py-6 border-b border-border flex-row justify-between items-center">
          <View>
            <Text className="text-2xl font-bold text-foreground">
              {isNew ? "Nova OS" : `OS #${osQuery.data?.numero ?? ""}`}
            </Text>
          </View>
          <Pressable
            onPress={() => router.back()}
            className="bg-surface border border-border px-3 py-2 rounded-lg"
          >
            <Text className="text-foreground text-sm">Voltar</Text>
          </Pressable>
        </View>

        <View className="px-4 pt-4">
          {ui.toasts.map((toast) => (
            <Toast key={toast.id} tipo={toast.tipo} mensagem={toast.mensagem} />
          ))}
        </View>

        <Section title="Dados do Veículo">
          <Select
            label="Cliente"
            options={customerOptions}
            value={customerId}
            onValueChange={(v) => {
              setCustomerId(v);
              setVehicleId("");
            }}
            required
          />
          <Select
            label="Veículo"
            options={vehicleOptions}
            value={vehicleId}
            onValueChange={setVehicleId}
            required
          />
          <FormInput
            label="KM Entrada"
            placeholder="0"
            value={kmEntrada}
            onChangeText={setKmEntrada}
            keyboardType="numeric"
          />
        </Section>

        <Section title="Diagnóstico">
          <Multiline
            label="Queixa do cliente"
            placeholder="O que o cliente relatou…"
            value={queixaCliente}
            onChange={setQueixaCliente}
          />
          <Pressable
            onPress={handleSuggestDiagnosis}
            disabled={suggestDiagnosis.isPending}
            className="bg-primary/10 px-3 py-2 rounded-lg mb-3 self-start"
          >
            {suggestDiagnosis.isPending ? (
              <View className="flex-row items-center gap-2">
                <ActivityIndicator size="small" />
                <Text className="text-primary text-sm font-semibold">
                  Consultando IA…
                </Text>
              </View>
            ) : (
              <Text className="text-primary text-sm font-semibold">
                🧠 Sugerir diagnóstico (IA)
              </Text>
            )}
          </Pressable>
          {aiSuggestion ? (
            <AiSuggestionCard
              suggestion={aiSuggestion}
              onApplyDiagnostico={applyAiDiagnostico}
              onApplyItems={applyAiItems}
              onDismiss={() => setAiSuggestion(null)}
            />
          ) : null}
          <Multiline
            label="Diagnóstico técnico"
            placeholder="Avaliação do mecânico…"
            value={diagnostico}
            onChange={setDiagnostico}
          />
        </Section>

        <Section title="Serviços (mão de obra)">
          {items
            .map((it, idx) => ({ it, idx }))
            .filter(({ it }) => it.tipo === "servico")
            .map(({ it, idx }) => (
              <ItemRow
                key={`s-${idx}`}
                item={it}
                onChange={(k, v) => updateItem(idx, k, v)}
                onRemove={() => removeItemAt(idx)}
              />
            ))}
          <Pressable
            onPress={() => setItems((p) => [...p, emptyItem("servico")])}
            className="bg-primary/10 px-3 py-2 rounded-lg mt-2"
          >
            <Text className="text-primary text-sm font-semibold text-center">
              + Adicionar serviço
            </Text>
          </Pressable>
        </Section>

        <Section title="Peças">
          {items
            .map((it, idx) => ({ it, idx }))
            .filter(({ it }) => it.tipo === "peca")
            .map(({ it, idx }) => (
              <ItemRow
                key={`p-${idx}`}
                item={it}
                onChange={(k, v) => updateItem(idx, k, v)}
                onRemove={() => removeItemAt(idx)}
              />
            ))}
          <Pressable
            onPress={() => setItems((p) => [...p, emptyItem("peca")])}
            className="bg-primary/10 px-3 py-2 rounded-lg mt-2"
          >
            <Text className="text-primary text-sm font-semibold text-center">
              + Adicionar peça
            </Text>
          </Pressable>
        </Section>

        <Section title="Totais">
          <Row label="Total peças" value={brl(totals.pecas)} />
          <Row label="Total mão de obra" value={brl(totals.mo)} />
          <Row label="TOTAL GERAL" value={brl(totals.total)} bold />
        </Section>

        {!isNew ? (
          <Section title="Fotos de entrada">
            <View className="flex-row flex-wrap gap-2">
              {photos.map((p) => (
                <View key={p.id} className="relative">
                  <Image
                    source={{ uri: p.fileKey }}
                    style={{ width: 80, height: 80, borderRadius: 8 }}
                  />
                  <Pressable
                    onPress={() => handleRemovePhoto(p.id)}
                    className="absolute top-0 right-0 bg-error rounded-full w-5 h-5 items-center justify-center"
                  >
                    <Text className="text-white text-xs">×</Text>
                  </Pressable>
                </View>
              ))}
            </View>
            <Pressable
              onPress={handlePickPhotos}
              className="bg-primary/10 px-3 py-2 rounded-lg mt-3"
            >
              <Text className="text-primary text-sm font-semibold text-center">
                + Adicionar fotos
              </Text>
            </Pressable>
          </Section>
        ) : null}

        {!isNew ? (
          <Section title="Aprovação">
            <Pressable
              onPress={handleCopyLink}
              className="bg-surface border border-border px-3 py-3 rounded-lg"
            >
              <Text className="text-foreground text-sm font-semibold text-center">
                🔗 Copiar link de aprovação
              </Text>
            </Pressable>
          </Section>
        ) : null}

        <View className="px-4 pt-6 flex-row gap-3">
          <Pressable
            onPress={() => router.back()}
            className="flex-1 bg-surface border border-border py-3 rounded-lg"
          >
            <Text className="text-foreground font-semibold text-center">
              Voltar
            </Text>
          </Pressable>
          <Pressable
            onPress={handleSave}
            disabled={createOS.isPending || updateOS.isPending}
            className="flex-1 bg-primary py-3 rounded-lg"
          >
            <Text className="text-white font-semibold text-center">
              {createOS.isPending || updateOS.isPending ? "Salvando…" : "Salvar"}
            </Text>
          </Pressable>
        </View>
      </ScrollView>
    </ScreenContainer>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <View className="px-4 pt-4">
      <Text className="text-base font-bold text-foreground mb-3">{title}</Text>
      <View className="bg-surface rounded-lg p-3 border border-border">
        {children}
      </View>
    </View>
  );
}

function Row({
  label,
  value,
  bold,
}: {
  label: string;
  value: string;
  bold?: boolean;
}) {
  return (
    <View className="flex-row justify-between py-2">
      <Text className={`text-sm ${bold ? "font-bold text-foreground" : "text-muted"}`}>
        {label}
      </Text>
      <Text className={`text-sm ${bold ? "font-bold text-foreground" : "text-foreground"}`}>
        {value}
      </Text>
    </View>
  );
}

function Multiline({
  label,
  placeholder,
  value,
  onChange,
}: {
  label: string;
  placeholder: string;
  value: string;
  onChange: (v: string) => void;
}) {
  return (
    <View className="mb-4">
      <Text className="text-sm font-medium text-foreground mb-2">{label}</Text>
      <TextInput
        value={value}
        onChangeText={onChange}
        placeholder={placeholder}
        multiline
        numberOfLines={3}
        className="border border-border rounded-lg px-3 py-2 text-foreground"
        textAlignVertical="top"
      />
    </View>
  );
}

function ItemRow({
  item,
  onChange,
  onRemove,
}: {
  item: ItemForm;
  onChange: <K extends keyof ItemForm>(key: K, value: ItemForm[K]) => void;
  onRemove: () => void;
}) {
  const isPeca = item.tipo === "peca";
  return (
    <View className="border border-border rounded-lg p-2 mb-2 bg-background">
      <View className="flex-row justify-between items-center mb-2">
        <Text className="text-xs font-bold text-muted uppercase">
          {isPeca ? "Peça" : "Serviço"}
        </Text>
        <Pressable onPress={onRemove} hitSlop={8}>
          <Text className="text-error text-lg">×</Text>
        </Pressable>
      </View>
      <FormInput
        label="Código"
        placeholder="Opcional"
        value={item.codigo}
        onChangeText={(v) => onChange("codigo", v)}
        returnKeyType="next"
      />
      <FormInput
        label="Descrição"
        placeholder="Ex: Filtro de óleo"
        value={item.descricao}
        onChangeText={(v) => onChange("descricao", v)}
        required
        returnKeyType="next"
      />
      <View className="flex-row gap-2">
        <View className="flex-1">
          <FormInput
            label="Qtd"
            placeholder="1"
            value={item.quantidade}
            onChangeText={(v) => onChange("quantidade", v)}
            keyboardType="numeric"
            returnKeyType="next"
          />
        </View>
        <View className="flex-1">
          <FormInput
            label="Valor unit."
            placeholder="0,00"
            value={item.valorUnit}
            onChangeText={(v) => onChange("valorUnit", v)}
            keyboardType="numeric"
            returnKeyType="next"
          />
        </View>
      </View>
      {isPeca ? (
        <FormInput
          label="Fornecedor"
          placeholder="Opcional"
          value={item.fornecedor}
          onChangeText={(v) => onChange("fornecedor", v)}
          returnKeyType="next"
        />
      ) : (
        <>
          <FormInput
            label="Tempo estimado (h)"
            placeholder="Ex: 2"
            value={item.tempoEstimadoHoras}
            onChangeText={(v) => onChange("tempoEstimadoHoras", v)}
            keyboardType="numeric"
            returnKeyType="next"
          />
          <FormInput
            label="Mecânico"
            placeholder="Responsável"
            value={item.mecanicoResponsavel}
            onChangeText={(v) => onChange("mecanicoResponsavel", v)}
            returnKeyType="next"
          />
        </>
      )}
    </View>
  );
}

function AiSuggestionCard({
  suggestion,
  onApplyDiagnostico,
  onApplyItems,
  onDismiss,
}: {
  suggestion: {
    diagnosticoSugerido: string;
    pecasProvaveis: string[];
    servicosProvaveis: string[];
    tempoEstimadoHoras: number;
    confianca: "baixa" | "media" | "alta";
  };
  onApplyDiagnostico: () => void;
  onApplyItems: () => void;
  onDismiss: () => void;
}) {
  const confColors: Record<string, { bg: string; fg: string; label: string }> =
    {
      alta: { bg: "#EAF3DE", fg: "#27500A", label: "Alta confiança" },
      media: { bg: "#FAEEDA", fg: "#633806", label: "Média confiança" },
      baixa: { bg: "#FCEBEB", fg: "#791F1F", label: "Baixa confiança" },
    };
  const conf = confColors[suggestion.confianca] ?? confColors.media;
  return (
    <View className="border border-primary/30 bg-primary/5 rounded-lg p-3 mb-4">
      <View className="flex-row justify-between items-center mb-2">
        <Text className="text-sm font-bold text-primary">
          🧠 Sugestão da IA
        </Text>
        <Pressable onPress={onDismiss} hitSlop={8}>
          <Text className="text-muted text-lg">×</Text>
        </Pressable>
      </View>
      <View
        style={{ backgroundColor: conf.bg, alignSelf: "flex-start" }}
        className="px-2 py-1 rounded-full mb-2"
      >
        <Text style={{ color: conf.fg }} className="text-xs font-semibold">
          {conf.label}
        </Text>
      </View>
      <Text className="text-xs font-semibold text-muted mt-1">
        Diagnóstico sugerido
      </Text>
      <Text className="text-sm text-foreground mb-2">
        {suggestion.diagnosticoSugerido}
      </Text>
      {suggestion.pecasProvaveis.length > 0 ? (
        <>
          <Text className="text-xs font-semibold text-muted">
            Peças prováveis
          </Text>
          {suggestion.pecasProvaveis.map((p, i) => (
            <Text key={`pp-${i}`} className="text-sm text-foreground">
              • {p}
            </Text>
          ))}
        </>
      ) : null}
      {suggestion.servicosProvaveis.length > 0 ? (
        <>
          <Text className="text-xs font-semibold text-muted mt-2">
            Serviços prováveis
          </Text>
          {suggestion.servicosProvaveis.map((s, i) => (
            <Text key={`sp-${i}`} className="text-sm text-foreground">
              • {s}
            </Text>
          ))}
        </>
      ) : null}
      <Text className="text-xs text-muted mt-2">
        Tempo estimado: {suggestion.tempoEstimadoHoras}h
      </Text>
      <View className="flex-row gap-2 mt-3">
        <Pressable
          onPress={onApplyDiagnostico}
          className="bg-primary px-3 py-2 rounded-lg flex-1"
        >
          <Text className="text-white text-xs font-semibold text-center">
            Usar diagnóstico
          </Text>
        </Pressable>
        <Pressable
          onPress={onApplyItems}
          className="bg-primary/20 px-3 py-2 rounded-lg flex-1"
        >
          <Text className="text-primary text-xs font-semibold text-center">
            Adicionar itens
          </Text>
        </Pressable>
      </View>
    </View>
  );
}
