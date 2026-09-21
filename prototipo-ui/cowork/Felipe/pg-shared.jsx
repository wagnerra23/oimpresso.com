/* @ts-nocheck */
/* eslint-disable */
// shared.jsx — Tokens canon + icons + helpers + mock data + atoms
// Compartilhado pelos 3 protótipos F1 PaymentGateway UI.
// Persona Eliana[E] (Cobrança), Wagner (Settings), Larissa (Sells modal).
// KB-9.75 alinhamento: tabular-nums, text-[12.5px], stone neutros, warm semantic.
(() => {

// ───────────────────── helpers ─────────────────────
const brl = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);
const brlNoSign = (v) => brl(v).replace('R$', '').trim();
const brlK = (v) => v >= 1000 ? 'R$ ' + (v/1000).toFixed(1).replace('.', ',') + 'k' : brl(v);
const fmtDate = (iso) => {
  if (!iso) return '—';
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y}`;
};
const fmtDateRel = (iso, today = '2026-05-19') => {
  if (!iso) return '—';
  const [yt, mt, dt] = today.split('-').map(Number);
  const [y, m, d] = iso.split('-').map(Number);
  const a = new Date(yt, mt-1, dt);
  const b = new Date(y, m-1, d);
  const days = Math.round((b - a) / 86400000);
  if (days === 0) return 'hoje';
  if (days === 1) return 'amanhã';
  if (days === -1) return 'ontem';
  if (days > 0 && days <= 30) return `em ${days}d`;
  if (days < 0 && days >= -30) return `há ${-days}d`;
  return fmtDate(iso);
};
const piiMask = (cnpj) => {
  if (!cnpj) return '—';
  // CNPJ 12.345.678/0001-90 → 12.***.***/0001-90; CPF 123.456.789-01 → ***.***.***-**
  if (cnpj.includes('/')) return cnpj.slice(0,2) + '.***.***' + cnpj.slice(-9);
  return '***.***.***-' + cnpj.slice(-2);
};
const cn = (...xs) => xs.filter(Boolean).join(' ');

// ───────────────────── icons (lucide-style inline SVG, sem emoji) ─────────────────────
const Icon = ({ d, size = 14, className = '', strokeWidth = 1.75 }) => (
  <svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor"
       strokeWidth={strokeWidth} strokeLinecap="round" strokeLinejoin="round" className={className}>{d}</svg>
);
const I = {
  search:    <Icon d={<><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></>} />,
  plus:      <Icon d={<><path d="M12 5v14M5 12h14"/></>} />,
  download:  <Icon d={<><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></>} />,
  upload:    <Icon d={<><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></>} />,
  copy:      <Icon d={<><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></>} />,
  check:     <Icon d={<path d="M20 6 9 17l-5-5"/>} />,
  x:         <Icon d={<><path d="M18 6 6 18M6 6l12 12"/></>} />,
  more:      <Icon d={<><circle cx="12" cy="6" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="18" r="1"/></>} />,
  link:      <Icon d={<><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></>} />,
  webhook:   <Icon d={<><circle cx="18" cy="18" r="3"/><circle cx="6" cy="6" r="3"/><circle cx="18" cy="6" r="3"/><path d="M9 6h6M18 9v6M15 18H9"/></>} />,
  shield:    <Icon d={<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>} />,
  refresh:   <Icon d={<><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/></>} />,
  bank:      <Icon d={<><path d="M3 21h18M3 10h18M5 6l7-3 7 3M5 10v11M19 10v11M9 14v4M15 14v4"/></>} />,
  wallet:    <Icon d={<><path d="M20 12V8a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4z"/><path d="M22 13h-4a2 2 0 0 1 0-4h4"/></>} />,
  settings:  <Icon d={<><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.36.16.69.4.96.71"/></>} />,
  external:  <Icon size={12} d={<><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6M10 14 21 3"/></>} />,
  receipt:   <Icon d={<><path d="M4 2v20l3-2 3 2 3-2 3 2 3-2 3 2V2H4z"/><path d="M8 7h8M8 11h8M8 15h5"/></>} />,
  eye:       <Icon d={<><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></>} />,
  alert:     <Icon d={<><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></>} />,
  zap:       <Icon d={<path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/>} />,
  creditcard:<Icon d={<><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></>} />,
  qrcode:    <Icon d={<><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM18 18h3v3h-3z"/></>} />,
  arrowR:    <Icon d={<><path d="M5 12h14M13 5l7 7-7 7"/></>} />,
  arrowL:    <Icon d={<path d="M19 12H5M12 19l-7-7 7-7"/>} />,
  chevR:     <Icon d={<path d="m9 18 6-6-6-6"/>} size={12} />,
  chevD:     <Icon d={<path d="m6 9 6 6 6-6"/>} size={12} />,
  building:  <Icon d={<><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22V12h6v10M9 6h.01M15 6h.01M9 10h.01M15 10h.01"/></>} />,
  rotate:    <Icon d={<><path d="M21 12a9 9 0 1 1-9-9c2.5 0 4.8 1 6.5 2.7l2.5-2.5"/><path d="M21 3v6h-6"/></>} />,
  pause:     <Icon d={<><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></>} />,
  play:      <Icon d={<path d="M5 3v18l16-9z"/>} />,
  ban:       <Icon d={<><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></>} />,
  xCircle:   <Icon d={<><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></>} />,
  clock:     <Icon d={<><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></>} />,
};

// ───────────────────── tokens — drivers ─────────────────────
// Cor + sigla + tipos suportados + ambiente disponível
const DRIVERS = {
  inter:   { key:'inter',   nome:'Inter PJ',     sigla:'IN', dot:'bg-orange-500',  bg:'bg-orange-50',  fg:'text-orange-700',  border:'border-orange-200',
             tipos:['boleto','pix_cob','pix_cobv'], ambientes:['sandbox','production'], cred:'mTLS · client_id+secret · cert.crt + cert.key' },
  c6:      { key:'c6',      nome:'C6 Bank',      sigla:'C6', dot:'bg-stone-800',   bg:'bg-stone-100',  fg:'text-stone-800',   border:'border-stone-300',
             tipos:['boleto'],                  ambientes:['production'],            cred:'agência + conta + código_cliente · CNAB 240' },
  asaas:   { key:'asaas',   nome:'Asaas',        sigla:'AS', dot:'bg-blue-600',    bg:'bg-blue-50',    fg:'text-blue-700',    border:'border-blue-200',
             tipos:['boleto','pix_cob','card'], ambientes:['sandbox','production'], cred:'api_key + webhook_secret' },
  bcb_pix: { key:'bcb_pix', nome:'BCB · PIX Automático', sigla:'BC', dot:'bg-emerald-600', bg:'bg-emerald-50', fg:'text-emerald-700', border:'border-emerald-200',
             tipos:['pix_recv'], ambientes:['sandbox','production'], cred:'mTLS BCB + CNPJ recebedor homologado · Resolução BCB 380/2024' },
};

const TIPOS = {
  boleto:   { label:'Boleto',          bg:'bg-stone-100',   fg:'text-stone-700',   short:'boleto' },
  pix_cob:  { label:'PIX cob',         bg:'bg-emerald-50',  fg:'text-emerald-700', short:'pix' },
  pix_cobv: { label:'PIX cobv',        bg:'bg-emerald-50',  fg:'text-emerald-700', short:'pix v' },
  pix_recv: { label:'PIX Automático',  bg:'bg-violet-50',   fg:'text-violet-700',  short:'pix aut.' },
  card:     { label:'Cartão',          bg:'bg-sky-50',      fg:'text-sky-700',     short:'cartão' },
};

const STATUS = {
  emitida:   { label:'Emitida',   bg:'bg-blue-50',     fg:'text-blue-700',     dot:'bg-blue-500' },
  paga:      { label:'Paga',      bg:'bg-emerald-50',  fg:'text-emerald-700',  dot:'bg-emerald-500' },
  vencida:   { label:'Vencida',   bg:'bg-rose-50',     fg:'text-rose-700',     dot:'bg-rose-500' },
  cancelada: { label:'Cancelada', bg:'bg-stone-100',   fg:'text-stone-500',    dot:'bg-stone-400' },
  erro:      { label:'Erro',      bg:'bg-rose-100',    fg:'text-rose-800',     dot:'bg-rose-600' },
  pending:   { label:'Pendente',  bg:'bg-stone-100',   fg:'text-stone-600',    dot:'bg-stone-400' },
};

const ORIGENS = {
  sale:                 { label:'Venda',             bg:'bg-amber-50',    fg:'text-amber-700' },
  invoice:              { label:'Recorrente',        bg:'bg-violet-50',   fg:'text-violet-700' },
  subscription_license: { label:'SaaS Oimpresso',    bg:'bg-fuchsia-50',  fg:'text-fuchsia-700' },
};

// ───────────────────── mock data ─────────────────────
const ACCOUNTS = [
  { id: 12, name: 'Inter PJ · Operacional',    agencia:'0001', conta:'4521-7', banco:'Inter',  driver:'inter' },
  { id: 8,  name: 'Itaú PJ · Reserva',         agencia:'0438', conta:'8842-1', banco:'Itaú',   driver:null },
  { id: 14, name: 'Asaas · Operacional',       agencia:null,   conta:'cliente_482931', banco:'Asaas', driver:'asaas' },
  { id: 22, name: 'BCB · Recebedor PIX Aut.',  agencia:null,   conta:'CNPJ 12345678000190', banco:'BCB', driver:'bcb_pix' },
];

const GATEWAYS = [
  { id:18, nome:'Inter PJ · Operacional',  driver:'inter',   ambiente:'production', ativo:true,  account_id:12, last_check:'2026-05-19T09:14:00', health:'ok',       latencia:240, created_at:'2026-04-12' },
  { id:19, nome:'Inter PJ · Sandbox dev',  driver:'inter',   ambiente:'sandbox',    ativo:false, account_id:12, last_check:'2026-05-18T22:00:00', health:'ok',       latencia:312, created_at:'2026-04-12' },
  { id:21, nome:'Asaas · Operacional',     driver:'asaas',   ambiente:'production', ativo:true,  account_id:14, last_check:'2026-05-19T09:14:00', health:'ok',       latencia:182, created_at:'2026-04-25' },
  { id:23, nome:'BCB · Recebedor Pix Aut.',driver:'bcb_pix', ambiente:'production', ativo:true,  account_id:22, last_check:'2026-05-19T09:13:00', health:'degraded', latencia:1140, created_at:'2026-05-08', warn:'mTLS expira em 47d' },
];

// 14 cobranças variadas — boleto + pix + pix_recv + card · 5 status · 3 origens · datas em torno de 2026-05-19
const COBRANCAS = [
  // emitidas / em aberto
  { id:1841, tipo:'boleto',   gateway:'inter',   account_id:12, contato:'Padaria Pão Quente LTDA',  contato_doc:'12.882.441/0001-08', valor:480.00,   vencimento:'2026-05-22', status:'emitida', emitida_em:'2026-05-15T14:22:00', paga_em:null,
    origem_type:'sale',     origem_id:9821, origem_label:'Venda TR-9821',
    nosso_numero:'00000000018410', linha_digitavel:'07790.00018 41000.001234 56789.012345 4 12340000048000',
    codigo_barras:'07794123400000480000000018410000123456789012345' },
  { id:1840, tipo:'pix_cob', gateway:'asaas',   account_id:14, contato:'Sapataria Bom Jesus',         contato_doc:'08.441.221/0001-12', valor:1280.00,  vencimento:'2026-05-20', status:'emitida', emitida_em:'2026-05-15T11:18:00', paga_em:null,
    origem_type:'sale', origem_id:9820, origem_label:'Venda TR-9820',
    pix_emv:'00020126360014BR.GOV.BCB.PIX0114+5511999999999520400005303986540512.805802BR5920ROTA LIVRE COM VISUAL6009SAO PAULO62070503***6304ABCD',
    pix_qr_code_path:'mock://qr-1840.png' },
  { id:1839, tipo:'pix_recv', gateway:'bcb_pix', account_id:22, contato:'Acme Comércio Ltda',         contato_doc:'11.882.001/0001-33', valor:99.90,    vencimento:'2026-06-19', status:'emitida', emitida_em:'2026-05-19T08:00:00', paga_em:null,
    origem_type:'subscription_license', origem_id:7, origem_label:'Assinatura SaaS · Acme',
    mandato_ciclo:'mensal', mandato_inicio:'2026-05-19', mandato_proximo:'2026-06-19' },
  { id:1838, tipo:'boleto',   gateway:'inter',   account_id:12, contato:'Distrib. Norte Mat. Elétrico', contato_doc:'04.882.001/0001-44', valor:3425.00,  vencimento:'2026-05-21', status:'emitida', emitida_em:'2026-05-13T09:02:00', paga_em:null,
    origem_type:'invoice', origem_id:9818, origem_label:'Fatura RB #9818',
    nosso_numero:'00000000018380', linha_digitavel:'07790.00018 38000.009012 34567.890123 2 12300000342500' },
  // pagas
  { id:1835, tipo:'boleto',   gateway:'inter',   account_id:12, contato:'Açougue do Bairro ME',         contato_doc:'22.014.882/0001-77', valor:890.00,   vencimento:'2026-05-08', status:'paga',    emitida_em:'2026-05-06T14:12:00', paga_em:'2026-05-08T11:32:00',
    origem_type:'sale', origem_id:9815, origem_label:'Venda TR-9815',
    nosso_numero:'00000000018350' },
  { id:1834, tipo:'pix_cob', gateway:'asaas',   account_id:14, contato:'Studio Alfa Design',           contato_doc:'19.221.882/0001-30', valor:2480.00,  vencimento:'2026-05-10', status:'paga',    emitida_em:'2026-05-09T10:00:00', paga_em:'2026-05-10T09:14:00',
    origem_type:'sale', origem_id:9812 },
  { id:1833, tipo:'pix_recv',gateway:'bcb_pix', account_id:22, contato:'TechPro Equipamentos',         contato_doc:'17.221.882/0001-22', valor:99.90,    vencimento:'2026-05-15', status:'paga',    emitida_em:'2026-05-15T08:00:00', paga_em:'2026-05-15T08:01:18',
    origem_type:'subscription_license', origem_id:4, origem_label:'Assinatura SaaS · TechPro',
    mandato_ciclo:'mensal' },
  { id:1832, tipo:'card',    gateway:'asaas',   account_id:14, contato:'Imobiliária Centro',           contato_doc:'17.221.882/0001-30', valor:4500.00,  vencimento:'2026-05-03', status:'paga',    emitida_em:'2026-05-02T10:00:00', paga_em:'2026-05-02T10:00:42',
    origem_type:'sale', origem_id:9812, origem_label:'Venda TR-9812',
    card_brand:'visa', card_last4:'4242', card_3ds:true },
  // vencidas
  { id:1829, tipo:'boleto',   gateway:'inter',   account_id:12, contato:'Mercado União',                contato_doc:'33.112.001/0001-22', valor:1670.00,  vencimento:'2026-05-12', status:'vencida', emitida_em:'2026-05-01T08:30:00', paga_em:null,
    origem_type:'sale', origem_id:9809, origem_label:'Venda TR-9809',
    nosso_numero:'00000000018290' },
  { id:1828, tipo:'pix_cob', gateway:'asaas',   account_id:14, contato:'Loja Casa & Cia',              contato_doc:'29.001.221/0001-15', valor:4120.00,  vencimento:'2026-05-10', status:'vencida', emitida_em:'2026-04-28T11:00:00', paga_em:null,
    origem_type:'sale', origem_id:9805 },
  // cancelada
  { id:1825, tipo:'boleto',   gateway:'inter',   account_id:12, contato:'Farmácia Vida',                contato_doc:'09.882.012/0001-55', valor:580.00,   vencimento:'2026-05-15', status:'cancelada', emitida_em:'2026-04-30T12:00:00', paga_em:null, cancelada_em:'2026-05-05T15:00:00', cancelamento_motivo:'cliente pagou via PIX manual',
    origem_type:'sale', origem_id:9805 },
  // erro
  { id:1824, tipo:'boleto',   gateway:'c6',      account_id:null, contato:'Auto Posto Rota Mor',         contato_doc:'42.118.221/0001-77', valor:1240.00, vencimento:'2026-05-20', status:'erro', emitida_em:'2026-05-19T07:00:00', paga_em:null,
    origem_type:'invoice', origem_id:9810,
    erro_msg:'C6 sandbox indisponível · timeout' },
  // mais subscription_license (tenant SaaS Wagner cobrando)
  { id:1810, tipo:'pix_recv',gateway:'bcb_pix', account_id:22, contato:'ROTA LIVRE Comunicação Visual', contato_doc:'12.345.678/0001-90', valor:99.90,    vencimento:'2026-05-19', status:'paga', emitida_em:'2026-05-19T08:00:00', paga_em:'2026-05-19T08:00:38',
    origem_type:'subscription_license', origem_id:1, origem_label:'Assinatura SaaS · ROTA LIVRE',
    mandato_ciclo:'mensal' },
  { id:1809, tipo:'pix_recv',gateway:'bcb_pix', account_id:22, contato:'WR Sistemas Ltda',              contato_doc:'15.001.882/0001-44', valor:199.90,   vencimento:'2026-05-19', status:'emitida', emitida_em:'2026-05-19T08:00:00', paga_em:null,
    origem_type:'subscription_license', origem_id:2, origem_label:'Assinatura SaaS Premium · WR' },
];

// ───────────────────── shared atoms ─────────────────────
function Btn({ variant = 'default', size = 'sm', children, className, ...rest }) {
  const sizes = { sm:'h-7 px-2.5 text-[12px]', md:'h-8 px-3 text-[12.5px]', xs:'h-6 px-2 text-[11.5px]', lg:'h-9 px-4 text-[13px]' };
  const variants = {
    default: 'bg-stone-900 text-white hover:bg-stone-800',
    outline: 'bg-white border border-stone-300 text-stone-800 hover:bg-stone-50',
    ghost:   'text-stone-600 hover:bg-stone-100',
    primary: 'bg-orange-500 text-white hover:bg-orange-600',
    danger:  'text-rose-700 hover:bg-rose-50',
  };
  return <button {...rest} className={cn('inline-flex items-center gap-1.5 rounded-md font-medium transition disabled:opacity-50 disabled:cursor-not-allowed focus-visible:ring-2 focus-visible:ring-stone-400 focus-visible:ring-offset-2', sizes[size], variants[variant], className)}>{children}</button>;
}

function StatusBadge({ status }) {
  const s = STATUS[status];
  if (!s) return null;
  return (
    <span className={cn("inline-flex items-center gap-1 text-[10.5px] font-medium px-1.5 py-0.5 rounded border", s.bg, s.fg, "border-current/20")}>
      <span className={cn("w-1 h-1 rounded-full", s.dot)} />
      {s.label}
    </span>
  );
}

// Chip gateway+tipo composto — quadrado colorido + sigla + tipo
function GatewayTipoChip({ gateway, tipo, compact }) {
  const drv = DRIVERS[gateway];
  const tp  = TIPOS[tipo];
  if (!drv || !tp) return null;
  return (
    <span className="inline-flex items-center gap-1.5">
      <span className={cn("w-4 h-4 rounded-sm grid place-items-center text-white text-[8.5px] font-bold tracking-tight shrink-0", drv.dot)}>
        {drv.sigla}
      </span>
      {!compact && (
        <span className={cn("text-[10px] font-medium px-1 py-0.5 rounded", tp.bg, tp.fg)}>
          {tp.short}
        </span>
      )}
    </span>
  );
}

function OrigemChip({ tipo, label, onClick }) {
  const o = ORIGENS[tipo];
  if (!o) return null;
  return (
    <button onClick={onClick}
      className={cn("inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded transition", o.bg, o.fg, onClick && "hover:opacity-80 cursor-pointer")}>
      {label || o.label}
      {onClick && <span className="opacity-60">{I.external}</span>}
    </button>
  );
}

function KpiCard({ label, value, sub, tone = 'default', icon, contextual }) {
  const tones = {
    default:  'bg-white border-stone-200',
    dark:     'bg-stone-900 text-white border-stone-900',
    emerald:  'bg-white border-stone-200',
    rose:     'bg-white border-stone-200',
    fuchsia:  'bg-fuchsia-50 border-fuchsia-200',
    violet:   'bg-violet-50 border-violet-200',
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
    <div className={cn("rounded-md border p-3.5 flex flex-col gap-1 relative", tones[tone])}>
      {contextual && <span className="absolute top-2 right-2 text-[9px] uppercase tracking-widest font-medium text-stone-400">contextual</span>}
      <div className="flex items-center gap-1.5">
        {icon && <span className={cn(tone === 'dark' ? 'text-stone-400' : 'text-stone-400')}>{icon}</span>}
        <div className={cn("text-[10px] uppercase tracking-widest font-medium", tone === 'dark' ? 'text-stone-400' : 'text-stone-500')}>{label}</div>
      </div>
      <div className={cn("text-[20px] font-semibold tabular-nums tracking-tight", valueTone[tone] || 'text-stone-900')}>{value}</div>
      {sub && <div className={cn("text-[11px] tabular-nums", tone === 'dark' ? 'text-stone-400' : 'text-stone-500')}>{sub}</div>}
    </div>
  );
}

// ───────────────────── header padrão Cockpit V2 ─────────────────────
function Header({ title, breadcrumb, right }) {
  return (
    <header className="flex items-center gap-4 shrink-0" style={{ padding: '12px 24px', minHeight: 44, background: 'var(--bg)', borderBottom: '1px solid var(--border)' }}>
      <div className="min-w-0 flex-1">
        <div className="tracking-tight whitespace-nowrap" style={{ fontSize: 22, fontWeight: 700, letterSpacing: '-0.025em', lineHeight: 1.2, marginBottom: 2, color: 'var(--text)' }}>{title}</div>
        {breadcrumb && <div className="whitespace-nowrap truncate" style={{ fontSize: 12.5, lineHeight: 1.4, color: 'var(--text-mute)' }}>{breadcrumb}</div>}
      </div>
      <div className="flex items-center gap-2 shrink-0">{right}</div>
    </header>
  );
}

// ───────────────────── exporta ─────────────────────
Object.assign(window, {
  PG_brl: brl, PG_brlNoSign: brlNoSign, PG_brlK: brlK,
  PG_fmtDate: fmtDate, PG_fmtDateRel: fmtDateRel, PG_piiMask: piiMask, PG_cn: cn,
  PG_I: I,
  PG_DRIVERS: DRIVERS, PG_TIPOS: TIPOS, PG_STATUS: STATUS, PG_ORIGENS: ORIGENS,
  PG_ACCOUNTS: ACCOUNTS, PG_GATEWAYS: GATEWAYS, PG_COBRANCAS: COBRANCAS,
  PG_Btn: Btn, PG_StatusBadge: StatusBadge, PG_GatewayTipoChip: GatewayTipoChip,
  PG_OrigemChip: OrigemChip, PG_KpiCard: KpiCard, PG_Header: Header,
});

})();
