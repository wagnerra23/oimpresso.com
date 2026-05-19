// atoms-settings.tsx — DriverChip · HealthBadge · Toggle · FileField
import { type ReactNode } from 'react';
import { Upload } from 'lucide-react';
import { DRIVERS, cn, type GatewayKey } from '../_lib/gateway-shared';
import { HEALTH_STYLES, type HealthStatus } from '../_lib/gateway-shared';

export function HealthBadge({ status }: { status: HealthStatus }) {
  const s = HEALTH_STYLES[status] || HEALTH_STYLES.ok;
  return (
    <span className={cn(
      'inline-flex items-center gap-1 text-[10.5px] font-medium px-1.5 py-0.5 rounded border border-current/20',
      s.bg, s.fg,
    )}>
      <span className={cn('w-1 h-1 rounded-full', s.dot)} />
      {s.label}
    </span>
  );
}

export function DriverChip({ driver, size = 'sm' }: { driver: string; size?: 'sm' | 'lg' }) {
  const d = DRIVERS[driver as GatewayKey];
  if (!d) return <span className="text-stone-400">{driver}</span>;
  return (
    <span className="inline-flex items-center gap-1.5">
      <span className={cn(
        'rounded-sm grid place-items-center text-white font-bold tracking-tight shrink-0',
        size === 'lg' ? 'w-7 h-7 text-[12px]' : 'w-5 h-5 text-[10px]',
        d.dot,
      )}>{d.sigla}</span>
      <span className={cn('font-medium', size === 'lg' ? 'text-[13px]' : 'text-[12px] text-stone-700')}>{d.nome}</span>
      {d.deprecated && (
        <span className="text-[9.5px] uppercase font-bold px-1 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-200">deprecated</span>
      )}
    </span>
  );
}

export function Toggle({ on, onConfirm, title }: { on: boolean; onConfirm: (newVal: boolean) => void; title?: string }) {
  return (
    <button
      onClick={e => { e.stopPropagation(); onConfirm(!on); }}
      title={title || (on ? 'Desativar' : 'Ativar')}
      className={cn('inline-flex w-9 h-5 rounded-full p-0.5 transition cursor-pointer', on ? 'bg-emerald-500' : 'bg-stone-300')}
      aria-pressed={on}
      aria-label={on ? 'Ativo — clique pra desativar' : 'Inativo — clique pra ativar'}
    >
      <span className={cn('w-4 h-4 rounded-full bg-white shadow-sm transition', on && 'translate-x-4')} />
    </button>
  );
}

export function FileField({
  label,
  hint,
  accept,
  onFile,
  selectedFileName,
}: {
  label: string;
  hint?: string;
  accept?: string;
  onFile?: (file: File | null) => void;
  selectedFileName?: string;
}) {
  const hasFile = !!selectedFileName;
  return (
    <label className="block cursor-pointer">
      <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-1">{label}</div>
      <div className={cn(
        'h-8 border rounded flex items-center gap-2 px-2 text-[11.5px] transition',
        hasFile
          ? 'bg-emerald-50 border-emerald-300 text-emerald-700'
          : 'bg-white border-stone-300 border-dashed text-stone-500 hover:border-stone-500',
      )}>
        <Upload className="h-3 w-3" />
        <span className="truncate">{hasFile ? selectedFileName : `arrastar arquivo ${accept || ''} ou clicar`}</span>
      </div>
      <input
        type="file"
        accept={accept}
        className="hidden"
        onChange={e => onFile?.(e.target.files?.[0] ?? null)}
      />
      {hint && <div className="text-[10.5px] text-stone-500 mt-1">{hint}</div>}
    </label>
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
