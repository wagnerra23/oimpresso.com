/**
 * useNotify — ponte do addToast antigo (useERP) para o toast global Oi (useToast).
 * Mesma assinatura: notify("sucesso" | "erro" | "info" | "aviso", mensagem).
 * Permite migrar tela a tela sem renderizar ui.toasts inline.
 */
import { useCallback } from "react";
import { useToast, type ToastTone } from "@/components/oi";

export type NotifyTipo = "sucesso" | "erro" | "info" | "aviso";

const TONE: Record<NotifyTipo, ToastTone> = { sucesso: "ok", erro: "danger", info: "default", aviso: "warn" };

export function useNotify() {
  const { show } = useToast();
  return useCallback((tipo: NotifyTipo, msg: string) => show(msg, TONE[tipo] ?? "default"), [show]);
}
