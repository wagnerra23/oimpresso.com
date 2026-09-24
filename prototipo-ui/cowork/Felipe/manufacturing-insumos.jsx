// manufacturing-insumos.jsx — impacto reverso: quais receitas usam cada insumo e o que
// acontece com custo/margem quando o preço de compra varia. window.MfgInsumosView.
//
// ADERÊNCIA AO DS (onda A): Drawer, StatusBadge e Button do bundle compilado. O drawer
// agora prende o foco e fecha no Esc por conta própria — antes o Esc desta tela não
// funcionava (o listener global vivia em manufacturing-page.jsx e não conhecia este estado).
// Continuam locais e declarados: a busca (B-02), o slider de simulação (C-04), a tabela (B-01).
(() => {
const { useState, useMemo } = React;
const I = window.I;
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};

function MfgInsumosView({ recipes, onAbrirReceita }) {
  const { Drawer, DrawerSection, Button, StatusBadge, EmptyState } = ds();
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
        {linhas.length > 0 && (
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
                <span className="r">{n
                  ? <StatusBadge tone={peso >= 50 ? "soft-danger" : peso >= 25 ? "soft-warning" : "soft-success"} label={num(peso, 0) + "% do custo"} />
                  : <span className="mfg-cat">sem receita</span>}</span>
              </div>
            ))}
          </div>
        )}
        {linhas.length === 0 && (
          <EmptyState variant="no-results" icon={<I.search size={18} />}
            title="Nenhum insumo com esse termo"
            description="Ajuste a busca por nome ou código do insumo."
            action={<Button size="sm" onClick={() => setQ("")}>Limpar busca</Button>} />
        )}
      </div>

      {sel && (
        <Drawer
          open
          onClose={() => setSku(null)}
          title={sel.n}
          subtitle={sel.sku + " · " + fmt(sel.c) + " / " + sel.u + " · estoque " + num(sel.est, 0) + " " + sel.u + " · usado em " + usos.length + " receita" + (usos.length === 1 ? "" : "s")}
          width={680}
          footer={<Button onClick={() => setSku(null)}>Fechar</Button>}>
          <DrawerSection title="Simular variação de preço">
            <div className="mfg-sim">
              <input type="range" min="-30" max="60" step="5" value={pct}
                aria-label="Variação do preço de compra, em porcento"
                aria-valuetext={(pct > 0 ? "+" : "") + pct + "%"}
                onChange={(e) => setPct(Number(e.target.value))} />
              <b className={pct > 0 ? "up" : pct < 0 ? "down" : ""}>{pct > 0 ? "+" : ""}{pct}%</b>
              <span>{fmt(sel.c)} → {fmt(sel.c * (1 + pct / 100))} / {sel.u}</span>
            </div>
          </DrawerSection>

          <DrawerSection title="Receitas afetadas">
            <div className="mfg-grp">
              <div className="mfg-ing mfg-ing5 mfg-ing-h"><span className="n">Receita</span><span className="m">Consumo</span><span className="m">Custo / un</span><span className="m">Com {pct > 0 ? "+" : ""}{pct}%</span><span className="m">Margem</span></div>
              {usos.map((u) => (
                <div className="mfg-ing mfg-ing5" key={u.r.id}>
                  <span className="n"><button className="mfg-link" onClick={() => onAbrirReceita(u.r.id)}>{u.r.name}</button><small>{u.r.sku} · {num(u.peso, 0)}% do custo</small></span>
                  <span className="m">{num(u.qtd, u.qtd < 1 ? 3 : 2)} {u.base}</span>
                  <span className="m">{fmt(u.unitAtual)}</span>
                  <span className="m tot">{fmt(u.unitNovo)}</span>
                  <span className="m"><StatusBadge tone={u.margemNova >= 55 ? "soft-success" : u.margemNova >= 45 ? "soft-warning" : "soft-danger"} label={num(u.margemNova, 0) + "%"} /></span>
                </div>
              ))}
            </div>
            <p className="mfg-note">A conta usa o consumo da receita já convertido para a unidade base. Uma nota lançada em <button className="mfg-link" onClick={() => window.__go && window.__go("compras")}>Compras</button> aplica a variação de verdade.</p>
          </DrawerSection>
        </Drawer>
      )}
    </>
  );
}

window.MfgInsumosView = MfgInsumosView;
})();
