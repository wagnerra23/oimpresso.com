// manufacturing-insumos.jsx — impacto reverso: quais receitas usam cada insumo e o que
// acontece com custo/margem quando o preço de compra varia. window.MfgInsumosView.
(() => {
const { useState, useMemo } = React;
const I = window.I;

function MfgInsumosView({ recipes, onAbrirReceita }) {
  const { INSUMOS, usosDoInsumo, fmt, num } = window.MFG;
  const [q, setQ] = useState("");
  const [sku, setSku] = useState(null);
  const [pct, setPct] = useState(10);

  const linhas = useMemo(() => INSUMOS.map((i) => {
    const usos = usosDoInsumo(i.sku, recipes, 0);
    return { i, n: usos.length, peso: usos.reduce((s, u) => Math.max(s, u.peso), 0) };
  }).filter(({ i }) => (i.n + " " + i.sku).toLowerCase().includes(q.trim().toLowerCase()))
    .sort((a, b) => b.n - a.n || b.peso - a.peso), [recipes, q]);

  const sel = sku ? window.MFG.bySku(sku) : null;
  const usos = sku ? usosDoInsumo(sku, recipes, pct) : [];

  return (
    <>
      <div className="mfg-bar">
        <div className="mfg-s">
          <I.search size={14} className="ic" />
          <input placeholder="Buscar insumo por nome ou SKU…" value={q} onChange={(e) => setQ(e.target.value)} />
        </div>
        <span className="mfg-crumb-meta">clique num insumo para ver quem sobe de custo quando o preço muda</span>
      </div>

      <div className="mfg-tablewrap">
        <div className="mfg-table ins">
          <div className="mfg-tr mfg-thead">
            <span className="mfg-th">Insumo</span><span className="mfg-th">Código</span>
            <span className="mfg-th r">Custo</span><span className="mfg-th r">Estoque</span>
            <span className="mfg-th r">Receitas</span><span className="mfg-th r">Maior peso</span>
          </div>
          {linhas.map(({ i, n, peso }) => (
            <div className={"mfg-tr" + (n ? " mfg-row" : "")} key={i.sku} onClick={() => n && setSku(i.sku)}>
              <span className="mfg-name"><b>{i.n}</b></span>
              <span className="mfg-sku">{i.sku}</span>
              <span className="mfg-num r">{fmt(i.c)}<span className="mfg-u">/ {i.u}</span></span>
              <span className="mfg-num dim r">{num(i.est, 0)}<span className="mfg-u">{i.u}</span></span>
              <span className="mfg-num r">{n || "—"}</span>
              <span className="r">{n ? <span className={"mfg-pill " + (peso >= 50 ? "bad" : peso >= 25 ? "warn" : "ok")}>{num(peso, 0)}% do custo</span> : <span className="mfg-cat">sem receita</span>}</span>
            </div>
          ))}
        </div>
      </div>

      {sel && (
        <>
          <div className="mfg-scrim" onClick={() => setSku(null)} />
          <aside className="mfg-drw" role="dialog" aria-label={"Impacto de " + sel.n}>
            <div className="mfg-drw-h">
              <div>
                <h2>{sel.n}</h2>
                <p>{sel.sku} · {fmt(sel.c)} / {sel.u} · estoque {num(sel.est, 0)} {sel.u} · usado em {usos.length} receita{usos.length === 1 ? "" : "s"}</p>
              </div>
              <button className="mfg-x" onClick={() => setSku(null)} aria-label="Fechar">✕</button>
            </div>
            <div className="mfg-drw-b">
              <div className="mfg-sec"><span>Simular variação de preço</span><span className="ln" /></div>
              <div className="mfg-sim">
                <input type="range" min="-30" max="60" step="5" value={pct} onChange={(e) => setPct(Number(e.target.value))} />
                <b className={pct > 0 ? "up" : pct < 0 ? "down" : ""}>{pct > 0 ? "+" : ""}{pct}%</b>
                <span>{fmt(sel.c)} → {fmt(sel.c * (1 + pct / 100))} / {sel.u}</span>
              </div>

              <div className="mfg-sec"><span>Receitas afetadas</span><span className="ln" /></div>
              <div className="mfg-grp">
                <div className="mfg-ing mfg-ing5 mfg-ing-h"><span className="n">Receita</span><span className="m">Consumo</span><span className="m">Custo / un</span><span className="m">Com {pct > 0 ? "+" : ""}{pct}%</span><span className="m">Margem</span></div>
                {usos.map((u) => (
                  <div className="mfg-ing mfg-ing5" key={u.r.id}>
                    <span className="n"><button className="mfg-link" onClick={() => onAbrirReceita(u.r.id)}>{u.r.name}</button><small>{u.r.sku} · {num(u.peso, 0)}% do custo</small></span>
                    <span className="m">{num(u.qtd, u.qtd < 1 ? 3 : 2)} {u.base}</span>
                    <span className="m">{fmt(u.unitAtual)}</span>
                    <span className="m tot">{fmt(u.unitNovo)}</span>
                    <span className="m"><span className={"mfg-pill " + (u.margemNova >= 55 ? "ok" : u.margemNova >= 45 ? "warn" : "bad")}>{num(u.margemNova, 0)}%</span></span>
                  </div>
                ))}
              </div>
              <p className="mfg-note">A conta usa o consumo da receita já convertido para a unidade base. Uma nota lançada em <button className="mfg-link" onClick={() => window.__go && window.__go("compras")}>Compras</button> aplica a variação de verdade.</p>
            </div>
            <div className="mfg-drw-f">
              <button className="os-btn ghost" onClick={() => setSku(null)}>Fechar</button>
            </div>
          </aside>
        </>
      )}
    </>
  );
}

window.MfgInsumosView = MfgInsumosView;
})();
