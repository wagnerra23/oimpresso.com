// vendas-ai.jsx — Refino #2 KB-9.75 · IA dentro do fluxo (2026-05-15)
// 3 componentes: <VdAiSummary> · <VdAiHistory> · <VdAiSuggest>
// + helper vdAiClientHistory(name) que computa dados do cliente sobre VENDAS_LIST
// + container <VdAiPanel venda/> usado na nova tab "✦ IA" do drawer
//
// Usa window.claude.complete (haiku 4.5, 1024 tokens). Defensivo: se indisponível,
// rende um aviso visual mas não quebra.

const { useState: useStateAi, useMemo: useMemoAi, useEffect: useEffectAi } = React;

// ──────────────────────────────────────────────────────────────
// HISTÓRICO DO CLIENTE — computa de VENDAS_LIST
// ──────────────────────────────────────────────────────────────
window.vdAiClientHistory = function vdAiClientHistory(clientName) {
  const list = window.VENDAS_DATA?.VENDAS_LIST || [];
  const mine = list.filter(v => v.client === clientName);
  if (mine.length === 0) {
    return {
      count: 0, total: 0, avg: 0, lastDate: null,
      preferredPayment: null, recurringProducts: [],
      sellers: [], firstDate: null, isNew: true,
    };
  }
  const total = mine.reduce((s, v) => s + v.totalNum, 0);
  const avg = total / mine.length;
  const dates = mine.map(v => v.date).sort();
  const lastDate = dates[dates.length - 1];
  const firstDate = dates[0];

  // Forma de pagamento mais usada
  const payCount = {};
  mine.forEach(v => { payCount[v.payment] = (payCount[v.payment] || 0) + 1; });
  const preferredPayment = Object.entries(payCount).sort((a,b) => b[1]-a[1])[0]?.[0] || null;

  // Produtos recorrentes (por nome, top 3)
  const prodCount = {};
  mine.forEach(v => {
    (v.itemsList || []).forEach(it => {
      const key = it.name;
      if (!prodCount[key]) prodCount[key] = { name: it.name, count: 0, lastPrice: it.unit };
      prodCount[key].count += it.qty;
      prodCount[key].lastPrice = it.unit;
    });
  });
  const recurringProducts = Object.values(prodCount).sort((a,b) => b.count - a.count).slice(0, 3);

  // Vendedores atendentes (top 2)
  const sCount = {};
  mine.forEach(v => {
    const s = window.VENDAS_DATA?.VENDEDORES_MAP?.[v.sellerId]?.name || v.seller || "?";
    sCount[s] = (sCount[s] || 0) + 1;
  });
  const sellers = Object.entries(sCount).sort((a,b) => b[1]-a[1]).map(([n,c]) => ({ name:n, count:c }));

  return {
    count: mine.length, total, avg, lastDate, firstDate,
    preferredPayment, recurringProducts, sellers,
    isNew: mine.length === 1,
  };
};

// Helper format
const _aiFmt = (n) => (n || 0).toLocaleString("pt-BR", { style:"currency", currency:"BRL" });
const _aiDateBR = (d) => d ? d.split("-").reverse().join("/") : "—";

// ──────────────────────────────────────────────────────────────
// PROMPTS — curados, curtos, contexto explícito
// ──────────────────────────────────────────────────────────────
function _aiPromptSummary(v) {
  const itemsTxt = (v.itemsList || []).map(it =>
    `- ${it.name} (${it.qty}× · ${_aiFmt(it.unit)} = ${_aiFmt(it.qty * it.unit)})`
  ).join("\n");
  return `Você é um vendedor de balcão experiente da Oimpresso (gráfica). Resuma essa venda em 2 frases curtas, pra um colega pegar o pedido no meio do dia. Foque em: o que é, urgência/atenção. Não use bullets, fale natural.

Cliente: ${v.client}
Total: ${_aiFmt(v.totalNum)} · ${v.payment}${v.installments > 1 ? ` ${v.installments}×` : ""}
Prazo: ${v.payTerm || 0} dias
Itens (${(v.itemsList || []).length}):
${itemsTxt}
${v.clientNote ? `Nota: ${v.clientNote}` : ""}
${v.urgent ? "⚠ Marcada como URGENTE." : ""}`;
}

function _aiPromptHistory(v, h) {
  const prods = h.recurringProducts.map(p => `- ${p.name} (${p.count} vez${p.count>1?"es":""})`).join("\n");
  return `Você é assistente da Oimpresso. Em 2-3 frases curtas, descreva o relacionamento com este cliente: padrão de compra + nível de relacionamento + atenção pro próximo atendimento.

Cliente: ${v.client}
${h.isNew
    ? `Cliente novo — primeira venda em ${_aiDateBR(h.firstDate)}.`
    : `${h.count} vendas de ${_aiDateBR(h.firstDate)} até ${_aiDateBR(h.lastDate)}.
Valor total acumulado: ${_aiFmt(h.total)} · ticket médio ${_aiFmt(h.avg)}.
Pagamento preferido: ${h.preferredPayment}.
Atendido por: ${h.sellers.map(s => s.name).join(" e ")}.`}
Produtos recorrentes:
${prods || "(nenhum recorrência)"}

Esta venda atual: ${_aiFmt(v.totalNum)} · ${v.payment}.`;
}

function _aiPromptSuggest(v, h) {
  const prods = h.recurringProducts.map(p =>
    `- ${p.name} · ${p.count}× comprado · último preço ${_aiFmt(p.lastPrice)}`
  ).join("\n");
  return `Você é vendedor da Oimpresso. Cliente "${v.client}" tem este histórico:

${prods || "(sem histórico de produtos)"}

Última compra em ${_aiDateBR(h.lastDate)}: ${(v.itemsList||[]).map(i=>i.name).join(", ")}.

Sugira UM próximo produto que esse cliente provavelmente vai pedir nos próximos 30-60 dias. Responda APENAS no formato (3 linhas exatas):
PRODUTO: <nome do produto>
PREÇO: <preço estimado em R$>
PORQUE: <1 frase justificando, máx 12 palavras>`;
}

// ──────────────────────────────────────────────────────────────
// COMPONENTE BASE — botão IA com loading + result + retry
// ──────────────────────────────────────────────────────────────
function VdAiBlock({ icon = "✦", title, hint, prompt, parseResult, idleCta = "Gerar com IA" }) {
  const [state, setState] = useStateAi("idle"); // idle | loading | done | error
  const [result, setResult] = useStateAi(null);

  const run = async () => {
    if (!window.claude?.complete) {
      setState("error");
      setResult("Helper window.claude.complete não disponível neste ambiente.");
      return;
    }
    setState("loading");
    try {
      const text = await window.claude.complete(prompt);
      setResult(parseResult ? parseResult(text) : text);
      setState("done");
    } catch (e) {
      setResult(String(e?.message || e));
      setState("error");
    }
  };

  return (
    <div className={`vd-ai-block vd-ai-${state}`}>
      <header className="vd-ai-block-h">
        <span className="vd-ai-ic">{icon}</span>
        <div className="vd-ai-block-tx">
          <b>{title}</b>
          {hint && <small>{hint}</small>}
        </div>
        {state === "idle" && (
          <button className="vd-ai-cta" onClick={run}>{idleCta}</button>
        )}
        {state === "loading" && (
          <span className="vd-ai-loader"><span/><span/><span/></span>
        )}
        {(state === "done" || state === "error") && (
          <button className="vd-ai-retry" onClick={run} title="Refazer">↻</button>
        )}
      </header>
      {state === "loading" && (
        <div className="vd-ai-skel">
          <div className="vd-ai-skel-line" style={{width:"92%"}}/>
          <div className="vd-ai-skel-line" style={{width:"78%"}}/>
          <div className="vd-ai-skel-line" style={{width:"45%"}}/>
        </div>
      )}
      {(state === "done" || state === "error") && (
        <div className={`vd-ai-out ${state === "error" ? "err" : ""}`}>
          {typeof result === "string" ? result : result}
        </div>
      )}
    </div>
  );
}

// ──────────────────────────────────────────────────────────────
// 1) RESUMIR PEDIDO — texto livre
// ──────────────────────────────────────────────────────────────
function VdAiSummary({ venda }) {
  return (
    <VdAiBlock
      title="Resumir pedido"
      hint={`${(venda.itemsList || []).length} itens · ${_aiFmt(venda.totalNum)} — TL;DR pra colega pegar no meio do dia`}
      prompt={_aiPromptSummary(venda)}
      idleCta="✦ Resumir"
    />
  );
}

// ──────────────────────────────────────────────────────────────
// 2) HISTÓRICO DO CLIENTE — RAG mockado sobre VENDAS_LIST
// ──────────────────────────────────────────────────────────────
function VdAiHistory({ venda }) {
  const h = useMemoAi(() => window.vdAiClientHistory(venda.client), [venda.client]);

  return (
    <div className="vd-ai-history">
      {/* dados objetivos sempre visíveis */}
      <div className="vd-ai-stats">
        <div className="vd-ai-stat">
          <small>Vendas</small>
          <b>{h.count}</b>
          {h.isNew && <span className="vd-ai-tag new">novo</span>}
        </div>
        <div className="vd-ai-stat">
          <small>Total acumulado</small>
          <b>{_aiFmt(h.total)}</b>
        </div>
        <div className="vd-ai-stat">
          <small>Ticket médio</small>
          <b>{_aiFmt(h.avg)}</b>
        </div>
        <div className="vd-ai-stat">
          <small>Última compra</small>
          <b>{_aiDateBR(h.lastDate)}</b>
        </div>
        {h.preferredPayment && (
          <div className="vd-ai-stat full">
            <small>Pagamento preferido</small>
            <b>{h.preferredPayment}</b>
          </div>
        )}
      </div>

      {h.recurringProducts.length > 0 && (
        <div className="vd-ai-prods">
          <small>Produtos recorrentes</small>
          <ul>
            {h.recurringProducts.map((p, i) => (
              <li key={i}>
                <span className="ct">{p.count}×</span>
                <span>{p.name}</span>
                <span className="px">{_aiFmt(p.lastPrice)}</span>
              </li>
            ))}
          </ul>
        </div>
      )}

      <VdAiBlock
        title="Perguntar à IA sobre esse cliente"
        hint="Padrão de compra · nível de relacionamento · o que olhar no próximo atendimento"
        prompt={_aiPromptHistory(venda, h)}
        idleCta="✦ Perguntar"
      />
    </div>
  );
}

// ──────────────────────────────────────────────────────────────
// 3) SUGERIR PRÓXIMA VENDA — parse PRODUTO/PREÇO/PORQUE
// ──────────────────────────────────────────────────────────────
function _parseSuggest(text) {
  const lines = String(text || "").split(/\r?\n/);
  const out = { produto: "", preco: "", porque: "" };
  lines.forEach(ln => {
    const m1 = ln.match(/^\s*PRODUTO:\s*(.+?)\s*$/i);
    const m2 = ln.match(/^\s*PREÇO:\s*(.+?)\s*$/i) || ln.match(/^\s*PRECO:\s*(.+?)\s*$/i);
    const m3 = ln.match(/^\s*PORQUE:\s*(.+?)\s*$/i) || ln.match(/^\s*POR\s*QUE:\s*(.+?)\s*$/i);
    if (m1) out.produto = m1[1];
    if (m2) out.preco = m2[1];
    if (m3) out.porque = m3[1];
  });
  if (!out.produto && !out.preco) return <span>{String(text)}</span>;
  return (
    <div className="vd-ai-suggest">
      <div className="vd-ai-suggest-h">
        <span className="vd-ai-suggest-ic">✦</span>
        <div>
          <b>{out.produto || "—"}</b>
          {out.preco && <small>{out.preco}</small>}
        </div>
      </div>
      {out.porque && <p>{out.porque}</p>}
      <button className="vd-ai-suggest-cta">Adicionar a novo orçamento →</button>
    </div>
  );
}

function VdAiSuggest({ venda }) {
  const h = useMemoAi(() => window.vdAiClientHistory(venda.client), [venda.client]);
  if (h.isNew) {
    return (
      <div className="vd-ai-block vd-ai-idle vd-ai-disabled">
        <header className="vd-ai-block-h">
          <span className="vd-ai-ic">✦</span>
          <div className="vd-ai-block-tx">
            <b>Sugerir próximo pedido</b>
            <small>Cliente novo — IA precisa de pelo menos 2 vendas pra inferir padrão.</small>
          </div>
        </header>
      </div>
    );
  }
  return (
    <VdAiBlock
      title="Sugerir próximo pedido"
      hint={`Baseado em ${h.count} vendas anteriores · ${h.recurringProducts.length} produtos recorrentes`}
      prompt={_aiPromptSuggest(venda, h)}
      parseResult={_parseSuggest}
      idleCta="✦ Sugerir"
    />
  );
}

// ──────────────────────────────────────────────────────────────
// PAINEL CONTAINER — usado na tab ✦ IA do drawer
// ──────────────────────────────────────────────────────────────
function VdAiPanel({ venda }) {
  return (
    <section className="vd-section vd-ai-panel">
      <div className="vd-ai-banner">
        <span className="vd-ai-banner-ic">✦</span>
        <div>
          <b>IA copiloto</b>
          <small>3 ferramentas — IA propõe, você decide. Sempre cita fonte. Pode editar antes de aplicar.</small>
        </div>
      </div>

      <h3>1 · Resumir pedido</h3>
      <VdAiSummary venda={venda}/>

      <h3>2 · Histórico do cliente</h3>
      <VdAiHistory venda={venda}/>

      <h3>3 · Sugerir próximo pedido</h3>
      <VdAiSuggest venda={venda}/>
    </section>
  );
}

window.VdAiPanel    = VdAiPanel;
window.VdAiSummary  = VdAiSummary;
window.VdAiHistory  = VdAiHistory;
window.VdAiSuggest  = VdAiSuggest;
