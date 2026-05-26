// cobranca-shared.ts — types + helpers + tokens (port de pg-shared.jsx Cowork F1)
// Tokens canon DRIVERS/TIPOS/STATUS/ORIGENS + helpers brl/fmtDate/piiMask.

export type CobrancaTipo = 'boleto' | 'pix_cob' | 'pix_cobv' | 'pix_recv' | 'card';
export type CobrancaStatus = 'emitida' | 'paga' | 'vencida' | 'cancelada' | 'erro' | 'pending';
export type OrigemType = 'sale' | 'invoice' | 'subscription_license';
export type GatewayKey =
  // API REST drivers
  | 'inter' | 'c6' | 'asaas' | 'bcb_pix' | 'pagarme'
  // CNAB drivers (Onda 4f.cnab — ADR 0170-bancos-nativos-top5-drivers-separados v3 Wagner 2026-05-26)
  | 'bradesco_cnab' | 'itau_cnab' | 'bb_cnab' | 'santander_cnab' | 'caixa_cnab'
  | 'sicoob_cnab' | 'ailos_cnab' | 'sicredi_cnab' | 'cresol_cnab' | 'banrisul_cnab' | 'btg_cnab';

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
  // Onda 4e.UI #5 (gap P2 estado-da-arte): deep-link pro painel do PSP
  // onde Wagner/Larissa gera a credencial. Reduz fricção do onboarding wizard.
  credentialSource?: { url: string; label: string };
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
      boleto: '250 grátis/mês · depois R$ [redacted Tier 0]',
      pix: 'grátis (chave) · QR dinâmico pode ter tarifa',
      settlement: 'D+0 (mesma data útil)',
    },
    requirements: ['Conta PJ Inter ativa', 'mTLS ICP-Brasil + client_id/secret', 'Onboarding via app Inter Empresas'],
    recommendedFor: 'PJ que já tem Inter como banco principal · alta vazão boleto/PIX recebido',
    credentialSource: { url: 'https://contadigital.inter.co', label: 'Inter Empresas → API Banking' },
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
    credentialSource: { url: 'https://www.c6bank.com.br/boletocobranca/', label: 'C6 Bank → Boleto Cobrança → CNAB 240' },
  },
  asaas: {
    key: 'asaas', nome: 'Asaas', sigla: 'AS',
    dot: 'bg-blue-600', bg: 'bg-blue-50', fg: 'text-blue-700', border: 'border-blue-200',
    tipos: ['boleto', 'pix_cob', 'card'],
    ambientes: ['sandbox', 'production'],
    cred: 'api_key + webhook_secret',
    pricing: {
      boleto: 'R$ [redacted Tier 0] (3 meses) · depois R$ [redacted Tier 0]',
      pix: '100 grátis/mês · depois R$ [redacted Tier 0] → R$ [redacted Tier 0]',
      card: '1,99% + R$ [redacted Tier 0] (3 meses) · depois 2,99%',
      settlement: 'PIX imediato · cartão D+30',
    },
    requirements: ['Cadastro online self-service (sem KYC enterprise)', 'Sem mensalidade · sem adesão'],
    recommendedFor: 'PME multi-meio (boleto + PIX + cartão) com setup rápido · sandbox público',
    credentialSource: { url: 'https://www.asaas.com/customerApiAccessToken/index', label: 'Asaas → Integrações → API Access Token' },
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
    credentialSource: { url: 'https://www.bcb.gov.br/estabilidadefinanceira/pix', label: 'BCB → PIX Automático → Homologação recebedor' },
  },
  pagarme: {
    key: 'pagarme', nome: 'Pagar.me', sigla: 'PG',
    dot: 'bg-rose-500', bg: 'bg-rose-50', fg: 'text-rose-700', border: 'border-rose-200',
    tipos: ['boleto', 'pix_cob', 'card'],
    ambientes: ['sandbox', 'production'],
    cred: 'secret_key + webhook_secret · HMAC SHA256 (X-Hub-Signature-256)',
    pricing: {
      boleto: 'R$ [redacted Tier 0] por boleto pago',
      pix: '1,19% por transação',
      card: '4,39% à vista · até 25,29% parcelado 12×',
      settlement: 'PIX imediato · cartão D+30',
    },
    requirements: ['KYC PJ Stone (1-3d homologação)', 'sk_test_* sandbox após approval', 'Webhook dashboard separado'],
    recommendedFor: 'Marketplace + parcelamento longo · grupo Stone · split de pagamentos',
    credentialSource: { url: 'https://dashboard.pagar.me', label: 'Pagar.me Dashboard → Configurações → Chaves' },
  },

  // ─── CNAB DRIVERS (Onda 4f.cnab — file-based, boleto registrado via arquivo remessa) ───
  // ADR 0170-bancos-nativos-top5-drivers-separados (v3 Wagner 2026-05-26 · 11 bancos)
  // Cliente emite dia 1 sem esperar homologação Open API (14-45d). Coexistem com REST.

  bradesco_cnab: {
    key: 'bradesco_cnab', nome: 'Bradesco', sigla: 'BR',
    dot: 'bg-red-600', bg: 'bg-red-50', fg: 'text-red-700', border: 'border-red-200',
    tipos: ['boleto'],
    ambientes: ['production'],
    cred: 'agência + conta + carteira (02/04/09/21/26) + cedente CNPJ · CNAB 240',
    pricing: {
      boleto: 'Tarifa boleto Bradesco (R$ [redacted Tier 0]-3,50/boleto típico PJ)',
      settlement: 'D+1 após pagamento',
    },
    requirements: ['Conta PJ Bradesco ativa', 'Convênio cobrança liberado pelo gerente PJ', 'Carteira definida (recomendado 09)', 'Sem PIX/cartão (só boleto)'],
    recommendedFor: 'PJ que já tem Bradesco como banco principal · emissão imediata sem esperar API Open Banking (14d homologação)',
    credentialSource: { url: 'https://banco.bradesco/internet-banking-pessoa-juridica', label: 'Bradesco Net Empresa → Convênio Cobrança' },
  },

  itau_cnab: {
    key: 'itau_cnab', nome: 'Itaú', sigla: 'IT',
    dot: 'bg-orange-600', bg: 'bg-orange-50', fg: 'text-orange-700', border: 'border-orange-300',
    tipos: ['boleto'],
    ambientes: ['production'],
    cred: 'agência + conta + DV + carteira (109/110/112/115/188) + cedente CNPJ · CNAB 240',
    pricing: {
      boleto: 'Tarifa boleto Itaú (R$ [redacted Tier 0]-3,80/boleto típico PJ)',
      settlement: 'D+1 após pagamento',
    },
    requirements: ['Conta PJ Itaú Empresas ativa', 'Convênio cobrança liberado pelo gerente PJ', 'Carteira 109 (cobrança simples com registro) padrão', 'CNAB 240 (Manual Itaú v10.2 descontinuou 400)'],
    recommendedFor: 'PJ Itaú Empresas · emissão imediata sem esperar Open API (2-3 semanas homologação)',
    credentialSource: { url: 'https://www.itau.com.br/empresas/', label: 'Itaú Empresas → Cobrança Bancária → Convênio' },
  },

  bb_cnab: {
    key: 'bb_cnab', nome: 'Banco do Brasil', sigla: 'BB',
    dot: 'bg-yellow-500', bg: 'bg-yellow-50', fg: 'text-yellow-800', border: 'border-yellow-300',
    tipos: ['boleto'],
    ambientes: ['production'],
    cred: 'agência + conta + convênio CIP + carteira (17 padrão) + cedente CNPJ · CNAB 240',
    pricing: {
      boleto: 'Tarifa BB cobrança (R$ [redacted Tier 0]-3,50/boleto típico PJ)',
      settlement: 'D+1 após pagamento',
    },
    requirements: ['Conta PJ BB ativa', 'Convênio CIP off-API liberado presencialmente pelo gerente PJ', 'numeroConvenio obrigatório (sem ele boleto não registra)', 'Carteira 17 (Cobrança Simples Eletrônica) padrão PJ'],
    recommendedFor: 'PJ Banco do Brasil · cobertura nacional · sandbox aberto pra dev mas convênio presencial',
    credentialSource: { url: 'https://www.bb.com.br/empresas', label: 'BB → PJ → Cobrança → Convênio CIP' },
  },

  santander_cnab: {
    key: 'santander_cnab', nome: 'Santander', sigla: 'SA',
    dot: 'bg-red-700', bg: 'bg-red-50', fg: 'text-red-800', border: 'border-red-300',
    tipos: ['boleto'],
    ambientes: ['production'],
    cred: 'agência + conta + código_cliente + carteira (101 padrão) + cedente CNPJ · CNAB 240',
    pricing: {
      boleto: 'Tarifa Santander cobrança (R$ [redacted Tier 0]-4,00/boleto típico PJ)',
      settlement: 'D+1 após pagamento',
    },
    requirements: ['Conta PJ Santander Empresas ativa', 'Código cliente SIGCB obtido com gerente PJ', 'Homologação 3-5 semanas (mais burocrático que outros)', 'Carteira 101 (Cobrança Simples com Registro) padrão'],
    recommendedFor: 'PJ Santander · alternativa CNAB enquanto cliente não compra cert A1 ICP-Brasil pra API REST (R$ [redacted Tier 0]-400/ano)',
    credentialSource: { url: 'https://www.santander.com.br/pj', label: 'Santander Empresas → Cobrança → Código Cliente SIGCB' },
  },

  caixa_cnab: {
    key: 'caixa_cnab', nome: 'Caixa', sigla: 'CX',
    dot: 'bg-blue-700', bg: 'bg-blue-50', fg: 'text-blue-800', border: 'border-blue-300',
    tipos: ['boleto'],
    ambientes: ['production'],
    cred: 'agência + código_cliente SIGCB + carteira (RG) + cedente CNPJ · CNAB 240',
    pricing: {
      boleto: 'Tarifa Caixa SIGCB (R$ [redacted Tier 0]-3,50/boleto típico PJ)',
      settlement: 'D+1 após pagamento',
    },
    requirements: ['Conta PJ Caixa ativa', 'Convênio SIGCB presencial via gerente PJ (30-90 dias)', 'Sem opção API REST viável em 2026 (portal devs.caixa.gov.br fora)', 'Carteira RG (Cobrança Registrada) única ativa'],
    recommendedFor: 'PJ Caixa · ÚNICA opção viável Caixa hoje (API REST não funciona pra PME)',
    credentialSource: { url: 'https://www.caixa.gov.br/empresa', label: 'Caixa Empresa → Cobrança SIGCB → Convênio' },
  },

  sicoob_cnab: {
    key: 'sicoob_cnab', nome: 'Sicoob', sigla: 'SO',
    dot: 'bg-emerald-700', bg: 'bg-emerald-50', fg: 'text-emerald-800', border: 'border-emerald-300',
    tipos: ['boleto'],
    ambientes: ['production'],
    cred: 'agência + conta + convênio + carteira (1/3) + modalidade + cedente CNPJ · CNAB 240',
    pricing: {
      boleto: 'Tarifa cooperativa Sicoob (R$ [redacted Tier 0]-3,00/boleto · varia por singular)',
      settlement: 'D+1 após pagamento',
    },
    requirements: ['Conta PJ cooperativa Sicoob (singular)', 'Convênio cobrança liberado pela cooperativa', 'Carteira 1 (cobrança simples) padrão', 'Modalidade definida (auto-explicativa singular)'],
    recommendedFor: 'PJ associada Sicoob (sistema cooperativista nacional · 5.000+ agências) · tarifa cooperativa vantajosa',
    credentialSource: { url: 'https://www.sicoob.com.br/web/empresarial/cobranca', label: 'Sicoob Empresarial → Cobrança → Convênio' },
  },

  ailos_cnab: {
    key: 'ailos_cnab', nome: 'Ailos (ex-Cecred)', sigla: 'AI',
    dot: 'bg-teal-600', bg: 'bg-teal-50', fg: 'text-teal-700', border: 'border-teal-200',
    tipos: ['boleto'],
    ambientes: ['production'],
    cred: 'agência+DV + conta+DV + carteira (1) + convênio + cedente CNPJ · CNAB 240',
    pricing: {
      boleto: 'Tarifa cooperativa Ailos (R$ [redacted Tier 0]-2,50/boleto típico)',
      settlement: 'D+1 após pagamento',
    },
    requirements: ['Conta PJ cooperativa Ailos (centrais SC/PR/RS)', 'Convênio liberado pela cooperativa', 'Cecred rebatizado Ailos em 2018'],
    recommendedFor: 'PJ associada Ailos (cooperativa Sul · forte SC/PR) · ex-clientes Cecred',
    credentialSource: { url: 'https://www.ailos.coop.br', label: 'Ailos → Internet Banking PJ → Cobrança' },
  },

  sicredi_cnab: {
    key: 'sicredi_cnab', nome: 'Sicredi', sigla: 'SR',
    dot: 'bg-lime-700', bg: 'bg-lime-50', fg: 'text-lime-800', border: 'border-lime-300',
    tipos: ['boleto'],
    ambientes: ['production'],
    cred: 'cooperativa + posto + conta + carteira (A) + byte_id_arquivo + cedente CNPJ · CNAB 240',
    pricing: {
      boleto: 'Tarifa cooperativa Sicredi (R$ [redacted Tier 0]-3,00/boleto · varia por singular)',
      settlement: 'D+1 após pagamento',
    },
    requirements: ['Conta PJ cooperativa Sicredi (2º maior sistema cooperativista BR)', 'Convênio cobrança liberado pela cooperativa', 'Carteira A (cobrança simples) padrão', 'byte_id_arquivo controlado por singular'],
    recommendedFor: 'PJ associada Sicredi · forte Centro-Sul · 2º maior cooperativista BR após Sicoob',
    credentialSource: { url: 'https://www.sicredi.com.br/empresas/', label: 'Sicredi Empresas → Cobrança → Convênio' },
  },

  cresol_cnab: {
    key: 'cresol_cnab', nome: 'Cresol', sigla: 'CR',
    dot: 'bg-green-700', bg: 'bg-green-50', fg: 'text-green-800', border: 'border-green-300',
    tipos: ['boleto'],
    ambientes: ['production'],
    cred: 'cooperativa + posto + conta + carteira + cedente CNPJ · CNAB 240',
    pricing: {
      boleto: 'Tarifa cooperativa Cresol (R$ [redacted Tier 0]-2,50/boleto típico)',
      settlement: 'D+1 após pagamento',
    },
    requirements: ['Conta PJ cooperativa Cresol (Centro-Sul rural)', 'Convênio liberado pela cooperativa', 'Estrutura similar Sicredi (cooperativa+posto)'],
    recommendedFor: 'PJ associada Cresol · cooperativa rural Centro-Sul · agronegócio familiar',
    credentialSource: { url: 'https://www.cresol.com.br', label: 'Cresol → Internet Banking PJ → Cobrança' },
  },

  banrisul_cnab: {
    key: 'banrisul_cnab', nome: 'Banrisul', sigla: 'BA',
    dot: 'bg-sky-700', bg: 'bg-sky-50', fg: 'text-sky-800', border: 'border-sky-300',
    tipos: ['boleto'],
    ambientes: ['production'],
    cred: 'agência + conta + carteira (1) + código_cliente + cedente CNPJ · CNAB 240',
    pricing: {
      boleto: 'Tarifa Banrisul cobrança (R$ [redacted Tier 0]-3,50/boleto típico PJ)',
      settlement: 'D+1 após pagamento',
    },
    requirements: ['Conta PJ Banrisul (RS/SC)', 'Código cliente 7+2DV obtido com gerente PJ via carta-circular', 'Carteira 1 (cobrança simples) padrão pra gráficas/confecções', 'Layout 240 (Banrisul descontinuou 400 em 12/2020)'],
    recommendedFor: 'PJ regional Sul (Banrisul presença forte SC/RS) · gráficas e confecções segmento vestuário',
    credentialSource: { url: 'https://www.banrisul.com.br/bob/pj', label: 'Banrisul Internet Banking PJ → Cobrança' },
  },

  btg_cnab: {
    key: 'btg_cnab', nome: 'BTG Pactual', sigla: 'BT',
    dot: 'bg-slate-800', bg: 'bg-slate-100', fg: 'text-slate-800', border: 'border-slate-300',
    tipos: ['boleto'],
    ambientes: ['production'],
    cred: 'agência + conta + carteira (1=Simples PJ) + código_cliente 12 dígitos + cedente CNPJ · CNAB 240',
    pricing: {
      boleto: 'Tarifa BTG Pactual cobrança PJ (negociável)',
      settlement: 'D+1 após pagamento',
    },
    requirements: ['Conta PJ BTG Pactual Empresas', 'Código cliente 12 dígitos obrigatório (agenciaCodigoBeneficiario)', 'CNPJ conta = CNPJ beneficiário', 'Carteira 1 (Cobrança Simples PJ) padrão'],
    recommendedFor: 'PJ BTG Pactual · banco de investimento com cobrança PJ moderna · clientes high-end',
    credentialSource: { url: 'https://www.btgpactualbusiness.com', label: 'BTG Pactual Business → Cobrança PJ' },
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
