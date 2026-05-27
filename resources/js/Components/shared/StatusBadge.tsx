import * as React from 'react';
import { Badge } from '@/Components/ui/badge';
import { cn } from '@/Lib/utils';

/**
 * StatusBadge — badge semântica por domínio.
 *
 * Cada `kind` tem um mapping interno de value → {variant, label}. Se o value
 * não for reconhecido, cai em fallback (outline + value bruto em title case).
 *
 * Uso:
 *   <StatusBadge kind="intercorrencia" value="pendente" />
 *   <StatusBadge kind="aprovacao" value="aprovada" />
 *   <StatusBadge kind="payment" value="paid" />
 *
 * Adicionar novo domínio: estender `mappings` abaixo + commitar.
 */
type Variant = 'default' | 'secondary' | 'destructive' | 'outline' | 'ghost' | 'link';

type StatusEntry = { variant: Variant; label: string; className?: string };

const mappings: Record<string, Record<string, StatusEntry>> = {
  intercorrencia: {
    rascunho:  { variant: 'outline',     label: 'Rascunho' },
    pendente:  { variant: 'secondary',   label: 'Pendente' },
    aprovada:  { variant: 'default',     label: 'Aprovada',  className: 'bg-emerald-600 hover:bg-emerald-700' },
    rejeitada: { variant: 'destructive', label: 'Rejeitada' },
    aplicada:  { variant: 'default',     label: 'Aplicada',  className: 'bg-blue-600 hover:bg-blue-700' },
    cancelada: { variant: 'outline',     label: 'Cancelada' },
  },
  aprovacao: {
    pendente:  { variant: 'secondary',   label: 'Pendente' },
    aprovada:  { variant: 'default',     label: 'Aprovada',  className: 'bg-emerald-600 hover:bg-emerald-700' },
    rejeitada: { variant: 'destructive', label: 'Rejeitada' },
    aprovada_em_lote: { variant: 'default', label: 'Lote OK', className: 'bg-emerald-600' },
  },
  prioridade: {
    baixa:   { variant: 'outline',     label: 'Baixa' },
    normal:  { variant: 'secondary',   label: 'Normal' },
    alta:    { variant: 'destructive', label: 'Alta' },
    urgente: { variant: 'destructive', label: 'Urgente', className: 'animate-pulse' },
  },
  payment: {
    pending:        { variant: 'secondary',   label: 'Pendente' },
    partial:        { variant: 'default',     label: 'Parcial',    className: 'bg-amber-600 hover:bg-amber-700' },
    paid:           { variant: 'default',     label: 'Pago',       className: 'bg-emerald-600 hover:bg-emerald-700' },
    due:            { variant: 'destructive', label: 'Vencido' },
    overdue:        { variant: 'destructive', label: 'Atrasado' },
  },
  financeiro_titulo: {
    aberto:    { variant: 'secondary',   label: 'Aberto' },
    parcial:   { variant: 'default',     label: 'Parcial',   className: 'bg-amber-600 hover:bg-amber-700' },
    quitado:   { variant: 'default',     label: 'Quitado',   className: 'bg-emerald-600 hover:bg-emerald-700' },
    cancelado: { variant: 'outline',     label: 'Cancelado' },
  },
  importacao: {
    pendente:    { variant: 'secondary', label: 'Pendente' },
    processando: { variant: 'default',   label: 'Processando', className: 'bg-blue-600' },
    sucesso:     { variant: 'default',   label: 'Sucesso',     className: 'bg-emerald-600' },
    erro:        { variant: 'destructive', label: 'Erro' },
  },
  nfse: {
    rascunho:    { variant: 'outline',     label: 'Rascunho' },
    processando: { variant: 'default',     label: 'Processando', className: 'bg-blue-600 hover:bg-blue-700' },
    emitida:     { variant: 'default',     label: 'Emitida',     className: 'bg-emerald-600 hover:bg-emerald-700' },
    cancelada:   { variant: 'outline',     label: 'Cancelada' },
    erro:        { variant: 'destructive', label: 'Erro' },
  },
  rep: {
    REP_P: { variant: 'outline', label: 'REP-P' },
    REP_C: { variant: 'outline', label: 'REP-C' },
    REP_A: { variant: 'outline', label: 'REP-A' },
  },
  // Onda 1 PR D 2026-05-26 — status de Vehicle (OficinaAuto). Frota Martinho.
  vehicle: {
    active:         { variant: 'default',     label: 'Ativo',           className: 'bg-emerald-600 hover:bg-emerald-700' },
    in_service:     { variant: 'default',     label: 'Em serviço',      className: 'bg-blue-600 hover:bg-blue-700' },
    awaiting_parts: { variant: 'default',     label: 'Aguardando peças', className: 'bg-amber-600 hover:bg-amber-700' },
    inactive:       { variant: 'outline',     label: 'Inativo' },
    written_off:    { variant: 'destructive', label: 'Baixado' },
  },
  ads_destination: {
    blocked:        { variant: 'destructive', label: 'Bloqueado' },
    pending_wagner: { variant: 'default',     label: 'Aguardando você', className: 'bg-amber-600 hover:bg-amber-700' },
    brain_b:        { variant: 'default',     label: 'Brain B',          className: 'bg-blue-600 hover:bg-blue-700' },
    brain_a:        { variant: 'default',     label: 'Brain A',          className: 'bg-emerald-600 hover:bg-emerald-700' },
    queued:         { variant: 'outline',     label: 'Na fila' },
  },
  ads_risco: {
    Baixo:   { variant: 'default',     label: 'Risco Baixo',   className: 'bg-emerald-600 hover:bg-emerald-700' },
    Médio:   { variant: 'default',     label: 'Risco Médio',   className: 'bg-amber-600 hover:bg-amber-700' },
    Alto:    { variant: 'default',     label: 'Risco Alto',    className: 'bg-orange-600 hover:bg-orange-700' },
    Crítico: { variant: 'destructive', label: 'Risco Crítico' },
  },
  mcp_status: {
    ok:             { variant: 'default',     label: 'ok',             className: 'bg-emerald-600 hover:bg-emerald-700' },
    denied:         { variant: 'default',     label: 'denied',         className: 'bg-amber-600 hover:bg-amber-700' },
    error:          { variant: 'destructive', label: 'error' },
    quota_exceeded: { variant: 'default',     label: 'quota_exceeded', className: 'bg-orange-600 hover:bg-orange-700' },
  },
};

interface Props {
  kind: keyof typeof mappings | string;
  value: string;
  className?: string;
  /** Override label se necessário (útil quando o DB retorna string diferente). */
  label?: string;
}

export default function StatusBadge({ kind, value, className, label }: Props) {
  const dict = mappings[kind as string] ?? {};
  const entry = dict[value?.toLowerCase?.() ?? value];

  if (!entry) {
    return (
      <Badge variant="outline" className={cn('font-medium', className)}>
        {label ?? toTitle(value)}
      </Badge>
    );
  }

  return (
    <Badge variant={entry.variant} className={cn('font-medium', entry.className, className)}>
      {label ?? entry.label}
    </Badge>
  );
}

function toTitle(s: string): string {
  if (!s) return '—';
  return s.replace(/[_-]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
