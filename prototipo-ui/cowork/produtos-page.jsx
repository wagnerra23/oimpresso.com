// produtos-page.jsx — Consulta de Produtos (`/products/unificado`)
// Porte do main lido em 2026-08-25: Pages/Produto/Unificado/Index.tsx + _components/
// (catalogo.ts · Colunas.tsx · KpiFiltros.tsx · FiltroTrigger.tsx · BulkBar.tsx ·
//  Disponibilidade.tsx · MiniaturaProduto.tsx · Mono.tsx · Observacao.tsx).
//
// Árvore do vivo: PageHeader → abas por TIPO → KPI-filtros → toolbar em UMA linha
// (filtros → ordem → limpar → contagem → busca) → grid sem raio com thead sticky →
// rodapé de paginação → drawer de detalhe. BulkBar flutua quando há seleção.
//
// Regra que governa tudo: custo e margem são AUTORIZAÇÃO, não preferência — coluna é
// MONTADA ou NÃO MONTADA, nunca escondida por CSS; ausência nunca imprime 0/—.
// As 4 sub-telas (Categorias · Insumos · Tabelas · Histórico) saíram desta tela por
// decisão do handoff V6 #11 — no protótipo elas seguem nas rotas prod-* da sidebar.
const { useState: useStateP, useMemo: useMemoP, useEffect: useEffectP, useRef: useRefP } = React;

const PISO_MARGEM = 0.42;   // parâmetro de negócio (vive no servidor no vivo)
const DIAS_PARADO = 60;
const CHAVE_PREFS = "oi.produtos.prefs.v1";

const brlP = (n) => "R$ " + n.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const pctP = (n) => Math.round(n * 100) + "%";
const numP = (n) => Number.isInteger(n) ? String(n) : n.toLocaleString("pt-BR", { maximumFractionDigits: 3 });
const parseBRp = (s) => typeof s === "number" ? s : parseFloat(String(s || "").replace(/[^\d,]/g, "").replace(",", ".")) || 0;

const TIPO_LABEL = { produto: "PROD", servico: "SERV", materia: "M-PRIMA", kit: "KIT" };
const ABAS_CATALOGO = [
["todos", "Todos"], ["produtos", "Produtos"], ["servicos", "Serviços"],
["materia", "Matéria-prima"], ["kits", "Kits"], ["inativos", "Inativos"]];

const TIPO_OPCOES = [
{ value: "produto", label: "Produto" }, { value: "servico", label: "Serviço" },
{ value: "materia", label: "Matéria-prima" }, { value: "kit", label: "Kit" }];

const ESTOQUE_OPCOES = [
{ value: "em", label: "Com saldo" }, { value: "baixo", label: "Abaixo do mínimo" },
{ value: "sem", label: "Sem saldo" }, { value: "nao", label: "Não estocável" }];

const MARGEM_OPCOES = [{ value: "sob_piso", label: "Sob o piso" }, { value: "ok", label: "Acima do piso" }];
const ORDEM_OPCOES = [
{ key: "cod", label: "Código" }, { key: "prod", label: "Produto" }, { key: "est", label: "Disponível" },
{ key: "preco", label: "Preço", preco: true }, { key: "margem", label: "Margem", custo: true, preco: true }];

const COLUNAS_OCULTAVEIS = [
{ key: "tipo", label: "Tipo" }, { key: "custo", label: "Custo" },
{ key: "preco", label: "Preço de venda" }, { key: "margem", label: "Margem" }];

const POR_PAGINA_OPCOES = [25, 50, 100];

/* ─── Vocabulário (catalogo.ts) ──────────────────────────────────────── */

function estadoEstoque(r) {
  if (r.stockQty === null) return { chave: "nao", label: "Não estocável", rel: null, rank: -1 };
  if (r.stockQty === 0) return { chave: "sem", label: "Sem saldo", rel: "0", rank: 0 };
  if (r.minimo !== null && r.stockQty <= r.minimo) return { chave: "baixo", label: "Abaixo do mínimo", rel: numP(r.stockQty), rank: 1 };
  return { chave: "em", label: "Disponível", rel: numP(r.stockQty), rank: 2 };
}
const margemFrac = (r) => r.margin !== undefined ? r.margin :
r.cost === undefined || r.price === undefined || r.price <= 0 ? undefined : (r.price - r.cost) / r.price;
const sobOPiso = (r) => {const m = margemFrac(r);return m !== undefined && m < PISO_MARGEM;};
const linhaUrgente = (r) => {const c = estadoEstoque(r).chave;return c === "sem" || c === "baixo" || sobOPiso(r);};
const gradeComFuro = (g) => !!g && g.total > 0 && g.com < g.total;
const marcadorGrade = (g) => !g || g.total === 0 ? "" : `${g.com} de ${g.total} com saldo`;

// Permissões — fail-closed: ausência de permissão declarada nunca vira permissão.
const permsDe = (papel) =>
papel === "administrador" ? { custo: true, preco: true, composicao: true, inativar: true } :
papel === "gerente" ? { custo: true, preco: true, composicao: true, inativar: false } :
papel === "balcao" ? { custo: false, preco: true, composicao: false, inativar: false } :
{ custo: false, preco: false, composicao: false, inativar: false };

/* ─── Linhas (o controller do vivo NÃO emite a chave que o perfil não pode ver) ─── */

function linhasCatalogo(perm) {
  const cad = PROD_DATA.PROD_CADASTRO, met = PROD_DATA.PROD_METRICS;
  const doProduto = PROD_DATA.PROD_LIST.map((p, i) => {
    const c = cad[p.id] || {}, m = met[p.id] || {};
    const tipo = p.type === "servico" ? "servico" : p.type === "composicao" ? "kit" :
    p.category === "Serviços" || p.unit === "h" ? "servico" : p.category === "Composições" ? "kit" : "produto";
    const estocavel = tipo === "produto";
    const vars = p.variants || [];
    const price = parseBRp(p.price);
    const row = {
      id: p.id, codigo: 1000 + i + 1, referencia: vars[0]?.sku || null, name: p.name, tipo,
      cat_label: p.category, unit: p.unit === "milheiro" ? "milh." : p.unit,
      stockQty: estocavel ? PROD_DATA.prodStock(p) : null,
      minimo: c.min === undefined ? null : c.min,
      parado: m.uses30 === 0, ultimaVenda: ULTIMA_VENDA[p.id] || null, active: p.active,
      bomCount: (p.bom || []).length, bom: p.bom || [], variants: vars, lead: p.lead,
      marca: p.brand || null, oem: p.oem || [], uses30: m.uses30
    };
    if (perm.preco) row.price = price;
    if (perm.custo && m.cost !== undefined) row.cost = m.cost;
    if (c.locais) row.locais = c.locais;
    if (c.obs) row.obs = c.obs;
    if (vars.length > 1) row.grade = { com: vars.filter((v) => (v.stock || 0) > 0).length, total: vars.length };
    return row;
  });
  // Insumos entram como MATÉRIA-PRIMA (é o que são no cadastro) — sem preço de venda,
  // então a célula de preço simplesmente não é construída pra eles.
  const doInsumo = PROD_INSUMOS.map((ins, i) => {
    const row = {
      id: "I-" + ins.id, codigo: 2000 + i + 1, referencia: null, name: ins.name, tipo: "materia",
      cat_label: "Insumos", unit: ins.unit, stockQty: ins.stock, minimo: ins.min,
      parado: false, ultimaVenda: null, active: true, bomCount: 0, bom: [], variants: [],
      marca: ins.fornecedor, oem: [], uses30: null, insumo: true
    };
    if (perm.custo) row.cost = ins.cost;
    return row;
  });
  return doProduto.concat(doInsumo);
}

// Última venda por item — declarada (o vivo lê do banco).
const ULTIMA_VENDA = {
  "P-001": "25/04/2026", "P-002": "28/04/2026", "P-003": "25/04/2026", "P-004": "22/04/2026",
  "P-005": "28/04/2026", "P-006": "27/04/2026", "P-007": "23/04/2026", "P-010": "23/04/2026",
  "P-012": "26/04/2026", "P-014": "27/04/2026", "S-001": "24/04/2026", "S-003": "22/04/2026",
  "K-001": "24/04/2026"
};

// Matéria-prima do catálogo (mesma fonte dos insumos · BOM), com nível mínimo declarado.
const PROD_INSUMOS = [
{ id: 1, name: "Papel couché 300g", unit: "folha", cost: 0.42, stock: 12400, min: 4000, fornecedor: "Suzano Papel" },
{ id: 2, name: "Papel couché 150g", unit: "folha", cost: 0.28, stock: 18600, min: 4000, fornecedor: "Suzano Papel" },
{ id: 3, name: "Couché 250g", unit: "folha", cost: 0.36, stock: 9200, min: 3000, fornecedor: "Suzano Papel" },
{ id: 4, name: "Lona 440g", unit: "m²", cost: 12.80, stock: 640, min: 200, fornecedor: "Sansuy" },
{ id: 5, name: "Vinil adesivo", unit: "m²", cost: 18.50, stock: 320, min: 150, fornecedor: "Avery Brasil" },
{ id: 6, name: "Tinta CMYK", unit: "L", cost: 148.00, stock: 24, min: 12, fornecedor: "Epson Distr." },
{ id: 7, name: "Tinta solvente", unit: "L", cost: 96.00, stock: 38, min: 20, fornecedor: "Roland BR" },
{ id: 8, name: "Verniz UV", unit: "L", cost: 210.00, stock: 6, min: 10, fornecedor: null },
{ id: 9, name: "PVC expandido 3mm", unit: "chapa", cost: 84.00, stock: 42, min: 20, fornecedor: "Sansuy" },
{ id: 10, name: "Kraft 120g", unit: "folha", cost: 0.31, stock: 7400, min: 2000, fornecedor: "Klabin" },
{ id: 11, name: "Ilhós", unit: "un", cost: 0.18, stock: 5200, min: 1000, fornecedor: "Ferragem União" },
{ id: 12, name: "Cola PUR", unit: "kg", cost: 62.00, stock: 0, min: 8, fornecedor: "Henkel" },
{ id: 13, name: "Óleo motor 5W30", unit: "L", cost: 34.00, stock: 96, min: 40, fornecedor: "Ipiranga" },
{ id: 14, name: "Fluido freio DOT4", unit: "L", cost: 28.00, stock: 22, min: 24, fornecedor: "Bosch" }];


/* ─── Peças ──────────────────────────────────────────────────────────── */

function Disponibilidade({ row, densa }) {
  const est = estadoEstoque(row);
  const pilula =
  <span className={"pd-est " + est.chave} title={est.rel === null ? est.label : `${est.label} · saldo ${est.rel}`}>
      {est.chave !== "nao" && <span className="pd-est-dot" aria-hidden="true" />}
      {est.label}
      {est.rel !== null && <span className="pd-est-n">{est.rel}{row.unit ? ` ${row.unit}` : ""}</span>}
    </span>;

  const locais = row.locais || [];
  if (locais.length < 2 || densa) return pilula;
  const zerados = locais.filter((l) => l.qtd === 0);
  const alerta = zerados.length > 0 && locais.some((l) => l.qtd > 0) ?
  `0 na ${zerados.map((l) => l.nome).join(" e ")} — saldo em outro local.` : "";
  return (
    <span className="pd-est-wrap">
      {pilula}
      <span className="pd-est-locais" tabIndex={0} aria-label={`Saldo por local de ${row.name}`}>
        {locais.length} locais
        <span className="pd-pop" role="tooltip">
          <b>Saldo por local</b>
          {locais.map((l) =>
          <span className="pd-pop-l" key={l.nome}>
              <span>{l.nome}</span>
              <span className={"pd-pop-n" + (l.qtd === 0 ? " zero" : "")}>{numP(l.qtd)} {row.unit}</span>
            </span>
          )}
          {alerta && <span className="pd-pop-alerta">{alerta}</span>}
        </span>
      </span>
    </span>);

}

function Miniatura({ nome, tamanho = 30 }) {
  return (
    <span className="pd-mini" style={{ width: tamanho, height: tamanho }}
    role="img" aria-label="Produto sem imagem" title="Sem imagem">
      <I.product size={Math.round(tamanho * 0.5)} />
    </span>);

}

function KpiFiltros({ kpis, ativo, onToggle, perm }) {
  const cards = [
  { key: "min", label: "Abaixo do mínimo", sub: "repor", tone: "warn", valor: kpis.min },
  { key: "zero", label: "Sem saldo", sub: "bloqueado", tone: "danger", valor: kpis.zero }];

  if (perm.custo) cards.push({ key: "parado", label: `Sem venda ${DIAS_PARADO}d`, sub: "sem giro", tone: "accent", valor: kpis.parado });
  if (perm.custo && perm.preco) cards.push({ key: "margem", label: "Margem baixa", sub: "sob o piso", tone: "warn", valor: kpis.margem });
  return (
    <div className="pd-kpis">
      {cards.map((c) => {
        const on = ativo === c.key;
        return (
          <button key={c.key} type="button" aria-pressed={on}
          title={`Recortar por ${c.label} (${c.sub})`}
          className={"pd-kpi" + (on ? " on" : "")}
          onClick={() => onToggle(on ? null : c.key)}>
            <span className={"pd-kpi-plate " + c.tone}>
              {c.key === "min" ? <I.alert size={16} /> : c.key === "zero" ? <I.close size={16} /> : c.key === "parado" ? <I.clock size={16} /> : <I.percent size={16} />}
            </span>
            <span className="pd-kpi-txt">
              <span className="pd-kpi-l">{c.label}</span>
              <span className="pd-kpi-v">{c.valor.toLocaleString("pt-BR")}</span>
              <span className="pd-kpi-s">{c.sub}</span>
            </span>
          </button>);

      })}
    </div>);

}

function FiltroTrigger({ label, value, options, onChange }) {
  const [aberto, setAberto] = useStateP(false);
  const ref = useRefP(null);
  useEffectP(() => {
    if (!aberto) return;
    const onClick = (e) => {if (ref.current && !ref.current.contains(e.target)) setAberto(false);};
    const onKey = (e) => {if (e.key === "Escape") setAberto(false);};
    document.addEventListener("mousedown", onClick);
    document.addEventListener("keydown", onKey);
    return () => {document.removeEventListener("mousedown", onClick);document.removeEventListener("keydown", onKey);};
  }, [aberto]);
  const sel = options.find((o) => o.value === value);
  return (
    <div className="pd-ft" ref={ref}>
      <button type="button" aria-haspopup="listbox" aria-expanded={aberto}
      className={"pd-ft-b" + (sel ? " on" : "")} onClick={() => setAberto((v) => !v)}>
        <span>{sel ? `${label}: ${sel.label}` : label}</span>
        <I.chevDown size={11} />
      </button>
      {aberto &&
      <div className="pd-ft-pop" role="listbox">
          {options.length === 0 && <p className="pd-ft-vazio">Nada cadastrado ainda.</p>}
          {sel && <button type="button" className="pd-ft-limpar" onClick={() => {onChange("");setAberto(false);}}>Limpar</button>}
          {options.map((o) =>
        <button key={o.value} type="button" role="option" aria-selected={o.value === value}
        className={"pd-ft-o" + (o.value === value ? " on" : "")}
        onClick={() => {onChange(o.value === value ? "" : o.value);setAberto(false);}}>
              <span>{o.label}</span>
              {o.value === value && <I.check size={11} />}
            </button>
        )}
        </div>
      }
    </div>);

}

function MenuAncorado({ label, icon, align = "end", children }) {
  const [aberto, setAberto] = useStateP(false);
  const ref = useRefP(null);
  useEffectP(() => {
    if (!aberto) return;
    const onClick = (e) => {if (ref.current && !ref.current.contains(e.target)) setAberto(false);};
    const onKey = (e) => {if (e.key === "Escape") setAberto(false);};
    document.addEventListener("mousedown", onClick);
    document.addEventListener("keydown", onKey);
    return () => {document.removeEventListener("mousedown", onClick);document.removeEventListener("keydown", onKey);};
  }, [aberto]);
  return (
    <div className="pd-menu" ref={ref}>
      <button type="button" className="pd-menu-b" aria-label={label} aria-expanded={aberto} title={label}
      onClick={() => setAberto((v) => !v)}>
        {icon}{label && <span className="pd-menu-lbl">{label}</span>}
      </button>
      {aberto && <div className={"pd-menu-pop " + align} role="menu">{children(() => setAberto(false))}</div>}
    </div>);

}

function BulkBar({ total, foraDaPagina, onInativar, onLimpar }) {
  if (total === 0) return null;
  return (
    <div className="pd-bulk-wrap">
      <div className="pd-bulk" role="status" aria-live="polite">
        <span className="pd-bulk-n">
          <b>{total.toLocaleString("pt-BR")}</b> {total === 1 ? "item selecionado" : "itens selecionados"}
          {foraDaPagina > 0 && <span className="pd-bulk-fora"> · {foraDaPagina} fora desta página</span>}
        </span>
        {onInativar && <button className="pd-bulk-acao" onClick={onInativar}>Inativar</button>}
        <button className="pd-bulk-x" onClick={onLimpar} aria-label="Limpar seleção"><I.close size={14} /></button>
      </div>
    </div>);

}

function Paleta({ aberta, onClose, onAba, onKpi, onLimpar, onDensa, recentes, onAbrir, perm }) {
  const [q, setQ] = useStateP("");
  const ref = useRefP(null);
  useEffectP(() => {if (aberta) {setQ("");setTimeout(() => ref.current?.focus(), 20);}}, [aberta]);
  if (!aberta) return null;
  const casa = (s) => !q.trim() || s.toLowerCase().includes(q.trim().toLowerCase());
  const recortes = [
  ["Abaixo do mínimo", () => onKpi("min")],
  ["Sem saldo", () => onKpi("zero")],
  ...(perm.custo ? [[`Sem venda ${DIAS_PARADO}d`, () => onKpi("parado")]] : []),
  ...(perm.custo && perm.preco ? [["Margem baixa", () => onKpi("margem")]] : []),
  ["Limpar recorte", onLimpar]];

  const acoes = [
  ["Novo produto", () => {}], ["Importar planilha", () => {}],
  ["Exportar planilha", () => {}], ["Linhas confortáveis", onDensa]];

  const Grupo = ({ titulo, itens }) => {
    const vis = itens.filter(([l]) => casa(l));
    if (vis.length === 0) return null;
    return <>
      <div className="pd-paleta-grupo">{titulo}</div>
      {vis.map(([l, fn, hint]) =>
      <button key={titulo + l} onClick={() => {fn();onClose();}}>
          <span>{l}</span>{hint && <span className="pd-paleta-hint">{hint}</span>}
        </button>
      )}
    </>;
  };
  return (
    <div className="pd-paleta-back" onClick={onClose}>
      <div className="pd-paleta" role="dialog" aria-modal="true" aria-label="Paleta de comandos" onClick={(e) => e.stopPropagation()}>
        <input ref={ref} value={q} onChange={(e) => setQ(e.target.value)}
        placeholder="Ir para item recente, aba, recorte ou ação..." aria-label="Comando" />
        <div className="pd-paleta-lista">
          <Grupo titulo="Recentes" itens={recentes.map((r) => [r.nome, () => onAbrir(r.id), String(r.codigo)])} />
          <Grupo titulo="Abas" itens={ABAS_CATALOGO.map(([k, l]) => [l, () => onAba(k)])} />
          <Grupo titulo="Recortes" itens={recortes} />
          <Grupo titulo="Ações" itens={acoes} />
        </div>
        <div className="pd-paleta-pe"><kbd>↑↓</kbd> navega · <kbd>↵</kbd> abre · <kbd>esc</kbd> fecha</div>
      </div>
    </div>);

}

function Esqueleto({ colunas }) {
  return (
    <div className="pd-skel" aria-busy="true" aria-label="Carregando produtos">
      {Array.from({ length: 10 }).map((_, r) =>
      <div className="pd-skel-row" key={r}>
          {Array.from({ length: colunas }).map((__, c) => <span key={c} />)}
        </div>
      )}
    </div>);

}

/* ─── Tela ───────────────────────────────────────────────────────────── */

function ProdListPage({ typeFilter = "all", onTypeFilter, estado = "dados", dense = false, papel = "administrador" }) {
  const perm = permsDe(papel);
  const semAcesso = papel === "sem-acesso";

  const [aba, setAba] = useStateP("todos");
  const [kpi, setKpi] = useStateP("");
  const [busca, setBusca] = useStateP("");
  const [fCategoria, setFCategoria] = useStateP("");
  const [fUnidade, setFUnidade] = useStateP("");
  const [fMarca, setFMarca] = useStateP("");
  const [fEstoque, setFEstoque] = useStateP("");
  const [fMargem, setFMargem] = useStateP("");
  const [ordem, setOrdem] = useStateP({ key: "cod", dir: "asc" });
  const [pagina, setPagina] = useStateP(1);
  const [porPagina, setPorPagina] = useStateP(25);
  const [maisFiltros, setMaisFiltros] = useStateP(false);
  const [sel, setSel] = useStateP([]);
  const [ativa, setAtiva] = useStateP(-1);
  const [abertoId, setAbertoId] = useStateP(null);
  const [paleta, setPaleta] = useStateP(false);
  const [confirmar, setConfirmar] = useStateP(false);
  const [inativados, setInativados] = useStateP([]);
  const [toast, setToast] = useStateP(null);
  const buscaRef = useRefP(null);

  // Preferências de APRESENTAÇÃO (densidade + colunas escondidas) — localStorage, não URL.
  const [densa, setDensa] = useStateP(dense);
  const [colsOcultas, setColsOcultas] = useStateP([]);
  const [recentes, setRecentes] = useStateP([]);
  useEffectP(() => {
    try {
      const r = JSON.parse(localStorage.getItem(CHAVE_PREFS) || "{}");
      if (typeof r.densa === "boolean") setDensa(r.densa);
      if (Array.isArray(r.colsOcultas)) setColsOcultas(r.colsOcultas);
      if (Array.isArray(r.recentes)) setRecentes(r.recentes);
    } catch (e) {}
  }, []);
  useEffectP(() => {
    try {localStorage.setItem(CHAVE_PREFS, JSON.stringify({ densa, colsOcultas, recentes }));} catch (e) {}
  }, [densa, colsOcultas, recentes]);
  useEffectP(() => {setDensa(dense);}, [dense]);

  const todas = useMemoP(() => linhasCatalogo(perm).map((r) =>
  inativados.includes(r.id) ? { ...r, active: false } : r
  ), [perm.custo, perm.preco, inativados]);

  // Tipo do topbar contextual do shell continua valendo: ele espelha a aba.
  useEffectP(() => {
    if (typeFilter === "all" && aba !== "todos" && aba !== "inativos") return;
    const mapa = { produto: "produtos", servico: "servicos", composicao: "kits" };
    if (typeFilter !== "all" && mapa[typeFilter] && mapa[typeFilter] !== aba) setAba(mapa[typeFilter]);
  }, [typeFilter]);

  const daAba = (r, k) =>
  k === "todos" ? r.active :
  k === "inativos" ? !r.active :
  r.active && (k === "produtos" ? r.tipo === "produto" : k === "servicos" ? r.tipo === "servico" : k === "materia" ? r.tipo === "materia" : r.tipo === "kit");

  const contagens = useMemoP(() => {
    const o = {};
    ABAS_CATALOGO.forEach(([k]) => {o[k] = todas.filter((r) => daAba(r, k)).length;});
    return o;
  }, [todas]);

  const base = useMemoP(() => todas.filter((r) => daAba(r, aba)), [todas, aba]);

  const kpis = useMemoP(() => {
    const o = {
      min: base.filter((r) => estadoEstoque(r).chave === "baixo").length,
      zero: base.filter((r) => estadoEstoque(r).chave === "sem").length,
      parado: base.filter((r) => r.parado).length,
      total: base.length
    };
    if (perm.custo && perm.preco) o.margem = base.filter((r) => sobOPiso(r)).length;
    return o;
  }, [base, perm]);

  const opcoes = useMemoP(() => ({
    categorias: [...new Set(todas.map((r) => r.cat_label))].map((c) => ({ value: c, label: c })),
    unidades: [...new Set(todas.map((r) => r.unit))].map((u) => ({ value: u, label: u })),
    marcas: [...new Set(todas.map((r) => r.marca).filter(Boolean))].map((m) => ({ value: m, label: m }))
  }), [todas]);

  const recorte = useMemoP(() => {
    let out = base;
    if (kpi === "min") out = out.filter((r) => estadoEstoque(r).chave === "baixo");
    if (kpi === "zero") out = out.filter((r) => estadoEstoque(r).chave === "sem");
    if (kpi === "parado") out = out.filter((r) => r.parado);
    if (kpi === "margem") out = out.filter((r) => sobOPiso(r));
    if (fCategoria) out = out.filter((r) => r.cat_label === fCategoria);
    if (typeFilter !== "all" && aba === "todos") out = out;
    if (fUnidade) out = out.filter((r) => r.unit === fUnidade);
    if (fMarca) out = out.filter((r) => r.marca === fMarca);
    if (fEstoque) out = out.filter((r) => estadoEstoque(r).chave === fEstoque);
    if (fMargem) out = out.filter((r) => fMargem === "sob_piso" ? sobOPiso(r) : margemFrac(r) !== undefined && !sobOPiso(r));
    const q = busca.trim().toLowerCase();
    if (q) out = out.filter((r) =>
    r.name.toLowerCase().includes(q) || String(r.codigo).includes(q) ||
    (r.referencia || "").toLowerCase().includes(q) ||
    r.variants.some((v) => (v.sku || "").toLowerCase().includes(q)) ||
    (r.marca || "").toLowerCase().includes(q) ||
    r.cat_label.toLowerCase().includes(q) || r.oem.join(" ").toLowerCase().includes(q));
    const val = (r) => {
      switch (ordem.key) {
        case "cod":return r.codigo;
        case "prod":return r.name.toLowerCase();
        case "est":return estadoEstoque(r).rank * 1e9 + (r.stockQty ?? 0);
        case "preco":return r.price ?? -1;
        case "margem":return margemFrac(r) ?? -1;
        default:return 0;
      }
    };
    return out.slice().sort((a, b) => {
      const va = val(a), vb = val(b);
      if (va < vb) return ordem.dir === "asc" ? -1 : 1;
      if (va > vb) return ordem.dir === "asc" ? 1 : -1;
      return 0;
    });
  }, [base, kpi, fCategoria, fUnidade, fMarca, fEstoque, fMargem, busca, ordem]);

  // Trocar recorte zera seleção e volta pra página 1 (regra do vivo, num lugar só).
  useEffectP(() => {setSel([]);setPagina(1);setAtiva(-1);}, [aba, kpi, fCategoria, fUnidade, fMarca, fEstoque, fMargem, busca, ordem]);

  const total = recorte.length;
  const paginas = Math.max(1, Math.ceil(total / porPagina));
  const pag = Math.min(Math.max(1, pagina), paginas);
  const linhas = recorte.slice((pag - 1) * porPagina, pag * porPagina);
  const primeira = total === 0 ? 0 : (pag - 1) * porPagina + 1;
  const ultima = Math.min(pag * porPagina, total);

  const mostraTipo = useMemoP(() => new Set(linhas.map((r) => r.tipo)).size > 1, [linhas]);
  const colunasPermitidas = useMemoP(() => [
  { key: "sel", label: "", width: 44 },
  { key: "cod", label: "Código", width: 88, sortable: true },
  { key: "prod", label: "Produto", width: 340, sortable: true, flex: true },
  ...(mostraTipo ? [{ key: "tipo", label: "Tipo", width: 96, sortable: true }] : []),
  { key: "est", label: "Disponível", width: 210, sortable: true },
  ...(perm.custo ? [{ key: "custo", label: "Custo", width: 108, align: "right", sortable: true }] : []),
  ...(perm.preco ? [{ key: "preco", label: "Preço de venda", width: 136, align: "right", sortable: true }] : []),
  ...(perm.custo && perm.preco ? [{ key: "margem", label: "Margem", width: 92, align: "right", sortable: true }] : []),
  { key: "act", label: "", width: 48, align: "right" }],
  [mostraTipo, perm]);
  const colunas = colunasPermitidas.filter((c) => !colsOcultas.includes(c.key));
  const minWidth = colunas.reduce((s, c) => s + c.width, 0);

  const idsPagina = linhas.map((r) => r.id);
  const selNaPagina = idsPagina.filter((id) => sel.includes(id));
  const paginaToda = idsPagina.length > 0 && selNaPagina.length === idsPagina.length;
  const marcarPagina = () => setSel((a) => paginaToda ? a.filter((id) => !idsPagina.includes(id)) : [...new Set([...a, ...idsPagina])]);
  const marcarLinha = (id) => setSel((a) => a.includes(id) ? a.filter((x) => x !== id) : [...a, id]);

  const abrirItem = (id) => {
    setAbertoId(id);
    const l = todas.find((r) => r.id === id);
    if (l) setRecentes((a) => [{ id, nome: l.name, codigo: l.codigo }, ...a.filter((x) => x.id !== id)].slice(0, 8));
  };
  const indiceAberto = abertoId === null ? -1 : linhas.findIndex((r) => r.id === abertoId);
  const vizinho = (d) => {const alvo = linhas[indiceAberto + d];if (alvo) abrirItem(alvo.id);};

  const inativarSelecao = () => {
    const n = sel.length;
    setInativados((a) => [...new Set([...a, ...sel])]);
    setSel([]);setConfirmar(false);setAbertoId(null);
    setToast(`${n} ${n === 1 ? "item inativado" : "itens inativados"}`);
  };
  useEffectP(() => {
    if (!toast) return;
    const t = setTimeout(() => setToast(null), 2600);
    return () => clearTimeout(t);
  }, [toast]);

  // Teclado: ⌘K · / · ↑↓ (vira página na borda) · ↵ · esc
  useEffectP(() => {
    const onKey = (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {e.preventDefault();setPaleta((v) => !v);return;}
      const t = e.target;
      if (t && /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName)) return;
      if (e.key === "/") {e.preventDefault();buscaRef.current?.focus();return;}
      if (e.key === "ArrowDown" || e.key === "ArrowUp") {
        if (linhas.length === 0) return;
        e.preventDefault();
        const passo = e.key === "ArrowDown" ? 1 : -1;
        if (abertoId !== null) {vizinho(passo);return;}
        const prox = ativa + passo;
        if (prox < 0) {if (pag > 1) {setAtiva(porPagina - 1);setPagina(pag - 1);}return;}
        if (prox >= linhas.length) {if (pag < paginas) {setAtiva(0);setPagina(pag + 1);}return;}
        setAtiva(prox);
        return;
      }
      if (e.key === "Enter" && ativa >= 0 && linhas[ativa]) {e.preventDefault();abrirItem(linhas[ativa].id);return;}
      if (e.key === "Escape" && abertoId === null) setAtiva(-1);
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [linhas, ativa, abertoId, pag, paginas, porPagina]);
  useEffectP(() => {setAtiva((i) => i >= linhas.length ? linhas.length - 1 : i);}, [linhas.length]);

  const temFiltro = !!(fCategoria || fUnidade || fMarca || fEstoque || fMargem);
  const limparFiltros = () => {
    setBusca("");setKpi("");setFCategoria("");setFUnidade("");setFMarca("");setFEstoque("");setFMargem("");
    onTypeFilter?.("all");
  };
  const rotuloOrdem = (ORDEM_OPCOES.find((o) => o.key === ordem.key)?.label || "Código") + (ordem.dir === "asc" ? " ↑" : " ↓");
  const ordemDisponivel = ORDEM_OPCOES.filter((o) => (!o.custo || perm.custo) && (!o.preco || perm.preco));
  const trocarOrdem = (key) => setOrdem((o) => ({ key, dir: o.key === key && o.dir === "asc" ? "desc" : "asc" }));

  // Dois valores EM DINHEIRO, como no main (brl() nos dois): o que está parado em estoque
  // e o que custa repor o que está abaixo do mínimo. Somar dinheiro do catálogo é leitura da
  // estrutura de custo — por isso o bloco todo cai junto com a permissão.
  const totaisDoRecorte = perm.custo ? {
    emEstoque: recorte.reduce((s, r) => s + (r.stockQty || 0) * (r.cost || 0), 0),
    repor: recorte.reduce((s, r) => {
      const falta = r.minimo === null || r.stockQty === null ? 0 : Math.max(0, r.minimo - r.stockQty);
      return s + falta * (r.cost || 0);
    }, 0)
  } : null;

  if (semAcesso) {
    return (
      <div className="pd-page">
        <div className="pd-head"><div className="pd-head-l"><h1>Produtos</h1></div></div>
        <div className="pd-grid-card">
          <div className="pd-vazio">
            <I.lock size={22} />
            <b>Você não tem acesso ao catálogo</b>
            <span>Falta a permissão <code>product.view</code> neste business. Peça ao administrador da empresa.</span>
          </div>
        </div>
      </div>);

  }

  const celula = (r, key) => {
    switch (key) {
      case "sel":return (
        <input type="checkbox" checked={sel.includes(r.id)} onClick={(e) => e.stopPropagation()}
        onChange={() => marcarLinha(r.id)} aria-label={`Selecionar ${r.name}`} />);

      case "cod":return (
        <button type="button" className="pd-cod" title="Copiar código"
        onClick={(e) => {e.stopPropagation();navigator.clipboard?.writeText(String(r.codigo));setToast(`Código ${r.codigo} copiado`);}}>
            {r.codigo}
          </button>);

      case "prod":return (
        <span className="pd-prod">
            <Miniatura nome={r.name} />
            <span className="pd-prod-t">
              <span className="pd-prod-n" title={r.name}>{r.name}</span>
              <span className="pd-prod-sub">
                {!densa && <span className="pd-prod-meta">{r.unit}{r.cat_label ? ` · ${r.cat_label}` : ""}</span>}
                {r.obs &&
              <span className="pd-obs" tabIndex={0} title={r.obs}>
                    <I.note size={11} /><span>{r.obs}</span>
                  </span>
              }
                {r.grade &&
              <span className={"pd-grade" + (gradeComFuro(r.grade) ? " furo" : "")}
              title={gradeComFuro(r.grade) ? `${r.grade.total - r.grade.com} de ${r.grade.total} combinações sem saldo` : "Todas as combinações têm saldo"}>
                    {marcadorGrade(r.grade)}
                  </span>
              }
              </span>
            </span>
          </span>);

      case "tipo":return <span className="pd-tipo">{TIPO_LABEL[r.tipo]}</span>;
      case "est":return <Disponibilidade row={r} densa={densa} />;
      case "custo":return r.cost === undefined ? null : <span className="pd-mono dim">{brlP(r.cost)}</span>;
      case "preco":return r.price === undefined ? null : <span className="pd-mono forte">{brlP(r.price)}</span>;
      case "margem":{
          const m = margemFrac(r);
          if (m === undefined) return null;
          return <span className={"pd-mono" + (sobOPiso(r) ? " sob-piso" : " dim")}>{pctP(m)}</span>;
        }
      case "act":return (
        <MenuAncorado label="" icon={<I.more size={16} />}>
            {(fechar) => <>
              <button role="menuitem" onClick={() => {abrirItem(r.id);fechar();}}>Abrir ficha</button>
              <button role="menuitem" onClick={fechar}>Editar produto</button>
              <button role="menuitem" onClick={fechar}>Usar na venda</button>
              {perm.inativar && <button role="menuitem" className="perigo" onClick={() => {setSel([r.id]);setConfirmar(true);fechar();}}>Inativar</button>}
            </>}
          </MenuAncorado>);

      default:return null;
    }
  };

  return (
    <div className="pd-page">
      <div className="pd-head">
        <div className="pd-head-l">
          <h1>Produtos</h1>
          <p><b>{contagens.todos.toLocaleString("pt-BR")}</b> cadastrados · ROTA LIVRE</p>
        </div>
        <div className="pd-head-r">
          <MenuAncorado label="" icon={<I.more size={16} />}>
            {() => <>
              <div className="pd-menu-grupo">Apresentação</div>
              <button role="menuitem" onClick={() => setDensa((v) => !v)}>
                <span className={"pd-check" + (densa ? " off" : "")}><I.check size={13} /></span> Linhas confortáveis
              </button>
              {COLUNAS_OCULTAVEIS.filter((c) => colunasPermitidas.some((p) => p.key === c.key)).map((c) =>
              <button key={c.key} role="menuitem"
              onClick={() => setColsOcultas((v) => v.includes(c.key) ? v.filter((k) => k !== c.key) : [...v, c.key])}>
                  <span className={"pd-check" + (colsOcultas.includes(c.key) ? " off" : "")}><I.check size={13} /></span> Coluna {c.label.toLowerCase()}
                </button>
              )}
              <div className="pd-menu-sep" />
              <div className="pd-menu-grupo">Dados</div>
              <button role="menuitem">Importar</button>
              <button role="menuitem">Exportar planilha</button>
            </>}
          </MenuAncorado>
          <button className="os-btn primary"><I.plus size={13} /> Novo produto</button>
        </div>
        <nav className="pd-abas" aria-label="Recorte por tipo de item">
          {ABAS_CATALOGO.map(([k, label]) => {
            const on = aba === k;
            return (
              <button key={k} type="button" role="tab" aria-selected={on}
              className={"pd-aba" + (on ? " on" : "")}
              onClick={() => {setAba(k);setKpi("");}}>
                {label}
                <span className="pd-aba-n">{(contagens[k] || 0).toLocaleString("pt-BR")}</span>
              </button>);

          })}
        </nav>
      </div>

      {estado === "carregando" ?
      <div className="pd-kpis">
        {Array.from({ length: perm.custo && perm.preco ? 4 : 2 }).map((_, i) =>
        <div className="pd-kpi-skel" key={i}><span /><span /><span /></div>
        )}
      </div> :
      <KpiFiltros kpis={kpis} ativo={kpi || null} perm={perm}
      onToggle={(k) => setKpi(!k || k === "total" ? "" : k)} />
      }

      <div className={"pd-fbar" + (maisFiltros ? " mais" : "")}>
        <FiltroTrigger label="Categoria" value={fCategoria} options={opcoes.categorias} onChange={setFCategoria} />
        <FiltroTrigger label="Tipo" value={typeFilter === "all" ? "" : typeFilter === "composicao" ? "kit" : typeFilter}
        options={TIPO_OPCOES} onChange={(v) => {
          onTypeFilter?.(v === "kit" ? "composicao" : v || "all");
          setAba(v === "produto" ? "produtos" : v === "servico" ? "servicos" : v === "materia" ? "materia" : v === "kit" ? "kits" : "todos");
        }} />
        <span className="pd-fopt"><FiltroTrigger label="Unidade" value={fUnidade} options={opcoes.unidades} onChange={setFUnidade} /></span>
        <span className="pd-fopt"><FiltroTrigger label="Marca" value={fMarca} options={opcoes.marcas} onChange={setFMarca} /></span>
        <span className="pd-fopt"><FiltroTrigger label="Disponível" value={fEstoque} options={ESTOQUE_OPCOES} onChange={setFEstoque} /></span>
        {perm.custo && perm.preco &&
        <span className="pd-fopt"><FiltroTrigger label="Margem" value={fMargem} options={MARGEM_OPCOES} onChange={setFMargem} /></span>
        }
        <button type="button" className="pd-fmore" aria-expanded={maisFiltros} onClick={() => setMaisFiltros((v) => !v)}>
          Mais filtros <I.chevDown size={12} />
        </button>

        <MenuAncorado label={rotuloOrdem} align="start" icon={<I.sort size={12} />}>
          {(fechar) => <>
            {ordemDisponivel.map((o) =>
            <button key={o.key} role="menuitem" onClick={() => {setOrdem({ key: o.key, dir: "asc" });fechar();}}>
                <span className={"pd-check" + (ordem.key === o.key ? "" : " off")}><I.check size={13} /></span> {o.label}
              </button>
            )}
            <div className="pd-menu-sep" />
            <button role="menuitem" onClick={() => {setOrdem((o) => ({ ...o, dir: o.dir === "asc" ? "desc" : "asc" }));fechar();}}>
              {ordem.dir === "asc" ? "Inverter (maior primeiro)" : "Inverter (menor primeiro)"}
            </button>
          </>}
        </MenuAncorado>

        {(temFiltro || kpi || busca) && <button className="pd-limpar" onClick={limparFiltros}>Limpar</button>}
        <span className="pd-contagem">{total.toLocaleString("pt-BR")} {total === 1 ? "registro" : "registros"}</span>

        <label className="pd-busca">
          <I.search size={15} />
          <input ref={buscaRef} type="search" value={busca} onChange={(e) => setBusca(e.target.value)}
          aria-label="Buscar produtos" aria-keyshortcuts="/"
          placeholder="Buscar descrição, código, referência…" />
          {busca ?
          <button type="button" onClick={() => setBusca("")} aria-label="Limpar busca"><I.close size={14} /></button> :
          <kbd aria-hidden="true">/</kbd>
          }
        </label>
      </div>

      <div className="pd-grid-card">
        {estado === "carregando" && <Esqueleto colunas={colunas.length} />}
        {estado === "erro" &&
        <div className="pd-vazio erro" role="alert">
            <I.alert size={22} />
            <b>Não deu pra carregar os produtos</b>
            <span>A consulta ao servidor falhou. Nada foi alterado — tente de novo.</span>
            <button className="os-btn">Tentar de novo</button>
          </div>
        }
        {estado === "vazio" &&
        <div className="pd-vazio">
            <I.product size={22} />
            <b>Seu catálogo está vazio</b>
            <span>Cadastre o primeiro produto ou importe uma planilha pra começar a orçar.</span>
            <button className="os-btn primary"><I.plus size={13} /> Novo produto</button>
          </div>
        }
        {estado === "dados" && <>
          <div className="pd-scroll">
            <table className="pd-table" style={{ minWidth }}>
              <thead>
                <tr>
                  {colunas.map((c) =>
                  <th key={c.key} scope="col" style={{ width: c.width }}
                  className={(c.align === "right" ? "r " : "") + (c.key === "prod" ? "pd-th-prod" : "")}>
                      {c.key === "sel" ?
                    <input type="checkbox" checked={paginaToda} disabled={linhas.length === 0}
                    onChange={marcarPagina}
                    aria-label={paginaToda ? "Desmarcar esta página" : "Marcar esta página"} /> :
                    c.sortable ?
                    <button type="button" onClick={() => trocarOrdem(c.key)}>
                          {c.label}
                          <span className="pd-th-ord">{ordem.key === c.key ? ordem.dir === "asc" ? "↑" : "↓" : "⇅"}</span>
                        </button> :
                    c.label || <span className="pd-sr">Ações</span>}
                    </th>
                  )}
                </tr>
              </thead>
              <tbody>
                {linhas.length === 0 &&
                <tr>
                    <td colSpan={colunas.length}>
                      <div className="pd-vazio">
                        <I.search size={22} />
                        <b>Nenhum produto neste recorte</b>
                        <span>Ajuste a busca, troque a aba ou solte os filtros aplicados.</span>
                        {(temFiltro || kpi || busca) && <button className="os-btn" onClick={limparFiltros}>Limpar</button>}
                      </div>
                    </td>
                  </tr>
                }
                {linhas.map((r, i) =>
                <tr key={r.id}
                className={"pd-tr" + (linhaUrgente(r) ? " urgente" : "") + (sel.includes(r.id) ? " sel" : "") +
                (abertoId === r.id ? " aberta" : "") + (i === ativa ? " ativa" : "") + (r.active ? "" : " inativa")}
                style={{ height: densa ? 40 : 52 }}
                tabIndex={0} role="button" aria-label={`Abrir ficha de ${r.name}`}
                onClick={() => abrirItem(r.id)}
                onKeyDown={(e) => {if (e.key === "Enter" || e.key === " ") {e.preventDefault();abrirItem(r.id);}}}>
                    {colunas.map((c) =>
                  <td key={c.key} className={(c.align === "right" ? "r " : "") + (c.key === "prod" ? "pd-td-prod" : "")}>
                        {celula(r, c.key)}
                      </td>
                  )}
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          <div className="pd-rodape">
            <span className="pd-rod-meta">
              {primeira.toLocaleString("pt-BR")}–{ultima.toLocaleString("pt-BR")} de {total.toLocaleString("pt-BR")}
              {totaisDoRecorte && total > 0 &&
              <span className="pd-rod-tot">
                {" "}· em estoque {brlP(totaisDoRecorte.emEstoque)}
                {totaisDoRecorte.repor > 0 && <> · repor {brlP(totaisDoRecorte.repor)}</>}
              </span>
              }
            </span>
            <label className="pd-rod-pp">
              Por página
              <select value={porPagina} onChange={(e) => {setPorPagina(Number(e.target.value));setPagina(1);}}>
                {POR_PAGINA_OPCOES.map((n) => <option key={n} value={n}>{n}</option>)}
              </select>
            </label>
            <div className="pd-rod-nav">
              <button disabled={pag === 1} onClick={() => setPagina(1)} aria-label="Primeira página">«</button>
              <button disabled={pag === 1} onClick={() => setPagina(pag - 1)} aria-label="Página anterior">‹</button>
              <span className="pd-rod-pag">{pag} / {paginas}</span>
              <button disabled={pag === paginas} onClick={() => setPagina(pag + 1)} aria-label="Próxima página">›</button>
              <button disabled={pag === paginas} onClick={() => setPagina(paginas)} aria-label="Última página">»</button>
            </div>
          </div>
        </>}
      </div>

      <BulkBar total={sel.length} foraDaPagina={sel.length - selNaPagina.length}
      onInativar={perm.inativar ? () => setConfirmar(true) : undefined}
      onLimpar={() => setSel([])} />

      {confirmar &&
      <div className="pd-modal-back" onClick={() => setConfirmar(false)}>
          <div className="pd-modal" role="dialog" aria-modal="true" aria-label="Confirmar inativação" onClick={(e) => e.stopPropagation()}>
            <b>Inativar {sel.length} {sel.length === 1 ? "item" : "itens"}?</b>
            <p>Eles saem da busca de venda e do balcão, mas continuam no histórico e podem ser reativados na aba Inativos.</p>
            <div className="pd-modal-acoes">
              <button className="os-btn" onClick={() => setConfirmar(false)}>Cancelar</button>
              <button className="os-btn perigo" onClick={inativarSelecao}>Inativar</button>
            </div>
          </div>
        </div>
      }

      <Paleta aberta={paleta} onClose={() => setPaleta(false)} perm={perm}
      onAba={(k) => {setAba(k);setKpi("");}} onKpi={(k) => {setKpi(k);setPagina(1);}}
      onLimpar={limparFiltros} onDensa={() => setDensa((v) => !v)}
      recentes={recentes} onAbrir={abrirItem} />

      {abertoId && window.ProdutoDetalheDrawer &&
      <window.ProdutoDetalheDrawer
        row={todas.find((r) => r.id === abertoId)} perm={perm} piso={PISO_MARGEM}
        temAnterior={indiceAberto > 0} temProximo={indiceAberto >= 0 && indiceAberto < linhas.length - 1}
        posicao={indiceAberto >= 0 ? `${primeira + indiceAberto} de ${total.toLocaleString("pt-BR")}` : ""}
        onVizinho={vizinho}
        onCopiar={(texto, rotulo) => {navigator.clipboard?.writeText(texto);setToast(`${rotulo} copiado`);}}
        onClose={() => setAbertoId(null)} />
      }

      {toast && <div className="pd-toast" role="status">{toast}</div>}
    </div>);

}

window.ProdListPage = ProdListPage;
window.PROD_CATALOGO = { estadoEstoque, margemFrac, sobOPiso, brlP, pctP, numP, PISO_MARGEM, TIPO_LABEL };
