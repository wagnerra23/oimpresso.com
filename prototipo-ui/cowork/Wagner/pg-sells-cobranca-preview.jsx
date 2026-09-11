/* @ts-nocheck */
/* eslint-disable */
// sells-emitir-cobranca-modal.jsx — Tela 3 F1 · Sells/Index drawer + botão "Emitir cobrança"
// Persona: Larissa balcão ROTA LIVRE. Modificação cirúrgica no drawer A+ 9,75 PR #1064.
// Risco regressão ALTO — gate F1.5 ≥90.
// Pino conceitual: mostra drawer SaleSheet (mock simplificado) com 3 estados.
(() => {
const { useState } = React;
const {
  PG_brl: brl, PG_brlNoSign: brlNoSign, PG_fmtDate: fmtDate, PG_cn: cn,
  PG_I: I, PG_DRIVERS: DRIVERS, PG_ACCOUNTS: ACCOUNTS,
  PG_Btn: Btn, PG_StatusBadge: StatusBadge, PG_GatewayTipoChip: GatewayTipoChip,
} = window;

// Venda mock — representativa de Sells/Index PR #1064
const SALE = {
  id: 4821,
  invoice_no: 'VD-4821',
  client: 'Acme Comércio Ltda',
  client_doc: '11.882.001/0001-33',
  date: '2026-05-15',
  time: '14:32',
  seller: 'Larissa B.',
  total: 4820.00,
  payment_status: 'pending', // toggled below
  fsm_stage: 'paid',
  itemsList: [
    { id:1, type:'produto', nome:'Banner 3×2m vinil + lona',     qtd:1, vlr:2800.00 },
    { id:2, type:'servico', nome:'Aplicação no local',            qtd:1, vlr:480.00 },
    { id:3, type:'produto', nome:'Adesivo recortado · 12 peças',  qtd:12, vlr:120.00 },
    { id:4, type:'produto', nome:'Faixa lona 5×1m',               qtd:1, vlr:540.00 },
  ],
};

// 3 estados da cobrança vinculada à venda
const COB_ESTADOS = {
  A: { label:'Sem cobrança', kind:'none' },
  B: { label:'Cobrança paga',     kind:'paid',
       cob: { id:1832, tipo:'pix_cob', gateway:'asaas', valor:4820.00, status:'paga',
              emitida_em:'2026-05-15T14:35:00', paga_em:'2026-05-15T14:36:18' } },
  C: { label:'Cobrança com erro', kind:'error',
       cob: { id:1824, tipo:'boleto', gateway:'c6', valor:4820.00, status:'erro',
              emitida_em:'2026-05-15T14:35:00', erro_msg:'C6 sandbox indisponível · timeout' } },
};

// ─────────────────────────────────────────────────────────────
// SellsEmitirCobrancaPin — pino conceitual mostrando o drawer
// ─────────────────────────────────────────────────────────────
function SellsEmitirCobrancaPin() {
  const [estado, setEstado] = useState('A');
  const [modalOpen, setModalOpen] = useState(false);
  const cob = COB_ESTADOS[estado].cob;

  return (
    <div className="h-full bg-stone-100 flex flex-col font-sans relative" data-screen-label="03 Sells drawer · Emitir cobrança">

      {/* Toolbar de pino — controla qual estado do drawer mostrar */}
      <div className="px-6 py-3 bg-white border-b border-stone-200 flex items-center gap-3 shrink-0">
        <div className="text-[10px] uppercase tracking-widest font-medium text-stone-500">Pino conceitual · estado da cobrança</div>
        <div className="inline-flex bg-stone-100 rounded-md p-0.5 border border-stone-200">
          {Object.entries(COB_ESTADOS).map(([k, v]) => (
            <button key={k} onClick={() => setEstado(k)} className={cn(
              "h-7 px-3 rounded text-[11.5px] font-medium transition flex items-center gap-1.5",
              estado === k ? "bg-white shadow-sm text-stone-900" : "text-stone-600 hover:text-stone-800"
            )}>
              <span className={cn(
                "w-4 h-4 rounded-full grid place-items-center text-[10px] font-bold",
                estado === k ? "bg-stone-900 text-white" : "bg-stone-300 text-stone-600"
              )}>{k}</span>
              {v.label}
            </button>
          ))}
        </div>
        <div className="ml-auto text-[10.5px] text-stone-500">
          escopo F1: <strong>botão + modal</strong> · drawer existente <strong>NÃO modificado</strong>
        </div>
      </div>

      {/* Layout fake: Sells/Index lista (esmaecida) + drawer aberto */}
      <div className="flex-1 grid grid-cols-[1fr_640px] min-h-0">

        {/* Lista esmaecida — só indica que estamos no Sells/Index */}
        <div className="bg-stone-50 px-6 py-4 overflow-hidden relative">
          <div className="opacity-50 select-none">
            <div className="text-[11px] uppercase tracking-widest font-medium text-stone-500 mb-3">Sells/Index (existente · A+ 9,75 KB-9.75 PR #1064)</div>
            <div className="border border-stone-200 rounded-md bg-white">
              <div className="px-3 py-2 border-b border-stone-100 grid grid-cols-[100px_1fr_120px_120px_100px] gap-3 text-[10px] uppercase tracking-widest text-stone-400 font-medium">
                <div>Data</div><div>Cliente</div><div>Status</div><div>FSM</div><div className="text-right">Total</div>
              </div>
              {[
                { id:4821, c:'Acme Comércio Ltda',         st:'paid',    fsm:'paga',           tot:4820.00, sel:true },
                { id:4820, c:'Padaria Estrela',            st:'pending', fsm:'aguard. pagto',  tot:680.00 },
                { id:4819, c:'TechPro Equipamentos',       st:'paid',    fsm:'entregue',       tot:1840.00 },
                { id:4818, c:'Distrib. Norte Mat. Elétrico', st:'pending', fsm:'orçamento',     tot:9420.00 },
              ].map(v => (
                <div key={v.id} className={cn(
                  "px-3 py-2 border-b border-stone-100 last:border-b-0 grid grid-cols-[100px_1fr_120px_120px_100px] gap-3 text-[12px]",
                  v.sel && "bg-stone-100 ring-1 ring-stone-200"
                )}>
                  <div className="text-stone-500 font-mono text-[11px]">15/05/2026</div>
                  <div className="font-medium">{v.c}</div>
                  <div><span className={cn("text-[10px] px-1.5 py-0.5 rounded border", v.st === 'paid' ? "bg-emerald-50 text-emerald-700 border-emerald-200" : "bg-amber-50 text-amber-700 border-amber-200")}>{v.st}</span></div>
                  <div className="text-stone-500 text-[11px]">{v.fsm}</div>
                  <div className="text-right font-semibold tabular-nums">{brlNoSign(v.tot)}</div>
                </div>
              ))}
            </div>
          </div>
          <div className="absolute inset-0 bg-gradient-to-r from-transparent to-stone-100/80 pointer-events-none" />
        </div>

        {/* DRAWER SaleSheet — destacado */}
        <SaleSheetDrawerMock estado={estado} cob={cob} onEmitir={() => setModalOpen(true)} />
      </div>

      {modalOpen && <ModalEmitirCobranca sale={SALE} onClose={() => setModalOpen(false)} />}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────
// SaleSheetDrawerMock — espelho simplificado do drawer SaleSheet PR #1064
// A área NOVA é APENAS o chip de cobrança + botão no footer.
// Resto preservado fielmente (header, abas, itens, totais, FSM stepper).
// ─────────────────────────────────────────────────────────────
function SaleSheetDrawerMock({ estado, cob, onEmitir }) {
  const [tab, setTab] = useState('itens');
  return (
    <div className="bg-white border-l border-stone-200 shadow-xl flex flex-col h-full overflow-hidden">

      {/* Header — PRESERVADO */}
      <div className="px-5 py-4 border-b border-stone-200">
        <div className="flex items-start gap-3">
          <div className="flex-1 min-w-0">
            <div className="text-[10px] uppercase tracking-widest font-mono text-stone-400 font-medium">#{SALE.invoice_no} · venda</div>
            <h2 className="text-[18px] font-semibold mt-0.5">{SALE.client}</h2>
            <p className="text-[12px] text-stone-500 mt-0.5">{fmtDate(SALE.date)} às {SALE.time} · {SALE.seller}</p>
          </div>
          <div className="text-right">
            <div className="text-[20px] font-semibold tabular-nums tracking-tight">{brl(SALE.total)}</div>
            <span className="inline-flex items-center gap-1 text-[10.5px] font-medium px-1.5 py-0.5 rounded border bg-emerald-50 text-emerald-700 border-emerald-200 mt-1">
              <span className="w-1 h-1 rounded-full bg-emerald-500" />Paga
            </span>
          </div>
        </div>

        {/* FSM stepper — PRESERVADO */}
        <div className="mt-3 flex items-center gap-1 text-[11px]">
          {['Orçamento','Em produção','Paga','Entregue'].map((s, i) => (
            <React.Fragment key={s}>
              <span className={cn(
                "px-2 py-0.5 rounded font-medium",
                i < 3 ? "bg-stone-900 text-white" : i === 3 ? "bg-stone-200 text-stone-500" : "text-stone-400"
              )}>{s}</span>
              {i < 3 && <span className="text-stone-300">→</span>}
            </React.Fragment>
          ))}
        </div>
      </div>

      {/* Tabs — PRESERVADO */}
      <div className="border-b border-stone-200 px-5 flex gap-1">
        {[
          { id:'itens',    l:'Itens',     ct:4 },
          { id:'pagto',    l:'Pagamento', ct:1, badge: estado !== 'A' ? 'NOVO' : null },
          { id:'fiscal',   l:'Fiscal',    ct:1 },
          { id:'historia', l:'Histórico', ct:6 },
        ].map(t => (
          <button key={t.id} onClick={() => setTab(t.id)} className={cn(
            "h-9 px-3 text-[12px] border-b-2 -mb-px transition flex items-center gap-1.5",
            tab === t.id ? "border-stone-900 text-stone-900 font-medium" : "border-transparent text-stone-500 hover:text-stone-800"
          )}>
            {t.l}
            {t.ct && <span className={cn("text-[10px] tabular-nums px-1 rounded", tab === t.id ? "bg-stone-100 text-stone-700" : "text-stone-400")}>{t.ct}</span>}
            {t.badge && <span className="text-[8.5px] uppercase font-bold px-1 py-px rounded bg-orange-100 text-orange-700">{t.badge}</span>}
          </button>
        ))}
      </div>

      <div className="flex-1 overflow-auto">
        {tab === 'itens' && <TabItens />}
        {tab === 'pagto' && <TabPagamento estado={estado} cob={cob} onEmitir={onEmitir} />}
        {tab === 'fiscal' && (
          <div className="p-6 text-[12px] text-stone-500">
            <div className="text-[10px] uppercase tracking-widest font-medium text-stone-400 mb-2">Fiscal</div>
            <div className="border border-stone-200 rounded-md p-3 text-stone-600">NF-e #582341 · autorizada SEFAZ · DANFE disponível</div>
          </div>
        )}
        {tab === 'historia' && (
          <div className="p-6 text-[12px] text-stone-500">
            <div className="text-[10px] uppercase tracking-widest font-medium text-stone-400 mb-2">Histórico FSM</div>
            <div className="text-stone-400 italic text-[11.5px]">timeline 6 eventos · PRESERVADO do PR #1064</div>
          </div>
        )}
      </div>

      {/* Footer NOVO: chip cobrança (linha 1) + botões ações (linha 2) */}
      <div className="border-t border-stone-200 bg-stone-50/40 p-3 space-y-2">
        {/* LINHA 1 — área NOVA do PR PaymentGateway · chip cobrança */}
        <CobrancaChipRow estado={estado} cob={cob} onEmitir={onEmitir} />

        {/* LINHA 2 — botões PRESERVADOS */}
        <div className="flex items-center gap-2">
          <Btn variant="outline" size="sm">{I.download}PDF</Btn>
          <Btn variant="outline" size="sm">Imprimir recibo</Btn>
          <Btn variant="outline" size="sm">Transcript</Btn>
          <div className="flex-1" />
          <Btn variant="danger" size="sm">{I.x}Cancelar venda</Btn>
        </div>
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────
// CobrancaChipRow — área NOVA · 3 estados (A/B/C)
// ESTADO A: botão primário "Emitir cobrança" (sem chip ainda)
// ESTADO B: chip cobrança paga + link "Ver cobrança"
// ESTADO C: chip cobrança erro + botão secundário "Reemitir"
// ─────────────────────────────────────────────────────────────
function CobrancaChipRow({ estado, cob, onEmitir }) {

  // Estado A — sem cobrança ainda
  if (estado === 'A') {
    return (
      <div className="bg-white border border-stone-200 rounded-md px-3 py-2 flex items-center gap-3">
        <span className="w-7 h-7 rounded grid place-items-center bg-stone-100 text-stone-500">{I.receipt}</span>
        <div className="flex-1 min-w-0">
          <div className="text-[12px] font-medium text-stone-900">Sem cobrança vinculada</div>
          <div className="text-[10.5px] text-stone-500 mt-0.5">Emita boleto, PIX ou cartão direto desta venda</div>
        </div>
        <Btn variant="primary" size="sm" onClick={onEmitir}>
          {I.plus}Emitir cobrança
        </Btn>
      </div>
    );
  }

  // Estado B — cobrança paga (link ver cobrança)
  if (estado === 'B') {
    const drv = DRIVERS[cob.gateway];
    return (
      <div className="bg-emerald-50/60 border border-emerald-200 rounded-md px-3 py-2 flex items-center gap-3">
        <span className="w-7 h-7 rounded grid place-items-center bg-emerald-100 text-emerald-700">{I.check}</span>
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2">
            <div className="text-[12px] font-medium text-stone-900">Cobrança #{cob.id} paga</div>
            <GatewayTipoChip gateway={cob.gateway} tipo={cob.tipo} />
          </div>
          <div className="text-[10.5px] text-stone-600 mt-0.5">
            {brl(cob.valor)} · liquidada em {fmtDate(cob.paga_em.slice(0,10))} {cob.paga_em.slice(11,16)} via {drv.nome}
          </div>
        </div>
        <Btn variant="outline" size="sm">
          {I.external}Ver cobrança
        </Btn>
      </div>
    );
  }

  // Estado C — cobrança com erro (botão reemitir)
  return (
    <div className="bg-rose-50/60 border border-rose-200 rounded-md px-3 py-2 flex items-center gap-3">
      <span className="w-7 h-7 rounded grid place-items-center bg-rose-100 text-rose-700">{I.alert}</span>
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2">
          <div className="text-[12px] font-medium text-stone-900">Cobrança #{cob.id} com erro</div>
          <GatewayTipoChip gateway={cob.gateway} tipo={cob.tipo} />
        </div>
        <div className="text-[10.5px] text-rose-700 font-mono mt-0.5 truncate">{cob.erro_msg}</div>
      </div>
      <Btn variant="outline" size="sm" onClick={onEmitir}>
        {I.refresh}Reemitir
      </Btn>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────
// TabItens — espelho do PR #1064 (PRESERVADO conceitualmente)
// ─────────────────────────────────────────────────────────────
function TabItens() {
  return (
    <div className="p-5">
      <div className="text-[10px] uppercase tracking-widest font-medium text-stone-500 mb-2">Itens · {SALE.itemsList.length}</div>
      <div className="border border-stone-200 rounded-md bg-white">
        {SALE.itemsList.map((it, i) => (
          <div key={it.id} className={cn("px-3 py-2.5 flex items-center gap-3", i < SALE.itemsList.length - 1 && "border-b border-stone-100")}>
            <span className={cn(
              "w-5 h-5 rounded grid place-items-center text-[10px] font-bold",
              it.type === 'produto' ? "bg-blue-50 text-blue-700" : "bg-violet-50 text-violet-700"
            )}>{it.type === 'produto' ? 'P' : 'S'}</span>
            <div className="flex-1 min-w-0">
              <div className="text-[12.5px] font-medium truncate">{it.nome}</div>
              <div className="text-[10.5px] text-stone-500">{it.qtd}× · unit {brl(it.vlr)}</div>
            </div>
            <div className="text-[13px] font-semibold tabular-nums">{brl(it.qtd * it.vlr)}</div>
          </div>
        ))}
      </div>
      <div className="mt-3 flex items-baseline gap-3">
        <div className="text-[10px] uppercase tracking-widest font-medium text-stone-500">Total</div>
        <div className="text-[20px] font-semibold tabular-nums tracking-tight">{brl(SALE.total)}</div>
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────
// TabPagamento — espelha o PR #1064 + seção nova "Cobrança"
// ─────────────────────────────────────────────────────────────
function TabPagamento({ estado, cob, onEmitir }) {
  return (
    <div className="p-5 space-y-4">
      <div>
        <div className="text-[10px] uppercase tracking-widest font-medium text-stone-500 mb-2">Pagamento registrado · PRESERVADO</div>
        <div className="border border-stone-200 rounded-md p-3 text-[12px] text-stone-600">
          <div className="flex items-center gap-2">
            <span className="w-2 h-2 rounded-full bg-emerald-500" />
            <span><strong>{brl(SALE.total)}</strong> via PIX · 15/05 14:36 · ref Asaas #PMT-9921</span>
          </div>
        </div>
      </div>

      <div>
        <div className="flex items-center gap-2 mb-2">
          <div className="text-[10px] uppercase tracking-widest font-medium text-stone-500">Cobrança</div>
          <span className="text-[8.5px] uppercase font-bold px-1 py-px rounded bg-orange-100 text-orange-700">NOVO · PaymentGateway</span>
        </div>
        <CobrancaChipRow estado={estado} cob={cob} onEmitir={onEmitir} />
        <div className="text-[10.5px] text-stone-400 mt-2">
          Cobrança ≠ Pagamento. Cobrança = boleto/PIX/cartão emitido AO cliente. Pagamento = lançamento contábil quando dinheiro entra. Cobrança paga dispara pagamento automaticamente via evento <span className="font-mono">CobrancaPaga</span>.
        </div>
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────
// ModalEmitirCobranca — modal compacto · versão simplificada do Sheet 4-step da Tela 1
// Diferença chave: contato + valor pre-preenchidos (vêm da venda)
// ─────────────────────────────────────────────────────────────
function ModalEmitirCobranca({ sale, onClose }) {
  const [tipo, setTipo] = useState('boleto');
  const [conta, setConta] = useState(12);
  const [venc, setVenc] = useState('2026-05-26');
  const [extrasOpen, setExtrasOpen] = useState(false);

  return (
    <div className="fixed inset-0 z-30 grid place-items-center p-6" onClick={onClose}>
      <div className="absolute inset-0 bg-stone-900/40" />
      <div className="relative w-[520px] max-h-[90vh] bg-white rounded-lg shadow-2xl border border-stone-200 flex flex-col" onClick={e => e.stopPropagation()}>

        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
          <div className="flex-1">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Emitir cobrança da venda</div>
            <div className="text-[15px] font-semibold mt-0.5">{sale.invoice_no} · {sale.client}</div>
          </div>
          <button onClick={onClose} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500">{I.x}</button>
        </div>

        {/* Resumo herdado da venda */}
        <div className="px-5 py-3 border-b border-stone-200 bg-amber-50/40 grid grid-cols-2 gap-3 text-[12px]">
          <div>
            <div className="text-[10px] uppercase tracking-widest font-medium text-stone-500">Pagador</div>
            <div className="mt-0.5 font-medium truncate">{sale.client}</div>
            <div className="text-[10.5px] text-stone-400 font-mono mt-0.5">{sale.client_doc}</div>
          </div>
          <div>
            <div className="text-[10px] uppercase tracking-widest font-medium text-stone-500">Valor</div>
            <div className="mt-0.5 font-semibold tabular-nums text-[14px]">{brl(sale.total)}</div>
            <div className="text-[10.5px] text-stone-500 mt-0.5">herdado da venda · editável abaixo</div>
          </div>
        </div>

        <div className="flex-1 overflow-auto p-5 space-y-4">
          {/* Tipo */}
          <div>
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-2">Tipo de cobrança</div>
            <div className="grid grid-cols-4 gap-2">
              {[
                { id:'boleto',   label:'Boleto',     icon:I.receipt,    available:true },
                { id:'pix_cob',  label:'PIX',        icon:I.qrcode,     available:true },
                { id:'pix_recv', label:'PIX Aut.',   icon:I.zap,        available:false, why:'só pra assinaturas' },
                { id:'card',     label:'Cartão',     icon:I.creditcard, available:true },
              ].map(t => (
                <button key={t.id} onClick={() => t.available && setTipo(t.id)} disabled={!t.available} className={cn(
                  "rounded-md border p-2.5 transition disabled:opacity-40 disabled:cursor-not-allowed",
                  tipo === t.id ? "border-stone-900 ring-2 ring-stone-900/10 bg-stone-50" : "border-stone-200 hover:border-stone-400 hover:bg-stone-50"
                )} title={t.why}>
                  <div className="text-stone-700 mb-1 mx-auto w-fit">{t.icon}</div>
                  <div className="text-[11px] font-medium text-stone-900">{t.label}</div>
                </button>
              ))}
            </div>
          </div>

          {/* Conta + Vencimento em 1 linha */}
          <div className="grid grid-cols-2 gap-3">
            <Field label="Conta destino">
              <select value={conta} onChange={e=>setConta(parseInt(e.target.value))} className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px]">
                {ACCOUNTS.filter(a => a.driver && DRIVERS[a.driver]?.tipos.includes(tipo === 'pix_cob' ? 'pix_cob' : tipo)).map(a => (
                  <option key={a.id} value={a.id}>{a.name}</option>
                ))}
              </select>
            </Field>
            <Field label="Vencimento">
              <input type="date" value={venc} onChange={e=>setVenc(e.target.value)} className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px]" />
            </Field>
          </div>

          {/* Multa/juros/desconto opcional (accordion fechado) */}
          <div>
            <button onClick={() => setExtrasOpen(o => !o)} className="text-[11.5px] text-stone-600 hover:text-stone-900 select-none flex items-center gap-1">
              {extrasOpen ? I.chevD : I.chevR}
              Multa, juros, desconto (opcional)
            </button>
            {extrasOpen && (
              <div className="mt-2 grid grid-cols-3 gap-2 pl-3 border-l-2 border-stone-200">
                <Field label="Multa %"><input placeholder="2,00" className="w-full h-7 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono" /></Field>
                <Field label="Juros %/dia"><input placeholder="0,033" className="w-full h-7 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono" /></Field>
                <Field label="Desconto até"><input type="date" className="w-full h-7 bg-white border border-stone-300 rounded px-2 text-[11.5px]" /></Field>
              </div>
            )}
          </div>

          {/* Preview o que vai acontecer */}
          <div className="bg-stone-50 border border-stone-200 rounded-md p-3 text-[11px] text-stone-700 space-y-1">
            <div className="flex items-start gap-2">
              <span className="text-stone-500 mt-0.5">{I.shield}</span>
              <div>
                Ao confirmar dispara <span className="font-mono">PaymentGateway::emitir{tipo === 'card' ? 'Cartao' : tipo.startsWith('pix') ? 'Pix' : 'Boleto'}()</span> com origem
                <span className="font-mono mx-1">sale:{sale.id}</span>
                (idempotente — não duplica).
              </div>
            </div>
          </div>
        </div>

        <div className="border-t border-stone-200 p-3 flex items-center gap-2 bg-stone-50/60">
          <div className="flex-1" />
          <Btn variant="outline" onClick={onClose}>Cancelar</Btn>
          <Btn variant="primary" onClick={onClose}>{I.check}Emitir cobrança</Btn>
        </div>
      </div>
    </div>
  );
}

function Field({ label, children }) {
  return (
    <label className="block">
      <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-1">{label}</div>
      {children}
    </label>
  );
}

window.PG_SellsEmitirCobrancaPin = SellsEmitirCobrancaPin;
})();
