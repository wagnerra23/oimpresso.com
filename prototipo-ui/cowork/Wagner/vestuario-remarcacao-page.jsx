// vestuario-remarcacao-page.jsx — Remarcação por coleção. Protótipo de 27/10/2026 (PR #10008), fonte desta tela no shell.
// O HTML da tela vai literal abaixo; o prototipo-html-page.jsx o monta numa shadow root (CSS isolado,
// tema do shell, scripts e onclick funcionando). Para editar: é HTML comum, mas dentro deste texto
// crase, ${ e barra invertida vão com barra na frente (\`  \${  \\).
(window.PROTO_HTML = window.PROTO_HTML || {})["vest-remarcacao"] = `<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Remarcação por coleção</title>
<!--
  Protótipo da tela /vestuario/remarcacao (US-VEST-032 · decisão [W] 2026-10-27 · ADR 0438 D1/D2).
  Fonte de design desta tela: este arquivo. Tokens copiados do DS canônico
  (prototipo-ui/design-system/colors_and_type.css, bloco cockpit claro/escuro), sem token novo.
  Monitor alvo 1280px (ROTA LIVRE). Dados fictícios.
-->
<style>
:root{--bg:oklch(0.985 0.003 90);--bg-2:oklch(0.965 0.004 90);--surface:#fff;--border:oklch(0.90 0.004 90);
--text:oklch(0.22 0.01 80);--text-dim:oklch(0.50 0.01 80);--accent:oklch(0.55 0.15 295);--accent-soft:oklch(0.95 0.04 295);
--accent-fg:#fff;--ok:oklch(0.52 0.13 155);--ok-soft:oklch(0.95 0.04 155);--warn:oklch(0.55 0.14 70);--warn-soft:oklch(0.96 0.05 80);
--danger:oklch(0.55 0.18 25)}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){--bg:oklch(0.26 0.006 240);--bg-2:oklch(0.23 0.006 240);
--surface:oklch(0.30 0.008 240);--border:oklch(0.34 0.008 240);--text:oklch(0.94 0.005 90);--text-dim:oklch(0.72 0.005 90);
--accent-soft:oklch(0.35 0.06 295);--ok-soft:oklch(0.33 0.05 155);--warn-soft:oklch(0.35 0.05 80)}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:14px/1.45 Inter,system-ui,sans-serif}
.page{max-width:1232px;margin:0 auto;padding:20px 16px 48px}
header.h{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;margin-bottom:16px}
h1{font-size:20px;margin:0}.sub{color:var(--text-dim);margin:2px 0 0}
.card{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:14px}
.card h2{font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:var(--text-dim);margin:0 0 12px}
.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
label{display:flex;flex-direction:column;gap:4px;font-size:12px;color:var(--text-dim)}
input,select{height:36px;border:1px solid var(--border);border-radius:8px;padding:0 10px;background:var(--surface);color:var(--text);font:inherit}
.op{display:flex;gap:12px;align-items:flex-end;margin-top:12px;flex-wrap:wrap}
.seg{display:inline-flex;border:1px solid var(--border);border-radius:8px;overflow:hidden;height:36px}
.seg button{border:0;background:transparent;padding:0 12px;color:var(--text);font:inherit;cursor:pointer}
.seg button[aria-pressed="true"]{background:var(--accent-soft);color:var(--accent);font-weight:600}
.btn{height:36px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);padding:0 14px;font:inherit;cursor:pointer}
.btn.primary{background:var(--accent);border-color:var(--accent);color:var(--accent-fg);font-weight:600}
.btn:disabled{opacity:.5;cursor:not-allowed}
.chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}.chip{background:var(--bg-2);border-radius:999px;padding:3px 10px;font-size:12px}
table{width:100%;border-collapse:collapse;font-variant-numeric:tabular-nums}
th{text-align:left;font-size:12px;color:var(--text-dim);font-weight:500;padding:8px;border-bottom:1px solid var(--border)}
td{padding:8px;border-bottom:1px solid var(--border)}td.n,th.n{text-align:right}
.antes{color:var(--text-dim);text-decoration:line-through}.depois{font-weight:600}.down{color:var(--ok)}.up{color:var(--warn)}
.bar{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:12px}
.banner{border-radius:8px;padding:10px 12px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;gap:12px}
.banner.ok{background:var(--ok-soft)}.banner.warn{background:var(--warn-soft)}
.status{font-size:12px;border-radius:999px;padding:2px 8px;background:var(--bg-2)}.status.ap{background:var(--accent-soft);color:var(--accent)}
dialog{border:1px solid var(--border);border-radius:12px;background:var(--surface);color:var(--text);max-width:440px}
</style>
</head>
<body>
<div class="page" data-screen-label="Comercial · Vestuário · remarcação">
  <header class="h">
    <div><h1>Remarcação por coleção</h1><p class="sub">Muda o preço de etiqueta de várias peças de uma vez. Você vê a prévia antes de gravar, e pode reverter depois.</p></div>
  </header>

  <div id="feito" class="banner ok" hidden>
    <span><b>Remarcação gravada.</b> <span id="feito-txt"></span></span>
    <span><button class="btn" onclick="alert('Abre /vestuario/etiquetas com as peças remarcadas')">Imprimir etiquetas</button></span>
  </div>

  <section class="card" data-contract="filtro">
    <h2>1 · Quais peças</h2>
    <div class="grid">
      <label>Categoria<select><option>Todas</option><option selected>Camisetas</option><option>Vestidos</option></select></label>
      <label>Subcategoria<select><option>Todas</option></select></label>
      <label>Marca<select><option>Todas</option></select></label>
      <label>Coleção (nome contém)<input value="Verão24" placeholder="ex.: Verão24"></label>
    </div>
    <div class="op">
      <label>Novo preço<span class="seg"><button aria-pressed="true" id="b-pct" onclick="modo('pct')">Percentual</button><button aria-pressed="false" id="b-val" onclick="modo('val')">Valor fixo</button></span></label>
      <label><span id="l-valor">Percentual (negativo baixa)</span><input id="valor" value="-30" style="width:140px"></label>
      <button class="btn primary" onclick="previa()">Ver prévia</button>
    </div>
  </section>

  <section class="card" data-contract="previa" id="previa" hidden>
    <h2>2 · Prévia — nada foi gravado ainda</h2>
    <div class="chips"><span class="chip" id="c-qtd"></span><span class="chip">2 sem mudança, fora da lista</span><span class="chip">Tabelas de preço por grupo não mudam</span></div>
    <table><thead><tr><th>Produto</th><th>Variação</th><th>SKU</th><th class="n">Antes</th><th class="n">Depois</th><th class="n">Diferença</th></tr></thead><tbody id="linhas"></tbody></table>
    <div class="bar"><span class="sub">Se algum preço mudar antes de você gravar, a gravação é recusada e a prévia precisa ser refeita.</span>
      <button class="btn primary" onclick="document.getElementById('conf').showModal()">Gravar remarcação</button></div>
  </section>

  <section class="card" data-contract="historico">
    <h2>Remarcações anteriores</h2>
    <table><thead><tr><th>Quando</th><th>Nome</th><th>Operação</th><th class="n">Peças</th><th>Situação</th><th></th></tr></thead>
    <tbody id="hist"><tr><td>20/10/2026 16:12</td><td>Liquidação Inverno24</td><td>−40%</td><td class="n">86</td><td><span class="status">revertida · 3 conflitos</span></td><td></td></tr></tbody></table>
  </section>

  <dialog id="conf">
    <h3 style="margin-top:0">Gravar a remarcação?</h3>
    <p id="conf-txt"></p>
    <label>Nome da remarcação<input id="nome" value="Liquidação Verão24"></label>
    <p class="sub">O preço de antes fica guardado. Você pode reverter pela lista de remarcações.</p>
    <div class="bar"><button class="btn" onclick="this.closest('dialog').close()">Cancelar</button><button class="btn primary" onclick="gravar()">Gravar</button></div>
  </dialog>
</div>
<script>
const PECAS=[["Verão24 Camiseta básica","P","CAM-VB-P",59.9],["Verão24 Camiseta básica","M","CAM-VB-M",59.9],["Verão24 Camiseta básica","G","CAM-VB-G",59.9],
["Verão24 Regata canelada","Único","REG-VC-U",49.9],["Verão24 Camiseta estampa","M","CAM-VE-M",79.9],["Verão24 Camiseta estampa","G","CAM-VE-G",79.9]];
let m='pct';const brl=n=>n.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
function modo(x){m=x;document.getElementById('b-pct').setAttribute('aria-pressed',x==='pct');document.getElementById('b-val').setAttribute('aria-pressed',x==='val');
document.getElementById('l-valor').textContent=x==='pct'?'Percentual (negativo baixa)':'Novo preço (R$)';document.getElementById('valor').value=x==='pct'?'-30':'39,90'}
function calc(){const v=parseFloat(document.getElementById('valor').value.replace(',','.'))||0;
return PECAS.map(([p,va,s,a])=>{const d=m==='pct'?Math.round(a*(1+v/100)*100)/100:Math.round(v*100)/100;return{p,va,s,a,d}})}
function previa(){const r=calc();document.getElementById('linhas').innerHTML=r.map(x=>\`<tr><td>\${x.p}</td><td>\${x.va}</td><td>\${x.s}</td><td class="n antes">\${brl(x.a)}</td><td class="n depois">\${brl(x.d)}</td><td class="n \${x.d<x.a?'down':'up'}">\${brl(x.d-x.a)}</td></tr>\`).join('');
document.getElementById('c-qtd').textContent=\`\${r.length} variações mudam de preço\`;document.getElementById('conf-txt').textContent=\`\${r.length} variações vão mudar de preço.\`;document.getElementById('previa').hidden=false}
function gravar(){document.getElementById('conf').close();const n=calc().length;document.getElementById('previa').hidden=true;
document.getElementById('feito').hidden=false;document.getElementById('feito-txt').textContent=\`\${n} variações remarcadas.\`;
document.getElementById('hist').insertAdjacentHTML('afterbegin',\`<tr><td>27/10/2026 10:05</td><td>\${document.getElementById('nome').value}</td><td>\${m==='pct'?document.getElementById('valor').value+'%':'R$ '+document.getElementById('valor').value}</td><td class="n">\${n}</td><td><span class="status ap">aplicada</span></td><td><button class="btn" onclick="alert('Reverter: devolve o preço de antes; peça mexida depois fica como está')">Reverter</button></td></tr>\`)}
</script>
</body>
</html>
`;
