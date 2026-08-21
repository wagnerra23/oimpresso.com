// produto-analises.jsx — Onda 3 do refino: a leitura do catálogo que o blade não dá.
// Não é tela nova de cadastro: é o mesmo dado do índice (PBD) lido por decisão de compra
// e de preço — reposição, margem, duplicatas, produtos parados e curva ABC.
// Expõe window.ProdutoAnalises.
(() => {
const { useState, useMemo } = React;
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };
const D = () => window.PBD;
const U = () => window.PBUI;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};

// Giro determinístico (o vivo lê de transactions) — só pra a análise ter chão.
const giro = (p) => (p.id * 17 + 23) % 90 + 4;

function ProdutoAnalises({ onIr, avisar }) {
  const { PRODUCTS, fmtBRL, fmtQty } = D();
  const { Widget } = U();
  const { Chart } = DS();
  const [aba, setAba] = useState("reposicao");
  const [pedido, setPedido] = useState([]);
  const irPara = (rota, ctx) => window.PBIr ? window.PBIr(rota, ctx) : null;

  const dados = useMemo(() => {
    const comMargem = PRODUCTS.map((p) => {
      const dpp = p.variations[0].dpp, dsp = p.variations[0].dsp;
      const vendido = giro(p);
      return { p, dpp, dsp, margem: dsp > 0 ? ((dsp - dpp) / dsp) * 100 : 0, lucro: dsp - dpp, vendido, receita: vendido * dsp };
    });
    const reposicao = comMargem
      .filter((x) => x.p.stockOn && x.p.alert != null && x.p.stock <= x.p.alert * 1.5)
      .map((x) => {
        const consumoDia = x.vendido / 30;
        const dias = consumoDia > 0 ? Math.floor(x.p.stock / consumoDia) : 999;
        const sugerido = Math.max(x.p.alert * 2 - x.p.stock, Math.ceil(consumoDia * 15));
        return { ...x, dias, sugerido, custo: sugerido * x.dpp };
      })
      .sort((a, b) => a.dias - b.dias);
    const margens = [...comMargem].sort((a, b) => a.margem - b.margem);
    const paradas = comMargem.filter((x) => x.p.stockOn && x.vendido < 20).sort((a, b) => a.vendido - b.vendido);
    // Duplicatas: mesmo SKU ou nome muito parecido (o que suja catálogo migrado do Firebird).
    const chave = (s) => s.toLowerCase().replace(/[^a-z0-9]/g, "").slice(0, 14);
    const mapa = {};
    PRODUCTS.forEach((p) => { const k = chave(p.name); (mapa[k] = mapa[k] || []).push(p); });
    const duplicatas = Object.values(mapa).filter((g) => g.length > 1);
    // Curva ABC por receita.
    const porReceita = [...comMargem].sort((a, b) => b.receita - a.receita);
    const total = porReceita.reduce((s, x) => s + x.receita, 0) || 1;
    let acc = 0;
    const abc = porReceita.map((x) => { acc += x.receita; const cum = acc / total; return { ...x, cum, classe: cum <= 0.8 ? "A" : cum <= 0.95 ? "B" : "C" }; });
    return { comMargem, reposicao, margens, paradas, duplicatas, abc, total };
  }, [PRODUCTS]);

  const abas = [
    { k: "reposicao", l: "Reposição", n: dados.reposicao.length },
    { k: "margem", l: "Margem", n: dados.margens.filter((x) => x.margem < 25).length },
    { k: "abc", l: "Curva ABC" },
    { k: "paradas", l: "Sem giro", n: dados.paradas.length },
    { k: "duplicatas", l: "Duplicatas", n: dados.duplicatas.length },
  ];
  const maxRec = Math.max(...dados.abc.map((x) => x.receita));

  return (
    <>
      <div className="pb-kpis">
        <div className={"pb-kpi" + (dados.reposicao.length ? " neg" : "")}><small>Repor agora</small><b>{dados.reposicao.length}</b><div className="ln">custo estimado {fmtBRL(dados.reposicao.reduce((s, x) => s + x.custo, 0))}</div></div>
        <div className="pb-kpi warn"><small>Margem abaixo de 25%</small><b>{dados.margens.filter((x) => x.margem < 25).length}</b><div className="ln">preço de venda perto do custo</div></div>
        <div className="pb-kpi"><small>Classe A (80% da receita)</small><b>{dados.abc.filter((x) => x.classe === "A").length}</b><div className="ln">de {PRODUCTS.length} produtos</div></div>
        <div className="pb-kpi"><small>Receita do período</small><b style={{ fontSize: 17 }}>{fmtBRL(dados.total)}</b><div className="ln">últimos 30 dias · por preço de venda</div></div>
      </div>

      <Widget flush titulo={<><Ic name="chart" size={13} /> Análises do catálogo</>} nota={PRODUCTS.length + " produtos"}>
        <nav className="cli-moduletopnav" aria-label="Análises do catálogo" style={{ padding: "0 12px" }}>
          {abas.map((a) => (
            <button key={a.k} className={"cli-moduletopnav-tab " + (aba === a.k ? "active" : "")} onClick={() => setAba(a.k)}>
              {a.l}{a.n != null && <span className="cli-moduletopnav-n">{a.n}</span>}
            </button>
          ))}
        </nav>

        {aba === "reposicao" &&
          <div className="pb-tblwrap">
            <table className="pb-tbl">
              <thead><tr><th>Produto</th><th className="r">Estoque</th><th className="r">Alerta</th><th className="r">Vendido (30d)</th><th className="r">Dura</th><th className="r">Sugestão de compra</th><th className="r">Custo</th><th /></tr></thead>
              <tbody>
                {dados.reposicao.map((x) => (
                  <tr key={x.p.id}>
                    <td><b>{x.p.name}</b><small>{x.p.sku} · {x.p.cat}</small></td>
                    <td className="r"><span className={"pb-stock" + (x.p.stock <= x.p.alert ? " low" : "")}>{fmtQty(x.p.stock)} {x.p.unit}</span></td>
                    <td className="r">{fmtQty(x.p.alert)}</td>
                    <td className="r">{fmtQty(x.vendido)}</td>
                    <td className="r" style={{ color: x.dias <= 7 ? "var(--neg)" : x.dias <= 15 ? "var(--warn)" : undefined, fontWeight: 600 }}>{x.dias} dias</td>
                    <td className="r">{fmtQty(x.sugerido)} {x.p.unit}</td>
                    <td className="r">{fmtBRL(x.custo)}</td>
                    <td><button className={"os-btn sm" + (pedido.includes(x.p.id) ? " primary" : "")}
                      onClick={() => setPedido((s) => s.includes(x.p.id) ? s.filter((i) => i !== x.p.id) : [...s, x.p.id])}>
                      {pedido.includes(x.p.id) ? "No pedido" : "Pedir"}</button></td>
                  </tr>
                ))}
                {dados.reposicao.length > 0 &&
                  <tr><td colSpan={6}><b>Selecionados pro pedido</b></td>
                    <td className="r"><b>{fmtBRL(dados.reposicao.filter((x) => pedido.includes(x.p.id)).reduce((s, x) => s + x.custo, 0))}</b></td>
                    <td><button className="os-btn sm primary" disabled={!pedido.length}
                      onClick={() => { const itens = dados.reposicao.filter((x) => pedido.includes(x.p.id)); avisar(itens.length + " item(ns) enviados pro rascunho de compra.", "ok"); irPara("compras", { acao: "rascunho-reposicao", itens: itens.map((x) => ({ sku: x.p.sku, nome: x.p.name, qtd: x.sugerido, unit: x.p.unit, custo: x.dpp })) }); }}>
                      Gerar rascunho de compra</button></td></tr>}
                {dados.reposicao.length === 0 &&
                  <tr><td colSpan={8}><div className="pb-vazio"><b>Nada pra repor hoje</b><small>Nenhum produto chegou perto da quantidade de alerta. Volte quando o giro apertar.</small></div></td></tr>}
              </tbody>
            </table>
          </div>}

        {aba === "margem" &&
          <div className="pb-tblwrap">
            <table className="pb-tbl">
              <thead><tr><th>Produto</th><th className="r">Compra</th><th className="r">Venda</th><th className="r">Lucro un.</th><th className="r">Margem</th><th className="r">Vendido (30d)</th><th /></tr></thead>
              <tbody>
                {dados.margens.map((x) => (
                  <tr key={x.p.id}>
                    <td><b>{x.p.name}</b><small>{x.p.sku}</small></td>
                    <td className="r">{fmtBRL(x.dpp)}</td>
                    <td className="r">{fmtBRL(x.dsp)}</td>
                    <td className="r" style={{ color: x.lucro <= 0 ? "var(--neg)" : undefined }}>{fmtBRL(x.lucro)}</td>
                    <td className="r">
                      <span className={"pb-pill " + (x.margem < 15 ? "off" : x.margem < 25 ? "variable" : "combo")}>{x.margem.toFixed(1).replace(".", ",")}%</span>
                    </td>
                    <td className="r">{fmtQty(x.vendido)}</td>
                    <td><button className="os-btn sm" onClick={() => onIr("editar", x.p)}>Corrigir preço</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>}

        {aba === "abc" &&
          <div className="pb-widget-b">
            <p className="pb-help" style={{ marginBottom: 8 }}>Receita dos últimos 30 dias, do maior pro menor. <b>A</b> = os primeiros 80% da receita, <b>B</b> = até 95%, <b>C</b> = a cauda. Preço e reposição de classe A merecem atenção semanal; classe C, semestral.</p>
            {Chart
              ? <Chart type="bar" height={140} formatValue={(v) => fmtBRL(v)}
                  data={dados.abc.map((x) => ({ label: x.p.name + " · classe " + x.classe, value: Math.round(x.receita) }))} />
              : <div className="pb-abc">
                  {dados.abc.map((x) => (
                    <i key={x.p.id} className={x.classe.toLowerCase()} style={{ height: Math.max(2, (x.receita / maxRec) * 100) + "%" }} />
                  ))}
                </div>}
            <div className="pb-abc-leg">
              <span><em style={{ background: "var(--accent)" }} /> Classe A · {dados.abc.filter((x) => x.classe === "A").length} produtos</span>
              <span><em style={{ background: "oklch(from var(--accent) l calc(c * 0.55) h)" }} /> Classe B · {dados.abc.filter((x) => x.classe === "B").length}</span>
              <span><em style={{ background: "var(--border)" }} /> Classe C · {dados.abc.filter((x) => x.classe === "C").length}</span>
            </div>
            <table className="pb-tbl" style={{ marginTop: 14 }}>
              <thead><tr><th>#</th><th>Produto</th><th className="r">Receita (30d)</th><th className="r">% acumulado</th><th>Classe</th></tr></thead>
              <tbody>
                {dados.abc.map((x, i) => (
                  <tr key={x.p.id}>
                    <td className="m">{i + 1}</td>
                    <td><b>{x.p.name}</b><small>{x.p.sku}</small></td>
                    <td className="r">{fmtBRL(x.receita)}</td>
                    <td className="r">{(x.cum * 100).toFixed(1).replace(".", ",")}%</td>
                    <td><span className={"pb-pill " + (x.classe === "A" ? "single" : x.classe === "B" ? "variable" : "")}>{x.classe}</span></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>}

        {aba === "paradas" &&
          <div className="pb-tblwrap">
            <table className="pb-tbl">
              <thead><tr><th>Produto</th><th className="r">Vendido (30d)</th><th className="r">Estoque</th><th className="r">Dinheiro parado</th><th>Situação</th><th /></tr></thead>
              <tbody>
                {dados.paradas.map((x) => (
                  <tr key={x.p.id}>
                    <td><b>{x.p.name}</b><small>{x.p.sku} · {x.p.cat}</small></td>
                    <td className="r">{fmtQty(x.vendido)}</td>
                    <td className="r">{fmtQty(x.p.stock)} {x.p.unit}</td>
                    <td className="r">{fmtBRL(x.p.stock * x.dpp)}</td>
                    <td>{x.p.active ? <span className="pb-pill">Ativo</span> : <span className="pb-pill off">Inativo</span>}</td>
                    <td><button className="os-btn sm" onClick={() => avisar("Produto marcado pra revisão de preço ou desativação.", "warn")}>Revisar</button></td>
                  </tr>
                ))}
                {dados.paradas.length === 0 &&
                  <tr><td colSpan={6}><div className="pb-vazio"><b>Catálogo girando</b><small>Todo produto com estoque teve saída no período.</small></div></td></tr>}
              </tbody>
            </table>
          </div>}

        {aba === "duplicatas" &&
          <div className="pb-widget-b">
            {dados.duplicatas.length === 0
              ? <div className="pb-vazio"><b>Nenhuma duplicata aparente</b><small>Nenhum par de produtos com nome quase igual ou SKU repetido. Em catálogo migrado do Firebird isso costuma aparecer — vale rodar de novo depois de cada importação.</small></div>
              : dados.duplicatas.map((g, i) => (
                <div key={i} style={{ border: "1px solid var(--border)", borderRadius: 10, marginBottom: 10, overflowX: "auto" }}>
                  <table className="pb-tbl">
                    <thead><tr><th>Produto</th><th>SKU</th><th>Categoria</th><th className="r">Venda</th><th className="r">Estoque</th><th /></tr></thead>
                    <tbody>
                      {g.map((p) => (
                        <tr key={p.id}>
                          <td><b>{p.name}</b></td><td className="m">{p.sku}</td><td>{p.cat}</td>
                          <td className="r">{fmtBRL(p.variations[0].dsp)}</td>
                          <td className="r">{p.stockOn ? fmtQty(p.stock) : "—"}</td>
                          <td><button className="os-btn sm" onClick={() => onIr("editar", p)}>Abrir</button></td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ))}
          </div>}
      </Widget>
    </>
  );
}

window.ProdutoAnalises = ProdutoAnalises;
})();
