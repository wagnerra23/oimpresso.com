/**
 * Produtos — Oimpresso design.
 *
 * Catálogo simples com busca debounced + ordenação por nome/preço + edit-on-tap
 * via OiSheet. Toda lógica (CRUD, optimistic updates) preservada.
 */
import { useMemo, useState } from "react";
import { ActivityIndicator, ScrollView, Text, View } from "react-native";
import { useRouter } from "expo-router";

import {
  OiBtn,
  OiCard,
  OiChip,
  OiChips,
  OiEmpty,
  OiHeader,
  OiList,
  OiListRow,
  OiMoney,
  OiScreen,
  OiSearch,
  OiSection,
  OiSheet,
  OiStatus,
} from "@/components/oi";
import { FormInput, Select, Toast } from "@/components/erp-ui";
import { useERP } from "@/lib/erp-context";
import {
  useCreateProduto,
  useDeleteProduto,
  useProdutos,
  useUpdateProduto,
} from "@/lib/erp-queries";
import type { Produto } from "@/lib/erp-context";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useOiTheme } from "@/lib/oi-theme-context";
import { fonts } from "@/lib/oi-theme";

const CATEGORIAS = [
  { label: "Desenvolvimento", value: "Desenvolvimento" },
  { label: "Design", value: "Design" },
  { label: "Consultoria", value: "Consultoria" },
  { label: "Suporte", value: "Suporte" },
  { label: "Outro", value: "Outro" },
];

type SortBy = "nome" | "preco";
type SortOrder = "asc" | "desc";

export default function ProdutosScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const { ui, addToast } = useERP();
  const produtosQuery = useProdutos();
  const createProduto = useCreateProduto();
  const updateProduto = useUpdateProduto();
  const deleteProduto = useDeleteProduto();

  const produtos = produtosQuery.data ?? [];

  // Form state (used for both create and edit).
  const [editing, setEditing] = useState<Produto | null>(null);
  const [sheetVisible, setSheetVisible] = useState(false);
  const [nome, setNome] = useState("");
  const [categoria, setCategoria] = useState("Desenvolvimento");
  const [preco, setPreco] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Sort + search (F1-04).
  const [sortBy, setSortBy] = useState<SortBy>("nome");
  const [sortOrder, setSortOrder] = useState<SortOrder>("asc");
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search, 300);

  const validateForm = () => {
    const newErrors: Record<string, string> = {};
    if (!nome.trim()) newErrors.nome = "Nome é obrigatório";
    if (!preco || parseFloat(preco) <= 0) newErrors.preco = "Preço deve ser maior que 0";
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const resetForm = () => {
    setEditing(null);
    setNome("");
    setCategoria("Desenvolvimento");
    setPreco("");
    setErrors({});
  };

  /**
   * v2: criação migrou para o wizard de 5 etapas em /produtos/new. O OiSheet
   * permanece somente para edição rápida (campo único).
   */
  const openCreate = () => {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    router.push("/produtos/new" as any);
  };

  const openEdit = (produto: Produto) => {
    setEditing(produto);
    setNome(produto.nome);
    setCategoria(produto.categoria);
    setPreco(String(produto.preco));
    setErrors({});
    setSheetVisible(true);
  };

  const handleSave = async () => {
    if (!validateForm()) return;
    try {
      if (editing) {
        await updateProduto.mutateAsync({
          id: editing.id,
          nome: nome.trim(),
          categoria,
          preco: parseFloat(preco),
        });
        addToast("sucesso", `Produto "${nome.trim()}" atualizado!`);
      } else {
        await createProduto.mutateAsync({
          nome: nome.trim(),
          categoria,
          preco: parseFloat(preco),
        });
        addToast("sucesso", `Produto "${nome.trim()}" adicionado!`);
      }
      setSheetVisible(false);
      resetForm();
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao salvar produto");
    }
  };

  const handleDelete = async (produto: Produto) => {
    try {
      await deleteProduto.mutateAsync({ id: produto.id });
      addToast("sucesso", `Produto "${produto.nome}" removido!`);
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao remover produto");
    }
  };

  const produtosOrdenados = useMemo(() => {
    const term = debouncedSearch.trim().toLowerCase();
    const filtered = term
      ? produtos.filter(
          (p) =>
            p.nome.toLowerCase().includes(term) ||
            p.categoria.toLowerCase().includes(term),
        )
      : produtos.slice();
    filtered.sort((a, b) =>
      sortBy === "nome" ? a.nome.localeCompare(b.nome) : a.preco - b.preco,
    );
    if (sortOrder === "desc") filtered.reverse();
    return filtered;
  }, [produtos, sortBy, sortOrder, debouncedSearch]);

  const toggleSort = (by: SortBy) => {
    if (sortBy === by) {
      setSortOrder(sortOrder === "asc" ? "desc" : "asc");
    } else {
      setSortBy(by);
      setSortOrder("asc");
    }
  };

  return (
    <OiScreen edges={["top"]}>
      <OiHeader
        title="Catálogo"
        eyebrow={`${produtos.length} produto${produtos.length === 1 ? "" : "s"}`}
        actions={[{ icon: "plus", onPress: openCreate }]}
      >
        <OiSearch
          value={search}
          onChangeText={setSearch}
          placeholder="Buscar produto ou categoria"
        />
      </OiHeader>

      {ui.toasts.length > 0 ? (
        <View style={{ paddingHorizontal: 16, paddingTop: 12 }}>
          {ui.toasts.map((toast) => (
            <Toast key={toast.id} tipo={toast.tipo} mensagem={toast.mensagem} />
          ))}
        </View>
      ) : null}

      <ScrollView
        contentContainerStyle={{ paddingBottom: 24 }}
        showsVerticalScrollIndicator={false}
      >
        {produtosQuery.isLoading ? (
          <View style={{ paddingVertical: 48, alignItems: "center" }}>
            <ActivityIndicator color={palette.accent} />
          </View>
        ) : produtosQuery.isError ? (
          <OiSection>
            <OiCard>
              <Text style={{ color: palette.danger, fontFamily: fonts.sans }}>
                {produtosQuery.error?.message ?? "Erro ao carregar produtos"}
              </Text>
            </OiCard>
          </OiSection>
        ) : produtos.length === 0 ? (
          <OiEmpty
            title="Nenhum produto cadastrado"
            subtitle="Toque em + para adicionar o primeiro produto ao seu catálogo."
            action={{ label: "Adicionar produto", onPress: openCreate }}
          />
        ) : (
          <>
            <OiSection>
              <OiChips>
                <OiChip
                  label={`Nome ${sortBy === "nome" ? (sortOrder === "asc" ? "↑" : "↓") : ""}`}
                  active={sortBy === "nome"}
                  onPress={() => toggleSort("nome")}
                />
                <OiChip
                  label={`Preço ${sortBy === "preco" ? (sortOrder === "asc" ? "↑" : "↓") : ""}`}
                  active={sortBy === "preco"}
                  onPress={() => toggleSort("preco")}
                />
              </OiChips>
            </OiSection>

            {produtosOrdenados.length === 0 ? (
              <OiSection>
                <OiCard>
                  <Text style={{ color: palette.textMute, fontFamily: fonts.sans, textAlign: "center" }}>
                    Nada encontrado para “{search}”.
                  </Text>
                </OiCard>
              </OiSection>
            ) : (
              <OiSection noPad>
                <OiList>
                  {produtosOrdenados.map((item, idx) => (
                    <OiListRow
                      key={item.id}
                      title={item.nome}
                      subtitle={item.categoria}
                      onPress={() => router.push(`/produtos/${item.id}` as never)}
                      last={idx === produtosOrdenados.length - 1}
                      right={
                        <View style={{ alignItems: "flex-end", gap: 4 }}>
                          <OiMoney value={item.preco} size={14} weight="semibold" />
                          <OiStatus
                            variant="accent"
                            label="editar"
                          />
                        </View>
                      }
                    />
                  ))}
                </OiList>
              </OiSection>
            )}
          </>
        )}
      </ScrollView>

      <OiSheet
        visible={sheetVisible}
        title={editing ? "Editar Produto" : "Novo Produto"}
        onClose={() => {
          setSheetVisible(false);
          resetForm();
        }}
      >
        <View style={{ padding: 16, gap: 12 }}>
          <FormInput
            label="Nome do Produto"
            placeholder="Ex: Banner 2x1m em lona"
            value={nome}
            onChangeText={setNome}
            error={errors.nome}
            required
            returnKeyType="next"
          />
          <Select
            label="Categoria"
            options={CATEGORIAS}
            value={categoria}
            onValueChange={setCategoria}
            required
          />
          <FormInput
            label="Preço (R$)"
            placeholder="Ex: 150"
            value={preco}
            onChangeText={setPreco}
            error={errors.preco}
            required
            keyboardType="numeric"
            returnKeyType="done"
          />
          <View style={{ flexDirection: "row", gap: 8, marginTop: 4 }}>
            {editing ? (
              <OiBtn
                label="Excluir"
                variant="danger"
                onPress={() => {
                  void handleDelete(editing);
                  setSheetVisible(false);
                  resetForm();
                }}
              />
            ) : null}
            <OiBtn
              label={editing ? "Salvar alterações" : "Adicionar produto"}
              variant="primary"
              block
              onPress={handleSave}
            />
          </View>
        </View>
      </OiSheet>
    </OiScreen>
  );
}
