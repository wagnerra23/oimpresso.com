// oficina-print.js — geração das folhas A4 imprimíveis da Oficina Auto.
// Expõe window.OficinaPrint = { printOS, printFila }.
// Vanilla DOM (sem React): monta HTML num #ofc-print-root, marca body.ofc-printing,
// chama window.print() e limpa no afterprint. CSS em oficina-print.css.
// Padrão canônico espelhado de Vendas (overlay + @media print isola o shell).
(() => {
  const REF = () => window.OFICINA_REF || { RECURSOS: [], MECANICOS: [], STAGES: [] };
  const recursoOf = (id) => REF().RECURSOS.find(r => r.id === id);
  const mechOf    = (id) => REF().MECANICOS.find(m => m.id === id);
  const stageOf   = (id) => REF().STAGES.find(s => s.id === id);

  const esc = (s) => String(s == null ? "" : s).replace(/[&<>"]/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));
  const num = (v) => {
    if (typeof v === "number") return v;
    return parseFloat(String(v || "").replace(/[^\d,.-]/g, "").replace(/\.(?=\d{3})/g, "").replace(",", ".")) || 0;
  };
  const brl = (n) => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const today = () => new Date().toLocaleDateString("pt-BR", { day: "2-digit", month: "2-digit", year: "numeric" });
  const nowStamp = () => new Date().toLocaleString("pt-BR", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });

  function root() {
    let el = document.getElementById("ofc-print-root");
    if (!el) { el = document.createElement("div"); el.id = "ofc-print-root"; document.body.appendChild(el); }
    return el;
  }

  function run(html) {
    root().innerHTML = html;
    document.body.classList.add("ofc-printing");
    const cleanup = () => {
      document.body.classList.remove("ofc-printing");
      root().innerHTML = "";
      window.removeEventListener("afterprint", cleanup);
    };
    window.addEventListener("afterprint", cleanup);
    setTimeout(() => window.print(), 80);
    // fallback se afterprint não disparar (alguns browsers)
    setTimeout(cleanup, 60000);
  }

  function brandHead(docTitle, docNum, sub) {
    return `<div class="ofc-sheet-top">
      <div class="ofc-sheet-brand">Oimpresso<small>Oficina Auto</small></div>
      <div class="ofc-sheet-doc">
        <div class="t">${esc(docTitle)}</div>
        ${docNum ? `<div class="n">${esc(docNum)}</div>` : ""}
        <div class="d">${esc(sub || today())}</div>
      </div>
    </div>`;
  }

  // ───────────────────────── OS (Ordem de Serviço) ─────────────────────────
  function printOS(os, opts) {
    opts = opts || {};
    const items = opts.items || [];
    const dvi = opts.dvi || [];
    const fotos = opts.fotos || [];
    const r = recursoOf(os.recurso);
    const m = mechOf(os.mech);
    const st = stageOf(os.stage);

    const field = (dt, dd, cls) => `<div class="f ${cls || ""}"><dt>${esc(dt)}</dt><dd>${esc(dd || "—")}</dd></div>`;

    const dviRows = dvi.length ? dvi.map(d => `
      <tr>
        <td>${esc(d.sistema)}</td>
        <td><span class="st ${esc(d.status)}">${d.status === "ok" ? "OK" : d.status === "warn" ? "Atenção" : "Crítico"}</span></td>
        <td>${esc(d.obs || "—")}</td>
        <td class="num">${d.valor ? brl(num(d.valor)) : "—"}</td>
      </tr>`).join("") : "";

    const tipoLbl = { peca: "Peça", mao_obra: "Mão de obra", terceiro: "Terceiro", servico_terceiro: "Terceiro" };
    const itemVal = (it) => {
      if (it.valor != null) return num(it.valor);
      const q = num(it.qty != null ? it.qty : (it.qtd != null ? it.qtd : 1));
      const u = num(it.unit != null ? it.unit : (it.preco != null ? it.preco : it.valor_unitario));
      return it.total != null ? num(it.total) : q * u;
    };
    const itemRows = items.length ? items.map(it => `
      <tr>
        <td>${esc(it.descricao || it.desc || it.nome || it.label || "—")}</td>
        <td>${esc(tipoLbl[it.tipo] || it.tipo || "—")}</td>
        <td class="num">${esc(it.qty != null ? it.qty : (it.qtd != null ? it.qtd : 1))}</td>
        <td class="num">${brl(itemVal(it))}</td>
      </tr>`).join("") : "";

    const dviTotal = dvi.reduce((a, d) => a + num(d.valor), 0);
    const itemsTotal = items.reduce((a, it) => a + itemVal(it), 0);
    const grand = (itemsTotal || dviTotal || num(os.value));

    const html = `<div class="ofc-sheet">
      ${brandHead("Ordem de Serviço", "OS #" + esc(os.id), today())}

      <div class="ofc-sheet-grid">
        ${field("Cliente", os.client, "wide")}
        ${field("Veículo", os.veh, "wide")}
        ${field("Placa", os.plate, "mono")}
        ${field("KM", os.km, "mono")}
        ${field("Box / Elevador", r ? r.label : "—")}
        ${field("Mecânico", m ? m.nome : "—")}
        ${field("Etapa atual", st ? st.label : os.stage)}
        ${field("Prazo", os.deadline)}
        ${field("Valor previsto", os.value, "mono")}
      </div>

      <div class="ofc-sheet-sec">
        <h3>Sintoma reportado</h3>
        <p class="ofc-sheet-symptom">${esc(os.symptom || "—")}</p>
      </div>

      <div class="ofc-sheet-sec">
        <h3>Vistoria digital · DVI</h3>
        ${dvi.length ? `<table class="ofc-sheet-tbl">
          <thead><tr><th>Sistema</th><th>Estado</th><th>Observação</th><th class="num">Valor</th></tr></thead>
          <tbody>${dviRows}</tbody>
          ${dviTotal ? `<tfoot><tr><td colspan="3">Total recomendado</td><td class="num">${brl(dviTotal)}</td></tr></tfoot>` : ""}
        </table>` : `<div class="ofc-sheet-empty">Sem itens de vistoria registrados.</div>`}
      </div>

      <div class="ofc-sheet-sec">
        <h3>Peças &amp; mão de obra</h3>
        ${items.length ? `<table class="ofc-sheet-tbl">
          <thead><tr><th>Descrição</th><th>Tipo</th><th class="num">Qtd</th><th class="num">Valor</th></tr></thead>
          <tbody>${itemRows}</tbody>
          <tfoot><tr><td colspan="3">Total</td><td class="num">${brl(itemsTotal)}</td></tr></tfoot>
        </table>` : `<div class="ofc-sheet-empty">Sem peças ou serviços lançados.</div>`}
      </div>

      ${fotos.length ? `<div class="ofc-sheet-sec">
        <h3>Fotos da vistoria</h3>
        <div class="ofc-sheet-photos">${fotos.map(f => `<figure class="ofc-sheet-photo"><img src="${esc(f.url)}" alt="${esc(f.label || "")}"/><figcaption>${esc(f.label || "")}</figcaption></figure>`).join("")}</div>
      </div>` : ""}

      <div class="ofc-sheet-sign">
        <div class="s"><div class="line">Assinatura do cliente</div></div>
        <div class="s"><div class="line">Responsável · ${esc(m ? m.nome : "Oficina")}</div></div>
      </div>

      <div class="ofc-sheet-foot">
        <span>Oimpresso ERP · Oficina Auto · OS #${esc(os.id)}</span>
        <span>Emitido ${esc(nowStamp())}</span>
      </div>
    </div>`;

    run(html);
  }

  // ───────────────────────── Fila (lista de OS abertas) ─────────────────────────
  function printFila(list, opts) {
    opts = opts || {};
    list = (list || []).slice();
    // ordem: urgentes primeiro, depois por etapa
    const STAGES = REF().STAGES;
    const stageIdx = (id) => { const i = STAGES.findIndex(s => s.id === id); return i < 0 ? 99 : i; };
    list.sort((a, b) => (b.urgent - a.urgent) || (stageIdx(a.stage) - stageIdx(b.stage)));

    const rows = list.map(os => {
      const r = recursoOf(os.recurso);
      const m = mechOf(os.mech);
      const st = stageOf(os.stage);
      return `<tr class="${os.urgent ? "urg" : ""}">
        <td class="stage-cell">${esc(st ? st.label : os.stage)}</td>
        <td class="num">#${esc(os.id)}</td>
        <td>${esc(os.veh)}</td>
        <td class="num">${esc(os.plate)}</td>
        <td>${esc(os.client)}</td>
        <td>${esc(m ? m.nome : "—")}</td>
        <td>${esc(r ? r.label : "—")}</td>
        <td>${esc(os.deadline)}</td>
        <td class="num">${esc(os.value)}</td>
      </tr>`;
    }).join("");

    const total = list.reduce((a, os) => a + num(os.value), 0);
    const urgentes = list.filter(o => o.urgent).length;

    const html = `<div class="ofc-sheet">
      ${brandHead("Fila da oficina", "", today())}
      <div class="ofc-sheet-note">${list.length} ordem(ns) aberta(s)${urgentes ? ` · ${urgentes} urgente(s)` : ""}${opts.filtro ? ` · filtro: ${esc(opts.filtro)}` : ""}</div>

      <table class="ofc-sheet-tbl">
        <thead><tr>
          <th>Etapa</th><th class="num">OS</th><th>Veículo</th><th class="num">Placa</th>
          <th>Cliente</th><th>Mecânico</th><th>Box</th><th>Prazo</th><th class="num">Valor</th>
        </tr></thead>
        <tbody>${rows || `<tr><td colspan="9" class="ofc-sheet-empty">Nenhuma OS na fila.</td></tr>`}</tbody>
        ${list.length ? `<tfoot><tr><td colspan="8">Total previsto em carteira</td><td class="num">${brl(total)}</td></tr></tfoot>` : ""}
      </table>

      <div class="ofc-sheet-foot">
        <span>Oimpresso ERP · Oficina Auto · Fila de produção</span>
        <span>Emitido ${esc(nowStamp())}</span>
      </div>
    </div>`;

    run(html);
  }

  window.OficinaPrint = { printOS, printFila };
})();
