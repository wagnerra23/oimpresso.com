import { useCallback } from "react";
import { Pressable, Text, View } from "react-native";
import { useQuery, useQueryClient } from "@tanstack/react-query";

import { useNetworkState } from "@/hooks/use-network-state";
import { useAuthContext } from "@/lib/auth-context";
import { getQueue, replayQueue } from "@/lib/mutation-queue";
import { trpc } from "@/lib/trpc";

/**
 * F3-04 — global offline / pending-sync banner.
 *
 * Renders nothing when online with an empty queue. Otherwise shows a thin
 * amber strip with:
 *   - "Você está offline" when there's no network, OR
 *   - "N alteração(ões) pendente(s) de sincronização" with a "Sincronizar
 *     agora" button when we're online but the queue still has entries.
 *
 * Mounted in `app/(tabs)/_layout.tsx` above `<Tabs>`.
 */
export function OfflineBanner() {
  const { isOnline } = useNetworkState();
  const { currentCompany } = useAuthContext();
  const companyId = currentCompany?.id ?? null;
  const queryClient = useQueryClient();
  // Pull the same react client the rest of the app uses.
  // useUtils returns the helper proxy whose internal client we can reuse.
  const utils = trpc.useUtils();

  const queueQuery = useQuery({
    queryKey: ["sync-queue", companyId],
    queryFn: () => getQueue(companyId),
    refetchInterval: 5000,
    staleTime: 2000,
  });

  const pending = queueQuery.data?.length ?? 0;

  const onSyncNow = useCallback(async () => {
    // utils.client is the underlying tRPC client.
    const client = (utils as unknown as { client: unknown }).client;
    await replayQueue(companyId, client);
    await queryClient.invalidateQueries({ queryKey: ["sync-queue", companyId] });
  }, [utils, companyId, queryClient]);

  if (isOnline && pending === 0) return null;

  const message = !isOnline
    ? "Você está offline"
    : `${pending} alteração${pending === 1 ? "" : "ões"} pendente${pending === 1 ? "" : "s"} de sincronização`;

  return (
    <View
      style={{
        backgroundColor: "#fbbf24",
        paddingVertical: 6,
        paddingHorizontal: 12,
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "space-between",
        gap: 12,
      }}
    >
      <Text
        style={{ color: "#1f2937", fontSize: 12, fontWeight: "600", flex: 1 }}
        numberOfLines={1}
      >
        {message}
      </Text>
      {isOnline && pending > 0 ? (
        <Pressable
          onPress={onSyncNow}
          accessibilityRole="button"
          style={{
            backgroundColor: "#1f2937",
            paddingHorizontal: 10,
            paddingVertical: 4,
            borderRadius: 4,
          }}
        >
          <Text style={{ color: "#fff", fontSize: 11, fontWeight: "600" }}>
            Sincronizar agora
          </Text>
        </Pressable>
      ) : null}
    </View>
  );
}
