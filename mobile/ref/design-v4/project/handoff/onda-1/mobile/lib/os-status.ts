/**
 * Mapa único de status da OS (Onda 1) — app (ServiceOrderStatus, 9 etapas) ↔ DS (StatusBadge kind="os").
 * Substitui STATUS_COLORS de app/(tabs)/oss.tsx e os mapas locais de oss/[id].tsx.
 * tone → palette (ok/warn/danger/accent/textMute) + hexAlpha(cor, 0.06) de fundo (nunca pastel sólido).
 */
import type { OiPalette } from "./oi-theme";

export type ServiceOrderStatus =
  | "recepcao" | "diagnostico" | "orcamento" | "aguardando_aprovacao"
  | "aguardando_pecas" | "em_execucao" | "revisao" | "pronto" | "entregue";

export type OsTone = "neutral" | "info" | "warn" | "danger" | "accent" | "ok";

export const OS_STATUS: Record<ServiceOrderStatus, { label: string; tone: OsTone; ds: string; trava: boolean }> = {
  recepcao:             { label: "Recepção",              tone: "neutral", ds: "aberta",               trava: false },
  diagnostico:          { label: "Diagnóstico",           tone: "info",    ds: "diagnostico",          trava: false },
  orcamento:            { label: "Orçamento",             tone: "info",    ds: "orcamento",            trava: false },
  aguardando_aprovacao: { label: "Aguardando aprovação",  tone: "warn",    ds: "aguardando_aprovacao", trava: true  },
  aguardando_pecas:     { label: "Aguardando peças",      tone: "danger",  ds: "aguardando_pecas",     trava: true  },
  em_execucao:          { label: "Em execução",           tone: "accent",  ds: "em_servico",           trava: false },
  revisao:              { label: "Revisão",               tone: "info",    ds: "revisao",              trava: false },
  pronto:               { label: "Pronto",                tone: "ok",      ds: "concluida",            trava: false },
  entregue:             { label: "Entregue",              tone: "neutral", ds: "entregue",             trava: false },
};

export const OS_ORDEM = Object.keys(OS_STATUS) as ServiceOrderStatus[];

export function osToneColor(p: OiPalette, tone: OsTone): string {
  switch (tone) {
    case "ok": return p.ok;
    case "warn": return p.warn;
    case "danger": return p.danger;
    case "accent": return p.accent;
    case "info": return p.info;
    default: return p.textMute;
  }
}
