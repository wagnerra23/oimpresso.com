// compras-reposicao-abc-page.jsx — Reposição e curva ABC. Protótipo de 27/10/2026 (PR #10006), fonte desta tela no shell.
// O HTML da tela vai literal abaixo; o prototipo-html-page.jsx o monta numa shadow root (CSS isolado,
// tema do shell, scripts e onclick funcionando). Para editar: é HTML comum, mas dentro deste texto
// crase, ${ e barra invertida vão com barra na frente (\`  \${  \\).
(window.PROTO_HTML = window.PROTO_HTML || {})["cmp-reposicao"] = `<title>Reposição e curva ABC</title>
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
.filtros { display: flex; flex-wrap: wrap; gap: 10px 16px; align-items: end; }
.field { display: grid; gap: 4px; }
.field label { font-size: 11.5px; color: var(--text-dim); }
select, input[type=text] { font: inherit; border: 1px solid var(--border); background: var(--surface); color: var(--text);
  border-radius: var(--radius-sm); padding: 5px 8px; }
input[type=text] { width: 64px; }
.seg { display: inline-flex; border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; }
.seg button { font: inherit; font-size: 12.5px; border: 0; background: var(--surface); color: var(--text-dim); padding: 5px 12px; cursor: pointer; }
.seg button[aria-pressed="true"] { background: var(--accent-soft); color: var(--accent); font-weight: 500; }
.resumo { display: flex; flex-wrap: wrap; gap: 8px 24px; background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 12px 14px; }
.resumo .it { display: grid; gap: 2px; }
.resumo .lb { color: var(--text-dim); font-size: 11.5px; }
.resumo .v { font-size: 17px; font-weight: 600; }
.pareto { flex: 1 1 260px; min-width: 0; display: grid; gap: 4px; }
.pareto .barra { display: flex; height: 10px; border-radius: 5px; overflow: hidden; background: var(--bg-2); }
.pareto .barra i { display: block; height: 100%; }
.pareto .leg { display: flex; flex-wrap: wrap; gap: 4px 12px; color: var(--text-mute); font-size: 11.5px; }
.cA { background: var(--accent); } .cB { background: oklch(0.70 0.10 295); } .cC { background: var(--border); }
.chips { display: flex; flex-wrap: wrap; gap: 6px; }
.chip { font: inherit; font-size: 12px; border: 1px solid var(--border); background: var(--surface); color: var(--text-dim);
  border-radius: 999px; padding: 3px 10px; cursor: pointer; }
.chip[aria-pressed="true"] { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }
.panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); min-width: 0; }
.tw { overflow-x: auto; }
table { border-collapse: collapse; width: 100%; font-size: 12.5px; }
th { text-align: left; font-weight: 500; color: var(--text-dim); font-size: 11.5px; padding: 8px 10px; border-bottom: 1px solid var(--border); white-space: nowrap; background: var(--bg-2); }
td { padding: 0 10px; height: var(--row-h); border-bottom: 1px solid var(--border-2); white-space: nowrap; }
td.r, th.r { text-align: right; }
td.prod { white-space: normal; min-width: 180px; }
td.prod small { display: block; color: var(--text-mute); font-family: var(--font-mono); font-size: 11px; }
.curva { display: inline-grid; place-items: center; width: 22px; height: 20px; border-radius: 4px; font-weight: 600; font-size: 11.5px; }
.curva.A { background: var(--accent); color: var(--accent-fg); }
.curva.B { background: var(--accent-soft); color: var(--accent); }
.curva.C { background: var(--bg-2); color: var(--text-dim); }
.sug { font-weight: 600; color: var(--warn); }
.sug.zero { color: var(--text-mute); font-weight: 400; }
.mute { color: var(--text-mute); }
.neg { color: var(--neg); }
.foot { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; padding: 10px 14px; color: var(--text-mute); font-size: 12px; }
.nota { color: var(--text-mute); font-size: 12px; line-height: 1.5; max-width: 90ch; }
</style>

<div class="page">
  <header class="ph">
    <div>
      <div class="crumb">Estoque › Reposição e curva ABC</div>
      <h1>Reposição e curva ABC</h1>
      <p class="sub">Quanto cada produto vende, quanto ele pesa no faturamento e quanto comprar para não faltar, pelo ritmo de venda e pelo prazo do fornecedor.</p>
    </div>
    <div class="acts">
      <span class="flag">Protótipo · dados de exemplo</span>
      <button class="btn primary" id="levar" type="button" disabled>Levar para a requisição de compra</button>
    </div>
  </header>

  <div class="filtros">
    <div class="field"><label for="periodo">Período de venda</label>
      <select id="periodo"><option>Últimos 30 dias</option><option selected>Últimos 90 dias</option><option>Últimos 180 dias</option></select></div>
    <div class="field"><label for="cob">Cobrir quantos dias</label>
      <div><input type="text" id="cob" class="num" value="30" inputmode="numeric"> <span class="mute">dias além do prazo</span></div></div>
    <div class="field"><span class="mute" style="font-size:11.5px">Curva por</span>
      <div class="seg" role="group" aria-label="Curva por">
        <button type="button" id="porFat" aria-pressed="true">Faturamento</button>
        <button type="button" id="porMar" aria-pressed="false">Margem</button>
      </div></div>
  </div>

  <section class="resumo" aria-label="Resumo do período">
    <div class="it"><span class="lb">Faturamento no período</span><span class="v num">R$ 48.230,00</span></div>
    <div class="it"><span class="lb">Itens curva A</span><span class="v num">5 de 9</span></div>
    <div class="it"><span class="lb">Para repor</span><span class="v num" style="color:var(--warn)">5 itens</span></div>
    <div class="pareto">
      <span class="lb" style="color:var(--text-dim);font-size:11.5px">Peso de cada curva no faturamento</span>
      <div class="barra" aria-hidden="true"><i class="cA" style="width:87.6%"></i><i class="cB" style="width:9.7%"></i><i class="cC" style="width:2.7%"></i></div>
      <div class="leg"><span>A 87,6% · 5 itens</span><span>B 9,7% · 2 itens</span><span>C 2,7% · 2 itens</span></div>
    </div>
  </section>

  <div class="chips" role="group" aria-label="Filtrar lista">
    <button class="chip" type="button" data-f="todos" aria-pressed="true">Todos</button>
    <button class="chip" type="button" data-f="repor" aria-pressed="false">Para repor</button>
    <button class="chip" type="button" data-f="A" aria-pressed="false">Curva A</button>
    <button class="chip" type="button" data-f="B" aria-pressed="false">Curva B</button>
    <button class="chip" type="button" data-f="C" aria-pressed="false">Curva C</button>
  </div>

  <section class="panel">
    <div class="tw">
      <table>
        <thead><tr>
          <th><input type="checkbox" id="todosSel" aria-label="Selecionar os que têm sugestão"></th>
          <th>Produto</th><th>Curva</th>
          <th class="r">Faturamento</th><th class="r">Margem</th>
          <th class="r">Vendido</th><th class="r">Por dia</th>
          <th class="r">Estoque</th><th class="r">A caminho</th>
          <th class="r">Prazo fornecedor</th><th class="r">Dura</th><th class="r">Comprar</th>
        </tr></thead>
        <tbody id="corpo"></tbody>
      </table>
    </div>
    <div class="foot"><span id="contagem"></span><span>Comprar = por dia × (prazo + 30) − estoque − a caminho, arredondado para cima.</span></div>
  </section>

  <p class="nota">Margem pelo custo do lote de cada venda, o mesmo do relatório de lucro. Quando a venda não tem lote amarrado, a margem do item fica sem valor e ele cai na curva C de margem. As OS e o estoque mínimo continuam na tela de requisições (MRP); esta lista olha só o ritmo de venda.</p>
</div>

<script>
const ITENS = [
  { p: "Lona fosca 440 g/m²", sku: "LON-440", fat: 18400, cF: "A", mar: 6900, cM: "A", vend: 920, un: "m²", dia: 10.22, est: 140, cam: 0, prazo: 7, sug: 239 },
  { p: "Vinil adesivo branco brilho", sku: "VIN-BR", fat: 9800, cF: "A", mar: 4100, cM: "A", vend: 700, un: "m²", dia: 7.78, est: 300, cam: 100, prazo: 10, sug: 0 },
  { p: "Banner pronto 90 × 120", sku: "BAN-9012", fat: 6250, cF: "A", mar: 1250, cM: "B", vend: 125, un: "un", dia: 1.39, est: 12, cam: 0, prazo: 5, sug: 37 },
  { p: "Placa PS 2 mm", sku: "PS-2", fat: 3700, cF: "A", mar: 1480, cM: "A", vend: 74, un: "chapa", dia: 0.82, est: 6, cam: 10, prazo: 15, sug: 21 },
  { p: "Tinta solvente ciano 1 L", sku: "TIN-C1", fat: 4100, cF: "A", mar: null, cM: "C", vend: 41, un: "L", dia: 0.46, est: 3, cam: 0, prazo: 20, sug: 20 },
  { p: "Ilhós latão 10 mm (cento)", sku: "ILH-10", fat: 3150, cF: "B", mar: 1890, cM: "A", vend: 315, un: "cento", dia: 3.5, est: 400, cam: 0, prazo: 3, sug: 0 },
  { p: "Bastão de madeira 1 m", sku: "BST-1", fat: 1520, cF: "B", mar: 610, cM: "B", vend: 190, un: "un", dia: 2.11, est: 20, cam: 0, prazo: null, sug: 44 },
  { p: "Fita dupla face 19 mm", sku: "FIT-19", fat: 860, cF: "C", mar: 300, cM: "C", vend: 86, un: "rolo", dia: 0.96, est: 60, cam: 0, prazo: 7, sug: 0 },
  { p: "Cordão de nylon 3 mm", sku: "COR-3", fat: 450, cF: "C", mar: -40, cM: "C", vend: 45, un: "rolo", dia: 0.5, est: 25, cam: 0, prazo: 7, sug: 0 },
];
const brl = (v) => v.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
const n = (v, d = 0) => v.toLocaleString("pt-BR", { minimumFractionDigits: d, maximumFractionDigits: d });
let porMargem = false, filtro = "todos";
const sel = new Set();

function desenhar() {
  const curva = (i) => porMargem ? i.cM : i.cF;
  let lista = [...ITENS].sort((a, b) => porMargem ? (b.mar ?? -1e9) - (a.mar ?? -1e9) : b.fat - a.fat);
  if (filtro === "repor") lista = lista.filter((i) => i.sug > 0);
  else if (filtro !== "todos") lista = lista.filter((i) => curva(i) === filtro);
  document.getElementById("corpo").innerHTML = lista.map((i) => {
    const dura = i.dia > 0 ? Math.round(i.est / i.dia) + " d" : "—";
    const marg = i.mar === null ? '<span class="mute" title="Venda sem lote amarrado">sem lote</span>'
      : '<span class="' + (i.mar < 0 ? "neg" : "") + '">' + brl(i.mar) + '</span>';
    return '<tr><td>' + (i.sug > 0 ? '<input type="checkbox" data-sku="' + i.sku + '" aria-label="Selecionar ' + i.p + '"' + (sel.has(i.sku) ? " checked" : "") + '>' : "") + '</td>' +
      '<td class="prod">' + i.p + '<small>' + i.sku + '</small></td>' +
      '<td><span class="curva ' + curva(i) + '">' + curva(i) + '</span></td>' +
      '<td class="r num">' + brl(i.fat) + '</td><td class="r num">' + marg + '</td>' +
      '<td class="r num">' + n(i.vend) + ' ' + i.un + '</td><td class="r num">' + n(i.dia, 2) + '</td>' +
      '<td class="r num">' + n(i.est) + '</td><td class="r num">' + (i.cam ? n(i.cam) : '<span class="mute">—</span>') + '</td>' +
      '<td class="r num">' + (i.prazo === null ? '<span class="mute" title="Sem prazo cadastrado: conta 0">sem prazo</span>' : i.prazo + " d") + '</td>' +
      '<td class="r num">' + dura + '</td>' +
      '<td class="r num"><span class="sug' + (i.sug > 0 ? "" : " zero") + '">' + (i.sug > 0 ? n(i.sug) + " " + i.un : "—") + '</span></td></tr>';
  }).join("");
  document.getElementById("contagem").textContent = lista.length + " de " + ITENS.length + " produtos vendidos no período";
  document.getElementById("levar").disabled = sel.size === 0;
  document.getElementById("levar").textContent = sel.size ? "Levar " + sel.size + " para a requisição de compra" : "Levar para a requisição de compra";
}
document.getElementById("corpo").addEventListener("change", (e) => {
  const sku = e.target.dataset.sku; if (!sku) return;
  e.target.checked ? sel.add(sku) : sel.delete(sku); desenhar();
});
document.getElementById("todosSel").addEventListener("change", (e) => {
  ITENS.filter((i) => i.sug > 0).forEach((i) => e.target.checked ? sel.add(i.sku) : sel.delete(i.sku)); desenhar();
});
for (const [id, v] of [["porFat", false], ["porMar", true]]) document.getElementById(id).addEventListener("click", () => {
  porMargem = v;
  document.getElementById("porFat").setAttribute("aria-pressed", String(!v));
  document.getElementById("porMar").setAttribute("aria-pressed", String(v));
  desenhar();
});
document.querySelectorAll(".chip").forEach((c) => c.addEventListener("click", () => {
  filtro = c.dataset.f; document.querySelectorAll(".chip").forEach((x) => x.setAttribute("aria-pressed", String(x === c))); desenhar();
}));
desenhar();
</script>
`;
