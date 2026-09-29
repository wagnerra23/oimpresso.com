import React, { useMemo, useState } from "react";
import {
  ActivityIndicator,
  Linking,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  View,
} from "react-native";

import { OiHeader, OiScreen } from "@/components/oi";
import {
  Button,
  FormInput,
  ModalDialog,
  Select,
  Toast,
} from "@/components/erp-ui";
import { useERP } from "@/lib/erp-context";
import {
  useConvertQuoteToPedido,
  useCreatePriceTable,
  useCreateQuote,
  useCustomers,
  useDeletePriceTable,
  useDeleteQuote,
  useGenerateQuotePdf,
  usePriceTables,
  useQuotes,
  useSuggestPricing,
  useUpdatePriceTable,
  useUpdateQuote,
} from "@/lib/erp-queries";

type PricingSuggestion = {
  itensSugeridos: Array<{
    descricao: string;
    precoPorM2Sugerido: number;
    totalItem: number;
    justificativa: string;
  }>;
  totalGeral: number;
  margemSugerida: number;
  observacoes: string;
};

type QuoteStatus =
  | "rascunho"
  | "enviado"
  | "aprovado"
  | "rejeitado"
  | "convertido";

const STATUS_TABS: { id: QuoteStatus | "todos"; label: string }[] = [
  { id: "todos", label: "Todos" },
  { id: "rascunho", label: "Rascunho" },
  { id: "enviado", label: "Enviado" },
  { id: "aprovado", label: "Aprovado" },
  { id: "convertido", label: "Convertido" },
];

const STATUS_OPTIONS: { label: string; value: QuoteStatus }[] = [
  { label: "Rascunho", value: "rascunho" },
  { label: "Enviado", value: "enviado" },
  { label: "Aprovado", value: "aprovado" },
  { label: "Rejeitado", value: "rejeitado" },
  { label: "Convertido", value: "convertido" },
];

const BRL = new Intl.NumberFormat("pt-BR", {
  style: "currency",
  currency: "BRL",
});

interface ItemForm {
  descricao: string;
  material: string;
  larguraCm: string;
  alturaCm: string;
  quantidade: string;
  precoPorM2: string;
  acabamento: string;
}

interface QuoteForm {
  customerId: string;
  titulo: string;
  validadeDias: string;
  observacoes: string;
  status: QuoteStatus;
  items: ItemForm[];
}

const EMPTY_ITEM: ItemForm = {
  descricao: "",
  material: "",
  larguraCm: "",
  alturaCm: "",
  quantidade: "1",
  precoPorM2: "",
  acabamento: "",
};

const EMPTY_FORM: QuoteForm = {
  customerId: "",
  titulo: "",
  validadeDias: "7",
  observacoes: "",
  status: "rascunho",
  items: [{ ...EMPTY_ITEM }],
};

function parseNumber(s: string): number {
  const n = Number(String(s).replace(",", "."));
  return Number.isFinite(n) ? n : 0;
}

function itemArea(it: ItemForm): number {
  return (
    (parseNumber(it.larguraCm) *
      parseNumber(it.alturaCm) *
      parseNumber(it.quantidade)) /
    10000
  );
}

function itemTotal(it: ItemForm): number {
  return itemArea(it) * parseNumber(it.precoPorM2);
}

export default function OrcamentosScreen() {
  const { ui, addToast } = useERP();

  const [statusTab, setStatusTab] = useState<QuoteStatus | "todos">("todos");
  const quotesQuery = useQuotes(statusTab === "todos" ? undefined : statusTab);
  const customersQuery = useCustomers();
  const priceTablesQuery = usePriceTables();

  const createQuote = useCreateQuote();
  const updateQuote = useUpdateQuote();
  const deleteQuote = useDeleteQuote();
  const generatePdf = useGenerateQuotePdf();
  const convertToPedido = useConvertQuoteToPedido();
  const createPT = useCreatePriceTable();
  const updatePT = useUpdatePriceTable();
  const deletePT = useDeletePriceTable();

  const quotes = quotesQuery.data ?? [];
  const customers = customersQuery.data ?? [];
  const priceTables = priceTablesQuery.data ?? [];

  const [modalVisible, setModalVisible] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState<QuoteForm>(EMPTY_FORM);
  const [priceTableModal, setPriceTableModal] = useState(false);

  const suggestPricing = useSuggestPricing();
  const [aiPricing, setAiPricing] = useState<PricingSuggestion | null>(null);

  const setField = <K extends keyof QuoteForm>(key: K, value: QuoteForm[K]) =>
    setForm((prev) => ({ ...prev, [key]: value }));

  const setItemField = <K extends keyof ItemForm>(
    idx: number,
    key: K,
    value: ItemForm[K],
  ) =>
    setForm((prev) => ({
      ...prev,
      items: prev.items.map((it, i) => (i === idx ? { ...it, [key]: value } : it)),
    }));

  const resetForm = () => {
    setEditingId(null);
    setForm(EMPTY_FORM);
  };

  const openCreate = () => {
    resetForm();
    setModalVisible(true);
  };

  const openEdit = (q: (typeof quotes)[number]) => {
    setEditingId(q.id);
    setForm({
      customerId: q.customerId ?? "",
      titulo: q.titulo,
      validadeDias: String(q.validadeDias),
      observacoes: q.observacoes ?? "",
      status: q.status,
      items:
        q.items.length > 0
          ? q.items.map((it) => ({
              descricao: it.descricao,
              material: it.material ?? "",
              larguraCm: String(it.larguraCm),
              alturaCm: String(it.alturaCm),
              quantidade: String(it.quantidade),
              precoPorM2: String(it.precoPorM2),
              acabamento: it.acabamento ?? "",
            }))
          : [{ ...EMPTY_ITEM }],
    });
    setModalVisible(true);
  };

  const totalGeral = useMemo(
    () => form.items.reduce((s, i) => s + itemTotal(i), 0),
    [form.items],
  );

  const addItem = () =>
    setForm((prev) => ({ ...prev, items: [...prev.items, { ...EMPTY_ITEM }] }));

  const removeItem = (idx: number) =>
    setForm((prev) => ({
      ...prev,
      items: prev.items.filter((_, i) => i !== idx),
    }));

  const handleSuggestPricing = async () => {
    const itensValidos = form.items.filter(
      (it) =>
        it.descricao.trim() &&
        parseNumber(it.larguraCm) > 0 &&
        parseNumber(it.alturaCm) > 0,
    );
    if (itensValidos.length === 0) {
      addToast(
        "erro",
        "Preencha descrição + dimensões de pelo menos 1 item",
      );
      return;
    }
    const customerName = customers.find((c) => c.id === form.customerId)?.nome;
    try {
      const result = await suggestPricing.mutateAsync({
        items: itensValidos.map((it) => ({
          descricao: it.descricao.trim(),
          material: it.material.trim() || undefined,
          larguraCm: parseNumber(it.larguraCm),
          alturaCm: parseNumber(it.alturaCm),
          quantidade: parseNumber(it.quantidade) || 1,
        })),
        customerName,
      });
      setAiPricing(result as PricingSuggestion);
      addToast("sucesso", "Sugestão gerada");
    } catch (err) {
      addToast(
        "erro",
        err instanceof Error ? err.message : "Erro ao consultar IA",
      );
    }
  };

  const applyAiPricingItem = (idx: number, preco: number) => {
    setItemField(idx, "precoPorM2", preco.toFixed(2));
    addToast("sucesso", `Preço aplicado no item ${idx + 1}`);
  };

  const handleSave = async () => {
    const titulo = form.titulo.trim();
    if (!titulo) {
      addToast("erro", "Informe o título");
      return;
    }
    const items = form.items
      .map((it) => ({
        descricao: it.descricao.trim(),
        material: it.material.trim() || null,
        larguraCm: parseNumber(it.larguraCm),
        alturaCm: parseNumber(it.alturaCm),
        quantidade: parseNumber(it.quantidade) || 1,
        precoPorM2: parseNumber(it.precoPorM2),
        acabamento: it.acabamento.trim() || null,
      }))
      .filter((it) => it.descricao);
    if (items.length === 0) {
      addToast("erro", "Adicione ao menos um item");
      return;
    }
    for (const it of items) {
      if (it.larguraCm <= 0 || it.alturaCm <= 0) {
        addToast("erro", "Dimensões devem ser maiores que zero");
        return;
      }
    }
    const validadeDias = Math.max(1, Math.floor(parseNumber(form.validadeDias) || 7));
    try {
      if (editingId) {
        await updateQuote.mutateAsync({
          id: editingId,
          customerId: form.customerId || null,
          titulo,
          validadeDias,
          observacoes: form.observacoes.trim() || null,
          status: form.status,
          items,
        });
        addToast("sucesso", "Orçamento atualizado!");
      } else {
        await createQuote.mutateAsync({
          customerId: form.customerId || null,
          titulo,
          validadeDias,
          observacoes: form.observacoes.trim() || null,
          status: form.status,
          items,
        });
        addToast("sucesso", "Orçamento criado!");
      }
      setModalVisible(false);
      resetForm();
    } catch (err) {
      addToast(
        "erro",
        err instanceof Error ? err.message : "Erro ao salvar orçamento",
      );
    }
  };

  const handleDelete = async (id: string) => {
    try {
      await deleteQuote.mutateAsync({ id });
      addToast("sucesso", "Orçamento removido");
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao remover");
    }
  };

  const handleGeneratePdf = async (id: string) => {
    try {
      const res = await generatePdf.mutateAsync({ id });
      addToast("sucesso", "PDF gerado!");
      if (res?.url) Linking.openURL(res.url).catch(() => {});
    } catch (err) {
      addToast(
        "erro",
        err instanceof Error ? err.message : "Erro ao gerar PDF",
      );
    }
  };

  const handleConvert = async (id: string) => {
    try {
      await convertToPedido.mutateAsync({ id });
      addToast("sucesso", "Convertido em pedido!");
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao converter");
    }
  };

  const applyPriceFromTable = (idx: number, ptId: string) => {
    const pt = priceTables.find((p) => p.id === ptId);
    if (!pt) return;
    setItemField(idx, "material", pt.material);
    setItemField(idx, "precoPorM2", String(pt.precoPorM2));
  };

  const customerOptions = useMemo(
    () => [
      { label: "Sem cliente", value: "" },
      ...customers.map((c) => ({ label: c.nome, value: c.id })),
    ],
    [customers],
  );

  const priceTableOptions = useMemo(
    () => [
      { label: "— manual —", value: "" },
      ...priceTables.map((p) => ({
        label: `${p.material} — ${BRL.format(p.precoPorM2)}/m²`,
        value: p.id,
      })),
    ],
    [priceTables],
  );

  return (
    <OiScreen edges={["top"]}>
      <OiHeader
        title="Orçamentos"
        eyebrow={`${quotes.length} proposta${quotes.length === 1 ? "" : "s"}`}
        actions={[
          { icon: "settings", onPress: () => setPriceTableModal(true) },
          { icon: "plus", onPress: openCreate },
        ]}
      />
      <ScrollView className="flex-1" contentContainerStyle={{ paddingBottom: 24 }}>

        {/* Toasts */}
        <View className="px-4 pt-4">
          {ui.toasts.map((toast) => (
            <Toast key={toast.id} tipo={toast.tipo} mensagem={toast.mensagem} />
          ))}
        </View>

        {/* Status tabs */}
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          className="px-4 py-2"
        >
          <View className="flex-row gap-2">
            {STATUS_TABS.map((t) => {
              const active = statusTab === t.id;
              return (
                <Pressable
                  key={t.id}
                  onPress={() => setStatusTab(t.id)}
                  className={`px-3 py-2 rounded-full border ${
                    active
                      ? "bg-primary border-primary"
                      : "bg-surface border-border"
                  }`}
                >
                  <Text
                    className={`text-xs font-semibold ${
                      active ? "text-white" : "text-foreground"
                    }`}
                  >
                    {t.label}
                  </Text>
                </Pressable>
              );
            })}
          </View>
        </ScrollView>

        <View className="px-4 py-2">
          {quotesQuery.isLoading ? (
            <View className="py-12 items-center">
              <ActivityIndicator />
            </View>
          ) : quotesQuery.isError ? (
            <View className="bg-error/10 rounded-lg p-4">
              <Text className="text-error text-sm">
                {quotesQuery.error?.message ?? "Erro ao carregar orçamentos"}
              </Text>
            </View>
          ) : quotes.length === 0 ? (
            <View className="bg-surface rounded-lg p-6 items-center">
              <Text className="text-muted text-sm">Nenhum orçamento</Text>
              <Text className="text-muted text-xs mt-2">
                Clique em "+ Novo" para começar
              </Text>
            </View>
          ) : (
            quotes.map((q) => (
              <View
                key={q.id}
                className="bg-surface rounded-lg p-4 mb-3 border border-border"
              >
                <View className="flex-row justify-between items-start">
                  <View className="flex-1 pr-2">
                    <Text className="font-semibold text-foreground">
                      {q.titulo}
                    </Text>
                    <Text className="text-xs text-muted mt-1">
                      {q.customer?.nome ?? "Sem cliente"}
                    </Text>
                    <Text className="text-base font-bold text-foreground mt-2">
                      {BRL.format(q.valorTotal)}
                    </Text>
                  </View>
                  <StatusBadge status={q.status} />
                </View>
                <View className="flex-row flex-wrap gap-2 mt-3">
                  <Pressable
                    onPress={() => openEdit(q)}
                    className="bg-surface border border-border px-3 py-2 rounded-lg"
                  >
                    <Text className="text-foreground font-semibold text-xs">
                      Editar
                    </Text>
                  </Pressable>
                  <Pressable
                    onPress={() => handleGeneratePdf(q.id)}
                    disabled={generatePdf.isPending}
                    className="bg-primary/20 px-3 py-2 rounded-lg"
                  >
                    <Text className="text-primary font-semibold text-xs">
                      {generatePdf.isPending ? "Gerando..." : "📄 Gerar PDF"}
                    </Text>
                  </Pressable>
                  {q.status === "aprovado" && (
                    <Pressable
                      onPress={() => handleConvert(q.id)}
                      disabled={convertToPedido.isPending}
                      className="bg-success/20 px-3 py-2 rounded-lg"
                      style={{ backgroundColor: "#EAF3DE" }}
                    >
                      <Text
                        className="font-semibold text-xs"
                        style={{ color: "#27500A" }}
                      >
                        Converter em Pedido
                      </Text>
                    </Pressable>
                  )}
                  <Pressable
                    onPress={() => handleDelete(q.id)}
                    className="px-3 py-2 rounded-lg"
                    style={{ backgroundColor: "#FCEBEB" }}
                  >
                    <Text
                      className="font-semibold text-xs"
                      style={{ color: "#791F1F" }}
                    >
                      Excluir
                    </Text>
                  </Pressable>
                </View>
              </View>
            ))
          )}
        </View>
      </ScrollView>

      {/* Create / Edit modal */}
      <ModalDialog
        visible={modalVisible}
        title={editingId ? "Editar Orçamento" : "Novo Orçamento"}
        onClose={() => {
          setModalVisible(false);
          resetForm();
        }}
        onConfirm={handleSave}
        confirmText={editingId ? "Salvar" : "Criar"}
      >
        <Select
          label="Cliente"
          options={customerOptions}
          value={form.customerId}
          onValueChange={(v) => setField("customerId", v)}
        />
        <FormInput
          label="Título"
          placeholder="Ex: Fachada loja XYZ"
          value={form.titulo}
          onChangeText={(v) => setField("titulo", v)}
          required
        />
        <FormInput
          label="Validade (dias)"
          placeholder="7"
          value={form.validadeDias}
          onChangeText={(v) => setField("validadeDias", v)}
          keyboardType="numeric"
        />
        <Select
          label="Status"
          options={STATUS_OPTIONS}
          value={form.status}
          onValueChange={(v) => setField("status", v as QuoteStatus)}
        />

        <Text className="text-sm font-semibold text-foreground mt-2 mb-2">
          Itens
        </Text>
        {form.items.map((it, idx) => {
          const area = itemArea(it);
          const tot = itemTotal(it);
          return (
            <View
              key={idx}
              className="border border-border rounded-lg p-3 mb-3"
            >
              <View className="flex-row justify-between items-center mb-2">
                <Text className="text-xs font-semibold text-muted">
                  Item {idx + 1}
                </Text>
                {form.items.length > 1 && (
                  <Pressable onPress={() => removeItem(idx)}>
                    <Text className="text-error text-xs font-semibold">
                      × Remover
                    </Text>
                  </Pressable>
                )}
              </View>
              <Select
                label="Material (tabela)"
                options={priceTableOptions}
                value=""
                onValueChange={(v) => applyPriceFromTable(idx, v)}
              />
              <FormInput
                label="Descrição"
                placeholder="Banner 1x2m"
                value={it.descricao}
                onChangeText={(v) => setItemField(idx, "descricao", v)}
                required
              />
              <FormInput
                label="Material"
                placeholder="Lona 440g"
                value={it.material}
                onChangeText={(v) => setItemField(idx, "material", v)}
              />
              <View className="flex-row gap-2">
                <View className="flex-1">
                  <FormInput
                    label="Largura (cm)"
                    placeholder="100"
                    value={it.larguraCm}
                    onChangeText={(v) => setItemField(idx, "larguraCm", v)}
                    keyboardType="numeric"
                  />
                </View>
                <View className="flex-1">
                  <FormInput
                    label="Altura (cm)"
                    placeholder="200"
                    value={it.alturaCm}
                    onChangeText={(v) => setItemField(idx, "alturaCm", v)}
                    keyboardType="numeric"
                  />
                </View>
                <View className="flex-1">
                  <FormInput
                    label="Qtd"
                    placeholder="1"
                    value={it.quantidade}
                    onChangeText={(v) => setItemField(idx, "quantidade", v)}
                    keyboardType="numeric"
                  />
                </View>
              </View>
              <Text className="text-xs text-muted mb-2">
                Área: {area.toFixed(2)} m²
              </Text>
              <FormInput
                label="Preço por m²"
                placeholder="45.00"
                value={it.precoPorM2}
                onChangeText={(v) => setItemField(idx, "precoPorM2", v)}
                keyboardType="numeric"
              />
              <FormInput
                label="Acabamento"
                placeholder="Ilhós + bainha"
                value={it.acabamento}
                onChangeText={(v) => setItemField(idx, "acabamento", v)}
              />
              <Text className="text-sm font-bold text-foreground mt-1">
                Subtotal: {BRL.format(tot)}
              </Text>
            </View>
          );
        })}
        <Pressable
          onPress={addItem}
          className="bg-surface border border-border rounded-lg py-2 mb-3"
        >
          <Text className="text-center text-primary font-semibold text-sm">
            + Adicionar item
          </Text>
        </Pressable>

        <Pressable
          onPress={handleSuggestPricing}
          disabled={suggestPricing.isPending}
          className="bg-primary/10 border border-primary/30 rounded-lg py-2 mb-3"
        >
          {suggestPricing.isPending ? (
            <View className="flex-row items-center justify-center gap-2">
              <ActivityIndicator size="small" />
              <Text className="text-primary text-sm font-semibold">
                Consultando IA…
              </Text>
            </View>
          ) : (
            <Text className="text-center text-primary font-semibold text-sm">
              🧠 Sugerir preços (IA)
            </Text>
          )}
        </Pressable>

        {aiPricing ? (
          <AiPricingCard
            suggestion={aiPricing}
            onApply={applyAiPricingItem}
            onDismiss={() => setAiPricing(null)}
          />
        ) : null}

        <View className="border-t border-border pt-2 mb-3">
          <Text className="text-lg font-bold text-foreground text-right">
            Total: {BRL.format(totalGeral)}
          </Text>
        </View>

        <Text className="text-sm font-medium text-foreground mb-2">
          Observações
        </Text>
        <TextInput
          value={form.observacoes}
          onChangeText={(v) => setField("observacoes", v)}
          multiline
          numberOfLines={3}
          className="border border-border rounded-lg px-3 py-2 text-foreground"
          style={{ minHeight: 70, textAlignVertical: "top" }}
          placeholder="Condições, prazo, forma de pagamento…"
        />
      </ModalDialog>

      {/* Price tables modal */}
      <PriceTablesModal
        visible={priceTableModal}
        onClose={() => setPriceTableModal(false)}
        priceTables={priceTables}
        onCreate={async (input) => {
          try {
            await createPT.mutateAsync(input);
            addToast("sucesso", "Tabela criada");
          } catch (err) {
            addToast(
              "erro",
              err instanceof Error ? err.message : "Erro ao criar tabela",
            );
          }
        }}
        onUpdate={async (input) => {
          try {
            await updatePT.mutateAsync(input);
            addToast("sucesso", "Tabela atualizada");
          } catch (err) {
            addToast("erro", err instanceof Error ? err.message : "Erro");
          }
        }}
        onDelete={async (id) => {
          try {
            await deletePT.mutateAsync({ id });
            addToast("sucesso", "Tabela removida");
          } catch (err) {
            addToast("erro", err instanceof Error ? err.message : "Erro");
          }
        }}
      />
    </OiScreen>
  );
}

function StatusBadge({ status }: { status: QuoteStatus }) {
  const map: Record<QuoteStatus, { bg: string; fg: string; label: string }> = {
    rascunho: { bg: "#E6F1FB", fg: "#0C447C", label: "Rascunho" },
    enviado: { bg: "#FAEEDA", fg: "#633806", label: "Enviado" },
    aprovado: { bg: "#EAF3DE", fg: "#27500A", label: "Aprovado" },
    rejeitado: { bg: "#FCEBEB", fg: "#791F1F", label: "Rejeitado" },
    convertido: { bg: "#EEEDFE", fg: "#3C3489", label: "Convertido" },
  };
  const s = map[status];
  return (
    <View
      style={{ backgroundColor: s.bg }}
      className="px-2 py-1 rounded-full"
    >
      <Text style={{ color: s.fg }} className="text-xs font-semibold">
        {s.label}
      </Text>
    </View>
  );
}

interface PT {
  id: string;
  material: string;
  precoPorM2: number;
  acabamentoExtra: number;
  ativo: boolean;
}

function PriceTablesModal({
  visible,
  onClose,
  priceTables,
  onCreate,
  onUpdate,
  onDelete,
}: {
  visible: boolean;
  onClose: () => void;
  priceTables: PT[];
  onCreate: (input: {
    material: string;
    precoPorM2: number;
    acabamentoExtra?: number;
  }) => Promise<void> | void;
  onUpdate: (input: {
    id: string;
    material?: string;
    precoPorM2?: number;
    acabamentoExtra?: number;
  }) => Promise<void> | void;
  onDelete: (id: string) => Promise<void> | void;
}) {
  const [material, setMaterial] = useState("");
  const [preco, setPreco] = useState("");
  const [acabamento, setAcabamento] = useState("");

  const reset = () => {
    setMaterial("");
    setPreco("");
    setAcabamento("");
  };

  return (
    <ModalDialog
      visible={visible}
      title="Tabelas de preço"
      onClose={() => {
        reset();
        onClose();
      }}
      showConfirmation={false}
    >
      <View className="mb-4">
        <FormInput
          label="Material"
          placeholder="Lona 440g"
          value={material}
          onChangeText={setMaterial}
        />
        <FormInput
          label="Preço por m²"
          placeholder="45.00"
          value={preco}
          onChangeText={setPreco}
          keyboardType="numeric"
        />
        <FormInput
          label="Acabamento extra"
          placeholder="0"
          value={acabamento}
          onChangeText={setAcabamento}
          keyboardType="numeric"
        />
        <Button
          title="Adicionar"
          onPress={async () => {
            if (!material.trim() || !preco) return;
            await onCreate({
              material: material.trim(),
              precoPorM2: parseNumber(preco),
              acabamentoExtra: parseNumber(acabamento) || 0,
            });
            reset();
          }}
        />
      </View>
      <View>
        {priceTables.length === 0 ? (
          <Text className="text-muted text-xs">Nenhuma tabela cadastrada</Text>
        ) : (
          priceTables.map((pt) => (
            <View
              key={pt.id}
              className="flex-row justify-between items-center border-b border-border py-2"
            >
              <View className="flex-1">
                <Text className="text-foreground font-semibold text-sm">
                  {pt.material}
                </Text>
                <Text className="text-xs text-muted">
                  {BRL.format(pt.precoPorM2)}/m²
                  {pt.acabamentoExtra > 0
                    ? ` + ${BRL.format(pt.acabamentoExtra)}`
                    : ""}
                </Text>
              </View>
              <Pressable onPress={() => onDelete(pt.id)} hitSlop={8}>
                <Text className="text-error text-lg">×</Text>
              </Pressable>
            </View>
          ))
        )}
      </View>
    </ModalDialog>
  );
}

function AiPricingCard({
  suggestion,
  onApply,
  onDismiss,
}: {
  suggestion: {
    itensSugeridos: Array<{
      descricao: string;
      precoPorM2Sugerido: number;
      totalItem: number;
      justificativa: string;
    }>;
    totalGeral: number;
    margemSugerida: number;
    observacoes: string;
  };
  onApply: (idx: number, preco: number) => void;
  onDismiss: () => void;
}) {
  return (
    <View className="border border-primary/30 bg-primary/5 rounded-lg p-3 mb-3">
      <View className="flex-row justify-between items-center mb-2">
        <Text className="text-sm font-bold text-primary">
          🧠 Análise IA — Precificação
        </Text>
        <Pressable onPress={onDismiss} hitSlop={8}>
          <Text className="text-muted text-lg">×</Text>
        </Pressable>
      </View>
      {suggestion.itensSugeridos.map((it, idx) => (
        <View
          key={`ai-${idx}`}
          className="border-b border-border pb-2 mb-2"
        >
          <Text className="text-sm font-semibold text-foreground">
            Item {idx + 1}: {it.descricao}
          </Text>
          <Text className="text-xs text-muted">
            Preço/m² sugerido: {BRL.format(it.precoPorM2Sugerido)} • Total:{" "}
            {BRL.format(it.totalItem)}
          </Text>
          <Text className="text-xs text-muted italic mt-1">
            {it.justificativa}
          </Text>
          <Pressable
            onPress={() => onApply(idx, it.precoPorM2Sugerido)}
            className="bg-primary/20 px-3 py-1 rounded mt-1 self-start"
          >
            <Text className="text-primary text-xs font-semibold">
              Aplicar preço
            </Text>
          </Pressable>
        </View>
      ))}
      <Text className="text-sm font-bold text-foreground mt-2">
        Total geral sugerido: {BRL.format(suggestion.totalGeral)}
      </Text>
      <Text className="text-xs text-muted">
        Margem sugerida: {suggestion.margemSugerida}%
      </Text>
      {suggestion.observacoes ? (
        <Text className="text-xs text-muted mt-2 italic">
          {suggestion.observacoes}
        </Text>
      ) : null}
    </View>
  );
}
