/**
 * Pedidos — Oimpresso design.
 *
 * Status tabs (Novo/Aprovado/Execução/Entregue) com contadores, lista de cards
 * por status, FAB para criar, sheet de edição. Toda a lógica de tRPC e
 * mutations otimistas é preservada (useCreatePedido/useUpdatePedido/etc).
 */
import { useMemo, useState } from "react";
import { ActivityIndicator, Pressable, ScrollView, Text, View } from "react-native";
import { useRouter } from "expo-router";

import { FormInput, ModalDialog, Select, Toast } from "@/components/erp-ui";
import {
  OiBtn,
  OiCard,
  OiChip,
  OiChips,
  OiEmpty,
  OiFab,
  OiHeader,
  OiIcon,
  OiMoney,
  OiOrigin,
  OiScreen,
  OiSearch,
  OiSection,
  OiStatus,
  type OiStatusVariant,
} from "@/components/oi";
import { useERP } from "@/lib/erp-context";
import {
  useCreatePedido,
  useCustomers,
  useDeletePedido,
  usePedidos,
  useProdutos,
  useSendWhatsappCustom,
  useUpdatePedido,
} from "@/lib/erp-queries";
import type { Pedido } from "@/lib/erp-context";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";

const STATUS_TABS: { id: Pedido["status"]; label: string; variant: OiStatusVariant }[] = [
  { id: "novo", label: "Novo", variant: "accent" },
  { id: "aprovado", label: "Aprovado", variant: "warn" },
  { id: "execucao", label: "Em Execução", variant: "warn" },
  { id: "entregue", label: "Entregue", variant: "ok" },
];

const PROXIMO_STATUS: Record<Pedido["status"], Pedido["status"]> = {
  novo: "aprovado",
  aprovado: "execucao",
  execucao: "entregue",
  entregue: "entregue",
};

function formatDate(iso: string) {
  try {
    return new Date(iso).toLocaleDateString("pt-BR", { day: "2-digit", month: "2-digit" });
  } catch {
    return iso;
  }
}

export default function VendasScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const { ui, addToast } = useERP();
  const pedidosQuery = usePedidos();
  const produtosQuery = useProdutos();
  const customersQuery = useCustomers();
  const createPedido = useCreatePedido();
  const updatePedido = useUpdatePedido();
  const deletePedido = useDeletePedido();
  const sendWhatsapp = useSendWhatsappCustom();

  const pedidos = pedidosQuery.data ?? [];
  const produtos = produtosQuery.data ?? [];
  const customers = customersQuery.data ?? [];

  // Form / edit state
  const [modalVisible, setModalVisible] = useState(false);
  const [editing, setEditing] = useState<Pedido | null>(null);
  const [customerId, setCustomerId] = useState("");
  const [cliente, setCliente] = useState("");
  const [produtoId, setProdutoId] = useState("");
  const [valor, setValor] = useState("");
  const [status, setStatus] = useState<Pedido["status"]>("novo");
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Tabs + search
  const [activeTab, setActiveTab] = useState<Pedido["status"]>("novo");
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search, 300);

  const produtoOptions = useMemo(
    () =>
      produtos.map((p) => ({
        label: `${p.nome} — R$ ${p.preco.toLocaleString("pt-BR")}`,
        value: p.id,
      })),
    [produtos],
  );

  const customerOptions = useMemo(
    () => [
      { label: "— Selecione um cliente —", value: "" },
      ...customers.map((c) => ({ label: c.nome, value: c.id })),
    ],
    [customers],
  );

  const resetForm = () => {
    setEditing(null);
    setCustomerId("");
    setCliente("");
    setProdutoId(produtos[0]?.id ?? "");
    setValor("");
    setStatus("novo");
    setErrors({});
  };

  const openCreate = () => {
    resetForm();
    setProdutoId(produtos[0]?.id ?? "");
    setModalVisible(true);
  };

  const openEdit = (pedido: Pedido) => {
    const produto = produtos.find((p) => p.nome === pedido.produto);
    const match = customers.find((c) => c.nome === pedido.cliente);
    setEditing(pedido);
    setCustomerId(match?.id ?? "");
    setCliente(pedido.cliente);
    setProdutoId(produto?.id ?? "");
    setValor(String(pedido.valor));
    setStatus(pedido.status);
    setErrors({});
    setModalVisible(true);
  };

  const validateForm = () => {
    const newErrors: Record<string, string> = {};
    if (!customerId && !cliente.trim()) newErrors.cliente = "Cliente é obrigatório";
    if (!produtoId) newErrors.produto = "Produto é obrigatório";
    if (!valor || parseFloat(valor) <= 0) newErrors.valor = "Valor deve ser maior que 0";
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSave = async () => {
    if (!validateForm()) return;
    const produto = produtos.find((p) => p.id === produtoId);
    const nomeProduto = produto?.nome ?? "";
    const selectedCustomer = customers.find((c) => c.id === customerId);
    const effectiveCliente = selectedCustomer?.nome ?? cliente.trim();
    try {
      if (editing) {
        await updatePedido.mutateAsync({
          id: editing.id,
          cliente: effectiveCliente,
          produto: nomeProduto,
          valor: parseFloat(valor),
          status,
          data: editing.data,
          tipo: editing.tipo,
          customerId: customerId || null,
        });
        addToast("sucesso", `Pedido de ${effectiveCliente} atualizado!`);
      } else {
        await createPedido.mutateAsync({
          cliente: effectiveCliente,
          customerId: customerId || undefined,
          produto: nomeProduto,
          valor: parseFloat(valor),
          data: new Date().toISOString(),
          tipo: "Venda",
        });
        addToast("sucesso", `Pedido de ${effectiveCliente} adicionado!`);
      }
      setModalVisible(false);
      resetForm();
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao salvar pedido");
    }
  };

  const handleAdvance = async (pedido: Pedido) => {
    const next = PROXIMO_STATUS[pedido.status];
    if (next === pedido.status) return;
    try {
      await updatePedido.mutateAsync({ ...pedido, status: next });
      addToast("sucesso", `Pedido avançado para ${next}!`);
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao avançar pedido");
    }
  };

  const handleDelete = async (pedido: Pedido) => {
    try {
      await deletePedido.mutateAsync({ id: pedido.id });
      addToast("sucesso", "Pedido removido!");
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao remover pedido");
    }
  };

  const handleWhatsApp = async (p: Pedido) => {
    const customer = customers.find((c) => c.nome === p.cliente);
    const telefone = customer?.telefone ?? null;
    if (!telefone) {
      addToast("erro", "Pedido sem telefone (cliente sem cadastro?)");
      return;
    }
    const mensagem = `Olá ${p.cliente}, atualização do seu pedido (${p.produto}): status atual ${p.status}.`;
    try {
      const res = await sendWhatsapp.mutateAsync({
        customerId: customer?.id,
        telefone,
        mensagem,
        referenciaTipo: "pedido",
        referenciaId: p.id,
      });
      if (res.ok) addToast("sucesso", "WhatsApp enviado!");
      else addToast("erro", res.error ?? "Falha ao enviar");
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao enviar");
    }
  };

  const contadores = useMemo(
    () =>
      STATUS_TABS.reduce<Record<Pedido["status"], number>>(
        (acc, t) => {
          acc[t.id] = pedidos.filter((p) => p.status === t.id).length;
          return acc;
        },
        { novo: 0, aprovado: 0, execucao: 0, entregue: 0 },
      ),
    [pedidos],
  );

  const pedidosAtivos = useMemo(() => {
    const term = debouncedSearch.trim().toLowerCase();
    return pedidos
      .filter((p) => p.status === activeTab)
      .filter((p) =>
        term
          ? p.cliente.toLowerCase().includes(term) ||
            p.produto.toLowerCase().includes(term) ||
            p.id.toLowerCase().includes(term)
          : true,
      );
  }, [pedidos, activeTab, debouncedSearch]);

  const ativos = pedidos.filter((p) => p.status !== "entregue").length;

  return (
    <OiScreen edges={["top"]}>
      <OiHeader
        title="Pedidos"
        eyebrow={`${pedidos.length} no total · ${ativos} ativos`}
        actions={[{ icon: "filter", onPress: () => {} }]}
      >
        <OiSearch
          value={search}
          onChangeText={setSearch}
          placeholder="OS, cliente, produto…"
        />
        <OiChips>
          {STATUS_TABS.map((t) => (
            <OiChip
              key={t.id}
              label={t.label}
              count={contadores[t.id]}
              active={activeTab === t.id}
              onPress={() => setActiveTab(t.id)}
            />
          ))}
        </OiChips>
      </OiHeader>

      {ui.toasts.length > 0 ? (
        <View style={{ paddingHorizontal: 16, paddingTop: 12 }}>
          {ui.toasts.map((toast) => (
            <Toast key={toast.id} tipo={toast.tipo} mensagem={toast.mensagem} />
          ))}
        </View>
      ) : null}

      <ScrollView
        contentContainerStyle={{ paddingBottom: 96 }}
        showsVerticalScrollIndicator={false}
      >
        {pedidosQuery.isLoading ? (
          <View style={{ paddingVertical: 48, alignItems: "center" }}>
            <ActivityIndicator color={palette.accent} />
          </View>
        ) : pedidosQuery.isError ? (
          <OiSection>
            <OiCard>
              <Text style={{ color: palette.danger, fontFamily: fonts.sans }}>
                {pedidosQuery.error?.message ?? "Erro ao carregar pedidos"}
              </Text>
            </OiCard>
          </OiSection>
        ) : pedidosAtivos.length === 0 ? (
          <OiEmpty
            icon="inbox"
            title="Nenhum pedido neste status"
            subtitle={
              search.length > 0
                ? `Nada encontrado para "${search}".`
                : "Crie um novo pedido usando o botão +."
            }
            action={
              search.length === 0
                ? { label: "Novo pedido", onPress: openCreate }
                : undefined
            }
          />
        ) : (
          <View style={{ paddingHorizontal: 16, paddingTop: 12, gap: 10 }}>
            {pedidosAtivos.map((p) => {
              const tab = STATUS_TABS.find((t) => t.id === p.status)!;
              return (
                <Pressable
                  key={p.id}
                  onPress={() => router.push(`/pedidos/${p.id}` as never)}
                  style={({ pressed }) => ({
                    backgroundColor: palette.surface,
                    borderColor: palette.border,
                    borderWidth: 1,
                    borderLeftWidth: 3,
                    borderLeftColor:
                      p.status === "novo"
                        ? palette.accent
                        : p.status === "entregue"
                          ? palette.ok
                          : palette.warn,
                    borderRadius: radius.md,
                    padding: 12,
                    gap: 6,
                    opacity: pressed ? 0.85 : 1,
                  })}
                >
                  <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                    <OiOrigin kind="CRM" />
                    <Text
                      style={{
                        fontSize: 11.5,
                        fontFamily: fonts.monoSemibold,
                        color: palette.textMute,
                      }}
                    >
                      {p.id.slice(0, 8).toUpperCase()}
                    </Text>
                    <OiStatus label={tab.label} variant={tab.variant} />
                    <View style={{ flex: 1 }} />
                    <OiMoney value={p.valor} size={14} weight="semibold" />
                  </View>
                  <Text
                    style={{
                      fontSize: 14,
                      fontFamily: fonts.sansSemibold,
                      color: palette.text,
                      letterSpacing: -0.1,
                    }}
                  >
                    {p.cliente}
                  </Text>
                  <Text
                    numberOfLines={1}
                    style={{
                      fontSize: 12,
                      color: palette.textDim,
                      fontFamily: fonts.sans,
                    }}
                  >
                    {p.produto}
                  </Text>
                  <View style={{ flexDirection: "row", alignItems: "center", gap: 8, marginTop: 2 }}>
                    <Text
                      style={{
                        fontSize: 10.5,
                        fontFamily: fonts.mono,
                        color: palette.textMute,
                      }}
                    >
                      {formatDate(p.data)}
                    </Text>
                    <View style={{ flex: 1 }} />
                    {p.status !== "entregue" ? (
                      <OiBtn
                        size="sm"
                        label="Avançar"
                        leftIcon="chev-r"
                        onPress={() => handleAdvance(p)}
                      />
                    ) : null}
                    <OiBtn
                      size="sm"
                      label="WhatsApp"
                      leftIcon="whatsapp"
                      onPress={() => handleWhatsApp(p)}
                    />
                    <Pressable
                      onPress={() => handleDelete(p)}
                      hitSlop={8}
                      style={{
                        width: 32, height: 32, alignItems: "center", justifyContent: "center",
                        borderRadius: radius.sm,
                      }}
                    >
                      <OiIcon name="trash" size={16} color={palette.textMute} />
                    </Pressable>
                  </View>
                </Pressable>
              );
            })}
          </View>
        )}
      </ScrollView>

      <OiFab onPress={openCreate} bottom={24} />

      <ModalDialog
        visible={modalVisible}
        title={editing ? "Editar Pedido" : "Novo Pedido"}
        onClose={() => {
          setModalVisible(false);
          resetForm();
        }}
        onConfirm={handleSave}
        confirmText={editing ? "Salvar" : "Adicionar"}
        cancelText="Cancelar"
      >
        {customers.length > 0 && (
          <Select
            label="Cliente cadastrado"
            options={customerOptions}
            value={customerId}
            onValueChange={(v) => {
              setCustomerId(v);
              if (v) setCliente("");
            }}
          />
        )}
        {!customerId && (
          <FormInput
            label={customers.length > 0 ? "Ou digite um novo cliente" : "Nome do Cliente"}
            placeholder="Ex: Empresa Alpha"
            value={cliente}
            onChangeText={setCliente}
            error={errors.cliente}
            required={customers.length === 0}
            returnKeyType="next"
          />
        )}
        <Select
          label="Produto"
          options={produtoOptions}
          value={produtoId}
          onValueChange={setProdutoId}
          error={errors.produto}
          required
        />
        <FormInput
          label="Valor (R$)"
          placeholder="Ex: 3500"
          value={valor}
          onChangeText={setValor}
          error={errors.valor}
          required
          keyboardType="numeric"
          returnKeyType="done"
        />
        {editing && (
          <Select
            label="Status"
            options={STATUS_TABS.map((t) => ({ label: t.label, value: t.id }))}
            value={status}
            onValueChange={(v) => setStatus(v as Pedido["status"])}
            required
          />
        )}
      </ModalDialog>
    </OiScreen>
  );
}
