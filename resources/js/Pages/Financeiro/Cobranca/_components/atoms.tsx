// atoms.tsx — átomos visuais reutilizáveis port de pg-shared.jsx
// Btn · StatusBadge · GatewayTipoChip · OrigemChip · KpiCard · PageHeader
import { type ReactNode, type ButtonHTMLAttributes } from 'react';
import {
  DRIVERS, TIPOS, STATUS, ORIGENS,
  cn, type CobrancaStatus, type CobrancaTipo, type GatewayKey, type OrigemType,
} from '../_lib/cobranca-shared';

interface BtnProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'default' | 'outline' | 'ghost' | 'primary' | 'danger';
  size?: 'xs' | 'sm' | 'md' | 'lg';
}

export function Btn({ variant = 'default', size = 'sm', className, children, ...rest }: BtnProps) {
  const sizes = {
    xs: 'h-6 px-2 text-[11.5px]',
    sm: 'h-7 px-2.5 text-[12px]',
    md: 'h-8 px-3 text-[12.5px]',
    lg: 'h-9 px-4 text-[13px]',
  };
  const variants = {
    default: 'bg-stone-900 text-white hover:bg-stone-800',
    outline: 'bg-white border border-stone-300 text-stone-800 hover:bg-stone-50',
    ghost:   'text-stone-600 hover:bg-stone-100',
    primary: 'bg-orange-500 text-white hover:bg-orange-600',
    danger:  'text-destructive hover:bg-destructive-soft',
  };
  return (
    <button
      {...rest}
      className={cn(
        'inline-flex items-center gap-1.5 rounded-md font-medium transition disabled:opacity-50 disabled:cursor-not-allowed focus-visible:ring-2 focus-visible:ring-stone-400 focus-visible:ring-offset-2',
        sizes[size], variants[variant], className,
      )}
    >
      {children}
    </button>
  );
}

export function StatusBadge({ status }: { status: CobrancaStatus }) {
  const s = STATUS[status];
  if (!s) return null;
  return (
    <span className={cn(
      'inline-flex items-center gap-1 text-[10.5px] font-medium px-1.5 py-0.5 rounded border',
      s.bg, s.fg, 'border-current/20',
    )}>
      <span className={cn('w-1 h-1 rounded-full', s.dot)} />
      {s.label}
    </span>
  );
}

export function GatewayTipoChip({ gateway, tipo, compact = false }: {
  gateway: GatewayKey;
  tipo: CobrancaTipo;
  compact?: boolean;
}) {
  const drv = DRIVERS[gateway];
  const tp = TIPOS[tipo];
  if (!drv || !tp) return null;
  return (
    <span className="inline-flex items-center gap-1.5">
      <span className={cn(
        'w-4 h-4 rounded-sm grid place-items-center text-white text-[8.5px] font-bold tracking-tight shrink-0',
        drv.dot,
      )}>{drv.sigla}</span>
      {!compact && (
        <span className={cn('text-[10px] font-medium px-1 py-0.5 rounded', tp.bg, tp.fg)}>
          {tp.short}
        </span>
      )}
    </span>
  );
}

export function OrigemChip({ tipo, label, onClick }: {
  tipo: OrigemType;
  label?: string;
  onClick?: () => void;
}) {
  const o = ORIGENS[tipo];
  if (!o) return null;
  const Tag = onClick ? 'button' : 'span';
  return (
    <Tag
      onClick={onClick}
      className={cn(
        'inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded transition',
        o.bg, o.fg, onClick && 'hover:opacity-80 cursor-pointer',
      )}
    >
      {label || o.label}
    </Tag>
  );
}

interface KpiCardProps {
  label: string;
  value: ReactNode;
  sub?: ReactNode;
  tone?: 'default' | 'dark' | 'emerald' | 'rose' | 'fuchsia' | 'violet';
  icon?: ReactNode;
  contextual?: boolean;
}

export function KpiCard({ label, value, sub, tone = 'default', icon, contextual }: KpiCardProps) {
  const tones = {
    default: 'bg-white border-stone-200',
    dark:    'bg-stone-900 text-white border-stone-900',
    emerald: 'bg-white border-stone-200',
    rose:    'bg-white border-stone-200',
    fuchsia: 'bg-fuchsia-50 border-fuchsia-200',
    violet:  'bg-violet-50 border-violet-200',
  };
  const valueTone = {
    default: 'text-stone-900',
    dark:    'text-white',
    emerald: 'text-emerald-700',
    rose:    'text-rose-700',
    fuchsia: 'text-fuchsia-700',
    violet:  'text-violet-700',
  };
  return (
    <div className={cn('rounded-md border p-3.5 flex flex-col gap-1 relative', tones[tone])}>
      {contextual && (
        <span className="absolute top-2 right-2 text-[9px] uppercase tracking-widest font-medium text-stone-400">contextual</span>
      )}
      <div className="flex items-center gap-1.5">
        {icon && <span className={tone === 'dark' ? 'text-stone-400' : 'text-stone-400'}>{icon}</span>}
        <div className={cn(
          'text-[10px] uppercase tracking-widest font-medium',
          tone === 'dark' ? 'text-stone-400' : 'text-stone-500',
        )}>{label}</div>
      </div>
      <div className={cn(
        'text-[20px] font-semibold tabular-nums tracking-tight',
        valueTone[tone] || 'text-stone-900',
      )}>{value}</div>
      {sub && <div className={cn(
        'text-[11px] tabular-nums',
        tone === 'dark' ? 'text-stone-400' : 'text-stone-500',
      )}>{sub}</div>}
    </div>
  );
}

export function PageHeader({ title, breadcrumb, right }: {
  title: string;
  breadcrumb?: ReactNode;
  right?: ReactNode;
}) {
  return (
    <header className="h-12 px-6 bg-white border-b border-stone-200 flex items-center gap-4 shrink-0">
      <div className="min-w-0 flex-1 flex items-baseline gap-3">
        <div className="text-[15px] font-semibold tracking-tight whitespace-nowrap">{title}</div>
        <div className="text-[11.5px] text-stone-500 whitespace-nowrap truncate">{breadcrumb}</div>
      </div>
      <div className="flex items-center gap-2 shrink-0">{right}</div>
    </header>
  );
}

export function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="block">
      <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-1">{label}</div>
      {children}
    </label>
  );
}
