// manufacturing-print.jsx — ficha técnica de produção (folha de prova PT-07).
// Aceita várias receitas (impressão em lote) e a variante "chão de fábrica" (sem custo,
// pra não circular preço de compra na produção). Portal no <body> + @media print.
//
// ADERÊNCIA AO DS (onda A-23): os quatro primitivos print-craft do bundle compilado —
// RegistrationMark (mira), ProofFrame (marcas de corte), Dimension (cota) e ProofStrip
// (tira CMYK + tira de densidade). Saíram daqui as 4 tintas de processo em hex
// (#00AEEF #EC008C #FFF200 #231F20) e a escada de cinza calculada em runtime.
//
// IMPRESSÃO PELO DS (24/09/2026, decisão da Maiara: "siga o DS"): as folhas abrem no
// PresenterMode (palco + folha A4 real + zoom + ← → + P imprime + Esc sai, @media print
// embutido — PresenterMode.jsx L1-35, fonte viva). Saíram daqui o window.print() automático
// após 120 ms, o afterprint e o bloco "body>* display:none" do CSS da tela. O portal no <body>
// fica: no print o DS esconde por visibility, e um ancestral oculto ainda ocuparia espaço.
//
// CONTORNO DECLARADO: os quatro pintam por token de tela e o cockpit não publica paleta
// de impressão (ADR 0413, C-08). ProofFrame entra com grid={false} — a grade de prova é
// ruído sobre papel. Esta folha precisa ser IMPRESSA e conferida antes de fechar a onda:
// se o token não sobreviver ao @media print, o resultado é a medição, não um ajuste aqui.
// MEDIDO 25/09/2026 (Felipe): com o cockpit em tema escuro os tokens NÃO sobrevivem — a folha saía
// cinza-escuro. Ajuste feito no CSS (.mfg-sheet redefine os tokens com os valores do tema claro,
// citados de colors_and_type.css) — ver o contorno em manufacturing-page.css e a pauta.
(() => {
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};

function Folha({ r, c, semCusto, hoje }) {
  const { RegistrationMark, ProofFrame, Dimension, ProofStrip } = ds();
  const { bySku, multDe, fmt, num } = window.MFG;
  const nIng = r.grupos.reduce((s, g) => s + g.itens.length, 0);
  const extraLabel = r.custoTipo === "percentual" ? r.extra + "% sobre ingredientes"
    : r.custoTipo === "unidade" ? fmt(r.extra) + " por " + r.un + " produzido" : "valor fixo";

  return (
    <article className="mfg-sheet">
      <ProofFrame cropMarks grid={false} padding={0} radius={0}>

      <header className="mfg-sheet-h">
        <span className="mfg-reg-host"><RegistrationMark size={26} strokeWidth={0.7} /></span>
        <div className="id">
          <span className="eyebrow">Office Impresso · Fabricação{semCusto ? " · via de produção" : ""}</span>
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
        <div className="cota"><span className="l">Lote da receita</span><Dimension value={num(r.qtd, 2) + " " + r.un} /></div>
        <div className="cota"><span className="l">Rendimento líquido</span><Dimension value={num(c.qtdLiq, 2) + " " + r.un} /><small>desperdício {num(r.waste, 0)}%</small></div>
        {r.subUn && <div className="cota"><span className="l">Em sub-unidade</span><Dimension value={num(c.qtdLiq * r.subFator, 2) + " " + r.subUn} /></div>}
        {!semCusto && <div className="cota hi"><span className="l">Custo por {r.un}</span><Dimension value={fmt(c.unit)} /><small>venda {fmt(r.venda)} · margem {num(c.margem, 1)}%</small></div>}
        {semCusto && <div className="cota hi"><span className="l">Conferir antes de iniciar</span><Dimension value={nIng + " itens"} /><small>separar tudo na bancada</small></div>}
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
        <ProofStrip kind="cmyk" height={9} swatch={13} />
        <ProofStrip kind="density" steps={6} height={9} swatch={13} />
        <span>{r.sku} · {semCusto ? "via de produção" : "custo do preço de compra atual"} · atualizado {r.atualizado}</span>
      </footer>

      </ProofFrame>
    </article>
  );
}

function MfgFichaPrint({ itens, semCusto, onDone }) {
  const { PresenterMode } = ds();
  const hoje = new Date().toLocaleString("pt-BR", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });
  if (!PresenterMode) return null;
  const n = itens.length;
  return ReactDOM.createPortal(
    <PresenterMode open onClose={onDone} pages={n} paper="A4" orientation="portrait"
      title={semCusto ? "Via de produção" : "Ficha técnica com custo"}
      subtitle={n + (n > 1 ? " receitas" : " receita") + (semCusto ? " · sem valores de compra" : "")}>
      {(i) => <Folha r={itens[i].r} c={itens[i].c} semCusto={semCusto} hoje={hoje} />}
    </PresenterMode>, document.body);
}

window.MfgFichaPrint = MfgFichaPrint;
})();
