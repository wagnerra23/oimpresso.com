// catchup-shared.jsx — pele comum das 12 telas do catch-up do legado (Compras,
// Financeiro operacional, WooCommerce, Restaurante). Ondas de refino 1-3:
//   · KPIs e grades derivam do documento (nenhum array paralelo de números)
//   · drawer de detalhe PT-02 com ação que MUDA estado (não só toast)
//   · teclado da Larissa em 1280px: j/k andar · ↵ abrir · / buscar · d densidade
// Expõe window.CatchupUI.
(() => {
const { useEffect } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };
const brl = (n) => (Number(n) < 0 ? "− " : "") + "R$ " + Math.abs(Number(n) || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const num = (n) => Number(n || 0).toLocaleString("pt-BR");

function Grade({ columns, rows, densa, altura = 320, onRowClick, vazio }) {
  const { DataTablePro, EmptyState } = DS();
  if (!rows.length) return <div style={{ padding: 24 }}>{EmptyState ? <EmptyState variant="no-results" icon={<Ic name="search" size={18} />} title={(vazio && vazio.t) || "Nada com esses filtros"} description={(vazio && vazio.d) || "Limpe um filtro ou amplie o período."} /> : null}</div>;
  if (!DataTablePro) return <p className="pb-help" style={{ padding: 16 }}>A grade do DS não carregou.</p>;
  return <div className="pb-grid-pro cu-grid"><DataTablePro columns={columns} rows={rows} height={altura} density={densa ? "compact" : "comfortable"} onRowClick={onRowClick} /></div>;
}

function Toolbar({ busca, setBusca, ph, densa, setDensa, buscaRef, children }) {
  return (
    <div className="pb-toolbar">
      <div className="pb-busca"><Ic name="search" size={12} /><input ref={buscaRef} value={busca} onChange={(e) => setBusca(e.target.value)} placeholder={ph} /></div>
      <span className="cu-kbd" aria-hidden="true"><b>j</b><b>k</b> andar<i>·</i><b>↵</b> abrir<i>·</i><b>/</b> buscar<i>·</i><b>d</b> densidade</span>
      <div className="sp" />
      <div className="pb-seg" role="group" aria-label="Densidade">
        <button className={densa ? "" : "on"} onClick={() => setDensa(false)}>Confortável</button>
        <button className={densa ? "on" : ""} onClick={() => setDensa(true)}>Compacto</button>
      </div>
      {children}
    </div>
  );
}

// Atalhos de grade densa. Enter/espaço para abrir já é do DataTablePro (linha focável).
function useNav(ref, buscaRef, setDensa) {
  useEffect(() => {
    const onKey = (e) => {
      const a = e.target;
      const digitando = a && (a.tagName === "INPUT" || a.tagName === "TEXTAREA" || a.isContentEditable);
      if (e.key === "/" && !digitando) { e.preventDefault(); if (buscaRef && buscaRef.current) buscaRef.current.focus(); return; }
      if (digitando || e.metaKey || e.ctrlKey || e.altKey) return;
      if (e.key === "d" && setDensa) { setDensa((v) => !v); return; }
      if (e.key !== "j" && e.key !== "k") return;
      const box = ref && ref.current; if (!box) return;
      const trs = Array.prototype.slice.call(box.querySelectorAll("tbody tr[tabindex]"));
      if (!trs.length) return;
      const i = trs.indexOf(document.activeElement);
      const prox = e.key === "j" ? Math.min(trs.length - 1, i + 1) : Math.max(0, (i < 0 ? 1 : i) - 1);
      e.preventDefault(); trs[prox].focus();
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [ref, buscaRef, setDensa]);
}

function Kpis({ itens }) {
  return <div className="cu-kpis">{itens.filter(Boolean).map((k, i) => (
    <div className={"cu-kpi" + (k.tom ? " " + k.tom : "")} key={i}>
      <small>{k.l}</small><b>{k.v}</b><span>{k.n}</span>
    </div>
  ))}</div>;
}

function Painel({ aberto, onClose, titulo, sub, badge, largura = 540, secoes = [], acoes }) {
  const { Drawer, DrawerSection } = DS();
  if (!Drawer || !aberto) return null;
  return (
    <Drawer open={!!aberto} onClose={onClose} title={titulo} subtitle={sub} badge={badge} width={largura} footer={acoes}>
      {secoes.filter(Boolean).map((s, i) => <DrawerSection key={i} title={s.t}>{s.c}</DrawerSection>)}
    </Drawer>
  );
}

function Def({ pares }) {
  return <dl className="cu-def">{pares.filter(Boolean).map(([k, v], i) => <div key={i}><dt>{k}</dt><dd>{v}</dd></div>)}</dl>;
}

function Itens({ linhas, total }) {
  const soma = linhas.reduce((a, l) => a + l.sub, 0);
  return (
    <table className="cu-itens">
      <thead><tr><th>Item</th><th className="n">Qtd</th><th className="n">Unitário</th><th className="n">Subtotal</th></tr></thead>
      <tbody>{linhas.map((l, i) => <tr key={i}><td>{l.nome}</td><td className="n mono">{num(l.qtd)}</td><td className="n mono">{brl(l.preco)}</td><td className="n mono">{brl(l.sub)}</td></tr>)}</tbody>
      <tfoot><tr><td colSpan="3">Total</td><td className="n mono">{brl(total == null ? soma : total)}</td></tr></tfoot>
    </table>
  );
}

// Linhas plausíveis a partir do documento: quantidade inteira, preço perto do catálogo e
// Σ subtotal == total do documento (a última linha absorve o arredondamento).
const CAT = [
  ["Lona 380g impressa (m²)", 55], ["Vinil de recorte (m²)", 39.5], ["Cartão de visita 1.000un", 149.8],
  ["Banner 440g com bastão", 75], ["Adesivo perfurado (m²)", 62], ["Papel couché A3 (pacote)", 44],
  ["Tinta solvente (galão)", 460], ["Ilhós metálico (cento)", 28], ["Lâmina de corte (un)", 96],
];
const hash = (s) => { let h = 7; s = String(s); for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) % 9973; return h; };
function itensDe(seed, n, total) {
  const T = Number(total) || 0, h = hash(seed), k = Math.max(1, Math.min(9, Number(n) || 1));
  let out = [];
  for (let i = 0; i < k; i++) {
    const c = CAT[(h + i * 3) % CAT.length];
    const q = Math.max(1, Math.round((T / k) / c[1]));
    out.push({ nome: c[0], qtd: q, preco: c[1], sub: q * c[1] });
  }
  const soma = out.reduce((a, l) => a + l.sub, 0);
  const f = soma ? T / soma : 1;
  out = out.map((l) => { const p = +(l.preco * f).toFixed(2); return { nome: l.nome, qtd: l.qtd, preco: p, sub: +(l.qtd * p).toFixed(2) }; });
  const dif = +(T - out.reduce((a, l) => a + l.sub, 0)).toFixed(2);
  if (out.length && dif) { const L = out[out.length - 1]; L.sub = +(L.sub + dif).toFixed(2); L.preco = +(L.sub / L.qtd).toFixed(2); }
  return out;
}

// Datas do protótipo: hoje é 22/08/2026 (mesma âncora das outras telas).
const HOJE = new Date(2026, 7, 22);
const dmy = (s) => { const p = String(s).split("/"); return new Date(+p[2], +p[1] - 1, +p[0]); };
const diasAte = (s) => Math.round((dmy(s) - HOJE) / 86400000);

window.CatchupUI = { Grade, Toolbar, Kpis, Painel, Def, Itens, useNav, brl, num, Ic, itensDe, diasAte, HOJE };
})();
