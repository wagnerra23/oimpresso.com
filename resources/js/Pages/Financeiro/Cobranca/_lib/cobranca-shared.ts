// cobranca-shared.ts — types + helpers + tokens (port de pg-shared.jsx Cowork F1)
// Tokens canon DRIVERS/TIPOS/STATUS/ORIGENS + helpers brl/fmtDate/piiMask.

export type CobrancaTipo = 'boleto' | 'pix_cob' | 'pix_cobv' | 'pix_recv' | 'card';
export type CobrancaStatus = 'emitida' | 'paga' | 'vencida' | 'cancelada' | 'erro' | 'pending';
export type OrigemType = 'sale' | 'invoice' | 'subscription_license';
export type GatewayKey = 'inter' | 'c6' | 'asaas' | 'bcb_pix' | 'pesapal' | 'pagarme';

export interface Cobranca {
  id: number;
  tipo: CobrancaTipo;
  status: CobrancaStatus;
  gateway: GatewayKey;
  account_id: number | null;
  contato: string;
  contato_doc: string | null;
  valor: number;
  vencimento: string;
  emitida_em: string | null;
  paga_em: string | null;
  cancelada_em: string | null;
  origem_type: OrigemType | null;
  origem_id: number | null;
  origem_label: string | null;
  nosso_numero: string | null;
  linha_digitavel: string | null;
  codigo_barras: string | null;
  pix_emv: string | null;
  pix_qr_code_path: string | null;
  mandato_ciclo: string | null;
  mandato_inicio: string | null;
  mandato_proximo: string | null;
  card_brand: string | null;
  card_last4: string | null;
  card_3ds: boolean | null;
  erro_msg: string | null;
  cancelamento_motivo: string | null;
}

export interface Account {
  id: number;
  name: string;
  agencia: string | null;
  conta: string | null;
  banco: string | null;
  driver: GatewayKey | null;
}

export interface Gateway {
  id: number;
  nome: string;
  driver: GatewayKey;
  ambiente: 'sandbox' | 'production';
  ativo: boolean;
  account_id: number | null;
  last_check: string;
  health: 'ok' | 'degraded' | 'down';
  latencia: number | null;
  created_at: string;
  warn: string | null;
}

export interface KpiData { qtd: number; valor: number }

export interface CobrancaKpis {
  pago_mes: KpiData;
  vencido: KpiData;
  aberto: KpiData;
  mandatos_ativos: number;
  mrr_pago: number;
}

export interface FunilStep { qtd: number; valor?: number; desc?: string }
export interface CobrancaFunil {
  aberto: FunilStep;
  lembrete: FunilStep;
  cobranca_ativa: FunilStep;
  vencido_5d: FunilStep;
  protesto: FunilStep;
  mandatos_cancelados: number;
}

export interface CobrancaFiltros {
  status: string | null;
  tipo: string | null;
  gateway: string | null;
  account_id: number | null;
  origem: string | null;
  busca: string | null;
}

export interface DriverPricing {
  boleto?: string;
  pix?: string;
  card?: string;
  settlement?: string;
}

export interface DriverToken {
  key: GatewayKey;
  nome: string;
  sigla: string;
  dot: string;
  bg: string;
  fg: string;
  border: string;
  tipos: CobrancaTipo[];
  ambientes: Array<'sandbox' | 'production'>;
  cred: string;
  // Onda 4e.UI #3 (gap P0 estado-da-arte 2026-05-23): comparativo drivers no wizard.
  // Valores REFERENCE 2026 (sites oficiais + docs públicas). Negociáveis com PSP.
  pricing?: DriverPricing;
  requirements?: string[];
  recommendedFor?: string;
  deprecated?: boolean;
  deprecatedReason?: string;
}

export const DRIVERS: Record<GatewayKey, DriverToken> = {
  inter: {
    key: 'inter', nome: 'Inter PJ', sigla: 'IN',
    dot: 'bg-orange-500', bg: 'bg-orange-50', fg: 'text-orange-700', border: 'border-orange-200',
    tipos: ['boleto', 'pix_cob', 'pix_cobv'],
    ambientes: ['sandbox', 'production'],
    cred: 'mTLS · client_id+secret · cert.crt + cert.key',
    pricing: {
      boleto: '250 grátis/mês · depois R$ 1,99',
      pix: 'grátis (chave) · QR dinâmico pode ter tarifa',
      settlement: 'D+0 (mesma data útil)',
    },
    requirements: ['Conta PJ Inter ativa', 'mTLS ICP-Brasil + client_id/secret', 'Onboarding via app Inter Empresas'],
    recommendedFor: 'PJ que já tem Inter como banco principal · alta vazão boleto/PIX recebido',
  },
  c6: {
    key: 'c6', nome: 'C6 Bank', sigla: 'C6',
    dot: 'bg-stone-800', bg: 'bg-stone-100', fg: 'text-stone-800', border: 'border-stone-300',
    tipos: ['boleto'],
    ambientes: ['production'],
    cred: 'agência + conta + código_cliente · CNAB 240',
    pricing: {
      boleto: '200 grátis Web · 2.000 grátis CNAB',
      settlement: 'D+0 (até 13h30)',
    },
    requirements: ['Conta PJ C6 ≥ 6 meses OU CNPJ ≥ 1 ano', 'Cadastro CNAB 240 via Web Banking', 'Sem PIX/cartão (só boleto)'],
    recommendedFor: 'Emissão massiva boleto via CNAB · sem cartão necessário',
  },
  asaas: {
    key: 'asaas', nome: 'Asaas', sigla: 'AS',
    dot: 'bg-blue-600', bg: 'bg-blue-50', fg: 'text-blue-700', border: 'border-blue-200',
    tipos: ['boleto', 'pix_cob', 'card'],
    ambientes: ['sandbox', 'production'],
    cred: 'api_key + webhook_secret',
    pricing: {
      boleto: 'R$ 0,99 (3 meses) · depois R$ 1,99',
      pix: '100 grátis/mês · depois R$ 0,99 → R$ 1,99',
      card: '1,99% + R$ 0,49 (3 meses) · depois 2,99%',
      settlement: 'PIX imediato · cartão D+30',
    },
    requirements: ['Cadastro online self-service (sem KYC enterprise)', 'Sem mensalidade · sem adesão'],
    recommendedFor: 'PME multi-meio (boleto + PIX + cartão) com setup rápido · sandbox público',
  },
  bcb_pix: {
    key: 'bcb_pix', nome: 'BCB · PIX Automático', sigla: 'BC',
    dot: 'bg-emerald-600', bg: 'bg-emerald-50', fg: 'text-emerald-700', border: 'border-emerald-200',
    tipos: ['pix_recv'],
    ambientes: ['sandbox', 'production'],
    cred: 'mTLS BCB + CNPJ recebedor homologado · Resolução BCB 380/2024',
    pricing: {
      pix: 'Pagador: grátis (regulado) · Recebedor: free agreement c/ PSP',
      settlement: 'Imediato',
    },
    requirements: ['Homologação BCB recebedor (Res. 380/2024)', 'mTLS ICP-Brasil', 'Mandato autorizado pagador-a-pagador'],
    recommendedFor: 'Mensalidade recorrente PIX (SaaS, plano gym) · cobrança autorizada 1× pelo cliente',
  },
  pesapal: {
    key: 'pesapal', nome: 'PesaPal', sigla: 'PP',
    dot: 'bg-purple-500', bg: 'bg-purple-50', fg: 'text-purple-700', border: 'border-purple-200',
    tipos: ['card'],
    ambientes: ['production'],
    cred: 'api_key + consumer_secret',
    deprecated: true,
    deprecatedReason: 'Migrar pra Asaas (cartão BR nativo + 3DS)',
  },
  pagarme: {
    key: 'pagarme', nome: 'Pagar.me', sigla: 'PG',
    dot: 'bg-rose-500', bg: 'bg-rose-50', fg: 'text-rose-700', border: 'border-rose-200',
    tipos: ['boleto', 'pix_cob', 'card'],
    ambientes: ['sandbox', 'production'],
    cred: 'secret_key + webhook_secret · HMAC SHA256 (X-Hub-Signature-256)',
    pricing: {
      boleto: 'R$ 3,49 por boleto pago',
      pix: '1,19% por transação',
      card: '4,39% à vista · até 25,29% parcelado 12×',
      settlement: 'PIX imediato · cartão D+30',
    },
    requirements: ['KYC PJ Stone (1-3d homologação)', 'sk_test_* sandbox após approval', 'Webhook dashboard separado'],
    recommendedFor: 'Marketplace + parcelamento longo · grupo Stone · split de pagamentos',
  },
};

export const TIPOS: Record<CobrancaTipo, { label: string; bg: string; fg: string; short: string }> = {
  boleto:   { label: 'Boleto',         bg: 'bg-stone-100',  fg: 'text-stone-700',   short: 'boleto' },
  pix_cob:  { label: 'PIX cob',        bg: 'bg-emerald-50', fg: 'text-emerald-700', short: 'pix' },
  pix_cobv: { label: 'PIX cobv',       bg: 'bg-emerald-50', fg: 'text-emerald-700', short: 'pix v' },
  pix_recv: { label: 'PIX Automático', bg: 'bg-violet-50',  fg: 'text-violet-700',  short: 'pix aut.' },
  card:     { label: 'Cartão',         bg: 'bg-sky-50',     fg: 'text-sky-700',     short: 'cartão' },
};

export const STATUS: Record<CobrancaStatus, { label: string; bg: string; fg: string; dot: string }> = {
  emitida:   { label: 'Emitida',   bg: 'bg-blue-50',    fg: 'text-blue-700',    dot: 'bg-blue-500' },
  paga:      { label: 'Paga',      bg: 'bg-emerald-50', fg: 'text-emerald-700', dot: 'bg-emerald-500' },
  vencida:   { label: 'Vencida',   bg: 'bg-rose-50',    fg: 'text-rose-700',    dot: 'bg-rose-500' },
  cancelada: { label: 'Cancelada', bg: 'bg-stone-100',  fg: 'text-stone-500',   dot: 'bg-stone-400' },
  erro:      { label: 'Erro',      bg: 'bg-rose-100',   fg: 'text-rose-800',    dot: 'bg-rose-600' },
  pending:   { label: 'Pendente',  bg: 'bg-stone-100',  fg: 'text-stone-600',   dot: 'bg-stone-400' },
};

export const ORIGENS: Record<OrigemType, { label: string; bg: string; fg: string }> = {
  sale:                 { label: 'Venda',          bg: 'bg-amber-50',   fg: 'text-amber-700' },
  invoice:              { label: 'Recorrente',     bg: 'bg-violet-50',  fg: 'text-violet-700' },
  subscription_license: { label: 'SaaS Oimpresso', bg: 'bg-fuchsia-50', fg: 'text-fuchsia-700' },
};

// ───────────────── helpers ─────────────────
export const brl = (v: number | null | undefined): string =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

export const brlNoSign = (v: number | null | undefined): string =>
  brl(v).replace('R$', '').trim();

export const fmtDate = (iso: string | null | undefined): string => {
  if (!iso) return '—';
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y}`;
};

export const fmtDateRel = (iso: string | null | undefined, today: string = new Date().toISOString().slice(0, 10)): string => {
  if (!iso) return '—';
  const [yt, mt, dt] = today.split('-').map(Number);
  const [y, m, d] = iso.split('-').map(Number);
  const a = new Date(yt, mt - 1, dt);
  const b = new Date(y, m - 1, d);
  const days = Math.round((b.getTime() - a.getTime()) / 86400000);
  if (days === 0) return 'hoje';
  if (days === 1) return 'amanhã';
  if (days === -1) return 'ontem';
  if (days > 0 && days <= 30) return `em ${days}d`;
  if (days < 0 && days >= -30) return `há ${-days}d`;
  return fmtDate(iso);
};

export const piiMask = (cnpj: string | null | undefined): string => {
  if (!cnpj) return '—';
  if (cnpj.includes('/')) return cnpj.slice(0, 2) + '.***.***' + cnpj.slice(-9);
  return '***.***.***-' + cnpj.slice(-2);
};

export const cn = (...xs: Array<string | false | null | undefined>): string =>
  xs.filter(Boolean).join(' ');

// LS namespace
export const LS_PREFIX = 'oimpresso.financeiro.cobranca.';

export function lsGet<T>(key: string, def: T): T {
  try {
    const v = localStorage.getItem(LS_PREFIX + key);
    return v == null ? def : (JSON.parse(v) as T);
  } catch {
    return def;
  }
}

export function lsSet(key: string, value: unknown): void {
  try {
    localStorage.setItem(LS_PREFIX + key, JSON.stringify(value));
  } catch {
    /* noop */
  }
}
