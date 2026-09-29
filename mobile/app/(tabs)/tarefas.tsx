/**
 * Tarefas — Tab 2 (Caixa de entrada operacional).
 *
 * Read-only aggregation de coisas que pedem ação:
 *  - Pedidos com status "novo" (aguardando aprovação)
 *  - OPs em "fila" (aguardando início)
 *  - OSs em "aguardando_aprovacao" / "diagnostico"
 *  - Itens com estoque baixo
 *
 * Sem schema novo — só junta queries existentes. Filtros via chips.
 */
import { useMemo, useState } from "react";
import { ActivityIndicator, ScrollView, Text, View } from "react-native";
import { useRouter } from "expo-router";

import {
  OiCard,
  OiChip,
  OiChips,
  OiEmpty,
  OiHeader,
  OiIcon,
  OiList,
  OiListRow,
  OiOrigin,
  OiScreen,
  OiSection,
  OiStatus,
  type OiIconName,
} from "@/components/oi";
import { fonts, hexAlpha } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import {
  useLowStockAlert,
  useOps,
  usePedidos,
  useServiceOrders,
} from "@/lib/erp-queries";

type Filter = "todas" | "pedidos" | "producao" | "os" | "estoque";

type Tarefa = {
  id: string;
  kind: "pedido" | "op" | "os" | "estoque";
  title: string;
  subtitle: string;
  when: string;
  variant: "accent" | "warn" | "danger";
  origin: "OS" | "MFG" | "FIN" | "CRM" | "PNT";
  to: string;
  icon: OiIconName;
};

function relative(date?: string | Date | null): string {
  if (!date) return "—";
  const d = typeof date === "string" ? new Date(date) : date;
  if (isNaN(d.getTime())) return "—";
  const now = Date.now();
  const diff = Math.max(0, now - d.getTime());
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return "agora";
  if (mins < 60) return `${mins}m`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs}h`;
  const days = Math.floor(hrs / 24);
  return `${days}d`;
}

export default function TarefasScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const [filter, setFilter] = useState<Filter>("todas");

  const pedidosQ = usePedidos();
  const opsQ = useOps();
  const ossQ = useServiceOrders();
  const lowStockQ = useLowStockAlert();

  const loading =
    pedidosQ.isLoading || opsQ.isLoading || ossQ.isLoading || lowStockQ.isLoading;

  const tarefas = useMemo<Tarefa[]>(() => {
    const list: Tarefa[] = [];

    for (const p of pedidosQ.data ?? []) {
      if (p.status !== "novo") continue;
      list.push({
        id: `ped-${p.id}`,
        kind: "pedido",
        title: `${p.cliente}`,
        subtitle: `${p.produto} · R$ ${p.valor.toLocaleString("pt-BR")}`,
        when: relative(p.data),
        variant: "accent",
        origin: "CRM",
        to: "/(tabs)/vendas",
        icon: "tag",
      });
    }

    for (const op of opsQ.data ?? []) {
      if (op.status !== "fila") continue;
      list.push({
        id: `op-${op.id}`,
        kind: "op",
        title: `${op.produto}`,
        subtitle: `OP em fila · ${op.cliente}`,
        when: relative((op as { criadoEm?: string }).criadoEm),
        variant: "warn",
        origin: "MFG",
        to: "/(tabs)/producao",
        icon: "box",
      });
    }

    for (const os of ossQ.data ?? []) {
      if (
        os.status !== "aguardando_aprovacao" &&
        os.status !== "diagnostico"
      )
        continue;
      const cliNome =
        (os as { customer?: { nome?: string } | null }).customer?.nome ??
        "Cliente";
      list.push({
        id: `os-${os.id}`,
        kind: "os",
        title: `OS ${(os as { numero?: number | string | null }).numero ?? os.id.slice(0, 8)}`,
        subtitle: `${cliNome} · ${os.status === "aguardando_aprovacao" ? "aguardando aprovação" : "em diagnóstico"}`,
        when: relative(os.createdAt as unknown as string),
        variant: os.status === "aguardando_aprovacao" ? "danger" : "warn",
        origin: "OS",
        to: `/oss/${os.id}`,
        icon: "settings",
      });
    }

    for (const item of lowStockQ.data ?? []) {
      list.push({
        id: `est-${item.id}`,
        kind: "estoque",
        title: item.nome,
        subtitle: `Estoque baixo · ${item.quantidade} / mín ${item.estoqueMinimo}`,
        when: "agora",
        variant: "danger",
        origin: "MFG",
        to: `/estoque/${item.id}`,
        icon: "alert",
      });
    }

    return list;
  }, [pedidosQ.data, opsQ.data, ossQ.data, lowStockQ.data]);

  const filtered = useMemo(() => {
    if (filter === "todas") return tarefas;
    return tarefas.filter((t) => {
      if (filter === "pedidos") return t.kind === "pedido";
      if (filter === "producao") return t.kind === "op";
      if (filter === "os") return t.kind === "os";
      if (filter === "estoque") return t.kind === "estoque";
      return true;
    });
  }, [tarefas, filter]);

  const counts = useMemo(() => {
    return {
      todas: tarefas.length,
      pedidos: tarefas.filter((t) => t.kind === "pedido").length,
      producao: tarefas.filter((t) => t.kind === "op").length,
      os: tarefas.filter((t) => t.kind === "os").length,
      estoque: tarefas.filter((t) => t.kind === "estoque").length,
    };
  }, [tarefas]);

  return (
    <OiScreen edges={["top"]}>
      <OiHeader
        title="Tarefas"
        eyebrow={`${tarefas.length} aguardando ação`}
        actions={[
          { icon: "check", onPress: () => {} },
          { icon: "filter", onPress: () => {} },
        ]}
      >
        <OiChips>
          <OiChip
            label="Todas"
            count={counts.todas}
            active={filter === "todas"}
            onPress={() => setFilter("todas")}
          />
          <OiChip
            label="Pedidos"
            count={counts.pedidos}
            active={filter === "pedidos"}
            onPress={() => setFilter("pedidos")}
          />
          <OiChip
            label="Produção"
            count={counts.producao}
            active={filter === "producao"}
            onPress={() => setFilter("producao")}
          />
          <OiChip
            label="OS"
            count={counts.os}
            active={filter === "os"}
            onPress={() => setFilter("os")}
          />
          <OiChip
            label="Estoque"
            count={counts.estoque}
            active={filter === "estoque"}
            onPress={() => setFilter("estoque")}
          />
        </OiChips>
      </OiHeader>

      <ScrollView
        contentContainerStyle={{ paddingBottom: 32 }}
        showsVerticalScrollIndicator={false}
      >
        {loading ? (
          <View style={{ paddingVertical: 48, alignItems: "center" }}>
            <ActivityIndicator color={palette.accent} />
          </View>
        ) : filtered.length === 0 ? (
          <OiEmpty
            ok
            icon="check-circle"
            title="Tudo em dia"
            subtitle="Nenhuma pendência operacional no momento."
          />
        ) : (
          <OiSection noPad>
            <OiList>
              {filtered.map((t, idx) => (
                <OiListRow
                  key={t.id}
                  title={t.title}
                  subtitle={t.subtitle}
                  onPress={() => router.push(t.to as never)}
                  last={idx === filtered.length - 1}
                  left={
                    <View
                      style={{
                        width: 40,
                        height: 40,
                        borderRadius: 10,
                        backgroundColor: hexAlpha(
                          t.variant === "danger"
                            ? palette.danger
                            : t.variant === "warn"
                              ? palette.warn
                              : palette.accent,
                          0.18,
                        ),
                        alignItems: "center",
                        justifyContent: "center",
                      }}
                    >
                      <OiIcon
                        name={t.icon}
                        size={18}
                        color={
                          t.variant === "danger"
                            ? palette.danger
                            : t.variant === "warn"
                              ? palette.warn
                              : palette.accent
                        }
                      />
                    </View>
                  }
                  right={
                    <View style={{ alignItems: "flex-end", gap: 4 }}>
                      <OiOrigin kind={t.origin} />
                      <Text
                        style={{
                          fontSize: 10.5,
                          fontFamily: fonts.mono,
                          color: palette.textMute,
                        }}
                      >
                        {t.when}
                      </Text>
                    </View>
                  }
                />
              ))}
            </OiList>
            <View style={{ paddingHorizontal: 16, paddingTop: 12 }}>
              <OiCard variant="tight">
                <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                  <OiStatus
                    variant="neutral"
                    label={`${filtered.length} ${filtered.length === 1 ? "item" : "itens"}`}
                  />
                  <Text
                    style={{
                      fontSize: 11.5,
                      color: palette.textMute,
                      fontFamily: fonts.sans,
                    }}
                  >
                    Toque para abrir o módulo correspondente.
                  </Text>
                </View>
              </OiCard>
            </View>
          </OiSection>
        )}
      </ScrollView>
    </OiScreen>
  );
}
