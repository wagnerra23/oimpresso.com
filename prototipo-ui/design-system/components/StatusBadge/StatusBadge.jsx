/**
 * StatusBadge — domain-mapped status pill (DS v6).
 * Pure, dependency-free: uses global React + inline token styles.
 * Domains (ERP): documento · fiscal · os · payment · prioridade · sla · atendimento · frescor · tipo
 * Módulo Ponto (RH): intercorrencia · rep — mantidos para o módulo de ponto eletrônico.
 *
 * `frescor` (CRM/Clientes) renders a soft pill with a colored dot and an optional
 * relative-time suffix via `rel` — e.g. <StatusBadge kind="frescor" value="recente" rel="há 1sem" />
 */
export function StatusBadge({ kind, value, label, rel, tone: toneProp }) {
  const C = {
    success: { bg: 'var(--color-success)', fg: '#fff' },
    warning: { bg: 'var(--color-warning)', fg: '#fff' },
    danger:  { bg: 'var(--color-destructive)', fg: '#fff' },
    info:    { bg: 'var(--color-info)', fg: '#fff' },
    neutral: { bg: 'var(--color-secondary)', fg: 'var(--color-secondary-foreground)' },
    outline: { bg: 'transparent', fg: 'var(--color-foreground)', border: 'var(--color-border)' },
    // soft styles (bg-soft + readable fg + colored dot) — SLA & atendimento
    'sla-fresh':   { bg: 'var(--color-sla-fresh-soft)',   fg: 'var(--color-sla-fresh)',   dot: true },
    'sla-aging':   { bg: 'var(--color-sla-aging-soft)',   fg: 'var(--color-sla-aging)',   dot: true },
    'sla-late':    { bg: 'var(--color-sla-late-soft)',    fg: 'var(--color-sla-late)',    dot: true },
    'sla-expired': { bg: 'var(--color-sla-expired-soft)', fg: 'var(--color-sla-expired)', dot: true },
    'canal-email': { bg: 'var(--color-canal-email-soft)', fg: 'var(--color-canal-email)', dot: true },
    'canal-ig':    { bg: 'var(--color-canal-ig-soft)',    fg: 'var(--color-canal-ig)',    dot: true },
    'canal-fb':    { bg: 'var(--color-canal-fb-soft)',    fg: 'var(--color-canal-fb)',    dot: true },
    'canal-ml':    { bg: 'var(--color-canal-ml-soft)',    fg: 'var(--color-canal-ml)',    dot: true },
    // frescor (CRM) — soft tinted + dot, derived from semantic tokens
    'fresc-hot':  { bg: 'color-mix(in oklch, var(--color-success) 16%, transparent)',     fg: 'var(--color-success)',          border: 'color-mix(in oklch, var(--color-success) 30%, transparent)',     dot: true },
    'fresc-warm': { bg: 'color-mix(in oklch, var(--color-warning) 16%, transparent)',     fg: 'var(--color-warning)',          border: 'color-mix(in oklch, var(--color-warning) 30%, transparent)',     dot: true },
    'fresc-cold': { bg: 'color-mix(in oklch, var(--color-destructive) 16%, transparent)', fg: 'oklch(0.74 0.14 18)',           border: 'color-mix(in oklch, var(--color-destructive) 30%, transparent)', dot: true },
    // tipo de cadastro — mono pill
    'tipo-pj': { bg: 'var(--color-tipo-pj-soft)', fg: 'var(--color-tipo-pj)', mono: true },
    'tipo-pf': { bg: 'var(--color-tipo-pf-soft)', fg: 'var(--color-tipo-pf)', mono: true },
  };
  const MAP = {
    // Documento / aprovação — workflow genérico de ERP (pedido, requisição, OP…)
    documento: {
      rascunho: ['Rascunho', 'outline'], pendente: ['Pendente', 'neutral'],
      aprovado: ['Aprovado', 'success'], rejeitado: ['Rejeitado', 'danger'],
      aplicado: ['Aplicado', 'info'], cancelado: ['Cancelado', 'outline'],
    },
    // Documento fiscal — NF-e / NFC-e / MDF-e
    fiscal: {
      rascunho: ['Rascunho', 'outline'], emitida: ['Emitida', 'neutral'],
      autorizada: ['Autorizada', 'success'], cancelada: ['Cancelada', 'outline'],
      denegada: ['Denegada', 'danger'], rejeitada: ['Rejeitada', 'danger'],
      inutilizada: ['Inutilizada', 'outline'],
    },
    // Ordem de serviço (Oficina Auto) — FSM real: aberta → orçamento → aprovada → em serviço/produção → concluída → entregue
    os: {
      aberta: ['Aberta', 'neutral'], orcamento: ['Aguardando aprovação', 'warning'],
      aprovada: ['Aprovada', 'success'], em_servico: ['Em serviço', 'warning'],
      em_producao: ['Em produção', 'warning'], concluida: ['Concluída', 'success'],
      entregue: ['Entregue', 'success'], cancelada: ['Cancelada', 'outline'],
      atrasada: ['Atrasada', 'danger'],
    },
    // Intercorrência (módulo Ponto/RH) — ajuste de marcação
    intercorrencia: {
      rascunho: ['Rascunho', 'outline'], pendente: ['Pendente', 'neutral'],
      aprovada: ['Aprovada', 'success'], rejeitada: ['Rejeitada', 'danger'],
      aplicada: ['Aplicada', 'info'], cancelada: ['Cancelada', 'outline'],
    },
    prioridade: {
      baixa: ['Baixa', 'outline'], normal: ['Normal', 'neutral'],
      alta: ['Alta', 'danger'], urgente: ['Urgente', 'danger'],
    },
    payment: {
      pending: ['Pendente', 'neutral'], partial: ['Parcial', 'warning'],
      paid: ['Pago', 'success'], due: ['Vencido', 'danger'], overdue: ['Atrasado', 'danger'],
    },
    rep: { rep_p: ['REP-P', 'outline'], rep_c: ['REP-C', 'outline'], rep_a: ['REP-A', 'outline'] },
    // SLA de atendimento — frescor do ticket / cobrança
    sla: {
      fresh: ['No prazo', 'sla-fresh'], aging: ['Vencendo', 'sla-aging'],
      late: ['Atrasado', 'sla-late'], expired: ['Vencido', 'sla-expired'],
    },
    // Tipo de cadastro — PJ / PF
    tipo: {
      pj: ['PJ', 'tipo-pj'], pf: ['PF', 'tipo-pf'],
    },
    // Frescor do cliente (recência de compra) — CRM / Clientes
    frescor: {
      recente:  ['recente',  'fresc-hot'],
      fresc:    ['fresc',    'fresc-warm'],
      frio:     ['frio',     'fresc-cold'],
      distante: ['distante', 'fresc-cold'],
    },
    // Canal do atendimento (inbox omnichannel)
    atendimento: {
      email: ['E-mail', 'canal-email'], instagram: ['Instagram', 'canal-ig'],
      facebook: ['Facebook', 'canal-fb'], mercadolivre: ['Mercado Livre', 'canal-ml'],
      whatsapp: ['WhatsApp', 'sla-fresh'],
    },
  };
  const key = (value || '').toString().toLowerCase();
  const entry = (MAP[kind] || {})[key];
  const tone = toneProp || (entry ? entry[1] : 'outline');
  const text = label || (entry ? entry[0] : value);
  const c = C[tone] || C.outline;
  const pulse = kind === 'prioridade' && key === 'urgente';
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 5,
      padding: c.mono ? '2px 6px' : '2px 10px', borderRadius: c.mono ? 'var(--radius-sm, 4px)' : 9999,
      fontSize: c.mono ? 'var(--fs-1)' : 'var(--fs-2)', fontWeight: c.mono ? 700 : 500, lineHeight: 1.5,
      fontFamily: c.mono ? 'var(--font-mono)' : 'inherit', letterSpacing: c.mono ? '.03em' : 'normal',
      background: c.bg, color: c.fg,
      border: '1px solid ' + (c.border || 'transparent'),
      animation: pulse ? 'sb-pulse 2s cubic-bezier(.4,0,.6,1) infinite' : 'none',
    }}>
      {c.dot && <span style={{ width: 6, height: 6, borderRadius: 9999, background: 'currentColor', flexShrink: 0 }} aria-hidden />}
      {text}
      {rel && <><span style={{ opacity: 0.55 }}>·</span><span style={{ opacity: 0.85, fontWeight: 400 }}>{rel}</span></>}
    </span>
  );
}
