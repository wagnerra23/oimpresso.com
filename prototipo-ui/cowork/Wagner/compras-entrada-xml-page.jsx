// compras-entrada-xml-page.jsx — Entrada por XML: conferência da NF-e do fornecedor antes de virar compra.
// Protótipo de 27/10/2026, fonte da tela Compras/EntradaXml (US-COM-003 · ADR entrada por XML, fase 4).
// Parte da aba Entradas de fiscal-tributacao.jsx (TrEntradas, UC-TRB-18) e acrescenta o que a tela real faz:
// enviar o XML, escolher a nota, criar produto novo e gerar a compra no local. Tokens copiados de
// compras-reposicao-abc-page.jsx (DS .cockpit). O HTML vai literal; o prototipo-html-page.jsx o monta.
(window.PROTO_HTML = window.PROTO_HTML || {})["cmp-entrada-xml"] = `<title>Entrada por XML</title>
<meta name="claude:viewport-fit-sides" content="cover">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
<style>
/* Protótipo US-ESTOQUE-003 (curva ABC + sugestão de reposição pelo giro). Tokens = .cockpit de
   prototipo-ui/design-system/colors_and_type.css (ADR 0438 · DS no git).
   Layout: PT-01 Lista — cabeçalho, filtros, faixa curta de resumo, tabela densa. Dados de EXEMPLO.
   Cálculo real: Modules/Compras/Services/CurvaAbcReposicao.php (PR #9983). */
:root {
  --font-sans: "IBM Plex Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
  --font-mono: "IBM Plex Mono", ui-monospace, "SF Mono", Menlo, monospace;
  --bg: oklch(0.985 0.003 90); --bg-2: oklch(0.965 0.004 90); --surface: #ffffff;
  --border: oklch(0.90 0.004 90); --border-2: oklch(0.93 0.004 90);
  --text: oklch(0.22 0.01 80); --text-dim: oklch(0.50 0.01 80); --text-mute: oklch(0.555 0.01 80);
  --accent: oklch(0.55 0.15 295); --accent-soft: oklch(0.95 0.04 295); --accent-fg: #ffffff;
  --pos: oklch(0.50 0.12 150); --pos-soft: oklch(0.95 0.075 150);
  --neg: oklch(0.55 0.18 25); --neg-soft: oklch(0.955 0.055 25);
  --warn: oklch(0.56 0.12 70); --warn-soft: oklch(0.955 0.072 75);
  --radius: 8px; --radius-sm: 6px; --row-h: 32px;
}
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) {
  --bg: oklch(0.26 0.006 240); --bg-2: oklch(0.23 0.006 240); --surface: oklch(0.30 0.008 240);
  --border: oklch(0.34 0.008 240); --border-2: oklch(0.31 0.008 240);
  --text: oklch(0.94 0.005 90); --text-dim: oklch(0.72 0.005 90); --text-mute: oklch(0.67 0.005 90);
  --accent-soft: oklch(0.33 0.09 295); --pos: oklch(0.76 0.18 150); --pos-soft: oklch(0.32 0.06 150);
  --neg: oklch(0.74 0.19 25); --neg-soft: oklch(0.32 0.06 25); --warn: oklch(0.82 0.16 75); --warn-soft: oklch(0.33 0.06 75);
  color-scheme: dark; } }
:root[data-theme="dark"] {
  --bg: oklch(0.26 0.006 240); --bg-2: oklch(0.23 0.006 240); --surface: oklch(0.30 0.008 240);
  --border: oklch(0.34 0.008 240); --border-2: oklch(0.31 0.008 240);
  --text: oklch(0.94 0.005 90); --text-dim: oklch(0.72 0.005 90); --text-mute: oklch(0.67 0.005 90);
  --accent-soft: oklch(0.33 0.09 295); --pos: oklch(0.76 0.18 150); --pos-soft: oklch(0.32 0.06 150);
  --neg: oklch(0.74 0.19 25); --neg-soft: oklch(0.32 0.06 25); --warn: oklch(0.82 0.16 75); --warn-soft: oklch(0.33 0.06 75);
  color-scheme: dark; }
body { background: var(--bg); color: var(--text); font-family: var(--font-sans); font-size: 13px; }
.page { max-width: 1240px; margin: 0 auto; padding-block: 20px 40px;
  padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px));
  display: grid; gap: 14px; }
.num { font-family: var(--font-mono); font-variant-numeric: tabular-nums; }
header.ph { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px;
  border-bottom: 1px solid var(--border); padding-bottom: 12px; }
.crumb { color: var(--text-mute); font-size: 12px; }
h1 { font-size: 20px; font-weight: 600; margin: 2px 0 0; text-wrap: balance; }
.sub { color: var(--text-dim); font-size: 12px; margin: 2px 0 0; max-width: 70ch; }
.acts { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.btn { font: inherit; font-size: 12.5px; border: 1px solid var(--border); background: var(--surface); color: var(--text);
  border-radius: var(--radius-sm); padding: 6px 12px; cursor: pointer; }
.btn.primary { background: var(--accent); border-color: var(--accent); color: var(--accent-fg); font-weight: 500; }
.btn:disabled { opacity: .5; cursor: not-allowed; }
.btn:focus-visible, select:focus-visible, input:focus-visible, .chip:focus-visible, .seg button:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
.flag { font-size: 12px; color: var(--warn); background: var(--warn-soft); border-radius: 999px; padding: 3px 10px; }
.cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr)); gap: 12px; }
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px 14px; }
.card h2 { font-size: 13px; font-weight: 600; margin: 0 0 8px; }
.row { display: flex; justify-content: space-between; gap: 12px; padding: 4px 0; border-top: 1px solid var(--border-2); font-size: 12.5px; }
.row:first-of-type { border-top: 0; }
.row span:first-child { color: var(--text-dim); }
.tbl { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
th { text-align: left; font-weight: 500; color: var(--text-dim); font-size: 11.5px; padding: 8px 10px; border-bottom: 1px solid var(--border); background: var(--bg-2); }
td { padding: 7px 10px; border-bottom: 1px solid var(--border-2); vertical-align: top; height: var(--row-h); }
tr:last-child td { border-bottom: 0; }
tbody.notas tr { cursor: pointer; }
tbody.notas tr[aria-selected="true"] td { background: var(--accent-soft); }
.r { text-align: right; }
.mini { color: var(--text-mute); font-size: 11.5px; }
.st { color: var(--warn); font-size: 11.5px; }
.ok { font-size: 12px; color: var(--pos); background: var(--pos-soft); border-radius: 999px; padding: 2px 9px; }
.neg { color: var(--neg); }
.vinc { display: grid; gap: 4px; min-width: 14rem; }
.rodape { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.nota-rodape { color: var(--text-mute); font-size: 11.5px; margin: 0; }
</style>
<div class="page">
  <header class="ph">
    <div>
      <div class="crumb">Compras › Entrada por XML</div>
      <h1>Entrada por XML</h1>
      <p class="sub">Confira a nota do fornecedor antes de ela virar compra. Notas da SEFAZ chegam sozinhas; as outras você envia em arquivo.</p>
    </div>
    <div class="acts">
      <span class="flag">Desligada por padrão · NFEBRASIL_ENTRADA_XML</span>
      <label class="btn" for="arq">Escolher XML</label><input id="arq" type="file" accept=".xml" hidden>
      <button class="btn primary" type="button" onclick="enviar()">Enviar XML</button>
    </div>
  </header>

  <section aria-label="Notas esperando virar compra" class="tbl">
    <table>
      <thead><tr><th>Fornecedor</th><th>Nota</th><th>Emissão</th><th>Origem</th><th class="r">Valor</th></tr></thead>
      <tbody class="notas" id="notas"></tbody>
    </table>
  </section>

  <section aria-label="Conferência da nota" style="display:grid;gap:12px">
    <div class="cards">
      <div class="card"><h2>Como a entrada é lida</h2>
        <div class="row"><span>Fornecedor</span><span id="c-forn"></span></div>
        <div class="row"><span>Total da nota</span><span class="num" id="c-total"></span></div>
        <div class="row"><span>Produto</span><span>código do fornecedor → seu produto, lembrado</span></div>
        <div class="row"><span>Crédito de ICMS</span><span>Simples não credita — vai pro custo</span></div>
      </div>
      <div class="card"><h2>O que trava a entrada</h2>
        <div class="row"><span>Item sem produto vinculado</span><span id="c-faltam"></span></div>
        <div class="row"><span>Fornecedor</span><span id="c-cad"></span></div>
        <div class="row"><span>CST 060 (ST retida)</span><span>marcado no item; o produto não muda sozinho</span></div>
      </div>
    </div>
    <div class="tbl">
      <table>
        <thead><tr><th>Item do XML</th><th>NCM</th><th>CFOP fornecedor → entrada</th><th>CST</th><th class="r">ICMS destacado</th><th class="r">Valor</th><th>Seu produto</th></tr></thead>
        <tbody id="itens"></tbody>
      </table>
    </div>
    <div class="rodape">
      <div class="field"><label for="local">Local de estoque</label><select id="local"><option>Loja Centro</option><option>Depósito</option></select></div>
      <button class="btn primary" id="gerar" type="button" onclick="gerar()">Gerar compra</button>
      <span id="msg" role="status" class="mini"></span>
    </div>
    <p class="nota-rodape">Compra = produtos − desconto + frete + IPI/ST/seguro/outras = total da nota; se não fechar, a importação é recusada. Produto novo nasce fora de venda, com preço 0. Dados de EXEMPLO.</p>
  </section>
</div>
<script>
var CFOP = { "5102": "1102", "6102": "2102", "5101": "1101", "6101": "2101", "5405": "1403", "6403": "2403", "5949": "1949" };
var PRODUTOS = ["Lona front 440g", "Tinta solvente ciano 1L", "Diluente 5L", "ACM 3mm branco"];
var NOTAS = [
  { id: 1, forn: "Lona e Cia Distribuidora", nota: "1174", data: "20/10/2026", origem: "SEFAZ", total: 1392.00, cad: true, itens: [
    { d: "LONA FRONT 440G 3,20M", cod: "LF440", q: "50 M2", ncm: "39211390", cfop: "6102", cst: "000", icms: 96.00, v: 1100.00, prod: "Lona front 440g" },
    { d: "TINTA SOLV CIANO 1L", cod: "TS-C1", q: "4 UN", ncm: "32089010", cfop: "6102", cst: "000", icms: 14.40, v: 120.00, prod: null, sug: "Tinta solvente ciano 1L" },
    { d: "DILUENTE 5L", cod: "DL5", q: "2 GL", ncm: "38140000", cfop: "6403", cst: "060", icms: null, v: 120.00, prod: null } ] },
  { id: 2, forn: "Chapas Norte Ltda", nota: "88", data: "18/10/2026", origem: "Arquivo", total: 640.00, cad: false, itens: [
    { d: "ACM 3MM BRANCO 1,22X5", cod: "ACM3B", q: "2 UN", ncm: "76061290", cfop: "5102", cst: "101", icms: null, v: 640.00, prod: "ACM 3mm branco" } ] }
];
var sel = 1; var vinc = {};
function brl(n) { return n.toLocaleString("pt-BR", { style: "currency", currency: "BRL" }); }
function nota() { return NOTAS.filter(function (n) { return n.id === sel; })[0]; }
function produto(i, k) { var key = sel + "-" + k; return key in vinc ? vinc[key] : i.prod; }
function render() {
  document.getElementById("notas").innerHTML = NOTAS.map(function (n) {
    return '<tr tabindex="0" aria-selected="' + (n.id === sel) + '" onclick="sel=' + n.id + ';render()"><td><b>' + n.forn + '</b></td><td class="num">' + n.nota + '</td><td>' + n.data + '</td><td>' + n.origem + '</td><td class="r num">' + brl(n.total) + '</td></tr>';
  }).join("");
  var n = nota(); var faltam = 0;
  document.getElementById("c-forn").textContent = n.forn;
  document.getElementById("c-total").textContent = brl(n.total);
  document.getElementById("c-cad").innerHTML = n.cad ? "cadastrado" : '<b class="neg">CNPJ não cadastrado</b>';
  document.getElementById("itens").innerHTML = n.itens.map(function (i, k) {
    var p = produto(i, k); if (!p) faltam++;
    var key = sel + "-" + k;
    var cel = p ? '<span class="ok">' + p + '</span> <button class="btn" type="button" data-k="' + key + '" data-v="" onclick="escolher(this)">trocar</button>'
      : '<div class="vinc">' + (i.sug ? '<button class="btn" type="button" data-k="' + key + '" data-v="' + i.sug + '" onclick="escolher(this)">Usar ' + i.sug + ' (mesmo EAN)</button>' : '')
        + '<button class="btn" type="button" data-k="' + key + '" data-v="Produto novo (fora de venda)" onclick="escolher(this)">Criar produto novo</button>'
        + '<select aria-label="Vincular ' + i.d + '" data-k="' + key + '" onchange="escolher(this)"><option value="">Vincular produto…</option>'
        + PRODUTOS.map(function (x) { return '<option>' + x + '</option>'; }).join("") + '</select></div>';
    return '<tr><td><b>' + i.d + '</b><div class="mini">' + i.cod + ' · ' + i.q + '</div></td><td class="num">' + i.ncm + '</td><td class="num">' + i.cfop + ' → <b>' + (CFOP[i.cfop] || "?") + '</b></td><td class="num">' + i.cst + (i.cst === "060" ? '<div class="st">ST retida</div>' : '') + '</td><td class="r num">' + (i.icms === null ? "—" : brl(i.icms)) + '</td><td class="r num">' + brl(i.v) + '</td><td>' + cel + '</td></tr>';
  }).join("");
  document.getElementById("c-faltam").innerHTML = faltam === 0 ? "nenhum" : "<b>" + faltam + "</b>";
  document.getElementById("gerar").disabled = faltam > 0 || !n.cad;
  document.getElementById("msg").textContent = !n.cad ? "Cadastre o fornecedor antes de gerar a compra." : (faltam > 0 ? "Vincule os itens para gerar a compra." : "");
}
function escolher(el) { vinc[el.dataset.k] = el.tagName === "SELECT" ? (el.value || null) : (el.dataset.v || null); render(); }
function enviar() { document.getElementById("msg").textContent = "XML enviado: a nota aparece na lista (exemplo)."; }
function gerar() { document.getElementById("msg").textContent = "Compra criada a partir da NF-e " + nota().nota + " (exemplo)."; }
render();
</script>
`;
