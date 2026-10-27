// crm-segmentos-page.jsx — Segmentos de clientes (RFM). Protótipo de 27/10/2026 (PR #9978), fonte desta tela no shell.
// O HTML da tela vai literal abaixo; o prototipo-html-page.jsx o monta numa shadow root (CSS isolado,
// tema do shell, scripts e onclick funcionando). Para editar: é HTML comum, mas dentro deste texto
// crase, ${ e barra invertida vão com barra na frente (\`  \${  \\).
(window.PROTO_HTML = window.PROTO_HTML || {})["crm-segmentos"] = `<title>Segmentos de clientes</title>
<meta name="claude:viewport-fit-sides" content="cover">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
<style>
/* Protótipo US-CRM-098 (RFM). O cashback (US-CRM-093) saiu: os pontos nativos já cobrem (decisão [W] 2026-10-27). Tokens = .cockpit de
   prototipo-ui/design-system/colors_and_type.css (ADR 0438 · DS no git).
   Layout: PT-04 Dashboard — cabeçalho, faixa de KPIs, duas seções de trabalho. Dados de EXEMPLO. */
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
  --radius: 8px; --radius-sm: 6px; --row-h: 30px;
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
  display: grid; gap: 16px; }
.num { font-family: var(--font-mono); font-variant-numeric: tabular-nums; }
header.ph { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px;
  border-bottom: 1px solid var(--border); padding-bottom: 12px; }
.crumb { color: var(--text-mute); font-size: 12px; }
h1 { font-size: 20px; font-weight: 600; margin: 2px 0 0; text-wrap: balance; }
h2 { font-size: 14px; font-weight: 600; margin: 0; }
.sub { color: var(--text-dim); font-size: 12px; margin: 2px 0 0; }
.btn { font: inherit; font-size: 12.5px; border: 1px solid var(--border); background: var(--surface); color: var(--text);
  border-radius: var(--radius-sm); padding: 6px 12px; cursor: pointer; }
.btn.primary { background: var(--accent); border-color: var(--accent); color: var(--accent-fg); font-weight: 500; }
.btn:focus-visible, input:focus-visible, select:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
.flag { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--warn); background: var(--warn-soft);
  border-radius: 999px; padding: 3px 10px; }
.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
.kpi { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px 14px; display: grid; gap: 4px; }
.kpi .lb { color: var(--text-dim); font-size: 11.5px; text-transform: uppercase; letter-spacing: .04em; }
.kpi .v { font-size: 20px; font-weight: 600; }
.kpi .ft { color: var(--text-mute); font-size: 11.5px; }
.grid2 { display: grid; grid-template-columns: minmax(0, 340px) minmax(0, 1fr); gap: 16px; align-items: start; }
@media (max-width: 860px) { .grid2 { grid-template-columns: minmax(0, 1fr); } }
.panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); min-width: 0; }
.panel > .hd { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px;
  padding: 12px 14px; border-bottom: 1px solid var(--border-2); }
.panel > .bd { padding: 14px; display: grid; gap: 12px; }
.field { display: grid; gap: 4px; }
.field label { font-size: 12px; color: var(--text-dim); }
.field .row { display: flex; align-items: center; gap: 6px; }
input[type=text], select { font: inherit; border: 1px solid var(--border); background: var(--surface); color: var(--text);
  border-radius: var(--radius-sm); padding: 6px 8px; width: 100%; min-width: 0; }
.hint { color: var(--text-mute); font-size: 11.5px; line-height: 1.45; }
.toggle { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.sw { width: 34px; height: 20px; border-radius: 999px; background: var(--border); position: relative; border: 0; cursor: pointer; }
.sw::after { content: ""; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: var(--surface); transition: left .15s; }
.sw[aria-checked="true"] { background: var(--accent); }
.sw[aria-checked="true"]::after { left: 16px; }
.exemplo { background: var(--bg-2); border-radius: var(--radius-sm); padding: 10px 12px; font-size: 12px; display: grid; gap: 4px; }
.exemplo .l { display: flex; justify-content: space-between; gap: 8px; }
.tw { overflow-x: auto; }
table { border-collapse: collapse; width: 100%; font-size: 12.5px; }
th { text-align: left; font-weight: 500; color: var(--text-dim); font-size: 11.5px; padding: 6px 14px; border-bottom: 1px solid var(--border); white-space: nowrap; }
td { padding: 0 14px; height: var(--row-h); border-bottom: 1px solid var(--border-2); white-space: nowrap; }
td.r, th.r { text-align: right; }
.pill { display: inline-block; border-radius: 999px; padding: 1px 8px; font-size: 11.5px; font-weight: 500; }
.p-ativo { color: var(--pos); background: var(--pos-soft); }
.p-vence { color: var(--warn); background: var(--warn-soft); }
.p-venc { color: var(--neg); background: var(--neg-soft); }
.p-usado { color: var(--text-dim); background: var(--bg-2); }
.segs { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; }
.seg { border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px 12px; background: var(--surface);
  display: grid; gap: 4px; text-align: left; font: inherit; color: var(--text); cursor: pointer; }
.seg[aria-pressed="true"] { border-color: var(--accent); background: var(--accent-soft); }
.seg .n { font-size: 18px; font-weight: 600; }
.seg .d { color: var(--text-mute); font-size: 11.5px; }
.bar { height: 4px; border-radius: 2px; background: var(--bg-2); overflow: hidden; }
.bar > i { display: block; height: 100%; background: var(--accent); }
.notas { display: inline-flex; gap: 3px; }
.notas b { font-family: var(--font-mono); font-weight: 500; font-size: 11px; width: 18px; height: 18px; border-radius: 4px;
  display: grid; place-items: center; background: var(--bg-2); color: var(--text-dim); }
.notas b.hi { background: var(--accent-soft); color: var(--accent); }
.foot { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px; padding: 10px 14px; color: var(--text-mute); font-size: 12px; }
@media (prefers-reduced-motion: reduce) { .sw::after { transition: none; } }
</style>

<div class="page">
  <header class="ph">
    <div>
      <div class="crumb">CRM › Segmentos</div>
      <h1>Segmentos de clientes</h1>
      <p class="sub">Clientes agrupados por recência, frequência e valor. Os pontos de fidelidade ficam em Configurações › Empresa, e o resgate no caixa.</p>
    </div>
    <span class="flag">Protótipo · dados de exemplo · empresa fictícia</span>
  </header>

  <section class="panel" aria-labelledby="t-rfm">
    <div class="hd">
      <div><h2 id="t-rfm">Segmentos RFM</h2><p class="sub">Recência da última compra, compras e valor nos últimos 12 meses. Nota de 1 a 5 por quintil dentro da empresa. Calculado hoje às 04:30.</p></div>
      <button type="button" class="btn primary" id="campanha">Criar campanha para Em risco</button>
    </div>
    <div class="bd">
      <div class="segs" id="segs"></div>
    </div>
    <div class="tw">
      <table>
        <thead><tr><th>Cliente</th><th class="r">Última compra</th><th class="r">Compras no ano</th><th class="r">Valor no ano</th><th>Notas R F M</th></tr></thead>
        <tbody id="lista"></tbody>
      </table>
    </div>
    <div class="foot"><span id="rodape"></span><span>Só entram na campanha os clientes com consentimento de WhatsApp.</span></div>
  </section>
</div>

<script>
const SEGS = [
  ['campeoes', 'Campeões', 'Compram muito e há pouco tempo', 64],
  ['leais', 'Leais', 'Compram com frequência', 118],
  ['novos', 'Novos', 'Primeira compra recente', 52],
  ['promissores', 'Promissores', 'Recentes, ainda compram pouco', 97],
  ['em_risco', 'Em risco', 'Eram bons e sumiram', 41],
  ['hibernando', 'Hibernando', 'Pouca compra e faz tempo', 76],
  ['perdidos', 'Perdidos', 'Não compram há muito tempo', 133],
];
const CLIENTES = {
  em_risco: [['Auto Peças Serrano', 142, 7, '4.380,00', [2, 4, 5]], ['Fernanda Lopes', 118, 5, '1.920,00', [2, 4, 4]], ['Studio Lume', 165, 6, '2.715,50', [2, 4, 4]]],
  campeoes: [['Gráfica Ponto Azul', 3, 18, '21.480,00', [5, 5, 5]], ['Marina Costa', 6, 11, '3.960,00', [5, 5, 4]], ['Ateliê Ramos', 9, 14, '8.110,00', [5, 5, 5]]],
};
const total = SEGS.reduce((s, x) => s + x[3], 0);
let atual = 'em_risco';
function render() {
  document.getElementById('segs').innerHTML = SEGS.map(([k, n, d, q]) =>
    \`<button type="button" class="seg" aria-pressed="\${k === atual}" data-k="\${k}"><span>\${n}</span><span class="n num">\${q}</span>\` +
    \`<span class="bar"><i style="width:\${(q / total * 100).toFixed(1)}%"></i></span><span class="d">\${d}</span></button>\`).join('');
  const rows = CLIENTES[atual] || CLIENTES.em_risco;
  document.getElementById('lista').innerHTML = rows.map(([c, r, f, m, notas]) =>
    \`<tr><td>\${c}</td><td class="r num">há \${r} dias</td><td class="r num">\${f}</td><td class="r num">R$ \${m}</td>\` +
    \`<td><span class="notas">\${notas.map(v => \`<b class="\${v >= 4 ? 'hi' : ''}">\${v}</b>\`).join('')}</span></td></tr>\`).join('');
  const seg = SEGS.find(s => s[0] === atual);
  document.getElementById('campanha').textContent = \`Criar campanha para \${seg[1]}\`;
  document.getElementById('rodape').textContent = \`\${seg[3]} clientes em \${seg[1]} · \${total} clientes com compra\`;
}
document.getElementById('segs').addEventListener('click', e => {
  const b = e.target.closest('.seg'); if (b) { atual = b.dataset.k; render(); }
});
render();
</script>
`;
