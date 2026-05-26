// resources/js/Lib/oimpressoToast.ts — toast hub canônico (gap P1 #10 KB-9.75).
// Refs:
//  - memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md
//  - resources/js/Pages/Sells/_components/VdNextActionPanel.tsx (dispatch oimpresso:venda-*)
//  - resources/js/Pages/Sells/_components/FsmActionPanel.tsx (sonner direto, mantém)
//
// Hub canon que centraliza dois canais:
//  (a) sonner toast UI (visível pro usuário — top-right);
//  (b) CustomEvent `oimpresso:toast` no window (escutável por outros widgets/SaleSheet/
//      Inertia partial reloaders pra trigger refresh sem polling).
//
// Adicionalmente dispatch eventos namespaced pelo domínio (oimpresso:venda-invoiced,
// venda-paid, venda-emitted-nfe, venda-emitted-nfse) — mesmo padrão usado em
// VdNextActionPanel pra desacoplar componente publisher do componente subscriber.
//
// NÃO substitui sonner direto — código existente em FsmActionPanel/CobrancaDrawer/
// CriarOsButton continua usando `import { toast } from 'sonner'`. Este hub é
// wrapper OPCIONAL que dá:
//  - dispatch evento namespaced (Inertia partial reload sem polling);
//  - log estruturado pra clarity/telemetria futura;
//  - vocabulário BR canônico (Faturar ≠ Receber pagamento).
//
// Multi-tenant Tier 0 ADR 0093: nenhum business_id explícito aqui — sale_id já é
// per-tenant (model SaleSell tem global scope BusinessIdScope).

import { toast } from 'sonner';

export type OimpressoToastTone = 'success' | 'error' | 'info' | 'warning';

export interface OimpressoToastDetail {
  tone: OimpressoToastTone;
  msg: string;
  saleId?: number | string | null;
  /** Opcional — metadados extras pra subscribers (ex: orderable_id, invoice_no). */
  meta?: Record<string, unknown>;
}

const HUB_EVENT = 'oimpresso:toast' as const;

function dispatchHub(detail: OimpressoToastDetail): void {
  if (typeof window === 'undefined') return;
  try {
    window.dispatchEvent(new CustomEvent(HUB_EVENT, { detail }));
  } catch {
    // SSR / non-DOM env — silent no-op.
  }
}

function dispatchNamed(name: string, detail: Record<string, unknown>): void {
  if (typeof window === 'undefined') return;
  try {
    window.dispatchEvent(new CustomEvent(name, { detail }));
  } catch {
    // silent no-op.
  }
}

/**
 * Notifica que a venda foi FATURADA (emissão fiscal autorizada — NF-e ou NFS-e).
 * Vocabulário BR canon ADR-pendente: "faturar" = emitir documento fiscal,
 * NÃO confundir com "receber pagamento" (vide `paid`).
 */
function invoiced(saleId: number | string, msg: string, meta?: Record<string, unknown>): void {
  toast.success(msg);
  dispatchHub({ tone: 'success', msg, saleId, meta });
  dispatchNamed('oimpresso:venda-invoiced', { saleId, msg, ...(meta ?? {}) });
}

/**
 * Notifica que a venda foi PAGA (recebimento financeiro registrado).
 * Distinto de `invoiced` — venda pode estar paga sem NF-e e vice-versa.
 */
function paid(saleId: number | string, msg: string, meta?: Record<string, unknown>): void {
  toast.success(msg);
  dispatchHub({ tone: 'success', msg, saleId, meta });
  dispatchNamed('oimpresso:venda-paid', { saleId, msg, ...(meta ?? {}) });
}

/**
 * Notifica emissão de documento fiscal específico (NF-e mercadoria ou NFS-e serviço).
 * Use kind='nfe' pra NF-e (modelo 55) e kind='nfse' pra NFS-e (serviço municipal).
 */
function emitted(
  saleId: number | string,
  kind: 'nfe' | 'nfse',
  msg: string,
  meta?: Record<string, unknown>,
): void {
  toast.success(msg);
  dispatchHub({ tone: 'success', msg, saleId, meta: { kind, ...(meta ?? {}) } });
  const eventName = kind === 'nfe' ? 'oimpresso:venda-emitted-nfe' : 'oimpresso:venda-emitted-nfse';
  dispatchNamed(eventName, { saleId, msg, kind, ...(meta ?? {}) });
}

/** Notifica erro genérico. saleId opcional (alguns erros são globais). */
function error(msg: string, saleId?: number | string, meta?: Record<string, unknown>): void {
  toast.error(msg);
  dispatchHub({ tone: 'error', msg, saleId: saleId ?? null, meta });
}

/** Notifica informação neutra (não success / não error). */
function info(msg: string, saleId?: number | string, meta?: Record<string, unknown>): void {
  toast.info(msg);
  dispatchHub({ tone: 'info', msg, saleId: saleId ?? null, meta });
}

/** Notifica aviso (warning) — entre info e error. */
function warning(msg: string, saleId?: number | string, meta?: Record<string, unknown>): void {
  toast.warning(msg);
  dispatchHub({ tone: 'warning', msg, saleId: saleId ?? null, meta });
}

export const oimpressoToast = {
  invoiced,
  paid,
  emitted,
  error,
  info,
  warning,
} as const;

export type OimpressoToastApi = typeof oimpressoToast;

// Re-export do nome do evento canon pra subscribers usarem como string literal type-safe.
export const OIMPRESSO_TOAST_EVENT = HUB_EVENT;

// Tipagem auxiliar pra subscribers usarem `window.addEventListener` com tipo certo.
declare global {
  interface WindowEventMap {
    'oimpresso:toast': CustomEvent<OimpressoToastDetail>;
    'oimpresso:venda-invoiced': CustomEvent<{ saleId: number | string; msg: string }>;
    'oimpresso:venda-paid': CustomEvent<{ saleId: number | string; msg: string }>;
    'oimpresso:venda-emitted-nfe': CustomEvent<{ saleId: number | string; msg: string; kind: 'nfe' }>;
    'oimpresso:venda-emitted-nfse': CustomEvent<{ saleId: number | string; msg: string; kind: 'nfse' }>;
  }
}
