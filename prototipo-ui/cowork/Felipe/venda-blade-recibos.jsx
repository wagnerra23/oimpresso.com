// venda-blade-recibos.jsx — Onda A: os layouts de recibo do legado
// (sale_pos/receipts/*.blade.php: slim, slim2, classic, elegant, elegant_modified,
// detailed, columnize-taxes, packing_slip, delivery_note).
// O protótipo já tinha térmica+A4 (vendas-extras ReciboView) — aqui é a folha do BLADE,
// com os 9 nomes reais e as diferenças que existem de verdade entre eles.
// Expõe window.VendaRecibo.
(() => {
const { useState } = React;
const UI = () => window.PBUI || {};
const brl = (n) => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const EMPRESA = {
  nome: "Office Impresso Comunicação Visual", doc: "CNPJ 41.882.334/0001-07",
  end: "Av. Brasil, 1420 — Centro · Jaú/SP", tel: "(14) 3622-1180 · contato@oimpresso.com.br",
  ie: "IE 415.882.334.110",
};
// Cada layout do blade → largura do papel e o que ele mostra.
const LAYOUTS = [
  { id: "slim", nome: "slim", papel: "58mm", tipo: "termica", desc: "Cupom estreito, sem impostos por linha." },
  { id: "slim2", nome: "slim2", papel: "80mm", tipo: "termica", desc: "Cupom 80mm com dados do cliente." },
  { id: "classic", nome: "classic", papel: "A4", tipo: "folha", desc: "Fatura clássica: tabela simples e totais à direita." },
  { id: "elegant", nome: "elegant", papel: "A4", tipo: "folha", desc: "Cabeçalho em faixa, tipografia maior." },
  { id: "elegant_modified", nome: "elegant_modified", papel: "A4", tipo: "folha", desc: "Elegant com bloco fiscal e observações." },
  { id: "detailed", nome: "detailed", papel: "A4", tipo: "folha", desc: "Imposto e desconto por linha." },
  { id: "columnize-taxes", nome: "columnize-taxes", papel: "A4", tipo: "folha", desc: "Impostos em colunas separadas (base, alíquota, valor)." },
  { id: "packing_slip", nome: "packing_slip", papel: "A4", tipo: "romaneio", desc: "Romaneio de separação — sem preços." },
  { id: "delivery_note", nome: "delivery_note", papel: "A4", tipo: "entrega", desc: "Nota de entrega com campo de assinatura." },
];

function Cabecalho({ centrado, faixa }) {
  return (
    <div className={"vr-head" + (centrado ? " c" : "") + (faixa ? " faixa" : "")}>
      <b>{EMPRESA.nome}</b>
      <span>{EMPRESA.end}</span>
      <span>{EMPRESA.tel}</span>
      <span>{EMPRESA.doc} · {EMPRESA.ie}</span>
    </div>
  );
}

function Termica({ v, linhas, largo }) {
  return (
    <div className={"vr-paper vr-termica" + (largo ? " l" : "")}>
      <Cabecalho centrado />
      <div className="vr-linha" />
      <div className="vr-meta">
        <div><span>Venda</span><b>{v.inv}</b></div>
        <div><span>Data</span><b>{v.data}</b></div>
        <div><span>Vendedor</span><b>{v.quem}</b></div>
        {largo && <div><span>Cliente</span><b>{v.cli}</b></div>}
        {largo && <div><span>Contato</span><b>{v.tel}</b></div>}
      </div>
      <div className="vr-linha" />
      <table className="vr-itens">
        <tbody>
          {linhas.map((l, i) => (
            <React.Fragment key={i}>
              <tr><td colSpan={2} className="vr-prod">{l.nome}</td></tr>
              <tr><td>{l.qtd} {l.un} × {brl(l.preco)}</td><td className="r">{brl(l.subtotal)}</td></tr>
            </React.Fragment>
          ))}
        </tbody>
      </table>
      <div className="vr-linha" />
      <div className="vr-tot">
        <div><span>Total</span><b>{brl(v.total)}</b></div>
        <div><span>Pago ({v.forma})</span><b>{brl(v.pago)}</b></div>
        {!!v.saldo && <div><span>Em aberto</span><b>{brl(v.saldo)}</b></div>}
      </div>
      <div className="vr-linha" />
      <p className="vr-foot">Obrigado pela preferência.<br />Documento não fiscal — a NF-e vai por e-mail.</p>
    </div>
  );
}

function Folha({ v, linhas, variante }) {
  const detalhado = variante === "detailed";
  const colunas = variante === "columnize-taxes";
  const imposto = 0.18;
  return (
    <div className={"vr-paper vr-a4 vr-" + variante}>
      <Cabecalho faixa={variante === "elegant" || variante === "elegant_modified"} />
      <div className="vr-a4-tit">
        <h1>Fatura {v.inv}</h1>
        <span>{v.data} · {v.loc}</span>
      </div>
      <div className="vr-a4-partes">
        <div><span>Cliente</span><b>{v.cli}</b><em>{v.tel}</em></div>
        <div><span>Vendedor</span><b>{v.quem}</b><em>{v.serv}</em></div>
        <div><span>Pagamento</span><b>{v.forma}</b><em>{v.saldo ? "Saldo " + brl(v.saldo) : "Quitada"}</em></div>
      </div>
      <table className="vr-a4-tbl">
        <thead>
          <tr>
            <th>Produto</th><th className="r">Qtd</th><th className="r">Preço unit.</th>
            {detalhado && <th className="r">Desconto</th>}
            {detalhado && <th className="r">Imposto</th>}
            {colunas && <th className="r">Base</th>}
            {colunas && <th className="r">Alíq.</th>}
            {colunas && <th className="r">ICMS</th>}
            <th className="r">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          {linhas.map((l, i) => (
            <tr key={i}>
              <td><b>{l.nome}</b><em>{l.sku}</em></td>
              <td className="r">{l.qtd} {l.un}</td>
              <td className="r">{brl(l.preco)}</td>
              {detalhado && <td className="r">{brl(0)}</td>}
              {detalhado && <td className="r">{brl(l.subtotal * imposto)}</td>}
              {colunas && <td className="r">{brl(l.subtotal)}</td>}
              {colunas && <td className="r">18%</td>}
              {colunas && <td className="r">{brl(l.subtotal * imposto)}</td>}
              <td className="r">{brl(l.subtotal)}</td>
            </tr>
          ))}
        </tbody>
      </table>
      <div className="vr-a4-tot">
        <dl>
          <dt>Subtotal</dt><dd>{brl(v.total)}</dd>
          <dt>Total pago</dt><dd>{brl(v.pago)}</dd>
          <dt>Saldo devedor</dt><dd>{brl(v.saldo)}</dd>
          <dt className="tot">Total</dt><dd className="tot">{brl(v.total)}</dd>
        </dl>
      </div>
      {variante === "elegant_modified" &&
        <div className="vr-a4-fiscal">
          <div><span>Regime</span><b>Simples Nacional</b></div>
          <div><span>Natureza</span><b>5102 — Venda de mercadoria</b></div>
          <div><span>Observação</span><b>{v.obs || "—"}</b></div>
        </div>}
      <footer className="vr-a4-foot">Documento não fiscal · gerado pelo Office Impresso em {v.data}</footer>
    </div>
  );
}

function Romaneio({ v, linhas, entrega }) {
  return (
    <div className="vr-paper vr-a4 vr-romaneio">
      <Cabecalho />
      <div className="vr-a4-tit">
        <h1>{entrega ? "Nota de entrega" : "Romaneio de separação"} · {v.inv}</h1>
        <span>{v.data} · {v.loc}</span>
      </div>
      <div className="vr-a4-partes">
        <div><span>Cliente</span><b>{v.cli}</b><em>{v.tel}</em></div>
        <div><span>Entrega</span><b>{v.serv}</b><em>{v.loc}</em></div>
        <div><span>Conferente</span><b>{v.quem}</b><em>{v.itens} volume(s)</em></div>
      </div>
      <table className="vr-a4-tbl">
        <thead><tr><th>Produto</th><th>SKU</th><th className="r">Qtd</th><th className="r">Conferido</th></tr></thead>
        <tbody>{linhas.map((l, i) => <tr key={i}><td>{l.nome}</td><td className="mono">{l.sku}</td><td className="r">{l.qtd} {l.un}</td><td className="r vr-box">☐</td></tr>)}</tbody>
      </table>
      {entrega &&
        <div className="vr-assina">
          <div><i />Recebi os itens acima em conformidade</div>
          <div><i />Data / hora</div>
        </div>}
      <footer className="vr-a4-foot">{entrega ? "Uma via fica com o cliente, outra volta assinada com o entregador." : "Sem valores — folha de separação para a produção."}</footer>
    </div>
  );
}

function VendaRecibo({ venda, onClose, avisar }) {
  const { Modal } = UI();
  const [lay, setLay] = useState("slim2");
  if (!venda || !Modal) return null;
  const linhas = (window.VENDA_LINHAS || (() => []))(venda);
  const atual = LAYOUTS.find((l) => l.id === lay) || LAYOUTS[0];
  const corpo =
    atual.tipo === "termica" ? <Termica v={venda} linhas={linhas} largo={lay === "slim2"} /> :
    atual.tipo === "romaneio" ? <Romaneio v={venda} linhas={linhas} /> :
    atual.tipo === "entrega" ? <Romaneio v={venda} linhas={linhas} entrega /> :
    <Folha v={venda} linhas={linhas} variante={lay} />;
  return (
    <Modal titulo={"Recibo · " + venda.inv} onClose={onClose} largura={980}
      acoes={<>
        <button className="os-btn" onClick={onClose}>Fechar</button>
        <button className="os-btn" onClick={() => avisar?.("Recibo enviado por e-mail para o cliente.", "ok")}>Enviar por e-mail</button>
        <button className="os-btn primary" onClick={() => { document.body.classList.add("vr-print"); window.print(); setTimeout(() => document.body.classList.remove("vr-print"), 400); }}>Imprimir</button>
      </>}>
      <div className="vr-wrap">
        <aside className="vr-lays">
          <span className="pb-help">Layout do recibo (Configurações → Layouts de fatura)</span>
          {LAYOUTS.map((l) => (
            <button key={l.id} className={"vr-lay" + (lay === l.id ? " on" : "")} onClick={() => setLay(l.id)}>
              <b className="mono">{l.nome}</b>
              <span>{l.papel} · {l.desc}</span>
            </button>
          ))}
        </aside>
        <div className="vr-stage">{corpo}</div>
      </div>
    </Modal>
  );
}

Object.assign(window, { VendaRecibo, VENDA_RECIBO_LAYOUTS: LAYOUTS });
})();
