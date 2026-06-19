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
    aprovada:  { variant: 'default',     label: 'Aprovada',  className: 'bg-success text-success-foreground hover:bg-success/90' },
    rejeitada: { variant: 'destructive', label: 'Rejeitada' },
    aplicada:  { variant: 'default',     label: 'Aplicada',  className: 'bg-info text-info-foreground hover:bg-info/90' },
    cancelada: { variant: 'outline',     label: 'Cancelada' },
  },
  aprovacao: {
    pendente:  { variant: 'secondary',   label: 'Pendente' },
    aprovada:  { variant: 'default',     label: 'Aprovada',  className: 'bg-success text-success-foreground hover:bg-success/90' },
    rejeitada: { variant: 'destructive', label: 'Rejeitada' },
    aprovada_em_lote: { variant: 'default', label: 'Lote OK', className: 'bg-success text-success-foreground' },
  },
  prioridade: {
    baixa:   { variant: 'outline',     label: 'Baixa' },
    normal:  { variant: 'secondary',   label: 'Normal' },
    alta:    { variant: 'destructive', label: 'Alta' },
    urgente: { variant: 'destructive', label: 'Urgente', className: 'animate-pulse' },
  },
  payment: {
    pending:        { variant: 'secondary',   label: 'Pendente' },
    partial:        { variant: 'default',     label: 'Parcial',    className: 'bg-warning text-warning-foreground hover:bg-warning/90' },
    paid:           { variant: 'default',     label: 'Pago',       className: 'bg-success text-success-foreground hover:bg-success/90' },
    due:            { variant: 'destructive', label: 'Vencido' },
    overdue:        { variant: 'destructive', label: 'Atrasado' },
  },
  financeiro_titulo: {
    aberto:    { variant: 'secondary',   label: 'Aberto' },
    parcial:   { variant: 'default',     label: 'Parcial',   className: 'bg-warning text-warning-foreground hover:bg-warning/90' },
    quitado:   { variant: 'default',     label: 'Quitado',   className: 'bg-success text-success-foreground hover:bg-success/90' },
    cancelado: { variant: 'outline',     label: 'Cancelado' },
  },
  importacao: {
    pendente:    { variant: 'secondary', label: 'Pendente' },
    processando: { variant: 'default',   label: 'Processando', className: 'bg-info text-info-foreground' },
    sucesso:     { variant: 'default',   label: 'Sucesso',     className: 'bg-success text-success-foreground' },
    erro:        { variant: 'destructive', label: 'Erro' },
  },
  nfse: {
    rascunho:    { variant: 'outline',     label: 'Rascunho' },
    processando: { variant: 'default',     label: 'Processando', className: 'bg-info text-info-foreground hover:bg-info/90' },
    emitida:     { variant: 'default',     label: 'Emitida',     className: 'bg-success text-success-foreground hover:bg-success/90' },
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
    active:         { variant: 'default',     label: 'Ativo',           className: 'bg-success text-success-foreground hover:bg-success/90' },
    in_service:     { variant: 'default',     label: 'Em serviço',      className: 'bg-info text-info-foreground hover:bg-info/90' },
    awaiting_parts: { variant: 'default',     label: 'Aguardando peças', className: 'bg-warning text-warning-foreground hover:bg-warning/90' },
    inactive:       { variant: 'outline',     label: 'Inativo' },
    written_off:    { variant: 'destructive', label: 'Baixado' },
  },
  ads_destination: {
    blocked:        { variant: 'destructive', label: 'Bloqueado' },
    pending_wagner: { variant: 'default',     label: 'Aguardando você', className: 'bg-warning text-warning-foreground hover:bg-warning/90' },
    brain_b:        { variant: 'default',     label: 'Brain B',          className: 'bg-info text-info-foreground hover:bg-info/90' },
    brain_a:        { variant: 'default',     label: 'Brain A',          className: 'bg-success text-success-foreground hover:bg-success/90' },
    queued:         { variant: 'outline',     label: 'Na fila' },
  },
  // Ramp de severidade de 4 níveis SEM token "orange" (Onda M1): colapsa nos 3
  // semânticos (success→warning→destructive); o ÁPICE (Crítico) pulsa pra ficar
  // visualmente distinto do Alto. Ambos vermelhos, label reforça. DS-puro.
  ads_risco: {
    Baixo:   { variant: 'default',     label: 'Risco Baixo',   className: 'bg-success text-success-foreground hover:bg-success/90' },
    Médio:   { variant: 'default',     label: 'Risco Médio',   className: 'bg-warning text-warning-foreground hover:bg-warning/90' },
    Alto:    { variant: 'destructive', label: 'Risco Alto' },
    Crítico: { variant: 'destructive', label: 'Risco Crítico', className: 'animate-pulse' },
  },
  mcp_status: {
    ok:             { variant: 'default',     label: 'ok',             className: 'bg-success text-success-foreground hover:bg-success/90' },
    denied:         { variant: 'default',     label: 'denied',         className: 'bg-warning text-warning-foreground hover:bg-warning/90' },
    error:          { variant: 'destructive', label: 'error' },
    quota_exceeded: { variant: 'destructive', label: 'quota_exceeded' },
  },
  // Admin Center (Centro de Operações) — semáforo green/yellow/red dos health
  // snapshots + estado online/offline de infra. Substitui bg-(green|amber|red)-100
  // inline repetido 6x no Admin/Index.tsx (tokenização DS, ADR UI-0013).
  admin_health: {
    green:   { variant: 'default',     label: 'green',   className: 'bg-success text-success-foreground hover:bg-success/90' },
    yellow:  { variant: 'default',     label: 'yellow',  className: 'bg-warning text-warning-foreground hover:bg-warning/90' },
    red:     { variant: 'destructive', label: 'red' },
    unknown: { variant: 'outline',     label: 'unknown' },
  },
  admin_reachable: {
    online:  { variant: 'default',     label: 'online',  className: 'bg-success text-success-foreground hover:bg-success/90' },
    offline: { variant: 'destructive', label: 'offline' },
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
