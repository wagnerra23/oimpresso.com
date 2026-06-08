/* @ts-nocheck */
/* eslint-disable */
// cobranca-page.jsx — Tela 1 F1 · Financeiro/Cobrança
// Substitui /financeiro/boletos. Persona Eliana[E] + Larissa (via Sells modal).
// KB-9.75 mira 9,75. Material canon: boleto-contas-app.jsx 215-557.
(() => {
const { useState, useMemo, useEffect } = React;
const {
  PG_brl: brl, PG_brlNoSign: brlNoSign, PG_brlK: brlK,
  PG_fmtDate: fmtDate, PG_fmtDateRel: fmtDateRel, PG_piiMask: piiMask, PG_cn: cn,
  PG_I: I, PG_DRIVERS: DRIVERS, PG_TIPOS: TIPOS, PG_STATUS: STATUS, PG_ORIGENS: ORIGENS,
  PG_ACCOUNTS: ACCOUNTS, PG_GATEWAYS: GATEWAYS, PG_COBRANCAS: COBRANCAS,
  PG_Btn: Btn, PG_StatusBadge: StatusBadge, PG_GatewayTipoChip: GatewayTipoChip,
  PG_OrigemChip: OrigemChip, PG_KpiCard: KpiCard, PG_Header: Header,
} = window;

const TODAY = '2026-05-19';
// businessId = 1 → Wagner Oimpresso HQ (mostra origem subscription_license)
// businessId = 4 → ROTA LIVRE (NÃO mostra subscription_license)
const BUSINESS_ID = 1;
const IS_SAAS_BUSINESS = BUSINESS_ID === 1;

// ─────────────────────────────────────────────────────────────
// CobrancaPage — Tela 1 root
// ─────────────────────────────────────────────────────────────
function CobrancaPage() {
  // KB-9.75: persistência localStorage namespace oimpresso.financeiro.cobranca.*
  const ls = (k, d) => { try { const v = localStorage.getItem('oimpresso.financeiro.cobranca.' + k); return v == null ? d : JSON.parse(v); } catch (e) { return d; } };
  const setLs = (k, v) => { try { localStorage.setItem('oimpresso.financeiro.cobranca.' + k, JSON.stringify(v)); } catch (e) {} };

  const [tabStatus, _setTabStatus] = useState(() => ls('tab', 'all'));
  const [tipoFilter, _setTipoFilter] = useState(() => ls('tipo', 'all'));
  const [gatewayFilter, _setGatewayFilter] = useState(() => ls('gateway', 'all'));
  const [accountFilter, _setAccountFilter] = useState(() => ls('account', 'all'));
  const [origemFilter, _setOrigemFilter] = useState(() => ls('origem', 'all'));
  const setTabStatus = (v) => { _setTabStatus(v); setLs('tab', v); };
  const setTipoFilter = (v) => { _setTipoFilter(v); setLs('tipo', v); };
  const setGatewayFilter = (v) => { _setGatewayFilter(v); setLs('gateway', v); };
  const setAccountFilter = (v) => { _setAccountFilter(v); setLs('account', v); };
  const setOrigemFilter = (v) => { _setOrigemFilter(v); setLs('origem', v); };

  const [busca, setBusca] = useState('');
  const [drawer, setDrawer] = useState(null);
  const [novaOpen, setNovaOpen] = useState(false);
  const [remessaOpen, setRemessaOpen] = useState(false);
  const [cheatOpen, setCheatOpen] = useState(false);
  const [aiOpen, setAiOpen] = useState(false);
  const [focusIdx, setFocusIdx] = useState(-1);
  const buscaRef = React.useRef(null);

  // tipo group → match: 'pix' agrupa pix_cob+pix_cobv (não recv)
  const tipoMatch = (t) => {
    if (tipoFilter === 'all') return true;
    if (tipoFilter === 'pix') return t === 'pix_cob' || t === 'pix_cobv';
    return t === tipoFilter;
  };

  // KB-9.75: atalhos teclado (J/K nav rows · / busca · ? cheat · Esc fecha drawer/sheet)
  useEffect(() => {
    const onKey = (e) => {
      const inField = ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target?.tagName);
      if (e.key === 'Escape') {
        if (drawer) { setDrawer(null); e.preventDefault(); }
        else if (novaOpen) { setNovaOpen(false); e.preventDefault(); }
        else if (remessaOpen) { setRemessaOpen(false); e.preventDefault(); }
        else if (cheatOpen) { setCheatOpen(false); e.preventDefault(); }
        return;
      }
      if (inField) return;
      if (drawer || novaOpen || remessaOpen || cheatOpen) return;
      if (e.key === '/') { e.preventDefault(); buscaRef.current?.focus(); }
      else if (e.key === '?') { e.preventDefault(); setCheatOpen(true); }
      else if (e.key === 'j' || e.key === 'ArrowDown') { e.preventDefault(); setFocusIdx(i => Math.min(filtered.length - 1, i + 1)); }
      else if (e.key === 'k' || e.key === 'ArrowUp') { e.preventDefault(); setFocusIdx(i => Math.max(0, i - 1)); }
      else if (e.key === 'Enter' && focusIdx >= 0) { e.preventDefault(); const c = filtered[focusIdx]; if (c) setDrawer(c); }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [drawer, novaOpen, remessaOpen, cheatOpen, focusIdx]);

  const filtered = useMemo(() => {
    return COBRANCAS.filter(c => {
      if (tabStatus !== 'all' && c.status !== tabStatus) return false;
      if (!tipoMatch(c.tipo)) return false;
      if (gatewayFilter !== 'all' && c.gateway !== gatewayFilter) return false;
      if (accountFilter !== 'all' && c.account_id !== parseInt(accountFilter)) return false;
      if (origemFilter !== 'all' && c.origem_type !== origemFilter) return false;
      if (busca) {
        const q = busca.toLowerCase();
        const hay = `${c.contato} ${c.contato_doc || ''} ${c.nosso_numero || ''} ${c.origem_label || ''}`.toLowerCase();
        if (!hay.includes(q)) return false;
      }
      return true;
    });
  }, [tabStatus, tipoFilter, gatewayFilter, accountFilter, origemFilter, busca]);

  const totais = useMemo(() => {
    const all = COBRANCAS;
    const emAberto = all.filter(c => c.status === 'emitida');
    const pago = all.filter(c => c.status === 'paga');
    const vencido = all.filter(c => c.status === 'vencida');
    const mandatos = all.filter(c => c.tipo === 'pix_recv' && c.status !== 'cancelada');
    const saas = all.filter(c => c.origem_type === 'subscription_license' && c.status === 'paga');
    return {
      qtd_aberto: emAberto.length,
      valor_aberto: emAberto.reduce((s,c) => s + c.valor, 0),
      qtd_pago: pago.length,
      valor_pago: pago.reduce((s,c) => s + c.valor, 0),
      qtd_vencido: vencido.length,
      valor_vencido: vencido.reduce((s,c) => s + c.valor, 0),
      qtd_mandatos: mandatos.length,
      mrr_pago: saas.reduce((s,c) => s + c.valor, 0),
      mrr_qtd: saas.length,
    };
  }, []);

  // breadcrumb dinâmico
  const breadcrumb = useMemo(() => {
    const parts = ['Financeiro', 'Cobrança'];
    if (gatewayFilter !== 'all') parts.push(DRIVERS[gatewayFilter]?.nome || gatewayFilter);
    if (tipoFilter !== 'all') parts.push(TIPOS[tipoFilter === 'pix' ? 'pix_cob' : tipoFilter]?.label || tipoFilter);
    return parts.join(' · ');
  }, [gatewayFilter, tipoFilter]);

  // KPI #4 contextual
  const kpiContextual = useMemo(() => {
    if (tipoFilter === 'pix_recv') {
      return { label: 'Mandatos ativos', value: totais.qtd_mandatos, sub:'contratos PIX Automático vigentes', tone:'violet', icon: I.zap };
    }
    if (origemFilter === 'subscription_license') {
      return { label: 'MRR cobrado este mês', value: brl(totais.mrr_pago), sub:`${totais.mrr_qtd} tenants pagos`, tone:'fuchsia', icon: I.building };
    }
    return null;
  }, [tipoFilter, origemFilter, totais]);

  const totalCounts = useMemo(() => {
    const byStatus = {};
    ['all','emitida','paga','vencida','cancelada','erro'].forEach(s => {
      byStatus[s] = s === 'all' ? COBRANCAS.length : COBRANCAS.filter(c => c.status === s).length;
    });
    return byStatus;
  }, []);

  return (
    <div className="h-full bg-stone-50 flex flex-col font-sans" data-screen-label="01 Cobrança">

      <Header
        title="Cobrança"
        breadcrumb={breadcrumb}
        right={<>
          <span className="text-[11px] text-stone-500 tabular-nums">{totais.qtd_aberto} em aberto · sync 09:14</span>
          <button onClick={() => setAiOpen(true)} className="pg-ai-btn" title="Resumir cobranças deste mês — IA">
            <span className="pg-ai-glyph">✦</span>
            <span>Resumir mês</span>
          </button>
          <Btn variant="outline" onClick={() => window.PgGotoRoute && window.PgGotoRoute('payment-gateways')} title="Configurar credenciais Inter, C6, Asaas, BCB Pix Aut. e PesaPal">{I.settings}Gateways</Btn>
          <Btn variant="outline" onClick={() => setRemessaOpen(true)}>{I.upload}Remessa/Retorno</Btn>
          <Btn variant="primary" onClick={() => setNovaOpen(true)}>{I.plus}Nova cobrança</Btn>
        </>}
      />

      {/* FUNIL 5 etapas + chip lateral "Mandato cancelado" se aplicável */}
      <div className="px-6 pt-5">
        <FunnelStrip totais={totais} cobs={COBRANCAS} />
      </div>

      {/* KPIs: 3 fixos + 1 contextual */}
      <div className="px-6 pt-4 grid grid-cols-4 gap-3">
        <KpiCard tone="emerald" label="Pago no mês"  value={brl(totais.valor_pago)}    sub={`${totais.qtd_pago} liquidações · ticket ${brl(totais.valor_pago/Math.max(totais.qtd_pago,1))}`} icon={I.check} />
        <KpiCard tone="rose"    label="Vencido"      value={brl(totais.valor_vencido)} sub={`${totais.qtd_vencido} cobranças · R-mat. cobrança`} icon={I.alert} />
        <KpiCard               label="Em aberto"    value={brl(totais.valor_aberto)}  sub={`${totais.qtd_aberto} cobranças · próximo vencimento ${fmtDateRel('2026-05-20', TODAY)}`} icon={I.receipt} />
        {kpiContextual
          ? <KpiCard tone={kpiContextual.tone} label={kpiContextual.label} value={kpiContextual.value} sub={kpiContextual.sub} icon={kpiContextual.icon} contextual />
          : <KpiCard tone="dark"   label="Próx. janela remessa" value="hoje 18:30" sub="C6 CNAB diário · 14 títulos prontos" icon={I.webhook} />}
      </div>

      {/* FILTROS — linha 1: tabs status + busca + ações · linha 2: chips tipo + dropdowns + chips origem */}
      <div className="px-6 pt-4 pb-2 flex items-center gap-2 flex-wrap">
        <div className="inline-flex bg-stone-100/80 rounded-md p-0.5 border border-stone-200">
          {[
            { id:'all',       label:'Todos' },
            { id:'emitida',   label:'Em aberto' },
            { id:'paga',      label:'Pagas' },
            { id:'vencida',   label:'Vencidas' },
            { id:'cancelada', label:'Canceladas' },
            { id:'erro',      label:'Erro' },
          ].map(t => (
            <button key={t.id} onClick={() => setTabStatus(t.id)} className={cn(
              "h-7 px-3 rounded text-[12px] flex items-center gap-1.5 transition tabular-nums",
              tabStatus === t.id ? "bg-white shadow-sm font-medium text-stone-900" : "text-stone-600 hover:text-stone-800"
            )}>
              <span>{t.label}</span>
              <span className={cn("text-[10px] tabular-nums px-1 rounded", tabStatus === t.id ? "bg-stone-200 text-stone-700" : "text-stone-400")}>
                {totalCounts[t.id]}
              </span>
            </button>
          ))}
        </div>

        <div className="ml-auto relative">
          <span className="absolute left-2 top-1/2 -translate-y-1/2 text-stone-400">{I.search}</span>
          <input ref={buscaRef} value={busca} onChange={e => setBusca(e.target.value)} placeholder="cliente, doc, nosso nº, origem… (/ pra focar)"
            className="h-7 w-[280px] pl-7 pr-7 bg-white border border-stone-300 rounded-md text-[12px] focus:outline-none focus:border-stone-500 focus-visible:ring-2 focus-visible:ring-stone-400" />
          <kbd className="absolute right-2 top-1/2 -translate-y-1/2 text-[9.5px] font-mono text-stone-400 px-1 border border-stone-200 rounded">/</kbd>
        </div>
        <Btn variant="ghost">{I.download}Exportar</Btn>
      </div>

      {/* LINHA 2 dos filtros: chips TIPO + dropdowns GATEWAY/ACCOUNT + chips ORIGEM */}
      <div className="px-6 pb-3 flex items-center gap-2 flex-wrap">
        <div className="text-[10px] uppercase tracking-widest font-medium text-stone-400 mr-1">Tipo</div>
        <div className="inline-flex gap-1">
          {[
            { id:'all',      label:'Todos' },
            { id:'boleto',   label:'Boleto' },
            { id:'pix',      label:'PIX' },
            { id:'pix_recv', label:'PIX Automático' },
            { id:'card',     label:'Cartão' },
          ].map(t => (
            <button key={t.id} onClick={() => setTipoFilter(t.id)} className={cn(
              "h-6 px-2 rounded text-[11px] font-medium border transition",
              tipoFilter === t.id ? "bg-stone-900 text-white border-stone-900" : "bg-white text-stone-700 border-stone-300 hover:bg-stone-50"
            )}>{t.label}</button>
          ))}
        </div>

        <div className="w-px h-5 bg-stone-200 mx-1" />

        <select value={gatewayFilter} onChange={(e) => setGatewayFilter(e.target.value)}
          className="h-6 text-[11.5px] bg-white border border-stone-300 rounded px-2 text-stone-700 focus-visible:ring-2 focus-visible:ring-stone-400">
          <option value="all">Todos gateways</option>
          {Object.values(DRIVERS).filter(d => !d.deprecated).map(d => <option key={d.key} value={d.key}>{d.nome}</option>)}
        </select>

        <select value={accountFilter} onChange={(e) => setAccountFilter(e.target.value)}
          className="h-6 text-[11.5px] bg-white border border-stone-300 rounded px-2 text-stone-700">
          <option value="all">Todas contas destino</option>
          {ACCOUNTS.map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
        </select>

        <div className="w-px h-5 bg-stone-200 mx-1" />

        <div className="text-[10px] uppercase tracking-widest font-medium text-stone-400 mr-1">Origem</div>
        <div className="inline-flex gap-1">
          {[
            { id:'all',                  label:'Todas' },
            { id:'sale',                 label:'Venda' },
            { id:'invoice',              label:'Recorrente' },
            IS_SAAS_BUSINESS && { id:'subscription_license', label:'SaaS Oimpresso' },
          ].filter(Boolean).map(o => (
            <button key={o.id} onClick={() => setOrigemFilter(o.id)} className={cn(
              "h-6 px-2 rounded text-[11px] font-medium border transition",
              origemFilter === o.id ? "bg-stone-700 text-white border-stone-700" : "bg-white text-stone-600 border-stone-200 hover:bg-stone-50"
            )}>{o.label}</button>
          ))}
        </div>
      </div>

      {/* TABELA */}
      <div className="px-6 pb-6 flex-1 overflow-auto">
        <div className="bg-white border border-stone-200 rounded-md overflow-hidden">
          <table className="w-full text-[12.5px]" style={{fontVariantNumeric:'tabular-nums'}}>
            <thead>
              <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/60">
                <th className="pl-5 pr-2 py-2 text-left font-medium w-[100px]">Vencimento</th>
                <th className="px-2 py-2 text-left font-medium">Pagador / Origem</th>
                <th className="px-2 py-2 text-left font-medium w-[110px]">Tipo · Gateway</th>
                <th className="px-2 py-2 text-left font-medium w-[180px]">Conta destino</th>
                <th className="px-2 py-2 text-left font-medium w-[120px]">Nosso nº</th>
                <th className="px-2 py-2 text-right font-medium w-[110px]">Valor</th>
                <th className="px-2 py-2 text-left font-medium w-[110px]">Status</th>
                <th className="pl-2 pr-5 py-2 text-right font-medium w-[100px]"></th>
              </tr>
            </thead>
            <tbody>
              {filtered.length === 0 && <EmptyRow setNovaOpen={setNovaOpen} cause={gatewayFilter !== 'all' ? 'gateway' : 'filter'} />}
              {filtered.map((c, idx) => {
                const acct = ACCOUNTS.find(a => a.id === c.account_id);
                const overdue = c.vencimento < TODAY && c.status === 'emitida';
                const isFocus = idx === focusIdx;
                return (
                  <tr key={c.id} className={cn("border-b border-stone-100 hover:bg-stone-50/60 cursor-pointer", isFocus && "bg-blue-50/40 ring-1 ring-inset ring-blue-300")} onClick={() => setDrawer(c)}>
                    <td className="pl-5 pr-2 py-2.5 text-stone-700">
                      <div className="font-medium">{fmtDate(c.vencimento)}</div>
                      <div className={cn("text-[10.5px]", overdue ? "text-rose-600" : "text-stone-400")}>
                        {overdue ? `${Math.round((new Date(TODAY) - new Date(c.vencimento))/86400000)}d atraso` : fmtDateRel(c.vencimento, TODAY)}
                      </div>
                    </td>
                    <td className="px-2 py-2.5">
                      <div className="font-medium text-stone-900 truncate max-w-[300px]">{c.contato}</div>
                      <div className="flex items-center gap-1.5 mt-0.5">
                        {c.contato_doc && <span className="text-[10.5px] text-stone-400 font-mono">{piiMask(c.contato_doc)}</span>}
                        {c.origem_type && <span className="text-stone-300 text-[10px]">·</span>}
                        {c.origem_type && <OrigemChip tipo={c.origem_type} label={c.origem_label?.replace(/^[^·]+·\s*/, '') || ORIGENS[c.origem_type]?.label} />}
                      </div>
                    </td>
                    <td className="px-2 py-2.5">
                      <GatewayTipoChip gateway={c.gateway} tipo={c.tipo} />
                    </td>
                    <td className="px-2 py-2.5 text-stone-600">
                      <div className="text-[11.5px] truncate">{acct?.name || <span className="text-stone-400">—</span>}</div>
                      {acct?.banco && <div className="text-[10px] text-stone-400">{acct.banco} {acct.agencia ? `· Ag ${acct.agencia}` : ''}</div>}
                    </td>
                    <td className="px-2 py-2.5 font-mono text-[11px] text-stone-600 truncate">
                      {c.nosso_numero || (c.tipo === 'pix_recv' ? <span className="text-stone-400">mandato #{c.id}</span> : c.tipo?.startsWith('pix') ? <span className="text-stone-400">cob_{c.id}</span> : <span className="text-stone-400">—</span>)}
                    </td>
                    <td className="px-2 py-2.5 text-right font-semibold tabular-nums">{brlNoSign(c.valor)}</td>
                    <td className="px-2 py-2.5"><StatusBadge status={c.status} />
                      {c.tipo === 'pix_recv' && c.status === 'emitida' && (
                        <div className="text-[10px] text-violet-700 mt-0.5">mandato ativo</div>
                      )}
                    </td>
                    <td className="pl-2 pr-5 py-2.5 text-right" onClick={(e) => e.stopPropagation()}>
                      <div className="pg-row-actions inline-flex items-center gap-0.5">
                        <button title="Copiar identificador" className="pg-action-btn">{I.copy}</button>
                        {c.tipo === 'boleto' && <button title="Baixar PDF" className="pg-action-btn">{I.download}</button>}
                        {c.tipo?.startsWith('pix') && c.tipo !== 'pix_recv' && <button title="Copiar BR Code" className="pg-action-btn">{I.qrcode}</button>}
                        <button title="Mais ações" className="pg-action-btn">{I.more}</button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {drawer && <DrawerCobranca cob={drawer} onClose={() => setDrawer(null)} />}
      {novaOpen && <SheetNovaCobranca onClose={() => setNovaOpen(false)} />}
      {remessaOpen && <SheetRemessaRetorno onClose={() => setRemessaOpen(false)} />}
      {cheatOpen && <CheatSheet onClose={() => setCheatOpen(false)} />}
      {aiOpen && <AiResumoMes totais={totais} cobs={COBRANCAS} onClose={() => setAiOpen(false)} />}
    </div>
  );
}

// KB-9.75: cheat-sheet overlay (atalho ?)
function CheatSheet({ onClose }) {
  useEffect(() => {
    const onKey = (e) => { if (e.key === 'Escape' || e.key === '?') { e.preventDefault(); onClose(); } };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);
  return (
    <div className="fixed inset-0 z-40 grid place-items-center p-6" onClick={onClose}>
      <div className="absolute inset-0 bg-stone-900/40" />
      <div className="relative w-[460px] bg-white rounded-lg shadow-2xl border border-stone-200 p-5" onClick={e => e.stopPropagation()}>
        <h3 className="text-[14px] font-semibold text-stone-900 mb-3">Atalhos · Cobrança</h3>
        <div className="space-y-1.5 text-[12.5px]">
          {[
            { k: '/', d: 'Focar busca' },
            { k: 'J / ↓', d: 'Próxima linha' },
            { k: 'K / ↑', d: 'Linha anterior' },
            { k: 'Enter', d: 'Abrir cobrança focada' },
            { k: 'Esc', d: 'Fechar drawer/sheet' },
            { k: '?', d: 'Mostrar/ocultar atalhos' },
          ].map(({k,d}) => (
            <div key={k} className="flex items-center justify-between py-1 border-b border-stone-100 last:border-b-0">
              <span className="text-stone-700">{d}</span>
              <kbd className="text-[10.5px] font-mono px-1.5 py-0.5 border border-stone-300 rounded bg-stone-50 text-stone-700">{k}</kbd>
            </div>
          ))}
        </div>
        <button className="mt-4 w-full os-btn" onClick={onClose}>Fechar (Esc)</button>
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────
// Funil 5 etapas + chip lateral mandato cancelado
// ─────────────────────────────────────────────────────────────
function FunnelStrip({ totais, cobs }) {
  const mandatosCancelados = cobs.filter(c => c.tipo === 'pix_recv' && c.status === 'cancelada').length;
  return (
    <div className="border border-stone-200 bg-white rounded-md overflow-hidden">
      <div className="px-3.5 py-1.5 text-[10px] uppercase tracking-widest font-medium text-stone-500 border-b border-stone-100 flex items-center justify-between">
        <span>Funil de cobrança · maio 2026</span>
        {mandatosCancelados > 0 && (
          <span className="inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded border bg-rose-50 text-rose-700 border-rose-200">
            <span className="w-1 h-1 rounded-full bg-rose-500" />{mandatosCancelados} mandato(s) cancelado(s)
          </span>
        )}
      </div>
      <div className="flex">
        {[
          { l:'Em aberto',       v: totais.qtd_aberto,                    vv: brl(totais.valor_aberto), active:true },
          { l:'→ Lembrete',      v: Math.round(totais.qtd_aberto * 0.5), vv:'3d antes do vcto' },
          { l:'→ Cobrança ativa',v: Math.round(totais.qtd_aberto * 0.2), vv:'1d após vcto' },
          { l:'→ Vencidos +5d',  v: totais.qtd_vencido,                   vv: brl(totais.valor_vencido), alert: totais.qtd_vencido > 0 },
          { l:'→ Protesto',      v: 0,                                    vv:'30d+' },
        ].map((s, i) => (
          <div key={i} className={cn(
            "flex-1 px-4 py-3 border-r border-stone-100 last:border-r-0",
            s.active && "bg-blue-50/40",
            s.alert && "bg-rose-50/40"
          )}>
            <div className={cn("text-[10.5px] font-medium", s.alert ? "text-rose-700" : s.active ? "text-blue-700" : "text-stone-500")}>{s.l}</div>
            <div className="text-[18px] font-semibold tabular-nums tracking-tight mt-1">{s.v}</div>
            <div className={cn("text-[10.5px] tabular-nums mt-0.5", s.alert ? "text-rose-600" : "text-stone-400")}>{s.vv}</div>
          </div>
        ))}
      </div>
    </div>
  );
}

function EmptyRow({ setNovaOpen, cause }) {
  return (
    <tr>
      <td colSpan={8} className="py-12 text-center">
        <div className="inline-flex flex-col items-center gap-3">
          <span className="text-stone-400">{I.receipt}</span>
          <div>
            <div className="text-[13px] font-medium text-stone-700">Nenhuma cobrança encontrada</div>
            <div className="text-[11.5px] text-stone-500 mt-0.5">
              {cause === 'gateway' ? 'Talvez este gateway não tenha credencial ativa.' : 'Tente outro filtro ou crie uma nova cobrança.'}
            </div>
          </div>
          <div className="flex gap-2 mt-1">
            {cause === 'gateway' && <Btn variant="outline">{I.settings}Configurar gateway</Btn>}
            <Btn variant="primary" onClick={() => setNovaOpen(true)}>{I.plus}Nova cobrança</Btn>
          </div>
        </div>
      </td>
    </tr>
  );
}

// ─────────────────────────────────────────────────────────────
// DrawerCobranca — render condicional por tipo
// ─────────────────────────────────────────────────────────────
function DrawerCobranca({ cob, onClose }) {
  const drv = DRIVERS[cob.gateway];
  const acct = ACCOUNTS.find(a => a.id === cob.account_id);
  const refundDisponivel = cob.status === 'paga' && drv && (drv.key === 'asaas' || drv.key === 'inter' || drv.key === 'pesapal');
  const refundLabel = drv?.key === 'bcb_pix' ? 'Não disponível pra PIX Automático' : drv?.key === 'inter' ? 'Suporte parcial (Inter)' : null;

  return (
    <div className="fixed inset-0 z-30 flex justify-end" onClick={onClose}>
      <div className="absolute inset-0 bg-stone-900/20" />
      <div className="relative w-[520px] bg-white h-full shadow-xl border-l border-stone-200 flex flex-col" onClick={e => e.stopPropagation()}>

        {/* HEADER drawer */}
        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-2">
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2 text-[10px] uppercase tracking-widest text-stone-500 font-medium">
              <span>Cobrança #{cob.id}</span>
              <GatewayTipoChip gateway={cob.gateway} tipo={cob.tipo} />
            </div>
            <div className="text-[15px] font-semibold mt-1 truncate">{cob.contato}</div>
            <div className="text-[11px] text-stone-500 font-mono mt-0.5">{piiMask(cob.contato_doc)}</div>
          </div>
          <StatusBadge status={cob.status} />
          <button onClick={onClose} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500">{I.x}</button>
        </div>

        <div className="flex-1 overflow-auto">
          {/* Origem (se houver) */}
          {cob.origem_type && (
            <div className="px-5 py-2.5 border-b border-stone-100 bg-stone-50/40 flex items-center gap-2">
              <div className="text-[10px] uppercase tracking-widest font-medium text-stone-500">Origem</div>
              <OrigemChip tipo={cob.origem_type} label={cob.origem_label || ORIGENS[cob.origem_type]?.label} onClick={() => {}} />
              {cob.origem_id && <span className="text-[11px] text-stone-500 font-mono ml-auto">#{cob.origem_id}</span>}
            </div>
          )}

          {/* Dados principais */}
          <div className="px-5 py-4 grid grid-cols-2 gap-x-5 gap-y-3 text-[12.5px]">
            <div>
              <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Vencimento</div>
              <div className="font-medium mt-0.5">{fmtDate(cob.vencimento)} <span className="text-[10.5px] text-stone-400">· {fmtDateRel(cob.vencimento, TODAY)}</span></div>
            </div>
            <div>
              <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Valor</div>
              <div className="font-semibold mt-0.5 tabular-nums">{brl(cob.valor)}</div>
            </div>
            <div className="col-span-2">
              <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Conta destino</div>
              <div className="font-medium mt-0.5 inline-flex items-center gap-2">
                <span className={cn("w-2 h-2 rounded-sm", drv?.dot)} />
                {acct?.name || '—'}
              </div>
              {acct?.banco && <div className="text-[10.5px] text-stone-400 mt-0.5">{acct.banco}{acct.agencia ? ` · Ag ${acct.agencia} · Cc ${acct.conta}` : acct.conta ? ` · ${acct.conta}` : ''}</div>}
            </div>
            <div className="col-span-2">
              <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Gateway</div>
              <div className="font-medium mt-0.5">{drv?.nome} <span className="text-[10.5px] text-stone-400 ml-1">{cob.gateway === 'inter' || cob.gateway === 'bcb_pix' || cob.gateway === 'asaas' ? '· production' : ''}</span></div>
            </div>
          </div>

          {/* Render condicional por tipo */}
          {cob.tipo === 'boleto' && <SectionBoleto cob={cob} />}
          {(cob.tipo === 'pix_cob' || cob.tipo === 'pix_cobv') && <SectionPix cob={cob} />}
          {cob.tipo === 'pix_recv' && <SectionPixRecv cob={cob} />}
          {cob.tipo === 'card' && <SectionCard cob={cob} />}

          {/* Erro */}
          {cob.status === 'erro' && (
            <div className="px-5 py-3 border-t border-stone-200">
              <div className="text-[10px] uppercase tracking-widest text-rose-700 font-medium mb-2">Erro do gateway</div>
              <div className="bg-rose-50 border border-rose-200 rounded px-3 py-2 text-[11.5px] text-rose-900 font-mono">
                {cob.erro_msg}
              </div>
              <Btn variant="outline" size="xs" className="mt-2">{I.refresh}Tentar reemitir</Btn>
            </div>
          )}

          {/* Timeline */}
          <div className="px-5 py-4 border-t border-stone-200">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 mb-2 font-medium">Linha do tempo</div>
            <Timeline cob={cob} />
          </div>
        </div>

        {/* Footer drawer */}
        <div className="border-t border-stone-200 p-3 flex items-center gap-2 bg-white">
          {cob.tipo === 'boleto' && <Btn variant="outline">{I.download}Baixar PDF</Btn>}
          {cob.tipo === 'boleto' && <Btn variant="outline">{I.link}Link 2ª via</Btn>}
          {cob.tipo?.startsWith('pix') && cob.tipo !== 'pix_recv' && <Btn variant="outline">{I.copy}Copiar BR Code</Btn>}
          <div className="flex-1" />
          {refundDisponivel && <Btn variant="outline" className="text-amber-700 hover:bg-amber-50">{I.rotate}Estornar</Btn>}
          {refundLabel && cob.status === 'paga' && <span className="text-[10.5px] text-stone-400" title={refundLabel}>refund indisp.</span>}
          {!['paga','cancelada','erro'].includes(cob.status) && <Btn variant="danger">{I.x}Cancelar</Btn>}
        </div>
      </div>
    </div>
  );
}

function SectionBoleto({ cob }) {
  return (
    <div className="px-5 py-4 border-t border-stone-200 space-y-3 text-[12.5px]">
      <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Boleto</div>
      <div>
        <div className="text-[10px] uppercase tracking-widest text-stone-500 mb-1 font-medium">Nosso número</div>
        <div className="font-mono">{cob.nosso_numero}</div>
      </div>
      {cob.linha_digitavel && (
        <div>
          <div className="text-[10px] uppercase tracking-widest text-stone-500 mb-1 font-medium">Linha digitável</div>
          <div className="flex items-center gap-2 bg-stone-50 border border-stone-200 rounded px-2.5 py-2">
            <div className="font-mono text-[11.5px] flex-1 break-all">{cob.linha_digitavel}</div>
            <Btn variant="outline" size="xs">{I.copy}</Btn>
          </div>
        </div>
      )}
      {cob.codigo_barras && (
        <div>
          <div className="text-[10px] uppercase tracking-widest text-stone-500 mb-1 font-medium">Código de barras</div>
          <div className="font-mono text-[10.5px] text-stone-500 break-all">{cob.codigo_barras}</div>
        </div>
      )}
    </div>
  );
}

function SectionPix({ cob }) {
  return (
    <div className="px-5 py-4 border-t border-stone-200 space-y-3 text-[12.5px]">
      <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">PIX {cob.tipo === 'pix_cob' ? 'cob (imediata)' : 'cobv (com vencimento)'}</div>
      <div className="flex gap-3">
        {/* QR placeholder */}
        <div className="w-[140px] h-[140px] bg-white border border-stone-300 rounded-md p-2 grid place-items-center shrink-0">
          <FakeQR />
        </div>
        <div className="flex-1 min-w-0">
          <div className="text-[10px] uppercase tracking-widest text-stone-500 mb-1 font-medium">BR Code copia-e-cola</div>
          <div className="bg-stone-50 border border-stone-200 rounded px-2.5 py-2">
            <div className="font-mono text-[10.5px] text-stone-700 break-all">{cob.pix_emv}</div>
          </div>
          <Btn variant="outline" size="xs" className="mt-2">{I.copy}Copiar BR Code</Btn>
        </div>
      </div>
    </div>
  );
}

function SectionPixRecv({ cob }) {
  return (
    <div className="px-5 py-4 border-t border-stone-200 space-y-3 text-[12.5px]">
      <div className="flex items-center gap-2">
        <div className="text-[10px] uppercase tracking-widest text-violet-700 font-medium">PIX Automático · mandato BCB</div>
        <span className="inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded border bg-emerald-50 text-emerald-700 border-emerald-200">
          <span className="w-1 h-1 rounded-full bg-emerald-500" />mandato ativo
        </span>
      </div>
      <div className="grid grid-cols-2 gap-3">
        <div>
          <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">CNPJ recebedor</div>
          <div className="font-mono mt-0.5">12.345.678/0001-90</div>
          <div className="text-[10px] text-stone-400 mt-0.5">homologado BCB · OK</div>
        </div>
        <div>
          <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Ciclo</div>
          <div className="font-medium mt-0.5">{cob.mandato_ciclo || 'mensal'}</div>
        </div>
        <div>
          <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Mandato desde</div>
          <div className="font-medium mt-0.5">{fmtDate(cob.mandato_inicio || cob.emitida_em?.slice(0,10))}</div>
        </div>
        <div>
          <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Próxima cobrança</div>
          <div className="font-medium mt-0.5">{fmtDate(cob.mandato_proximo || cob.vencimento)}</div>
        </div>
      </div>
      <div className="bg-violet-50 border border-violet-200 rounded p-2.5 text-[11px] text-violet-900">
        <div className="flex gap-2"><span>{I.shield}</span><div>Resolução BCB 380/2024 · pagador pode cancelar mandato a qualquer momento via app do banco · gera evento <span className="font-mono">CobrancaCancelada</span>.</div></div>
      </div>
    </div>
  );
}

function SectionCard({ cob }) {
  return (
    <div className="px-5 py-4 border-t border-stone-200 space-y-3 text-[12.5px]">
      <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Cartão de crédito</div>
      <div className="bg-stone-50 border border-stone-200 rounded p-3 flex items-center gap-3">
        <div className="w-10 h-7 rounded bg-stone-900 grid place-items-center text-white text-[10px] font-bold uppercase">
          {cob.card_brand || 'visa'}
        </div>
        <div className="flex-1">
          <div className="font-mono">•••• •••• •••• {cob.card_last4 || '4242'}</div>
          <div className="text-[10.5px] text-stone-400 mt-0.5">tokenizado no provedor · sem PAN local</div>
        </div>
        {cob.card_3ds && (
          <span className="inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded border bg-emerald-50 text-emerald-700 border-emerald-200">
            {I.shield}3DS autenticado
          </span>
        )}
      </div>
    </div>
  );
}

function FakeQR() {
  // Pattern fake QR — grid 21x21
  const cells = [];
  for (let i = 0; i < 21*21; i++) {
    const x = i % 21, y = Math.floor(i/21);
    // 3 finder squares
    const isFinder = (x < 7 && y < 7) || (x > 13 && y < 7) || (x < 7 && y > 13);
    const finderOn = isFinder && (
      (x === 0 || x === 6 || y === 0 || y === 6) ||
      (x >= 2 && x <= 4 && y >= 2 && y <= 4)
    );
    const seed = (x * 31 + y * 17 + 7) % 11;
    const on = finderOn || (!isFinder && seed < 4);
    cells.push(<div key={i} className={cn("w-[5px] h-[5px]", on ? "bg-stone-900" : "bg-transparent")} />);
  }
  return <div className="grid" style={{gridTemplateColumns:'repeat(21, 5px)', gap:0}}>{cells}</div>;
}

function Timeline({ cob }) {
  const evs = [
    { ts: cob.emitida_em, label:'Cobrança emitida', actor:`gateway ${cob.gateway} · ${cob.tipo}` },
    cob.status === 'paga' && { ts: cob.paga_em, label:'Pagamento confirmado', actor:'webhook · liquidação automática', meta:'AccountTransaction #4892' },
    cob.status === 'vencida' && { ts: cob.vencimento, label:`Venceu sem pagamento`, actor:'evento CobrancaVencida · smart retry agendado' },
    cob.status === 'cancelada' && { ts: cob.cancelada_em, label:'Cobrança cancelada', actor:'Eliana Souza', meta: cob.cancelamento_motivo },
    cob.status === 'erro' && { ts: cob.emitida_em, label:'Erro do gateway', actor: cob.erro_msg, severity: 'rose' },
  ].filter(Boolean);
  return (
    <div className="space-y-2.5">
      {evs.map((e, i) => (
        <div key={i} className="flex gap-3 text-[12px]">
          <div className="w-[64px] text-stone-500 tabular-nums whitespace-nowrap pt-0.5 text-[10.5px]">{fmtDate(e.ts?.slice(0,10))} {e.ts?.slice(11,16)}</div>
          <div className="w-2 mt-1.5 relative">
            <div className={cn("absolute inset-0 w-1.5 h-1.5 rounded-full top-0", e.severity === 'rose' ? "bg-rose-500" : "bg-stone-900")} />
            {i < evs.length - 1 && <div className="absolute left-[3px] top-2 w-px h-7 bg-stone-200" />}
          </div>
          <div className="flex-1 min-w-0">
            <div className={cn("font-medium", e.severity === 'rose' ? "text-rose-700" : "text-stone-900")}>{e.label}</div>
            <div className="text-stone-500 text-[11px] mt-0.5">{e.actor}{e.meta && <span className="ml-1 text-stone-400">· {e.meta}</span>}</div>
          </div>
        </div>
      ))}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────
// SheetNovaCobranca — 4-step wizard
// ─────────────────────────────────────────────────────────────
function SheetNovaCobranca({ onClose }) {
  const [step, setStep] = useState(1);
  const [tipo, setTipo] = useState(null);
  const [contato, setContato] = useState('');
  const [valor, setValor] = useState('');
  const [vencimento, setVencimento] = useState('2026-05-26');
  const [account, setAccount] = useState(12);

  const drivers = useMemo(() => {
    if (!tipo) return [];
    return Object.values(DRIVERS).filter(d => !d.deprecated && d.tipos.includes(tipo));
  }, [tipo]);

  const next = () => setStep(s => Math.min(4, s+1));
  const prev = () => setStep(s => Math.max(1, s-1));
  const canNext = step === 1 ? !!tipo : step === 2 ? !!contato : step === 3 ? !!valor && !!vencimento : true;

  return (
    <div className="fixed inset-0 z-30 flex justify-end" onClick={onClose}>
      <div className="absolute inset-0 bg-stone-900/30" />
      <div className="relative w-[640px] bg-white h-full shadow-xl border-l border-stone-200 flex flex-col" onClick={e => e.stopPropagation()}>

        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
          <div className="flex-1">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Nova cobrança</div>
            <div className="text-[15px] font-semibold mt-0.5">passo {step} de 4</div>
          </div>
          <button onClick={onClose} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500">{I.x}</button>
        </div>

        {/* Step indicator */}
        <div className="px-5 py-3 border-b border-stone-200 bg-stone-50/40 flex items-center gap-2 text-[11px]">
          {['Tipo','Pagador','Valores','Revisar'].map((s, i) => (
            <React.Fragment key={i}>
              <div className={cn("flex items-center gap-1.5", step === i+1 ? "text-stone-900 font-semibold" : step > i+1 ? "text-emerald-700" : "text-stone-400")}>
                <span className={cn("w-5 h-5 rounded-full grid place-items-center text-[10px] font-bold",
                  step === i+1 ? "bg-stone-900 text-white" :
                  step > i+1 ? "bg-emerald-100 text-emerald-700" :
                  "bg-stone-200 text-stone-500"
                )}>
                  {step > i+1 ? '✓' : i+1}
                </span>
                {s}
              </div>
              {i < 3 && <span className="text-stone-300">{I.chevR}</span>}
            </React.Fragment>
          ))}
        </div>

        <div className="flex-1 overflow-auto p-5">
          {step === 1 && (
            <div className="space-y-3">
              <div className="text-[11px] text-stone-500 mb-2">Escolha o tipo de cobrança. Opções desabilitadas significam que não há gateway ativo configurado em Settings.</div>
              <div className="grid grid-cols-2 gap-2.5">
                {[
                  { id:'boleto',   label:'Boleto',         desc:'Inter · C6 · Asaas',          icon:I.receipt,    available:true },
                  { id:'pix_cob',  label:'PIX',            desc:'Inter · Asaas · imediato',    icon:I.qrcode,     available:true },
                  { id:'pix_recv', label:'PIX Automático', desc:'BCB · mandato recorrente',    icon:I.zap,        available:true, highlight:true },
                  { id:'card',     label:'Cartão',         desc:'Asaas · 3DS · 1-12x',         icon:I.creditcard, available:true },
                ].map(t => (
                  <button key={t.id} onClick={() => t.available && setTipo(t.id)} disabled={!t.available} className={cn(
                    "text-left rounded-md border p-3 transition disabled:opacity-50 disabled:cursor-not-allowed",
                    tipo === t.id ? "border-stone-900 ring-2 ring-stone-900/10 bg-stone-50" : "border-stone-200 hover:border-stone-400 hover:bg-stone-50",
                    t.highlight && tipo !== t.id && "border-violet-200 bg-violet-50/40"
                  )}>
                    <div className="flex items-center gap-2">
                      <span className="text-stone-700">{t.icon}</span>
                      <div className="text-[13px] font-semibold">{t.label}</div>
                      {t.highlight && <span className="text-[9px] uppercase tracking-widest font-medium text-violet-700 ml-auto">novo</span>}
                    </div>
                    <div className="text-[11px] text-stone-500 mt-1.5">{t.desc}</div>
                  </button>
                ))}
              </div>
            </div>
          )}

          {step === 2 && (
            <div className="space-y-3">
              <Field label="Pagador (contato)">
                <input value={contato} onChange={e => setContato(e.target.value)} placeholder="busca por nome ou CPF/CNPJ..." autoFocus
                  className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] focus:outline-none focus:border-stone-500" />
              </Field>
              <div className="text-[11px] text-stone-500">Sugestões recentes:</div>
              <div className="grid gap-1.5">
                {['Padaria Pão Quente LTDA','Distrib. Norte Mat. Elétrico','Studio Alfa Design','Mercado União'].map(s => (
                  <button key={s} onClick={() => setContato(s)} className={cn(
                    "text-left h-9 px-3 rounded border text-[12.5px] transition",
                    contato === s ? "border-stone-900 bg-stone-50 text-stone-900" : "border-stone-200 text-stone-600 hover:bg-stone-50"
                  )}>{s}</button>
                ))}
              </div>
            </div>
          )}

          {step === 3 && (
            <div className="space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <Field label="Valor"><input value={valor} onChange={e=>setValor(e.target.value)} placeholder="0,00" className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono tabular-nums" /></Field>
                <Field label="Vencimento"><input type="date" value={vencimento} onChange={e=>setVencimento(e.target.value)} className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px]" /></Field>
              </div>
              <Field label="Conta destino">
                <select value={account} onChange={e=>setAccount(parseInt(e.target.value))} className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px]">
                  {ACCOUNTS.filter(a => a.driver).map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
                </select>
              </Field>
              {tipo === 'pix_recv' && (
                <div className="bg-violet-50 border border-violet-200 rounded p-3 space-y-2.5">
                  <div className="text-[10px] uppercase tracking-widest font-medium text-violet-700">Configuração do mandato PIX Automático</div>
                  <div className="grid grid-cols-2 gap-3">
                    <Field label="Ciclo"><select className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px]"><option>mensal</option><option>trimestral</option><option>anual</option></select></Field>
                    <Field label="Duração (ciclos)"><input placeholder="12" className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono" /></Field>
                  </div>
                  <div className="text-[10.5px] text-violet-900">Mandato precisa ser autorizado pelo pagador no app do banco antes da 1ª cobrança.</div>
                </div>
              )}
              <details className="text-[12px]">
                <summary className="cursor-pointer text-stone-600 hover:text-stone-900 select-none">+ Multa, juros, desconto (opcional)</summary>
                <div className="mt-2 grid grid-cols-3 gap-2 pl-3 border-l-2 border-stone-200">
                  <Field label="Multa %"><input placeholder="2,00" className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono" /></Field>
                  <Field label="Juros %/dia"><input placeholder="0,033" className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono" /></Field>
                  <Field label="Desconto até"><input type="date" className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px]" /></Field>
                </div>
              </details>
            </div>
          )}

          {step === 4 && (
            <div className="space-y-3">
              <div className="text-[10px] uppercase tracking-widest font-medium text-stone-500">Revisar</div>
              <div className="border border-stone-200 rounded-md divide-y divide-stone-100 text-[12.5px]">
                <Row k="Tipo" v={<GatewayTipoChip gateway={drivers[0]?.key || 'inter'} tipo={tipo} />} />
                <Row k="Pagador" v={contato || '—'} />
                <Row k="Valor" v={<span className="font-semibold tabular-nums">{valor ? brl(parseFloat(valor)) : '—'}</span>} />
                <Row k="Vencimento" v={fmtDate(vencimento)} />
                <Row k="Conta destino" v={ACCOUNTS.find(a => a.id === account)?.name} />
                <Row k="Driver" v={drivers[0]?.nome || 'auto'} />
              </div>
              <div className="bg-stone-50 border border-stone-200 rounded p-3 text-[11px] text-stone-700">
                Ao confirmar, dispara <span className="font-mono">PaymentGateway::emitir{tipo === 'pix_recv' ? 'PixAutomatico' : tipo === 'card' ? 'Cartao' : tipo?.startsWith('pix') ? 'Pix' : 'Boleto'}()</span>. Idempotency key gerada automaticamente.
              </div>
            </div>
          )}
        </div>

        <div className="border-t border-stone-200 p-3 flex items-center gap-2 bg-stone-50/60">
          {step > 1 && <Btn variant="outline" onClick={prev}>{I.arrowL}Voltar</Btn>}
          <div className="flex-1" />
          <Btn variant="outline" onClick={onClose}>Cancelar</Btn>
          {step < 4 && <Btn variant="primary" onClick={next} disabled={!canNext}>Avançar{I.arrowR}</Btn>}
          {step === 4 && <Btn variant="primary" onClick={onClose}>{I.check}Emitir cobrança</Btn>}
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

function Row({ k, v }) {
  return (
    <div className="px-3 py-2 flex items-center gap-3">
      <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium w-[120px]">{k}</div>
      <div className="flex-1 text-stone-800">{v}</div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────
// SheetRemessaRetorno — mantém canon (C6 CNAB)
// ─────────────────────────────────────────────────────────────
function SheetRemessaRetorno({ onClose }) {
  const remessas = [
    { id:'r-008', tipo:'remessa', filename:'REM_C6_20260519_001.REM',  ts:'2026-05-19T18:00:00', qtd:14, total:18420.00, status:'enviada' },
    { id:'r-007', tipo:'retorno', filename:'RET_C6_20260518.RET',      ts:'2026-05-18T22:18:00', qtd:8,  total:9420.00, status:'processado' },
    { id:'r-006', tipo:'remessa', filename:'REM_C6_20260517_002.REM',  ts:'2026-05-17T17:30:00', qtd:6,  total:4220.00, status:'enviada' },
  ];
  return (
    <div className="fixed inset-0 z-30 flex justify-end" onClick={onClose}>
      <div className="absolute inset-0 bg-stone-900/30" />
      <div className="relative w-[560px] bg-white h-full shadow-xl border-l border-stone-200 flex flex-col" onClick={e => e.stopPropagation()}>
        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-2">
          <div className="flex-1">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Remessa &amp; Retorno · CNAB 240</div>
            <div className="text-[15px] font-semibold mt-0.5">C6 Bank · Operacional</div>
          </div>
          <button onClick={onClose} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500">{I.x}</button>
        </div>
        <div className="px-5 py-3 border-b border-stone-200 grid grid-cols-2 gap-2">
          <Btn variant="primary">{I.download}Gerar remessa do dia</Btn>
          <Btn variant="outline">{I.upload}Importar arquivo retorno</Btn>
        </div>
        <div className="flex-1 overflow-auto">
          {remessas.map(r => (
            <div key={r.id} className="px-5 py-3 border-b border-stone-100 hover:bg-stone-50/60 flex items-center gap-3">
              <span className={cn("w-7 h-7 rounded inline-grid place-items-center",
                r.tipo === 'remessa' ? 'bg-blue-50 text-blue-700' : 'bg-emerald-50 text-emerald-700')}>
                {r.tipo === 'remessa' ? I.upload : I.download}
              </span>
              <div className="flex-1 min-w-0">
                <div className="text-[12.5px] font-medium font-mono truncate">{r.filename}</div>
                <div className="text-[10.5px] text-stone-500 tabular-nums mt-0.5">
                  {fmtDate(r.ts.slice(0,10))} {r.ts.slice(11,16)} · {r.qtd} títulos · {brl(r.total)}
                </div>
              </div>
              <Btn variant="ghost" size="xs">{I.download}</Btn>
            </div>
          ))}
        </div>
        <div className="border-t border-stone-200 px-5 py-3 bg-amber-50/60 text-[11px] text-amber-900">
          <div className="flex gap-2"><span>{I.alert}</span><div><strong>C6:</strong> único driver via CNAB. Inter/Asaas/BCB usam API direta (webhook).</div></div>
        </div>
      </div>
    </div>
  );
}

// KB-9.75: AI panel ✦ (canon Vendas/Index PR #1064)
function AiResumoMes({ totais, cobs, onClose }) {
  useEffect(() => {
    const onKey = (e) => { if (e.key === 'Escape') { e.preventDefault(); onClose(); } };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  const inadimplencia = totais.qtd_aberto > 0 ? (totais.qtd_vencido / (totais.qtd_aberto + totais.qtd_vencido) * 100).toFixed(1) : '0';
  const drv = window.PG_DRIVERS;
  const porGateway = cobs.reduce((acc, c) => { acc[c.gateway] = (acc[c.gateway] || 0) + c.valor; return acc; }, {});
  const topGw = Object.entries(porGateway).sort((a,b) => b[1] - a[1])[0];

  return (
    <div className="fixed inset-0 z-40 flex justify-end" onClick={onClose}>
      <div className="absolute inset-0 bg-stone-900/30" />
      <div className="relative w-[480px] bg-white h-full shadow-xl border-l border-stone-200 flex flex-col" onClick={e => e.stopPropagation()}>
        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
          <span style={{ fontSize: 20, color: 'oklch(0.50 0.18 295)' }}>✦</span>
          <div className="flex-1">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">IA · Resumo executivo</div>
            <div className="text-[15px] font-semibold mt-0.5">Cobrança · maio 2026</div>
          </div>
          <button onClick={onClose} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500" title="Fechar (Esc)">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
        </div>

        <div className="flex-1 overflow-auto px-5 py-4 space-y-4 text-[12.5px]">
          <section>
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-1.5">Panorâmica</div>
            <p className="text-stone-700 leading-relaxed">
              {totais.qtd_pago} cobranças liquidadas em maio totalizando <strong>{brl(totais.valor_pago)}</strong>.
              {totais.qtd_aberto > 0 && <> Em aberto: <strong>{brl(totais.valor_aberto)}</strong> ({totais.qtd_aberto} títulos).</>}
            </p>
          </section>

          {totais.qtd_vencido > 0 && (
            <section className="bg-rose-50 border border-rose-200 rounded-md p-3">
              <div className="text-[10px] uppercase tracking-widest text-rose-700 font-medium mb-1.5">⚠ Atenção</div>
              <p className="text-rose-900 leading-relaxed">
                {totais.qtd_vencido} cobranças vencidas — <strong>{brl(totais.valor_vencido)}</strong>. Taxa de inadimplência: {inadimplencia}%.
              </p>
              <button className="mt-2 text-[11.5px] text-rose-700 hover:underline font-medium">Ver lista de vencidos →</button>
            </section>
          )}

          <section>
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-1.5">Distribuição por gateway</div>
            <div className="space-y-1.5">
              {Object.entries(porGateway).sort((a,b) => b[1] - a[1]).map(([gw, vl]) => {
                const pct = (vl / Object.values(porGateway).reduce((s,v)=>s+v,0) * 100).toFixed(0);
                const d = drv[gw];
                return (
                  <div key={gw} className="flex items-center gap-2">
                    <span className={cn("w-4 h-4 rounded-sm grid place-items-center text-white text-[8.5px] font-bold", d.dot)}>{d.sigla}</span>
                    <span className="flex-1 text-stone-700">{d.nome}</span>
                    <span className="tabular-nums text-stone-900 font-medium">{brl(vl)}</span>
                    <span className="text-[10.5px] text-stone-400 tabular-nums w-8 text-right">{pct}%</span>
                  </div>
                );
              })}
            </div>
          </section>

          <section>
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-1.5">Insights</div>
            <ul className="space-y-1.5 text-stone-700">
              {topGw && <li>• <strong>{drv[topGw[0]].nome}</strong> concentra a maior parte ({brl(topGw[1])})</li>}
              <li>• PIX Automático BCB ativo em {cobs.filter(c => c.tipo === 'pix_recv').length} mandatos recorrentes</li>
              <li>• Tique médio liquidação: {brl(totais.valor_pago / Math.max(totais.qtd_pago, 1))}</li>
            </ul>
          </section>
        </div>

        <div className="border-t border-stone-200 p-3 bg-stone-50/60 flex items-center gap-2">
          <span className="text-[10.5px] text-stone-500">IA · gerado há 12s</span>
          <div className="flex-1" />
          <Btn variant="outline" size="sm">Copiar resumo</Btn>
          <Btn variant="outline" size="sm">Atualizar</Btn>
        </div>
      </div>
    </div>
  );
}

window.PG_CobrancaPage = CobrancaPage;
})();
