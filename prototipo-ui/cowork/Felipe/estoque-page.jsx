// estoque-page.jsx — módulo Estoque (movimentações) no shell Cockpit V2.
// Reconciliado com o vivo lido no main 2026-08-22 (StockAdjustment/StockTransfer Index+Create,
// Estoque/Movimentacao.casos.md). Áreas: Painel · Ajustes · Transferências · Vencimentos · Contagem.
// Domínio em estoque-data.jsx · formulários/drawers/folha em estoque-forms.jsx · contagem em estoque-contagem.jsx.
// IIFE — expõe window.EstoquePage.
(() => {
const { useState, useMemo, useEffect, useRef } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const E = () => window.EstData;
const FM = () => window.EstForms || {};
const CT = () => window.EstContagem || {};
const irRota = (r) => { if (typeof window.__go === "function") window.__go(r); };
const pct = (v, t) => t ? Math.round(v / t * 100) : 0;
const alturaGrid = (n, dense) => 56 + Math.max(1, n) * (dense ? 50 : 58);
const dt = (s) => new Date(s + "T00:00");
// Presets ancorados na data do mock (08/05/2026) — janela rolante sobre "hoje" real esvaziaria a lista.
const PRESETS_EST = [
  { id: "dia", label: "Dia", range: () => ({ from: dt("2026-05-08"), to: dt("2026-05-08") }) },
  { id: "semana", label: "Semana", range: () => ({ from: dt("2026-05-02"), to: dt("2026-05-08") }) },
  { id: "mes", label: "Mês", range: () => ({ from: dt("2026-05-01"), to: dt("2026-05-31") }) },
];
const casa = (txt, q) => !q || String(txt || "").toLowerCase().indexOf(q.toLowerCase()) >= 0;

// ══════════════════════════ PAINEL ══════════════════════════
function painelData(papel, base) {
  const D = E();
  const ajustes = base.ajustes.filter((a) => D.ajusteVisivel(papel, a));
  const transf = base.transf.filter((t) => D.transfVisivel(papel, t));
  const contagens = base.contagens.filter((c) => D.contagemVisivel(papel, c));
  const perda = ajustes.reduce((s, a) => s + D.totalItens(a.itens) - a.recuperado, 0);
  const anormais = ajustes.filter((a) => a.tipo === "abnormal");
  const perdaAnormal = anormais.reduce((s, a) => s + D.totalItens(a.itens) - a.recuperado, 0);
  const emAberto = transf.filter((t) => !D.STATUS_TRF[t.status].move);
  const valorAberto = emAberto.reduce((s, t) => s + D.totalItens(t.itens) + t.frete, 0);
  const venc = D.vencimentos().filter((v) => D.podeVerLocal(papel, v.local));
  const vencidos = venc.filter((v) => v.estado === "vencido");
  const valorVencido = vencidos.reduce((s, v) => s + v.qtd * v.custo, 0);
  const ctAbertas = contagens.filter((c) => c.status !== "fechada");
  const divAbertas = ctAbertas.reduce((s, c) => s + D.divergencias(c).length, 0);

  // INV-4 — quanto do saldo está reservado (não é baixa, mas também não é vendável).
  const reservado = D.PRODUTOS.reduce((s, p) => s + D.locaisDe(papel).reduce((x, k) => x + D.reservado(p, k) * p.custo, 0), 0);

  const porProduto = D.PRODUTOS.map((p) => ({
    label: p.sku, nome: p.nome,
    v: ajustes.reduce((s, a) => { const i = a.itens.find((x) => x.sku === p.sku); return s + (i ? i.qtd * p.custo : 0); }, 0),
  })).filter((x) => x.v > 0).sort((a, b) => b.v - a.v);
  const totalAj = porProduto.reduce((s, x) => s + x.v, 0);

  return {
    perda, valorAberto, valorVencido, reservado, nAjustes: ajustes.length, nTransf: transf.length,
    kpis: [
      { label: "Perda do mês", value: D.fmt(perda), icon: "receipt", sub: ajustes.length + " ajustes · " + D.fmt(perdaAnormal) + " anormal", emphasize: true },
      { label: "Em rota", value: String(emAberto.length), icon: "truck", sub: D.fmt(valorAberto) + " sem mover saldo" },
      { label: "Reservado", value: D.fmt(reservado), icon: "clock", sub: "no estoque, mas já prometido" },
      { label: "Lotes vencidos", value: String(vencidos.length), icon: "chart", sub: vencidos.length ? D.fmt(valorVencido) + " pra baixar" : "nenhum" },
    ],
    analises: [
      { id: "onde", kind: "bars", icon: "chart", title: "Onde a perda se concentra", sub: "valor ajustado por produto · mai/26",
        pill: { tone: porProduto.length && pct(porProduto[0].v, totalAj) > 40 ? "warn" : "ok", label: porProduto.length ? pct(porProduto[0].v, totalAj) + "% num só item" : "—" },
        bars: porProduto.map((x) => ({ label: x.label, bar: pct(x.v, totalAj), pct: D.fmt(x.v) })),
        footer: porProduto.length ? porProduto[0].nome + " responde pela maior fatia — vale olhar manuseio e corte antes de comprar mais." : "",
        origem: ["Quantidade ajustada × custo do produto, somada por SKU nos ajustes que o seu papel enxerga.", "Valor recuperado não é abatido aqui — ele aparece no total da perda."] },
      { id: "tipo", kind: "buckets", icon: "list", title: "Normal x anormal", sub: "a régua fiscal do ajuste",
        buckets: Object.keys(D.TIPOS).map((k) => {
          const nas = ajustes.filter((a) => a.tipo === k);
          const v = nas.reduce((s, a) => s + D.totalItens(a.itens) - a.recuperado, 0);
          return { label: D.TIPOS[k], bar: pct(nas.length, ajustes.length), val: nas.length + " · " + D.fmt(v), color: k === "abnormal" ? "var(--warn)" : "var(--accent)" };
        }),
        footer: "Anormal precisa justificativa: perda evitável, sinistro, vencimento. Normal é o refile esperado da produção.",
        origem: ["Campo `adjustment_type` (normal/abnormal) — R-ADJ-002 do charter da tela viva."] },
      { id: "saldo", kind: "buckets", icon: "database", title: "Físico, reservado e livre", sub: "INV-4 — reserva não é baixa",
        buckets: D.locaisDe(papel).map((k) => {
          const fis = D.PRODUTOS.reduce((s, p) => s + D.saldo(p, k) * p.custo, 0);
          const res = D.PRODUTOS.reduce((s, p) => s + D.reservado(p, k) * p.custo, 0);
          return { label: D.LOCAIS[k].l, bar: pct(res, fis), val: D.fmt(res) + " de " + D.fmt(fis), color: pct(res, fis) > 25 ? "var(--warn)" : "var(--accent)" };
        }),
        footer: "A barra é a fatia reservada em venda ou OS aberta. Ajuste e transferência só podem consumir o que está livre.",
        origem: ["Saldo físico × custo por local, e a parcela reservada em documento aberto (INV-4).", "O vivo ainda não mostra reserva em tela — é gap de produto, não de dado."] },
      { id: "venc", kind: "list", icon: "clock", title: "Validade vencendo", sub: "lotes com 30 dias ou menos",
        list: venc.map((v) => ({ left: v.lote + " · " + v.sku + " · " + D.LOCAIS[v.local].l, right: (v.dias < 0 ? "vencido há " + (-v.dias) : "em " + v.dias) + " dias" })),
        footer: <span className="mp-total">valor vencido <b>{D.fmt(valorVencido)}</b></span>,
        footnote: "Lote vencido continua somando saldo até alguém lançar o ajuste anormal.",
        origem: ["Lotes com `exp_date` até 30 dias — a régua do stock_expiry_report."] },
    ],
    acoes: [
      ...(ctAbertas.length ? [{ id: "ct", tone: "peach", icon: "list", title: ctAbertas.length === 1 ? "Uma contagem aberta com " + divAbertas + " divergências" : ctAbertas.length + " contagens abertas",
        sub: "Contagem aberta é foto que envelhece — fechar gera o ajuste e conserta o saldo.",
        cta: { label: "Ver contagem" }, ir: { aba: "contagem" } }] : []),
      ...(vencidos.length ? [{ id: "vencido", tone: "grey", icon: "clock", title: vencidos.length + " lotes vencidos ainda somando saldo",
        sub: D.fmt(valorVencido) + " que o sistema conta e a prateleira não tem.",
        cta: { label: "Ver vencimentos", tone: "ghost" }, ir: { aba: "vencimentos" } }] : []),
      { id: "concluir", tone: "violet", icon: "truck", title: emAberto.length + " transferências sem mover saldo",
        sub: D.fmt(valorAberto) + " em rota. Só o status terminal libera a venda no destino (R-XFER-005).",
        cta: { label: "Ver em rota", tone: "ghost" }, ir: { aba: "transferencias", filtro: "in_transit" } },
    ],
  };
}

function Painel({ papel, ajustes, transf, contagens, onIr }) {
  const D = E();
  const MP = window.ModuloPadrao;
  const [drill, setDrill] = useState(null);
  const d = useMemo(() => painelData(papel, { ajustes, transf, contagens }), [papel, ajustes, transf, contagens]);
  if (!MP) return null;
  return (
    <>
      <MP.Resumo quando="08/05, 09:42"
        linhas={[
          <>{d.nAjustes} ajustes e {d.nTransf} transferências no seu escopo. A perda acumulada é <b>{D.fmt(d.perda)}</b>, boa parte de ajuste anormal.</>,
          <>Fora do lugar: <b>{D.fmt(d.valorAberto)}</b> em rota, <b>{D.fmt(d.valorVencido)}</b> em lote vencido e <b>{D.fmt(d.reservado)}</b> reservado em documento aberto — nada disso é vendável hoje.</>,
        ]}
        destaque={<>Comece pela contagem aberta: ela é a única que te diz onde o sistema e a prateleira discordam.</>}
        chips={[
          { label: "Contagem", icon: "list", tone: "warn", ir: { aba: "contagem" } },
          { label: "Lotes vencidos", icon: "clock", ir: { aba: "vencimentos" } },
          { label: "Em rota", icon: "truck", ir: { aba: "transferencias", filtro: "in_transit" } },
          { label: "Novo ajuste", icon: "plus", ir: { aba: "ajuste-novo" } },
        ]}
        onChip={(c) => onIr(c.ir)} />
      <MP.Kpis kpis={d.kpis} />
      <MP.Secao titulo="ANÁLISES DO MÓDULO" sub="clique num card pra ver de onde vem o número" />
      <MP.Analises analises={d.analises} onDrill={setDrill} />
      <MP.Secao titulo="O QUE FAZER PRIMEIRO" icon="bulb" />
      <MP.Acoes acoes={d.acoes} onCta={(a) => onIr(a.ir)} />
      <MP.Drill item={drill} onClose={() => setDrill(null)} />
    </>
  );
}

// ══════════════════════════ TOOLBAR ══════════════════════════
function Toolbar({ colunas, cols, setCols, onExport, dense, setDense, info, acoes, filtros, busca, setBusca, periodo, setPeriodo }) {
  const { Button, FilterChip, PeriodBar } = DS();
  const [aberto, setAberto] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    if (!aberto) return;
    const fora = (e) => { if (ref.current && !ref.current.contains(e.target)) setAberto(false); };
    document.addEventListener("mousedown", fora);
    return () => document.removeEventListener("mousedown", fora);
  }, [aberto]);
  return (
    <>
      {PeriodBar && periodo &&
        <div className="est-periodo">
          <PeriodBar label="Período" value={periodo} onChange={setPeriodo} presets={PRESETS_EST} />
        </div>}
      <div className="est-toolbar">
        {setBusca &&
          <label className="est-busca-inline">
            <span aria-hidden="true">⌕</span>
            <input value={busca} placeholder="Buscar ref, local, motivo..." aria-label="Buscar na lista"
              onChange={(e) => setBusca(e.target.value)} />
          </label>}
        <span className="est-tb-info">{info}</span>
        <div className="est-sp" />
        {Button && <Button variant="ghost" size="sm" onClick={() => setDense(!dense)}>{dense ? "Confortável" : "Compacto"}</Button>}
        {Button && <Button variant="ghost" size="sm" onClick={() => onExport("csv")}>CSV</Button>}
        {Button && <Button variant="ghost" size="sm" onClick={() => onExport("excel")}>Excel</Button>}
        {Button && <Button variant="ghost" size="sm" onClick={() => onExport("print")}>Imprimir</Button>}
        <div className="est-colsel" ref={ref}>
          {Button && <Button variant="ghost" size="sm" onClick={() => setAberto((v) => !v)}>Colunas</Button>}
          {aberto &&
            <div className="est-colsel-menu">
              {colunas.map((c) =>
                <label key={c.key} className={"est-colsel-item" + (c.req ? " req" : "")}>
                  <input type="checkbox" checked={!!cols[c.key]} disabled={c.req}
                    onChange={() => !c.req && setCols((o) => ({ ...o, [c.key]: !o[c.key] }))} />
                  {c.label}
                </label>)}
            </div>}
        </div>
        {acoes}
      </div>
      {filtros && filtros.length > 0 &&
        <div className="est-filtros">
          <span className="est-filtros-l">filtros</span>
          {filtros.map((f) => FilterChip
            ? <FilterChip key={f.id} label={f.label} value={f.value} onRemove={f.onRemove} />
            : <span key={f.id} className="est-chip">{f.label}: {f.value}</span>)}
        </div>}
    </>
  );
}

function usarCols(chave, colunas) {
  const inicial = colunas.reduce((o, c) => (o[c.key] = c.off ? false : true, o), {});
  const [cols, setCols] = useState(() => {
    try { return { ...inicial, ...JSON.parse(localStorage.getItem(chave) || "{}") }; } catch (e) { return inicial; }
  });
  useEffect(() => { try { localStorage.setItem(chave, JSON.stringify(cols)); } catch (e) {} }, [chave, cols]);
  return [cols, setCols];
}

// ══════════════════════════ ABA · AJUSTES ══════════════════════════
const COLS_AJ = [
  { key: "aj", label: "Ajuste", width: 150, req: true, sortable: true, resizable: true },
  { key: "data", label: "Data", mono: true, width: 100, sortable: true, sortValue: (r) => r.raw.data },
  { key: "local", label: "Local", width: 130, sortable: true },
  { key: "tipo", label: "Tipo", width: 104, sortable: true, sortValue: (r) => r.raw.tipo },
  { key: "itens", label: "Itens", align: "right", width: 74, sortable: true, sortValue: (r) => r.raw.itens.length },
  { key: "total", label: "Valor ajustado", align: "right", mono: true, width: 140, sortable: true, preco: true, sortValue: (r) => window.EstData.totalItens(r.raw.itens) },
  { key: "recup", label: "Recuperado", align: "right", mono: true, width: 126, sortable: true, preco: true, off: true, sortValue: (r) => r.raw.recuperado },
  { key: "motivo", label: "Motivo do ajuste", width: 300, resizable: true },
  { key: "por", label: "Lançado por", width: 120, sortable: true },
];

function AbaAjustes({ papel, dense, setDense, dados, filtro, setFiltro, local, setLocal, periodo, setPeriodo, onAbrir, onNovo, onExcluir, aviso }) {
  const D = E();
  const { DataTablePro, TabBar, Button, StatusBadge, Pagination, EmptyState, BulkBar } = DS();
  const verPreco = D.can(papel, "preco");
  const [cols, setCols] = usarCols("oimpresso.estoque.colsAj", COLS_AJ);
  const [pag, setPag] = useState(1);
  const [sel, setSel] = useState([]);
  const [busca, setBusca] = useState("");
  const perPage = 10;
  const visiveis = dados.filter((a) => D.ajusteVisivel(papel, a));
  const rows = useMemo(() => visiveis
    .filter((a) => filtro === "all" || a.tipo === filtro)
    .filter((a) => !local || a.local === local)
    .filter((a) => D.noPeriodo(a.data, periodo))
    .filter((a) => casa(a.ref, busca) || casa(D.LOCAIS[a.local].l, busca) || casa(a.motivo, busca) || casa(a.id, busca)),
    [dados, filtro, local, periodo, busca, papel]);
  useEffect(() => { setPag(1); setSel([]); }, [filtro, local, busca, periodo]);
  if (!DataTablePro) return null;
  const pagina = rows.slice((pag - 1) * perPage, pag * perPage);
  const colunas = COLS_AJ.filter((c) => cols[c.key] && (!c.preco || verPreco));
  const linhas = pagina.map((a) => ({
    id: a.id, raw: a,
    state: a.tipo === "abnormal" ? "urgent" : undefined,
    cells: {
      aj: { primary: a.id, sub: a.ref },
      data: D.fmtData(a.data),
      local: D.LOCAIS[a.local].l,
      tipo: StatusBadge ? <StatusBadge label={D.TIPOS[a.tipo]} tone={a.tipo === "abnormal" ? "warning" : "neutral"} /> : D.TIPOS[a.tipo],
      itens: a.itens.length,
      total: D.fmt(D.totalItens(a.itens)),
      recup: a.recuperado ? D.fmt(a.recuperado) : "—",
      motivo: <span className="est-motivo" title={a.motivo}>{a.motivo}</span>,
      por: a.por,
    },
  }));
  const somaAj = rows.reduce((s, a) => s + D.totalItens(a.itens), 0);
  const somaRec = rows.reduce((s, a) => s + a.recuperado, 0);

  const exportar = (tipo) => {
    if (tipo === "print") return window.print();
    const cab = ["Ajuste", "Referência", "Data", "Local", "Tipo", "Itens", ...(verPreco ? ["Valor ajustado", "Recuperado"] : []), "Motivo", "Lançado por"];
    const dados = rows.map((a) => [a.id, a.ref, D.fmtData(a.data), D.LOCAIS[a.local].l, D.TIPOS[a.tipo], a.itens.length,
      ...(verPreco ? [D.totalItens(a.itens).toFixed(2), a.recuperado.toFixed(2)] : []), a.motivo, a.por]);
    const ok = D.baixar("ajustes-estoque." + (tipo === "excel" ? "xls.csv" : "csv"), D.csv(cab, dados), "text/csv");
    aviso(ok ? rows.length + " ajustes exportados." : "Não consegui gerar o arquivo aqui.", ok ? "ok" : "warn");
  };

  return (
    <>
      {TabBar &&
        <TabBar tabs={[
          { key: "all", label: "Todos", count: visiveis.length },
          { key: "normal", label: "Normal", count: visiveis.filter((a) => a.tipo === "normal").length },
          { key: "abnormal", label: "Anormal", count: visiveis.filter((a) => a.tipo === "abnormal").length },
        ]} active={filtro} onChange={setFiltro} />}
      <Toolbar colunas={COLS_AJ.filter((c) => !c.preco || verPreco)} cols={cols} setCols={setCols}
        onExport={exportar} dense={dense} setDense={setDense} busca={busca} setBusca={setBusca}
        periodo={periodo} setPeriodo={setPeriodo}
        info="Trilho vermelho = ajuste anormal. Excluir devolve o saldo (UC-EST-05)."
        filtros={[
          ...(local ? [{ id: "local", label: "Local", value: D.LOCAIS[local].l, onRemove: () => setLocal("") }] : []),
          ...(D.papel(papel).own ? [{ id: "own", label: "Escopo", value: "só os meus lançamentos" }] : []),
        ]}
        acoes={<>
          <select className="est-sel-filtro" value={local} onChange={(e) => setLocal(e.target.value)} aria-label="Filtrar por local">
            <option value="">Todos os locais</option>
            {D.locaisDe(papel).map((k) => <option key={k} value={k}>{D.LOCAIS[k].l}</option>)}
          </select>
          {Button && D.can(papel, "criar") && <Button variant="primary" size="sm" onClick={onNovo}>Novo ajuste</Button>}
        </>} />
      <div className="est-tbl">
        {linhas.length
          ? <DataTablePro columns={colunas} rows={linhas} height={alturaGrid(linhas.length, dense)}
              density={dense ? "compact" : "comfortable"} selectable onSelectionChange={setSel}
              onRowClick={(r) => onAbrir(r.id)} defaultSort={{ key: "data", dir: "desc" }} />
          : EmptyState && <EmptyState variant="no-results" title="Nenhum ajuste com estes filtros" description="Troque tipo, local, período ou busca — ou lance um ajuste novo." />}
      </div>
      <div className="est-rodape">
        <div className="est-somas">
          <div><small>Ajustes</small><b>{rows.length}</b></div>
          {verPreco && <div><small>Valor ajustado</small><b>{D.fmt(somaAj)}</b></div>}
          {verPreco && <div className="ok"><small>Recuperado</small><b>{D.fmt(somaRec)}</b></div>}
          {verPreco && <div className="warn"><small>Perda líquida</small><b>{D.fmt(somaAj - somaRec)}</b></div>}
        </div>
        <div className="est-sp" />
        {Pagination && <Pagination page={pag} pageCount={Math.max(1, Math.ceil(rows.length / perPage))} total={rows.length} pageSize={perPage} onChange={setPag} />}
      </div>
      {BulkBar && sel.length > 0 &&
        <BulkBar count={sel.length} onClose={() => setSel([])}
          actions={[
            { label: "Exportar seleção", onClick: () => exportar("csv") },
            ...(D.can(papel, "excluir") ? [{ label: "Excluir e reverter", tone: "danger", onClick: () => { onExcluir(sel[0]); setSel([]); } }] : []),
          ]} />}
    </>
  );
}

// ══════════════════════════ ABA · TRANSFERÊNCIAS ══════════════════════════
const COLS_TR = [
  { key: "trf", label: "Transferência", width: 160, req: true, sortable: true, resizable: true },
  { key: "data", label: "Data", mono: true, width: 100, sortable: true, sortValue: (r) => r.raw.data },
  { key: "rota", label: "Origem → destino", width: 220, sortable: true, resizable: true },
  { key: "status", label: "Status", width: 130, sortable: true, sortValue: (r) => r.raw.status },
  { key: "saldo", label: "Mexeu no saldo?", width: 140 },
  { key: "itens", label: "Itens", align: "right", width: 74, sortable: true, sortValue: (r) => r.raw.itens.length },
  { key: "frete", label: "Frete", align: "right", mono: true, width: 104, sortable: true, preco: true, sortValue: (r) => r.raw.frete },
  { key: "total", label: "Total", align: "right", mono: true, width: 126, sortable: true, preco: true, sortValue: (r) => window.EstData.totalItens(r.raw.itens) + r.raw.frete },
  { key: "obs", label: "Observação", width: 240, resizable: true, off: true },
];

function AbaTransferencias({ papel, dense, setDense, filtro, setFiltro, local, setLocal, periodo, setPeriodo, dados, onAbrir, onNovo, onStatusLote, onImprimir, onExcluir, aviso }) {
  const D = E();
  const { DataTablePro, TabBar, Button, StatusBadge, Pagination, EmptyState, BulkBar } = DS();
  const verPreco = D.can(papel, "preco");
  const [cols, setCols] = usarCols("oimpresso.estoque.colsTr", COLS_TR);
  const [pag, setPag] = useState(1);
  const [sel, setSel] = useState([]);
  const [busca, setBusca] = useState("");
  const perPage = 10;
  const visiveis = dados.filter((t) => D.transfVisivel(papel, t));
  const rows = useMemo(() => visiveis
    .filter((t) => filtro === "all" || t.status === filtro)
    .filter((t) => !local || t.de === local || t.para === local)
    .filter((t) => D.noPeriodo(t.data, periodo))
    .filter((t) => casa(t.ref, busca) || casa(D.LOCAIS[t.de].l, busca) || casa(D.LOCAIS[t.para].l, busca) || casa(t.id, busca)),
    [filtro, local, periodo, busca, dados, papel]);
  useEffect(() => { setPag(1); setSel([]); }, [filtro, local, busca, periodo]);
  if (!DataTablePro) return null;
  const pagina = rows.slice((pag - 1) * perPage, pag * perPage);
  const colunas = COLS_TR.filter((c) => cols[c.key] && (!c.preco || verPreco));
  const linhas = pagina.map((t) => {
    const move = D.STATUS_TRF[t.status].move;
    return {
      id: t.id, raw: t,
      state: t.status === "pending" ? "urgent" : move ? "archived" : undefined,
      cells: {
        trf: { primary: t.id, sub: t.ref },
        data: D.fmtData(t.data),
        rota: { primary: D.LOCAIS[t.de].l + " → " + D.LOCAIS[t.para].l, sub: t.itens.map((i) => i.sku).join(" · ") },
        status: StatusBadge ? <StatusBadge label={D.STATUS_TRF[t.status].l} tone={D.STATUS_TRF[t.status].tone} /> : D.STATUS_TRF[t.status].l,
        saldo: <span className={move ? "est-mov ok" : "est-mov"}>{move ? "moveu" : "só reserva"}</span>,
        itens: t.itens.length,
        frete: t.frete ? D.fmt(t.frete) : "—",
        total: D.fmt(D.totalItens(t.itens) + t.frete),
        obs: <span className="est-motivo" title={t.obs}>{t.obs || "—"}</span>,
      },
    };
  });
  const soma = rows.reduce((s, t) => s + D.totalItens(t.itens) + t.frete, 0);
  const emRota = rows.filter((t) => !D.STATUS_TRF[t.status].move).reduce((s, t) => s + D.totalItens(t.itens) + t.frete, 0);

  const exportar = (tipo) => {
    if (tipo === "print") return window.print();
    const cab = ["Transferência", "Referência", "Data", "Origem", "Destino", "Status", "Moveu saldo", "Itens", ...(verPreco ? ["Frete", "Total"] : []), "Observação"];
    const dd = rows.map((t) => [t.id, t.ref, D.fmtData(t.data), D.LOCAIS[t.de].l, D.LOCAIS[t.para].l, D.STATUS_TRF[t.status].l, D.STATUS_TRF[t.status].move ? "sim" : "não", t.itens.length,
      ...(verPreco ? [t.frete.toFixed(2), (D.totalItens(t.itens) + t.frete).toFixed(2)] : []), t.obs]);
    const ok = D.baixar("transferencias-estoque." + (tipo === "excel" ? "xls.csv" : "csv"), D.csv(cab, dd), "text/csv");
    aviso(ok ? rows.length + " transferências exportadas." : "Não consegui gerar o arquivo aqui.", ok ? "ok" : "warn");
  };

  return (
    <>
      {TabBar &&
        <TabBar tabs={[
          { key: "all", label: "Todas", count: visiveis.length },
          ...Object.keys(D.STATUS_TRF).map((k) => ({ key: k, label: D.STATUS_TRF[k].l, count: visiveis.filter((t) => t.status === k).length })),
        ]} active={filtro} onChange={setFiltro} />}
      <Toolbar colunas={COLS_TR.filter((c) => !c.preco || verPreco)} cols={cols} setCols={setCols}
        onExport={exportar} dense={dense} setDense={setDense} busca={busca} setBusca={setBusca}
        periodo={periodo} setPeriodo={setPeriodo}
        info="Pendente e em trânsito só reservam. Concluída e finalizada movem o saldo (R-XFER-005)."
        filtros={[
          ...(local ? [{ id: "local", label: "Local", value: D.LOCAIS[local].l, onRemove: () => setLocal("") }] : []),
          ...(D.papel(papel).own ? [{ id: "own", label: "Escopo", value: "só as minhas" }] : []),
        ]}
        acoes={<>
          <select className="est-sel-filtro" value={local} onChange={(e) => setLocal(e.target.value)} aria-label="Filtrar por local">
            <option value="">Todos os locais</option>
            {D.locaisDe(papel).map((k) => <option key={k} value={k}>{D.LOCAIS[k].l}</option>)}
          </select>
          {Button && D.can(papel, "criar") && <Button variant="primary" size="sm" onClick={onNovo}>Nova transferência</Button>}
        </>} />
      <div className="est-tbl">
        {linhas.length
          ? <DataTablePro columns={colunas} rows={linhas} height={alturaGrid(linhas.length, dense)}
              density={dense ? "compact" : "comfortable"} selectable onSelectionChange={setSel}
              onRowClick={(r) => onAbrir(r.id)} defaultSort={{ key: "data", dir: "desc" }} />
          : EmptyState && <EmptyState variant="no-results" title="Nenhuma transferência com estes filtros" description="Troque status, local, período ou busca — ou lance uma transferência nova." />}
      </div>
      <div className="est-rodape">
        <div className="est-somas">
          <div><small>Transferências</small><b>{rows.length}</b></div>
          {verPreco && <div><small>Total transferido</small><b>{D.fmt(soma)}</b></div>}
          {verPreco && <div className="warn"><small>Ainda sem mover saldo</small><b>{D.fmt(emRota)}</b></div>}
        </div>
        <div className="est-sp" />
        {Pagination && <Pagination page={pag} pageCount={Math.max(1, Math.ceil(rows.length / perPage))} total={rows.length} pageSize={perPage} onChange={setPag} />}
      </div>
      {BulkBar && sel.length > 0 &&
        <BulkBar count={sel.length} onClose={() => setSel([])}
          actions={[
            ...(D.can(papel, "status") ? [{ label: "Concluir selecionadas", onClick: () => { onStatusLote(sel, "completed"); setSel([]); } }] : []),
            { label: "Imprimir folhas", onClick: () => onImprimir(sel[0]) },
            { label: "Exportar seleção", onClick: () => exportar("csv") },
            ...(D.can(papel, "excluir") ? [{ label: "Excluir e reverter", tone: "danger", onClick: () => { onExcluir(sel[0]); setSel([]); } }] : []),
          ]} />}
    </>
  );
}

// ══════════════════════════ ABA · VENCIMENTOS ══════════════════════════
function AbaVencimentos({ papel, dense, onBaixar, aviso }) {
  const D = E();
  const { DataTablePro, StatusBadge, EmptyState, Alert } = DS();
  const [janela, setJanela] = useState(30);
  const verPreco = D.can(papel, "preco");
  const rows = useMemo(() => D.vencimentos(janela).filter((v) => D.podeVerLocal(papel, v.local)), [janela, papel]);
  if (!DataTablePro) return null;
  const colunas = [
    { key: "prod", label: "Produto", width: 220, sortable: true, resizable: true },
    { key: "local", label: "Local", width: 130, sortable: true },
    { key: "lote", label: "Lote", mono: true, width: 110, sortable: true },
    { key: "val", label: "Validade", mono: true, width: 110, sortable: true, sortValue: (r) => r.raw.val },
    { key: "estado", label: "Situação", width: 150, sortable: true, sortValue: (r) => r.raw.dias },
    { key: "qtd", label: "Estoque restante", align: "right", mono: true, width: 150, sortable: true, sortValue: (r) => r.raw.qtd },
    ...(verPreco ? [{ key: "valor", label: "Valor", align: "right", mono: true, width: 120, sortable: true, sortValue: (r) => r.raw.qtd * r.raw.custo }] : []),
    { key: "acao", label: "Ação", width: 150 },
  ];
  const linhas = rows.map((v) => ({
    id: v.sku + v.lote, raw: v,
    state: v.estado === "vencido" ? "urgent" : undefined,
    cells: {
      prod: { primary: v.nome, sub: v.sku },
      local: D.LOCAIS[v.local].l,
      lote: v.lote,
      val: D.fmtData(v.val),
      estado: StatusBadge
        ? <StatusBadge label={v.estado === "vencido" ? "Vencido há " + (-v.dias) + " dias" : "Vence em " + v.dias + " dias"} tone={v.estado === "vencido" ? "danger" : "warning"} />
        : v.estado,
      qtd: D.fmtQtd(v.qtd) + " " + v.un,
      valor: D.fmt(v.qtd * v.custo),
      acao: D.can(papel, "criar")
        ? <button className="est-mini" onClick={(e) => { e.stopPropagation(); onBaixar(v); }}>Baixar estoque</button>
        : <span className="est-sem-lote">sem permissão</span>,
    },
  }));
  const totalVencido = rows.filter((v) => v.estado === "vencido").reduce((s, v) => s + v.qtd * v.custo, 0);
  return (
    <>
      <div className="est-toolbar">
        <span className="est-tb-info">Espelha o relatório de vencimento do legado. "Baixar estoque" abre o ajuste anormal já preenchido com o lote — é o <code>remove-expired-stock</code>. Não existe tela React viva pra isso.</span>
        <div className="est-sp" />
        <select className="est-sel-filtro" value={janela} onChange={(e) => setJanela(Number(e.target.value))} aria-label="Janela de validade">
          <option value={0}>Só vencidos</option>
          <option value={30}>Até 30 dias</option>
          <option value={90}>Até 90 dias</option>
          <option value={3650}>Todos os lotes</option>
        </select>
      </div>
      {totalVencido > 0 && Alert && verPreco &&
        <Alert tone="danger" title={D.fmt(totalVencido) + " em lote vencido ainda contando como saldo"}>
          Enquanto ninguém lança o ajuste, a disponibilidade que o balcão vê está errada e o orçamento promete material que não existe.
        </Alert>}
      <div className="est-tbl">
        {linhas.length
          ? <DataTablePro columns={colunas} rows={linhas} height={alturaGrid(linhas.length, dense)} density={dense ? "compact" : "comfortable"} defaultSort={{ key: "val", dir: "asc" }} />
          : EmptyState && <EmptyState variant="done" title="Nenhum lote nesta janela" description="Nada vencido nem vencendo no prazo escolhido." />}
      </div>
    </>
  );
}

// ══════════════════════════ PÁGINA ══════════════════════════
const ABAS = {
  "estoque": "painel", "est-painel": "painel",
  "est-ajustes": "ajustes", "est-ajuste-novo": "ajuste-novo",
  "est-transferencias": "transferencias", "est-transferencia-nova": "transferencia-nova",
  "est-vencimentos": "vencimentos",
  "est-contagem": "contagem", "est-contagem-nova": "contagem-nova",
};

function EstoquePage({ view, papel = "gestor", dense = false, lote = true }) {
  const D = E();
  const MP = window.ModuloPadrao || {};
  const F = FM();
  const C = CT();
  const [aba, setAba] = (MP.useAba || (() => useState("painel")))("oimpresso.estoque.aba", "painel");
  const [avisoNode, avisar] = (MP.useAviso || (() => [null, () => {}]))();
  const [denso, setDenso] = useState(dense);
  useEffect(() => { setDenso(dense); }, [dense]);
  const [filtroAj, setFiltroAj] = useState("all");
  const [filtroTr, setFiltroTr] = useState("all");
  const [localAj, setLocalAj] = useState("");
  const [localTr, setLocalTr] = useState("");
  const [periodo, setPeriodo] = useState({ from: null, to: null, preset: null });
  const [selAj, setSelAj] = useState(null);
  const [selTr, setSelTr] = useState(null);
  const [selCt, setSelCt] = useState(null);
  const [ajustes, setAjustes] = useState(D.AJUSTES);
  const [transf, setTransf] = useState(D.TRANSF);
  const [contagens, setContagens] = useState(D.CONTAGENS);
  const [editar, setEditar] = useState(null);
  const [folha, setFolha] = useState(null);
  const [prefill, setPrefill] = useState(null);
  const [excluir, setExcluir] = useState(null);
  const [hora, setHora] = useState("09:42");

  useEffect(() => { const a = ABAS[view]; if (a) setAba(a); }, [view]);
  useEffect(() => {
    const ok = D.locaisDe(papel);
    if (localAj && ok.indexOf(localAj) < 0) setLocalAj("");
    if (localTr && ok.indexOf(localTr) < 0) setLocalTr("");
  }, [papel]);

  const ir = (alvo) => {
    if (!alvo) return;
    if (alvo.aba) setAba(alvo.aba);
    if (alvo.filtro) { if (alvo.aba === "ajustes") setFiltroAj(alvo.filtro); else setFiltroTr(alvo.filtro); }
    if (alvo.id) { if (alvo.aba === "ajustes") setSelAj(alvo.id); else if (alvo.aba === "contagem") setSelCt(alvo.id); else setSelTr(alvo.id); }
  };

  // remove-expired-stock: o lote vencido vira ajuste anormal pré-preenchido.
  const baixarVencido = (v) => {
    setPrefill({ local: v.local, tipo: "abnormal", itens: [{ sku: v.sku, qtd: v.qtd, lote: v.lote }],
      motivo: "Lote " + v.lote + " vencido em " + D.fmtData(v.val) + " — baixa de estoque vencido." });
    setAba("ajuste-novo");
    avisar("Ajuste anormal pré-preenchido com o lote " + v.lote + ".", "ok");
  };

  // Fechar contagem = gerar o ajuste da diferença (nunca mexer no saldo direto).
  const fecharContagem = (c) => {
    const div = D.divergencias(c);
    if (!div.length) {
      setContagens((v) => v.map((x) => x.id === c.id ? { ...x, status: "fechada" } : x));
      setSelCt(null);
      avisar(c.id + " fechada sem divergência — nada a ajustar.", "ok");
      return;
    }
    setPrefill({ local: c.local, tipo: "normal", contagem: c.id,
      itens: div.map((i) => ({ sku: i.sku, qtd: Math.abs(i.contado - i.sistema), lote: i.lote || "" })),
      motivo: "Contagem " + c.id + " em " + D.LOCAIS[c.local].l + " — " + div.length + " divergências apuradas." });
    setContagens((v) => v.map((x) => x.id === c.id ? { ...x, status: "fechada" } : x));
    setSelCt(null);
    setAba("ajuste-novo");
    avisar(c.id + " fechada — ajuste da diferença pré-preenchido.", "ok");
  };

  const confirmarExclusao = (alvo) => {
    if (alvo.tipo === "ajuste") { setAjustes((v) => v.filter((x) => x.id !== alvo.id)); setSelAj(null); }
    else { setTransf((v) => v.filter((x) => x.id !== alvo.id)); setSelTr(null); }
    setExcluir(null);
    avisar(alvo.id + " excluído — saldo revertido. Protótipo, nada foi gravado.", "ok");
  };
  const pedirExclusao = (id) => {
    const a = ajustes.find((x) => x.id === id);
    if (a) return setExcluir({ tipo: "ajuste", id, itens: a.itens });
    const t = transf.find((x) => x.id === id);
    if (t) setExcluir({ tipo: "transferencia", id, itens: t.itens });
  };

  const ajusteSel = ajustes.find((a) => a.id === selAj);
  const trfSel = transf.find((t) => t.id === selTr);
  const ctSel = contagens.find((c) => c.id === selCt);
  const trfFolha = transf.find((t) => t.id === folha);
  const trfEditar = transf.find((t) => t.id === editar);
  const abertas = transf.filter((t) => !D.STATUS_TRF[t.status].move && D.transfVisivel(papel, t)).length;
  const nVenc = D.vencimentos().filter((v) => D.podeVerLocal(papel, v.local)).length;
  const ctAbertas = contagens.filter((c) => c.status !== "fechada" && D.contagemVisivel(papel, c)).length;
  const Pagina = MP.Pagina || (({ children, className }) => <div className={className}>{children}</div>);

  return (
    <Pagina label="01 Estoque" className={"est-root" + (denso ? " est-denso" : "")}>
      {MP.Header &&
        <MP.Header modulo="Estoque" papel={D.papel(papel).l}
          contexto={["OFFICEIMPRESSO", D.locaisDe(papel).length + " locais", ajustes.length + " ajustes · " + transf.length + " transferências"]}
          atualizadoAs={hora}
          onRefresh={() => { setHora(new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })); avisar("Reapurado agora — painel, ajustes, transferências, vencimentos e contagem.", "ok"); }}
          glyph={<window.JcIcon name="database" />}
          acoes={<>
            {D.can(papel, "criar") && <button className="jc-btn ghost" onClick={() => { setPrefill(null); setAba("ajuste-novo"); }}><window.JcIcon name="plus" className="ic" /><span>Novo ajuste</span></button>}
            {D.can(papel, "criar") && <button className="jc-btn ghost" onClick={() => setAba("contagem-nova")}><window.JcIcon name="list" className="ic" /><span>Contar</span></button>}
            {D.can(papel, "criar") && <button className="jc-btn dark" onClick={() => { setEditar(null); setAba("transferencia-nova"); }}><window.JcIcon name="truck" className="ic" /><span>Nova transferência</span></button>}
          </>} />}

      {MP.Tabs &&
        <MP.Tabs tab={aba} onTab={setAba} aria="Áreas de Estoque"
          tabs={[
            { key: "painel", label: "Painel", icon: "chart" },
            { key: "ajustes", label: "Ajustes", icon: "list", n: ajustes.filter((a) => D.ajusteVisivel(papel, a)).length },
            { key: "transferencias", label: "Transferências", icon: "truck", n: abertas || undefined },
            { key: "vencimentos", label: "Vencimentos", icon: "clock", n: nVenc || undefined },
            { key: "contagem", label: "Contagem", icon: "database", n: ctAbertas || undefined },
          ]} />}

      <div className="mp-body">
        {aba === "painel" && <Painel papel={papel} ajustes={ajustes} transf={transf} contagens={contagens} onIr={ir} />}

        {aba === "ajustes" &&
          <AbaAjustes papel={papel} dense={denso} setDense={setDenso} dados={ajustes}
            filtro={filtroAj} setFiltro={setFiltroAj} local={localAj} setLocal={setLocalAj}
            periodo={periodo} setPeriodo={setPeriodo}
            onAbrir={setSelAj} onExcluir={pedirExclusao}
            onNovo={() => { setPrefill(null); setAba("ajuste-novo"); }} aviso={avisar} />}

        {aba === "transferencias" &&
          <AbaTransferencias papel={papel} dense={denso} setDense={setDenso} dados={transf}
            filtro={filtroTr} setFiltro={setFiltroTr} local={localTr} setLocal={setLocalTr}
            periodo={periodo} setPeriodo={setPeriodo}
            onAbrir={setSelTr} onNovo={() => { setEditar(null); setAba("transferencia-nova"); }}
            onImprimir={setFolha} onExcluir={pedirExclusao} aviso={avisar}
            onStatusLote={(ids, s) => { setTransf((v) => v.map((x) => ids.indexOf(x.id) >= 0 ? { ...x, status: s } : x)); avisar(ids.length + " transferências concluídas.", "ok"); }} />}

        {aba === "vencimentos" &&
          <AbaVencimentos papel={papel} dense={denso} onBaixar={baixarVencido} aviso={avisar} />}

        {aba === "contagem" && C.AbaContagens &&
          <C.AbaContagens papel={papel} dense={denso} dados={contagens} aviso={avisar}
            onAbrir={setSelCt} onNova={() => setAba("contagem-nova")} />}

        {aba === "contagem-nova" && C.FormContagem &&
          <>
            <h2 className="est-h1">Nova contagem cíclica</h2>
            <p className="est-sub">Tela nova — não existe no legado nem no vivo. A contagem não move saldo: fechá-la gera o ajuste da diferença, que é o caminho auditável.</p>
            <C.FormContagem papel={papel} lote={lote} aviso={avisar}
              onCancelar={() => setAba("contagem")}
              onSalvar={(nova) => {
                const id = "CT-" + String(10 + contagens.length - D.CONTAGENS.length).padStart(4, "0");
                setContagens((v) => [{ id, por: D.papel(papel).quem, ajuste: null, ...nova }, ...v]);
                setAba("contagem");
                avisar(id + " aberta em " + D.LOCAIS[nova.local].l + " com " + nova.itens.length + " linhas.", "ok");
              }} />
          </>}

        {aba === "ajuste-novo" && F.FormAjuste &&
          <>
            <h2 className="est-h1">Novo ajuste de estoque</h2>
            <p className="est-sub">Ajuste é o que reconhece a diferença entre o sistema e a prateleira. O saldo é por local, e o teto é o disponível — não o físico.</p>
            <F.FormAjuste papel={papel} lote={lote} inicial={prefill} aviso={avisar}
              onCancelar={() => { setPrefill(null); setAba("ajustes"); }}
              onSalvar={(novo) => {
                const id = "AJ-" + String(143 + ajustes.length - D.AJUSTES.length).padStart(4, "0");
                const contagem = prefill && prefill.contagem;
                setAjustes((v) => [{ id, ref: "AJ2026/" + id.slice(3), data: D.HOJE, por: D.papel(papel).quem, contagem, ...novo }, ...v]);
                if (contagem) setContagens((v) => v.map((c) => c.id === contagem ? { ...c, ajuste: id } : c));
                setPrefill(null); setAba("ajustes");
                avisar(id + " lançado em " + D.LOCAIS[novo.local].l + " — protótipo, nada foi gravado.", "ok");
              }} />
          </>}

        {aba === "transferencia-nova" && F.FormTransferencia &&
          <>
            <h2 className="est-h1">{trfEditar ? "Editar " + trfEditar.id : "Nova transferência"}</h2>
            <p className="est-sub">Transferência move saldo entre locais e conserva o total. {trfEditar ? "Status terminal não se edita mais." : "Só o status terminal libera a venda no destino."}</p>
            <F.FormTransferencia papel={papel} lote={lote} editar={trfEditar} aviso={avisar}
              onCancelar={() => { setEditar(null); setAba("transferencias"); }}
              onSalvar={(nova) => {
                if (trfEditar) {
                  setTransf((v) => v.map((x) => x.id === trfEditar.id ? { ...x, ...nova } : x));
                  avisar(trfEditar.id + " atualizada — protótipo, nada foi gravado.", "ok");
                } else {
                  const id = "TRF-" + String(89 + transf.length - D.TRANSF.length).padStart(4, "0");
                  setTransf((v) => [{ id, por: D.papel(papel).quem, ...nova }, ...v]);
                  avisar(id + " criada · " + D.LOCAIS[nova.de].l + " → " + D.LOCAIS[nova.para].l + " — protótipo, nada foi gravado.", "ok");
                }
                setEditar(null); setAba("transferencias");
              }} />
          </>}
      </div>

      {ajusteSel && F.DrawerAjuste &&
        <F.DrawerAjuste a={ajusteSel} papel={papel} lote={lote} aviso={avisar}
          onClose={() => setSelAj(null)} onIr={irRota} onExcluir={pedirExclusao} />}

      {trfSel && F.DrawerTransferencia &&
        <F.DrawerTransferencia t={trfSel} papel={papel} lote={lote} aviso={avisar}
          onClose={() => setSelTr(null)} onIr={irRota} onExcluir={pedirExclusao}
          onImprimir={(id) => { setFolha(id); setSelTr(null); }}
          onEditar={(id) => { setEditar(id); setSelTr(null); setAba("transferencia-nova"); }}
          onStatus={(id, s) => setTransf((v) => v.map((x) => x.id === id ? { ...x, status: s } : x))} />}

      {ctSel && C.DrawerContagem &&
        <C.DrawerContagem c={ctSel} papel={papel} aviso={avisar}
          onClose={() => setSelCt(null)} onIr={ir} onFechar={fecharContagem}
          onContar={(id, idx, valor) => setContagens((v) => v.map((c) => c.id !== id ? c : {
            ...c, status: "contando",
            itens: c.itens.map((i, n) => n !== idx ? i : { ...i, contado: valor === "" ? null : Number(valor) }),
          }))} />}

      {trfFolha && F.FolhaTransferencia &&
        <F.FolhaTransferencia t={trfFolha} verPreco={D.can(papel, "preco")} onFechar={() => setFolha(null)} />}

      {excluir && F.ModalExcluir &&
        <F.ModalExcluir alvo={excluir} onClose={() => setExcluir(null)} onConfirmar={confirmarExclusao} />}

      {avisoNode}
    </Pagina>
  );
}

window.EstoquePage = EstoquePage;
})();
