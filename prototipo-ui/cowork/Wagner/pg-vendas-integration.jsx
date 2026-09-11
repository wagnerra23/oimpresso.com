/* @ts-nocheck */
/* eslint-disable */
// pg-vendas-integration.jsx — integração do botão "Emitir cobrança" no drawer
// Vendas REAL (vendas-page.jsx). REFATOR F1.5 P0:
//   - Drawer lateral .os-drawer-back canon (não modal centralizado)
//   - Tailwind classes herdando pg-shell-scope (não inline styles)
//   - ESC + focus trap + scroll lock
//   - Erro: chip mostra 1ª linha do erro_msg truncada
//   - Mock por hash com pesos realistas (paga 50%/pending 30%/none 15%/overdue 4%/error 1%)

(() => {
const { useState, useMemo, useEffect, useRef } = React;

// ─── Mock pesado realista (5% overdue, 1% erro) ───
function getCobrancaState(venda) {
  if (!venda) return { kind: 'none' };

  if (venda.status === 'paga') {
    return {
      kind: 'paid',
      cob: { id: 1830 + (venda.id % 30), valor: venda.totalNum, tipo: 'pix_cob', gateway: 'asaas',
             emitida_em:'2026-05-15T14:35:00', paga_em:'2026-05-15T14:36:18' },
    };
  }

  // Hash distribuído com pesos realistas: pending 60%, none 25%, overdue 12%, error 3%
  const h = String(venda.id || '').split('').reduce((s, c) => s + c.charCodeAt(0), 0);
  const r = h % 100;
  if (r < 60) return {
    kind: 'pending',
    cob: { id: 1841, valor: venda.totalNum, tipo:'boleto', gateway:'inter',
           vencimento:'2026-05-22', emitida_em:'2026-05-19T09:14:00' },
  };
  if (r < 85) return { kind: 'none' };
  if (r < 97) return {
    kind: 'overdue',
    cob: { id: 1829, valor: venda.totalNum, tipo:'boleto', gateway:'inter',
           vencimento:'2026-05-12', emitida_em:'2026-05-01T08:30:00' },
  };
  return {
    kind: 'error',
    cob: { id: 1824, valor: venda.totalNum, tipo:'boleto', gateway:'c6',
           erro_msg:'C6 sandbox indisponível · timeout (após 30s)' },
  };
}

const fmtDateBR = (iso) => {
  if (!iso) return '';
  const [y,m,d] = iso.split('-');
  return `${d}/${m}/${y.slice(2)}`;
};
const daysFrom = (iso, today='2026-05-19') => {
  if (!iso) return 0;
  const [yt,mt,dt] = today.split('-').map(Number);
  const [y,m,d] = iso.split('-').map(Number);
  return Math.round((new Date(y,m-1,d) - new Date(yt,mt-1,dt)) / 86400000);
};

// ─── Btn principal renderizado no footer do drawer Vendas ───
function PgEmitirCobranca({ venda }) {
  const [drawerOpen, setDrawerOpen] = useState(false);
  const state = useMemo(() => getCobrancaState(venda), [venda?.id, venda?.status]);

  const goToCobranca = () => setDrawerOpen(true);

  return (
    <>
      {state.kind === 'none' && (
        <button className="os-btn primary" onClick={() => setDrawerOpen(true)}
          title="Emitir boleto, PIX ou cartão direto desta venda · ADR 0144 PaymentGateway">
          <span style={{ fontSize: 11 }}>＋</span>Emitir cobrança
        </button>
      )}

      {state.kind === 'paid' && (
        <button className="vd-cob-chip vd-cob-chip-paid" onClick={goToCobranca}
          title={`Cobrança #${state.cob.id} paga em ${fmtDateBR(state.cob.paga_em.slice(0,10))} ${state.cob.paga_em.slice(11,16)} via ${state.cob.gateway}`}>
          <span className="vd-cob-dot vd-cob-dot-paid" />
          Cobrança #{state.cob.id} paga
        </button>
      )}

      {state.kind === 'pending' && (
        <button className="vd-cob-chip vd-cob-chip-pending" onClick={goToCobranca}
          title={`Cobrança #${state.cob.id} emitida · vence ${fmtDateBR(state.cob.vencimento)} via ${state.cob.gateway}`}>
          <span className="vd-cob-dot vd-cob-dot-pending" />
          Cobrança #{state.cob.id} · vence {fmtDateBR(state.cob.vencimento)}
        </button>
      )}

      {state.kind === 'overdue' && (
        <button className="vd-cob-chip vd-cob-chip-overdue" onClick={goToCobranca}
          title={`Cobrança #${state.cob.id} vencida há ${-daysFrom(state.cob.vencimento)}d`}>
          <span className="vd-cob-dot vd-cob-dot-overdue" />
          Cobrança #{state.cob.id} vencida · {-daysFrom(state.cob.vencimento)}d
        </button>
      )}

      {state.kind === 'error' && (
        <button className="vd-cob-chip vd-cob-chip-error" onClick={() => setDrawerOpen(true)}
          title={state.cob.erro_msg}>
          <span className="vd-cob-dot vd-cob-dot-error" />
          Cobrança erro · {state.cob.erro_msg.slice(0, 32)}{state.cob.erro_msg.length > 32 ? '…' : ''}
        </button>
      )}

      {drawerOpen && (
        <window.PG_CobrancaDrawer venda={venda} state={state} onClose={() => setDrawerOpen(false)} />
      )}
    </>
  );
}

// ─── Drawer lateral canon .os-drawer (não modal centralizado) ───
// Reusa shadcn classes pg-shell-scope + ESC + focus trap + scroll lock
function CobrancaDrawer({ venda, state, onClose }) {
  const drawerRef = useRef(null);
  const isView = state.kind === 'paid' || state.kind === 'pending' || state.kind === 'overdue';
  const [tipo, setTipo] = useState(isView ? state.cob.tipo : 'boleto');
  const [conta, setConta] = useState(12);
  const [venc, setVenc] = useState('2026-05-26');

  // ESC + scroll lock + focus trap (WCAG 2.1)
  useEffect(() => {
    const onKey = (e) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    // Focus first focusable in drawer
    setTimeout(() => {
      const focusable = drawerRef.current?.querySelector('button, input, select, [tabindex="0"]');
      focusable?.focus();
    }, 100);
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = prev;
    };
  }, [onClose]);

  const drv = window.PG_DRIVERS;
  const tps = window.PG_TIPOS;
  const accounts = window.PG_ACCOUNTS || [];

  return (
    <div className="os-drawer-back" onClick={onClose}>
      <aside className="os-drawer pg-shell-scope" ref={drawerRef}
        onClick={e => e.stopPropagation()}
        style={{ width: 480, fontFamily: 'var(--font-sans)' }}>

        <header className="os-drawer-head">
          <div className="os-drawer-head-l">
            <span className="os-drawer-id">
              {isView ? `Cobrança #${state.cob.id}` : 'Nova cobrança'}
            </span>
            <h2 style={{ fontSize: 16 }}>{venda.client}</h2>
            <p>Venda #{venda.id} · {(venda.totalNum || 0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'})}</p>
          </div>
          <div className="os-drawer-head-r">
            <button onClick={onClose}
              className="text-stone-500 hover:text-stone-900 hover:bg-stone-100 rounded w-7 h-7 inline-grid place-items-center"
              title="Fechar (ESC)">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
                <path d="M18 6 6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>
        </header>

        <div className="os-drawer-body" style={{ padding: 0 }}>

          {/* Resumo herdado da venda */}
          <div className="border-b border-stone-200 bg-stone-50/40 p-4 grid grid-cols-2 gap-3 text-[12px]">
            <div>
              <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Pagador</div>
              <div className="mt-0.5 font-medium text-stone-900">{venda.client}</div>
            </div>
            <div>
              <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Valor</div>
              <div className="mt-0.5 font-semibold text-[14px] tabular-nums text-stone-900">
                {(venda.totalNum || 0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'})}
              </div>
            </div>
          </div>

          {/* SE É VIEW: mostra detalhes da cobrança existente */}
          {isView && state.cob && (
            <div className="p-4 space-y-3">
              <div className="bg-stone-50 border border-stone-200 rounded-md p-3">
                <div className="flex items-center gap-2 text-[12px]">
                  <span className={"w-4 h-4 rounded-sm grid place-items-center text-white text-[8.5px] font-bold tracking-tight " + drv[state.cob.gateway].dot}>
                    {drv[state.cob.gateway].sigla}
                  </span>
                  <strong className="text-stone-900">{drv[state.cob.gateway].nome}</strong>
                  <span className="text-stone-500">· {tps[state.cob.tipo].label}</span>
                  <span className="ml-auto text-[10.5px] text-stone-500">
                    emitida {fmtDateBR(state.cob.emitida_em?.slice(0,10))}
                  </span>
                </div>

                {state.kind === 'paid' && (
                  <div className="mt-2 pt-2 border-t border-stone-200 text-[11.5px] text-emerald-700 flex items-center gap-1.5">
                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                    Paga em {fmtDateBR(state.cob.paga_em.slice(0,10))} {state.cob.paga_em.slice(11,16)} — liquidação automática via webhook
                  </div>
                )}
                {state.kind === 'pending' && (
                  <div className="mt-2 pt-2 border-t border-stone-200 text-[11.5px] text-blue-700 flex items-center gap-1.5">
                    <span className="w-1.5 h-1.5 rounded-full bg-blue-500" />
                    Vence {fmtDateBR(state.cob.vencimento)} — aguardando pagamento
                  </div>
                )}
                {state.kind === 'overdue' && (
                  <div className="mt-2 pt-2 border-t border-stone-200 text-[11.5px] text-rose-700 flex items-center gap-1.5">
                    <span className="w-1.5 h-1.5 rounded-full bg-rose-500" />
                    Vencida há {-daysFrom(state.cob.vencimento)}d — smart retry agendado
                  </div>
                )}
              </div>

              <div className="flex gap-2">
                <button className="os-btn sm" onClick={() => { window.PgGotoRoute && window.PgGotoRoute('cobranca'); onClose(); }}>
                  Ver em /financeiro/cobrança →
                </button>
                {(state.kind === 'pending' || state.kind === 'overdue') && (
                  <button className="os-btn sm" style={{ color: 'oklch(0.45 0.18 25)' }}>
                    Cancelar cobrança
                  </button>
                )}
              </div>
            </div>
          )}

          {/* SE NÃO É VIEW (none ou error): mostra form pra emitir */}
          {!isView && (
            <div className="p-4 space-y-4">

              {state.kind === 'error' && (
                <div className="bg-rose-50 border border-rose-200 rounded-md p-3 text-[11.5px] text-rose-900">
                  <div className="font-medium">Tentativa anterior falhou</div>
                  <div className="font-mono text-[10.5px] mt-1">{state.cob.erro_msg}</div>
                </div>
              )}

              <div>
                <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-2">Tipo de cobrança</div>
                <div className="grid grid-cols-4 gap-2">
                  {[
                    { id:'boleto',   label:'Boleto' },
                    { id:'pix_cob',  label:'PIX' },
                    { id:'pix_recv', label:'PIX Aut.', disabled:true, why:'só pra assinaturas' },
                    { id:'card',     label:'Cartão' },
                  ].map(t => (
                    <button key={t.id} disabled={t.disabled} onClick={() => setTipo(t.id)} title={t.why || ''}
                      className={"p-2.5 border rounded-md text-[11.5px] font-medium transition disabled:opacity-40 disabled:cursor-not-allowed " +
                        (tipo === t.id
                          ? "border-stone-900 bg-stone-50 text-stone-900 font-semibold ring-2 ring-stone-900/10"
                          : "border-stone-200 text-stone-700 hover:border-stone-400 hover:bg-stone-50")}>
                      {t.label}
                    </button>
                  ))}
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <label className="block">
                  <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-1">Conta destino</div>
                  <select value={conta} onChange={e=>setConta(parseInt(e.target.value))}
                    className="w-full h-8 px-2 text-[12.5px] bg-white border border-stone-300 rounded text-stone-900">
                    {accounts.filter(a => a.driver && drv[a.driver]?.tipos?.includes(tipo)).map(a => (
                      <option key={a.id} value={a.id}>{a.name}</option>
                    ))}
                  </select>
                </label>
                <label className="block">
                  <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-1">Vencimento</div>
                  <input type="date" value={venc} onChange={e=>setVenc(e.target.value)}
                    className="w-full h-8 px-2 text-[12.5px] bg-white border border-stone-300 rounded text-stone-900" />
                </label>
              </div>

              <div className="bg-stone-50 border border-stone-200 rounded p-3 text-[11px] text-stone-700">
                Dispara <span className="font-mono">PaymentGateway::emitir{tipo === 'card' ? 'Cartao' : tipo.startsWith('pix') ? 'Pix' : 'Boleto'}()</span> com origem <span className="font-mono">sale:{venda.id}</span> — idempotente (não duplica).
              </div>
            </div>
          )}
        </div>

        <footer className="os-drawer-actions">
          <button className="os-btn" onClick={onClose}>Fechar (ESC)</button>
          <div style={{ flex: 1 }} />
          {!isView && (
            <button className="os-btn primary" onClick={onClose}>
              <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
              Emitir cobrança
            </button>
          )}
          {state.kind === 'error' && (
            <button className="os-btn primary" onClick={onClose}>↻ Reemitir</button>
          )}
        </footer>
      </aside>
    </div>
  );
}

window.PG_CobrancaDrawer = CobrancaDrawer;
window.PgEmitirCobranca = PgEmitirCobranca;
})();
