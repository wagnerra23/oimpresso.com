/**
 * usePendingSync — lê a mesma fila do OfflineBanner e diz, por rota tRPC, quantas
 * alterações ainda não subiram. Permite marcar a linha/registro como "pendente" na tela.
 *
 *   const { count, has } = usePendingSync("serviceOrders.");
 *   has("serviceOrders.update", (i) => i.id === os.id)  // → true se essa OS tem edição na fila
 */
import { useQuery } from "@tanstack/react-query";

import { useAuthContext } from "@/lib/auth-context";
import { getQueue, type QueuedMutation } from "@/lib/mutation-queue";

export function usePendingSync(prefix?: string) {
  const { currentCompany } = useAuthContext();
  const companyId = currentCompany?.id ?? null;
  const q = useQuery({ queryKey: ["sync-queue", companyId], queryFn: () => getQueue(companyId), refetchInterval: 5000, staleTime: 2000 });
  const items: QueuedMutation[] = (q.data ?? []).filter((m) => !prefix || m.path.startsWith(prefix));
  return {
    items,
    count: items.length,
    has: (path: string, match?: (input: any) => boolean) => items.some((m) => m.path === path && (!match || match(m.input))),
    falhou: items.filter((m) => !!m.error).length,
  };
}
