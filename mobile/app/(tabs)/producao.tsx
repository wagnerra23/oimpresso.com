/**
 * Produção — Oimpresso design.
 *
 * Status chips (Fila/Andamento/Revisão/Concluído) + lista de OPs como cards
 * com borda colorida por prioridade. "Gerar OP" no header. Preserva todas as
 * mutations otimistas + actions de arte (anexar / copiar link de aprovação).
 */
import { useMemo, useState } from "react";
import {
  ActivityIndicator,
  Linking,
  Pressable,
  ScrollView,
  Text,
  View,
} from "react-native";
import * as Clipboard from "expo-clipboard";
import { useRouter } from "expo-router";

import { FormInput, ModalDialog, Select, Toast } from "@/components/erp-ui";
import {
  OiBtn,
  OiCard,
  OiChip,
  OiChips,
  OiEmpty,
  OiHeader,
  OiIcon,
  OiMiniPipeline,
  OiOrigin,
  OiScreen,
  OiSearch,
  OiStatus,
  type OiStatusVariant,
} from "@/components/oi";
import { hexAlpha } from "@/lib/oi-theme";
import { useERP } from "@/lib/erp-context";
import {
  useArtworksByOp,
  useCreateOP,
  useDeleteOP,
  useOps,
  usePedidos,
  useUpdateOP,
} from "@/lib/erp-queries";
import type { OP } from "@/lib/erp-context";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";

const STATUS_COLS: { id: OP["status"]; label: string; variant: OiStatusVariant }[] = [
  { id: "fila", label: "Fila", variant: "warn" },
  { id: "andamento", label: "Em Andamento", variant: "accent" },
  { id: "revisao", label: "Revisão", variant: "warn" },
  { id: "concluido", label: "Concluído", variant: "ok" },
];

const PROXIMO_STATUS: Record<OP["status"], OP["status"]> = {
  fila: "andamento",
  andamento: "revisao",
  revisao: "concluido",
  concluido: "concluido",
};

export default function ProducaoScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const { ui, addToast } = useERP();
  const opsQuery = useOps();
  const pedidosQuery = usePedidos();
  const createOP = useCreateOP();
  const updateOP = useUpdateOP();
  const deleteOP = useDeleteOP();

  const ops = opsQuery.data ?? [];
  const pedidos = pedidosQuery.data ?? [];

  const [editing, setEditing] = useState<OP | null>(null);
  const [modalVisible, setModalVisible] = useState(false);
  const [editStatus, setEditStatus] = useState<OP["status"]>("fila");
  const [editProduto, setEditProduto] = useState("");
  const [editCliente, setEditCliente] = useState("");

  const [activeTab, setActiveTab] = useState<OP["status"]>("fila");
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search, 300);

  const handleGerarOP = async () => {
    const pedidosAprovados = pedidos.filter((p) => p.status === "aprovado");
    const opsExistentes = new Set(ops.map((op) => op.pedidoId));
    let novas = 0;
    for (const pedido of pedidosAprovados) {
      if (!opsExistentes.has(pedido.id)) {
        try {
          await createOP.mutateAsync({
            pedidoId: pedido.id,
            cliente: pedido.cliente,
            produto: pedido.produto,
            status: "fila",
          });
          novas += 1;
        } catch (err) {
          addToast("erro", err instanceof Error ? err.message : "Erro ao gerar OP");
        }
      }
    }
    if (novas > 0) addToast("sucesso", `${novas} OP(s) gerada(s) com sucesso!`);
    else addToast("info", "Nenhum pedido aprovado para gerar OP");
  };

  const handleAdvance = async (op: OP) => {
    const next = PROXIMO_STATUS[op.status];
    if (next === op.status) return;
    try {
      await updateOP.mutateAsync({ ...op, status: next });
      addToast("sucesso", `OP avançada para ${next}!`);
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao avançar OP");
    }
  };

  const handleDelete = async (id: string) => {
    try {
      await deleteOP.mutateAsync({ id });
      addToast("sucesso", "OP removida!");
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao remover OP");
    }
  };

  const openEdit = (op: OP) => {
    setEditing(op);
    setEditCliente(op.cliente);
    setEditProduto(op.produto);
    setEditStatus(op.status);
    setModalVisible(true);
  };

  const handleSaveEdit = async () => {
    if (!editing) return;
    try {
      await updateOP.mutateAsync({
        ...editing,
        cliente: editCliente.trim(),
        produto: editProduto.trim(),
        status: editStatus,
      });
      addToast("sucesso", "OP atualizada!");
      setModalVisible(false);
      setEditing(null);
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao salvar OP");
    }
  };

  const opsFiltradas = useMemo(() => {
    const term = debouncedSearch.trim().toLowerCase();
    const filtered = term
      ? ops.filter(
          (op) =>
            op.cliente.toLowerCase().includes(term) ||
            op.produto.toLowerCase().includes(term),
        )
      : ops;
    return filtered.filter((op) => op.status === activeTab);
  }, [ops, debouncedSearch, activeTab]);

  const contadores = useMemo(
    () =>
      STATUS_COLS.reduce<Record<OP["status"], number>>(
        (acc, c) => {
          acc[c.id] = ops.filter((o) => o.status === c.id).length;
          return acc;
        },
        { fila: 0, andamento: 0, revisao: 0, concluido: 0 },
      ),
    [ops],
  );

  return (
    <OiScreen edges={["top"]}>
      <OiHeader
        title="Produção"
        eyebrow={`${ops.length} OPs · ${contadores.andamento} em andamento`}
        actions={[
          {
            icon: "refresh",
            onPress: handleGerarOP,
          },
        ]}
      >
        <OiSearch
          value={search}
          onChangeText={setSearch}
          placeholder="Buscar cliente ou produto"
        />
        {/* Bloco 5.2 — Triagem 4-stats tocáveis */}
        <View style={{ flexDirection: "row", gap: 6, marginTop: 8 }}>
          {(STATUS_COLS as readonly { id: OP["status"]; label: string; variant: OiStatusVariant }[]).map((s) => {
            const on = activeTab === s.id;
            const tint =
              s.variant === "ok"
                ? palette.ok
                : s.variant === "accent"
                  ? palette.accent
                  : s.variant === "danger"
                    ? palette.danger
                    : palette.warn;
            return (
              <Pressable
                key={s.id}
                onPress={() => setActiveTab(s.id)}
                style={{
                  flex: 1,
                  paddingVertical: 8,
                  borderRadius: radius.md,
                  borderWidth: 1,
                  borderColor: on ? tint : palette.border,
                  backgroundColor: on ? hexAlpha(tint, 0.16) : palette.surface,
                  alignItems: "center",
                }}
              >
                <Text style={{ fontFamily: fonts.sansBold, fontSize: 18, color: tint }}>
                  {contadores[s.id]}
                </Text>
                <Text
                  style={{
                    fontSize: 9.5,
                    color: palette.textMute,
                    fontFamily: fonts.sansSemibold,
                    textTransform: "uppercase",
                    letterSpacing: 0.6,
                  }}
                  numberOfLines={1}
                >
                  {s.label}
                </Text>
              </Pressable>
            );
          })}
        </View>
        <OiChips>
          {STATUS_COLS.map((t) => (
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
        contentContainerStyle={{ paddingBottom: 32 }}
        showsVerticalScrollIndicator={false}
      >
        {opsQuery.isLoading ? (
          <View style={{ paddingVertical: 48, alignItems: "center" }}>
            <ActivityIndicator color={palette.accent} />
          </View>
        ) : opsQuery.isError ? (
          <OiCard style={{ margin: 16 }}>
            <Text style={{ color: palette.danger, fontFamily: fonts.sans }}>
              {opsQuery.error?.message ?? "Erro ao carregar OPs"}
            </Text>
          </OiCard>
        ) : opsFiltradas.length === 0 ? (
          <OiEmpty
            icon="box"
            title="Nenhuma OP nesta etapa"
            subtitle={
              ops.length === 0
                ? "Clique no botão de refresh no header para gerar OPs de pedidos aprovados."
                : `Sem OPs em "${STATUS_COLS.find((s) => s.id === activeTab)?.label}".`
            }
            action={
              ops.length === 0
                ? { label: "Gerar OP", onPress: handleGerarOP }
                : undefined
            }
          />
        ) : (
          <View style={{ paddingHorizontal: 16, paddingTop: 12, gap: 10 }}>
            {opsFiltradas.map((op) => {
              const tab = STATUS_COLS.find((t) => t.id === op.status)!;
              return (
                <Pressable
                  key={op.id}
                  onPress={() => router.push(`/producao/${op.id}` as never)}
                  style={({ pressed }) => ({
                    backgroundColor: palette.surface,
                    borderColor: palette.border,
                    borderWidth: 1,
                    borderLeftWidth: 3,
                    borderLeftColor:
                      op.status === "concluido"
                        ? palette.ok
                        : op.status === "andamento"
                          ? palette.accent
                          : palette.warn,
                    borderRadius: radius.md,
                    padding: 12,
                    gap: 6,
                    opacity: pressed ? 0.85 : 1,
                  })}
                >
                  <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                    <OiOrigin kind="MFG" />
                    <Text
                      style={{
                        fontSize: 11.5,
                        fontFamily: fonts.monoSemibold,
                        color: palette.textMute,
                      }}
                    >
                      {op.id.slice(0, 8).toUpperCase()}
                    </Text>
                    <OiStatus label={tab.label} variant={tab.variant} />
                  </View>
                  <Text
                    style={{
                      fontSize: 14,
                      fontFamily: fonts.sansSemibold,
                      color: palette.text,
                      letterSpacing: -0.1,
                    }}
                  >
                    {op.produto}
                  </Text>
                  <Text
                    style={{
                      fontSize: 12,
                      color: palette.textDim,
                      fontFamily: fonts.sans,
                    }}
                  >
                    {op.cliente}
                  </Text>
                  {/* Bloco 5.3 — MiniPipeline 4 segmentos */}
                  <OiMiniPipeline
                    total={STATUS_COLS.length}
                    currentIndex={STATUS_COLS.findIndex((s) => s.id === op.status)}
                  />
                  <View style={{ flexDirection: "row", gap: 6, marginTop: 4 }}>
                    {op.status !== "concluido" ? (
                      <OiBtn
                        size="sm"
                        label={
                          op.status === "fila"
                            ? "Iniciar"
                            : op.status === "revisao"
                              ? "Expedir"
                              : "Concluir etapa"
                        }
                        leftIcon={op.status === "revisao" ? "send" : "chev-r"}
                        onPress={() => handleAdvance(op)}
                      />
                    ) : null}
                    <ArtworkActions
                      opId={op.id}
                      onInfo={(m) => addToast("info", m)}
                      onSuccess={(m) => addToast("sucesso", m)}
                    />
                    <View style={{ flex: 1 }} />
                    <Pressable
                      onPress={() => handleDelete(op.id)}
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

      <ModalDialog
        visible={modalVisible}
        title="Editar OP"
        onClose={() => {
          setModalVisible(false);
          setEditing(null);
        }}
        onConfirm={handleSaveEdit}
        confirmText="Salvar"
        cancelText="Cancelar"
      >
        <FormInput
          label="Cliente"
          placeholder="Nome do cliente"
          value={editCliente}
          onChangeText={setEditCliente}
          required
          returnKeyType="next"
        />
        <FormInput
          label="Produto"
          placeholder="Produto"
          value={editProduto}
          onChangeText={setEditProduto}
          required
          returnKeyType="next"
        />
        <Select
          label="Status"
          options={STATUS_COLS.map((c) => ({ label: c.label, value: c.id }))}
          value={editStatus}
          onValueChange={(v) => setEditStatus(v as OP["status"])}
          required
        />
      </ModalDialog>
    </OiScreen>
  );
}

function ArtworkActions({
  opId,
  onInfo,
  onSuccess,
}: {
  opId: string;
  onInfo: (msg: string) => void;
  onSuccess: (msg: string) => void;
}) {
  const artworksQuery = useArtworksByOp(opId);
  const arts = artworksQuery.data ?? [];
  const count = arts.length;

  const handleCopyLink = async () => {
    if (count === 0) {
      onInfo("Anexe uma arte primeiro");
      return;
    }
    const token = arts[0].publicToken;
    const base =
      typeof window !== "undefined" && window.location?.origin
        ? window.location.origin
        : "";
    const url = `${base}/a/${token}`;
    try {
      await Clipboard.setStringAsync(url);
      onSuccess("Link de aprovação copiado!");
    } catch {
      onInfo(url);
    }
  };

  const handleAttach = async () => {
    if (count > 0 && arts[0].fileUrl) {
      Linking.openURL(arts[0].fileUrl).catch(() => {});
      return;
    }
    onInfo("Upload de arte em breve — use Orçamentos por enquanto");
  };

  return (
    <>
      <OiBtn
        size="sm"
        label={count > 0 ? `Arte (${count})` : "Arte"}
        leftIcon="paperclip"
        onPress={handleAttach}
      />
      <OiBtn size="sm" label="Link" leftIcon="send" onPress={handleCopyLink} />
    </>
  );
}
