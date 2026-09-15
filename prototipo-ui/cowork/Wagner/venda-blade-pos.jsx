// venda-blade-pos.jsx — Onda 1 do menu Vendas: a tela POS do legado (sale_pos/create.blade.php
// + partials pos_form, sales_table, pos_form_totals, pos_form_actions, product_list_box,
// recent_transactions, payment_modal, suspended_sales_modal, keyboard_shortcuts).
// Tradução pro Cockpit V2 — mesma anatomia do blade, sem tela inventada.
// Expõe window.VendaPos, window.VendaPagamentoModal (reusado pela lista de POS/vendas).
(() => {
const { useState, useMemo, useEffect, useRef } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const UI = () => window.PBUI || {};
const D = () => window.VBD || {};
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };
const brl = (n) => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// Catálogo do balcão — featured_products.blade.php + product_list_box.blade.php.
const CAT = [
  { id: 1, nome: "Lona 380g impressa", sku: "PRD-0001", un: "m²", preco: 55, cat: "Comunicação visual", destaque: true },
  { id: 2, nome: "Vinil adesivo brilho", sku: "PRD-0002", un: "m²", preco: 42, cat: "Adesivos", destaque: true },
  { id: 3, nome: "Placa ACM 3mm", sku: "PRD-0003", un: "m²", preco: 161.3, cat: "Comunicação visual", destaque: true },
  { id: 4, nome: "Cartão de visita 1.000 un", sku: "PRD-0004", un: "cx", preco: 149.8, cat: "Impressos", destaque: true },
  { id: 5, nome: "Kit fachada 3x1m", sku: "PRD-0005", un: "Un", preco: 777.6, cat: "Comunicação visual" },
  { id: 6, nome: "Banner 440g com bastão", sku: "PRD-0010", un: "m²", preco: 75, cat: "Comunicação visual", destaque: true },
  { id: 7, nome: "Adesivo de recorte", sku: "PRD-0009", un: "m²", preco: 39.5, cat: "Adesivos" },
  { id: 8, nome: "Instalação (hora técnica)", sku: "PRD-0011", un: "Un", preco: 319, cat: "Serviços", destaque: true },
  { id: 9, nome: "Criação de arte final", sku: "PRD-0012", un: "Un", preco: 149.4, cat: "Serviços" },
];
const FORMAS_PG = [
  { id: "cash", name: "Dinheiro" }, { id: "pix", name: "Pix" }, { id: "card", name: "Cartão" },
  { id: "bank_transfer", name: "Transferência" }, { id: "cheque", name: "Cheque" }, { id: "custom_pay_1", name: "Boleto" },
];
const CONTAS = ["Caixa balcão", "Banco Inter — 077", "Banco do Brasil — 1234-5"];
const ATALHOS = [
  { op: "Finalizar expresso (dinheiro)", k: "shift+e" }, { op: "Pagar e finalizar", k: "shift+p" },
  { op: "Rascunho", k: "shift+d" }, { op: "Cotação", k: "shift+q" }, { op: "Cancelar", k: "shift+c" },
  { op: "Editar desconto", k: "shift+i" }, { op: "Editar imposto do pedido", k: "shift+t" },
  { op: "Adicionar linha de pagamento", k: "shift+n" }, { op: "Buscar produto", k: "f2" },
];
const SUSPENSAS = [
  { id: 1, ref: "SUSP-0031", cli: "Cliente balcão", itens: 4, total: 388.4, quando: "hoje 10:12", nota: "Cliente foi buscar o cartão." },
  { id: 2, ref: "SUSP-0030", cli: "Agência Norte", itens: 2, total: 1210, quando: "ontem 17:44", nota: "Aguardando aprovação da arte." },
];

// ─────────── Modal de pagamento (payment_modal + payment_row_form) ───────────
// Usado no POS e na lista de vendas ("Adicionar pagamento") — mesma peça do blade.
function VendaPagamentoModal({ venda, aberto, onClose, onConfirm, avisar }) {
  const { Modal, Fld, Sel } = UI();
  const devido = venda?.saldo != null ? venda.saldo : venda?.total || 0;
  const [linhas, setLinhas] = useState([{ id: 1, metodo: "cash", valor: devido, conta: CONTAS[0], nota: "", data: new Date().toLocaleDateString("pt-BR") }]);
  const [prazo, setPrazo] = useState(false);
  useEffect(() => { if (aberto) setLinhas([{ id: 1, metodo: "cash", valor: devido, conta: CONTAS[0], nota: "", data: new Date().toLocaleDateString("pt-BR") }]); }, [aberto, devido]);
  if (!aberto || !Modal) return null;
  const pago = linhas.reduce((a, l) => a + (parseFloat(String(l.valor).replace(",", ".")) || 0), 0);
  const troco = Math.max(0, pago - devido);
  const saldo = Math.max(0, devido - pago);
  const set = (id, k, v) => setLinhas((ls) => ls.map((l) => l.id === id ? { ...l, [k]: v } : l));
  return (
    <Modal titulo={"Pagamento" + (venda?.inv ? " · " + venda.inv : "")} onClose={onClose} largura={760}
      acoes={<>
        <button className="os-btn" onClick={onClose}>Cancelar</button>
        <button className="os-btn primary" onClick={() => { onConfirm?.({ pago, troco, saldo, prazo, linhas }); avisar?.(prazo ? "Venda a prazo registrada — saldo de " + brl(saldo) + " em contas a receber." : "Pagamento de " + brl(pago) + " registrado" + (troco ? " · troco " + brl(troco) : "") + ".", "ok"); }}>
          Finalizar
        </button>
      </>}>
      <div className="vp-pgto-resumo">
        <div><span>Total a pagar</span><b>{brl(devido)}</b></div>
        <div><span>Recebido</span><b>{brl(pago)}</b></div>
        <div className={saldo ? "warn" : ""}><span>{saldo ? "Em aberto" : "Troco"}</span><b>{brl(saldo || troco)}</b></div>
      </div>
      {linhas.map((l, i) => (
        <div key={l.id} className="vp-pgto-linha">
          <div className="pb-grid c4">
            <Fld label="Valor" req><input className="num" value={l.valor} onChange={(e) => set(l.id, "valor", e.target.value)} /></Fld>
            <Fld label="Pago em"><input value={l.data} onChange={(e) => set(l.id, "data", e.target.value)} /></Fld>
            <Fld label="Forma de pagamento" req><Sel value={l.metodo} onChange={(v) => set(l.id, "metodo", v)} options={FORMAS_PG} /></Fld>
            <Fld label="Conta de destino"><Sel value={l.conta} onChange={(v) => set(l.id, "conta", v)} options={CONTAS} vazio="Nenhuma" /></Fld>
            <Fld label="Observação do pagamento" span={4}><input value={l.nota} onChange={(e) => set(l.id, "nota", e.target.value)} placeholder="Ex.: Pix do sócio, comprovante 4471" /></Fld>
          </div>
          {linhas.length > 1 && <button className="icon-btn vp-pgto-x" aria-label="Remover linha" onClick={() => setLinhas((ls) => ls.filter((x) => x.id !== l.id))}>✕</button>}
          {i === linhas.length - 1 &&
            <button className="os-btn sm" onClick={() => setLinhas((ls) => [...ls, { id: Date.now(), metodo: "pix", valor: saldo || 0, conta: CONTAS[0], nota: "", data: new Date().toLocaleDateString("pt-BR") }])}>
              <Ic name="plus" size={12} /> Adicionar linha de pagamento
            </button>}
        </div>
      ))}
      <label className="pb-chk" style={{ marginTop: 10 }}>
        <input type="checkbox" checked={prazo} onChange={(e) => setPrazo(e.target.checked)} />
        <b>Venda a prazo</b><small>Fecha sem receber: o saldo vai pro contas a receber com o prazo do cliente.</small>
      </label>
    </Modal>
  );
}

// ─────────── POS (sale_pos/create.blade.php) ───────────
function VendaPos({ avisar, onSair }) {
  const { Widget, Fld, Sel, Modal } = UI();
  const { EmptyState } = DS();
  const [cliente, setCliente] = useState("Cliente balcão");
  const [busca, setBusca] = useState("");
  const [itens, setItens] = useState([]);
  const [desconto, setDesconto] = useState({ tipo: "percentage", valor: 0 });
  const [imposto, setImposto] = useState({ nome: "Nenhum", taxa: 0 });
  const [frete, setFrete] = useState({ valor: 0, endereco: "", status: "", entregador: "" });
  const [modal, setModal] = useState(null);
  const [pgto, setPgto] = useState(false);
  const buscaRef = useRef(null);

  const add = (p) => setItens((its) => {
    const ja = its.find((i) => i.id === p.id);
    return ja ? its.map((i) => i.id === p.id ? { ...i, qtd: i.qtd + 1 } : i) : [...its, { ...p, qtd: 1, preco: p.preco }];
  });
  const sub = itens.reduce((a, i) => a + i.qtd * i.preco, 0);
  const descV = desconto.tipo === "percentage" ? sub * (desconto.valor / 100) : Number(desconto.valor) || 0;
  const impV = (sub - descV) * (imposto.taxa / 100);
  const total = Math.max(0, sub - descV + impV + (Number(frete.valor) || 0));
  const qtdTotal = itens.reduce((a, i) => a + i.qtd, 0);
  const achados = useMemo(() => busca ? CAT.filter((p) => (p.nome + " " + p.sku).toLowerCase().includes(busca.toLowerCase())) : [], [busca]);

  const acao = (tipo) => {
    if (!itens.length) return avisar("Carrinho vazio — busque um produto (F2) antes de finalizar.", "warn");
    if (tipo === "pagar") return setPgto(true);
    const msg = {
      cash: "Venda finalizada em dinheiro — " + brl(total) + ". Recibo na impressora.",
      card: "Venda finalizada no cartão — " + brl(total) + ".",
      credit: "Venda a prazo registrada — " + brl(total) + " em contas a receber.",
      draft: "Rascunho salvo — aparece em “Lista de rascunhos”.",
      quotation: "Cotação salva — aparece em “Lista de compromissos”.",
      suspend: "Venda suspensa — retome pelo botão Suspensas.",
    }[tipo];
    avisar(msg, tipo === "suspend" ? "warn" : "ok");
    if (["cash", "card", "credit", "draft", "quotation", "suspend"].includes(tipo)) { setItens([]); setDesconto({ tipo: "percentage", valor: 0 }); setFrete({ valor: 0, endereco: "", status: "", entregador: "" }); }
  };

  useEffect(() => {
    const onKey = (e) => {
      if (e.key === "F2") { e.preventDefault(); buscaRef.current?.focus(); return; }
      if (!e.shiftKey || e.ctrlKey || e.metaKey) return;
      const k = e.key.toLowerCase();
      const mapa = { e: () => acao("cash"), p: () => acao("pagar"), d: () => acao("draft"), q: () => acao("quotation"), c: () => { setItens([]); avisar("Venda cancelada.", "warn"); }, i: () => setModal("desconto"), t: () => setModal("imposto") };
      if (mapa[k]) { e.preventDefault(); mapa[k](); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  });

  if (!Widget) return null;
  return (
    <div className="vp-pos">
      <div className="vp-pos-main">
        <Widget flush titulo={<><Ic name="cash" size={13} /> Venda no balcão</>} nota={qtdTotal + " item(ns)"}>
          <div className="vp-pos-head">
            <div className="vp-campo">
              <Ic name="clients" size={12} />
              <input value={cliente} onChange={(e) => setCliente(e.target.value)} placeholder="Digite nome do cliente / telefone" />
              <button className="icon-btn" title="Novo cliente" onClick={() => avisar("Cadastro rápido de cliente.", "ok")}>+</button>
            </div>
            <div className="vp-campo grande">
              <Ic name="search" size={12} />
              <input ref={buscaRef} value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Digite o nome / SKU / código de barras do produto" />
              <kbd>F2</kbd>
            </div>
            <button className="os-btn sm" onClick={() => setModal("suspensas")}>Suspensas <span className="vp-n">{SUSPENSAS.length}</span></button>
            <button className="os-btn sm ghost" onClick={() => setModal("atalhos")}>Atalhos</button>
          </div>
          {achados.length > 0 &&
            <div className="vp-sugestoes">
              {achados.map((p) => (
                <button key={p.id} onClick={() => { add(p); setBusca(""); }}>
                  <b>{p.nome}</b><span className="mono">{p.sku} · {brl(p.preco)}/{p.un}</span>
                </button>
              ))}
            </div>}

          <table className="pb-tbl vp-carrinho">
            <thead><tr><th>Produto</th><th className="r">Quantidade</th><th className="r">Preço unit.</th><th className="r">Subtotal</th><th /></tr></thead>
            <tbody>
              {itens.map((i) => (
                <tr key={i.id}>
                  <td><b>{i.nome}</b><div className="pb-help mono">{i.sku} · {i.un}</div></td>
                  <td className="r"><input className="vp-qtd" value={i.qtd} onChange={(e) => setItens((its) => its.map((x) => x.id === i.id ? { ...x, qtd: Math.max(0, Number(e.target.value) || 0) } : x))} /></td>
                  <td className="r"><input className="vp-qtd larga" value={i.preco} onChange={(e) => setItens((its) => its.map((x) => x.id === i.id ? { ...x, preco: Number(String(e.target.value).replace(",", ".")) || 0 } : x))} /></td>
                  <td className="r mono">{brl(i.qtd * i.preco)}</td>
                  <td className="r"><button className="icon-btn" aria-label="Remover item" onClick={() => setItens((its) => its.filter((x) => x.id !== i.id))}>✕</button></td>
                </tr>
              ))}
              {!itens.length &&
                <tr><td colSpan={5} style={{ padding: 0 }}>
                  {EmptyState
                    ? <EmptyState variant="first" icon={<Ic name="cash" size={18} />} title="Carrinho vazio" description="Busque um produto por nome, SKU ou código de barras — ou clique num destaque ao lado." />
                    : <p className="pb-help" style={{ padding: 16 }}>Carrinho vazio.</p>}
                </td></tr>}
            </tbody>
          </table>
        </Widget>

        <div className="vp-totais">
          <div><span>Itens</span><b className="mono">{qtdTotal}</b></div>
          <div><span>Total</span><b className="mono">{brl(sub)}</b></div>
          <button className="vp-tot-edit" onClick={() => setModal("desconto")}><span>Desconto (−) <Ic name="pencil" size={11} /></span><b className="mono">{brl(descV)}</b></button>
          <button className="vp-tot-edit" onClick={() => setModal("imposto")}><span>Imposto do pedido (+) <Ic name="pencil" size={11} /></span><b className="mono">{brl(impV)}</b></button>
          <button className="vp-tot-edit" onClick={() => setModal("frete")}><span>Frete (+) <Ic name="pencil" size={11} /></span><b className="mono">{brl(Number(frete.valor) || 0)}</b></button>
        </div>

        <div className="vp-acoes">
          <button className="os-btn" onClick={() => acao("draft")}><Ic name="quote" size={13} /> Rascunho</button>
          <button className="os-btn" onClick={() => acao("quotation")}>Cotação</button>
          <button className="os-btn" onClick={() => acao("suspend")}>Suspender</button>
          <button className="os-btn" onClick={() => acao("credit")}>Venda a prazo</button>
          <button className="os-btn" onClick={() => acao("card")}>Cartão expresso</button>
          <div className="sp" />
          <div className="vp-pagar"><span>Total a pagar</span><b className="mono">{brl(total)}</b></div>
          <button className="os-btn" onClick={() => acao("cash")}>Dinheiro expresso</button>
          <button className="os-btn primary" onClick={() => acao("pagar")}>Pagar e finalizar</button>
          <button className="os-btn ghost danger" onClick={() => { setItens([]); avisar("Venda cancelada.", "warn"); onSair?.(); }}>Cancelar</button>
        </div>
      </div>

      <aside className="vp-pos-side">
        <Widget titulo={<><Ic name="grid" size={13} /> Produtos em destaque</>}>
          <div className="vp-destaques">
            {CAT.filter((p) => p.destaque).map((p) => (
              <button key={p.id} onClick={() => add(p)}>
                <b>{p.nome}</b>
                <span className="mono">{brl(p.preco)}/{p.un}</span>
              </button>
            ))}
          </div>
        </Widget>
        <Widget titulo={<><Ic name="clock" size={13} /> Transações recentes</>}>
          <ul className="vp-recentes">
            {(D().POS || []).slice(0, 5).map((s) => (
              <li key={s.id}>
                <button onClick={() => avisar("Abrindo " + s.inv + ".", "ok")}>
                  <b className="mono">{s.inv}</b>
                  <span>{s.cli}</span>
                  <em className="mono">{brl(s.total)}</em>
                </button>
              </li>
            ))}
          </ul>
        </Widget>
      </aside>

      {modal === "desconto" && Modal &&
        <Modal titulo="Editar desconto" onClose={() => setModal(null)} largura={520}
          acoes={<><button className="os-btn" onClick={() => setModal(null)}>Fechar</button><button className="os-btn primary" onClick={() => setModal(null)}>Aplicar</button></>}>
          <div className="pb-grid c2">
            <Fld label="Tipo de desconto"><Sel value={desconto.tipo} onChange={(v) => setDesconto({ ...desconto, tipo: v })} options={[{ id: "fixed", name: "Fixo" }, { id: "percentage", name: "Percentual" }]} /></Fld>
            <Fld label="Valor do desconto"><input className="num" value={desconto.valor} onChange={(e) => setDesconto({ ...desconto, valor: e.target.value })} /></Fld>
          </div>
          <p className="pb-help" style={{ marginTop: 10 }}>Desconto aplicado sobre {brl(sub)} — resultado {brl(descV)}.</p>
        </Modal>}
      {modal === "imposto" && Modal &&
        <Modal titulo="Editar imposto do pedido" onClose={() => setModal(null)} largura={520}
          acoes={<><button className="os-btn" onClick={() => setModal(null)}>Fechar</button><button className="os-btn primary" onClick={() => setModal(null)}>Aplicar</button></>}>
          <Fld label="Imposto">
            <Sel value={imposto.nome} onChange={(v) => setImposto({ nome: v, taxa: { "Nenhum": 0, "ICMS 18%": 18, "ICMS 12%": 12, "ISS 5%": 5 }[v] || 0 })} options={["Nenhum", "ICMS 18%", "ICMS 12%", "ISS 5%"]} />
          </Fld>
          <p className="pb-help" style={{ marginTop: 10 }}>Incide sobre {brl(sub - descV)} — resultado {brl(impV)}.</p>
        </Modal>}
      {modal === "frete" && Modal &&
        <Modal titulo="Frete e entrega" onClose={() => setModal(null)} largura={660}
          acoes={<><button className="os-btn" onClick={() => setModal(null)}>Fechar</button><button className="os-btn primary" onClick={() => setModal(null)}>Aplicar</button></>}>
          <div className="pb-grid c2">
            <Fld label="Valor do frete"><input className="num" value={frete.valor} onChange={(e) => setFrete({ ...frete, valor: e.target.value })} /></Fld>
            <Fld label="Status de envio"><Sel value={frete.status} onChange={(v) => setFrete({ ...frete, status: v })} options={D().ENVIO || []} vazio="Sem envio" /></Fld>
            <Fld label="Entregador"><Sel value={frete.entregador} onChange={(v) => setFrete({ ...frete, entregador: v })} options={D().ENTREGADORES || []} vazio="Sem entregador" /></Fld>
            <Fld label="Endereço de entrega" span={2}><input value={frete.endereco} onChange={(e) => setFrete({ ...frete, endereco: e.target.value })} placeholder="Rua, número, bairro, cidade" /></Fld>
          </div>
        </Modal>}
      {modal === "suspensas" && Modal &&
        <Modal titulo="Vendas suspensas" onClose={() => setModal(null)} largura={640}
          acoes={<button className="os-btn" onClick={() => setModal(null)}>Fechar</button>}>
          <table className="pb-tbl">
            <thead><tr><th>Referência</th><th>Cliente</th><th className="r">Itens</th><th className="r">Total</th><th>Nota</th><th /></tr></thead>
            <tbody>
              {SUSPENSAS.map((s) => (
                <tr key={s.id}>
                  <td className="mono">{s.ref}<div className="pb-help">{s.quando}</div></td>
                  <td>{s.cli}</td><td className="r mono">{s.itens}</td><td className="r mono">{brl(s.total)}</td>
                  <td className="pb-help">{s.nota}</td>
                  <td className="r"><button className="os-btn sm" onClick={() => { setModal(null); avisar(s.ref + " retomada no balcão.", "ok"); }}>Retomar</button></td>
                </tr>
              ))}
            </tbody>
          </table>
        </Modal>}
      {modal === "atalhos" && Modal &&
        <Modal titulo="Atalhos de teclado" onClose={() => setModal(null)} largura={520}
          acoes={<button className="os-btn" onClick={() => setModal(null)}>Fechar</button>}>
          <table className="pb-tbl">
            <thead><tr><th>Operação</th><th>Atalho</th></tr></thead>
            <tbody>{ATALHOS.map((a) => <tr key={a.k}><td>{a.op}</td><td className="mono">{a.k}</td></tr>)}</tbody>
          </table>
          <p className="pb-help" style={{ marginTop: 10 }}>Configurável em Configurações → POS, igual ao legado.</p>
        </Modal>}

      <VendaPagamentoModal aberto={pgto} venda={{ total, saldo: total }} onClose={() => setPgto(false)} avisar={avisar}
        onConfirm={() => { setPgto(false); setItens([]); }} />
    </div>
  );
}

Object.assign(window, { VendaPos, VendaPagamentoModal, VENDA_POS_CAT: CAT, VENDA_FORMAS_PG: FORMAS_PG });
})();
