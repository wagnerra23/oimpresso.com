// gateway-shared.ts — types + tokens specific to Settings/PaymentGateways
// Reusa DRIVERS/Account/GatewayKey de Financeiro/Cobranca/_lib/cobranca-shared.
export {
  DRIVERS, TIPOS, brl, fmtDate, fmtDateRel, cn, lsGet, lsSet,
} from '../../../Financeiro/Cobranca/_lib/cobranca-shared';
export type {
  Account, DriverToken, GatewayKey, Gateway as CobrancaGateway,
} from '../../../Financeiro/Cobranca/_lib/cobranca-shared';

export type HealthStatus = 'ok' | 'degraded' | 'down';

export interface SettingsGateway {
  id: number;
  driver: string;
  nome: string;
  ambiente: 'sandbox' | 'production';
  ativo: boolean;
  account_id: number | null;
  last_check: string | null;
  health: HealthStatus;
  latencia: number | null;
  created_at: string | null;
  warn: string | null;
}

export interface SettingsKpis {
  ativos: number;
  total: number;
  fail: number;
  cobs_hoje: number;
}

export const HEALTH_STYLES: Record<HealthStatus, { bg: string; fg: string; dot: string; label: string }> = {
  ok:       { bg: 'bg-emerald-50', fg: 'text-emerald-700', dot: 'bg-emerald-500', label: 'OK' },
  degraded: { bg: 'bg-amber-50',   fg: 'text-amber-700',   dot: 'bg-amber-500',   label: 'Degradado' },
  down:     { bg: 'bg-rose-50',    fg: 'text-rose-700',    dot: 'bg-rose-500',    label: 'Fora do ar' },
};

// LS namespace Settings
export const LS_PREFIX_SETTINGS = 'oimpresso.settings.gateways.';
export function lsGetSettings<T>(key: string, def: T): T {
  try {
    const v = localStorage.getItem(LS_PREFIX_SETTINGS + key);
    return v == null ? def : (JSON.parse(v) as T);
  } catch {
    return def;
  }
}
export function lsSetSettings(key: string, value: unknown): void {
  try {
    localStorage.setItem(LS_PREFIX_SETTINGS + key, JSON.stringify(value));
  } catch { /* noop */ }
}
