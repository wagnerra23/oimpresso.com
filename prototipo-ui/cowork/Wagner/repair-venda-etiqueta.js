// repair-venda-etiqueta.js — etiqueta da VENDA de reparo (Repair/Show).
// Porta de repair/partials/preview_label.blade.php (RepairController@printLabel, rota
// print-label/{transaction_id}). Não confundir com a etiqueta do EQUIPAMENTO
// (repair-print.js → printEtiqueta), que identifica a máquina pela folha de serviço.
//
// O que a Blade imprime (e esta etiqueta mantém): nome da empresa · cliente · modelo ·
// código de barras do nº da FATURA (invoice_no). Tamanho e colunas vêm da configuração de
// etiqueta do Repair (barcode_id) — aqui o formato contínuo 100×50 mm, o mesmo do protótipo.
// O nome no topo é o da EMPRESA do usuário (session business.name), nunca "Office Impresso".
//
// Mesmo motor de impressão do repair-print.js (#rep-print-root + body.rep-printing +
// window.print), e as mesmas classes .rep-label* do repair-print.css.
// Ponto de uso pedido ao shell: botão "Etiqueta" no rodapé do drawer da venda
// (repair-page.jsx, "DRAWER da venda de reparo"), chamando
// window.RepairVendaEtiqueta.printEtiquetaVenda(reparo).
(() => {
const D = () => window.RepData;
const esc = (s) => String(s == null ? "" : s).replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));

// Empresa do usuário logado. No protótipo não há sessão: usa CONFIG.empresa se existir.
const empresa = () => (D().CONFIG && D().CONFIG.empresa) || "Sua empresa";

function root() {
  let el = document.getElementById("rep-print-root");
  if (!el) { el = document.createElement("div"); el.id = "rep-print-root"; document.body.appendChild(el); }
  return el;
}
function run(html) {
  root().innerHTML = html;
  document.body.classList.add("rep-printing");
  const limpar = () => {
    document.body.classList.remove("rep-printing");
    root().innerHTML = "";
    window.removeEventListener("afterprint", limpar);
  };
  window.addEventListener("afterprint", limpar);
  setTimeout(() => window.print(), 80);
  setTimeout(limpar, 60000);
}

// "Código de barras": representação gráfica do próprio nº da fatura, sem inventar dado.
// Na tela real o servidor gera o código no tipo configurado (DNS1D, barcode_type).
// Cada caractere vira 3 barras (larguras pelo código do caractere), pra a amostra ter a
// densidade de um Code 128 de verdade em vez de 4 traços.
const barras = (txt) => String(txt).split("").map((ch) => {
  const c = ch.charCodeAt(0);
  return [c % 3, (c >> 2) % 3, (c >> 4) % 3].map((w) => `<i style="width:${1 + w}px"></i>`).join("");
}).join("");

function htmlEtiqueta(reparo) {
  const P = D();
  const folha = (P.FOLHAS || []).find((f) => f.id === reparo.folha) || {};
  const m = folha.modelo != null ? P.modeloDe(folha.modelo) : null;
  return `<article class="rep-label rep-label-venda" aria-label="Etiqueta da venda ${esc(reparo.fatura)}">
    <div class="rep-label-h"><b>${esc(empresa())}</b><span class="mono">${esc(reparo.repair)}</span></div>
    <b class="rep-label-n">${esc(reparo.fatura)}</b>
    ${folha.cliente ? `<div class="rep-label-l">Cliente: ${esc(folha.cliente)}</div>` : ""}
    ${m ? `<div class="rep-label-l">Modelo: ${esc(m.marca)} ${esc(m.nome)}</div>` : ""}
    <div class="rep-barras" role="img" aria-label="Código de barras da fatura ${esc(reparo.fatura)}">${barras(reparo.fatura)}</div>
    <div class="rep-label-l mono rep-label-cod">${esc(reparo.fatura)}</div>
  </article>`;
}

function printEtiquetaVenda(reparo) { run(htmlEtiqueta(reparo)); }

window.RepairVendaEtiqueta = { printEtiquetaVenda, htmlEtiqueta };
})();
