// Wave G — Pages/Cliente/_components/Pills.tsx
//
// Pills/chips semânticos da listagem de cliente:
//   - TipoPill: PF (verde) | PJ (roxo)
//   - TagChip: 9 cores semânticas (varejo/atacado/corporativo/evento/parceiro/
//     agencia/governo/vip/reincidente)
//   - FrescorPill: 4 estados calculados client-side por last_purchase_at
//     (fresc verde 0-14d / recente azul 15-60d / distante âmbar 61-180d / frio cinza 180+)
//   - SaldoCell: BRL com cor vermelha quando devedor (>0)
//
// Refs:
//   - ADR 0179 §dim 2 paleta cor semântica (avatar/tag/frescor/saldo)
//   - HANDOFF_CLIENTES.md §2.5 classificacao tags + §3 schema (saldo, last_purchase_at)
//   - prototipo-ui/prototipos/clientes/clientes-listagem.jsx (TipoPill, TagChip)
//   - prototipo-ui/prototipos/clientes/clientes-975.jsx (FrescorPill)
//
// Pegadinhas:
//   - SaldoCell positivo = devedor (cliente nos deve), negativo = adiantado.
//     Convenção UPOS Transactions schema.
//   - FrescorPill calcula client-side via Date.now() — não server-side, pra
//     evitar payload bloat e timezone confusion. Tolerância 24h ok pra UX.
//   - TipoPill verde/roxo segue protótipo Cowork (PF emerald, PJ violet).

import { useMemo, type ReactNode } from 'react';

// ─── TipoPill ────────────────────────────────────────────────────────────────

export interface TipoPillProps {
  tipo: 'PF' | 'PJ' | null | undefined;
}

export function TipoPill({ tipo }: TipoPillProps) {
  if (!tipo) {
    return <span className="text-xs text-muted-foreground/50" aria-hidden="true">—</span>;
  }
  // PF verde / PJ violeta = COR DE CATEGORIA (identidade binária do protótipo Cowork),
  // não estado → exceção documentada (decisão "B"), mantida crua de propósito.
  const cls =
    tipo === 'PJ'
      ? 'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300'
      : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300';
  return (
    <span
      className={
        'inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold font-mono tracking-wide ' +
        cls
      }
      title={tipo === 'PJ' ? 'Pessoa jurídica' : 'Pessoa física'}
    >
      {tipo}
    </span>
  );
}

// ─── TagChip ─────────────────────────────────────────────────────────────────

// ⚠️ EXCEÇÃO DE COR DE CATEGORIA (documentada · decisão Wagner "B"): as 9 cores
// abaixo são IDENTIDADE (distinguir 9 tipos de tag de relance), não estado semântico.
// Colapsar em success/warning/info/destructive destruiria a distinção → NÃO tokenizar.
// Allowlist consciente vs a catraca de cor crua. (TipoPill PF/PJ idem, mais abaixo.)
const TAG_COLORS: Record<string, string> = {
  varejo:
    'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900/40',
  atacado:
    'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-900/40',
  corporativo:
    'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900/40',
  evento:
    'bg-pink-50 text-pink-700 border-pink-200 dark:bg-pink-950/40 dark:text-pink-300 dark:border-pink-900/40',
  parceiro:
    'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900/40',
  agencia:
    'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-900/40',
  // Alias com acento (HANDOFF_CLIENTES.md §2.5 lista "agência" também)
  'agência':
    'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-900/40',
  governo:
    'bg-red-50 text-red-700 border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-900/40',
  vip:
    'bg-yellow-100 text-yellow-800 border-yellow-300 dark:bg-yellow-950/40 dark:text-yellow-300 dark:border-yellow-900/40',
  reincidente:
    'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-950/40 dark:text-orange-300 dark:border-orange-900/40',
};

export interface TagChipProps {
  tag: string;
  className?: string;
}

export function TagChip({ tag, className = '' }: TagChipProps) {
  const cls =
    TAG_COLORS[tag] ??
    'bg-stone-100 text-stone-700 border-stone-200 dark:bg-stone-900/40 dark:text-stone-300 dark:border-stone-800';
  return (
    <span
      className={
        'inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium lowercase tracking-tight ' +
        cls +
        ' ' +
        className
      }
    >
      {tag}
    </span>
  );
}

// ─── FrescorPill ─────────────────────────────────────────────────────────────

export type FrescorState = 'fresc' | 'recente' | 'distante' | 'frio';

/**
 * Calcula frescor por dias desde last_purchase_at.
 * Z-2.1 alinhado ao protótipo Cowork `clientes-975.jsx::FrescorPill`:
 *   <30d  → fresc    (verde — cliente quente, saudável)
 *   <90d  → recente  (âmbar — atenção, está esfriando)
 *   <180d → frio     (cinza — sem atividade há 3-6 meses)
 *   ≥180d → distante (rosa — provável churn, ação IA sugere reativação)
 *   null  → frio     (sem histórico)
 */
export function calcFrescor(iso: string | null | undefined): FrescorState {
  if (!iso) return 'frio';
  const t = new Date(iso).getTime();
  if (Number.isNaN(t)) return 'frio';
  const days = Math.floor((Date.now() - t) / 86400000);
  if (days < 30) return 'fresc';
  if (days < 90) return 'recente';
  if (days < 180) return 'frio';
  return 'distante';
}

// Frescor é ESTADO semântico (saúde do relacionamento) → tokens -soft/-fg do DS
// elevado (extraídos desta exata tela, #2639). Trocam light+dark sozinhos.
const FRESCOR_STYLE: Record<FrescorState, string> = {
  fresc: 'bg-success-soft text-success-fg border-success/20',
  recente: 'bg-warning-soft text-warning-fg border-warning/20',
  frio: 'bg-muted text-muted-foreground border-border',
  distante: 'bg-destructive-soft text-destructive-fg border-destructive/20',
};

const FRESCOR_LABEL: Record<FrescorState, string> = {
  fresc: 'fresc',
  recente: 'recente',
  distante: 'distante',
  frio: 'frio',
};

/** Formato relativo PT-BR — espelha `window.relDate` do protótipo Cowork. */
export function relativeFromIso(iso: string | null | undefined): string {
  if (!iso) return 'sem histórico';
  const t = new Date(iso).getTime();
  if (Number.isNaN(t)) return 'sem histórico';
  const diff = (Date.now() - t) / 1000;
  if (diff < 60) return 'agora';
  if (diff < 3600) return `há ${Math.floor(diff / 60)}min`;
  if (diff < 86400) return `há ${Math.floor(diff / 3600)}h`;
  const d = Math.floor(diff / 86400);
  if (d < 7) return `há ${d}d`;
  if (d < 30) return `há ${Math.floor(d / 7)}sem`;
  if (d < 365) return `há ${Math.floor(d / 30)}m`;
  return `há ${Math.floor(d / 365)}a`;
}

export interface FrescorPillProps {
  lastPurchaseAt: string | null | undefined;
  /** Suprime o sufixo "há Xd". Útil se a coluna já tem a data ao lado. */
  hideRelative?: boolean;
}

export function FrescorPill({ lastPurchaseAt, hideRelative = false }: FrescorPillProps) {
  const state = useMemo(() => calcFrescor(lastPurchaseAt), [lastPurchaseAt]);
  const rel = useMemo(() => relativeFromIso(lastPurchaseAt), [lastPurchaseAt]);
  return (
    <span
      className={
        'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-medium ' +
        FRESCOR_STYLE[state]
      }
      title={lastPurchaseAt ? `Última compra: ${rel}` : 'Sem compras registradas'}
    >
      <span>{FRESCOR_LABEL[state]}</span>
      {!hideRelative && (
        <>
          <span className="opacity-60" aria-hidden="true">·</span>
          <span className="opacity-80 font-normal">{rel}</span>
        </>
      )}
    </span>
  );
}

// ─── SaldoCell ───────────────────────────────────────────────────────────────

const BRL_FORMATTER = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
});

export interface SaldoCellProps {
  /** Valor em BRL. Convenção UPOS: positivo = cliente devedor; negativo = adiantado. */
  valor: number | null | undefined;
}

export function SaldoCell({ valor }: SaldoCellProps) {
  if (valor === null || valor === undefined || valor === 0) {
    return (
      <span className="text-muted-foreground/50 tabular-nums" aria-label="Sem saldo">
        —
      </span>
    );
  }
  const isDevedor = valor > 0;
  return (
    <span
      className={
        'tabular-nums font-medium ' +
        // Devedor = vermelho / crédito = verde — ESTADO semântico → token -fg.
        (isDevedor ? 'text-destructive-fg' : 'text-success-fg')
      }
      title={isDevedor ? 'Cliente em débito' : 'Cliente com crédito (adiantamento)'}
    >
      {BRL_FORMATTER.format(valor)}
    </span>
  );
}

// ─── StatusPill (reuso shadcn — exportado pra Index.tsx substituir StatusBadge) ─

export type StatusValue = 'ativo' | 'inativo' | 'bloqueado' | string;

// Status do cliente é ESTADO semântico → tokens -soft/-fg (light+dark no token).
const STATUS_STYLE_MAP: Record<string, { bg: string; label: string }> = {
  ativo: {
    bg: 'bg-success-soft text-success-fg border-success/20',
    label: 'Ativo',
  },
  inativo: {
    bg: 'bg-muted text-muted-foreground border-border',
    label: 'Inativo',
  },
  bloqueado: {
    bg: 'bg-destructive-soft text-destructive-fg border-destructive/20',
    label: 'Bloqueado',
  },
};

export interface StatusPillProps {
  status: StatusValue | null | undefined;
  children?: ReactNode;
}

export function StatusPill({ status, children }: StatusPillProps) {
  const key = (status ?? 'inativo').toString().toLowerCase();
  const s = STATUS_STYLE_MAP[key] ?? STATUS_STYLE_MAP.inativo;
  return (
    <span
      className={
        'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-medium ' +
        s.bg
      }
    >
      <span
        className="h-1.5 w-1.5 rounded-full bg-current opacity-80"
        aria-hidden="true"
      />
      {children ?? s.label}
    </span>
  );
}
