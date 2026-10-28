// compras-cards-novos.jsx — os cards que entram na Nova compra, na edição e no detalhe da compra
// antes do cutover das Blades (pedido [W] 2026-10-27). Nenhum deles estava desenhado; este arquivo
// é a proposta de forma para o OK de design. Comportamento e regras já estão nos PRs fechados
// #10061 · #10070 · #10071 · #10073 · #10074 · #10077 (branches guardados).
//
//   · Itens da compra → "Importar planilha" + "Baixar modelo" no cabeçalho do card
//   · Despesas adicionais e anexo → até 4 pares nome/valor, somam ao total; anexo da nota
//   · Prazo, frete e campos da empresa → prazo (dias/meses), detalhes do frete, campos que a
//     empresa configurou (só os que têm rótulo, obrigatório marcado)
//   · Pagamento (opcional) → valor pago agora, forma, data, conta; não pode passar do total
//   · Detalhe › Pagamentos → "+ Registrar pagamento" (só com saldo, como no DrawerView de
//     compras-page.jsx) abre um diálogo com valor, forma, data, conta e observação
//
// Tokens só do DS (escopo .compras-root, mesmo namespace --cmp-* de compras-page.css).
// Expõe window.ComprasCardsNovosPage. Não registra rota: quem integra ao shell é a sessão do
// shell (#10055).
(() => {
const { useState } = React;
const brl = (n) => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const ESTILO = `
.cmp-novos{padding:16px 20px;display:flex;flex-direction:column;gap:12px;overflow:auto;height:100%}
.cmp-novos .abas{display:flex;gap:6px}
.cmp-novos .aba{border:1px solid var(--cmp-line);background:var(--cmp-paper);color:var(--cmp-ink-2);border-radius:5px;padding:5px 12px;cursor:pointer}
.cmp-novos .aba[aria-selected="true"]{border-color:var(--cmp-accent);color:var(--cmp-accent);background:var(--cmp-accent-soft)}
.cmp-novos .card{background:var(--cmp-paper);border:1px solid var(--cmp-line);border-radius:6px}
.cmp-novos .card-hd{display:flex;align-items:center;gap:8px;padding:10px 14px 6px}
.cmp-novos .card-hd h3{flex:1;margin:0;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--cmp-ink-3);font-weight:500}
.cmp-novos .card-bd{padding:4px 14px 14px}
.cmp-novos .grade{display:grid;gap:10px}
.cmp-novos .g2{grid-template-columns:1fr 1fr}
.cmp-novos .g4{grid-template-columns:repeat(4,1fr)}
.cmp-novos label{display:block;font-size:12px;color:var(--cmp-ink-2);margin-bottom:4px}
.cmp-novos input,.cmp-novos select,.cmp-novos textarea{width:100%;height:34px;padding:0 9px;border:1px solid var(--cmp-line);border-radius:5px;background:var(--cmp-bg);color:var(--cmp-ink)}
.cmp-novos textarea{height:auto;padding:7px 9px}
.cmp-novos .num{text-align:right;font-family:var(--cmp-mono)}
.cmp-novos .dica{font-size:11px;color:var(--cmp-ink-3);margin-top:4px}
.cmp-novos .erro{font-size:12px;color:var(--cmp-err);margin-top:4px}
.cmp-novos .btn{height:30px;padding:0 12px;border:1px solid var(--cmp-line);border-radius:5px;background:var(--cmp-paper);color:var(--cmp-ink);cursor:pointer}
.cmp-novos .btn.primario{background:var(--cmp-accent);border-color:var(--cmp-accent);color:var(--accent-fg)}
.cmp-novos .link{font-size:12px;color:var(--cmp-accent);text-decoration:none}
.cmp-novos .linha-total{display:flex;justify-content:space-between;padding:3px 0}
.cmp-novos .linha-total.final{border-top:1px solid var(--cmp-line);margin-top:6px;padding-top:8px;font-weight:600}
.cmp-novos table{width:100%;border-collapse:collapse;font-size:12px}
.cmp-novos th{text-align:left;font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:var(--cmp-ink-3);font-weight:500;padding:6px 0;border-bottom:1px solid var(--cmp-line)}
.cmp-novos td{padding:6px 0;border-bottom:1px solid var(--cmp-line-2)}
.cmp-novos .r{text-align:right}
.cmp-novos .fundo{position:absolute;inset:0;background:color-mix(in oklab, var(--cmp-ink) 35%, transparent);display:flex;align-items:center;justify-content:center}
.cmp-novos .dialogo{width:440px;background:var(--cmp-paper);border:1px solid var(--cmp-line);border-radius:8px;padding:16px}
.cmp-novos .dialogo h4{margin:0 0 2px;font-size:15px}
.cmp-novos .dialogo .sub{font-size:12px;color:var(--cmp-ink-3);margin-bottom:12px}
.cmp-novos .acoes{display:flex;justify-content:flex-end;gap:8px;margin-top:12px}
`;

function Card({ titulo, acoes, children, contrato }) {
  return (
    <section className="card" data-contract={contrato}>
      <div className="card-hd"><h3>{titulo}</h3>{acoes}</div>
      <div className="card-bd">{children}</div>
    </section>
  );
}

function NovaCompra() {
  const [despesas, setDespesas] = useState([
    { nome: "Frete extra", valor: 12.5 },
    { nome: "Seguro", valor: 2.5 },
    { nome: "", valor: 0 },
    { nome: "", valor: 0 },
  ]);
  const [pago, setPago] = useState(15);
  const itens = 40;
  const frete = 5;
  const somaDespesas = despesas.reduce((s, d) => s + (Number(d.valor) || 0), 0);
  const total = itens + frete + somaDespesas;
  const acima = pago > total;

  return (
    <>
      <Card
        titulo="Itens da compra"
        contrato="purchase-itens"
        acoes={<>
          <span className="dica">1 item</span>
          <button className="btn">Importar planilha</button>
          <a className="link" href="#">Baixar modelo</a>
        </>}>
        <table>
          <thead><tr><th>Produto</th><th className="r">Qtd</th><th className="r">Custo</th><th className="r">Subtotal</th></tr></thead>
          <tbody><tr><td>Camiseta básica P/Preto</td><td className="r">4</td><td className="r">{brl(10)}</td><td className="r">{brl(40)}</td></tr></tbody>
        </table>
      </Card>

      <div className="grade g2">
        <Card titulo="Despesas adicionais e anexo" contrato="purchase-despesas-anexo">
          <div className="grade g2">
            <div className="grade">
              {despesas.map((d, i) => (
                <div key={i} className="grade" style={{ gridTemplateColumns: "1fr 8rem" }}>
                  <input aria-label={`Despesa ${i + 1}: nome`} placeholder={`Despesa ${i + 1}`} value={d.nome}
                    onChange={(e) => setDespesas(despesas.map((x, j) => (j === i ? { ...x, nome: e.target.value } : x)))} />
                  <input aria-label={`Despesa ${i + 1}: valor`} className="num" value={d.valor ? d.valor.toFixed(2).replace(".", ",") : ""}
                    onChange={(e) => setDespesas(despesas.map((x, j) => (j === i ? { ...x, valor: Number(e.target.value.replace(",", ".")) || 0 } : x)))} />
                </div>
              ))}
              <p className="dica">Somam ao total final da compra.</p>
            </div>
            <div>
              <label htmlFor="cn-anexo">Anexo da nota</label>
              <input id="cn-anexo" type="file" style={{ paddingTop: 6 }} />
              <p className="dica">PDF, imagem, planilha ou documento.</p>
            </div>
          </div>
        </Card>

        <Card titulo="Totais" contrato="purchase-totais">
          <div className="linha-total"><span>Subtotal itens</span><span>{brl(itens)}</span></div>
          <div className="linha-total"><span>Frete</span><span>+ {brl(frete)}</span></div>
          <div className="linha-total"><span>Despesas adicionais</span><span>+ {brl(somaDespesas)}</span></div>
          <div className="linha-total final"><span>Total final</span><span>{brl(total)}</span></div>
        </Card>
      </div>

      <Card titulo="Prazo, frete e campos da empresa" contrato="purchase-campos-extras">
        <div className="grade g4">
          <div>
            <label htmlFor="cn-prazo">Prazo de pagamento</label>
            <div className="grade" style={{ gridTemplateColumns: "5rem 1fr" }}>
              <input id="cn-prazo" className="num" defaultValue="30" />
              <select aria-label="Unidade do prazo" defaultValue="days"><option value="">—</option><option value="days">dias</option><option value="months">meses</option></select>
            </div>
          </div>
          <div style={{ gridColumn: "span 3" }}>
            <label htmlFor="cn-frete">Detalhes do frete</label>
            <input id="cn-frete" defaultValue="CIF por conta do fornecedor" />
          </div>
          <div>
            <label htmlFor="cn-cf1">Pedido do fornecedor *</label>
            <input id="cn-cf1" defaultValue="OC-1" required />
          </div>
          <div>
            <label htmlFor="cn-cf2">Transportadora</label>
            <input id="cn-cf2" defaultValue="Transportadora Y" />
          </div>
        </div>
        <p className="dica">Os dois últimos são exemplo: aparecem só os campos que a empresa configurou em Empresa › Rótulos.</p>
      </Card>

      <Card titulo="Pagamento (opcional)" contrato="purchase-pagamento">
        <div className="grade g4">
          <div>
            <label htmlFor="cn-pago">Valor pago agora</label>
            <input id="cn-pago" className="num" aria-invalid={acima} value={pago ? pago.toFixed(2).replace(".", ",") : ""}
              onChange={(e) => setPago(Number(e.target.value.replace(",", ".")) || 0)} />
            <p className="dica">Total da compra: {brl(total)}</p>
          </div>
          <div>
            <label htmlFor="cn-forma">Forma de pagamento</label>
            <select id="cn-forma" defaultValue="cash"><option value="cash">Dinheiro</option><option value="bank_transfer">Transferência</option><option value="custom_pay_1">PIX</option></select>
          </div>
          <div>
            <label htmlFor="cn-data">Data</label>
            <input id="cn-data" type="datetime-local" defaultValue="2026-10-27T10:30" />
          </div>
          <div>
            <label htmlFor="cn-conta">Conta</label>
            <select id="cn-conta" defaultValue=""><option value="">Sem conta</option><option value="1">Caixa da loja</option></select>
          </div>
        </div>
        {acima && <p className="erro">O pagamento passa do total da compra.</p>}
        <p className="dica">Em branco, a compra nasce a prazo.</p>
      </Card>
    </>
  );
}

function DetalheCompra() {
  const total = 135;
  const [pagos, setPagos] = useState([{ data: "20/10/2026 09:12", forma: "PIX", valor: 50 }]);
  const [aberto, setAberto] = useState(false);
  const [valor, setValor] = useState(0);
  const pago = pagos.reduce((s, p) => s + p.valor, 0);
  const saldo = total - pago;
  const acima = valor > saldo;

  const abrir = () => { setValor(saldo); setAberto(true); };
  const registrar = () => {
    if (valor <= 0 || acima) return;
    setPagos([...pagos, { data: "27/10/2026 10:30", forma: "Dinheiro", valor }]);
    setAberto(false);
  };

  return (
    <div className="grade g2" style={{ position: "relative" }}>
      <Card
        titulo="Pagamentos"
        contrato="registrar-pagamento"
        acoes={saldo > 0.005 ? <button className="btn primario" onClick={abrir}>+ Registrar pagamento</button> : null}>
        <table>
          <thead><tr><th>Data</th><th>Método</th><th className="r">Valor</th></tr></thead>
          <tbody>{pagos.map((p, i) => <tr key={i}><td>{p.data}</td><td>{p.forma}</td><td className="r">{brl(p.valor)}</td></tr>)}</tbody>
        </table>
      </Card>
      <Card titulo="Totais">
        <div className="linha-total"><span>Total</span><span>{brl(total)}</span></div>
        <div className="linha-total"><span>Pago</span><span>{brl(pago)}</span></div>
        <div className="linha-total final"><span>A pagar</span><span style={{ color: saldo > 0 ? "var(--cmp-err)" : "var(--cmp-ink-3)" }}>{brl(saldo)}</span></div>
      </Card>

      {aberto && (
        <div className="fundo" role="presentation" onClick={() => setAberto(false)}>
          <div className="dialogo" role="dialog" aria-modal="true" aria-labelledby="rp-titulo" onClick={(e) => e.stopPropagation()}>
            <h4 id="rp-titulo">Registrar pagamento</h4>
            <div className="sub">Compra PO-2026-0114 · saldo {brl(saldo)}</div>
            <div className="grade">
              <div>
                <label htmlFor="rp-valor">Valor (R$)</label>
                <input id="rp-valor" className="num" aria-invalid={acima} value={valor ? valor.toFixed(2).replace(".", ",") : ""}
                  onChange={(e) => setValor(Number(e.target.value.replace(",", ".")) || 0)} />
                {acima && <p className="erro">O valor passa do saldo da compra ({brl(saldo)}).</p>}
              </div>
              <div className="grade g2">
                <div>
                  <label htmlFor="rp-forma">Forma de pagamento</label>
                  <select id="rp-forma" defaultValue="cash"><option value="cash">Dinheiro</option><option value="bank_transfer">Transferência</option></select>
                </div>
                <div>
                  <label htmlFor="rp-data">Data</label>
                  <input id="rp-data" type="date" defaultValue="2026-10-27" />
                </div>
              </div>
              <div>
                <label htmlFor="rp-conta">Conta</label>
                <select id="rp-conta" defaultValue=""><option value="">Sem conta</option><option value="1">Caixa da loja</option></select>
              </div>
              <div>
                <label htmlFor="rp-obs">Observação</label>
                <textarea id="rp-obs" rows={2} />
              </div>
            </div>
            <div className="acoes">
              <button className="btn" onClick={() => setAberto(false)}>Cancelar</button>
              <button className="btn primario" onClick={registrar}>Registrar</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function ComprasCardsNovosPage() {
  const [aba, setAba] = useState("nova");
  return (
    <div className="compras-root">
      <style>{ESTILO}</style>
      <div className="cmp-novos">
        <div className="abas" role="tablist" aria-label="Telas com cards novos">
          <button className="aba" role="tab" aria-selected={aba === "nova"} onClick={() => setAba("nova")}>Nova compra e edição</button>
          <button className="aba" role="tab" aria-selected={aba === "detalhe"} onClick={() => setAba("detalhe")}>Detalhe da compra</button>
        </div>
        {aba === "nova" ? <NovaCompra /> : <DetalheCompra />}
      </div>
    </div>
  );
}

window.ComprasCardsNovosPage = ComprasCardsNovosPage;
})();
