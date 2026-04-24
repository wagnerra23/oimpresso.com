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
  importacao: {
    pendente:    { variant: 'secondary', label: 'Pendente' },
    processando: { variant: 'default',   label: 'Processando', className: 'bg-blue-600' },
    sucesso:     { variant: 'default',   label: 'Sucesso',     className: 'bg-emerald-600' },
    erro:        { variant: 'destructive', label: 'Erro' },
  },
  rep: {
    REP_P: { variant: 'outline', label: 'REP-P' },
    REP_C: { variant: 'outline', label: 'REP-C' },
    REP_A: { variant: 'outline', label: 'REP-A' },
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
