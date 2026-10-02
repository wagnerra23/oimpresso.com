import React, { useMemo, useState } from "react";
import { ActivityIndicator, ScrollView, Text, View } from "react-native";
import { router } from "expo-router";
import * as Clipboard from "expo-clipboard";

import { OiBtn, OiCard, OiChip, OiChips, OiEmpty, OiHeader, OiMoney, OiPlaca, OiScreen, OiStatus } from "@/components/oi";
import { fonts } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useNotify } from "@/lib/notify";
import { OS_ORDEM, OS_STATUS } from "@/lib/os-status";
import {
  useAdvanceServiceOrderStatus,
  useGenerateOSLink,
  useServiceOrders,
  type ServiceOrderStatus,
} from "@/lib/erp-queries";

function nextStatus(s: ServiceOrderStatus): ServiceOrderStatus | null {
  const idx = OS_ORDEM.indexOf(s);
  if (idx < 0 || idx >= OS_ORDEM.length - 1) return null;
  return OS_ORDEM[idx + 1];
}

export default function OSsScreen() {
  const { palette } = useOiTheme();
  const notify = useNotify();
  const [statusFilter, setStatusFilter] = useState<ServiceOrderStatus | null>(null);
  // Uma query só: a contagem por etapa e o filtro saem da mesma lista (antes eram 2 requisições).
  const allQuery = useServiceOrders();
  const ossQuery = allQuery;
  const advanceStatus = useAdvanceServiceOrderStatus();
  const generateLink = useGenerateOSLink();

  const oss = useMemo(() => (allQuery.data ?? []).filter((o) => !statusFilter || o.status === statusFilter), [allQuery.data, statusFilter]);
  const counts = useMemo(() => {
    const m: Partial<Record<ServiceOrderStatus, number>> = {};
    for (const o of allQuery.data ?? []) m[o.status as ServiceOrderStatus] = (m[o.status as ServiceOrderStatus] ?? 0) + 1;
    return m;
  }, [allQuery.data]);
  const travadas = (counts.aguardando_aprovacao ?? 0) + (counts.aguardando_pecas ?? 0);

  const handleAdvance = async (id: string, current: ServiceOrderStatus) => {
    const next = nextStatus(current);
    if (!next) return notify("info", "Já está no último status (Entregue)");
    try {
      await advanceStatus.mutateAsync({ id, status: next });
      notify("sucesso", `Avançado para ${OS_STATUS[next].label}`);
    } catch (err) {
      notify("erro", err instanceof Error ? err.message : "Erro ao avançar status");
    }
  };

  const handleCopyLink = async (id: string, numero: number) => {
    try {
      const result = await generateLink.mutateAsync({ id, expiresInDays: 30 });
      await Clipboard.setStringAsync(result.url);
      notify("sucesso", `Link de aprovação da OS #${numero} copiado`);
    } catch (err) {
      notify("erro", err instanceof Error ? err.message : "Erro ao gerar link");
    }
  };

  return (
    <OiScreen edges={["top"]}>
      <OiHeader
        title="Ordens de serviço"
        eyebrow={`${allQuery.data?.length ?? 0} no pátio${travadas ? ` · ${travadas} travadas` : ""}`}
        actions={[{ icon: "plus", onPress: () => router.push("/oss/new" as never) }]}
      >
        <OiChips>
          <OiChip label="Todas" count={allQuery.data?.length} active={statusFilter === null} onPress={() => setStatusFilter(null)} />
          {OS_ORDEM.map((s) => (
            <OiChip key={s} label={OS_STATUS[s].label} count={counts[s] ?? 0} active={statusFilter === s} onPress={() => setStatusFilter(s)} />
          ))}
        </OiChips>
      </OiHeader>
      <ScrollView style={{ flex: 1 }} contentContainerStyle={{ padding: 16, gap: 10, paddingBottom: 24 }}>
        {ossQuery.isLoading ? (
          <View style={{ paddingVertical: 48, alignItems: "center" }}><ActivityIndicator color={palette.accent} /></View>
        ) : ossQuery.isError ? (
          <OiEmpty icon="alert" title="Não foi possível carregar as OS" subtitle={ossQuery.error?.message} action={{ label: "Tentar de novo", onPress: () => ossQuery.refetch() }} />
        ) : oss.length === 0 ? (
          <OiEmpty
            icon="wrench"
            title={statusFilter ? `Nenhuma OS em ${OS_STATUS[statusFilter].label}` : "Nenhuma OS no pátio"}
            subtitle={statusFilter ? "Troque o filtro para ver outras etapas." : "Abra a primeira OS a partir da recepção do veículo."}
            action={statusFilter ? { label: "Ver todas", onPress: () => setStatusFilter(null) } : { label: "Nova OS", onPress: () => router.push("/oss/new" as never) }}
          />
        ) : (
          oss.map((os) => {
            const st = OS_STATUS[os.status as ServiceOrderStatus];
            const next = nextStatus(os.status as ServiceOrderStatus);
            return (
              <OiCard key={os.id} variant="tight">
                <View style={{ flexDirection: "row", alignItems: "flex-start", gap: 10 }}>
                  <View style={{ flex: 1, minWidth: 0, gap: 4 }}>
                    <Text style={{ fontFamily: fonts.monoSemibold, fontSize: 13, color: palette.textDim }}>OS #{os.numero}</Text>
                    {os.customer ? <Text numberOfLines={1} style={{ fontFamily: fonts.sansSemibold, fontSize: 15, color: palette.text }}>{os.customer.nome}</Text> : null}
                    {os.vehicle ? (
                      <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                        <OiPlaca text={os.vehicle.placa} size="sm" />
                        <Text numberOfLines={1} style={{ flex: 1, fontFamily: fonts.sans, fontSize: 12.5, color: palette.textDim }}>{os.vehicle.marca} {os.vehicle.modelo}</Text>
                      </View>
                    ) : null}
                  </View>
                  <View style={{ alignItems: "flex-end", gap: 6 }}>
                    <OiMoney value={os.valorTotal} />
                    {st ? <OiStatus label={st.label} variant={st.tone} /> : null}
                  </View>
                </View>
                <View style={{ flexDirection: "row", gap: 8, marginTop: 4 }}>
                  <OiBtn size="sm" variant="primary" label="Abrir" onPress={() => router.push(`/oss/${os.id}` as any)} />
                  {next ? <OiBtn size="sm" label={`→ ${OS_STATUS[next].label}`} onPress={() => handleAdvance(os.id, os.status as ServiceOrderStatus)} /> : null}
                  <OiBtn size="sm" variant="ghost" leftIcon="paperclip" label="Link" onPress={() => handleCopyLink(os.id, os.numero)} />
                </View>
              </OiCard>
            );
          })
        )}
      </ScrollView>
    </OiScreen>
  );
}
