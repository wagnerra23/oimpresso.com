// manufacturing-page.jsx — módulo Manufacturing dentro do shell Cockpit V2.
// Quatro abas: Receitas (ficha técnica/BOM), Ordens de produção, Relatório e Configurações.
// Dados em manufacturing-data.jsx (window.MFG); CRUD da receita em manufacturing-recipe.jsx;
// produção/relatório/config em manufacturing-producao.jsx.
// CSS em manufacturing-page.css (escopo .mfg-root). Expõe window.ManufacturingPage.
//
// ADERÊNCIA AO DS (onda A, 2026-09-08; onda B, 2026-09-23): moldura, KPIs, overlays, barra de
// seleção, estado vazio, etiqueta de margem, toast, e agora a grade (DataGrid com ordenação,
// seleção e página controladas), a barra (Toolbar + SearchInput com "/" via focusKey), o filtro
// de categoria (Segmented) e o carregamento (Skeleton) vêm do bundle compilado
// window.OfficeImpressoPontoWR2DesignSystem_019dd0.
// [TELA] SearchInput e não ToolbarSearch (que o PT-01 usa): o kbd do ToolbarSearch é só visual
// (Toolbar.jsx L59-72), o atalho "/" que a tela já tinha pararia de funcionar.
// [TELA] Segmented aceita 2–5 opções (Segmented.d.ts); hoje são 4 (Todas + 3 categorias). Uma
// quinta categoria nova ainda cabe; a sexta pede outra peça.
(() => {
const { useState, useMemo, useEffect, useRef } = React;
const I = window.I;
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};

// A família é lazy e a fila do carregador (oimpresso.com.html L344-437) executa um arquivo por
// macrotask. Este arquivo pode rodar ANTES de manufacturing-print/insumos/producao terminarem —
// medido em 08/09/2026: com a rota "manufacturing" salva, MfgProducaoView já existia enquanto
// MfgInsumosView e MfgFichaPrint ainda eram undefined. Renderizar <undefined /> lança
// "Element type is invalid" e o boundary do shell troca o módulo inteiro por "Carregando módulo…".
// Guarda: ler o irmão por função e forçar re-render a cada tick da fila até ela fechar.
const irmao = (nome) => window[nome];
function useFilaDoLoader() {
  const [, tick] = useState(0);
  useEffect(() => {
    if (window.__oiLazyDone) return;
    const h = () => tick((n) => n + 1);
    document.addEventListener("oi:lazy-tick", h);
    document.addEventListener("oi:lazy-done", h);
    return () => { document.removeEventListener("oi:lazy-tick", h); document.removeEventListener("oi:lazy-done", h); };
  }, []);
}
function Aguardando({ o_que }) {
  const { Skeleton } = ds();
  return <div role="status" aria-label={"Carregando " + o_que} style={{ padding: "16px 20px" }}>{Skeleton && <Skeleton variant="row" count={4} />}</div>;
}

const ABAS = [
  { id: "receitas", l: "Receitas" },
  { id: "insumos", l: "Insumos" },
  { id: "producao", l: "Ordens de produção" },
  { id: "relatorio", l: "Relatório" },
  { id: "config", l: "Configurações" },
];

// [TELA] Rede de proteção (§8.11): sem o bundle, ds() devolve {} e desestruturar os componentes
// derrubava a árvore com "Element type is invalid". A guarda fica num componente de fora para a
// ordem dos hooks do módulo não depender de o bundle ter carregado.
const DS_NECESSARIOS = ["PageHeader", "TabBar", "Button", "KpiCard", "BulkBar", "EmptyState", "StatusBadge", "Modal", "Toast", "DataGrid", "Toolbar", "SearchInput", "Segmented", "Drawer", "DrawerSection", "Skeleton"];
function ManufacturingPage(props) {
  const m = ds();
  const faltam = DS_NECESSARIOS.filter((n) => !m[n]);
  if (faltam.length) return (
    <div className="mfg-root" role="alert" style={{ padding: "24px 20px", display: "flex", flexDirection: "column", gap: 8, maxWidth: 640 }}>
      <b style={{ fontSize: "var(--fs-5, 15px)", color: "var(--text)" }}>A Fabricação não abriu</b>
      <span style={{ fontSize: "var(--fs-4, 13.5px)", color: "var(--text-dim)", lineHeight: 1.5 }}>Os componentes do design system não carregaram ({faltam.length === DS_NECESSARIOS.length ? "nenhum disponível" : "faltam " + faltam.join(", ")}). O restante do sistema continua disponível — recarregue a página para tentar de novo.</span>
    </div>
  );
  return <ManufacturingModulo {...props} />;
}

function ManufacturingModulo({ initialView }) {
  useFilaDoLoader();
  const { PageHeader, TabBar, Button, KpiCard, BulkBar, EmptyState, StatusBadge, Modal, Toast, DataGrid, Toolbar, SearchInput, Segmented } = ds();
  const MFG = window.MFG;
  const { fmt, num, custos } = MFG;
  const [aba, setAba] = useState(initialView || "receitas");
  const [recipes, setRecipes] = useState(MFG.RECIPES);
  const [producoes, setProducoes] = useState(MFG.PRODUCOES);
  const [settings, setSettings] = useState(MFG.SETTINGS);
  const [perms, setPerms] = useState({ ver: true, criar: true, editar: true, prod: true });
  const [tela, setTela0] = useState(null); // {tipo:'receita-edit'|'op-form', id}
  // [TELA] Foco (§8.10): o editor e o formulário trocam o corpo do módulo, então o gatilho some do
  // DOM. Ao entrar, o foco vai ao título da trilha; ao sair, volta pelo descritor "volta" —
  // botão pelo rótulo, ou linha da grade pela legenda da tabela + texto da linha. Sem alvo: título da página.
  const telaRef = useRef(null);
  const voltaRef = useRef(null);
  const setTela = (t, volta) => {
    if (t) {
      const a = document.activeElement;
      voltaRef.current = volta || (a && a.tagName === "BUTTON" ? { botao: a.textContent.trim() } : null);
    }
    setTela0(t);
  };
  const [novaOpen, setNovaOpen] = useState(false);
  const [opAberta, setOpAberta] = useState(null);

  // ── Receitas: filtros/lista ──
  const [q, setQ] = useState("");
  const [cat, setCat] = useState("Todas");
  const [kpi, setKpi] = useState(null);
  const [sel, setSel] = useState([]);
  const [openId, setOpenId] = useState(null);
  const [ord, setOrd] = useState({ k: "name", dir: "asc" });
  const [pag, setPag] = useState(1);
  const [confirma, setConfirma] = useState(null); // receita a excluir
  const [imprimir, setImprimir] = useState(null);
  const [toast, setToast] = useState(null);
  const aviso = (t) => { setToast(t); setTimeout(() => setToast(null), 2600); };
  const telaPronta = !!tela && !!(tela.tipo === "receita-edit" ? window.MfgIngredientesEditor : window.MfgProducaoForm);
  const estavaEmTela = useRef(false);
  useEffect(() => {
    if (tela) {
      estavaEmTela.current = true;
      if (!telaPronta) return;
      const id = requestAnimationFrame(() => {
        const t = telaRef.current && telaRef.current.querySelector(".mfg-crumb b");
        if (t) { t.setAttribute("tabindex", "-1"); t.focus(); }
      });
      return () => cancelAnimationFrame(id);
    }
    if (!estavaEmTela.current) return;
    estavaEmTela.current = false;
    const v = voltaRef.current; voltaRef.current = null;
    const id = requestAnimationFrame(() => {
      const root = document.querySelector(".mfg-root");
      if (!root) return;
      let alvo = null;
      if (v && v.botao) alvo = Array.from(root.querySelectorAll("button")).find((b) => b.textContent.trim() === v.botao);
      if (v && v.tabela) {
        const tb = Array.from(root.querySelectorAll("table")).find((t) => t.caption && t.caption.textContent.trim() === v.tabela);
        if (tb) alvo = Array.from(tb.querySelectorAll("tbody tr[tabindex]")).find((tr) => tr.textContent.includes(v.texto));
      }
      if (!alvo) { alvo = root.querySelector("h1"); if (alvo) alvo.setAttribute("tabindex", "-1"); }
      if (alvo) alvo.focus();
    });
    return () => cancelAnimationFrame(id);
  }, [tela, telaPronta]);

  const linhas = useMemo(() => recipes.map((r) => ({ r, c: custos(r) })), [recipes, custos]);
  const CATS = useMemo(() => ["Todas", ...Array.from(new Set(recipes.map((r) => r.cat)))], [recipes]);
  const CHAVES = { name: (l) => l.r.name.toLowerCase(), cat: (l) => l.r.cat + l.r.sub, qtd: (l) => l.c.qtdLiq, total: (l) => l.c.total, unit: (l) => l.c.unit, venda: (l) => l.r.venda, margem: (l) => l.c.margem };
  const filtradas = useMemo(() => {
    const base = linhas.filter(({ r, c }) => {
      if (cat !== "Todas" && r.cat !== cat) return false;
      if (kpi === "margem" && c.margem >= 45) return false;
      if (kpi === "custo" && r.waste < 8) return false;
      const t = q.trim().toLowerCase();
      return !t || (r.name + " " + r.sku + " " + r.cat + " " + r.sub).toLowerCase().includes(t);
    });
    const f = CHAVES[ord.k] || CHAVES.name;
    return base.sort((a, b) => { const va = f(a), vb = f(b); const s = va > vb ? 1 : va < vb ? -1 : 0; return ord.dir === "asc" ? s : -s; });
  }, [q, cat, kpi, linhas, ord]);
  const POR_PAG = 10;
  const nPags = Math.max(1, Math.ceil(filtradas.length / POR_PAG));
  const pagina = Math.min(pag, nPags);
  const ordenar = (k) => { setOrd((o) => ({ k, dir: o.k === k && o.dir === "asc" ? "desc" : "asc" })); setPag(1); };
  const COLS = [
    { key: "name", label: "Receita", sortable: true },
    { key: "cat", label: "Categoria", sortable: true },
    { key: "qtd", label: "Quantidade", align: "right", mono: true, sortable: true },
    { key: "total", label: "Custo total", align: "right", mono: true, sortable: true },
    { key: "unit", label: "Custo unitário", align: "right", mono: true, sortable: true },
    { key: "venda", label: "Venda", align: "right", mono: true, sortable: true },
    { key: "margem", label: "Margem", align: "right", sortable: true },
  ];

  const magra = linhas.filter(({ c }) => c.margem < 45).length;
  const perda = linhas.filter(({ r }) => r.waste >= 8).length;
  const custoMed = linhas.length ? linhas.reduce((s, l) => s + l.c.unit, 0) / linhas.length : 0;
  const toggle = (id) => setSel((s) => s.includes(id) ? s.filter((x) => x !== id) : [...s, id]);
  const aberta = linhas.find(({ r }) => r.id === openId);

  // ── Ações ──
  const criarReceita = ({ nome, cat: c2, sub, un, qtd, clone }) => {
    const src = clone ? recipes.find((r) => r.id === clone) : null;
    const id = Math.max(0, ...recipes.map((r) => r.id)) + 1;
    const nova = {
      id, name: nome, sku: "MFG-" + String(id).padStart(4, "0"), cat: c2, sub, qtd, un,
      waste: src ? src.waste : 0, extra: src ? src.extra : 0, custoTipo: src ? src.custoTipo : "fixo",
      venda: src ? src.venda : 0, atualizado: "agora", produto: "—",
      grupos: src ? JSON.parse(JSON.stringify(src.grupos)) : [],
    };
    setRecipes((rs) => [...rs, nova]);
    setNovaOpen(false);
    setTela({ tipo: "receita-edit", id }, { botao: "Nova receita" });
  };
  const salvarReceita = (r) => {
    setRecipes((rs) => rs.map((x) => x.id === r.id ? { ...r, atualizado: "agora" } : x));
    setTela(null);
    aviso("Receita salva · custo recalculado");
  };
  const excluirReceita = (id) => {
    setRecipes((rs) => rs.filter((r) => r.id !== id));
    setTela(null); setOpenId(null); setConfirma(null);
    aviso("Receita excluída");
  };
  const salvarOP = (op0) => {
    // ao finalizar, congela o custo do dia (histórico) — leitura ao vivo só vale pro rascunho
    const c = MFG.consumoOP({ ...op0, custoSnap: null }, recipes);
    const op = op0.final ? { ...op0, custoSnap: op0.custoSnap != null ? op0.custoSnap : Number(c.vivo.toFixed(2)) } : { ...op0, custoSnap: null };
    setProducoes((ps) => op.id ? ps.map((x) => x.id === op.id ? op : x) : [...ps, { ...op, id: Math.max(0, ...ps.map((p) => p.id)) + 1 }]);
    setTela(null);
    aviso(op.final ? "Produção finalizada · estoque movimentado" : "Rascunho salvo");
  };
  const atualizarPrecos = () => {
    setRecipes((rs) => rs.map((r) => sel.includes(r.id) ? { ...r, venda: Number((custos(r).unit * 2).toFixed(2)), atualizado: "agora" } : r));
    aviso(sel.length + " preço(s) de venda atualizados a partir do custo");
    setSel([]);
  };

  // ── Telas full (editor / form) tomam o corpo do módulo ──
  if (tela && tela.tipo === "receita-edit") {
    const r = recipes.find((x) => x.id === tela.id);
    const Editor = irmao("MfgIngredientesEditor");
    if (r) return (
      <div className="mfg-root" ref={telaRef}>
        {Editor
          ? <Editor recipe={r} settings={settings} perms={perms}
              onSave={salvarReceita} onCancel={() => setTela(null)} onDelete={() => setConfirma(r)} />
          : <Aguardando o_que="o editor de ingredientes" />}
      </div>
    );
  }
  if (tela && tela.tipo === "op-form") {
    const Form = irmao("MfgProducaoForm");
    return (
      <div className="mfg-root" ref={telaRef}>
        {Form
          ? <Form recipes={recipes} producoes={producoes} settings={settings} perms={perms}
              editing={tela.id ? producoes.find((p) => p.id === tela.id) : null}
              onSave={salvarOP} onCancel={() => setTela(null)} />
          : <Aguardando o_que="o formulário de produção" />}
      </div>
    );
  }

  const Insumos = irmao("MfgInsumosView");
  const ProducaoView = irmao("MfgProducaoView");
  const ProducaoDrawer = irmao("MfgProducaoDrawer");
  const Relatorio = irmao("MfgRelatorio");
  const Config = irmao("MfgConfig");
  const NovaReceita = irmao("MfgNovaReceita");
  const FichaPrint = irmao("MfgFichaPrint");

  const rascunhos = producoes.filter((p) => !p.final).length;
  const abasVisiveis = ABAS.filter((a) => a.id !== "producao" || perms.prod).map((a) => ({
    key: a.id, label: a.l,
    count: a.id === "receitas" ? recipes.length
      : a.id === "producao" ? producoes.length + (rascunhos ? " · " + rascunhos + " rasc." : "")
      : undefined,
  }));

  return (
    <div className="mfg-root" data-screen-label={"Fabricação · " + (ABAS.find((a) => a.id === aba) || {}).l}>
      <PageHeader
        // D-RET-01 decidida por [W] em 2026-09-26 (comentário no #7991): espelho do vivo —
        // Recipes.tsx → "Manufacturing" + "custo recalculado"; Index.tsx (Ordens) → "Produção", sem a
        // 3ª parte (lá o custo é o final_total GRAVADO, não recalculado). A frase aparece INTEIRA: o
        // corte de 56ch do PageHeader do DS é anulado em manufacturing-page.css (contorno na pauta).
        // Substitui a opção A do Felipe (25/09), que tirava a frase de todas as abas.
        // Título fora da aba Ordens: "Fabricação", pedido da Maiara (dona das 5 telas, design-lock) em
        // 2026-09-28, para bater com o item do menu lateral. Vai ao [W] no PR — revê a parte do título
        // na D-RET-01. As telas vivas (Recipes/Insumos/Report/Settings.tsx) seguem "Manufacturing".
        title={aba === "producao" ? "Produção" : "Fabricação"}
        stats={[
          { value: recipes.length, label: recipes.length === 1 ? "receita" : "receitas" },
          { value: producoes.length, label: "ordens de produção" + (aba === "producao" ? "" : " · custo recalculado pelo preço atual dos ingredientes") },
        ]}
        actions={<>
          {aba === "receitas" && perms.criar && <Button variant="primary" size="sm" onClick={() => setNovaOpen(true)}><I.plus size={13} /> Nova receita</Button>}
          {aba === "producao" && perms.criar && <Button variant="primary" size="sm" onClick={() => setTela({ tipo: "op-form", id: null })}><I.plus size={13} /> Nova produção</Button>}
        </>}
      />

      <TabBar tabs={abasVisiveis} active={aba} onChange={setAba} inset={20} />

      {aba === "receitas" && (
        <>
          <div className="mfg-kpis">
            <KpiCard label="Custo médio / unidade" value={fmt(custoMed)} description={"média das " + recipes.length + " receitas"} />
            <KpiCard variant="filter" label="Margem abaixo de 45%" value={magra} sub="preço de venda desatualizado"
              icon={<I.scale size={17} />} tone="amber" selected={kpi === "margem"}
              onClick={() => setKpi(kpi === "margem" ? null : "margem")} />
            <KpiCard variant="filter" label="Desperdício ≥ 8%" value={perda} sub="revisar plotagem / encaixe"
              icon={<I.scissor size={17} />} tone="amber" selected={kpi === "custo"}
              onClick={() => setKpi(kpi === "custo" ? null : "custo")} />
            <KpiCard label="Produção do mês" value={producoes.filter((p) => p.final).length}
              description={rascunhos + " rascunho" + (rascunhos === 1 ? "" : "s") + " em aberto"} />
          </div>

          <Toolbar tone="transparent"
            left={<>
              <div className="mfg-s-host">
                <SearchInput placeholder="Buscar receita por nome, SKU, categoria…" value={q} onChange={(e) => { setQ(e.target.value); setPag(1); }} />
              </div>
              <Segmented size="sm" ariaLabel="Categoria" value={cat} onChange={(v) => { setCat(v); setPag(1); }}
                options={CATS.map((c) => ({ value: c, label: c }))} />
            </>} />

          <div className="mfg-tablewrap">
            {filtradas.length > 0 && (
              <div className="mfg-grid">
                <DataGrid caption="Receitas" totalLabel="receitas" columns={COLS}
                  rows={filtradas.map(({ r, c }) => ({
                    id: r.id,
                    cells: {
                      name: { primary: r.name, sub: r.sku + " · " + r.grupos.reduce((s, g) => s + g.itens.length, 0) + " ingredientes" },
                      cat: r.cat + " / " + r.sub,
                      qtd: (r.subUn ? num(c.qtdLiq * r.subFator, 2) : num(c.qtdLiq, 2)) + " " + (r.subUn || r.un),
                      total: fmt(c.total), unit: fmt(c.unit), venda: fmt(r.venda),
                      margem: <StatusBadge tone={c.margem >= 55 ? "success" : c.margem >= 45 ? "warning" : "danger"} label={num(c.margem, 0) + "%"} />,
                    },
                  }))}
                  sortKey={ord.k} sortDir={ord.dir} onSort={ordenar}
                  selectable selectedIds={sel} onToggleRow={(id) => toggle(id)}
                  onToggleAll={(on) => setSel(on ? filtradas.map(({ r }) => r.id) : [])}
                  onRowClick={(row) => setOpenId(row.id)}
                  page={pagina} onPageChange={setPag} pageSize={POR_PAG} pageSizeOptions={[POR_PAG]} />
              </div>
            )}
            {filtradas.length === 0 && (
              <EmptyState variant="no-results" icon={<I.search size={18} />}
                title="Nenhuma receita encontrada"
                description="Ajuste a busca, troque a categoria ou limpe o filtro de KPI."
                action={<Button size="sm" onClick={() => { setQ(""); setCat("Todas"); setKpi(null); }}>Limpar filtros</Button>} />
            )}
          </div>

          {sel.length > 0 && (
            <BulkBar
              count={sel.length}
              label={"receita" + (sel.length > 1 ? "s" : "") + " selecionada" + (sel.length > 1 ? "s" : "")}
              actions={[
                { label: "Imprimir fichas", icon: <I.print size={13} />, onClick: () => setImprimir({ itens: linhas.filter(({ r }) => sel.includes(r.id)), semCusto: false }) },
                ...(settings.permitirPreco && perms.editar ? [{ label: "Atualizar preço de venda do produto", onClick: atualizarPrecos }] : []),
              ]}
              onClose={() => setSel([])} />
          )}
        </>
      )}

      {aba === "insumos" && (Insumos
        ? <Insumos recipes={recipes} onAbrirReceita={(id) => { setAba("receitas"); setOpenId(id); }} />
        : <Aguardando o_que="a aba Insumos" />)}

      {aba === "producao" && perms.prod && (ProducaoView
        ? <ProducaoView producoes={producoes} recipes={recipes} perms={perms}
            onNew={() => setTela({ tipo: "op-form", id: null })} onOpen={(id) => setOpAberta(id)} />
        : <Aguardando o_que="a aba Ordens de produção" />)}

      {aba === "relatorio" && (Relatorio
        ? <Relatorio producoes={producoes} recipes={recipes} />
        : <Aguardando o_que="a aba Relatório" />)}

      {aba === "config" && (Config
        ? <Config settings={settings} setSettings={(s) => { setSettings(s); aviso("Configurações atualizadas"); }} perms={perms} setPerms={setPerms} />
        : <Aguardando o_que="a aba Configurações" />)}

      {aberta && <RecipeDrawer r={aberta.r} c={aberta.c} perms={perms} settings={settings}
        onClose={() => setOpenId(null)}
        onEdit={() => { setOpenId(null); setTela({ tipo: "receita-edit", id: aberta.r.id }, { tabela: "Receitas", texto: aberta.r.name }); }}
        onImprimir={(semCusto) => { setOpenId(null); setImprimir({ itens: [aberta], semCusto }); }}
        onProduzir={() => { setOpenId(null); setTela({ tipo: "op-form", id: null }, { tabela: "Receitas", texto: aberta.r.name }); }} />}

      <Modal open={!!confirma} onClose={() => setConfirma(null)} title="Excluir receita" width={400}
        footer={<>
          <Button onClick={() => setConfirma(null)}>Cancelar</Button>
          <Button variant="danger" onClick={() => excluirReceita(confirma.id)}>Excluir receita</Button>
        </>}>
        {confirma && <>
          <p style={{ margin: "0 0 8px" }}>Excluir <b>{confirma.name}</b> apaga a ficha técnica e os {confirma.grupos.reduce((s, g) => s + g.itens.length, 0)} ingredientes. Ordens de produção já lançadas continuam com o custo registrado.</p>
          <p style={{ margin: 0 }}>Não dá pra desfazer.</p>
        </>}
      </Modal>

      {/* [TELA] um overlay por vez: Drawer e PresenterMode escutam Esc no document; com os dois abertos o Esc fecha o de baixo. Abrir a impressão fecha o drawer. */}
      {imprimir && FichaPrint && <FichaPrint itens={imprimir.itens} semCusto={imprimir.semCusto} onDone={() => setImprimir(null)} />}

      {opAberta && ProducaoDrawer && <ProducaoDrawer op={producoes.find((p) => p.id === opAberta)} recipes={recipes}
        onClose={() => setOpAberta(null)} onEdit={() => { const id = opAberta; const o = producoes.find((p) => p.id === id); setOpAberta(null); setTela({ tipo: "op-form", id }, o ? { tabela: "Ordens de produção", texto: o.ref } : null); }} />}

      {novaOpen && NovaReceita && <NovaReceita recipes={recipes} onClose={() => setNovaOpen(false)} onCreate={criarReceita} />}

      {/* [TELA] C-02: o Toast do DS é <span> sem role (Toast.jsx L12, fonte viva). A região de anúncio
          é o div de posicionamento da tela, montado sempre para o leitor já conhecê-la quando o texto entra.
          Só atributo: o Toast segue intacto. Proposta de role="status" no Toast registrada na pauta. */}
      <div role="status" aria-live="polite" style={{ position: "fixed", left: "50%", bottom: 24, transform: "translateX(-50%)", zIndex: 80 }}>
        {toast && <Toast tone="ok" icon={<I.check size={14} />}>{toast}</Toast>}
      </div>
    </div>
  );
}

function RecipeDrawer({ r, c, perms, settings, onClose, onEdit, onProduzir, onImprimir }) {
  const { Drawer, DrawerSection, Button } = ds();
  const { bySku, multDe, fmt, num } = window.MFG;
  const I = window.I;
  return (
    <Drawer
      open
      onClose={onClose}
      title={r.name}
      subtitle={r.sku + " · " + r.cat + " / " + r.sub + " · rende " + num(c.qtdLiq, 2) + " " + r.un + " · atualizado " + r.atualizado}
      width={680}
      footer={<>
        <Button onClick={onClose}>Fechar</Button>
        <Button onClick={() => onImprimir(false)}><I.print size={13} /> Ficha com custo</Button>
        <Button onClick={() => onImprimir(true)}>Via de produção</Button>
        {/* [TELA] QA 2026-09-29: "Produzir" abre Nova produção, que exige mfg.prod E criar. Só com
            mfg.prod o botão levava a um formulário com Salvar desativado — a regra da tela é esconder. */}
        {perms.prod && perms.criar && <Button onClick={onProduzir}>Produzir</Button>}
        {perms.editar && <Button variant="primary" onClick={onEdit}><I.pencil size={13} /> Editar ingredientes</Button>}
      </>}>
      <div style={{ padding: "0 18px 8px" }}>
        {r.grupos.map((g, gi) => {
          const sub = g.itens.reduce((a, i) => { const p = bySku(i.sku); return a + i.q * (p ? p.c : 0) * multDe(i); }, 0);
          return (
            <div className="mfg-grp" key={g.g + gi}>
              <div className="mfg-grp-h"><b>{g.g}</b><span className="v">{fmt(sub)}</span></div>
              {g.itens.map((i, ii) => {
                const p = bySku(i.sku) || { n: i.sku, u: "", c: 0 };
                return (
                  <div className="mfg-ing" key={i.sku + ii}>
                    <span className="n">{p.n}<small>{i.sku}{multDe(i) > 1 ? " · " + num(i.q * multDe(i), 2) + " " + p.u : ""}</small></span>
                    <span className="m">{num(i.q, i.q < 1 ? 3 : 2)} {i.subUn || p.u}</span>
                    <span className="m">{fmt(p.c)}</span>
                    <span className="m tot">{fmt(i.q * p.c * multDe(i))}</span>
                  </div>
                );
              })}
              {g.itens.length === 0 && <p className="mfg-pick-empty">Grupo sem ingredientes.</p>}
            </div>
          );
        })}
      </div>

      <DrawerSection title="Custo">
        <dl className="mfg-tot">
          <dt>Ingredientes</dt><dd>{fmt(c.ing)}</dd>
          <dt>Custo extra ({r.custoTipo === "percentual" ? r.extra + "% sobre ingredientes" : r.custoTipo === "unidade" ? window.MFG.fmt(r.extra) + " por " + r.un + " produzido" : "valor fixo"})</dt><dd>{fmt(c.extra)}</dd>
          <dt>Desperdício</dt><dd>{num(r.waste, 0)}% · rende {num(c.qtdLiq, 2)} de {num(r.qtd, 2)} {r.un}</dd>
          {r.subUn && <><dt>Sub-unidade de saída</dt><dd>{num(c.qtdLiq * r.subFator, 2)} {r.subUn}</dd></>}
          <hr />
          <dt style={{ fontWeight: 600, color: "var(--text)" }}>Custo por {r.un}</dt>
          <dd style={{ fontSize: 15, fontWeight: 600, color: "var(--accent)" }}>{fmt(c.unit)}</dd>
          <dt>Preço de venda atual</dt><dd>{fmt(r.venda)}</dd>
          <dt>Margem</dt><dd>{num(c.margem, 1)}%</dd>
        </dl>
        <p className="mfg-note">O custo é recalculado a cada leitura a partir do preço atual dos ingredientes — a receita não guarda valor congelado. Uma compra de insumo salva em <button className="mfg-link" onClick={() => window.__go && window.__go("compras")}>Compras</button> muda este número.</p>
      </DrawerSection>
    </Drawer>
  );
}

window.ManufacturingPage = ManufacturingPage;
})();
