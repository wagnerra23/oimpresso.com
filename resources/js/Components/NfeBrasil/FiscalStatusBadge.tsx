// @memcofre
//   modulo: NfeBrasil (FiscalStatusBadge)
//   stories: US-NFE-002 (status NFC-e) generalizado p/ NF-e 55 + NFS-e (handoff Cowork 2026-06-02)
//   adrs: UI-0008 (cockpit), 0235 (roxo canon — status é exceção semântica R-DS-002)
//   nota: COMPONENTE ÚNICO de apresentação de status fiscal. Cobre os 3 documentos
//         (NFC-e 65 · NF-e 55 · NFS-e) com mesmo vocabulário, mesma cor, mesmo ícone.
//         PURAMENTE apresentacional (sem fetch) — quem polla é o NfceStatusBadge
//         (wrapper reativo) e quem lista é o FiscalSection. SoC: dado entra, status sai.

import { Ban, CheckCircle2, Clock, FileCheck2, Loader2, XCircle } from 'lucide-react';

import {
  docLabel,
  emissaoStatusToKind,
  type EmissaoLikeStatus,
  type FiscalDocModel,
  type FiscalStatusKind,
} from './fiscalStatus';

// Re-export type-only (conveniência p/ consumidores que já importam do componente).
export type { EmissaoLikeStatus, FiscalDocModel, FiscalStatusKind } from './fiscalStatus';

// ── Tone semântico (cor + ícone por estado) ────────────────────────────────

type Tone = 'info' | 'ok' | 'warn' | 'error' | 'muted';

interface KindMeta {
  tone: Tone;
  Icon: typeof CheckCircle2;
  /** Título base (sem o doc/numero — montados em runtime). */
  word: string;
}

const KIND_META: Record<FiscalStatusKind, KindMeta> = {
  emitting:   { tone: 'info',  Icon: Loader2,     word: 'Emitindo' },
  waiting:    { tone: 'warn',  Icon: Clock,       word: 'Processando' },
  authorized: { tone: 'ok',    Icon: CheckCircle2, word: 'Autorizada' },
  rejected:   { tone: 'error', Icon: XCircle,     word: 'Rejeitada' },
  denied:     { tone: 'error', Icon: Ban,         word: 'Denegada' },
  cancelled:  { tone: 'muted', Icon: Ban,         word: 'Cancelada' },
  inutilized: { tone: 'muted', Icon: FileCheck2,  word: 'Inutilizada' },
};

// Cores semânticas oklch (R-DS-002 exceção status — não usam o roxo canon ADR 0235).
// Fonte ÚNICA das cores de status fiscal no app.
const PALETTE: Record<Tone, { border: string; bg: string; bgDark: string; fg: string; fgDark: string }> = {
  info: {
    border: 'oklch(0.78 0.10 230)',
    bg: 'oklch(0.96 0.03 230 / 0.85)',
    bgDark: 'oklch(0.32 0.06 230 / 0.40)',
    fg: 'oklch(0.42 0.10 230)',
    fgDark: 'oklch(0.82 0.08 230)',
  },
  ok: {
    border: 'oklch(0.70 0.18 145)',
    bg: 'oklch(0.96 0.05 145 / 0.85)',
    bgDark: 'oklch(0.32 0.10 145 / 0.40)',
    fg: 'oklch(0.40 0.16 145)',
    fgDark: 'oklch(0.80 0.10 145)',
  },
  warn: {
    border: 'oklch(0.78 0.15 80)',
    bg: 'oklch(0.96 0.05 80 / 0.85)',
    bgDark: 'oklch(0.32 0.08 80 / 0.40)',
    fg: 'oklch(0.42 0.12 80)',
    fgDark: 'oklch(0.82 0.10 80)',
  },
  error: {
    border: 'oklch(0.55 0.20 25)',
    bg: 'oklch(0.96 0.04 25 / 0.85)',
    bgDark: 'oklch(0.32 0.10 25 / 0.40)',
    fg: 'oklch(0.40 0.18 25)',
    fgDark: 'oklch(0.78 0.10 25)',
  },
  muted: {
    border: 'oklch(0.80 0.01 250)',
    bg: 'oklch(0.96 0.005 250 / 0.85)',
    bgDark: 'oklch(0.32 0.01 250 / 0.40)',
    fg: 'oklch(0.45 0.01 250)',
    fgDark: 'oklch(0.78 0.005 250)',
  },
};

// ── Componente ──────────────────────────────────────────────────────────────

export interface FiscalStatusBadgeProps {
  /** Estado do documento — string dos hooks OU kind semântico já normalizado. */
  status: FiscalStatusKind | EmissaoLikeStatus | string | null | undefined;
  /** Documento ('65'|'55'|'nfse') ou rótulo legado ('NFe'). Vira o label exibido. */
  model?: FiscalDocModel | string | null;
  /** Sobrescreve o rótulo do documento (default derivado de `model`). */
  label?: string;
  numero?: number | null;
  chave?: string | null;
  cstat?: string | number | null;
  motivo?: string | null;
  /** `banner` = card completo (default, ex pós-venda) · `pill` = chip inline (ex linha de lista). */
  variant?: 'banner' | 'pill';
  /** Banner: só ícone + chave/motivo curto. Pill: ignora detalhe. Default false. */
  compact?: boolean;
  /** Anima o ícone (spinner) — usado pelo wrapper de polling. */
  spin?: boolean;
  /** Sobrescreve o título do banner (ex "aguardando SEFAZ" quando o poll desiste). */
  title?: string;
  /** Sobrescreve o detalhe do banner. */
  detail?: string;
}

function isKind(s: string): s is FiscalStatusKind {
  return s in KIND_META;
}

/**
 * Apresentação unificada do status de um documento fiscal (NFC-e/NF-e/NFS-e).
 * Determinístico, sem fetch. Aceita tanto o status string dos hooks quanto o kind semântico.
 */
export function FiscalStatusBadge({
  status,
  model,
  label,
  numero,
  chave,
  cstat,
  motivo,
  variant = 'banner',
  compact = false,
  spin,
  title,
  detail,
}: FiscalStatusBadgeProps) {
  const kind: FiscalStatusKind =
    typeof status === 'string' && isKind(status) ? status : emissaoStatusToKind(status);
  const meta = KIND_META[kind];
  const doc = label ?? docLabel(model);
  const c = PALETTE[meta.tone];
  const wantSpin = spin ?? kind === 'emitting';

  // Título: "<Doc> #123 autorizada" / "Emitindo NF-e…" / "NFS-e rejeitada"
  const computedTitle =
    title ??
    (kind === 'emitting'
      ? `Emitindo ${doc}…`
      : numero != null && kind === 'authorized'
        ? `${doc} #${numero} ${meta.word.toLowerCase()}`
        : `${doc} ${meta.word.toLowerCase()}`);

  // Detalhe: chave + cstat (autorizada) ou motivo (rejeição).
  const computedDetail =
    detail ??
    (kind === 'authorized'
      ? compact
        ? (chave ?? '')
        : `Chave ${chave ?? '—'}${cstat != null ? ` · cstat ${cstat}` : ''}`
      : kind === 'rejected' || kind === 'denied'
        ? compact
          ? (motivo ?? (cstat != null ? `cstat ${cstat}` : ''))
          : `${cstat != null ? `cstat ${cstat} — ` : ''}${motivo ?? 'sem motivo informado'}`
        : kind === 'waiting'
          ? 'Aguardando retorno do webservice.'
          : 'Aguardando processamento.');

  if (variant === 'pill') {
    return (
      <span
        role="status"
        title={computedDetail || undefined}
        style={{
          display: 'inline-flex',
          alignItems: 'center',
          gap: 4,
          borderRadius: 9999,
          border: `1px solid ${c.border}`,
          background: `light-dark(${c.bg}, ${c.bgDark})`,
          color: `light-dark(${c.fg}, ${c.fgDark})`,
          padding: '1px 8px',
          fontSize: 10,
          fontWeight: 600,
          lineHeight: 1.4,
          whiteSpace: 'nowrap',
        }}
      >
        <meta.Icon
          size={10}
          aria-hidden
          style={{ flexShrink: 0, animation: wantSpin ? 'spin 1s linear infinite' : undefined }}
        />
        {meta.word}
      </span>
    );
  }

  return (
    <div
      role="status"
      aria-live="polite"
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: 10,
        padding: '10px 12px',
        borderRadius: 8,
        border: `1px solid ${c.border}`,
        background: `light-dark(${c.bg}, ${c.bgDark})`,
        color: `light-dark(${c.fg}, ${c.fgDark})`,
        fontSize: 12,
      }}
    >
      <meta.Icon
        size={18}
        aria-hidden
        style={{ flexShrink: 0, animation: wantSpin ? 'spin 1s linear infinite' : undefined }}
      />
      <div style={{ minWidth: 0, flex: 1 }}>
        <div style={{ fontWeight: 600, fontSize: 13, lineHeight: 1.3 }}>{computedTitle}</div>
        {computedDetail && <div style={{ fontSize: 11, opacity: 0.85, marginTop: 2 }}>{computedDetail}</div>}
      </div>
    </div>
  );
}
