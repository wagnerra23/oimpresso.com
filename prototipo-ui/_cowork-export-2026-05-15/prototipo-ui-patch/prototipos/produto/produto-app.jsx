// Produto — Catálogo unificado.
// Single screen: KPI strip → segmented filter → unified table/grid → drawer detail with BOM.
// Persona-first: Larissa (1280px, balcão, density-first).

const { useState, useMemo, useEffect, useRef, useCallback } = React;

const fmtBRL = (n) => n.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
const fmtBRLshort = (n) => {
  if (Math.abs(n) >= 1000) return "R$ " + (n / 1000).toLocaleString("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + "k";
  return fmtBRL(n);
};
const fmtPct = (n) => Math.round(n * 100) + "%";

const DENSITY = {
  compact:    { rowH: 32, py: "py-1",   text: "text-[12.5px]", iconBox: 22, klass: "dens-compact" },
  comfortable:{ rowH: 44, py: "py-2.5", text: "text-sm",       iconBox: 26, klass: "dens-comfortable" },
  spacious:   { rowH: 56, py: "py-4",   text: "text-sm",       iconBox: 30, klass: "dens-spacious" },
};

const CAT_LABEL = {
  impressos: "Impressos",
  comvis: "Comunicação visual",
  embalagens: "Embalagens",
  brindes: "Brindes",
  adesivos: "Adesivos",
};

/* ── Sidebar ───────────────────────────────────────────────────────────── */
const NAV = [
  { id: "dash",    label: "Dashboard",  icon: PI.LayoutDashboard },
  { id: "sells",   label: "Vendas",     icon: PI.ShoppingBag },
  { id: "repair",  label: "Repair",     icon: PI.Wrench },
  { id: "fin",     label: "Financeiro", icon: PI.Wallet },
  { id: "clients", label: "Clientes",   icon: PI.Users },
  { id: "catalog", label: "Catálogo",   icon: PI.Box, active: true },
  { id: "fiscal",  label: "Fiscal",     icon: PI.FileText },
];
const CAT_SUB = [
  { id: "produtos",   label: "Produtos" },
  { id: "categorias", label: "Categorias" },
  { id: "insumos",    label: "Insumos · BOM" },
  { id: "tabelas",    label: "Tabelas de preço" },
  { id: "historico",  label: "Histórico de uso" },
];
const CAT_TITLES = {
  produtos: "Produtos",
  categorias: "Categorias",
  insumos: "Insumos · BOM",
  tabelas: "Tabelas de preço",
  historico: "Histórico de uso",
};

const Sidebar = ({ tela, setTela }) => (
  <aside className="w-[220px] shrink-0 bg-white border-r border-stone-200 flex flex-col h-screen sticky top-0">
    <div className="px-4 h-14 flex items-center gap-2 border-b border-stone-200">
      <div className="w-7 h-7 rounded-md bg-stone-900 text-white grid place-items-center font-semibold text-[13px] tracking-tight">o</div>
      <div className="flex-1">
        <div className="text-[13px] font-semibold leading-tight">oimpresso</div>
        <div className="text-[11px] text-stone-500 leading-tight">ROTA LIVRE</div>
      </div>
      <button className="w-6 h-6 grid place-items-center text-stone-400 hover:text-stone-700 rounded">
        <PI.ChevronLeft size={14} />
      </button>
    </div>

    <nav className="flex-1 nice-scroll overflow-y-auto py-2 text-[13px]">
      {NAV.map((n) => {
        const Icon = n.icon;
        return (
          <div key={n.id}>
            <a href="#" className={`mx-2 px-2.5 h-8 flex items-center gap-2.5 rounded-md transition-colors duration-150 ${n.active ? "bg-stone-100 text-stone-900 font-medium" : "text-stone-600 hover:bg-stone-50 hover:text-stone-900"}`}>
              <Icon size={16} className={n.active ? "text-stone-900" : "text-stone-500"} />
              <span>{n.label}</span>
              {n.id === "catalog" && <span className="ml-auto text-[10px] text-stone-400 num">28</span>}
              {n.id === "sells" && <span className="ml-auto text-[10px] text-stone-400 num">12</span>}
              {n.id === "repair" && <span className="ml-auto text-[10px] text-stone-400 num">3</span>}
            </a>
            {n.active && (
              <div className="mt-0.5 mb-1.5 ml-7 mr-2 border-l border-stone-200">
                {CAT_SUB.map((s) => (
                  <button key={s.id} onClick={() => setTela(s.id)}
                    className={`w-full text-left pl-3 pr-2 h-7 flex items-center rounded-r-md text-[12.5px] transition-colors duration-150 ${tela === s.id ? "text-stone-900 font-medium border-l-2 -ml-px border-stone-900 bg-stone-50/60" : "text-stone-500 hover:text-stone-800"}`}>
                    {s.label}
                  </button>
                ))}
              </div>
            )}
          </div>
        );
      })}
    </nav>

    <div className="border-t border-stone-200 p-3 flex items-center gap-2.5">
      <div className="w-7 h-7 rounded-full bg-stone-200 grid place-items-center text-[11px] font-semibold text-stone-700">LA</div>
      <div className="flex-1 min-w-0">
        <div className="text-[12.5px] font-medium truncate">Larissa Souza</div>
        <div className="text-[11px] text-stone-500 truncate">Balcão · ROTA LIVRE</div>
      </div>
      <button className="w-7 h-7 grid place-items-center rounded text-stone-500 hover:bg-stone-100">
        <PI.Settings size={14} />
      </button>
    </div>
  </aside>
);

/* ── Header ────────────────────────────────────────────────────────────── */
const Header = ({ telaTitle, onCmdK, onNew, totalCount }) => (
  <header className="sticky top-0 z-30 bg-white/85 backdrop-blur border-b border-stone-200">
    <div className="px-6 h-14 flex items-center gap-4">
      <div className="flex items-center gap-1.5 text-[12px] text-stone-500 whitespace-nowrap">
        <span>Catálogo</span>
        <PI.ChevronRight size={12} className="text-stone-400" />
        <span className="text-stone-900 font-medium">{telaTitle}</span>
      </div>

      <div className="flex-1" />

      <button onClick={onCmdK} className="h-8 px-3 flex items-center gap-2 rounded-md border border-stone-200 bg-white text-[12.5px] text-stone-500 hover:text-stone-800 hover:border-stone-300 transition-colors duration-150 w-[200px]">
        <PI.Search size={14} />
        <span className="truncate">Buscar produto…</span>
        <span className="ml-auto flex items-center gap-1 text-[11px] text-stone-400 font-mono">
          <kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-stone-50">⌘</kbd>
          <kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-stone-50">K</kbd>
        </span>
      </button>

      <button className="h-8 w-8 grid place-items-center rounded-md text-stone-500 hover:bg-stone-100 relative shrink-0">
        <PI.Bell size={16} />
      </button>

      <div className="h-5 w-px bg-stone-200 shrink-0" />

      <button className="h-8 px-3 flex items-center gap-1.5 rounded-md border border-stone-200 text-[12.5px] text-stone-700 hover:bg-stone-50 transition-colors duration-150 shrink-0 whitespace-nowrap">
        <PI.Upload size={14} />
        Importar CSV
      </button>
      <button className="h-8 w-8 grid place-items-center rounded-md border border-stone-200 text-stone-700 hover:bg-stone-50 transition-colors duration-150 shrink-0" title="Exportar">
        <PI.Download size={14} />
      </button>
      <button onClick={onNew} className="h-8 px-3 flex items-center gap-1.5 rounded-md bg-stone-900 text-white text-[12.5px] hover:bg-stone-800 transition-colors duration-150 shrink-0 whitespace-nowrap">
        <PI.Plus size={14} />
        Novo produto
        <kbd className="ml-1 px-1.5 py-0.5 rounded border border-stone-700 bg-stone-800 text-[10px] font-mono text-stone-300">N</kbd>
      </button>
    </div>

    <div className="px-6 pt-4 pb-3 flex items-baseline gap-3">
      <h1 className="text-[24px] font-semibold tracking-tight leading-none whitespace-nowrap">{telaTitle}</h1>
      <span className="text-[11px] uppercase tracking-widest text-stone-500 font-medium whitespace-nowrap">
        {totalCount} produtos · 5 categorias · ROTA LIVRE
      </span>
      <div className="ml-auto text-[12px] text-stone-500 flex items-center gap-1.5 whitespace-nowrap shrink-0">
        <PI.Refresh size={13} />
        <span>Atualizado há 2 min</span>
      </div>
    </div>
  </header>
);

/* ── KPI strip ─────────────────────────────────────────────────────────── */
const KPI = ({ label, value, sub, tone = "default", emphasize = false }) => {
  const toneClass = { default: "text-stone-900", pos: "text-emerald-700", neg: "text-rose-700" }[tone];
  return (
    <div className={`flex-1 px-5 py-4 ${emphasize ? "bg-stone-900 text-stone-50" : "bg-white"}`}>
      <div className={`text-[10px] uppercase tracking-widest font-medium ${emphasize ? "text-stone-400" : "text-stone-500"}`}>{label}</div>
      <div className={`mt-1 text-[28px] leading-none font-semibold tracking-tight num ${emphasize ? "text-stone-50" : toneClass}`}>{value}</div>
      {sub && <div className={`mt-2 text-[11.5px] ${emphasize ? "text-stone-400" : "text-stone-500"} flex items-center gap-1.5`}>{sub}</div>}
    </div>
  );
};

const KPIStrip = ({ rows, allRows }) => {
  const k = useMemo(() => {
    const ativos = allRows.filter(r => r.active).length;
    const inativos = allRows.length - ativos;
    const populares = allRows.filter(r => r.active && r.pop >= 70).length;
    const cats = new Set(allRows.map(r => r.cat)).size;
    const semUso30 = allRows.filter(r => r.active && r.uses30 === 0).length;
    const margemMedia = allRows.filter(r => r.active && r.cost > 0).reduce((s,r,_,a) => s + r.margin/a.length, 0);
    const ticket = allRows.filter(r => r.active).reduce((s,r,_,a) => s + r.price/a.length, 0);
    const usos = allRows.reduce((s,r) => s + r.uses30, 0);
    return { ativos, inativos, populares, cats, semUso30, margemMedia, ticket, usos };
  }, [allRows]);

  return (
    <div className="mx-6 rounded-md bg-white border border-stone-200 shadow-sm flex divide-x divide-stone-200 overflow-hidden">
      <KPI label="Catálogo ativo" value={k.ativos} emphasize
        sub={<><span>{k.cats} categorias</span><span className="text-stone-400">·</span><span>{k.inativos} inativos</span></>} />
      <KPI label="Mais vendidos · 30d" value={k.populares}
        sub={<><PI.Flame size={11} className="text-amber-600" /><span>popularidade ≥ 70%</span></>} />
      <KPI label="Saídas em 30 dias" value={k.usos} tone="pos"
        sub={<><PI.TrendUp size={11} className="text-emerald-600" /><span>itens vendidos via OS</span></>} />
      <KPI label="Margem média" value={fmtPct(k.margemMedia)}
        sub={<span>ticket médio <span className="text-stone-700 font-medium">{fmtBRLshort(k.ticket)}</span></span>} />
      <KPI label="Sem giro" value={k.semUso30}
        sub={<><span className="text-rose-700 font-medium">{k.semUso30}</span><span>ativos sem venda em 30d</span></>} />
    </div>
  );
};

/* ── Filter bar ────────────────────────────────────────────────────────── */
const TABS = [
  { id: "all",     label: "Todos" },
  { id: "active",  label: "Ativos" },
  { id: "popular", label: "Populares" },
  { id: "express", label: "Express" },
  { id: "stale",   label: "Sem giro" },
  { id: "inactive",label: "Inativos" },
];

const Pill = ({ icon: Icon, label, value, onClick, active }) => (
  <button onClick={onClick}
    className={`h-8 px-2.5 flex items-center gap-1.5 rounded-md text-[12.5px] border transition-colors duration-150 ${active ? "border-stone-300 bg-stone-50 text-stone-900" : "border-stone-200 bg-white text-stone-600 hover:text-stone-900 hover:border-stone-300"}`}>
    {Icon && <Icon size={13} className="text-stone-500" />}
    <span>{label}</span>
    {value && <span className="text-stone-900 font-medium">· {value}</span>}
    <PI.ChevronDown size={12} className="text-stone-400 -mr-0.5" />
  </button>
);

const FilterBar = ({ tab, setTab, counts, query, setQuery, cat, setCat, view, setView }) => (
  <div className="px-6 pt-4 pb-3 flex items-center gap-3 flex-wrap">
    <div className="inline-flex bg-stone-100/80 rounded-md p-0.5 border border-stone-200">
      {TABS.map((t) => (
        <button key={t.id} onClick={() => setTab(t.id)}
          className={`h-7 px-3 rounded text-[12.5px] flex items-center gap-1.5 transition-colors duration-150 ${tab === t.id ? "bg-white shadow-sm text-stone-900 font-medium" : "text-stone-600 hover:text-stone-900"}`}>
          {t.label}
          <span className={`text-[10.5px] num ${tab === t.id ? "text-stone-500" : "text-stone-400"}`}>{counts[t.id]}</span>
        </button>
      ))}
    </div>

    <div className="h-6 w-px bg-stone-200" />

    <button onClick={() => {
      const cats = ["all", ...PRODUTO_DATA.PROD_CATEGORIES.map(c => c.id)];
      setCat(cats[(cats.indexOf(cat) + 1) % cats.length]);
    }} className={`h-8 px-2.5 flex items-center gap-1.5 rounded-md text-[12.5px] border transition-colors duration-150 ${cat !== "all" ? "border-stone-300 bg-stone-50 text-stone-900" : "border-stone-200 bg-white text-stone-600 hover:text-stone-900 hover:border-stone-300"}`}>
      <PI.Tag size={13} className="text-stone-500" />
      <span>Categoria</span>
      <span className="text-stone-900 font-medium">· {cat === "all" ? "Todas" : CAT_LABEL[cat]}</span>
      <PI.ChevronDown size={12} className="text-stone-400 -mr-0.5" />
    </button>

    <Pill icon={PI.Layers} label="Insumo" value="Todos" />
    <Pill icon={PI.Clock} label="Prazo" value="Todos" />

    <div className="ml-auto flex items-center gap-2">
      <div className="relative">
        <PI.Search size={13} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-stone-400 pointer-events-none" />
        <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Filtrar nesta lista…"
          className="h-8 pr-3 w-[220px] rounded-md border border-stone-200 bg-white text-[12.5px] placeholder:text-stone-400 focus:border-stone-400" style={{ paddingLeft: 28 }} />
      </div>

      <div className="inline-flex bg-stone-100/80 rounded-md p-0.5 border border-stone-200">
        <button onClick={() => setView("table")}
          className={`h-7 w-7 grid place-items-center rounded transition-colors duration-150 ${view === "table" ? "bg-white shadow-sm text-stone-900" : "text-stone-500 hover:text-stone-800"}`} title="Tabela">
          <PI.List size={14} />
        </button>
        <button onClick={() => setView("grid")}
          className={`h-7 w-7 grid place-items-center rounded transition-colors duration-150 ${view === "grid" ? "bg-white shadow-sm text-stone-900" : "text-stone-500 hover:text-stone-800"}`} title="Grade">
          <PI.Grid size={14} />
        </button>
      </div>
    </div>
  </div>
);

/* ── Tags / badges ─────────────────────────────────────────────────────── */
const TAG_STYLES = {
  "best-seller":    { bg: "bg-amber-50",   fg: "text-amber-700",   icon: PI.Flame },
  "express":        { bg: "bg-sky-50",     fg: "text-sky-700",     icon: PI.Zap },
  "popular":        { bg: "bg-emerald-50", fg: "text-emerald-700", icon: PI.TrendUp },
  "alta margem":    { bg: "bg-stone-100",  fg: "text-stone-700",   icon: PI.Star },
  "descontinuar?":  { bg: "bg-rose-50",    fg: "text-rose-700",    icon: PI.X },
};
const TagBadge = ({ tag }) => {
  const s = TAG_STYLES[tag] || { bg: "bg-stone-100", fg: "text-stone-700", icon: PI.Tag };
  const Icon = s.icon;
  return (
    <span className={`inline-flex items-center gap-1 px-1.5 py-0.5 rounded ${s.bg} ${s.fg} text-[10.5px] font-medium`}>
      <Icon size={9} strokeWidth={2.2} />
      {tag}
    </span>
  );
};

/* ── Product thumb (placeholder) ───────────────────────────────────────── */
const Thumb = ({ size = 32, cat }) => (
  <div className="ph-stripe rounded grid place-items-center text-stone-400 shrink-0 border border-stone-200" style={{ width: size, height: size }}>
    <PI.Image size={Math.round(size * 0.42)} />
  </div>
);

/* ── Table ─────────────────────────────────────────────────────────────── */
const Row = ({ row, density, selected, onSelect, onOpen, dim, showCost }) => {
  const dens = DENSITY[density];
  const popPct = row.pop;
  return (
    <tr className={`border-b border-stone-100 row-hover cursor-pointer ${selected ? "row-selected" : ""} ${dim ? "opacity-55" : ""}`} onClick={() => onOpen(row)}>
      <td className={`pl-6 pr-2 ${dens.py}`} onClick={(e) => { e.stopPropagation(); onSelect(row.id); }}>
        <input type="checkbox" checked={selected} onChange={() => {}} className="w-3.5 h-3.5 accent-stone-900" />
      </td>
      <td className={`pr-2 ${dens.py}`}>
        <Thumb size={dens.iconBox} cat={row.cat} />
      </td>
      <td className={`pr-3 ${dens.py}`}>
        <div className="font-mono text-[11.5px] text-stone-500">{row.id}</div>
      </td>
      <td className={`pr-4 ${dens.py}`}>
        <div className="flex items-center gap-2 min-w-0">
          <div className="min-w-0">
            <div className={`${dens.text} font-medium text-stone-900 truncate`}>{row.name}</div>
            {row.tags.length > 0 && density !== "compact" && (
              <div className="mt-1 flex items-center gap-1 flex-wrap">
                {row.tags.slice(0,2).map(t => <TagBadge key={t} tag={t} />)}
              </div>
            )}
          </div>
        </div>
      </td>
      <td className={`pr-4 ${dens.py} text-[12px] text-stone-600`}>
        {CAT_LABEL[row.cat]}
      </td>
      <td className={`pr-4 ${dens.py} num text-right`}>
        <div className={`${dens.text} font-medium text-stone-900`}>{fmtBRL(row.price)}</div>
        <div className="text-[11px] text-stone-500">/{row.unit}</div>
      </td>
      {showCost && (
        <td className={`pr-4 ${dens.py} num text-right`}>
          <div className={`${dens.text} text-stone-700`}>{fmtBRL(row.cost)}</div>
          <div className={`text-[11px] ${row.margin >= 0.5 ? "text-emerald-700" : row.margin >= 0.3 ? "text-stone-500" : "text-rose-700"}`}>{fmtPct(row.margin)} margem</div>
        </td>
      )}
      <td className={`pr-4 ${dens.py} text-center`}>
        <span className="inline-flex items-center gap-1 text-[12px] text-stone-700">
          <PI.Clock size={11} className="text-stone-400" />
          <span className="num">{row.lead}d</span>
        </span>
      </td>
      <td className={`pr-4 ${dens.py}`}>
        <div className="flex items-center gap-2">
          <div className="w-16 h-1 rounded-full bg-stone-100 overflow-hidden">
            <div className={`h-full ${popPct >= 70 ? "bg-emerald-600" : popPct >= 40 ? "bg-stone-700" : "bg-stone-400"}`} style={{ width: popPct + "%" }} />
          </div>
          <span className="num text-[11.5px] text-stone-600 w-8">{popPct}%</span>
        </div>
      </td>
      <td className={`pr-4 ${dens.py} num text-right text-[12px] text-stone-700`}>{row.uses30}</td>
      <td className={`pr-6 ${dens.py}`}>
        {row.active ? (
          <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-medium">
            <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />ativo
          </span>
        ) : (
          <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-stone-100 text-stone-500 text-[11px] font-medium">
            <span className="w-1.5 h-1.5 rounded-full bg-stone-400" />inativo
          </span>
        )}
      </td>
    </tr>
  );
};

const ProductTable = ({ rows, density, selected, setSelected, onOpen, openId, showCost }) => {
  const allChecked = rows.length > 0 && rows.every(r => selected.has(r.id));
  const toggleAll = () => {
    if (allChecked) setSelected(new Set());
    else setSelected(new Set(rows.map(r => r.id)));
  };
  const toggleOne = (id) => {
    const next = new Set(selected);
    if (next.has(id)) next.delete(id); else next.add(id);
    setSelected(next);
  };
  return (
    <div className={`mx-6 rounded-md bg-white border border-stone-200 shadow-sm overflow-hidden ${DENSITY[density].klass}`}>
      <table className="w-full text-left">
        <thead className="text-[10.5px] uppercase tracking-widest text-stone-500 font-medium">
          <tr className="border-b border-stone-200 bg-stone-50/40">
            <th className="pl-6 pr-2 py-2 w-8">
              <input type="checkbox" checked={allChecked} onChange={toggleAll} className="w-3.5 h-3.5 accent-stone-900" />
            </th>
            <th className="pr-2 py-2 w-10"></th>
            <th className="pr-3 py-2 w-20">SKU</th>
            <th className="pr-4 py-2">Produto</th>
            <th className="pr-4 py-2 w-44">Categoria</th>
            <th className="pr-4 py-2 w-32 text-right">Preço</th>
            {showCost && <th className="pr-4 py-2 w-32 text-right">Custo</th>}
            <th className="pr-4 py-2 w-20 text-center">Prazo</th>
            <th className="pr-4 py-2 w-32">Popularidade</th>
            <th className="pr-4 py-2 w-16 text-right">Vendas 30d</th>
            <th className="pr-6 py-2 w-24">Status</th>
          </tr>
        </thead>
        <tbody>
          {rows.map(r => (
            <Row key={r.id} row={r} density={density} selected={selected.has(r.id)} onSelect={toggleOne} onOpen={onOpen} dim={openId && openId !== r.id} showCost={showCost} />
          ))}
          {rows.length === 0 && (
            <tr><td colSpan={11} className="py-12 text-center text-stone-400 text-[13px]">Nenhum produto encontrado · ajuste filtros</td></tr>
          )}
        </tbody>
      </table>
    </div>
  );
};

/* ── Grid view ─────────────────────────────────────────────────────────── */
const ProductGrid = ({ rows, onOpen, openId, showCost }) => (
  <div className="mx-6 grid gap-3" style={{ gridTemplateColumns: "repeat(auto-fill, minmax(240px, 1fr))" }}>
    {rows.map(r => (
      <button key={r.id} onClick={() => onOpen(r)}
        className={`text-left bg-white rounded-md border shadow-sm transition-colors duration-150 hover:border-stone-400 ${openId === r.id ? "border-stone-900" : "border-stone-200"} ${!r.active ? "opacity-60" : ""}`}>
        <div className="aspect-[16/10] ph-stripe rounded-t-md grid place-items-center text-stone-400 border-b border-stone-200">
          <PI.Image size={28} />
        </div>
        <div className="p-3">
          <div className="flex items-center gap-1.5 mb-1.5">
            <span className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">{CAT_LABEL[r.cat]}</span>
            <span className="ml-auto font-mono text-[10.5px] text-stone-400">{r.id}</span>
          </div>
          <div className="text-[13px] font-medium text-stone-900 leading-snug" style={{ textWrap: "pretty" }}>{r.name}</div>
          <div className="mt-2.5 flex items-baseline gap-1">
            <span className="text-[15px] font-semibold num">{fmtBRL(r.price)}</span>
            <span className="text-[11px] text-stone-500">/{r.unit}</span>
          </div>
          {showCost && (
            <div className="text-[11px] text-stone-500">
              custo {fmtBRL(r.cost)} · <span className={r.margin >= 0.5 ? "text-emerald-700 font-medium" : r.margin >= 0.3 ? "text-stone-700" : "text-rose-700 font-medium"}>{fmtPct(r.margin)} margem</span>
            </div>
          )}
          <div className="mt-2.5 flex items-center gap-2">
            <div className="flex-1 h-1 rounded-full bg-stone-100 overflow-hidden">
              <div className={`h-full ${r.pop >= 70 ? "bg-emerald-600" : r.pop >= 40 ? "bg-stone-700" : "bg-stone-400"}`} style={{ width: r.pop + "%" }} />
            </div>
            <span className="num text-[10.5px] text-stone-500 w-8 text-right">{r.pop}%</span>
          </div>
          <div className="mt-2 flex items-center gap-1.5 text-[11px] text-stone-500">
            <PI.Clock size={11} />
            <span className="num">{r.lead}d</span>
            <span className="text-stone-300">·</span>
            <span><b className="num text-stone-700">{r.uses30}</b> em 30d</span>
            <span className="ml-auto">{r.active ? "ativo" : "inativo"}</span>
          </div>
          {r.tags.length > 0 && (
            <div className="mt-2 flex items-center gap-1 flex-wrap">
              {r.tags.map(t => <TagBadge key={t} tag={t} />)}
            </div>
          )}
        </div>
      </button>
    ))}
  </div>
);

/* ── Drawer ────────────────────────────────────────────────────────────── */
const Drawer = ({ row, onClose, showCost }) => {
  if (!row) return null;
  const insLookup = (id) => PRODUTO_DATA.INSUMOS.find(i => i.id === id);

  return (
    <>
      <div className="fixed inset-0 bg-stone-900/10 z-40" onClick={onClose} />
      <aside className="fixed top-0 right-0 h-screen w-[480px] bg-white border-l border-stone-200 shadow-md z-50 flex flex-col drawer-shown">
        <div className="px-5 h-14 flex items-center gap-3 border-b border-stone-200 shrink-0">
          <div className="font-mono text-[11.5px] text-stone-500">{row.id}</div>
          <span className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">{CAT_LABEL[row.cat]}</span>
          <div className="flex-1" />
          <button className="w-8 h-8 grid place-items-center rounded text-stone-500 hover:bg-stone-100">
            <PI.More size={16} />
          </button>
          <button onClick={onClose} className="w-8 h-8 grid place-items-center rounded text-stone-500 hover:bg-stone-100">
            <PI.X size={16} />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto nice-scroll">
          <div className="px-5 py-5 border-b border-stone-200">
            <div className="flex items-start gap-4">
              <div className="ph-stripe rounded border border-stone-200 grid place-items-center text-stone-400 shrink-0" style={{ width: 84, height: 84 }}>
                <PI.Image size={32} />
              </div>
              <div className="min-w-0 flex-1">
                <h2 className="text-[18px] font-semibold leading-tight text-stone-900" style={{ textWrap: "pretty" }}>{row.name}</h2>
                <div className="mt-1.5 flex items-center gap-2 flex-wrap">
                  {row.active ? (
                    <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-medium">
                      <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />ativo
                    </span>
                  ) : (
                    <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-stone-100 text-stone-500 text-[11px] font-medium">
                      <span className="w-1.5 h-1.5 rounded-full bg-stone-400" />inativo
                    </span>
                  )}
                  {row.tags.map(t => <TagBadge key={t} tag={t} />)}
                </div>
              </div>
            </div>

            <div className="mt-5 grid grid-cols-3 gap-px bg-stone-200 rounded-md overflow-hidden border border-stone-200">
              <div className="bg-white px-3 py-2.5">
                <div className="text-[9.5px] uppercase tracking-widest text-stone-500 font-medium">Preço</div>
                <div className="mt-0.5 text-[18px] font-semibold num leading-none">{fmtBRL(row.price)}</div>
                <div className="text-[10.5px] text-stone-500">/{row.unit}</div>
              </div>
              <div className="bg-white px-3 py-2.5">
                <div className="text-[9.5px] uppercase tracking-widest text-stone-500 font-medium">Custo</div>
                <div className="mt-0.5 text-[18px] font-semibold num leading-none text-stone-700">{fmtBRL(row.cost)}</div>
                <div className={`text-[10.5px] ${row.margin >= 0.5 ? "text-emerald-700" : row.margin >= 0.3 ? "text-stone-500" : "text-rose-700"}`}>{fmtPct(row.margin)} margem</div>
              </div>
              <div className="bg-white px-3 py-2.5">
                <div className="text-[9.5px] uppercase tracking-widest text-stone-500 font-medium">Prazo</div>
                <div className="mt-0.5 text-[18px] font-semibold num leading-none">{row.lead}d</div>
                <div className="text-[10.5px] text-stone-500">produção</div>
              </div>
            </div>
          </div>

          {/* BOM */}
          <div className="px-5 py-5 border-b border-stone-200">
            <div className="flex items-center justify-between mb-3">
              <div>
                <div className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Composição (BOM)</div>
                <div className="text-[12px] text-stone-500 mt-0.5">{row.bom.length} insumo{row.bom.length !== 1 ? "s" : ""} · custo {fmtBRL(row.cost)}</div>
              </div>
              <button className="h-7 px-2.5 flex items-center gap-1.5 rounded-md border border-stone-200 text-[12px] text-stone-700 hover:bg-stone-50">
                <PI.Pencil size={11} />
                Editar
              </button>
            </div>
            {row.bom.length === 0 ? (
              <div className="px-3 py-6 rounded-md border border-dashed border-stone-200 text-center text-[12px] text-stone-400">
                Sem composição cadastrada
              </div>
            ) : (
              <div className="rounded-md border border-stone-200 overflow-hidden divide-y divide-stone-100">
                {row.bom.map((b, i) => {
                  const ins = insLookup(b.insId);
                  if (!ins) return null;
                  const sub = ins.cost * b.qty;
                  return (
                    <div key={i} className="px-3 py-2 flex items-center gap-3 bg-white">
                      <PI.Beaker size={13} className="text-stone-400 shrink-0" />
                      <div className="min-w-0 flex-1">
                        <div className="text-[12.5px] font-medium text-stone-900 truncate">{ins.name}</div>
                        <div className="text-[11px] text-stone-500 font-mono">{ins.id} · {ins.supplier}</div>
                      </div>
                      <div className="text-right shrink-0">
                        <div className="text-[12px] num text-stone-700">{b.qty} {ins.unit}</div>
                        <div className="text-[11px] num text-stone-500">{fmtBRL(sub)}</div>
                      </div>
                    </div>
                  );
                })}
                <div className="px-3 py-2 flex items-center bg-stone-50/60">
                  <div className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Custo total</div>
                  <div className="ml-auto text-[14px] num font-semibold">{fmtBRL(row.cost)}</div>
                </div>
              </div>
            )}
          </div>

          {/* Specs */}
          <div className="px-5 py-5 border-b border-stone-200">
            <div className="text-[11px] uppercase tracking-widest text-stone-500 font-medium mb-3">Especificações</div>
            <div className="grid grid-cols-2 gap-x-6 gap-y-2 text-[12.5px]">
              <div className="flex justify-between border-b border-stone-100 py-1.5">
                <span className="text-stone-500">SKU</span>
                <span className="font-mono text-stone-900">{row.id}</span>
              </div>
              <div className="flex justify-between border-b border-stone-100 py-1.5">
                <span className="text-stone-500">Unidade</span>
                <span className="text-stone-900">{row.unit}</span>
              </div>
              <div className="flex justify-between border-b border-stone-100 py-1.5">
                <span className="text-stone-500">Estoque</span>
                <span className="text-stone-900">{row.stockKind === "estoque" ? `${row.stockQty} un` : "sob demanda"}</span>
              </div>
              <div className="flex justify-between border-b border-stone-100 py-1.5">
                <span className="text-stone-500">Categoria</span>
                <span className="text-stone-900">{CAT_LABEL[row.cat]}</span>
              </div>
              <div className="flex justify-between border-b border-stone-100 py-1.5">
                <span className="text-stone-500">Atualizado</span>
                <span className="num text-stone-900">{row.updated}</span>
              </div>
              <div className="flex justify-between border-b border-stone-100 py-1.5">
                <span className="text-stone-500">Vendas · 30d</span>
                <span className="num text-stone-900">{row.uses30}</span>
              </div>
            </div>
          </div>

          {/* Trend mini */}
          <div className="px-5 py-5">
            <div className="text-[11px] uppercase tracking-widest text-stone-500 font-medium mb-3">Vendas últimos 6 meses</div>
            <div className="flex items-end gap-1.5 h-16">
              {[0.4, 0.6, 0.5, 0.85, 0.7, 1.0].map((v, i) => (
                <div key={i} className="flex-1 bg-stone-200 rounded-t" style={{ height: (v * 100) + "%" }} />
              ))}
            </div>
            <div className="mt-1.5 flex justify-between text-[10.5px] text-stone-500 font-mono">
              <span>nov</span><span>dez</span><span>jan</span><span>fev</span><span>mar</span><span>abr</span>
            </div>
          </div>
        </div>

        <div className="border-t border-stone-200 px-5 py-3 flex items-center gap-2 shrink-0 bg-white">
          <button className="h-9 px-3 flex items-center gap-1.5 rounded-md bg-stone-900 text-white text-[12.5px] hover:bg-stone-800">
            <PI.Pencil size={13} /> Editar
          </button>
          <button className="h-9 px-3 flex items-center gap-1.5 rounded-md border border-stone-200 text-[12.5px] text-stone-700 hover:bg-stone-50">
            <PI.Copy size={13} /> Duplicar
          </button>
          <button className="h-9 px-3 flex items-center gap-1.5 rounded-md border border-stone-200 text-[12.5px] text-stone-700 hover:bg-stone-50">
            <PI.ArrowRight size={13} /> Usar em OS
          </button>
          <div className="flex-1" />
          <button className="h-9 px-3 flex items-center gap-1.5 rounded-md text-[12.5px] text-rose-700 hover:bg-rose-50">
            <PI.Trash size={13} /> {row.active ? "Desativar" : "Reativar"}
          </button>
        </div>
      </aside>
    </>
  );
};

/* ── Bulk bar (when items selected) ────────────────────────────────────── */
const BulkBar = ({ count, onClear }) => (
  <div className="fixed bottom-5 left-1/2 -translate-x-1/2 z-30 bg-stone-900 text-stone-50 rounded-md shadow-lg flex items-center gap-1 px-2 h-11">
    <span className="px-2 text-[12.5px]"><span className="num font-semibold">{count}</span> selecionado{count !== 1 ? "s" : ""}</span>
    <div className="h-5 w-px bg-stone-700" />
    <button className="h-8 px-2.5 flex items-center gap-1.5 rounded text-[12.5px] hover:bg-stone-800">
      <PI.Tag size={12} /> Categoria
    </button>
    <button className="h-8 px-2.5 flex items-center gap-1.5 rounded text-[12.5px] hover:bg-stone-800">
      <PI.TrendUp size={12} /> Reajustar preço
    </button>
    <button className="h-8 px-2.5 flex items-center gap-1.5 rounded text-[12.5px] hover:bg-stone-800">
      <PI.Download size={12} /> Exportar
    </button>
    <button className="h-8 px-2.5 flex items-center gap-1.5 rounded text-[12.5px] hover:bg-stone-800">
      <PI.X size={12} /> Desativar
    </button>
    <div className="h-5 w-px bg-stone-700" />
    <button onClick={onClear} className="h-8 w-8 grid place-items-center rounded text-stone-400 hover:text-stone-50">
      <PI.X size={14} />
    </button>
  </div>
);

/* ── Other "tela" placeholders ─────────────────────────────────────────── */
const TelaCategorias = () => {
  const counts = useMemo(() => {
    const m = {};
    PRODUTO_DATA.PROD_LIST.forEach(p => {
      m[p.cat] = m[p.cat] || { count: 0, ativos: 0, soma: 0, vendas: 0 };
      m[p.cat].count++;
      if (p.active) m[p.cat].ativos++;
      m[p.cat].soma += p.price;
      m[p.cat].vendas += p.uses30;
    });
    return m;
  }, []);
  return (
    <div className="px-6 mt-3 grid grid-cols-2 gap-3">
      {PRODUTO_DATA.PROD_CATEGORIES.map(c => {
        const k = counts[c.id] || { count:0, ativos:0, soma:0, vendas:0 };
        const avg = k.count > 0 ? k.soma / k.count : 0;
        return (
          <div key={c.id} className="bg-white border border-stone-200 rounded-md shadow-sm p-5">
            <div className="flex items-start gap-3">
              <div className="w-10 h-10 rounded bg-stone-100 grid place-items-center text-stone-700">
                <PI.Box size={18} />
              </div>
              <div className="flex-1">
                <div className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Categoria</div>
                <div className="text-[16px] font-semibold mt-0.5">{c.label}</div>
              </div>
              <button className="text-stone-500 hover:text-stone-900"><PI.More size={14} /></button>
            </div>
            <div className="mt-4 grid grid-cols-4 gap-x-3">
              <div>
                <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Total</div>
                <div className="text-[18px] num font-semibold">{k.count}</div>
              </div>
              <div>
                <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Ativos</div>
                <div className="text-[18px] num font-semibold text-emerald-700">{k.ativos}</div>
              </div>
              <div>
                <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Ticket méd.</div>
                <div className="text-[18px] num font-semibold">{fmtBRLshort(avg)}</div>
              </div>
              <div>
                <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Saídas 30d</div>
                <div className="text-[18px] num font-semibold">{k.vendas}</div>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
};

const TelaInsumos = () => (
  <div className="mx-6 mt-3 rounded-md bg-white border border-stone-200 shadow-sm overflow-hidden">
    <table className="w-full text-left">
      <thead className="text-[10.5px] uppercase tracking-widest text-stone-500 font-medium">
        <tr className="border-b border-stone-200 bg-stone-50/40">
          <th className="pl-6 pr-3 py-2 w-20">SKU</th>
          <th className="pr-4 py-2">Insumo</th>
          <th className="pr-4 py-2 w-32">Fornecedor</th>
          <th className="pr-4 py-2 w-24 text-right">Custo</th>
          <th className="pr-4 py-2 w-24">Unidade</th>
          <th className="pr-4 py-2 w-24 text-right">Estoque</th>
          <th className="pr-6 py-2 w-32">Status</th>
        </tr>
      </thead>
      <tbody>
        {PRODUTO_DATA.INSUMOS.map(i => (
          <tr key={i.id} className="border-b border-stone-100 row-hover" style={{ height: 40 }}>
            <td className="pl-6 pr-3 font-mono text-[11.5px] text-stone-500">{i.id}</td>
            <td className="pr-4 text-[13px] font-medium">{i.name}</td>
            <td className="pr-4 text-[12px] text-stone-600">{i.supplier}</td>
            <td className="pr-4 text-[12.5px] num text-right">{fmtBRL(i.cost)}</td>
            <td className="pr-4 text-[12px] text-stone-600">{i.unit}</td>
            <td className="pr-4 text-[12.5px] num text-right">{i.stock.toLocaleString("pt-BR")}</td>
            <td className="pr-6">
              {i.stock === 0 && i.supplier === "Interno" ? (
                <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 text-[11px] font-medium"><span className="w-1.5 h-1.5 rounded-full bg-sky-500" />serviço</span>
              ) : i.stock < 50 ? (
                <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 text-[11px] font-medium"><span className="w-1.5 h-1.5 rounded-full bg-rose-500" />baixo</span>
              ) : (
                <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-medium"><span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />ok</span>
              )}
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  </div>
);

// ── Tabelas de preço ────────────────────────────────────────────────────
const PRICE_TABLES = [
  { id:"balcao",  label:"Balcão",     desc:"Cliente final no balcão",       mult: 1.00 },
  { id:"atacado", label:"Atacado",    desc:"Volume ≥ 10un · cliente fiel",   mult: 0.85 },
  { id:"b2b",     label:"Tabela B2B",  desc:"Contratos / fidelidade Premium", mult: 0.75 },
  { id:"parceiro",label:"Parceiro",   desc:"Revenda / agência",              mult: 0.65 },
];
const TelaTabelas = () => {
  const [tableId, setTableId] = useState("balcao");
  const [search, setSearch] = useState("");
  const cur = PRICE_TABLES.find(t => t.id === tableId);
  const rows = PRODUTO_DATA.PROD_LIST.filter(r => r.active && (search.trim() === "" || r.name.toLowerCase().includes(search.toLowerCase()) || r.id.toLowerCase().includes(search.toLowerCase())));
  return (
    <div className="px-6 mt-3 space-y-4">
      <div className="grid grid-cols-4 gap-3">
        {PRICE_TABLES.map(t => {
          const active = t.id === tableId;
          return (
            <button key={t.id} onClick={() => setTableId(t.id)}
              className={`text-left p-4 rounded-md border shadow-sm transition-colors duration-150 ${active ? "bg-stone-900 text-stone-50 border-stone-900" : "bg-white border-stone-200 hover:border-stone-400"}`}>
              <div className={`text-[10px] uppercase tracking-widest font-medium ${active ? "text-stone-400" : "text-stone-500"}`}>Tabela</div>
              <div className="mt-1 text-[16px] font-semibold leading-none">{t.label}</div>
              <div className={`mt-1.5 text-[12px] ${active ? "text-stone-300" : "text-stone-600"}`}>{t.desc}</div>
              <div className="mt-3 flex items-baseline gap-1">
                <span className={`text-[20px] font-semibold num leading-none ${active ? "" : t.mult < 1 ? "text-stone-700" : ""}`}>{Math.round(t.mult * 100)}%</span>
                <span className={`text-[11px] ${active ? "text-stone-400" : "text-stone-500"}`}>do balcão</span>
              </div>
            </button>
          );
        })}
      </div>

      <div className="flex items-center gap-3">
        <h2 className="text-[15px] font-semibold">{cur.label}</h2>
        <span className="text-[12px] text-stone-500">{rows.length} produtos · multiplicador {cur.mult.toFixed(2)}</span>
        <div className="ml-auto flex items-center gap-2">
          <div className="relative">
            <PI.Search size={13} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-stone-400 pointer-events-none" />
            <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Buscar…"
              className="h-8 pr-3 w-[200px] rounded-md border border-stone-200 bg-white text-[12.5px] placeholder:text-stone-400" style={{ paddingLeft: 28 }} />
          </div>
          <button className="h-8 px-3 flex items-center gap-1.5 rounded-md border border-stone-200 text-[12.5px] text-stone-700 hover:bg-stone-50"><PI.Pencil size={12} /> Editar tabela</button>
          <button className="h-8 px-3 flex items-center gap-1.5 rounded-md border border-stone-200 text-[12.5px] text-stone-700 hover:bg-stone-50"><PI.Download size={12} /> Exportar</button>
        </div>
      </div>

      <div className="rounded-md bg-white border border-stone-200 shadow-sm overflow-hidden">
        <table className="w-full text-left">
          <thead className="text-[10.5px] uppercase tracking-widest text-stone-500 font-medium">
            <tr className="border-b border-stone-200 bg-stone-50/40">
              <th className="pl-6 pr-3 py-2 w-20">SKU</th>
              <th className="pr-4 py-2">Produto</th>
              <th className="pr-4 py-2 w-40">Categoria</th>
              <th className="pr-4 py-2 w-28 text-right">Preço balcão</th>
              <th className="pr-4 py-2 w-28 text-right">Esta tabela</th>
              <th className="pr-4 py-2 w-24 text-right">Custo</th>
              <th className="pr-6 py-2 w-24 text-right">Margem</th>
            </tr>
          </thead>
          <tbody>
            {rows.map(r => {
              const tabPrice = r.price * cur.mult;
              const tabMargin = tabPrice > 0 ? (tabPrice - r.cost) / tabPrice : 0;
              return (
                <tr key={r.id} className="border-b border-stone-100 row-hover" style={{ height: 40 }}>
                  <td className="pl-6 pr-3 font-mono text-[11.5px] text-stone-500">{r.id}</td>
                  <td className="pr-4 text-[13px] font-medium">{r.name}</td>
                  <td className="pr-4 text-[12px] text-stone-600">{CAT_LABEL[r.cat]}</td>
                  <td className="pr-4 text-[12.5px] num text-right text-stone-500">{fmtBRL(r.price)}</td>
                  <td className="pr-4 text-[13px] num text-right font-semibold">{fmtBRL(tabPrice)}</td>
                  <td className="pr-4 text-[12px] num text-right text-stone-600">{fmtBRL(r.cost)}</td>
                  <td className={`pr-6 text-[12.5px] num text-right font-medium ${tabMargin >= 0.4 ? "text-emerald-700" : tabMargin >= 0.15 ? "text-stone-700" : "text-rose-700"}`}>{fmtPct(tabMargin)}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
};

// ── Histórico de uso ────────────────────────────────────────────────────
const HIST_CLIENTS = ["Padaria Pão Quente","Auto Posto Trevo","Loja Bella Moda","Construtora Vértice","Farmácia Saúde Total","Restaurante Sabor & Cia","Studio Foco","Imobiliária Horizonte","Transporte Veloz","Academia Movimento","Clínica Vida Plena","Cervejaria Lupulada","Pet Latido Feliz","Mercado União"];
function makeHistory() {
  // Synthesize ~80 lançamentos from product.uses30 weights
  const out = [];
  let osCounter = 4910;
  PRODUTO_DATA.PROD_LIST.forEach(p => {
    const n = Math.min(8, Math.ceil(p.uses30 / 18));
    for (let i = 0; i < n; i++) {
      const day = 1 + Math.floor((i * 73 + p.id.charCodeAt(2)) % 30);
      const qty = Math.max(1, Math.round((p.uses30 / Math.max(1,n)) * (0.6 + ((i*7) % 5) / 10)));
      out.push({
        os: `OS-${osCounter--}`,
        date: `2026-04-${String(day).padStart(2,"0")}`,
        prodId: p.id, prodName: p.name, cat: p.cat, unit: p.unit,
        client: HIST_CLIENTS[(osCounter + p.name.length) % HIST_CLIENTS.length],
        qty,
        value: p.price * qty,
      });
    }
  });
  return out.sort((a,b) => b.date.localeCompare(a.date) || b.os.localeCompare(a.os));
}
const HISTORY_ROWS = makeHistory();

const TelaHistorico = () => {
  const [period, setPeriod] = useState("30d");
  const [search, setSearch] = useState("");
  const rows = HISTORY_ROWS.filter(r => search.trim() === "" || r.prodName.toLowerCase().includes(search.toLowerCase()) || r.client.toLowerCase().includes(search.toLowerCase()) || r.os.toLowerCase().includes(search.toLowerCase()));
  const totalQty = rows.reduce((s,r) => s + r.qty, 0);
  const totalValue = rows.reduce((s,r) => s + r.value, 0);
  // top products by qty
  const byProd = {};
  rows.forEach(r => { byProd[r.prodId] = byProd[r.prodId] || { name: r.prodName, qty: 0, value: 0 }; byProd[r.prodId].qty += r.qty; byProd[r.prodId].value += r.value; });
  const top = Object.entries(byProd).sort((a,b) => b[1].qty - a[1].qty).slice(0, 5);
  const maxTop = top[0]?.[1].qty || 1;

  return (
    <div className="px-6 mt-3 space-y-4">
      {/* KPI strip */}
      <div className="rounded-md bg-white border border-stone-200 shadow-sm flex divide-x divide-stone-200 overflow-hidden">
        <KPI label="Lançamentos" value={rows.length} emphasize sub={<span>OS com produto consumido</span>} />
        <KPI label="Itens consumidos" value={totalQty.toLocaleString("pt-BR")} sub={<span>unidades · 30d</span>} />
        <KPI label="Faturamento" value={fmtBRLshort(totalValue)} tone="pos" sub={<><PI.TrendUp size={11} className="text-emerald-600" /><span>preço de balcão</span></>} />
        <KPI label="Top produto" value={top[0] ? top[0][1].qty : 0} sub={<span className="truncate">{top[0]?.[1].name || "—"}</span>} />
      </div>

      <div className="grid grid-cols-3 gap-4">
        {/* Top products */}
        <div className="col-span-1 rounded-md bg-white border border-stone-200 shadow-sm p-5">
          <div className="flex items-baseline justify-between mb-3">
            <div className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Top 5 · 30d</div>
            <div className="text-[11px] text-stone-400">por unidades</div>
          </div>
          <div className="space-y-3">
            {top.map(([id, t], i) => (
              <div key={id}>
                <div className="flex items-baseline gap-2 text-[12.5px]">
                  <span className="font-mono text-[10.5px] text-stone-400 w-6">#{i+1}</span>
                  <span className="font-medium truncate flex-1">{t.name}</span>
                  <span className="num text-stone-700">{t.qty}</span>
                </div>
                <div className="mt-1 ml-6 h-1 rounded-full bg-stone-100 overflow-hidden">
                  <div className="h-full bg-stone-700" style={{ width: ((t.qty / maxTop) * 100) + "%" }} />
                </div>
                <div className="ml-6 mt-1 text-[10.5px] text-stone-500 num">{fmtBRL(t.value)}</div>
              </div>
            ))}
          </div>
        </div>

        {/* Timeline */}
        <div className="col-span-2 rounded-md bg-white border border-stone-200 shadow-sm overflow-hidden">
          <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
            <div className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Lançamentos recentes</div>
            <div className="inline-flex bg-stone-100/80 rounded-md p-0.5 border border-stone-200">
              {[["7d","7d"],["30d","30d"],["qtd","Trimestre"]].map(([id,l]) => (
                <button key={id} onClick={() => setPeriod(id)} className={`h-6 px-2.5 rounded text-[11.5px] transition-colors duration-150 ${period === id ? "bg-white shadow-sm text-stone-900 font-medium" : "text-stone-600 hover:text-stone-900"}`}>{l}</button>
              ))}
            </div>
            <div className="ml-auto relative">
              <PI.Search size={13} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-stone-400 pointer-events-none" />
              <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Filtrar…" className="h-7 pr-3 w-[180px] rounded-md border border-stone-200 bg-white text-[12px]" style={{ paddingLeft: 28 }} />
            </div>
          </div>
          <div className="max-h-[460px] overflow-y-auto nice-scroll">
            <table className="w-full text-left">
              <thead className="text-[10.5px] uppercase tracking-widest text-stone-500 font-medium sticky top-0 bg-stone-50">
                <tr className="border-b border-stone-200">
                  <th className="pl-5 pr-3 py-2 w-24">Data</th>
                  <th className="pr-3 py-2 w-24">OS</th>
                  <th className="pr-3 py-2">Produto</th>
                  <th className="pr-3 py-2 w-44">Cliente</th>
                  <th className="pr-3 py-2 w-16 text-right">Qtd</th>
                  <th className="pr-5 py-2 w-28 text-right">Valor</th>
                </tr>
              </thead>
              <tbody>
                {rows.slice(0, 60).map(r => (
                  <tr key={r.os + r.prodId} className="border-b border-stone-100 row-hover" style={{ height: 36 }}>
                    <td className="pl-5 pr-3 num text-[12px] text-stone-700">{r.date.slice(8)} abr</td>
                    <td className="pr-3 font-mono text-[11.5px] text-sky-700">{r.os}</td>
                    <td className="pr-3 text-[12.5px] font-medium truncate">{r.prodName}</td>
                    <td className="pr-3 text-[12px] text-stone-600 truncate">{r.client}</td>
                    <td className="pr-3 num text-[12.5px] text-right">{r.qty}</td>
                    <td className="pr-5 num text-[12.5px] text-right font-medium">{fmtBRL(r.value)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
};

const TelaPlaceholder = ({ title }) => (
  <div className="mx-6 mt-3 px-6 py-16 rounded-md bg-white border border-dashed border-stone-200 text-center">
    <div className="text-[14px] font-semibold text-stone-700">{title}</div>
    <div className="text-[12px] text-stone-500 mt-1">Em desenvolvimento</div>
  </div>
);

/* ── App ───────────────────────────────────────────────────────────────── */
const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "density": "comfortable",
  "view": "table",
  "showCost": true
}/*EDITMODE-END*/;

function App() {
  const [tela, setTela]         = useState("produtos");
  const [tab, setTab]           = useState("all");
  const [cat, setCat]           = useState("all");
  const [query, setQuery]       = useState("");
  const [openId, setOpenId]     = useState(null);
  const [selected, setSelected] = useState(new Set());
  // Persist tweaks across reloads via localStorage
  const STORAGE_KEY = "oimpresso.produto.tweaks";
  const initial = useMemo(() => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (raw) return { ...TWEAK_DEFAULTS, ...JSON.parse(raw) };
    } catch {}
    return TWEAK_DEFAULTS;
  }, []);
  const [tweaksRaw, setTweaksRaw] = useTweaks ? useTweaks(initial) : [initial, () => {}];
  const setTweaks = useCallback((keyOrEdits, val) => {
    const edits = typeof keyOrEdits === "object" ? keyOrEdits : { [keyOrEdits]: val };
    setTweaksRaw(edits);
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify({ ...tweaksRaw, ...edits })); } catch {}
  }, [tweaksRaw, setTweaksRaw]);
  const tweaks = tweaksRaw;

  const allRows = PRODUTO_DATA.PROD_LIST;

  const counts = useMemo(() => ({
    all:      allRows.length,
    active:   allRows.filter(r => r.active).length,
    popular:  allRows.filter(r => r.active && r.pop >= 70).length,
    express:  allRows.filter(r => r.active && r.tags.includes("express")).length,
    stale:    allRows.filter(r => r.active && r.uses30 === 0).length,
    inactive: allRows.filter(r => !r.active).length,
  }), [allRows]);

  const filtered = useMemo(() => {
    let out = allRows;
    if (tab === "active")   out = out.filter(r => r.active);
    if (tab === "popular")  out = out.filter(r => r.active && r.pop >= 70);
    if (tab === "express")  out = out.filter(r => r.active && r.tags.includes("express"));
    if (tab === "stale")    out = out.filter(r => r.active && r.uses30 === 0);
    if (tab === "inactive") out = out.filter(r => !r.active);
    if (cat !== "all")      out = out.filter(r => r.cat === cat);
    if (query.trim()) {
      const q = query.toLowerCase();
      out = out.filter(r => r.name.toLowerCase().includes(q) || r.id.toLowerCase().includes(q));
    }
    return out.slice().sort((a,b) => b.pop - a.pop);
  }, [allRows, tab, cat, query]);

  const open = openId ? allRows.find(r => r.id === openId) : null;

  // keyboard
  useEffect(() => {
    const onKey = (e) => {
      if (e.target.tagName === "INPUT" || e.target.tagName === "TEXTAREA") return;
      if (e.key === "Escape") { setOpenId(null); setSelected(new Set()); }
      if (e.key === "n" || e.key === "N") { e.preventDefault(); /* noop new */ }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  const setTweak = (k, v) => setTweaks({ [k]: v });
  const TPanel   = window.TweaksPanel;
  const TSection = window.TweakSection;
  const TRadio   = window.TweakRadio;
  const TToggle  = window.TweakToggle;
  const TSelect  = window.TweakSelect;

  return (
    <div className="flex">
      <Sidebar tela={tela} setTela={setTela} />

      <main className="flex-1 min-w-0">
        <Header telaTitle={CAT_TITLES[tela]} totalCount={counts.all} onCmdK={() => {}} onNew={() => {}} />

        {tela === "produtos" && (
          <>
            <div className="py-5">
              <KPIStrip rows={filtered} allRows={allRows} />
            </div>
            <FilterBar
              tab={tab} setTab={setTab} counts={counts}
              query={query} setQuery={setQuery}
              cat={cat} setCat={setCat}
              view={tweaks.view} setView={(v) => setTweak("view", v)}
            />
            {tweaks.view === "table" ? (
              <ProductTable
                rows={filtered}
                density={tweaks.density}
                selected={selected}
                setSelected={setSelected}
                onOpen={(r) => setOpenId(r.id)}
                openId={openId}
                showCost={tweaks.showCost}
              />
            ) : (
              <ProductGrid rows={filtered} onOpen={(r) => setOpenId(r.id)} openId={openId} showCost={tweaks.showCost} />
            )}
            <div className="px-6 py-4 text-[11.5px] text-stone-500 flex items-center gap-3">
              <span>{filtered.length} de {allRows.length} produtos</span>
              <span className="text-stone-300">·</span>
              <span>ordenado por popularidade</span>
              <span className="ml-auto flex items-center gap-1">
                <kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-white font-mono text-[10px]">↑↓</kbd>
                navegar
                <span className="text-stone-300">·</span>
                <kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-white font-mono text-[10px]">Enter</kbd>
                abrir
                <span className="text-stone-300">·</span>
                <kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-white font-mono text-[10px]">N</kbd>
                novo
              </span>
            </div>
          </>
        )}

        {tela === "categorias" && <TelaCategorias />}
        {tela === "insumos"    && <TelaInsumos />}
        {tela === "tabelas"    && <TelaTabelas />}
        {tela === "historico"  && <TelaHistorico />}
      </main>

      {open && <Drawer row={open} onClose={() => setOpenId(null)} showCost={tweaks.showCost} />}
      {selected.size > 0 && <BulkBar count={selected.size} onClear={() => setSelected(new Set())} />}

      {TPanel && (
        <TPanel title="Tweaks">
          <TSection label="Densidade" />
          <TRadio label="Linha" value={tweaks.density} onChange={(v) => setTweak("density", v)}
            options={[
              { value: "compact",     label: "Densa" },
              { value: "comfortable", label: "Padrão" },
              { value: "spacious",    label: "Ampla" },
            ]} />
          <TSection label="Visualização" />
          <TRadio label="Modo" value={tweaks.view} onChange={(v) => setTweak("view", v)}
            options={[
              { value: "table", label: "Tabela" },
              { value: "grid",  label: "Grade"  },
            ]} />
          <TSection label="Custo / Margem" />
          <TToggle label="Mostrar custo" value={tweaks.showCost} onChange={(v) => setTweak("showCost", v)} />
        </TPanel>
      )}
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<App />);
