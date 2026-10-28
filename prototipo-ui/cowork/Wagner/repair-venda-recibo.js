// repair-venda-recibo.js — recibo da VENDA de reparo para o cliente (Repair/Show).
// Porta de repair/receipts/classic.blade.php, que o RepairController@printCustomerCopy
// (rota print-repair/{transaction_id}/customer-copy, "repair.customerCopy") renderiza.
// Não confundir com a via do cliente da FOLHA (repair-print.js → printViaCliente), que é o
// comprovante do que o cliente DEIXOU no balcão. Este é o recibo do que ele PAGOU.
//
// Blocos da Blade, na mesma ordem: cabeçalho da empresa · título + nº da fatura + datas ·
// cliente · dados do reparo (status, marca, equipamento, modelo, série, garantia, técnico) ·
// itens (produto, qtd, unitário, subtotal) · totais · pagamentos · total pago / saldo ·
// observações · rodapé · código de barras do nº da fatura.
// Linhas que a Blade só mostra quando há valor (desconto, imposto, frete, embalagem,
// arredondamento, pontos) ficam fora quando zeradas — aqui não há nenhuma, então não aparecem.
//
// Números ilustrativos (repair-data.jsx), sempre fechando a conta: os itens somam o total
// da venda e "pago" = total − saldo. Na tela real os valores vêm do servidor, sem recálculo.
// Ponto de uso pedido ao shell: o botão "Via do cliente" do drawer da venda (hoje só
// avisa) passa a chamar window.RepairVendaRecibo.printReciboVenda(reparo).
(() => {
const D = () => window.RepData;
const esc = (s) => String(s == null ? "" : s).replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));
const empresa = () => (D().CONFIG && D().CONFIG.empresa) || "Sua empresa";
const agora = () => new Date().toLocaleString("pt-BR", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });

const PAGAMENTO = { paid: "Pago", partial: "Pago em parte", pending: "Pendente", due: "Vencido" };

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

const cortes = `<span class="rep-corte tl"></span><span class="rep-corte tr"></span><span class="rep-corte bl"></span><span class="rep-corte br"></span>`;
const linha = (l, v) => `<div class="rep-kv"><label>${esc(l)}</label><span>${esc(v || "—")}</span></div>`;
const barras = (txt) => String(txt).split("").map((ch) => {
  const c = ch.charCodeAt(0);
  return [c % 3, (c >> 2) % 3, (c >> 4) % 3].map((w) => `<i style="width:${1 + w}px"></i>`).join("");
}).join("");

// Itens da venda: as peças da folha + a mão de obra que fecha o total da venda.
function itensDe(reparo, folha) {
  const P = D();
  const pecas = (folha.pecas || []).map((p) => ({ nome: p.nome, qtd: p.qtd, unit: p.valor }));
  const somaPecas = pecas.reduce((s, p) => s + p.qtd * p.unit, 0);
  const mo = Math.round((reparo.total - somaPecas) * 100) / 100;
  const itens = mo > 0 ? [...pecas, { nome: (P.CONFIG && P.CONFIG.produtoPadrao) || "Serviço de assistência técnica", qtd: 1, unit: mo }] : pecas;
  // Se as peças passarem do total (dado de exemplo inconsistente), mostra uma linha só.
  return mo < 0 ? [{ nome: "Reparo " + reparo.repair, qtd: 1, unit: reparo.total }] : itens;
}

function htmlRecibo(reparo) {
  const P = D();
  const folha = (P.FOLHAS || []).find((f) => f.id === reparo.folha) || {};
  const m = folha.modelo != null ? P.modeloDe(folha.modelo) : {};
  const st = folha.status != null ? P.statusDe(folha.status) : null;
  const itens = itensDe(reparo, folha);
  const pago = Math.round((reparo.total - reparo.saldo) * 100) / 100;

  return `<article class="rep-sheet rep-recibo" aria-label="Recibo da venda ${esc(reparo.fatura)}">
    ${cortes}
    <header class="rep-sheet-top">
      <div class="rep-sheet-brand">${esc(empresa())}<small>Recibo de reparo</small></div>
      <div class="rep-sheet-doc">
        <span class="t">Recibo</span>
        <b class="n">${esc(reparo.fatura)}</b>
        <small>Emitido em ${esc(P.d2(reparo.emitido))} · impresso em ${esc(agora())}</small>
      </div>
    </header>

    <section class="rep-sheet-grid">
      ${linha("Cliente", folha.cliente)}
      ${linha("Venda de reparo", reparo.repair)}
      ${linha("Folha de serviço", folha.os)}
    </section>

    <section class="rep-sheet-bloco">
      <h3>Reparo</h3>
      <div class="rep-sheet-grid">
        ${linha("Status do reparo", st && st.nome)}
        ${linha("Marca", m.marca)}
        ${linha("Equipamento", m.dispositivo)}
        ${linha("Modelo", m.nome)}
        ${linha("Número de série", folha.serie)}
        ${linha("Garantia", reparo.garantia)}
        ${linha("Técnico", folha.tecnico)}
      </div>
    </section>

    <section class="rep-sheet-bloco">
      <h3>Itens</h3>
      <table class="rep-tb">
        <thead><tr><th>Produto</th><th class="r">Qtd</th><th class="r">Unitário</th><th class="r">Subtotal</th></tr></thead>
        <tbody>
          ${itens.map((i) => `<tr><td>${esc(i.nome)}</td><td class="r">${i.qtd}</td><td class="r">${esc(P.fmt(i.unit))}</td><td class="r">${esc(P.fmt(i.qtd * i.unit))}</td></tr>`).join("")}
        </tbody>
        <tfoot>
          <tr><td colspan="3">Subtotal</td><td class="r">${esc(P.fmt(reparo.total))}</td></tr>
          <tr class="rep-recibo-total"><td colspan="3"><b>Total</b></td><td class="r"><b>${esc(P.fmt(reparo.total))}</b></td></tr>
        </tfoot>
      </table>
    </section>

    <section class="rep-sheet-bloco">
      <h3>Pagamento</h3>
      <div class="rep-sheet-grid">
        ${linha("Situação", PAGAMENTO[reparo.pagamento] || reparo.pagamento)}
        ${linha("Total pago", P.fmt(pago))}
        ${linha("Saldo a pagar", P.fmt(reparo.saldo))}
      </div>
    </section>

    <section class="rep-sheet-bloco rep-recibo-barras">
      <div class="rep-barras" role="img" aria-label="Código de barras da fatura ${esc(reparo.fatura)}">${barras(reparo.fatura)}</div>
      <span class="mono">${esc(reparo.fatura)}</span>
    </section>

    <footer class="rep-sheet-foot">
      <span>Garantia conforme o termo da folha ${esc(folha.os || "")} (Art. 26 CDC).</span>
      <span class="mono">${esc(reparo.repair)}</span>
    </footer>
  </article>`;
}

function printReciboVenda(reparo) { run(htmlRecibo(reparo)); }

window.RepairVendaRecibo = { printReciboVenda, htmlRecibo, itensDe };
})();
