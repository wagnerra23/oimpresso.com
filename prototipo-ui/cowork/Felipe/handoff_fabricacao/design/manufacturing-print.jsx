// manufacturing-print.jsx — ficha técnica de produção (folha de prova PT-07).
// Aceita várias receitas (impressão em lote) e a variante "chão de fábrica" (sem custo,
// pra não circular preço de compra na produção). Portal no <body> + @media print.
(() => {
const { useEffect } = React;

const REG = (
  <svg className="mfg-reg" viewBox="0 0 24 24" aria-hidden="true">
    <circle cx="12" cy="12" r="6.4" fill="none" stroke="currentColor" strokeWidth=".7" />
    <circle cx="12" cy="12" r="2.2" fill="none" stroke="currentColor" strokeWidth=".7" />
    <path d="M12 .8v6.6M12 16.6v6.6M.8 12h6.6M16.6 12h6.6" stroke="currentColor" strokeWidth=".7" />
  </svg>
);

function Folha({ r, c, semCusto, hoje }) {
  const { bySku, multDe, fmt, num } = window.MFG;
  const nIng = r.grupos.reduce((s, g) => s + g.itens.length, 0);
  const extraLabel = r.custoTipo === "percentual" ? r.extra + "% sobre ingredientes"
    : r.custoTipo === "unidade" ? fmt(r.extra) + " por " + r.un + " produzido" : "valor fixo";

  return (
    <article className="mfg-sheet">
      <i className="cm tl" /><i className="cm tr" /><i className="cm bl" /><i className="cm br" />

      <header className="mfg-sheet-h">
        {REG}
        <div className="id">
          <span className="eyebrow">Office Impresso · Manufacturing{semCusto ? " · via de produção" : ""}</span>
          <h1>{r.name}</h1>
          <p>{r.cat} / {r.sub} · {nIng} ingredientes em {r.grupos.length} grupos</p>
        </div>
        <dl className="stamp">
          <div><dt>Receita</dt><dd>{r.sku}</dd></div>
          <div><dt>Produto</dt><dd>{r.produto || "—"}</dd></div>
          <div><dt>Emitida em</dt><dd>{hoje}</dd></div>
        </dl>
      </header>

      <section className="mfg-sheet-cotas">
        <div className="cota"><span className="l">Lote da receita</span><b>{num(r.qtd, 2)} {r.un}</b><i className="ln" /></div>
        <div className="cota"><span className="l">Rendimento líquido</span><b>{num(c.qtdLiq, 2)} {r.un}</b><i className="ln" /><small>desperdício {num(r.waste, 0)}%</small></div>
        {r.subUn && <div className="cota"><span className="l">Em sub-unidade</span><b>{num(c.qtdLiq * r.subFator, 2)} {r.subUn}</b><i className="ln" /></div>}
        {!semCusto && <div className="cota hi"><span className="l">Custo por {r.un}</span><b>{fmt(c.unit)}</b><i className="ln" /><small>venda {fmt(r.venda)} · margem {num(c.margem, 1)}%</small></div>}
        {semCusto && <div className="cota hi"><span className="l">Conferir antes de iniciar</span><b>{nIng} itens</b><i className="ln" /><small>separar tudo na bancada</small></div>}
      </section>

      <table className="mfg-sheet-t">
        <thead>
          <tr>
            <th>Ingrediente</th><th>Código</th><th className="r">Consumo</th>
            {semCusto ? <th className="r sep">Separado</th> : <><th className="r">Custo unit.</th><th className="r">Subtotal</th></>}
          </tr>
        </thead>
        {r.grupos.map((g, gi) => {
          const sub = g.itens.reduce((a, i) => { const p = bySku(i.sku); return a + i.q * (p ? p.c : 0) * multDe(i); }, 0);
          return (
            <tbody key={g.g + gi}>
              <tr className="grp"><th colSpan="3">{g.g}</th><th className="r mono" colSpan={semCusto ? 1 : 2}>{semCusto ? g.itens.length + " itens" : fmt(sub)}</th></tr>
              {g.itens.map((i, ii) => {
                const p = bySku(i.sku) || { n: i.sku, u: "", c: 0 };
                const m = multDe(i);
                return (
                  <tr key={i.sku + ii}>
                    <td>{p.n}</td>
                    <td className="mono dim">{i.sku}</td>
                    <td className="r mono">{num(i.q, i.q < 1 ? 3 : 2)} {i.subUn || p.u}{m > 1 && <small className="eq"> = {num(i.q * m, 2)} {p.u}</small>}</td>
                    {semCusto ? <td className="r"><i className="box" /></td> : <>
                      <td className="r mono dim">{fmt(p.c)}{m > 1 ? " / " + p.u : ""}</td>
                      <td className="r mono">{fmt(i.q * p.c * m)}</td>
                    </>}
                  </tr>
                );
              })}
            </tbody>
          );
        })}
      </table>

      <section className="mfg-sheet-close">
        <div className="assina">
          <span className="eyebrow">Conferência</span>
          <div className="ass"><i /><span>Produção · nome e data</span></div>
          <div className="ass"><i /><span>Conferido por · nome e data</span></div>
          <p className="obs">Ficha de uso interno — não é documento fiscal.{semCusto ? " Via de produção: sem valores de compra." : " Reimprimir após entrada de nota de insumo."}</p>
        </div>
        {!semCusto && (
          <table className="mfg-sheet-t tot">
            <tbody>
              <tr><td>Ingredientes ({nIng})</td><td className="r mono">{fmt(c.ing)}</td></tr>
              <tr><td>Custo de produção · {extraLabel}</td><td className="r mono">{fmt(c.extra)}</td></tr>
              <tr><td>Total do lote</td><td className="r mono">{fmt(c.total)}</td></tr>
              <tr className="big"><td>Custo por {r.un}</td><td className="r mono">{fmt(c.unit)}</td></tr>
            </tbody>
          </table>
        )}
      </section>

      <footer className="mfg-sheet-f">
        <div className="strip">{["#00AEEF", "#EC008C", "#FFF200", "#231F20"].map((h) => <i key={h} style={{ background: h }} />)}</div>
        <div className="strip d">{[0, 20, 40, 60, 80, 100].map((k) => <i key={k} style={{ background: "rgb(" + (255 - k * 2.55) + "," + (255 - k * 2.55) + "," + (255 - k * 2.55) + ")" }} />)}</div>
        <span>{r.sku} · {semCusto ? "via de produção" : "custo do preço de compra atual"} · atualizado {r.atualizado}</span>
      </footer>
    </article>
  );
}

function MfgFichaPrint({ itens, semCusto, onDone }) {
  useEffect(() => {
    const fim = () => onDone && onDone();
    window.addEventListener("afterprint", fim);
    const t = setTimeout(() => window.print(), 120);
    return () => { window.removeEventListener("afterprint", fim); clearTimeout(t); };
  }, []);
  const hoje = new Date().toLocaleString("pt-BR", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });
  return ReactDOM.createPortal(
    <div className="mfg-print-host">
      {itens.map(({ r, c }) => <Folha key={r.id} r={r} c={c} semCusto={semCusto} hoje={hoje} />)}
    </div>, document.body);
}

window.MfgFichaPrint = MfgFichaPrint;
})();
