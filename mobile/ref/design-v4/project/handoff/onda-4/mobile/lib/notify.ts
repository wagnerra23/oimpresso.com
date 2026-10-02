/**
 * useNotify — ponte do addToast antigo para o toast global Oi (Onda 2).
 * Onda 4: notifyError() distingue erro de rede (a mutação FOI guardada na fila pelo
 * MutationCache do _layout) de erro de verdade. Antes a tela mostrava "Erro ao salvar"
 * para algo que ia ser enviado depois.
 */
import { useCallback } from "react";
import { useToast, type ToastTone } from "@/components/oi";
import { isNetworkError } from "@/lib/mutation-queue";

export type NotifyTipo = "sucesso" | "erro" | "info" | "aviso";

const TONE: Record<NotifyTipo, ToastTone> = { sucesso: "ok", erro: "danger", info: "default", aviso: "warn" };

export function useNotify() {
  const { show } = useToast();
  return useCallback((tipo: NotifyTipo, msg: string) => show(msg, TONE[tipo] ?? "default"), [show]);
}

/** Use no catch de toda mutação: notifyError(err, "Erro ao salvar OS"). */
export function useNotifyError() {
  const { show } = useToast();
  return useCallback((err: unknown, fallback: string) => {
    if (isNetworkError(err)) {
      show("Sem conexão — guardado, envia quando a internet voltar", "warn", 3200);
      return;
    }
    show(err instanceof Error && err.message ? err.message : fallback, "danger");
  }, [show]);
}
