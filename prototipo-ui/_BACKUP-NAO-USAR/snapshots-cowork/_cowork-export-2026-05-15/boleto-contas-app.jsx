/* @ts-nocheck */
/* eslint-disable */
// F1 Cowork — Boleto + Contas/Caixa (Inter)
// IIFE: encapsula tudo, expõe window.BoletosPage.
(() => {
const { useState, useMemo, useEffect, useCallback } = React;

// ───────────────────── helpers ─────────────────────
const brl = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);
const brlNoSign = (v) => brl(v).replace('R$', '').trim();
const fmtDate = (iso) => {
  if (!iso) return '—';
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y}`;
};
const cn = (...xs) => xs.filter(Boolean).join(' ');

// ───────────────────── mock data ─────────────────────
const BANCOS = {
  '077': { nome: 'Banco Inter', short: 'Inter', cor: 'bg-orange-500' },
  '001': { nome: 'Banco do Brasil', short: 'BB', cor: 'bg-yellow-400' },
  '341': { nome: 'Itaú Unibanco', short: 'Itaú', cor: 'bg-orange-700' },
  '748': { nome: 'Sicredi', short: 'Sicredi', cor: 'bg-green-600' },
  '756': { nome: 'Sicoob', short: 'Sicoob', cor: 'bg-green-700' },
  '274': { nome: 'Asaas', short: 'Asaas', cor: 'bg-blue-600' },
};

const CONTAS = [
  {
    id: 12, name: 'Inter PJ · Operacional', account_number: '4521-7', complemento_id: 4,
    banco_codigo: '077', agencia: '0001', agencia_dv: null, conta_dv: '7',
    carteira: '112', codigo_cedente: '4892331', beneficiario_documento: '[REDACTED]',
    beneficiario_razao_social: 'ROTA LIVRE COMUNICAÇÃO VISUAL LTDA', ativo_para_boleto: true,
    rb_gateway_credential_id: 18, gateway_banco: 'inter', gateway_ambiente: 'production',
    gateway_ativo: true, gateway_client_id: '4f9c2a8e-7b1d-4e3f-9c80-2a8e7b1d4e3f',
    saldo: 18420.50, saldo_d1: 16800.00, saldo_d_7: 22100.00,
    ultima_movimentacao: '2026-05-11T09:14:00', boletos_aberto: 14, boletos_aberto_valor: 12840.00,
  },
  {
    id: 8, name: 'Itaú PJ · Reserva', account_number: '8842-1', complemento_id: 2,
    banco_codigo: '341', agencia: '0438', agencia_dv: null, conta_dv: '1',
    carteira: '109', codigo_cedente: null, beneficiario_documento: '[REDACTED]',
    beneficiario_razao_social: 'ROTA LIVRE COMUNICAÇÃO VISUAL LTDA', ativo_para_boleto: true,
    rb_gateway_credential_id: null, gateway_banco: null,
    saldo: 41200.00, saldo_d1: 41200.00, saldo_d_7: 38800.00,
    ultima_movimentacao: '2026-05-09T16:32:00', boletos_aberto: 0, boletos_aberto_valor: 0,
  },
  {
    id: 21, name: 'Caixa interno · loja', account_number: 'CX-001', complemento_id: null,
    banco_codigo: null, agencia: null, ativo_para_boleto: false,
    saldo: 480.00, saldo_d1: 480.00, saldo_d_7: 320.00,
    ultima_movimentacao: '2026-05-11T08:00:00', boletos_aberto: 0, boletos_aberto_valor: 0,
    is_caixa: true,
  },
  {
    id: 4, name: 'Sicoob · Antigo', account_number: '12440-3', complemento_id: 1,
    banco_codigo: '756', agencia: '3001', conta_dv: '3',
    carteira: '1', ativo_para_boleto: false,
    saldo: 0, saldo_d1: 0, saldo_d_7: 0,
    ultima_movimentacao: '2025-12-20T10:00:00', boletos_aberto: 0, boletos_aberto_valor: 0,
  },
];

const BOLETOS = [
  { id: 1841, titulo_id: 9821, titulo_numero: 'TR-9821', cliente: 'Padaria Pão Quente LTDA', cliente_doc: '[REDACTED]', conta_id: 12, nosso_numero: '0000000018410', linha_digitavel: '07790.00018 41000.001234 56789.012345 4 12340000048000', codigo_barras: '07794123400000480000000018410000123456789012345', valor: 480.00, vencimento: '2026-05-14', status: 'registrado', strategy: 'gateway_inter', enviado_em: '2026-05-09T14:22:00', pago_em: null, created_at: '2026-05-09T14:21:00' },
  { id: 1840, titulo_id: 9820, titulo_numero: 'TR-9820', cliente: 'Sapataria Bom Jesus', cliente_doc: '[REDACTED]', conta_id: 12, nosso_numero: '0000000018400', linha_digitavel: '07790.00018 40000.005678 90123.456789 1 12350000128000', codigo_barras: '07791123500001280000000018400000567890123456789', valor: 1280.00, vencimento: '2026-05-15', status: 'registrado', strategy: 'gateway_inter', enviado_em: '2026-05-09T11:18:00', pago_em: null, created_at: '2026-05-09T11:17:00' },
  { id: 1838, titulo_id: 9818, titulo_numero: 'TR-9818', cliente: 'Distrib. Norte Mat. Elétrico', cliente_doc: '[REDACTED]', conta_id: 12, nosso_numero: '0000000018380', linha_digitavel: '07790.00018 38000.009012 34567.890123 2 12300000342500', codigo_barras: '07792123000003425000000018380000901234567890123', valor: 3425.00, vencimento: '2026-05-12', status: 'registrado', strategy: 'gateway_inter', enviado_em: '2026-05-08T09:02:00', pago_em: null, created_at: '2026-05-08T09:01:00' },
  { id: 1835, titulo_id: 9815, titulo_numero: 'TR-9815', cliente: 'Açougue do Bairro ME', cliente_doc: '[REDACTED]', conta_id: 12, nosso_numero: '0000000018350', linha_digitavel: '07790.00018 35000.001478 96321.012345 5 12200000089000', codigo_barras: '07795122000000890000000018350000147896321012345', valor: 890.00, vencimento: '2026-05-08', status: 'pago', strategy: 'gateway_inter', enviado_em: '2026-05-06T14:12:00', pago_em: '2026-05-08T11:32:00', created_at: '2026-05-06T14:11:00' },
  { id: 1832, titulo_id: 9812, titulo_numero: 'TR-9812', cliente: 'Imobiliária Centro', cliente_doc: '[REDACTED]', conta_id: 12, nosso_numero: '0000000018320', linha_digitavel: '07790.00018 32000.001239 87456.987654 8 12150000045000', codigo_barras: '07798121500000450000000018320000123987456987654', valor: 4500.00, vencimento: '2026-05-03', status: 'pago', strategy: 'gateway_inter', enviado_em: '2026-05-02T10:00:00', pago_em: '2026-05-03T09:14:00', created_at: '2026-05-02T09:58:00' },
  { id: 1829, titulo_id: 9809, titulo_numero: 'TR-9809', cliente: 'Mercado União', cliente_doc: '[REDACTED]', conta_id: 12, nosso_numero: '0000000018290', linha_digitavel: '07790.00018 29000.003214 56987.852014 1 12120000167000', codigo_barras: '07791121200001670000000018290000321456987852014', valor: 1670.00, vencimento: '2026-05-02', status: 'vencido', strategy: 'gateway_inter', enviado_em: '2026-05-01T08:30:00', pago_em: null, created_at: '2026-05-01T08:29:00' },
  { id: 1825, titulo_id: 9805, titulo_numero: 'TR-9805', cliente: 'Farmácia Vida', cliente_doc: '[REDACTED]', conta_id: 12, nosso_numero: '0000000018250', linha_digitavel: '07790.00018 25000.008541 23698.745120 3 12100000058000', codigo_barras: '07793121000000580000000018250000854123698745120', valor: 580.00, vencimento: '2026-05-01', status: 'cancelado', strategy: 'gateway_inter', enviado_em: '2026-04-30T12:00:00', pago_em: null, created_at: '2026-04-30T11:59:00' },
  { id: 1822, titulo_id: 9802, titulo_numero: 'TR-9802', cliente: 'Padaria Pão Quente LTDA', cliente_doc: '[REDACTED]', conta_id: 12, nosso_numero: '0000000018220', linha_digitavel: '07790.00018 22000.001234 56789.012345 6 12080000048000', codigo_barras: '07796120800000480000000018220000123456789012345', valor: 480.00, vencimento: '2026-04-25', status: 'pago', strategy: 'gateway_inter', enviado_em: '2026-04-23T10:00:00', pago_em: '2026-04-25T14:22:00', created_at: '2026-04-23T09:59:00' },
];

const REMESSAS_CNAB = [
  { id: 'r-008', tipo: 'remessa', filename: 'REM_077_20260509_001.REM', criado_em: '2026-05-09T18:00:00', qtd_titulos: 14, valor_total: 18420.00, status: 'enviada', enviado_em: '2026-05-09T18:02:00' },
  { id: 'r-007', tipo: 'retorno', filename: 'RET_077_20260508.RET', criado_em: '2026-05-08T22:18:00', qtd_titulos: 8, valor_total: 9420.00, status: 'processado' },
  { id: 'r-006', tipo: 'remessa', filename: 'REM_077_20260507_002.REM', criado_em: '2026-05-07T17:30:00', qtd_titulos: 6, valor_total: 4220.00, status: 'enviada', enviado_em: '2026-05-07T17:32:00' },
  { id: 'r-005', tipo: 'retorno', filename: 'RET_077_20260506.RET', criado_em: '2026-05-06T23:01:00', qtd_titulos: 12, valor_total: 14210.00, status: 'processado' },
];

const TITULOS_DISPONIVEIS = [
  { id: 9930, numero: 'TR-9930', cliente: 'Studio Alfa Design', valor: 2480, vencimento: '2026-05-18' },
  { id: 9929, numero: 'TR-9929', cliente: 'Auto Posto Rota Mor', valor: 890, vencimento: '2026-05-17' },
  { id: 9928, numero: 'TR-9928', cliente: 'Loja Casa & Cia', valor: 4120, vencimento: '2026-05-20' },
  { id: 9927, numero: 'TR-9927', cliente: 'Padaria Pão Quente LTDA', valor: 380, vencimento: '2026-05-16' },
];

// ───────────────────── icons ─────────────────────
const Icon = ({ d, size = 14, className = '' }) => (
  <svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" className={className}>{d}</svg>
);
const I = {
  search: <Icon size={14} d={<><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></>} />,
  plus: <Icon d={<><path d="M12 5v14M5 12h14"/></>} />,
  download: <Icon d={<><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></>} />,
  upload: <Icon d={<><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></>} />,
  copy: <Icon d={<><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></>} />,
  check: <Icon d={<path d="M20 6 9 17l-5-5"/>} />,
  x: <Icon d={<><path d="M18 6 6 18M6 6l18 18"/></>} />,
  more: <Icon d={<><circle cx="12" cy="6" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="18" r="1"/></>} />,
  link: <Icon d={<><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></>} />,
  webhook: <Icon d={<><circle cx="18" cy="18" r="3"/><circle cx="6" cy="6" r="3"/><circle cx="18" cy="6" r="3"/><path d="M9 6h6M18 9v6M15 18H9"/></>} />,
  shield: <Icon d={<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>} />,
  refresh: <Icon d={<><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/></>} />,
  bank: <Icon d={<><path d="M3 21h18M3 10h18M5 6l7-3 7 3M5 10v11M19 10v11M9 14v4M15 14v4"/></>} />,
  wallet: <Icon d={<><path d="M20 12V8a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4z"/><path d="M22 13h-4a2 2 0 0 1 0-4h4"/></>} />,
  settings: <Icon d={<><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.36.16.69.4.96.71"/></>} />,
  external: <Icon size={12} d={<><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6M10 14 21 3"/></>} />,
  arrow_up: <Icon d={<path d="m5 12 7-7 7 7M12 19V5"/>} />,
  arrow_down: <Icon d={<path d="M12 5v14M19 12l-7 7-7-7"/>} />,
  receipt: <Icon d={<><path d="M4 2v20l3-2 3 2 3-2 3 2 3-2 3 2V2H4z"/><path d="M8 7h8M8 11h8M8 15h5"/></>} />,
  eye: <Icon d={<><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></>} />,
  filter: <Icon d={<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>} />,
  alert: <Icon d={<><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></>} />,
  dot: <span className="inline-block w-1.5 h-1.5 rounded-full bg-current align-middle" />,
};

// ───────────────────── chrome ─────────────────────
function Sidebar({ active, onChange }) {
  const items = [
    { id: 'unificado', label: 'Visão unificada', icon: I.receipt, dim: true },
    { id: 'boletos', label: 'Boletos', icon: I.receipt, badge: 14 },
    { id: 'contas', label: 'Contas & Caixa', icon: I.wallet },
    { id: 'fluxo', label: 'Fluxo de caixa', icon: I.arrow_down, dim: true },
    { id: 'conciliacao', label: 'Conciliação', icon: I.refresh, dim: true },
    { id: 'dre', label: 'DRE / Relatórios', icon: I.eye, dim: true },
    { id: 'plano', label: 'Plano de contas', icon: I.bank, dim: true },
  ];
  return (
    <aside className="w-[212px] bg-white border-r border-stone-200 h-screen flex flex-col">
      <div className="px-4 h-12 flex items-center border-b border-stone-200">
        <div className="text-[11.5px] uppercase tracking-widest text-stone-500 font-semibold">Financeiro</div>
      </div>
      <nav className="flex-1 px-2 py-3 space-y-0.5">
        {items.map(n => (
          <button key={n.id} onClick={() => onChange(n.id)} className={cn(
            "w-full h-8 px-2.5 rounded text-left flex items-center gap-2.5 text-[12.5px] transition",
            active === n.id ? "bg-stone-900 text-white" : n.dim ? "text-stone-400 hover:bg-stone-50" : "text-stone-700 hover:bg-stone-50"
          )}>
            <span className={active === n.id ? "text-white" : n.dim ? "text-stone-300" : "text-stone-500"}>{n.icon}</span>
            <span className="flex-1">{n.label}</span>
            {n.badge != null && <span className={cn("text-[10px] tabular-nums px-1.5 py-0.5 rounded-full", active === n.id ? "bg-white/20 text-white" : "bg-stone-200 text-stone-700")}>{n.badge}</span>}
          </button>
        ))}
      </nav>
      <div className="px-3 py-3 border-t border-stone-200">
        <div className="text-[10.5px] text-stone-500">ROTA LIVRE Com. Visual</div>
        <div className="text-[11px] text-stone-400 mt-0.5">Eliana · operadora</div>
      </div>
    </aside>
  );
}

function Header({ title, breadcrumb, right }) {
  return (
    <header className="h-12 px-6 bg-white border-b border-stone-200 flex items-center gap-4 shrink-0">
      <div className="min-w-0 flex-1 flex items-baseline gap-3">
        <div className="text-[15px] font-semibold tracking-tight whitespace-nowrap">{title}</div>
        <div className="text-[11.5px] text-stone-500 whitespace-nowrap">{breadcrumb}</div>
      </div>
      <div className="flex items-center gap-2 shrink-0 tab text-[11.5px] text-stone-500">{right}</div>
    </header>
  );
}

// ───────────────────── shared atoms ─────────────────────
function Btn({ variant = 'default', size = 'sm', children, className, ...rest }) {
  const sizes = { sm: 'h-7 px-2.5 text-[12px]', md: 'h-8 px-3 text-[12.5px]', xs: 'h-6 px-2 text-[11.5px]' };
  const variants = {
    default: 'bg-stone-900 text-white hover:bg-stone-800',
    outline: 'bg-white border border-stone-300 text-stone-800 hover:bg-stone-50',
    ghost: 'text-stone-600 hover:bg-stone-100',
    primary: 'bg-orange-500 text-white hover:bg-orange-600',
    danger: 'text-rose-700 hover:bg-rose-50',
  };
  return <button {...rest} className={cn('inline-flex items-center gap-1.5 rounded-md font-medium transition disabled:opacity-50 disabled:cursor-not-allowed', sizes[size], variants[variant], className)}>{children}</button>;
}

function StatusBadge({ status }) {
  const map = {
    gerado_mock: ['Mock', 'bg-purple-50 text-purple-700 border-purple-200'],
    gerado: ['Gerado', 'bg-stone-100 text-stone-700 border-stone-200'],
    enviado: ['Enviado', 'bg-blue-50 text-blue-700 border-blue-200'],
    registrado: ['Registrado', 'bg-blue-50 text-blue-700 border-blue-200'],
    pago: ['Pago', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
    vencido: ['Vencido', 'bg-rose-50 text-rose-700 border-rose-200'],
    cancelado: ['Cancelado', 'bg-stone-100 text-stone-500 border-stone-200'],
  };
  const [label, cls] = map[status] || ['—', 'bg-stone-100 text-stone-600'];
  return <span className={cn("inline-flex items-center gap-1 text-[10.5px] font-medium px-1.5 py-0.5 rounded border", cls)}>
    <span className="w-1 h-1 rounded-full bg-current opacity-70" />{label}
  </span>;
}

function KpiCard({ label, value, sub, tone = 'default', icon }) {
  const tones = {
    default: 'bg-white border-stone-200',
    dark: 'bg-stone-900 text-white border-stone-900',
    emerald: 'bg-white border-stone-200',
    amber: 'bg-white border-stone-200',
  };
  return (
    <div className={cn("rounded-md border p-3.5 flex flex-col gap-1", tones[tone])}>
      <div className="flex items-center gap-1.5">
        {icon && <span className={cn(tone === 'dark' ? 'text-stone-400' : 'text-stone-400')}>{icon}</span>}
        <div className={cn("text-[10px] uppercase tracking-widest font-medium", tone === 'dark' ? 'text-stone-400' : 'text-stone-500')}>{label}</div>
      </div>
      <div className="text-[20px] font-semibold tabular-nums tracking-tight">{value}</div>
      {sub && <div className={cn("text-[11px] tabular-nums", tone === 'dark' ? 'text-stone-400' : 'text-stone-500')}>{sub}</div>}
    </div>
  );
}

// ───────────────────── BOLETOS ─────────────────────
function TelaBoletos({ onOpen }) {
  const [tab, setTab] = useState('all');
  const [busca, setBusca] = useState('');
  const [contaFilter, setContaFilter] = useState('all');
  const [drawer, setDrawer] = useState(null);
  const [emitirOpen, setEmitirOpen] = useState(false);
  const [remessaPanel, setRemessaPanel] = useState(false);

  const tabs = [
    { id: 'all', label: 'Todos', count: BOLETOS.length },
    { id: 'open', label: 'Em aberto', count: BOLETOS.filter(b => ['registrado','enviado','gerado'].includes(b.status)).length },
    { id: 'pago', label: 'Pagos', count: BOLETOS.filter(b => b.status === 'pago').length },
    { id: 'vencido', label: 'Vencidos', count: BOLETOS.filter(b => b.status === 'vencido').length },
    { id: 'cancelado', label: 'Cancelados', count: BOLETOS.filter(b => b.status === 'cancelado').length },
  ];

  const filtered = useMemo(() => {
    return BOLETOS.filter(b => {
      if (tab === 'open' && !['registrado','enviado','gerado'].includes(b.status)) return false;
      if (tab === 'pago' && b.status !== 'pago') return false;
      if (tab === 'vencido' && b.status !== 'vencido') return false;
      if (tab === 'cancelado' && b.status !== 'cancelado') return false;
      if (contaFilter !== 'all' && b.conta_id !== parseInt(contaFilter)) return false;
      if (busca) {
        const q = busca.toLowerCase();
        if (!b.cliente.toLowerCase().includes(q) && !b.nosso_numero.includes(busca) && !b.titulo_numero.toLowerCase().includes(q)) return false;
      }
      return true;
    });
  }, [tab, busca, contaFilter]);

  const totais = useMemo(() => ({
    valor_aberto: BOLETOS.filter(b => ['registrado','enviado'].includes(b.status)).reduce((s, b) => s + b.valor, 0),
    qtd_aberto: BOLETOS.filter(b => ['registrado','enviado'].includes(b.status)).length,
    valor_pago_mes: BOLETOS.filter(b => b.status === 'pago').reduce((s, b) => s + b.valor, 0),
    qtd_pago_mes: BOLETOS.filter(b => b.status === 'pago').length,
    valor_vencido: BOLETOS.filter(b => b.status === 'vencido').reduce((s, b) => s + b.valor, 0),
    qtd_vencido: BOLETOS.filter(b => b.status === 'vencido').length,
  }), []);

  return (
    <>
      <Header
        title="Boletos"
        breadcrumb="Financeiro · Boletos emitidos via Inter API"
        right={<>
          <span>14 ativos · sync 09:14</span>
          <Btn variant="outline" onClick={() => setRemessaPanel(true)}>{I.upload}Remessa/Retorno</Btn>
          <Btn variant="primary" onClick={() => setEmitirOpen(true)}>{I.plus}Emitir boleto</Btn>
        </>}
      />

      <div className="px-6 pt-6">
        <div className="bol-funnel">
          <div className="bol-funnel-h">Funil de cobrança · maio 2026</div>
          <div className="bol-funnel-row">
            <div className="bol-funnel-step active">
              <div className="l">Em aberto</div>
              <div className="v">{totais.qtd_aberto}</div>
              <div className="vv">{brl(totais.valor_aberto)}</div>
            </div>
            <div className="bol-funnel-step">
              <div className="l">→ Lembrete</div>
              <div className="v">{Math.round(totais.qtd_aberto * 0.5)}</div>
              <div className="vv">3d antes do vcto</div>
            </div>
            <div className="bol-funnel-step">
              <div className="l">→ Cobrança ativa</div>
              <div className="v">{Math.round(totais.qtd_aberto * 0.2)}</div>
              <div className="vv">1d após vcto</div>
            </div>
            <div className={"bol-funnel-step " + (totais.qtd_vencido > 0 ? "alert" : "")}>
              <div className="l">→ Vencidos +5d</div>
              <div className="v">{totais.qtd_vencido}</div>
              <div className="vv">{brl(totais.valor_vencido)}</div>
            </div>
            <div className="bol-funnel-step">
              <div className="l">→ Protesto</div>
              <div className="v">0</div>
              <div className="vv">30d+</div>
            </div>
          </div>
        </div>
      </div>

      <div className="p-6 grid grid-cols-4 gap-3">
        <KpiCard label="Pago no mês" value={brl(totais.valor_pago_mes)} sub={`${totais.qtd_pago_mes} liquidações · ticket médio ${brl(totais.valor_pago_mes/totais.qtd_pago_mes)}`} icon={I.check} />
        <KpiCard label="Vencido" value={brl(totais.valor_vencido)} sub={`${totais.qtd_vencido} boleto · R-mat. cobrança`} icon={I.alert} />
        <KpiCard label="Próxima janela" value="hoje 18:30" sub="remessa diária · 14 boletos prontos" icon={I.webhook} />
      </div>

      <div className="px-6 pb-3 flex items-center gap-2">
        <div className="inline-flex bg-stone-100/80 rounded-md p-0.5 border border-stone-200">
          {tabs.map(t => (
            <button key={t.id} onClick={() => setTab(t.id)} className={cn(
              "h-7 px-3 rounded text-[12px] flex items-center gap-1.5 tab",
              tab === t.id ? "bg-white shadow-sm font-medium text-stone-900" : "text-stone-600"
            )}>
              <span>{t.label}</span>
              <span className={cn("text-[10px] tabular-nums px-1 rounded", tab === t.id ? "bg-stone-200 text-stone-700" : "text-stone-400")}>{t.count}</span>
            </button>
          ))}
        </div>
        <select value={contaFilter} onChange={(e) => setContaFilter(e.target.value)}
          className="h-7 text-[12px] bg-white border border-stone-300 rounded-md px-2 text-stone-700">
          <option value="all">todas as contas</option>
          {CONTAS.filter(c => c.banco_codigo).map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
        <div className="ml-auto relative">
          <span className="absolute left-2 top-1/2 -translate-y-1/2 text-stone-400">{I.search}</span>
          <input value={busca} onChange={e => setBusca(e.target.value)} placeholder="cliente, título, nosso nº…"
            className="h-7 w-[260px] pl-7 pr-2 bg-white border border-stone-300 rounded-md text-[12px] focus:outline-none focus:border-stone-500" />
        </div>
        <Btn variant="ghost">{I.download}Exportar</Btn>
      </div>

      <div className="px-6 pb-6 flex-1 overflow-auto">
        <div className="bg-white border border-stone-200 rounded-md overflow-hidden">
          <table className="w-full text-[12.5px] tab">
            <thead>
              <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/60">
                <th className="pl-5 pr-2 py-2 text-left font-medium w-[110px]">Vencimento</th>
                <th className="px-2 py-2 text-left font-medium">Cliente / Título</th>
                <th className="px-2 py-2 text-left font-medium w-[160px]">Nosso número</th>
                <th className="px-2 py-2 text-left font-medium w-[88px]">Conta</th>
                <th className="px-2 py-2 text-right font-medium w-[110px]">Valor</th>
                <th className="px-2 py-2 text-left font-medium w-[120px]">Status</th>
                <th className="pl-2 pr-5 py-2 text-right font-medium w-[140px]"></th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((b) => {
                const conta = CONTAS.find(c => c.id === b.conta_id);
                const banco = conta && BANCOS[conta.banco_codigo];
                const today = '2026-05-11';
                const overdue = b.vencimento < today && ['registrado','enviado'].includes(b.status);
                return (
                  <tr key={b.id} className="border-b border-stone-100 hover:bg-stone-50/60 cursor-pointer" onClick={() => setDrawer(b)}>
                    <td className="pl-5 pr-2 py-2.5 text-stone-700">
                      <div className="font-medium">{fmtDate(b.vencimento)}</div>
                      {overdue && <div className="text-[10.5px] text-rose-600">{Math.round((new Date(today) - new Date(b.vencimento))/86400000)}d atraso</div>}
                    </td>
                    <td className="px-2 py-2.5">
                      <div className="font-medium text-stone-900 truncate max-w-[280px]">{b.cliente}</div>
                      <div className="text-[10.5px] text-stone-400 fontmono">{b.titulo_numero} · {b.cliente_doc}</div>
                    </td>
                    <td className="px-2 py-2.5 fontmono text-[11.5px] text-stone-600">{b.nosso_numero}</td>
                    <td className="px-2 py-2.5">
                      <span className="inline-flex items-center gap-1.5">
                        <span className={cn("w-2 h-2 rounded-sm", banco?.cor || 'bg-stone-300')} />
                        <span className="text-[11.5px] text-stone-600">{banco?.short || '—'}</span>
                      </span>
                    </td>
                    <td className="px-2 py-2.5 text-right font-semibold tabular-nums">{brlNoSign(b.valor)}</td>
                    <td className="px-2 py-2.5"><StatusBadge status={b.status} /></td>
                    <td className="pl-2 pr-5 py-2.5 text-right" onClick={(e) => e.stopPropagation()}>
                      <button title="Copiar linha digitável" className="inline-flex items-center justify-center w-6 h-6 rounded hover:bg-stone-200 text-stone-500">{I.copy}</button>
                      <button title="Baixar PDF" className="inline-flex items-center justify-center w-6 h-6 rounded hover:bg-stone-200 text-stone-500 ml-0.5">{I.download}</button>
                      <button title="Mais ações" className="inline-flex items-center justify-center w-6 h-6 rounded hover:bg-stone-200 text-stone-500 ml-0.5">{I.more}</button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {drawer && <DrawerBoleto boleto={drawer} onClose={() => setDrawer(null)} />}
      {emitirOpen && <SheetEmitirBoleto onClose={() => setEmitirOpen(false)} />}
      {remessaPanel && <SheetRemessaRetorno onClose={() => setRemessaPanel(false)} />}
    </>
  );
}

function DrawerBoleto({ boleto, onClose }) {
  const conta = CONTAS.find(c => c.id === boleto.conta_id);
  const banco = conta && BANCOS[conta.banco_codigo];
  const eventos = [
    { ts: boleto.created_at, label: 'Boleto criado no sistema', actor: 'Eliana Souza' },
    { ts: boleto.enviado_em, label: 'Enviado ao Inter API', actor: 'sistema · POST /cobranca/v3/cobrancas', meta: '201 Created' },
    boleto.status === 'pago' ? { ts: boleto.pago_em, label: 'Pagamento confirmado (webhook)', actor: 'inter.webhook.pagamento', meta: 'liquidado em conta · lançamento #4892' } : null,
    boleto.status === 'cancelado' ? { ts: boleto.pago_em || boleto.enviado_em, label: 'Boleto cancelado', actor: 'Eliana Souza', meta: 'cliente pagou via PIX' } : null,
  ].filter(Boolean);
  return (
    <div className="fixed inset-0 z-30 flex justify-end" onClick={onClose}>
      <div className="absolute inset-0 bg-stone-900/20" />
      <div className="relative w-[520px] bg-white h-full shadow-xl border-l border-stone-200 flex flex-col" onClick={e => e.stopPropagation()}>
        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-2">
          <div className="min-w-0 flex-1">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Boleto {boleto.titulo_numero}</div>
            <div className="text-[15px] font-semibold mt-0.5">{boleto.cliente}</div>
          </div>
          <StatusBadge status={boleto.status} />
          <button onClick={onClose} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500">{I.x}</button>
        </div>
        <div className="flex-1 overflow-auto">
          <div className="px-5 py-4 grid grid-cols-2 gap-x-5 gap-y-2.5 text-[12.5px] tab">
            <div><div className="text-[10px] uppercase tracking-widest text-stone-500">Vencimento</div><div className="font-medium mt-0.5">{fmtDate(boleto.vencimento)}</div></div>
            <div><div className="text-[10px] uppercase tracking-widest text-stone-500">Valor</div><div className="font-semibold mt-0.5">{brl(boleto.valor)}</div></div>
            <div><div className="text-[10px] uppercase tracking-widest text-stone-500">Conta emissora</div><div className="font-medium mt-0.5 inline-flex items-center gap-1.5"><span className={cn("w-2 h-2 rounded-sm", banco?.cor)} />{conta?.name}</div></div>
            <div><div className="text-[10px] uppercase tracking-widest text-stone-500">Estratégia</div><div className="font-medium mt-0.5 fontmono text-[11.5px]">{boleto.strategy}</div></div>
            <div className="col-span-2"><div className="text-[10px] uppercase tracking-widest text-stone-500">Nosso número</div><div className="fontmono mt-0.5">{boleto.nosso_numero}</div></div>
            <div className="col-span-2">
              <div className="text-[10px] uppercase tracking-widest text-stone-500 mb-1">Linha digitável</div>
              <div className="flex items-center gap-2 bg-stone-50 border border-stone-200 rounded px-2.5 py-2">
                <div className="fontmono text-[11.5px] flex-1 break-all">{boleto.linha_digitavel}</div>
                <Btn variant="outline" size="xs">{I.copy}</Btn>
              </div>
            </div>
            <div className="col-span-2"><div className="text-[10px] uppercase tracking-widest text-stone-500">Código de barras</div><div className="fontmono text-[10.5px] text-stone-500 mt-0.5 break-all">{boleto.codigo_barras}</div></div>
          </div>

          <div className="px-5 py-3 border-t border-stone-200">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 mb-2 font-medium">Linha do tempo</div>
            <div className="space-y-2.5">
              {eventos.map((e, i) => (
                <div key={i} className="flex gap-3 text-[12px]">
                  <div className="w-[78px] text-stone-500 tab whitespace-nowrap pt-0.5">{fmtDate(e.ts?.slice(0,10))} {e.ts?.slice(11,16)}</div>
                  <div className="w-2 mt-1.5 relative">
                    <div className="absolute inset-0 w-1.5 h-1.5 rounded-full bg-stone-900 top-0" />
                    {i < eventos.length - 1 && <div className="absolute left-[3px] top-2 w-px h-7 bg-stone-200" />}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="text-stone-900 font-medium">{e.label}</div>
                    <div className="text-stone-500 text-[11.5px] mt-0.5">{e.actor}{e.meta && <span className="ml-1 text-stone-400">· {e.meta}</span>}</div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
        <div className="border-t border-stone-200 p-3 flex items-center gap-2">
          <Btn variant="outline">{I.download}Baixar PDF</Btn>
          <Btn variant="outline">{I.link}Link 2ª via</Btn>
          <div className="flex-1" />
          {!['pago','cancelado'].includes(boleto.status) && <Btn variant="danger">{I.x}Cancelar boleto</Btn>}
        </div>
      </div>
    </div>
  );
}

function SheetEmitirBoleto({ onClose }) {
  const [step, setStep] = useState('select');
  const [picked, setPicked] = useState([]);
  const togglePick = (id) => setPicked(p => p.includes(id) ? p.filter(x => x !== id) : [...p, id]);
  const total = TITULOS_DISPONIVEIS.filter(t => picked.includes(t.id)).reduce((s, t) => s + t.valor, 0);
  return (
    <div className="fixed inset-0 z-30 flex justify-end" onClick={onClose}>
      <div className="absolute inset-0 bg-stone-900/30" />
      <div className="relative w-[600px] h-full bg-white shadow-xl border-l border-stone-200 flex flex-col" onClick={e => e.stopPropagation()}>
        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
          <div className="flex-1">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Emitir boleto</div>
            <div className="text-[15px] font-semibold mt-0.5">a partir de títulos a receber</div>
          </div>
          <button onClick={onClose} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500">{I.x}</button>
        </div>
        <div className="px-5 py-3 border-b border-stone-200 bg-stone-50/60 grid grid-cols-2 gap-3 text-[12px]">
          <label>
            <div className="text-[10px] uppercase tracking-widest text-stone-500 mb-1 font-medium">Conta emissora</div>
            <select className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px]">
              <option>Inter PJ · Operacional · Cart. 112</option>
              <option>Itaú PJ · Reserva · Cart. 109</option>
            </select>
          </label>
          <label>
            <div className="text-[10px] uppercase tracking-widest text-stone-500 mb-1 font-medium">Vencimento padrão</div>
            <input type="date" defaultValue="2026-05-18" className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px]" />
          </label>
        </div>
        <div className="flex-1 overflow-auto">
          <div className="px-5 py-2 text-[10px] uppercase tracking-widest text-stone-500 font-medium border-b border-stone-200">
            Títulos sem boleto emitido · {TITULOS_DISPONIVEIS.length}
          </div>
          {TITULOS_DISPONIVEIS.map(t => (
            <label key={t.id} className="px-5 py-2.5 flex items-center gap-3 border-b border-stone-100 hover:bg-stone-50/60 cursor-pointer">
              <input type="checkbox" checked={picked.includes(t.id)} onChange={() => togglePick(t.id)} className="accent-stone-900" />
              <div className="flex-1 min-w-0">
                <div className="text-[12.5px] font-medium text-stone-900 truncate">{t.cliente}</div>
                <div className="text-[10.5px] text-stone-500 fontmono">{t.numero} · venc. {fmtDate(t.vencimento)}</div>
              </div>
              <div className="text-[12.5px] font-semibold tab">{brl(t.valor)}</div>
            </label>
          ))}
        </div>
        <div className="border-t border-stone-200 p-3 flex items-center gap-2 bg-stone-50/60">
          <div className="text-[11px] text-stone-500">{picked.length} selecionados</div>
          <div className="text-[14px] font-semibold tab ml-2">{brl(total)}</div>
          <div className="flex-1" />
          <Btn variant="outline" onClick={onClose}>Cancelar</Btn>
          <Btn variant="primary" disabled={picked.length === 0}>{I.plus}Emitir {picked.length || ''} {picked.length === 1 ? 'boleto' : picked.length > 1 ? 'boletos' : 'boletos'}</Btn>
        </div>
      </div>
    </div>
  );
}

function SheetRemessaRetorno({ onClose }) {
  return (
    <div className="fixed inset-0 z-30 flex justify-end" onClick={onClose}>
      <div className="absolute inset-0 bg-stone-900/30" />
      <div className="relative w-[560px] h-full bg-white shadow-xl border-l border-stone-200 flex flex-col" onClick={e => e.stopPropagation()}>
        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-2">
          <div className="flex-1">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Remessa & Retorno · CNAB 240</div>
            <div className="text-[15px] font-semibold mt-0.5">Inter PJ · Operacional</div>
          </div>
          <button onClick={onClose} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500">{I.x}</button>
        </div>
        <div className="px-5 py-3 border-b border-stone-200 grid grid-cols-2 gap-2">
          <Btn variant="primary">{I.download}Gerar remessa do dia</Btn>
          <Btn variant="outline">{I.upload}Importar arquivo retorno</Btn>
        </div>
        <div className="flex-1 overflow-auto">
          {REMESSAS_CNAB.map(r => (
            <div key={r.id} className="px-5 py-3 border-b border-stone-100 hover:bg-stone-50/60">
              <div className="flex items-center gap-3">
                <span className={cn("w-7 h-7 rounded inline-grid place-items-center",
                  r.tipo === 'remessa' ? 'bg-blue-50 text-blue-700' : 'bg-emerald-50 text-emerald-700')}>
                  {r.tipo === 'remessa' ? I.upload : I.download}
                </span>
                <div className="flex-1 min-w-0">
                  <div className="text-[12.5px] font-medium fontmono truncate">{r.filename}</div>
                  <div className="text-[10.5px] text-stone-500 tab mt-0.5">
                    {fmtDate(r.criado_em.slice(0,10))} {r.criado_em.slice(11,16)} · {r.qtd_titulos} títulos · {brl(r.valor_total)}
                  </div>
                </div>
                <Btn variant="ghost" size="xs">{I.download}</Btn>
              </div>
            </div>
          ))}
        </div>
        <div className="border-t border-stone-200 px-5 py-3 bg-amber-50/60 text-[11.5px] text-amber-900">
          <div className="flex gap-2"><span>{I.alert}</span><div><strong>Inter API:</strong> registro online é instantâneo (webhook). Arquivo CNAB fica disponível para reconciliação manual e backup.</div></div>
        </div>
      </div>
    </div>
  );
}

// ───────────────────── CONTAS / CAIXA ─────────────────────
function TelaContas() {
  const [config, setConfig] = useState(null);
  const [novaConta, setNovaConta] = useState(false);

  const ativas = CONTAS.filter(c => !c.is_caixa);
  const caixa = CONTAS.filter(c => c.is_caixa);
  const saldoTotal = CONTAS.reduce((s, c) => s + c.saldo, 0);
  const totalAtivas = CONTAS.filter(c => c.ativo_para_boleto).length;

  return (
    <>
      <Header
        title="Contas & Caixa"
        breadcrumb="Financeiro · contas bancárias + caixa interno + gateways de cobrança"
        right={<>
          <span>{totalAtivas} ativas · sync 09:14</span>
          <Btn variant="outline">{I.refresh}Sincronizar saldos</Btn>
          <Btn variant="primary" onClick={() => setNovaConta(true)}>{I.plus}Nova conta</Btn>
        </>}
      />

      <div className="p-6 grid grid-cols-4 gap-3">
        <KpiCard tone="dark" label="Saldo consolidado" value={brl(saldoTotal)} sub={`${CONTAS.length} contas · ${totalAtivas} ativas p/ boleto`} icon={I.wallet} />
        <KpiCard label="A receber via boleto" value={brl(CONTAS.reduce((s,c)=>s+(c.boletos_aberto_valor||0),0))} sub={`${CONTAS.reduce((s,c)=>s+(c.boletos_aberto||0),0)} boletos registrados`} icon={I.receipt} />
        <KpiCard label="Conexão Inter" value="online" sub="OAuth ok · webhook 200ms · último sync 09:14" icon={I.webhook} />
        <KpiCard label="Caixa físico" value={brl(caixa.reduce((s,c)=>s+c.saldo,0))} sub={caixa.length + ' caixa interno · contagem manual'} icon={I.bank} />
      </div>

      <div className="px-6 pb-6 space-y-3">
        <div>
          <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-2">Contas bancárias · {ativas.length}</div>
          <div className="grid grid-cols-2 gap-3">
            {ativas.map(c => <CardConta key={c.id} conta={c} onConfig={() => setConfig(c)} />)}
          </div>
        </div>

        {caixa.length > 0 && (
          <div className="pt-2">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-2">Caixa interno · {caixa.length}</div>
            <div className="grid grid-cols-2 gap-3">
              {caixa.map(c => <CardConta key={c.id} conta={c} onConfig={() => setConfig(c)} caixa />)}
            </div>
          </div>
        )}
      </div>

      {config && <SheetConfigInter conta={config} onClose={() => setConfig(null)} />}
      {novaConta && <SheetNovaConta onClose={() => setNovaConta(false)} />}
    </>
  );
}

function CardConta({ conta, onConfig, caixa }) {
  const banco = conta.banco_codigo && BANCOS[conta.banco_codigo];
  const delta = conta.saldo - conta.saldo_d1;
  const deltaPct = conta.saldo_d_7 ? ((conta.saldo - conta.saldo_d_7) / conta.saldo_d_7) * 100 : 0;
  const inativa = !conta.ativo_para_boleto && !caixa;
  return (
    <div className={cn("bg-white border rounded-md p-4 hover:shadow-sm transition", inativa ? "border-stone-200 opacity-70" : "border-stone-200")}>
      <div className="flex items-start gap-3">
        <div className={cn("w-9 h-9 rounded-md grid place-items-center text-white text-[11px] font-semibold shrink-0", banco?.cor || (caixa ? 'bg-stone-700' : 'bg-stone-400'))}>
          {banco?.short.slice(0,2).toUpperCase() || (caixa ? 'CX' : '—')}
        </div>
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2">
            <div className="font-semibold text-[13px] text-stone-900 truncate">{conta.name}</div>
            {conta.gateway_banco === 'inter' && (
              <span className="inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded border bg-emerald-50 text-emerald-700 border-emerald-200">
                {I.webhook} Inter API · {conta.gateway_ambiente === 'sandbox' ? 'sandbox' : 'produção'}
              </span>
            )}
            {inativa && (
              <span className="inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded border bg-stone-100 text-stone-500 border-stone-200">inativa</span>
            )}
          </div>
          <div className="text-[11px] text-stone-500 mt-0.5 fontmono">
            {caixa ? `${conta.account_number}` : `${banco?.nome} · Ag ${conta.agencia} · Cc ${conta.account_number}`}
          </div>
        </div>
        <button onClick={onConfig} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500" title="Configurar">{I.settings}</button>
      </div>

      <div className="mt-4 flex items-baseline gap-3">
        <div className="text-[24px] font-semibold tab tracking-tight">{brlNoSign(conta.saldo)}</div>
        {!caixa && (
          <div className={cn("text-[11.5px] tab", delta >= 0 ? "text-emerald-700" : "text-rose-700")}>
            {delta >= 0 ? '+' : '−'} {brlNoSign(Math.abs(delta))} hoje
          </div>
        )}
      </div>
      {!caixa && (
        <div className="mt-1 text-[10.5px] text-stone-500 tab">
          7d: {deltaPct >= 0 ? '+' : ''}{deltaPct.toFixed(1)}% · última mov. {fmtDate(conta.ultima_movimentacao?.slice(0,10))}
        </div>
      )}

      {!caixa && conta.boletos_aberto > 0 && (
        <div className="mt-3 pt-3 border-t border-stone-100 flex items-center gap-2 text-[11.5px]">
          <span className="text-stone-500">{conta.boletos_aberto} boletos abertos</span>
          <span className="text-stone-300">·</span>
          <span className="text-stone-700 font-medium tab">{brl(conta.boletos_aberto_valor)}</span>
          <div className="flex-1" />
          <button className="text-stone-500 hover:text-stone-900 underline-offset-2 hover:underline inline-flex items-center gap-0.5">ver extrato {I.external}</button>
        </div>
      )}
    </div>
  );
}

function SheetConfigInter({ conta, onClose }) {
  const [tab, setTab] = useState('identificacao');
  const isInter = conta.banco_codigo === '077';
  const [ambiente, setAmbiente] = useState(conta.gateway_ambiente || 'production');
  const [testStatus, setTestStatus] = useState(null);
  const testar = () => {
    setTestStatus('testando');
    setTimeout(() => setTestStatus('ok'), 1200);
  };
  return (
    <div className="fixed inset-0 z-30 flex justify-end" onClick={onClose}>
      <div className="absolute inset-0 bg-stone-900/30" />
      <div className="relative w-[640px] h-full bg-white shadow-xl border-l border-stone-200 flex flex-col" onClick={e => e.stopPropagation()}>
        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
          <div className="flex-1 min-w-0">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Configurar conta</div>
            <div className="text-[15px] font-semibold mt-0.5 truncate">{conta.name}</div>
          </div>
          {isInter && (
            <span className="inline-flex items-center gap-1 text-[10.5px] font-medium px-1.5 py-0.5 rounded border bg-orange-50 text-orange-700 border-orange-200">
              {I.webhook} Inter API
            </span>
          )}
          <button onClick={onClose} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500">{I.x}</button>
        </div>

        <div className="border-b border-stone-200 bg-stone-50/40 px-5">
          <div className="flex gap-1">
            {[
              { id: 'identificacao', label: 'Identificação' },
              { id: 'boleto', label: 'Boleto / Carteira' },
              isInter && { id: 'inter', label: 'Inter API' },
              { id: 'beneficiario', label: 'Beneficiário' },
            ].filter(Boolean).map(t => (
              <button key={t.id} onClick={() => setTab(t.id)} className={cn(
                "h-9 px-3 text-[12px] border-b-2 -mb-px",
                tab === t.id ? "border-stone-900 text-stone-900 font-medium" : "border-transparent text-stone-500 hover:text-stone-800"
              )}>{t.label}</button>
            ))}
          </div>
        </div>

        <div className="flex-1 overflow-auto px-5 py-4 space-y-4">
          {tab === 'identificacao' && (
            <div className="space-y-3">
              <Field label="Apelido da conta" defaultValue={conta.name} />
              <div className="grid grid-cols-2 gap-3">
                <Field label="Banco">
                  <div className="h-8 bg-stone-50 border border-stone-300 rounded px-2 flex items-center gap-2 text-[12.5px]">
                    <span className={cn("w-2 h-2 rounded-sm", BANCOS[conta.banco_codigo]?.cor)} />
                    <span className="fontmono text-stone-500">{conta.banco_codigo}</span>
                    <span>{BANCOS[conta.banco_codigo]?.nome}</span>
                  </div>
                </Field>
                <Field label="Tipo" defaultValue="Corrente PJ" />
                <Field label="Agência" defaultValue={conta.agencia} />
                <Field label="Conta corrente" defaultValue={`${conta.account_number}${conta.conta_dv ? '-' + conta.conta_dv : ''}`} />
              </div>
              <Field label="Ativa para emissão de boleto" toggle defaultChecked={conta.ativo_para_boleto} />
            </div>
          )}
          {tab === 'boleto' && (
            <div className="space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <Field label="Carteira" defaultValue={conta.carteira} hint="Inter PJ usa 112" />
                <Field label="Código cedente" defaultValue={conta.codigo_cedente || ''} hint="código que o banco devolve no contrato" />
                <Field label="Convênio" defaultValue="" hint="opcional · BB/Sicoob/Caixa" />
                <Field label="Variação carteira" defaultValue="" hint="opcional" />
              </div>
              <div className="mt-2 px-3 py-2.5 bg-amber-50 border border-amber-200 rounded text-[11.5px] text-amber-900">
                <strong>R-FIN-011:</strong> uma vez que o código de carteira tiver boletos emitidos, ele fica imutável (auditoria CNAB).
              </div>
            </div>
          )}
          {tab === 'inter' && isInter && (
            <div className="space-y-4">
              <div className="flex items-center gap-2">
                <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Ambiente</div>
                <div className="inline-flex bg-stone-100 rounded p-0.5 border border-stone-200 ml-auto">
                  {['production', 'sandbox'].map(a => (
                    <button key={a} onClick={() => setAmbiente(a)} className={cn(
                      "h-6 px-3 rounded text-[11.5px]",
                      ambiente === a ? "bg-white shadow-sm font-medium" : "text-stone-600"
                    )}>{a === 'production' ? 'produção' : 'sandbox'}</button>
                  ))}
                </div>
              </div>

              <Field label="Client ID" defaultValue={conta.gateway_client_id} mono />
              <Field label="Client Secret" type="password" placeholder="••••••••••••••••" mono hint="armazenado criptografado · re-envio sobrescreve" />

              <div className="grid grid-cols-2 gap-3">
                <Field label="Certificado .crt" file accept=".crt,.pem" hint="público · base64 no config_json" />
                <Field label="Chave privada .key" file accept=".key,.pem" hint="criptografada · nunca exibida" />
              </div>

              <div>
                <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-1.5">Scopes habilitados</div>
                <div className="flex flex-wrap gap-1.5">
                  {['boleto-cobranca.read','boleto-cobranca.write','extrato.read','pix.read','pix.write'].map(s => (
                    <span key={s} className="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-1 rounded border bg-emerald-50 text-emerald-700 border-emerald-200">
                      {I.check} {s}
                    </span>
                  ))}
                </div>
              </div>

              <div>
                <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-1.5">Webhook · gerado automaticamente</div>
                <div className="flex items-center gap-2 bg-stone-50 border border-stone-200 rounded px-2.5 py-2">
                  <div className="fontmono text-[11.5px] flex-1 break-all text-stone-700">
                    https://app.oimpresso.com/api/webhooks/inter/{conta.rb_gateway_credential_id}/cobranca
                  </div>
                  <Btn variant="outline" size="xs">{I.copy}Copiar</Btn>
                </div>
                <div className="text-[10.5px] text-stone-500 mt-1.5">Cole no portal Inter PJ → Integrações → Webhooks → Cobrança</div>
              </div>

              <div className="border-t border-stone-200 pt-3 flex items-center gap-2">
                <Btn variant="outline" onClick={testar}>
                  {testStatus === 'testando' ? I.refresh : I.shield} 
                  {testStatus === 'testando' ? 'testando…' : 'Testar conexão'}
                </Btn>
                {testStatus === 'ok' && (
                  <span className="inline-flex items-center gap-1 text-[11.5px] text-emerald-700 font-medium">
                    {I.check} OAuth ok · 1 boleto listado · 240ms
                  </span>
                )}
              </div>
            </div>
          )}
          {tab === 'beneficiario' && (
            <div className="space-y-3">
              <div className="grid grid-cols-3 gap-3">
                <div className="col-span-2"><Field label="Razão social" defaultValue={conta.beneficiario_razao_social} /></div>
                <Field label="CNPJ" defaultValue={conta.beneficiario_documento} mono />
              </div>
              <Field label="Logradouro" defaultValue="" />
              <div className="grid grid-cols-3 gap-3">
                <Field label="Bairro" defaultValue="" />
                <Field label="Cidade" defaultValue="" />
                <Field label="UF" defaultValue="" />
              </div>
              <Field label="CEP" defaultValue="" mono />
            </div>
          )}
        </div>

        <div className="border-t border-stone-200 p-3 flex items-center gap-2 bg-stone-50/60">
          <div className="text-[11px] text-stone-500">Alterações em campos com lançamentos exigem confirmação extra.</div>
          <div className="flex-1" />
          <Btn variant="outline" onClick={onClose}>Cancelar</Btn>
          <Btn>Salvar configuração</Btn>
        </div>
      </div>
    </div>
  );
}

function SheetNovaConta({ onClose }) {
  return (
    <div className="fixed inset-0 z-30 flex justify-end" onClick={onClose}>
      <div className="absolute inset-0 bg-stone-900/30" />
      <div className="relative w-[560px] h-full bg-white shadow-xl border-l border-stone-200 flex flex-col" onClick={e => e.stopPropagation()}>
        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-2">
          <div className="flex-1">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Nova conta</div>
            <div className="text-[15px] font-semibold mt-0.5">conta bancária ou caixa interno</div>
          </div>
          <button onClick={onClose} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500">{I.x}</button>
        </div>
        <div className="flex-1 overflow-auto p-5 space-y-3">
          <div className="grid grid-cols-2 gap-2">
            {[
              { id: 'bank', label: 'Conta bancária', sub: 'agência + conta corrente · pode emitir boleto', icon: I.bank },
              { id: 'cash', label: 'Caixa interno', sub: 'dinheiro físico · contagem manual', icon: I.wallet },
            ].map(t => (
              <button key={t.id} className="text-left rounded-md border border-stone-200 p-3 hover:border-stone-400 hover:bg-stone-50">
                <div className="text-stone-700">{t.icon}</div>
                <div className="text-[13px] font-medium mt-1.5">{t.label}</div>
                <div className="text-[11px] text-stone-500 mt-0.5">{t.sub}</div>
              </button>
            ))}
          </div>
          <Field label="Apelido" placeholder="ex: Inter PJ · Operacional" />
          <div className="grid grid-cols-2 gap-3">
            <Field label="Banco">
              <select className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px]">
                {Object.entries(BANCOS).map(([code, b]) => <option key={code}>{code} · {b.nome}</option>)}
              </select>
            </Field>
            <Field label="Saldo inicial" placeholder="R$ [redacted Tier 0]" mono />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Agência" placeholder="0001" mono />
            <Field label="Conta corrente" placeholder="12345-6" mono />
          </div>
          <div className="text-[11px] text-stone-500 px-3 py-2 bg-stone-50 border border-stone-200 rounded">
            Dados de carteira/cedente e credenciais Inter são preenchidos depois da criação, na aba "Configurar".
          </div>
        </div>
        <div className="border-t border-stone-200 p-3 flex items-center gap-2 bg-stone-50/60">
          <div className="flex-1" />
          <Btn variant="outline" onClick={onClose}>Cancelar</Btn>
          <Btn variant="primary">Criar conta</Btn>
        </div>
      </div>
    </div>
  );
}

function Field({ label, defaultValue, placeholder, type = 'text', children, hint, mono, file, accept, toggle, defaultChecked }) {
  if (toggle) {
    return (
      <label className="flex items-center gap-3 py-1.5">
        <input type="checkbox" defaultChecked={defaultChecked} className="accent-stone-900 w-4 h-4" />
        <div className="text-[12.5px] text-stone-700">{label}</div>
      </label>
    );
  }
  return (
    <label className="block">
      <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-1">{label}</div>
      {children || (file ? (
        <div className="h-8 bg-white border border-stone-300 border-dashed rounded flex items-center gap-2 px-2 text-[12px] text-stone-500">
          <span>{I.upload}</span>
          <span>arraste arquivo {accept || ''} ou clique para selecionar</span>
        </div>
      ) : (
        <input
          type={type}
          defaultValue={defaultValue}
          placeholder={placeholder}
          className={cn("w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] focus:outline-none focus:border-stone-500", mono && "fontmono")}
        />
      ))}
      {hint && <div className="text-[10.5px] text-stone-500 mt-1">{hint}</div>}
    </label>
  );
}

// ───────────────────── App ─────────────────────
function BoletosPage() {
  const [tela, setTela] = useState('boletos');
  const sub = [
    { id: 'boletos', label: 'Boletos' },
    { id: 'contas',  label: 'Contas & Caixa' },
  ];
  const labelAtiva = sub.find(s => s.id === tela)?.label;
  return (
    <div className="os-page bol-root" data-screen-label="01 Boletos">
      <div className="os-page-h">
        <div className="os-page-h-l">
          <h1>Financeiro · Boletos</h1>
          <p>Maio 2026 · ROTA LIVRE <span style={{margin:'0 8px', color:'var(--text-mute)'}}>·</span> {labelAtiva} · via Inter API</p>
        </div>
        <div className="os-page-h-r">
          <button className="os-btn ghost">{I.refresh}Sincronizar</button>
          <button className="os-btn primary">{I.plus}Emitir boleto</button>
        </div>
      </div>
      <nav className="fin-subnav">
        {sub.map(s => (
          <button key={s.id} onClick={() => setTela(s.id)}
            className={"fin-subnav-tab" + (tela === s.id ? " active" : "")}>
            {s.label}
          </button>
        ))}
      </nav>
      <div className="fin-body">
        {tela === 'boletos' && <TelaBoletos />}
        {tela === 'contas' && <TelaContas />}
      </div>
    </div>
  );
}

window.BoletosPage = BoletosPage;
})();
