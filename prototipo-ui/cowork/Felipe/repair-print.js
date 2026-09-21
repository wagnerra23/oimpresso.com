// repair-print.js — O3 do refino: impressões do Repair, portadas dos blades
// job_sheet/print_pdf.blade.php (formato 1 e 2), job_sheet/print_label.blade.php e
// repair/receipts/* (via do cliente — repair.customerCopy).
// Padrão canônico do shell (mesmo de oficina-print.js): vanilla DOM num #rep-print-root,
// body.rep-printing isola o app, window.print(), limpeza no afterprint.
// Expõe window.RepairPrint = { printFolha, printEtiqueta, printViaCliente }.
(() => {
const D = () => window.RepData;
const esc = (s) => String(s == null ? "" : s).replace(/[&<>]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;" }[c]));
const agora = () => new Date().toLocaleString("pt-BR", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });

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

// Mira de registro — o glyph-assinatura do sistema, em SVG inline (o DS React não roda aqui).
const mira = (s = 26) => `<svg class="rep-mira" width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><circle cx="12" cy="12" r="6"/><path d="M12 1v6M12 17v6M1 12h6M17 12h6"/></svg>`;
const cortes = `<span class="rep-corte tl"></span><span class="rep-corte tr"></span><span class="rep-corte bl"></span><span class="rep-corte br"></span>`;

function cabeca(titulo, num, sub) {
  return `<header class="rep-sheet-top">
    <div class="rep-sheet-brand">Office Impresso<small>Assistência técnica</small></div>
    <div class="rep-sheet-doc">
      <span class="t">${esc(titulo)}</span>
      <b class="n">${esc(num)}</b>
      <small>${esc(sub || "")}</small>
    </div>
    <div class="rep-sheet-mira">${mira()}</div>
  </header>`;
}

const linha = (l, v) => `<div class="rep-kv"><label>${esc(l)}</label><span>${esc(v || "—")}</span></div>`;

function blocoAssinaturas() {
  return `<div class="rep-sign">
    <div><span class="ln"></span><small>Assinatura do cliente</small></div>
    <div><span class="ln"></span><small>Assinatura autorizada</small></div>
  </div>`;
}

const TERMOS = [
  "Equipamento não retirado em 90 dias após o aviso de conclusão poderá ser cobrado como armazenagem (Art. 1.275 CC).",
  "Orçamento aprovado é condição para execução; serviço recusado gera cobrança do diagnóstico.",
  "Garantia de 90 dias sobre o serviço executado (Art. 26 CDC), restrita ao defeito descrito nesta folha.",
  "Dados pessoais coletados para execução do contrato — LGPD Art. 7º, II.",
];

// ── Folha de OS (formato 1 = completa; formato 2 = enxuta, meia folha) ──
function printFolha(f, opts) {
  const P = D(); const o = opts || {};
  const m = P.modeloDe(f.modelo);
  const st = P.statusDe(f.status);
  const enxuta = o.formato === 2;
  const checklist = m.checklist.split("|");
  const pecas = f.pecas || [];
  const totalPecas = pecas.reduce((s, p) => s + p.valor * p.qtd, 0);

  const corpo = `
    <section class="rep-sheet-grid">
      ${linha("Cliente", f.cliente)}
      ${linha("Local", P.LOCAIS[f.local])}
      ${linha("Tipo de serviço", P.SERVICO[f.servico])}
      ${linha("Aberta em", P.d2(f.criado))}
      ${linha("Entrega prevista", P.d2(f.entrega))}
      ${linha("Técnico", f.tecnico)}
    </section>
    <section class="rep-sheet-grid">
      ${linha("Marca", m.marca)}
      ${linha("Equipamento", m.dispositivo)}
      ${linha("Modelo", m.nome)}
      ${linha("Número de série", f.serie)}
      ${linha("Configuração", f.configuracao)}
      ${linha("Status atual", st.nome)}
    </section>
    <section class="rep-sheet-bloco">
      <h3>Defeito relatado pelo cliente</h3>
      <ul>${(f.defeitos || []).map((d) => `<li>${esc(d)}</li>`).join("") || "<li>—</li>"}</ul>
      <p class="rep-cond"><b>Condição na entrada:</b> ${esc(f.condicao || "—")}</p>
    </section>`;

  const completa = `
    <section class="rep-sheet-bloco">
      <h3>Checklist de pré-reparo · ${esc(m.nome)}</h3>
      <ul class="rep-cl">${checklist.map((c) => `<li class="${(f.checklist || []).includes(c) ? "on" : ""}"><span class="bx"></span>${esc(c)}</li>`).join("")}</ul>
    </section>
    <section class="rep-sheet-bloco">
      <h3>Peças e serviços</h3>
      <table class="rep-tb">
        <thead><tr><th>Item</th><th class="r">Qtd</th><th class="r">Unitário</th><th class="r">Total</th></tr></thead>
        <tbody>
          ${pecas.map((p) => `<tr><td>${esc(p.nome)}</td><td class="r">${p.qtd}</td><td class="r">${esc(P.fmt(p.valor))}</td><td class="r">${esc(P.fmt(p.valor * p.qtd))}</td></tr>`).join("") || `<tr><td colspan="4">Nenhuma peça lançada</td></tr>`}
        </tbody>
        <tfoot>
          <tr><td colspan="3">Peças</td><td class="r">${esc(P.fmt(totalPecas))}</td></tr>
          <tr><td colspan="3">Custo estimado do reparo</td><td class="r"><b>${esc(f.custo ? P.fmt(f.custo) : "a orçar")}</b></td></tr>
        </tfoot>
      </table>
    </section>
    <section class="rep-sheet-bloco rep-termos">
      <h3>Termos e condições do reparo</h3>
      <ol>${TERMOS.map((t) => `<li>${esc(t)}</li>`).join("")}</ol>
    </section>`;

  run(`<article class="rep-sheet${enxuta ? " enxuta" : ""}">
    ${cortes}
    ${cabeca("Folha de OS", f.os, "Impresso em " + agora())}
    ${corpo}
    ${enxuta ? "" : completa}
    ${blocoAssinaturas()}
    <footer class="rep-sheet-foot"><span>Office Impresso · assistência técnica</span><span class="mono">${esc(f.os)} · ${esc(f.serie)}</span></footer>
  </article>`);
}

// ── Etiqueta do equipamento (print_label) — 100×50 mm, vai colada na máquina ──
function printEtiqueta(f) {
  const P = D(); const m = P.modeloDe(f.modelo);
  // "Código de barras": representação gráfica do próprio número da folha, sem inventar dado.
  const barras = String(f.os).replace(/\D/g, "").split("").map((n) => `<i style="width:${1 + (Number(n) % 3)}px"></i>`).join("");
  run(`<article class="rep-label">
    ${cortes}
    <div class="rep-label-h"><b>Office Impresso</b>${mira(16)}</div>
    <b class="rep-label-n">${esc(f.os)}</b>
    <div class="rep-label-l">${esc(f.cliente)}</div>
    <div class="rep-label-l">${esc(m.marca)} ${esc(m.nome)}</div>
    <div class="rep-label-l mono">série ${esc(f.serie)}</div>
    <div class="rep-label-l mono">entrega ${esc(P.d2(f.entrega))} · ${esc(f.tecnico)}</div>
    <div class="rep-barras">${barras}</div>
  </article>`);
}

// ── Via do cliente (repair.customerCopy) — recibo do que ele deixou ──
function printViaCliente(f) {
  const P = D(); const m = P.modeloDe(f.modelo);
  run(`<article class="rep-sheet enxuta">
    ${cortes}
    ${cabeca("Via do cliente", f.os, "Impresso em " + agora())}
    <section class="rep-sheet-grid">
      ${linha("Cliente", f.cliente)}
      ${linha("Recebido em", P.d2(f.criado))}
      ${linha("Equipamento", m.marca + " " + m.nome)}
      ${linha("Número de série", f.serie)}
      ${linha("Entrega prevista", P.d2(f.entrega))}
      ${linha("Custo estimado", f.custo ? P.fmt(f.custo) : "a orçar")}
    </section>
    <section class="rep-sheet-bloco">
      <h3>Defeito relatado</h3>
      <ul>${(f.defeitos || []).map((d) => `<li>${esc(d)}</li>`).join("") || "<li>—</li>"}</ul>
    </section>
    <section class="rep-sheet-bloco rep-portal">
      <h3>Acompanhe pelo site</h3>
      <p>Consulte o andamento em <b>oimpresso.com/repair-status</b> com o número <b class="mono">${esc(f.os)}</b> e o número de série <b class="mono">${esc(f.serie)}</b>.</p>
    </section>
    <section class="rep-sheet-bloco rep-termos"><h3>Termos</h3><ol>${TERMOS.map((t) => `<li>${esc(t)}</li>`).join("")}</ol></section>
    ${blocoAssinaturas()}
  </article>`);
}

window.RepairPrint = { printFolha, printEtiqueta, printViaCliente };
})();
