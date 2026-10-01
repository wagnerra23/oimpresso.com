/**
 * OiPendingNote — aviso inline para ações que dependem do servidor (Fiscal, Pagamentos).
 * Offline: explica que a emissão/cobrança vai para a fila COM chave de idempotência
 * (mutation-queue: payments.create, fiscal.documents.emit) — não duplica ao reenviar.
 * Com itens dessa rota na fila: mostra quantos.
 */
import { Text, View } from "react-native";

import { OiIcon } from "./OiIcon";
import { fonts, hexAlpha, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useNetworkState } from "@/hooks/use-network-state";
import { usePendingSync } from "@/hooks/use-pending-sync";

export function OiPendingNote({ path, offlineMsg, pendingMsg }: { path: string; offlineMsg: string; pendingMsg: (n: number) => string }) {
  const { palette } = useOiTheme();
  const { isOnline } = useNetworkState();
  const { count } = usePendingSync(path);
  if (isOnline && count === 0) return null;
  const msg = !isOnline ? offlineMsg : pendingMsg(count);
  return (
    <View style={{ flexDirection: "row", gap: 10, alignItems: "flex-start", padding: 12, borderRadius: radius.md, backgroundColor: hexAlpha(palette.warn, 0.06), borderWidth: 1, borderColor: hexAlpha(palette.warn, 0.22), marginBottom: 10 }}>
      <OiIcon name={isOnline ? "refresh" : "alert"} size={18} color={palette.warn} />
      <Text style={{ flex: 1, fontFamily: fonts.sans, fontSize: 13, lineHeight: 18, color: palette.text }}>{msg}</Text>
    </View>
  );
}
