// financeiro-centro-custo-orcado-page.jsx — Centro de custo e orçado × realizado. Protótipo de 27/10/2026 (PR #9956), fonte desta tela no shell.
// O HTML da tela vai literal abaixo; o prototipo-html-page.jsx o monta numa shadow root (CSS isolado,
// tema do shell, scripts e onclick funcionando). Para editar: é HTML comum, mas dentro deste texto
// crase, ${ e barra invertida vão com barra na frente (\`  \${  \\).
(window.PROTO_HTML = window.PROTO_HTML || {})["fin-cc-orcado"] = `<title>Centro de custo e orçamento</title>
<meta name="claude:viewport-fit-sides" content="cover">
<!--
  Protótipo — US-FIN-075 (centro de custo com rateio no título) + US-FIN-076 (orçado × realizado).
  Fonte de design no git (ADR 0438 D1). Gerado ancorado no DS canon: tokens do bloco \`.cockpit\`
  de prototipo-ui/design-system/colors_and_type.css, PT-01 Lista para o cadastro, seção de drawer
  do título para o rateio. Dados de exemplo, fictícios.
-->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap">
<style>
/* Layout: casca do cockpit (faixa de abas) + uma tela por aba, cartão PT-01 de largura cheia. */
:root {
  --font-sans: "IBM Plex Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
  --font-mono: "IBM Plex Mono", ui-monospace, "SF Mono", Menlo, monospace;
  --fs-1: 10.5px; --fs-2: 11.5px; --fs-3: 12.5px; --fs-4: 13.5px; --fs-5: 15px;
  --bg: oklch(0.985 0.003 90); --bg-2: oklch(0.965 0.004 90); --surface: #ffffff;
  --border: oklch(0.90 0.004 90); --border-2: oklch(0.93 0.004 90);
  --text: oklch(0.22 0.01 80); --text-dim: oklch(0.50 0.01 80); --text-mute: oklch(0.555 0.01 80);
  --accent: oklch(0.55 0.15 295); --accent-2: oklch(0.62 0.15 295); --accent-soft: oklch(0.95 0.04 295); --accent-fg: #ffffff;
  --pos: oklch(0.50 0.12 150); --pos-soft: oklch(0.95 0.075 150);
  --neg: oklch(0.55 0.18 25); --neg-soft: oklch(0.955 0.055 25);
  --warn: oklch(0.56 0.12 70); --warn-soft: oklch(0.955 0.072 75);
  --radius: 8px; --radius-sm: 6px; --shadow-soft: 0 1px 2px rgba(0,0,0,.04);
}
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) {
  --bg: oklch(0.26 0.006 240); --bg-2: oklch(0.23 0.006 240); --surface: oklch(0.30 0.008 240);
  --border: oklch(0.34 0.008 240); --border-2: oklch(0.31 0.008 240);
  --text: oklch(0.94 0.005 90); --text-dim: oklch(0.72 0.005 90); --text-mute: oklch(0.67 0.005 90);
  --accent-soft: oklch(0.33 0.09 295); --pos: oklch(0.76 0.18 150); --neg: oklch(0.74 0.19 25); --warn: oklch(0.82 0.16 75);
  --pos-soft: oklch(0.34 0.06 150); --neg-soft: oklch(0.34 0.07 25); --warn-soft: oklch(0.35 0.06 75);
  color-scheme: dark; } }
:root[data-theme="dark"] {
  --bg: oklch(0.26 0.006 240); --bg-2: oklch(0.23 0.006 240); --surface: oklch(0.30 0.008 240);
  --border: oklch(0.34 0.008 240); --border-2: oklch(0.31 0.008 240);
  --text: oklch(0.94 0.005 90); --text-dim: oklch(0.72 0.005 90); --text-mute: oklch(0.67 0.005 90);
  --accent-soft: oklch(0.33 0.09 295); --pos: oklch(0.76 0.18 150); --neg: oklch(0.74 0.19 25); --warn: oklch(0.82 0.16 75);
  --pos-soft: oklch(0.34 0.06 150); --neg-soft: oklch(0.34 0.07 25); --warn-soft: oklch(0.35 0.06 75);
  color-scheme: dark;
}
body { background: var(--bg); color: var(--text); font-family: var(--font-sans); font-size: var(--fs-4); }
.wrap { max-width: 1180px; margin: 0 auto; padding-block: 20px 48px;
  padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px)); }
.eyebrow { font-size: var(--fs-1); text-transform: uppercase; letter-spacing: .12em; color: var(--text-dim); font-weight: 500; }
h1 { font-size: 20px; font-weight: 600; margin: 2px 0 14px; text-wrap: balance; }
.tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--border); margin-bottom: 16px; overflow-x: auto; }
.tab { appearance: none; background: none; border: 0; border-bottom: 2px solid transparent; padding: 8px 12px; font: inherit;
  font-size: var(--fs-3); color: var(--text-dim); cursor: pointer; white-space: nowrap; }
.tab[aria-selected="true"] { color: var(--text); border-bottom-color: var(--accent); font-weight: 500; }
.tab:focus-visible, .btn:focus-visible, input:focus-visible, select:focus-visible { outline: 2px solid var(--accent); outline-offset: 1px; }
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-soft); min-width: 0; }
.card-h { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 12px 16px; border-bottom: 1px solid var(--border); }
.card-h .t { font-size: var(--fs-5); font-weight: 600; }
.card-h .sp { margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap; }
.btn { height: 32px; padding: 0 12px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface);
  color: var(--text-dim); font: inherit; font-size: var(--fs-3); cursor: pointer; white-space: nowrap; }
.btn:hover { color: var(--text); background: var(--bg-2); }
.btn.pri { background: var(--accent); border-color: var(--accent); color: var(--accent-fg); }
.btn.pri:hover { background: var(--accent-2); }
.btn.sm { height: 26px; padding: 0 8px; font-size: var(--fs-2); }
.btn:disabled { opacity: .5; cursor: not-allowed; }
input, select { height: 32px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--surface);
  color: var(--text); font: inherit; font-size: var(--fs-3); padding: 0 8px; min-width: 0; }
.scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: var(--fs-3); font-variant-numeric: tabular-nums; }
th { text-align: left; font-size: var(--fs-1); text-transform: uppercase; letter-spacing: .1em; color: var(--text-dim);
  font-weight: 500; padding: 8px 10px; background: var(--bg-2); border-bottom: 1px solid var(--border); white-space: nowrap; }
td { padding: 8px 10px; border-bottom: 1px solid var(--border-2); }
th.r, td.r { text-align: right; }
td.mono { font-family: var(--font-mono); font-size: var(--fs-2); color: var(--text-dim); }
tr.grp td { background: var(--bg-2); font-weight: 600; }
tr.tot td { font-weight: 600; border-top: 1px solid var(--border); }
.pill { display: inline-flex; align-items: center; gap: 5px; font-size: var(--fs-2); font-weight: 500; padding: 1px 8px; border-radius: 999px; }
.pill::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.pill.on { background: var(--pos-soft); color: var(--pos); } .pill.off { background: var(--bg-2); color: var(--text-mute); }
.mute { color: var(--text-mute); }
.pos { color: var(--pos); } .neg { color: var(--neg); } .warn { color: var(--warn); }
.link { background: none; border: 0; padding: 0; font: inherit; font-size: var(--fs-2); color: var(--text-dim); cursor: pointer; text-decoration: underline; text-underline-offset: 2px; }
.note { font-size: var(--fs-2); color: var(--text-dim); padding: 10px 16px; }
/* drawer do título (recorte) */
.drawer { max-width: 760px; margin-left: auto; }
.dl { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; padding: 14px 16px; border-bottom: 1px solid var(--border); }
.dl div span { display: block; font-size: var(--fs-1); text-transform: uppercase; letter-spacing: .1em; color: var(--text-dim); }
.dl div b { font-weight: 500; }
.sec { padding: 14px 16px; display: grid; gap: 10px; }
.sec-h { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.sec-h .t { font-weight: 600; }
.seg { display: inline-flex; border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; margin-left: auto; }
.seg button { border: 0; background: var(--surface); padding: 0 10px; height: 28px; font: inherit; font-size: var(--fs-2); color: var(--text-dim); cursor: pointer; }
.seg button[aria-pressed="true"] { background: var(--accent-soft); color: var(--text); font-weight: 500; }
.rline { display: grid; grid-template-columns: minmax(0, 1fr) 110px 130px 28px; gap: 8px; align-items: center; }
.rline input { text-align: right; width: 100%; }
.rline .calc { text-align: right; font-size: var(--fs-3); color: var(--text-dim); }
.x { background: none; border: 0; color: var(--text-mute); cursor: pointer; font-size: 16px; }
.soma { display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: center; padding: 10px 12px; border-radius: var(--radius-sm); font-size: var(--fs-3); }
.soma.ok { background: var(--pos-soft); color: var(--pos); } .soma.bad { background: var(--neg-soft); color: var(--neg); }
.soma .sp { margin-left: auto; }
.bar { position: relative; height: 6px; background: var(--bg-2); border-radius: 3px; min-width: 80px; }
.bar i { position: absolute; left: 0; top: 0; bottom: 0; border-radius: 3px; background: var(--text-mute); }
.bar i.neg { background: var(--neg); } .bar i.pos { background: var(--pos); }
.bar b { position: absolute; top: -3px; bottom: -3px; width: 2px; background: var(--text); left: 100%; }
td input.orc { width: 110px; text-align: right; height: 28px; }
@media (max-width: 560px) { .rline { grid-template-columns: minmax(0, 1fr) 80px 28px; } .rline .calc { display: none; } }
@media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
</style>

<div class="wrap">
  <div class="eyebrow">Financeiro · protótipo US-FIN-075 / US-FIN-076</div>
  <h1>Centro de custo e orçamento</h1>
  <div class="tabs" role="tablist">
    <button class="tab" role="tab" id="t-cad" aria-selected="true" data-v="cad">Centros de custo</button>
    <button class="tab" role="tab" id="t-rat" aria-selected="false" data-v="rat">Rateio no título</button>
    <button class="tab" role="tab" id="t-orc" aria-selected="false" data-v="orc">Orçado × realizado</button>
  </div>

  <!-- 1 · Cadastro (PT-01 Lista) -->
  <section id="v-cad">
    <div class="card">
      <div class="card-h">
        <div><div class="eyebrow">Cadastros</div><div class="t">Centros de custo</div></div>
        <div class="sp">
          <input id="busca" placeholder="Buscar centro…" aria-label="Buscar centro de custo">
          <button class="btn pri" id="novo">+ Novo centro</button>
        </div>
      </div>
      <div class="scroll"><table>
        <thead><tr><th style="width:90px">Código</th><th>Nome</th><th style="width:110px">Situação</th>
          <th class="r" style="width:120px">Títulos no mês</th><th class="r" style="width:150px">Rateado no mês</th><th style="width:120px"></th></tr></thead>
        <tbody id="cad-body"></tbody>
      </table></div>
      <div class="note">Centro com título rateado não pode ser excluído, só inativado. Inativo some da escolha no título, mas o histórico continua nos relatórios.</div>
    </div>
  </section>

  <!-- 2 · Rateio no drawer do título -->
  <section id="v-rat" hidden>
    <div class="card drawer">
      <div class="card-h"><div><div class="eyebrow">A pagar · #000842</div><div class="t">Aluguel do galpão — outubro</div></div></div>
      <div class="dl">
        <div><span>Valor</span><b id="rat-total-lbl"></b></div>
        <div><span>Vencimento</span><b>10/11/2026</b></div>
        <div><span>Competência</span><b>10/2026</b></div>
        <div><span>Plano de contas</span><b>4.2.01 Aluguel</b></div>
      </div>
      <div class="sec">
        <div class="sec-h">
          <span class="t">Rateio por centro de custo</span>
          <span class="mute" style="font-size:var(--fs-2)">opcional</span>
          <div class="seg" role="group" aria-label="Modo do rateio">
            <button id="m-pct" aria-pressed="true">Por %</button><button id="m-val" aria-pressed="false">Por valor</button>
          </div>
        </div>
        <div id="rat-lines" style="display:grid; gap:8px"></div>
        <div style="display:flex; gap:8px; flex-wrap:wrap">
          <button class="btn sm" id="add">+ Adicionar centro</button>
          <button class="btn sm" id="dividir">Dividir igual</button>
          <button class="link" id="limpar" style="margin-left:auto">Remover rateio</button>
        </div>
        <div id="soma" class="soma"></div>
        <div class="note" style="padding:0">No modo %, os centavos que sobram do arredondamento vão para as linhas de maior resto. A soma das linhas é sempre o valor do título.</div>
      </div>
      <div class="card-h" style="border-top:1px solid var(--border); border-bottom:0">
        <div class="sp"><button class="btn">Cancelar</button><button class="btn pri" id="salvar">Salvar título</button></div>
      </div>
    </div>
  </section>

  <!-- 3 · Orçado × realizado -->
  <section id="v-orc" hidden>
    <div class="card">
      <div class="card-h">
        <div><div class="eyebrow">Relatórios</div><div class="t">Orçado × realizado</div></div>
        <div class="sp">
          <select id="mes" aria-label="Mês"><option>10/2026</option><option>09/2026</option><option>08/2026</option></select>
          <select id="cc" aria-label="Centro de custo"></select>
          <div class="seg" role="group" aria-label="Regime"><button id="r-comp" aria-pressed="true">Competência</button><button id="r-caixa" aria-pressed="false">Caixa</button></div>
        </div>
      </div>
      <div class="scroll"><table>
        <thead><tr><th>Conta</th><th class="r">Orçado</th><th class="r">Realizado</th><th class="r">Desvio</th><th class="r">%</th><th style="width:140px">Consumo</th></tr></thead>
        <tbody id="orc-body"></tbody>
      </table></div>
      <div class="note">Orçado é por mês e por conta do plano de contas; com um centro escolhido, é o orçamento daquele centro. Realizado em competência soma os títulos não cancelados do mês (rateados pelo centro quando há filtro); em caixa, as baixas do mês. Clique no orçado para editar.</div>
    </div>
  </section>
</div>

<script>
const brl = (c) => (c < 0 ? "−" : "") + "R$ " + (Math.abs(c) / 100).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const pctTxt = (p) => (p / 10000).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 4 }) + "%";

/* ── dados de exemplo ── */
const CENTROS = [
  { id: 1, cod: "ADM", nome: "Administrativo", ativo: true, n: 14, v: 1854320 },
  { id: 2, cod: "PROD", nome: "Produção — impressão digital", ativo: true, n: 22, v: 4120015 },
  { id: 3, cod: "INST", nome: "Instalação externa", ativo: true, n: 9, v: 963000 },
  { id: 4, cod: "COM", nome: "Comercial", ativo: true, n: 7, v: 712580 },
  { id: 5, cod: "LJ2", nome: "Loja Centro (fechada)", ativo: false, n: 0, v: 0 },
];

/* ── 1 · cadastro ── */
function renderCad() {
  const q = document.getElementById("busca").value.toLowerCase();
  document.getElementById("cad-body").innerHTML = CENTROS.filter((c) => (c.nome + c.cod).toLowerCase().includes(q)).map((c) => \`
    <tr>
      <td class="mono">\${c.cod}</td><td>\${c.nome}</td>
      <td><span class="pill \${c.ativo ? "on" : "off"}">\${c.ativo ? "Ativo" : "Inativo"}</span></td>
      <td class="r">\${c.n || '<span class="mute">—</span>'}</td>
      <td class="r">\${c.v ? brl(c.v) : '<span class="mute">—</span>'}</td>
      <td class="r"><button class="link">editar</button> · <button class="link" data-tog="\${c.id}">\${c.ativo ? "inativar" : "ativar"}</button></td>
    </tr>\`).join("");
}
document.getElementById("busca").addEventListener("input", renderCad);
document.getElementById("cad-body").addEventListener("click", (e) => {
  const id = +e.target.dataset.tog; if (!id) return;
  const c = CENTROS.find((x) => x.id === id); c.ativo = !c.ativo; renderCad(); renderRat(); renderCcSelect();
});
document.getElementById("novo").addEventListener("click", () => {
  const id = CENTROS.length + 1;
  CENTROS.push({ id, cod: "NOVO" + id, nome: "Novo centro " + id, ativo: true, n: 0, v: 0 }); renderCad(); renderRat(); renderCcSelect();
});

/* ── 2 · rateio (mesma conta do RateioService: inteiros, piso + maior resto) ── */
const TOTAL = 1250001; // R$ 12.500,01
let modo = "pct";
let linhas = [{ cc: 1, pct: "40", val: "" }, { cc: 2, pct: "35", val: "" }, { cc: 3, pct: "25", val: "" }];
const paraInt = (s, casas) => {
  s = String(s || "").trim(); if (s.includes(",")) s = s.replace(/\\./g, "").replace(",", ".");
  if (!/^\\d*(\\.\\d*)?$/.test(s) || s === "" || s === ".") return NaN;
  const [i, f = ""] = s.split("."); const fr = (f + "0".repeat(casas + 1)).slice(0, casas + 1);
  return parseInt((i || "0") + fr.slice(0, casas), 10) + (+fr[casas] >= 5 ? 1 : 0);
};
function distribuir() {
  if (modo === "pct") {
    const p = linhas.map((l) => paraInt(l.pct, 4));
    const soma = p.reduce((a, b) => a + (b || 0), 0);
    const base = p.map((x) => Math.floor((TOTAL * (x || 0)) / 1e6));
    const resto = p.map((x, i) => (TOTAL * (x || 0)) - base[i] * 1e6);
    let sobra = soma === 1e6 ? TOTAL - base.reduce((a, b) => a + b, 0) : 0;
    [...resto.keys()].sort((a, b) => resto[b] - resto[a] || a - b).forEach((i) => { if (sobra > 0) { base[i]++; sobra--; } });
    return { ok: soma === 1e6 && p.every((x) => x > 0), valores: base, pcts: p, falta: 1e6 - soma };
  }
  const v = linhas.map((l) => paraInt(l.val, 2));
  const soma = v.reduce((a, b) => a + (b || 0), 0);
  return { ok: soma === TOTAL && v.every((x) => x > 0), valores: v, falta: TOTAL - soma };
}
function renderRat() {
  document.getElementById("rat-total-lbl").textContent = brl(TOTAL);
  document.getElementById("m-pct").setAttribute("aria-pressed", modo === "pct");
  document.getElementById("m-val").setAttribute("aria-pressed", modo === "val");
  const d = distribuir();
  const ativos = CENTROS.filter((c) => c.ativo);
  document.getElementById("rat-lines").innerHTML = linhas.length === 0
    ? '<div class="mute" style="font-size:var(--fs-3)">Sem rateio: o título entra como “sem centro” nos relatórios.</div>'
    : linhas.map((l, i) => \`
    <div class="rline">
      <select id="cc-\${i}" data-i="\${i}" data-k="cc" aria-label="Centro de custo da linha \${i + 1}">\${ativos.map((c) => \`<option value="\${c.id}" \${c.id === l.cc ? "selected" : ""}>\${c.cod} · \${c.nome}</option>\`).join("")}</select>
      \${modo === "pct"
        ? \`<input id="pct-\${i}" data-i="\${i}" data-k="pct" value="\${l.pct}" inputmode="decimal" aria-label="Percentual da linha \${i + 1}"><div class="calc">\${isNaN(d.valores[i]) ? "—" : brl(d.valores[i])}</div>\`
        : \`<input id="val-\${i}" data-i="\${i}" data-k="val" value="\${l.val}" inputmode="decimal" aria-label="Valor da linha \${i + 1}"><div class="calc">\${d.valores[i] > 0 ? pctTxt(Math.round((d.valores[i] * 1e6) / TOTAL)) : "—"}</div>\`}
      <button class="x" data-del="\${i}" aria-label="Remover linha \${i + 1}">×</button>
    </div>\`).join("");
  const s = document.getElementById("soma");
  if (linhas.length === 0) { s.className = "soma"; s.textContent = ""; s.hidden = true; return; }
  s.hidden = false;
  const somaV = d.valores.reduce((a, b) => a + (b || 0), 0);
  if (d.ok) { s.className = "soma ok"; s.innerHTML = \`<span>Soma \${modo === "pct" ? "100%" : brl(somaV)}</span><span class="sp">\${brl(somaV)} de \${brl(TOTAL)} ✓</span>\`; }
  else { s.className = "soma bad"; s.innerHTML = modo === "pct"
    ? \`<span>\${d.falta > 0 ? "Faltam " + pctTxt(d.falta) : "Passou " + pctTxt(-d.falta)}</span><span class="sp">Os percentuais precisam somar 100%</span>\`
    : \`<span>\${d.falta > 0 ? "Faltam " + brl(d.falta) : "Passou " + brl(-d.falta)}</span><span class="sp">Os valores precisam somar \${brl(TOTAL)}</span>\`; }
  document.getElementById("salvar").disabled = !d.ok;
}
const ratBox = document.getElementById("rat-lines");
ratBox.addEventListener("input", (e) => { const i = e.target.dataset.i; if (i === undefined) return; linhas[i][e.target.dataset.k] = e.target.value; const f = document.activeElement.id; renderRat(); const el = document.getElementById(f); if (el) { el.focus(); el.setSelectionRange?.(el.value.length, el.value.length); } });
ratBox.addEventListener("change", (e) => { if (e.target.dataset.k === "cc") { linhas[e.target.dataset.i].cc = +e.target.value; renderRat(); } });
ratBox.addEventListener("click", (e) => { if (e.target.dataset.del !== undefined) { linhas.splice(+e.target.dataset.del, 1); renderRat(); } });
document.getElementById("add").addEventListener("click", () => { const usado = linhas.map((l) => l.cc); const c = CENTROS.find((x) => x.ativo && !usado.includes(x.id)); if (c) { linhas.push({ cc: c.id, pct: "", val: "" }); renderRat(); } });
document.getElementById("dividir").addEventListener("click", () => {
  const n = linhas.length; if (!n) return;
  if (modo === "pct") { const b = Math.floor(1e6 / n); linhas.forEach((l, i) => l.pct = String(((i === n - 1 ? 1e6 - b * (n - 1) : b) / 1e4))); }
  else { const b = Math.floor(TOTAL / n); linhas.forEach((l, i) => l.val = ((i === n - 1 ? TOTAL - b * (n - 1) : b) / 100).toFixed(2).replace(".", ",")); }
  renderRat();
});
document.getElementById("limpar").addEventListener("click", () => { linhas = []; renderRat(); });
document.getElementById("m-pct").addEventListener("click", () => { modo = "pct"; renderRat(); });
document.getElementById("m-val").addEventListener("click", () => { const d = distribuir(); if (d.ok) linhas.forEach((l, i) => l.val = (d.valores[i] / 100).toFixed(2).replace(".", ",")); modo = "val"; renderRat(); });

/* ── 3 · orçado × realizado ── */
const CONTAS = [
  { g: "Receitas", tipo: "rec", cod: "3.1.01", nome: "Venda de produtos", orc: 6800000, comp: 7215040, caixa: 6402210 },
  { g: "Receitas", tipo: "rec", cod: "3.1.02", nome: "Serviços de instalação", orc: 1200000, comp: 963000, caixa: 1010500 },
  { g: "Despesas", tipo: "desp", cod: "4.1.01", nome: "Matéria-prima e insumos", orc: 2600000, comp: 2911430, caixa: 2450000 },
  { g: "Despesas", tipo: "desp", cod: "4.2.01", nome: "Aluguel", orc: 1250001, comp: 1250001, caixa: 1250001 },
  { g: "Despesas", tipo: "desp", cod: "4.2.04", nome: "Energia elétrica", orc: 380000, comp: 412790, caixa: 0 },
  { g: "Despesas", tipo: "desp", cod: "4.3.01", nome: "Folha e encargos", orc: 2100000, comp: 2054300, caixa: 2054300 },
  { g: "Despesas", tipo: "desp", cod: "4.4.02", nome: "Marketing", orc: 300000, comp: 0, caixa: 0 },
];
let regime = "comp";
function renderCcSelect() {
  const sel = document.getElementById("cc"); const v = sel.value;
  sel.innerHTML = \`<option value="">Todos os centros</option>\` + CENTROS.map((c) => \`<option value="\${c.id}">\${c.cod} · \${c.nome}\${c.ativo ? "" : " (inativo)"}</option>\`).join("");
  sel.value = v;
}
function linhaOrc(c, i) {
  const real = c[regime], desv = real - c.orc;
  const ruim = c.tipo === "rec" ? desv < 0 : desv > 0;
  const pct = c.orc ? Math.round((desv / c.orc) * 1000) / 10 : null;
  const cons = c.orc ? Math.min(real / c.orc, 1.5) : 0;
  return \`<tr>
    <td><span class="mono" style="font-family:var(--font-mono);font-size:var(--fs-2);color:var(--text-dim)">\${c.cod}</span> \${c.nome}</td>
    <td class="r"><input class="orc" id="orc-\${i}" data-o="\${i}" value="\${(c.orc / 100).toFixed(2).replace(".", ",")}" aria-label="Orçado de \${c.nome}"></td>
    <td class="r">\${real ? brl(real) : '<span class="mute">—</span>'}</td>
    <td class="r \${desv === 0 ? "mute" : ruim ? "neg" : "pos"}">\${desv === 0 ? "—" : (desv > 0 ? "+" : "") + brl(desv)}</td>
    <td class="r \${pct === null || desv === 0 ? "mute" : ruim ? "neg" : "pos"}">\${pct === null ? "—" : (pct > 0 ? "+" : "") + pct.toLocaleString("pt-BR") + "%"}</td>
    <td><div class="bar" title="\${Math.round(cons * 100)}% do orçado"><i class="\${ruim ? "neg" : "pos"}" style="width:\${(cons / 1.5) * 100}%"></i><b style="left:\${100 / 1.5}%"></b></div></td>
  </tr>\`;
}
function renderOrc() {
  document.getElementById("r-comp").setAttribute("aria-pressed", regime === "comp");
  document.getElementById("r-caixa").setAttribute("aria-pressed", regime === "caixa");
  let html = ""; const tot = {};
  ["Receitas", "Despesas"].forEach((g) => {
    html += \`<tr class="grp"><td colspan="6">\${g}</td></tr>\`;
    tot[g] = { orc: 0, real: 0 };
    CONTAS.forEach((c, i) => { if (c.g !== g) return; html += linhaOrc(c, i); tot[g].orc += c.orc; tot[g].real += c[regime]; });
  });
  const ro = tot.Receitas.orc - tot.Despesas.orc, rr = tot.Receitas.real - tot.Despesas.real, rd = rr - ro;
  html += \`<tr class="tot"><td>Resultado do mês</td><td class="r">\${brl(ro)}</td><td class="r">\${brl(rr)}</td><td class="r \${rd < 0 ? "neg" : "pos"}">\${(rd > 0 ? "+" : "") + brl(rd)}</td><td></td><td></td></tr>\`;
  document.getElementById("orc-body").innerHTML = html;
}
document.getElementById("orc-body").addEventListener("change", (e) => { const i = e.target.dataset.o; if (i === undefined) return; const v = paraInt(e.target.value, 2); if (!isNaN(v)) CONTAS[i].orc = v; renderOrc(); });
document.getElementById("r-comp").addEventListener("click", () => { regime = "comp"; renderOrc(); });
document.getElementById("r-caixa").addEventListener("click", () => { regime = "caixa"; renderOrc(); });

/* ── abas ── */
const VIEWS = ["cad", "rat", "orc"];
function abrir(v) {
  VIEWS.forEach((x) => { document.getElementById("v-" + x).hidden = x !== v; document.getElementById("t-" + x).setAttribute("aria-selected", x === v); });
}
document.querySelector(".tabs").addEventListener("click", (e) => { if (e.target.dataset.v) abrir(e.target.dataset.v); });
renderCad(); renderRat(); renderCcSelect(); renderOrc();
const h = location.hash.slice(1); if (VIEWS.includes(h)) abrir(h);
</script>
`;
