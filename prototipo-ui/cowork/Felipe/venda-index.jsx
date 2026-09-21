// venda-index.jsx — Onda A1: "Todas as vendas" espelhando o VIVO `Sells/Index.tsx`
// (charter v9, status live) + `_components/SellsTabelaUnificada.tsx` e `SellsTabsVisao.tsx`,
// lidos no `main` (tree 6a8e45998ee5). O protótipo antigo (`vendas-page.jsx`, maio) ficou
// para trás: 3 visões com presets de coluna, pipeline FSM, badges fiscais, SLA, origem
// (ADR 0192), setinha de devolução (v8), ações por linha (v7) e emissão em lote.
// Expõe window.VendaTodasPage.
(() => {
const { useState, useMemo, useEffect, useRef } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const UI = () => window.PBUI || {};
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };
const brl = (n) => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// Presets de coluna — verbatim do SellsTabelaUnificada (COLUMNS_*).
const VISOES = [
  { id: "operacional", label: "Operacional", cols: ["check", "invoice", "date", "client", "seller", "source", "pipeline", "fiscal", "payment", "total", "status"], dica: "Pipeline, fiscal, SLA e ações rápidas — o dia a dia do balcão." },
  { id: "financeira", label: "Financeira", cols: ["check", "invoice", "date", "client", "total", "paid", "due", "payment", "status", "commission"], dica: "Total, pago, a receber e comissão." },
  { id: "producao", label: "Produção", cols: ["check", "invoice", "date", "client", "location", "source", "pipeline", "payment", "total", "status"], dica: "Localização, estágio e origem da venda." },
];
const PILLS = [
  { id: "todas", label: "Todas" }, { id: "paga", label: "Paga" }, { id: "pendente", label: "Pendente" },
  { id: "faturada", label: "Faturada" }, { id: "cancelada", label: "Cancelada" },
];
const ETAPAS = ["Orçamento", "Aprovada", "Produção", "Acabamento", "Expedição", "Entregue"];

// Mock com o shape do SaleRow do vivo.
const V = (id, inv, dia, hora, cli, itens, seller, abbr, origem, src, os, passo, fiscal, modelo, forma, parc, total, pago, sla, dias, loc, com, ret, placa) =>
  ({ id, invoice_no: inv, dia, hora, cli, items_summary: itens, seller, abbr, seller_origin: origem, source: src, os_ref: os,
     pipeline_step: passo, fiscal_status: fiscal, fiscal_modelo: modelo, payment_method_label: forma, installments: parc,
     final_total: total, total_paid: pago, sla_kind: sla, days_to_due: dias, location_name: loc, commission_agent_name: com,
     has_return: ret, vehicle_plate: placa });

const VENDAS = [
  V(4821, "VD-2026-4821", "22/08", "09:12", "Rota Livre Transportes", "Lona 380g · 12 m² + arte", "Larissa Prado", "LP", "balcão", "balcao", null, 4, "autorizada", "55", "Pix", 1, 3120, 3120, "paid", null, "Matriz", "Marcos V.", false, null),
  V(4820, "VD-2026-4820", "22/08", "10:34", "Martinho Oficina", "Adesivo de recorte · 4 m²", "Larissa Prado", "LP", "oficina", "oficina", "OS-1188", 2, "pendente", null, "Boleto", 2, 742, 300, "warning", 3, "Matriz", null, true, "RTA5B21"),
  V(4819, "VD-2026-4819", "21/08", "16:05", "Agência Norte", "Cartão de visita · 3 caixas", "Marcos Vinícius", "MV", "balcão", "balcao", null, 6, "autorizada", "65", "Cartão de crédito", 3, 1875.4, 1875.4, "paid", null, "Filial Centro", "Marcos V.", false, null),
  V(4818, "VD-2026-4818", "21/08", "11:48", "Supermercado Bom Dia", "Fachada ACM · instalação", "Wagner Ramos", "WR", "online", "online", null, 3, null, null, "Boleto", 4, 5490, 2000, "overdue", -6, "Matriz", null, false, null),
  V(4817, "VD-2026-4817", "20/08", "15:21", "Prefeitura de Jaú", "Placas de sinalização · 24 un", "Wagner Ramos", "WR", "balcão", "balcao", null, 1, "rejeitada", "55", "Empenho", 1, 12480, 0, "warning", 9, "Filial Centro", null, false, null),
  V(4816, "VD-2026-4816", "20/08", "09:03", "Cliente balcão", "Banner 440g · 2 m²", "Larissa Prado", "LP", "balcão", "balcao", null, 6, null, null, "Dinheiro", 1, 98.5, 98.5, "paid", null, "Matriz", null, false, null),
  V(4815, "VD-2026-4815", "19/08", "17:42", "Martinho Oficina", "Envelopamento · Kombi", "Larissa Prado", "LP", "oficina", "oficina", "OS-1184", 5, "autorizada", "55", "Pix", 1, 2210, 2210, "paid", null, "Matriz", null, false, "BRA2E19"),
  V(4814, "VD-2026-4814", "19/08", "13:27", "Agência Norte", "Adesivos promocionais", "Marcos Vinícius", "MV", "online", "online", null, 3, "pendente", null, "Cartão de débito", 1, 640, 0, "fresh", 12, "Filial Centro", "Marcos V.", false, null),
];
const CANCELADAS = [4817];

const pillDe = (v) => CANCELADAS.includes(v.id) ? "cancelada"
  : v.fiscal_status === "autorizada" ? "faturada"
  : v.total_paid >= v.final_total ? "paga" : "pendente";

// ─────────── Células ───────────
const Dots = ({ passo }) => (
  <span className="vi-dots" title={ETAPAS[(passo || 1) - 1]}>
    {ETAPAS.map((e, i) => <i key={e} className={i < (passo || 0) ? "on" : ""} />)}
    <b>{ETAPAS[(passo || 1) - 1]}</b>
  </span>
);
const Fiscal = ({ status, modelo }) => {
  if (!status) return <span className="vi-mute">—</span>;
  const rot = { autorizada: "Autorizada", pendente: "Processando", rejeitada: "Rejeitada", denegada: "Denegada", cancelada: "Cancelada" }[status];
  const { StatusBadge } = DS();
  // Nome do documento vem do dado (55 = NF-e mercadoria · 65 = NFC-e consumidor),
  // pela mesma função que o drawer usa — uma fonte só.
  const doc = modelo ? (window.VENDA_DOC_FISCAL ? window.VENDA_DOC_FISCAL({ fiscal_modelo: modelo }) : "NF-e " + modelo) : null;
  return (
    <span className="vi-fiscal">
      {StatusBadge ? <StatusBadge kind="fiscal" value={rot} /> : rot}
      {doc && <em className="mono">{doc}</em>}
    </span>
  );
};
const Sla = ({ kind, dias }) => {
  if (kind === "paid") return <span className="vi-sla paid">quitada</span>;
  if (kind === "overdue") return <span className="vi-sla overdue">{Math.abs(dias)}d em atraso</span>;
  if (kind === "warning") return <span className="vi-sla warning">vence em {dias}d</span>;
  return <span className="vi-sla fresh">vence em {dias}d</span>;
};
const Origem = ({ src, os, onOs }) => (
  <span className={"vi-src " + src}>
    {{ balcao: "Balcão", oficina: "Oficina", online: "Online" }[src] || src}
    {os && <button className="vi-os" onClick={(e) => { e.stopPropagation(); onOs(os); }}>↗ #{os}</button>}
  </span>
);

function VendaTodasPage({ avisar: avisarFora }) {
  const M = window.ModuloPadrao || {};
  const { Widget, Kebab, Modal } = UI();
  const { StatusBadge, BulkBar, EmptyState } = DS();
  const [avisoNode, avisarLocal] = M.useAviso ? M.useAviso() : [null, () => {}];
  const avisar = avisarFora || avisarLocal;
  const [visao, setVisao] = useState("operacional");
  const [pill, setPill] = useState("todas");
  const [busca, setBusca] = useState("");
  const [sel, setSel] = useState([]);
  const [fav, setFav] = useState([4821]);
  const [salva, setSalva] = useState(null);
  const [origemAberta, setOrigemAberta] = useState(false);
  const [ver, setVer] = useState(null);
  const [pagar, setPagar] = useState(null);
  const [recibo, setRecibo] = useState(null);
  const [lote, setLote] = useState(null);
  const buscaRef = useRef(null);

  const rows = useMemo(() => VENDAS.filter((v) =>
    (pill === "todas" || pillDe(v) === pill) &&
    (!salva || (salva === "faturar" ? (v.total_paid < v.final_total && !v.fiscal_status) : v.source === salva)) &&
    (!busca || (v.invoice_no + " " + v.cli + " " + (v.items_summary || "")).toLowerCase().includes(busca.toLowerCase()))
  ), [pill, salva, busca]);

  // Venda cancelada não entra em número nenhum (canon append-only: fica na lista,
  // fora dos totais). Todos os KPIs derivam desta base.
  const VIVAS = VENDAS.filter((v) => pillDe(v) !== "cancelada");
  const hoje = VIVAS.filter((v) => v.dia === "22/08");
  const kpis = {
    faturado: hoje.reduce((a, v) => a + v.final_total, 0),
    ticket: hoje.length ? hoje.reduce((a, v) => a + v.final_total, 0) / hoje.length : 0,
    receber: VIVAS.reduce((a, v) => a + Math.max(0, v.final_total - v.total_paid), 0),
    notas: VIVAS.filter((v) => v.fiscal_status === "autorizada").length,
  };
  const faixas = [
    { l: "0–30 dias", v: VIVAS.filter((v) => v.sla_kind === "fresh" || v.sla_kind === "warning").reduce((a, v) => a + (v.final_total - v.total_paid), 0) },
    { l: "vencido", v: VIVAS.filter((v) => v.sla_kind === "overdue").reduce((a, v) => a + (v.final_total - v.total_paid), 0) },
  ];

  useEffect(() => {
    const onKey = (e) => {
      if (e.target.tagName === "INPUT" || e.target.tagName === "TEXTAREA") return;
      if (e.key === "/") { e.preventDefault(); buscaRef.current?.focus(); }
      if (e.key === "?") { e.preventDefault(); avisar("Atalhos: / busca · J/K navega · ↵ abre · B favorita · R imprime recibo.", "ok"); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [avisar]);

  const cols = VISOES.find((x) => x.id === visao).cols;
  const cabec = { check: "", invoice: "Venda", date: "Data", client: "Cliente", seller: "Atendido por", source: "Origem", pipeline: "Pipeline", fiscal: "Fiscal", payment: "Pagamento", location: "Localização", paid: "Pago", due: "A receber", total: "Total", status: "Status", commission: "Comissão" };
  const marcar = (id) => setSel((s) => s.includes(id) ? s.filter((x) => x !== id) : [...s, id]);
  const irOs = (os) => avisar("Abrindo " + os + " na Oficina.", "ok");

  const celula = (c, v) => {
    const due = Math.max(0, v.final_total - v.total_paid);
    switch (c) {
      case "check": return <td key={c} className="vi-chk" onClick={(e) => e.stopPropagation()}><input type="checkbox" checked={sel.includes(v.id)} onChange={() => marcar(v.id)} aria-label={"Selecionar " + v.invoice_no} /></td>;
      case "invoice": return <td key={c} className="vi-id">{fav.includes(v.id) && <span className="vi-fav" title="Favorita (B)">★</span>}#{v.invoice_no.replace("VD-2026-", "")}{v.has_return && <span className="vi-ret" title="Venda com devolução">↩</span>}</td>;
      case "date": return <td key={c} className="vi-date">{v.dia}<em>{v.hora}</em></td>;
      case "client": return <td key={c} className="vi-cli"><b>{v.cli}</b>{v.items_summary && <em>{v.items_summary}</em>}{v.vehicle_plate && <span className="vi-placa">{v.vehicle_plate}</span>}</td>;
      case "seller": return <td key={c}><span className="vi-sel"><i>{v.abbr}</i><span><b>{v.seller.split(" ")[0]}</b><small>{v.seller_origin}</small></span></span></td>;
      case "source": return <td key={c}><Origem src={v.source} os={v.os_ref} onOs={irOs} /></td>;
      case "pipeline": return <td key={c}><Dots passo={v.pipeline_step} /></td>;
      case "fiscal": return <td key={c}><Fiscal status={v.fiscal_status} modelo={v.fiscal_modelo} /></td>;
      case "payment": return <td key={c} className="vi-pay"><b>{v.payment_method_label}{v.installments > 1 && <span className="vi-inst">{v.installments}×</span>}</b><Sla kind={v.sla_kind} dias={v.days_to_due} /></td>;
      case "location": return <td key={c} className="vi-mute">{v.location_name}</td>;
      case "paid": return <td key={c} className="vi-num">{brl(v.total_paid)}</td>;
      case "due": return <td key={c} className={"vi-num" + (due ? " neg" : "")}>{brl(due)}</td>;
      case "total": return <td key={c} className="vi-num strong">{brl(v.final_total)}</td>;
      case "commission": return <td key={c} className="vi-mute">{v.commission_agent_name || "—"}</td>;
      case "status": return (
        <td key={c} onClick={(e) => e.stopPropagation()}>
          <div className="vi-status">
            {StatusBadge ? <StatusBadge kind="documento" value={{ paga: "Paga", pendente: "Pendente", faturada: "Faturada", cancelada: "Cancelada" }[pillDe(v)]} /> : pillDe(v)}
            <div className="vi-acts">
              {due > 0 && <button className="vi-act" title="Registrar pagamento" onClick={() => setPagar({ inv: v.invoice_no, total: v.final_total, saldo: due, forma: v.payment_method_label })}><Ic name="cash" size={11} /></button>}
              {v.fiscal_status === "autorizada" && <button className="vi-act" title="Baixar DANFE" onClick={() => avisar("DANFE de " + v.invoice_no + " baixada.", "ok")}><Ic name="doc" size={11} /></button>}
              <button className="vi-act" title="Imprimir recibo (R)" onClick={() => setRecibo({ inv: v.invoice_no, data: v.dia + "/2026 " + v.hora, cli: v.cli, tel: "—", loc: v.location_name, quem: v.seller, serv: "Balcão", forma: v.payment_method_label, total: v.final_total, pago: v.total_paid, saldo: due, itens: 3, envio: "delivered", obs: "" })}><Ic name="print" size={11} /></button>
              {Kebab && <Kebab acoes={[
                { l: "Ver detalhes", ic: "search", on: () => setVer(v) },
                { l: "Editar", ic: "pencil", on: () => avisar("Editando " + v.invoice_no + ".", "ok") },
                ...(due > 0 ? [{ l: "Adicionar pagamento", ic: "cash", on: () => setPagar({ inv: v.invoice_no, total: v.final_total, saldo: due }) }] : []),
                { l: "Imprimir nota", ic: "print", on: () => avisar("Nota de " + v.invoice_no + " na impressora.", "ok") },
                { l: "Devolução", ic: "list", on: () => { window.__vendaDevolverAlvo = { ...v, inv: v.invoice_no, total: v.final_total, pago: v.total_paid, saldo: due, itens: 3, data: v.dia + "/2026", cli: v.cli, quem: v.seller, loc: v.location_name, tel: "—", serv: "Balcão", forma: v.payment_method_label }; window.__selectRoute && window.__selectRoute("venda-devolver"); } },
                "-",
                { l: "Excluir", ic: "x", tone: "danger", on: () => avisar("Excluir " + v.invoice_no + " — o servidor recusa venda com NF-e autorizada.", "warn") },
              ]} />}
            </div>
          </div>
        </td>
      );
      default: return <td key={c} />;
    }
  };

  return (
    <div className="pb-root vb-root vi-root" data-screen-label="Venda · Todas as vendas">
      {M.Header &&
        <M.Header modulo="Vendas" papel="Todas as vendas"
          contexto={["OFFICEIMPRESSO", "matriz", "pedidos · faturamento · NF-e/NFS-e", "espelho de Sells/Index (vivo)"]}
          atualizadoAs={new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}
          glyph={<Ic name="cash" />}
          acoes={<>
            <button className="os-btn" onClick={() => window.__selectRoute && window.__selectRoute("venda-pos")}>Lista de POS</button>
            <button className="os-btn primary" onClick={() => window.__selectRoute && window.__selectRoute("venda-nova")}><Ic name="plus" size={13} /> Nova venda</button>
          </>} />}

      <div className="pb-body">
        <div className="vi-kpis">
          <div className="vi-kpi hero"><span>Faturado hoje</span><b>{brl(kpis.faturado)}</b><em>{hoje.length} venda(s)</em></div>
          <div className="vi-kpi"><span>Ticket médio</span><b>{brl(kpis.ticket)}</b><em>hoje</em></div>
          <div className="vi-kpi"><span>A receber</span><b>{brl(kpis.receber)}</b>
            <div className="vi-faixas">{faixas.map((f) => <span key={f.l}>{f.l} <i>{brl(f.v)}</i></span>)}</div>
            <em>cancelada não entra</em>
          </div>
          <div className="vi-kpi"><span>Notas fiscais</span><b>{kpis.notas}</b><em>autorizadas</em></div>
        </div>

        <Widget flush titulo={<><Ic name="list" size={13} /> Vendas</>} nota={rows.length + " de " + VENDAS.length}>
          <div className="vi-toolbar">
            <div className="vi-pills" role="tablist" aria-label="Situação da venda">
              {PILLS.map((p) => <button key={p.id} role="tab" aria-selected={pill === p.id} className={pill === p.id ? "on" : ""} onClick={() => setPill(p.id)}>{p.label}</button>)}
            </div>
            <div className="vi-seg" role="tablist" aria-label="Visão da lista">
              {VISOES.map((x) => <button key={x.id} role="tab" aria-selected={visao === x.id} title={x.dica} className={visao === x.id ? "on" : ""} onClick={() => setVisao(x.id)}>{x.label}</button>)}
            </div>
            <div className="pb-busca">
              <Ic name="search" size={12} />
              <input ref={buscaRef} value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar venda, cliente ou item…" />
              <kbd>/</kbd>
            </div>
            <div className="sp" />
            <div className="vi-salvas">
              <button className={salva === "faturar" ? "on" : ""} onClick={() => setSalva(salva === "faturar" ? null : "faturar")}>Aguardando faturamento</button>
              <button className={origemAberta ? "on" : ""} onClick={() => setOrigemAberta((o) => !o)}>Por origem ▾</button>
              {origemAberta &&
                <div className="vi-origem">
                  {["balcao", "oficina", "online"].map((s) => (
                    <button key={s} className={salva === s ? "on" : ""} onClick={() => { setSalva(salva === s ? null : s); setOrigemAberta(false); }}>
                      {{ balcao: "Balcão", oficina: "Oficina", online: "Online" }[s]}
                      <i>{VENDAS.filter((v) => v.source === s).length}</i>
                    </button>
                  ))}
                </div>}
            </div>
          </div>

          <div className="vi-tabela-wrap">
            <table className="vi-tabela">
              <thead><tr>{cols.map((c) => <th key={c} className={"vi-th-" + c}>{c === "check" ? <input type="checkbox" checked={sel.length === rows.length && rows.length > 0} onChange={() => setSel(sel.length === rows.length ? [] : rows.map((r) => r.id))} aria-label="Selecionar todas" /> : cabec[c]}</th>)}</tr></thead>
              <tbody>
                {rows.map((v) => (
                  <tr key={v.id} className={"vi-row" + (v.sla_kind === "overdue" ? " urgent" : "") + (sel.includes(v.id) ? " sel" : "") + (v.source === "oficina" ? " oficina" : "")}
                    onClick={() => setVer(v)}>
                    {cols.map((c) => celula(c, v))}
                  </tr>
                ))}
              </tbody>
            </table>
            {!rows.length && EmptyState &&
              <div style={{ padding: 24 }}><EmptyState variant="no-results" icon={<Ic name="search" size={18} />} title="Nenhuma venda com esse filtro" description="Troque a situação, limpe a visão salva ou busque por outro termo." /></div>}
          </div>

          <div className="pb-pag">
            <span>{rows.length} venda(s) · {brl(rows.reduce((a, v) => a + v.final_total, 0))} no filtro atual</span>
            <div className="sp" />
            <span className="pb-help">Clique na linha abre o detalhe · <span className="mono">/</span> busca · <span className="mono">?</span> atalhos</span>
          </div>
        </Widget>
      </div>

      {sel.length > 0 && (BulkBar
        ? <BulkBar count={sel.length} onClose={() => setSel([])} actions={[
            { label: "Emitir NF-e em lote", onClick: () => setLote({ n: sel.length }) },
            { label: "Imprimir recibos", onClick: () => avisar(sel.length + " recibo(s) na fila de impressão.", "ok") },
            { label: "Exportar seleção", onClick: () => avisar(sel.length + " venda(s) exportadas em CSV.", "ok") },
          ]} />
        : null)}

      {lote && Modal &&
        <Modal titulo={"Emitir NF-e em lote · " + lote.n + " venda(s)"} onClose={() => setLote(null)} largura={620}
          acoes={<>
            <button className="os-btn" onClick={() => setLote(null)}>Cancelar</button>
            <button className="os-btn primary" onClick={() => { avisar(lote.n + " nota(s) enviadas pra SEFAZ — acompanhe a coluna Fiscal.", "ok"); setLote(null); setSel([]); }}>Transmitir</button>
          </>}>
          <p className="pb-help">Cada venda vira uma transmissão independente: o que a SEFAZ rejeitar continua na lista com o motivo. Cliente sem CNPJ/CPF é recusado antes do envio.</p>
        </Modal>}

      {ver && window.VendaDetalhe &&
        <window.VendaDetalhe
          venda={{ ...ver, inv: ver.invoice_no, data: ver.dia + "/2026 " + ver.hora, tel: "—", loc: ver.location_name, quem: ver.seller, serv: "Balcão", forma: ver.payment_method_label,
            pg: ver.total_paid >= ver.final_total ? "paid" : "partial",
            envio: ver.pipeline_step >= 6 ? "delivered" : ver.pipeline_step === 5 ? "shipped" : ver.pipeline_step === 4 ? "packed" : "ordered",
            itens: 3, total: ver.final_total, pago: ver.total_paid, saldo: Math.max(0, ver.final_total - ver.total_paid), obs: ver.items_summary }}
          onClose={() => setVer(null)} avisar={avisar}
          onAcao={(a, v) => { if (a === "pagamento") { setVer(null); setPagar(v); } else avisar("NF-e de " + v.inv + " na fila da SEFAZ.", "ok"); }} />}
      {window.VendaPagamentoModal && <window.VendaPagamentoModal aberto={!!pagar} venda={pagar} onClose={() => setPagar(null)} avisar={avisar} onConfirm={() => setPagar(null)} />}
      {recibo && window.VendaRecibo && <window.VendaRecibo venda={recibo} onClose={() => setRecibo(null)} avisar={avisar} />}
      {avisoNode}
    </div>
  );
}

window.VendaTodasPage = VendaTodasPage;
// As vendas ficam disponíveis pras telas irmãs (loja online, financeiro) — quem consome
// não remonta cliente e valor à mão: VD-#### resolve para o mesmo documento em qualquer tela.
window.VENDAS_MOCK = VENDAS;
})();
