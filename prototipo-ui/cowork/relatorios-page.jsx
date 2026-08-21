// relatorios-page.jsx — módulo RELATÓRIOS do Cockpit V2, importado dos blades
// `resources/views/report/*`. Hub (índice) + tela de relatório.
// Onda 1: filtros = os `Form::label` do blade (nada inferido); colunas custom-field
//         entram como opcionais no menu de colunas, como o colvis do DataTable do vivo.
// Onda 2: coluna de ação (`messages.action`) com DropdownMenu, drawer PT-02 de detalhe,
//         seleção + BulkBar, paginação real (fatia client-side) e o modal de validade
//         (report/partials/stock_expiry_edit_modal.blade.php).
// Onda 3: o grupo "Gráfica" (m², bobina, lucro por OS, retrabalho) — telas NOVAS, marcadas.
// Catálogo e apuração ficam em relatorios-data.jsx (window.RELD).
// Expõe window.RelatoriosPage.
(() => {
const { useState, useMemo, useEffect, useRef } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };
const ICONE_GRUPO = { Financeiro: "cash", Comercial: "chart", Estoque: "archive", Fiscal: "receipt", Gráfica: "scissor", Sistema: "audit" };
const PAGINA = 10;
const irPara = (rota) => { if (rota && window.__selectRoute) window.__selectRoute(rota); };

// ─────────── Índice (o menu "Relatórios" da sidebar Blade, agrupado) ───────────
function Indice({ onAbrir, grupo }) {
  const { REPORTS, FORA } = window.RELD;
  const ORDEM = grupo ? [grupo] : window.RELD.GRUPOS_ORDEM;
  const [busca, setBusca] = useState("");
  const filtrados = useMemo(() => {
    const b = busca.trim().toLowerCase();
    if (!b) return REPORTS;
    return REPORTS.filter((r) => ((r.label + " " + (r.legado || "") + " " + (r.blade || "") + " " + r.desc).toLowerCase().indexOf(b) >= 0));
  }, [busca, REPORTS]);
  const { EmptyState } = DS();
  return (
    <>
      <div className="rel-busca">
        <Ic name="search" size={14} />
        <input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar relatório — nome, blade ou o que ele responde" aria-label="Buscar relatório" />
      </div>
      {filtrados.length === 0 && EmptyState &&
        <EmptyState variant="no-results" title="Nenhum relatório com esse termo" description="Tente pelo nome do blade (ex: stock_report) ou pelo nome do menu antigo." />}
      {ORDEM.map((g) => {
        const itens = filtrados.filter((r) => r.grupo === g);
        if (!itens.length) return null;
        return (
          <section className="rel-grupo" key={g}>
            <h2 className="rel-grupo-h"><Ic name={ICONE_GRUPO[g]} size={13} /> {g} <span className="rel-n">{itens.length}</span>{g === "Gráfica" && <span className="rel-novo">novo — pendente de aprovação [W]</span>}</h2>
            <div className="rel-cards">
              {itens.map((r) =>
                <button className="rel-card" key={r.id} onClick={() => onAbrir(r.id)} data-novo={r.novo ? "1" : null}>
                  <span className="rel-card-top">
                    <span className="rel-card-ic"><Ic name={ICONE_GRUPO[g]} size={13} /></span>
                    <span className="rel-card-t">{r.label}</span>
                  </span>
                  <span className="rel-card-d">{r.desc}</span>
                  <span className="rel-card-b">
                    <span className="rel-tag">{r.blade || "sem blade — tela nova"}</span>
                    {r.modulo && <span className="rel-tag">módulo {r.modulo}</span>}
                    {r.legado ? <span>menu antigo: {r.legado}</span> : <span>não existe no vivo</span>}
                  </span>
                </button>)}
            </div>
          </section>);
      })}
      {!busca && !grupo &&
        <div className="rel-fora">
          <div className="rel-grupo-h">Blades que não vieram <span className="rel-n">{FORA.length}</span></div>
          {FORA.map((f) =>
            <div className="rel-fora-l" key={f.blade}><code>{f.blade}</code><span>{f.motivo}</span></div>)}
        </div>}
    </>);
}

// ─────────── Barra de filtros (os `Form::select` do blade) ───────────
function Filtros({ rep, valores, setValor, periodo, setPeriodo, periodo2, setPeriodo2, onAplicar }) {
  const { Select, PeriodBar } = DS();
  const { F } = window.RELD;
  if (!Select) return null;
  const periodos = rep.periodos || ["Período"];
  return (
    <div className="rel-filtros">
      <div className="rel-filtros-h">
        <span>Filtros{rep.filtroNota && <span className="rel-filtros-nota"> — {rep.filtroNota}</span>}</span>
        <button className="rel-btn" data-primary onClick={onAplicar}><Ic name="filter" size={12} /> Aplicar filtros</button>
      </div>
      {PeriodBar && <PeriodBar value={periodo} onChange={setPeriodo} label={periodos[0]} />}
      {PeriodBar && periodos[1] && <PeriodBar value={periodo2} onChange={setPeriodo2} label={periodos[1]} />}
      <div className="rel-filtros-g">
        {(rep.filtros || []).map((f) => {
          const def = F[f];
          if (!def) return null;
          return <Select key={f} label={def.label} options={def.opts} value={valores[f] || def.opts[0]} onChange={(e) => setValor(f, e.target.value)} />;
        })}
      </div>
    </div>);
}

// ─────────── Menu de colunas (o colvis do DataTable do vivo) ───────────
function Colunas({ cols, ocultas, setOcultas }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    if (!open) return;
    const fora = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    const esc = (e) => { if (e.key === "Escape") setOpen(false); };
    document.addEventListener("mousedown", fora); document.addEventListener("keydown", esc);
    return () => { document.removeEventListener("mousedown", fora); document.removeEventListener("keydown", esc); };
  }, [open]);
  const visiveis = cols.filter((c) => !ocultas[c.k]).length;
  return (
    <span className="rel-colvis" ref={ref}>
      <button className="rel-btn" onClick={() => setOpen((o) => !o)} aria-haspopup="menu" aria-expanded={open}>
        <Ic name="layers" size={12} /> Colunas <span className="rel-colvis-n">{visiveis}/{cols.length}</span>
      </button>
      {open &&
        <div className="rel-colvis-menu" role="menu">
          {cols.map((c) =>
            <label className="rel-colvis-item" key={c.k}>
              <input type="checkbox" checked={!ocultas[c.k]} onChange={() => setOcultas((o) => ({ ...o, [c.k]: !o[c.k] }))} />
              <span>{c.l}</span>
              {c.opt && <span className="rel-colvis-tag">opcional</span>}
            </label>)}
        </div>}
    </span>);
}

// ─────────── Tabela + ações + seleção + paginação + rodapé de totais ───────────
function Tabela({ rep, cols, acoes, nota, apurado, todas, onAviso, onDetalhe, onValidade }) {
  const { DataTablePro, Pagination, BulkBar, DropdownMenu } = DS();
  const { totais } = window.RELD;
  const chave = "oimpresso.rel.cols." + rep.id + "." + cols.map((c) => c.k).join("");
  const [ocultas, setOcultas] = useState(() => { const o = {}; cols.forEach((c) => { if (c.opt) o[c.k] = true; }); try { const j = localStorage.getItem(chave); if (j) return JSON.parse(j); } catch (e) {} return o; });
  const [pagina, setPagina] = useState(1);
  const [sel, setSel] = useState([]);
  useEffect(() => { try { localStorage.setItem(chave, JSON.stringify(ocultas)); } catch (e) {} }, [chave, ocultas]);
  useEffect(() => { setPagina(1); setSel([]); }, [rep.id, cols, apurado]);
  const visiveis = useMemo(() => cols.filter((c) => !ocultas[c.k]), [cols, ocultas]);
  const tot = useMemo(() => totais(visiveis, todas), [visiveis, todas]);
  const pageCount = Math.max(1, Math.ceil(todas.length / PAGINA));
  const fatia = todas.slice((pagina - 1) * PAGINA, pagina * PAGINA);
  const { StatusBadge } = DS();
  const colunas = visiveis.map((c) => ({ key: c.k, label: c.l, sortable: true, align: c.t === "m" || c.t === "q" || c.t === "p" ? "right" : undefined, width: c.t === "m" || c.t === "q" || c.t === "p" ? 148 : undefined }));
  if ((acoes || []).length) colunas.push({ key: "__acao", label: "Ação", width: 60, sortable: false, resizable: false });
  const linhas = fatia.map((r) => {
    const cells = {};
    visiveis.forEach((c) => { cells[c.k] = c.t === "s" && StatusBadge ? <StatusBadge kind="payment" value={r.cells[c.k]} /> : r.cells[c.k]; });
    if ((acoes || []).length && DropdownMenu)
      cells.__acao = <span className="rel-acao-w" onClick={(e) => e.stopPropagation()}><DropdownMenu align="right" width={210}
        trigger={<span className="rel-acao-t" aria-hidden="true"><Ic name="moreV" size={14} /></span>}
        items={acoes.map((a) => ({ id: a.id, label: a.label, icon: <Ic name={a.icon} size={13} />, onSelect: () => {
          if (a.modal === "validade") return onValidade(r);
          if (a.id === "imprimirLinha") { onAviso("Linha enviada pra impressão — o layout de prova sai no F3."); return; }
          onAviso(a.label + " — abrindo " + a.rota + " com o contexto da linha.");
          irPara(a.rota);
        } }))} /></span>;
    return { id: r.id, cells, __raw: r };
  });
  if (!DataTablePro) return null;
  const chavesTot = Object.keys(tot);
  return (
    <div className="rel-tabela">
      <div className="rel-tabela-h">
        <span className="rel-cont">{todas.length} linhas apuradas · página {pagina} de {pageCount}</span>
        <span className="rel-acoes">
          <Colunas cols={cols} ocultas={ocultas} setOcultas={setOcultas} />
          <button className="rel-btn" onClick={() => onAviso("CSV gerado — o download real sai no Inertia (F3).")}><Ic name="sheet" size={12} /> CSV</button>
          <button className="rel-btn" onClick={() => onAviso("PDF gerado — o download real sai no Inertia (F3).")}><Ic name="doc" size={12} /> PDF</button>
          <button className="rel-btn" onClick={() => window.print()}><Ic name="printer" size={12} /> Imprimir</button>
        </span>
      </div>
      <DataTablePro columns={colunas} rows={linhas} height={Math.min(520, 92 + fatia.length * 41)} density="compact"
        selectable onSelectionChange={setSel} onRowClick={(row) => { const l = linhas.find((x) => x.id === row.id); if (l) onDetalhe({ rep, cols: visiveis, row: l.__raw }); }} />
      {chavesTot.length > 0 &&
        <div className="rel-totais">
          {chavesTot.map((k) => {
            const col = visiveis.find((c) => c.k === k);
            return <span className="rel-total" key={k}><span className="rel-total-l">Total — {col.l}</span><span className="rel-total-v">{tot[k]}</span></span>;
          })}
        </div>}
      <div className="rel-rodape">
        <span className="rel-nota">{nota}</span>
        {Pagination && <Pagination page={pagina} pageCount={pageCount} total={todas.length} pageSize={PAGINA} onChange={setPagina} />}
      </div>
      {sel.length > 0 && BulkBar &&
        <BulkBar count={sel.length} label={sel.length === 1 ? "linha selecionada" : "linhas selecionadas"} onClose={() => setSel([])}
          actions={[
            { id: "csv", label: "Exportar seleção", onSelect: () => onAviso(sel.length + " linhas exportadas em CSV.") },
            { id: "print", label: "Imprimir seleção", onSelect: () => window.print() },
            { id: "os", label: "Abrir no módulo", onSelect: () => onAviso("Abrindo as linhas selecionadas no módulo de origem.") }]} />}
    </div>);
}

// ─────────── Resumo (profit_loss, purchase_sell, product_stock_details) ───────────
function Resumo({ resumo, fecho }) {
  const { BRL, QTD } = window.RELD;
  const linhasFecho = fecho || resumo.fecho || [];
  const fmt = (v) => resumo.unidade ? QTD(v) + " " + resumo.unidade : BRL(v);
  const painel = (p) =>
    <div className="rel-painel">
      <div className="rel-painel-h">{p.titulo}</div>
      {p.linhas.map((l) => <div className="rel-linha" key={l[0]}><span className="rel-linha-l">{l[0]}</span><span className="rel-linha-v">{fmt(l[1])}</span></div>)}
    </div>;
  return (
    <>
      <div className="rel-resumo">{painel(resumo.esq)}{painel(resumo.dir)}</div>
      {linhasFecho.length > 0 && <div className="rel-fecho">
        {linhasFecho.map((f) =>
          <div className="rel-fecho-c" data-tone={f[2]} key={f[0]}>
            <span className="rel-fecho-l">{f[0]}</span>
            <span className="rel-fecho-v">{fmtFecho(f[0], f[1], resumo.unidade)}</span>
          </div>)}
      </div>}
    </>);
}

// KPI/fecho do topo — o formato vem do rótulo (%, m², contagem, dinheiro).
const fmtKpi = (label, v) => {
  const { BRL, QTD, PCT } = window.RELD;
  if (/margem|perda sobre|sobre total|%/i.test(label)) return PCT(v);
  if (/m²/i.test(label)) return QTD(v) + " m²";
  if (/^os /i.test(label)) return QTD(v);
  return BRL(v);
};
const fmtFecho = (label, v, unidade) => unidade ? window.RELD.QTD(v) + " " + unidade : fmtKpi(label, v);

// ─────────── Tela de um relatório ───────────
function Relatorio({ rep, onVoltar }) {
  const { TabBar, KpiCard, Chart, Drawer, DrawerSection, Modal, Toast, Alert, Input } = DS();
  const { gerar, hoje, menos } = window.RELD;
  const [valores, setValores] = useState({});
  const [periodo, setPeriodo] = useState({ from: menos(29), to: hoje(), preset: "Mês" });
  const [periodo2, setPeriodo2] = useState({ from: menos(29), to: hoje(), preset: "Mês" });
  const [aba, setAba] = useState((rep.tabs || [])[0] ? rep.tabs[0].key : null);
  const [apurado, setApurado] = useState(0);
  const [aviso, setAviso] = useState(null);
  const [detalhe, setDetalhe] = useState(null);
  const [validade, setValidade] = useState(null);
  useEffect(() => { setAba((rep.tabs || [])[0] ? rep.tabs[0].key : null); setValores({}); setApurado(0); }, [rep.id]);
  useEffect(() => { if (!aviso) return; const t = setTimeout(() => setAviso(null), 2600); return () => clearTimeout(t); }, [aviso]);
  const setValor = (k, v) => setValores((o) => ({ ...o, [k]: v }));
  const tab = (rep.tabs || []).find((t) => t.key === aba);
  const cols = tab ? tab.cols : rep.cols;
  const acoes = (tab && tab.acoes) || rep.acoes || [];
  const repEfetivo = tab && tab.n ? { ...rep, n: tab.n } : rep;
  const linhas = useMemo(() => cols ? gerar(repEfetivo, cols, repEfetivo.n || 12) : [], [repEfetivo, cols, apurado]);
  const derivado = useMemo(() => window.RELD.derivar(rep, linhas, tab), [rep, linhas, tab]);
  const kpis = (derivado && derivado.kpis) || rep.kpis;
  const grafico = useMemo(() => {
    if (rep.kind !== "grafico") return null;
    return gerar(rep, rep.cols, rep.n || 8).map((r) => ({ label: r.cells.produto, value: Number(String(r.cells.vendido).replace(/\./g, "").replace(",", ".")) }));
  }, [rep, apurado]);
  return (
    <>
      <div className="rel-barra">
        <span className="rel-barra-l">
          <button className="rel-voltar" onClick={onVoltar}><Ic name="chev" size={12} /> Relatórios</button>
          <strong>{rep.label}</strong>
          <span className="rel-nota">{rep.blade || "tela nova — sem blade de origem"}</span>
        </span>
        <span className="rel-acoes">
          <button className="rel-btn" onClick={() => { setApurado((n) => n + 1); setAviso("Reapurado com os filtros atuais."); }}><Ic name="refresh" size={12} /> Reapurar</button>
        </span>
      </div>

      {rep.novo && Alert &&
        <Alert tone="warn" title="Relatório novo — não existe no vivo">
          Esta leitura não vem de nenhum blade: é proposta de gráfica (m², bobina, OS, retrabalho) e depende de aprovação [W] antes de virar tela no Inertia.
        </Alert>}

      <Filtros rep={rep} valores={valores} setValor={setValor} periodo={periodo} setPeriodo={setPeriodo} periodo2={periodo2} setPeriodo2={setPeriodo2} onAplicar={() => { setApurado((n) => n + 1); setAviso("Filtros aplicados."); }} />

      {kpis && KpiCard &&
        <div className="rel-kpis">
          {kpis.map((k) => <KpiCard key={k[0]} label={k[0]} value={fmtKpi(k[0], k[1])} tone={k[2]} />)}
        </div>}

      {rep.resumo && <Resumo resumo={rep.resumo} fecho={derivado && derivado.fecho} />}

      {rep.kind === "grafico" && Chart &&
        <div className="rel-painel">
          <div className="rel-painel-h">Top {valores.limite || "5"} produtos do período — unidades vendidas</div>
          <div className="rel-painel-b">
            <Chart type="bar" data={grafico} height={200} formatValue={(v) => Number(v).toLocaleString("pt-BR") + " un"} />
          </div>
        </div>}

      {(rep.tabs || []).length > 0 && TabBar &&
        <TabBar tabs={rep.tabs.map((t) => ({ key: t.key, label: t.label }))} active={aba} onChange={setAba} />}

      {cols && <Tabela rep={repEfetivo} cols={cols} acoes={acoes} apurado={apurado} todas={linhas}
        nota={(rep.blade || "tela nova") + (tab ? " · aba " + tab.label.toLowerCase() : "")}
        onAviso={setAviso} onDetalhe={setDetalhe} onValidade={(row) => setValidade(row)} />}

      {Drawer && DrawerSection &&
        <Drawer open={!!detalhe} onClose={() => setDetalhe(null)} badge={rep.grupo} title={rep.label} subtitle={detalhe ? "Linha apurada · " + (rep.blade || "tela nova") : ""}
          footer={<>
            <button className="rel-btn" onClick={() => setDetalhe(null)}>Fechar</button>
            {acoes[0] && <button className="rel-btn" data-primary onClick={() => { setDetalhe(null); irPara(acoes[0].rota); }}>{acoes[0].label}</button>}
          </>}>
          {detalhe &&
            <DrawerSection title="Valores da linha">
              {detalhe.cols.map((c) =>
                <div className="rel-linha" key={c.k}><span className="rel-linha-l">{c.l}</span><span className="rel-linha-v">{detalhe.row.cells[c.k]}</span></div>)}
            </DrawerSection>}
          {detalhe &&
            <DrawerSection title="De onde vem">
              <p className="rel-drawer-p">Apurado de <code>{rep.blade || "tela nova (sem blade)"}</code> com o período e os filtros aplicados na tela. No vivo, esta linha abre o registro de origem no módulo correspondente.</p>
            </DrawerSection>}
        </Drawer>}

      {Modal &&
        <Modal open={!!validade} onClose={() => setValidade(null)} title="Editar validade do lote"
          footer={<>
            <button className="rel-btn" onClick={() => setValidade(null)}>Cancelar</button>
            <button className="rel-btn" data-primary onClick={() => { setValidade(null); setAviso("Validade atualizada no lote."); }}>Salvar</button>
          </>}>
          {validade && Input &&
            <>
              <p className="rel-drawer-p">Lote <code>{validade.cells.lote}</code> · {validade.cells.produto} · {validade.cells.local}</p>
              <Input label="Nova data de validade" defaultValue={validade.cells.validade} help="dd/mm/aaaa — igual ao modal report/partials/stock_expiry_edit_modal.blade.php" />
            </>}
        </Modal>}

      {aviso && Toast && <Toast tone="ok" icon={<Ic name="check" size={12} />}>{aviso}</Toast>}
    </>);
}

// ─────────── Página ───────────
function RelatoriosPage({ grupo }) {
  const { PageHeader } = DS();
  const { REPORTS, GRUPOS_ORDEM } = window.RELD;
  const [rid, setRid] = useState(() => { try { return localStorage.getItem("oimpresso.rel.aberto") || null; } catch (e) { return null; } });
  useEffect(() => { try { rid ? localStorage.setItem("oimpresso.rel.aberto", rid) : localStorage.removeItem("oimpresso.rel.aberto"); } catch (e) {} }, [rid]);
  useEffect(() => { if (grupo) setRid(null); }, [grupo]);
  const rep = REPORTS.find((r) => r.id === rid);
  const doGrupo = grupo ? REPORTS.filter((r) => r.grupo === grupo).length : 0;
  return (
    <div className="jc-page rel-page" data-screen-label={rep ? "Relatório · " + rep.label : grupo ? "Relatórios · " + grupo : "Relatórios"}>
      {PageHeader &&
        <PageHeader title={rep ? rep.label : grupo ? "Relatórios · " + grupo : "Relatórios"}
          stats={rep ? [{ value: rep.grupo, label: "" }, { value: rep.legado || "tela nova", label: rep.legado ? "no menu antigo" : "" }] :
            grupo ? [{ value: doGrupo, label: "relatórios" }] :
            [{ value: REPORTS.length, label: "relatórios" }, { value: GRUPOS_ORDEM.length, label: "grupos" }]}
          actions={rep ? null : <span className="rel-nota">resources/views/report/*</span>} />}
      {rep ? <Relatorio rep={rep} onVoltar={() => setRid(null)} /> : <Indice onAbrir={setRid} grupo={grupo} />}
    </div>);
}
window.RelatoriosPage = RelatoriosPage;
})();
