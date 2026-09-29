import React, { useMemo, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  View,
} from "react-native";
import { router as expoRouter } from "expo-router";

import { OiHeader, OiScreen } from "@/components/oi";
import { FormInput, ModalDialog, Select, Toast } from "@/components/erp-ui";
import { useERP } from "@/lib/erp-context";
import {
  useCreateInventory,
  useDeleteInventory,
  useInventory,
  useLowStockAlert,
  useProdutos,
  useRecordMovement,
  useRestoreInventory,
  useUpdateInventory,
} from "@/lib/erp-queries";
import { useDebouncedValue } from "@/hooks/use-debounced-value";

type Tab = "todos" | "baixo" | "inativos";

const UNIDADE_OPTIONS = [
  { label: "Unidade (un)", value: "un" },
  { label: "Metro (m)", value: "m" },
  { label: "Metro² (m²)", value: "m²" },
  { label: "Quilo (kg)", value: "kg" },
  { label: "Litro (L)", value: "L" },
  { label: "Hora (h)", value: "h" },
];

const TIPO_MOV_OPTIONS = [
  { label: "Entrada (+)", value: "entrada" },
  { label: "Saída (-)", value: "saida" },
  { label: "Ajuste (=)", value: "ajuste" },
  { label: "Perda (-)", value: "perda" },
];

type TipoMov = "entrada" | "saida" | "ajuste" | "perda";

interface InventoryFormState {
  produtoId: string;
  codigo: string;
  nome: string;
  unidade: string;
  quantidade: string;
  estoqueMinimo: string;
  custoUnit: string;
  precoVenda: string;
  fornecedorPrincipal: string;
  localizacao: string;
}

const EMPTY_FORM: InventoryFormState = {
  produtoId: "",
  codigo: "",
  nome: "",
  unidade: "un",
  quantidade: "",
  estoqueMinimo: "",
  custoUnit: "",
  precoVenda: "",
  fornecedorPrincipal: "",
  localizacao: "",
};

export default function EstoqueScreen() {
  const { ui, addToast } = useERP();
  const [tab, setTab] = useState<Tab>("todos");
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search, 300);

  const inventoryQuery = useInventory({
    search: debouncedSearch || undefined,
    onlyLowStock: tab === "baixo" ? true : undefined,
    includeInactive: tab === "inativos" ? true : undefined,
  });
  const lowStockQuery = useLowStockAlert();
  const produtosQuery = useProdutos();
  const createMutation = useCreateInventory();
  const updateMutation = useUpdateInventory();
  const deleteMutation = useDeleteInventory();
  const restoreMutation = useRestoreInventory();
  const recordMutation = useRecordMovement();

  const items = (inventoryQuery.data ?? []).filter((i) =>
    tab === "inativos" ? !i.ativo : i.ativo,
  );
  const lowStockCount = lowStockQuery.data?.length ?? 0;
  const produtos = produtosQuery.data ?? [];

  const produtoOptions = useMemo(
    () => [
      { label: "— Nenhum —", value: "" },
      ...produtos.map((p) => ({ label: p.nome, value: p.id })),
    ],
    [produtos],
  );

  // ─── Create/Edit modal ──
  const [editingId, setEditingId] = useState<string | null>(null);
  const [formVisible, setFormVisible] = useState(false);
  const [form, setForm] = useState<InventoryFormState>(EMPTY_FORM);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const setField = <K extends keyof InventoryFormState>(
    key: K,
    value: InventoryFormState[K],
  ) => setForm((prev) => ({ ...prev, [key]: value }));

  const resetForm = () => {
    setEditingId(null);
    setForm(EMPTY_FORM);
    setErrors({});
  };

  const openCreate = () => {
    resetForm();
    setFormVisible(true);
  };

  const openEdit = (item: (typeof items)[number]) => {
    setEditingId(item.id);
    setForm({
      produtoId: item.produtoId ?? "",
      codigo: item.codigo ?? "",
      nome: item.nome,
      unidade: item.unidade,
      quantidade: String(item.quantidade),
      estoqueMinimo: String(item.estoqueMinimo),
      custoUnit: String(item.custoUnit),
      precoVenda: item.precoVenda != null ? String(item.precoVenda) : "",
      fornecedorPrincipal: item.fornecedorPrincipal ?? "",
      localizacao: item.localizacao ?? "",
    });
    setErrors({});
    setFormVisible(true);
  };

  const validateForm = () => {
    const next: Record<string, string> = {};
    if (!form.nome.trim()) next.nome = "Nome é obrigatório";
    if (!form.unidade.trim()) next.unidade = "Unidade é obrigatória";
    const numFields: Array<[keyof InventoryFormState, string]> = [
      ["quantidade", "Quantidade"],
      ["estoqueMinimo", "Estoque mínimo"],
      ["custoUnit", "Custo unitário"],
    ];
    for (const [k, label] of numFields) {
      const raw = form[k];
      if (raw === "") continue;
      const n = Number(raw);
      if (!Number.isFinite(n) || n < 0) next[k as string] = `${label} inválido`;
    }
    if (form.precoVenda !== "") {
      const n = Number(form.precoVenda);
      if (!Number.isFinite(n) || n < 0) next.precoVenda = "Preço de venda inválido";
    }
    setErrors(next);
    return Object.keys(next).length === 0;
  };

  const handleSave = async () => {
    if (!validateForm()) return;
    const payload = {
      produtoId: form.produtoId || undefined,
      codigo: form.codigo.trim() || undefined,
      nome: form.nome.trim(),
      unidade: form.unidade,
      estoqueMinimo: form.estoqueMinimo === "" ? 0 : Number(form.estoqueMinimo),
      custoUnit: form.custoUnit === "" ? 0 : Number(form.custoUnit),
      precoVenda: form.precoVenda === "" ? null : Number(form.precoVenda),
      fornecedorPrincipal: form.fornecedorPrincipal.trim() || undefined,
      localizacao: form.localizacao.trim() || undefined,
    };
    try {
      if (editingId) {
        await updateMutation.mutateAsync({ id: editingId, ...payload });
        addToast("sucesso", `Item "${payload.nome}" atualizado!`);
      } else {
        await createMutation.mutateAsync({
          ...payload,
          quantidade: form.quantidade === "" ? 0 : Number(form.quantidade),
        });
        addToast("sucesso", `Item "${payload.nome}" cadastrado!`);
      }
      setFormVisible(false);
      resetForm();
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao salvar item");
    }
  };

  const handleDelete = async (item: (typeof items)[number]) => {
    try {
      await deleteMutation.mutateAsync({ id: item.id });
      addToast("sucesso", `"${item.nome}" inativado`);
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao inativar");
    }
  };

  const handleRestore = async (item: (typeof items)[number]) => {
    try {
      await restoreMutation.mutateAsync({ id: item.id });
      addToast("sucesso", `"${item.nome}" reativado`);
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao reativar");
    }
  };

  // ─── Movement modal ──
  const [movVisible, setMovVisible] = useState(false);
  const [movTargetId, setMovTargetId] = useState<string | null>(null);
  const [movTargetName, setMovTargetName] = useState<string>("");
  const [movTipo, setMovTipo] = useState<TipoMov>("entrada");
  const [movQuantidade, setMovQuantidade] = useState("");
  const [movMotivo, setMovMotivo] = useState("");
  const [movErrors, setMovErrors] = useState<Record<string, string>>({});

  const openMovement = (item: (typeof items)[number]) => {
    setMovTargetId(item.id);
    setMovTargetName(item.nome);
    setMovTipo("entrada");
    setMovQuantidade("");
    setMovMotivo("");
    setMovErrors({});
    setMovVisible(true);
  };

  const handleRecord = async () => {
    const next: Record<string, string> = {};
    const q = Number(movQuantidade);
    if (!Number.isFinite(q) || q <= 0) next.quantidade = "Quantidade > 0";
    setMovErrors(next);
    if (Object.keys(next).length > 0 || !movTargetId) return;
    try {
      await recordMutation.mutateAsync({
        inventoryId: movTargetId,
        tipo: movTipo,
        quantidade: q,
        motivo: movMotivo.trim() || undefined,
      });
      addToast("sucesso", `Movimentação registrada em "${movTargetName}"`);
      setMovVisible(false);
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro na movimentação");
    }
  };

  const colorForStock = (q: number, min: number) => {
    if (q <= min) return "#791F1F"; // red
    if (q <= min * 2) return "#9A6A05"; // amber
    return "#27500A"; // green
  };

  return (
    <OiScreen edges={["top"]}>
      <OiHeader
        title="Estoque"
        eyebrow={`${inventoryQuery.data?.length ?? 0} itens`}
        actions={[{ icon: "plus", onPress: openCreate }]}
      />
      <ScrollView className="flex-1" contentContainerStyle={{ paddingBottom: 20 }}>

        <View className="px-4 pt-4">
          {ui.toasts.map((toast) => (
            <Toast key={toast.id} tipo={toast.tipo} mensagem={toast.mensagem} />
          ))}
        </View>

        {lowStockCount > 0 && tab !== "baixo" && (
          <View className="px-4 pt-2">
            <Pressable
              onPress={() => setTab("baixo")}
              className="bg-error/10 border border-error/30 rounded-lg px-4 py-3"
            >
              <Text className="text-error font-semibold">
                ⚠ {lowStockCount}{" "}
                {lowStockCount === 1
                  ? "item com estoque baixo"
                  : "itens com estoque baixo"}
              </Text>
              <Text className="text-error text-xs mt-1">
                Toque para filtrar
              </Text>
            </Pressable>
          </View>
        )}

        <View className="px-4 pt-3">
          <SearchInput
            value={search}
            onChange={setSearch}
            placeholder="Buscar por nome ou código"
          />
        </View>

        <View className="px-4 py-3 flex-row gap-2">
          {([
            { id: "todos", label: "Todos" },
            { id: "baixo", label: "Baixo estoque" },
            { id: "inativos", label: "Inativos" },
          ] as { id: Tab; label: string }[]).map((t) => {
            const active = tab === t.id;
            return (
              <Pressable
                key={t.id}
                onPress={() => setTab(t.id)}
                className={`px-3 py-2 rounded-lg border ${
                  active ? "bg-primary border-primary" : "bg-surface border-border"
                }`}
              >
                <Text
                  className={`text-xs font-semibold ${active ? "text-white" : "text-foreground"}`}
                >
                  {t.label}
                </Text>
              </Pressable>
            );
          })}
        </View>

        <View className="px-4 py-2">
          {inventoryQuery.isLoading ? (
            <View className="py-12 items-center">
              <ActivityIndicator />
            </View>
          ) : inventoryQuery.isError ? (
            <View className="bg-error/10 rounded-lg p-4">
              <Text className="text-error text-sm">
                {inventoryQuery.error?.message ?? "Erro ao carregar estoque"}
              </Text>
            </View>
          ) : items.length > 0 ? (
            items.map((item) => {
              const stockColor = colorForStock(item.quantidade, item.estoqueMinimo);
              return (
                <View
                  key={item.id}
                  className="bg-surface rounded-lg p-3 mb-2 border border-border"
                >
                  <View className="flex-row justify-between items-start">
                    <View className="flex-1">
                      <Text className="font-semibold text-foreground text-sm">
                        {item.nome}
                        {item.codigo ? (
                          <Text className="text-muted"> ({item.codigo})</Text>
                        ) : null}
                      </Text>
                      <View className="flex-row items-center gap-2 mt-1">
                        <Text
                          style={{ color: stockColor }}
                          className="text-base font-bold"
                        >
                          {item.quantidade} {item.unidade}
                        </Text>
                        <Text className="text-xs text-muted">
                          mín: {item.estoqueMinimo}
                        </Text>
                      </View>
                    </View>
                  </View>
                  <View className="flex-row gap-2 mt-3">
                    <Pressable
                      onPress={() =>
                        expoRouter.push(
                          // Typed routes regenerated by Expo at build time; cast
                          // is safe because the file exists in the app router.
                          `/estoque/${item.id}` as never,
                        )
                      }
                      className="flex-1 bg-primary/10 px-2 py-2 rounded-lg"
                    >
                      <Text className="text-primary text-xs font-semibold text-center">
                        📊 Movimentações
                      </Text>
                    </Pressable>
                    <Pressable
                      onPress={() => openMovement(item)}
                      className="flex-1 bg-surface border border-border px-2 py-2 rounded-lg"
                    >
                      <Text className="text-foreground text-xs font-semibold text-center">
                        + Movimento
                      </Text>
                    </Pressable>
                    <Pressable
                      onPress={() => openEdit(item)}
                      className="flex-1 bg-surface border border-border px-2 py-2 rounded-lg"
                    >
                      <Text className="text-foreground text-xs font-semibold text-center">
                        Editar
                      </Text>
                    </Pressable>
                    {item.ativo ? (
                      <Pressable
                        onPress={() => handleDelete(item)}
                        className="px-3 py-2 rounded-lg"
                        hitSlop={8}
                      >
                        <Text className="text-error text-lg">×</Text>
                      </Pressable>
                    ) : (
                      <Pressable
                        onPress={() => handleRestore(item)}
                        className="px-3 py-2 rounded-lg"
                      >
                        <Text className="text-primary text-xs font-semibold">
                          ↺
                        </Text>
                      </Pressable>
                    )}
                  </View>
                </View>
              );
            })
          ) : (
            <View className="bg-surface rounded-lg p-6 items-center">
              <Text className="text-muted text-sm">
                {tab === "baixo"
                  ? "Nenhum item com estoque baixo"
                  : tab === "inativos"
                    ? "Nenhum item inativo"
                    : debouncedSearch
                      ? `Nada encontrado para "${debouncedSearch}"`
                      : "Nenhum item cadastrado"}
              </Text>
            </View>
          )}
        </View>
      </ScrollView>

      <ModalDialog
        visible={formVisible}
        title={editingId ? "Editar Item" : "Novo Item"}
        onClose={() => {
          setFormVisible(false);
          resetForm();
        }}
        onConfirm={handleSave}
        confirmText={editingId ? "Salvar Alterações" : "Cadastrar"}
        cancelText="Cancelar"
      >
        {produtos.length > 0 && (
          <Select
            label="Produto vinculado (opcional)"
            options={produtoOptions}
            value={form.produtoId}
            onValueChange={(v) => setField("produtoId", v)}
          />
        )}
        <FormInput
          label="Código"
          placeholder="SKU / código interno"
          value={form.codigo}
          onChangeText={(v) => setField("codigo", v)}
          returnKeyType="next"
        />
        <FormInput
          label="Nome"
          placeholder="Ex: Tinta acrílica branca"
          value={form.nome}
          onChangeText={(v) => setField("nome", v)}
          error={errors.nome}
          required
          returnKeyType="next"
        />
        <Select
          label="Unidade"
          options={UNIDADE_OPTIONS}
          value={form.unidade}
          onValueChange={(v) => setField("unidade", v)}
          required
        />
        {!editingId && (
          <FormInput
            label="Quantidade inicial"
            placeholder="0"
            value={form.quantidade}
            onChangeText={(v) => setField("quantidade", v)}
            error={errors.quantidade}
            keyboardType="numeric"
            returnKeyType="next"
          />
        )}
        <FormInput
          label="Estoque mínimo"
          placeholder="0"
          value={form.estoqueMinimo}
          onChangeText={(v) => setField("estoqueMinimo", v)}
          error={errors.estoqueMinimo}
          keyboardType="numeric"
          returnKeyType="next"
        />
        <FormInput
          label="Custo unitário (R$)"
          placeholder="0"
          value={form.custoUnit}
          onChangeText={(v) => setField("custoUnit", v)}
          error={errors.custoUnit}
          keyboardType="numeric"
          returnKeyType="next"
        />
        <FormInput
          label="Preço de venda (R$)"
          placeholder="opcional"
          value={form.precoVenda}
          onChangeText={(v) => setField("precoVenda", v)}
          error={errors.precoVenda}
          keyboardType="numeric"
          returnKeyType="next"
        />
        <FormInput
          label="Fornecedor principal"
          placeholder="opcional"
          value={form.fornecedorPrincipal}
          onChangeText={(v) => setField("fornecedorPrincipal", v)}
          returnKeyType="next"
        />
        <FormInput
          label="Localização"
          placeholder="Ex: Prateleira A2"
          value={form.localizacao}
          onChangeText={(v) => setField("localizacao", v)}
          returnKeyType="done"
        />
      </ModalDialog>

      <ModalDialog
        visible={movVisible}
        title={`Movimentar: ${movTargetName}`}
        onClose={() => setMovVisible(false)}
        onConfirm={handleRecord}
        confirmText="Registrar"
        cancelText="Cancelar"
      >
        <Select
          label="Tipo"
          options={TIPO_MOV_OPTIONS}
          value={movTipo}
          onValueChange={(v) => setMovTipo(v as TipoMov)}
          required
        />
        <FormInput
          label="Quantidade"
          placeholder={movTipo === "ajuste" ? "Saldo final desejado" : "Ex: 5"}
          value={movQuantidade}
          onChangeText={setMovQuantidade}
          error={movErrors.quantidade}
          required
          keyboardType="numeric"
          returnKeyType="next"
        />
        <FormInput
          label="Motivo"
          placeholder="opcional"
          value={movMotivo}
          onChangeText={setMovMotivo}
          returnKeyType="done"
        />
      </ModalDialog>
    </OiScreen>
  );
}

function SearchInput({
  value,
  onChange,
  placeholder,
}: {
  value: string;
  onChange: (v: string) => void;
  placeholder: string;
}) {
  return (
    <View className="flex-row items-center bg-surface border border-border rounded-lg px-3">
      <TextInput
        value={value}
        onChangeText={onChange}
        placeholder={placeholder}
        className="flex-1 py-2 text-foreground"
        autoCorrect={false}
        returnKeyType="search"
      />
      {value.length > 0 && (
        <Pressable onPress={() => onChange("")} hitSlop={8} className="px-2">
          <Text className="text-muted">×</Text>
        </Pressable>
      )}
    </View>
  );
}
