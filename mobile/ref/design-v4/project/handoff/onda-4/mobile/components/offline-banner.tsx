import { useCallback, useState } from "react";
import { Pressable, Text, View } from "react-native";
import { useQuery, useQueryClient } from "@tanstack/react-query";

import { OiIcon } from "@/components/oi";
import { useNetworkState } from "@/hooks/use-network-state";
import { useAuthContext } from "@/lib/auth-context";
import { fonts, radius, touch } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { getQueue, replayQueue } from "@/lib/mutation-queue";
import { trpc } from "@/lib/trpc";

/**
 * F3-04 — faixa global offline / pendências (Onda 4: tokens do DS + contagem também offline
 * + resultado da sincronização). Mesma query ["sync-queue", companyId] usada por usePendingSync.
 */
export function OfflineBanner() {
  const { palette } = useOiTheme();
  const { isOnline } = useNetworkState();
  const { currentCompany } = useAuthContext();
  const companyId = currentCompany?.id ?? null;
  const queryClient = useQueryClient();
  const utils = trpc.useUtils();
  const [syncing, setSyncing] = useState(false);
  const [falhas, setFalhas] = useState(0);

  const queueQuery = useQuery({
    queryKey: ["sync-queue", companyId],
    queryFn: () => getQueue(companyId),
    refetchInterval: 5000,
    staleTime: 2000,
  });
  const pending = queueQuery.data?.length ?? 0;

  const onSyncNow = useCallback(async () => {
    setSyncing(true);
    try {
      const client = (utils as unknown as { client: unknown }).client;
      const r = await replayQueue(companyId, client);
      setFalhas(r.failed);
    } finally {
      setSyncing(false);
      await queryClient.invalidateQueries({ queryKey: ["sync-queue", companyId] });
    }
  }, [utils, companyId, queryClient]);

  if (isOnline && pending === 0) return null;

  const plural = (n: number, s: string, p: string) => (n === 1 ? s : p);
  const message = !isOnline
    ? pending > 0
      ? `Sem conexão — ${pending} ${plural(pending, "alteração guardada", "alterações guardadas")}`
      : "Sem conexão — o que você fizer fica guardado"
    : falhas > 0
      ? `${falhas} ${plural(falhas, "alteração não foi aceita", "alterações não foram aceitas")} pelo servidor`
      : `${pending} ${plural(pending, "alteração pendente", "alterações pendentes")} de sincronização`;

  return (
    <View style={{ backgroundColor: palette.warn, minHeight: 38, paddingLeft: 12, paddingRight: 6, flexDirection: "row", alignItems: "center", gap: 10 }}>
      <OiIcon name={isOnline ? "refresh" : "alert"} size={16} color="#1f1a12" />
      <Text style={{ color: "#1f1a12", fontSize: 12.5, fontFamily: fonts.sansSemibold, flex: 1 }} numberOfLines={2}>{message}</Text>
      {isOnline && pending > 0 ? (
        <Pressable onPress={syncing ? undefined : onSyncNow} accessibilityRole="button" hitSlop={4}
          style={{ backgroundColor: "#1f1a12", height: touch.sm, paddingHorizontal: 10, borderRadius: radius.sm, justifyContent: "center", opacity: syncing ? 0.6 : 1 }}>
          <Text style={{ color: "#fff", fontSize: 12, fontFamily: fonts.sansSemibold }}>{syncing ? "Sincronizando…" : "Sincronizar agora"}</Text>
        </Pressable>
      ) : null}
    </View>
  );
}
