// Financeiro — unified ledger app.
// Single screen: KPI strip → segmented filter → unified table (entrada ↑ / saída ↓ intermixed by date) → drawer detail.
// Density tweak: compact / comfortable / spacious. Persona: Eliana [E].

const { useState, useMemo, useEffect, useRef, useCallback } = React;

/* ─────────────────────────────────────────────────────────────────────────
 * Format helpers
 * ─────────────────────────────────────────────────────────────────────── */
const fmtBRL = (n) =>
  n.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
const fmtBRLshort = (n) => {
  if (Math.abs(n) >= 1000) return "R$ " + (n / 1000).toLocaleString("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + "k";
  return fmtBRL(n);
};
const fmtDate = (d) =>
  d.toLocaleDateString("pt-BR", { day: "2-digit", month: "2-digit" });
const fmtDateLong = (d) =>
  d.toLocaleDateString("pt-BR", { day: "2-digit", month: "short", year: "numeric" });
const dayLabel = (delta) => {
  if (delta === 0) return "hoje";
  if (delta === 1) return "amanhã";
  if (delta === -1) return "ontem";
  if (delta > 0) return `em ${delta} dias`;
  return `há ${-delta} dias`;
};

/* ─────────────────────────────────────────────────────────────────────────
 * Density spec — driven by tweak
 * ─────────────────────────────────────────────────────────────────────── */
const DENSITY = {
  compact:    { rowH: 32, py: "py-1",   text: "text-[12.5px]", gap: "gap-2", iconBox: 22 },
  comfortable:{ rowH: 44, py: "py-2.5", text: "text-sm",       gap: "gap-3", iconBox: 26 },
  spacious:   { rowH: 56, py: "py-4",   text: "text-sm",       gap: "gap-4", iconBox: 30 },
};

/* ─────────────────────────────────────────────────────────────────────────
 * Status badge
 * ─────────────────────────────────────────────────────────────────────── */
const STATUS_STYLES = {
  recebido: { bg: "bg-emerald-50", fg: "text-emerald-700", dot: "bg-emerald-500", label: "Recebido" },
  pago:     { bg: "bg-emerald-50", fg: "text-emerald-700", dot: "bg-emerald-500", label: "Pago" },
  pendente: { bg: "bg-stone-100",  fg: "text-stone-600",   dot: "bg-stone-400",   label: "Pendente" },
  vencendo: { bg: "bg-amber-50",   fg: "text-amber-700",   dot: "bg-amber-500",   label: "Vencendo" },
  atrasado: { bg: "bg-rose-50",    fg: "text-rose-700",    dot: "bg-rose-500",    label: "Atrasado" },
};
const StatusBadge = ({ status, compact }) => {
  const s = STATUS_STYLES[status];
  return (
    <span className={`inline-flex items-center gap-1.5 ${compact ? "px-1.5 py-px" : "px-2 py-0.5"} rounded-full ${s.bg} ${s.fg} text-[11px] font-medium`}>
      <span className={`w-1.5 h-1.5 rounded-full ${s.dot}`} />
      {s.label}
    </span>
  );
};

/* ─────────────────────────────────────────────────────────────────────────
 * Sidebar (Cockpit V2)
 * ─────────────────────────────────────────────────────────────────────── */
const NAV = [
  { id: "dash",    label: "Dashboard",  icon: I.LayoutDashboard },
  { id: "sells",   label: "Vendas",     icon: I.ShoppingBag },
  { id: "repair",  label: "Repair",     icon: I.Wrench },
  { id: "fin",     label: "Financeiro", icon: I.Wallet, active: true },
  { id: "clients", label: "Clientes",   icon: I.Users },
  { id: "catalog", label: "Catálogo",   icon: I.Box },
  { id: "fiscal",  label: "Fiscal",     icon: I.FileText },
];
const FIN_SUB = [
  { id: "unified", label: "Visão unificada" },
  { id: "fluxo",   label: "Fluxo de caixa" },
  { id: "concil",  label: "Conciliação" },
  { id: "dre",     label: "DRE / Relatórios" },
  { id: "pcontas", label: "Plano de contas" },
];
const FIN_SUB_TITLES = {
  unified: "Visão unificada",
  fluxo:   "Fluxo de caixa",
  concil:  "Conciliação",
  dre:     "DRE / Relatórios",
  pcontas: "Plano de contas",
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
        <I.ChevronLeft size={14} />
      </button>
    </div>

    <nav className="flex-1 nice-scroll overflow-y-auto py-2 text-[13px]">
      {NAV.map((n) => {
        const Icon = n.icon;
        return (
          <div key={n.id}>
            <a
              href="#"
              className={`mx-2 px-2.5 h-8 flex items-center gap-2.5 rounded-md transition-colors duration-150 ${
                n.active
                  ? "bg-stone-100 text-stone-900 font-medium"
                  : "text-stone-600 hover:bg-stone-50 hover:text-stone-900"
              }`}
            >
              <Icon size={16} className={n.active ? "text-stone-900" : "text-stone-500"} />
              <span>{n.label}</span>
              {n.id === "sells" && <span className="ml-auto text-[10px] text-stone-400 num">12</span>}
              {n.id === "repair" && <span className="ml-auto text-[10px] text-stone-400 num">3</span>}
            </a>
            {n.active && (
              <div className="mt-0.5 mb-1.5 ml-7 mr-2 border-l border-stone-200">
                {FIN_SUB.map((s) => (
                  <button
                    key={s.id}
                    onClick={() => setTela(s.id)}
                    className={`w-full text-left pl-3 pr-2 h-7 flex items-center rounded-r-md text-[12.5px] transition-colors duration-150 ${
                      tela === s.id
                        ? "text-stone-900 font-medium border-l-2 -ml-px border-stone-900 bg-stone-50/60"
                        : "text-stone-500 hover:text-stone-800"
                    }`}
                  >
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
      <div className="w-7 h-7 rounded-full bg-stone-200 grid place-items-center text-[11px] font-semibold text-stone-700">EL</div>
      <div className="flex-1 min-w-0">
        <div className="text-[12.5px] font-medium truncate">Eliana Lopes</div>
        <div className="text-[11px] text-stone-500 truncate">Financeiro</div>
      </div>
      <button className="w-7 h-7 grid place-items-center rounded text-stone-500 hover:bg-stone-100">
        <I.Settings size={14} />
      </button>
    </div>
  </aside>
);

/* ─────────────────────────────────────────────────────────────────────────
 * Header (sticky)
 * ─────────────────────────────────────────────────────────────────────── */
const Header = ({ onCmdK, onNew, telaTitle }) => (
  <header className="sticky top-0 z-30 bg-white/85 backdrop-blur border-b border-stone-200">
    <div className="px-6 h-14 flex items-center gap-4">
      <div className="flex items-center gap-1.5 text-[12px] text-stone-500 whitespace-nowrap">
        <span>Financeiro</span>
        <I.ChevronRight size={12} className="text-stone-400" />
        <span className="text-stone-900 font-medium">{telaTitle}</span>
      </div>

      <div className="flex-1" />

      <button
        onClick={onCmdK}
        className="h-8 px-3 flex items-center gap-2 rounded-md border border-stone-200 bg-white text-[12.5px] text-stone-500 hover:text-stone-800 hover:border-stone-300 transition-colors duration-150 w-[200px]"
      >
        <I.Search size={14} />
        <span className="truncate">Buscar lançamento…</span>
        <span className="ml-auto flex items-center gap-1 text-[11px] text-stone-400 font-mono">
          <kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-stone-50">⌘</kbd>
          <kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-stone-50">K</kbd>
        </span>
      </button>

      <button className="h-8 w-8 grid place-items-center rounded-md text-stone-500 hover:bg-stone-100 relative shrink-0">
        <I.Bell size={16} />
        <span className="absolute top-1.5 right-1.5 w-1.5 h-1.5 rounded-full bg-rose-500" />
      </button>

      <div className="h-5 w-px bg-stone-200 shrink-0" />

      <button className="h-8 px-3 flex items-center gap-1.5 rounded-md border border-stone-200 text-[12.5px] text-stone-700 hover:bg-stone-50 transition-colors duration-150 shrink-0 whitespace-nowrap">
        <I.Refresh size={14} />
        Conciliar
      </button>
      <button className="h-8 w-8 grid place-items-center rounded-md border border-stone-200 text-stone-700 hover:bg-stone-50 transition-colors duration-150 shrink-0" title="Exportar">
        <I.Download size={14} />
      </button>
      <button
        onClick={onNew}
        className="h-8 px-3 flex items-center gap-1.5 rounded-md bg-stone-900 text-white text-[12.5px] hover:bg-stone-800 transition-colors duration-150 shrink-0 whitespace-nowrap"
      >
        <I.Plus size={14} />
        Novo
      </button>
    </div>

    <div className="px-6 pt-4 pb-3 flex items-baseline gap-3">
      <h1 className="text-[24px] font-semibold tracking-tight leading-none whitespace-nowrap">Financeiro</h1>
      <span className="text-[11px] uppercase tracking-widest text-stone-500 font-medium whitespace-nowrap">Maio 2026 · ROTA LIVRE</span>
      <div className="ml-auto text-[12px] text-stone-500 flex items-center gap-1.5 whitespace-nowrap shrink-0">
        <I.Calendar size={13} />
        <button className="text-stone-700 hover:text-stone-900 underline-offset-2 hover:underline">09 mai 2026</button>
      </div>
    </div>
  </header>
);

/* ─────────────────────────────────────────────────────────────────────────
 * KPI strip
 * ─────────────────────────────────────────────────────────────────────── */
const KPI = ({ label, value, sub, tone = "default", emphasize = false }) => {
  const toneClass = {
    default: "text-stone-900",
    pos: "text-emerald-700",
    neg: "text-rose-700",
  }[tone];
  return (
    <div className={`flex-1 px-5 py-4 ${emphasize ? "bg-stone-900 text-stone-50" : "bg-white"}`}>
      <div className={`text-[10px] uppercase tracking-widest font-medium ${emphasize ? "text-stone-400" : "text-stone-500"}`}>
        {label}
      </div>
      <div className={`mt-1 text-[28px] leading-none font-semibold tracking-tight num ${emphasize ? "text-stone-50" : toneClass}`}>
        {value}
      </div>
      {sub && (
        <div className={`mt-2 text-[11.5px] ${emphasize ? "text-stone-400" : "text-stone-500"} flex items-center gap-1.5`}>
          {sub}
        </div>
      )}
    </div>
  );
};

const KPIStrip = ({ rows }) => {
  const k = useMemo(() => {
    const recebido = rows.filter((r) => r.kind === "receivable" && r.paid_at).reduce((s, r) => s + r.amount, 0);
    const pago     = rows.filter((r) => r.kind === "payable"    && r.paid_at).reduce((s, r) => s + r.amount, 0);
    const aReceber = rows.filter((r) => r.kind === "receivable" && !r.paid_at).reduce((s, r) => s + r.amount, 0);
    const aPagar   = rows.filter((r) => r.kind === "payable"    && !r.paid_at).reduce((s, r) => s + r.amount, 0);
    const atrasadoRec = rows.filter((r) => r.kind === "receivable" && !r.paid_at && r.status === "atrasado").reduce((s, r) => s + r.amount, 0);
    const saldoAtual = recebido - pago;
    const saldoPrevisto = saldoAtual + aReceber - aPagar;
    return { recebido, pago, aReceber, aPagar, atrasadoRec, saldoAtual, saldoPrevisto };
  }, [rows]);

  return (
    <div className="mx-6 rounded-md bg-white border border-stone-200 shadow-sm flex divide-x divide-stone-200 overflow-hidden">
      <KPI
        label="Saldo previsto · maio"
        value={fmtBRL(k.saldoPrevisto)}
        sub={
          <>
            <span className="text-stone-700 font-medium">{fmtBRL(k.saldoAtual)}</span>
            <span>realizado</span>
            <span className="ml-1 text-stone-400">+</span>
            <span>{fmtBRLshort(k.aReceber - k.aPagar)} pendente</span>
          </>
        }
        emphasize
      />
      <KPI
        label="Recebido"
        value={fmtBRL(k.recebido)}
        tone="pos"
        sub={<><I.ArrowUp size={11} className="text-emerald-600" /><span>{rows.filter((r) => r.kind === "receivable" && r.paid_at).length} entradas confirmadas</span></>}
      />
      <KPI
        label="A receber"
        value={fmtBRL(k.aReceber)}
        sub={
          <>
            <span className="text-rose-700 font-medium">{fmtBRL(k.atrasadoRec)}</span>
            <span>em atraso</span>
          </>
        }
      />
      <KPI
        label="Pago"
        value={fmtBRL(k.pago)}
        tone="neg"
        sub={<><I.ArrowDown size={11} className="text-rose-600" /><span>{rows.filter((r) => r.kind === "payable" && r.paid_at).length} saídas liquidadas</span></>}
      />
      <KPI
        label="A pagar"
        value={fmtBRL(k.aPagar)}
        sub={
          <>
            <span>próx. vencimento </span>
            <span className="text-stone-700 font-medium">10 mai · Suprigraf</span>
          </>
        }
      />
    </div>
  );
};

/* ─────────────────────────────────────────────────────────────────────────
 * Filters
 * ─────────────────────────────────────────────────────────────────────── */
const TABS = [
  { id: "all",      label: "Todas" },
  { id: "open",     label: "Aberto" },
  { id: "rec",      label: "Receber" },
  { id: "pay",      label: "Pagar" },
  { id: "received", label: "Recebidas" },
  { id: "paid",     label: "Pagas" },
  { id: "late",     label: "Atraso" },
];

const PERIOD_OPTS = ["Maio 2026", "Abril 2026", "Últimos 30 dias", "Trimestre", "Personalizado"];
const CATEGORIES = ["Banner", "Adesivo", "Fachada", "Placa", "Gráfica rápida", "Insumo", "Aluguel", "Utilidade", "Imposto", "Folha", "Serviço"];

const Pill = ({ icon: Icon, label, value, onClick, active }) => (
  <button
    onClick={onClick}
    className={`h-8 px-2.5 flex items-center gap-1.5 rounded-md text-[12.5px] border transition-colors duration-150 ${
      active
        ? "border-stone-300 bg-stone-50 text-stone-900"
        : "border-stone-200 bg-white text-stone-600 hover:text-stone-900 hover:border-stone-300"
    }`}
  >
    {Icon && <Icon size={13} className="text-stone-500" />}
    <span>{label}</span>
    {value && <span className="text-stone-900 font-medium">· {value}</span>}
    <I.ChevronDown size={12} className="text-stone-400 -mr-0.5" />
  </button>
);

const FilterBar = ({ tab, setTab, counts, query, setQuery, period, setPeriod }) => (
  <div className="px-6 pt-4 pb-3 flex items-center gap-3 flex-wrap">
    <div className="inline-flex bg-stone-100/80 rounded-md p-0.5 border border-stone-200">
      {TABS.map((t) => (
        <button
          key={t.id}
          onClick={() => setTab(t.id)}
          className={`h-7 px-3 rounded text-[12.5px] flex items-center gap-1.5 transition-colors duration-150 ${
            tab === t.id
              ? "bg-white shadow-sm text-stone-900 font-medium"
              : "text-stone-600 hover:text-stone-900"
          }`}
        >
          {t.label}
          <span className={`text-[10.5px] num ${tab === t.id ? "text-stone-500" : "text-stone-400"}`}>{counts[t.id]}</span>
        </button>
      ))}
    </div>

    <div className="h-6 w-px bg-stone-200" />

    <Pill icon={I.Calendar} label="Período" value={period} onClick={() => setPeriod((p) => PERIOD_OPTS[(PERIOD_OPTS.indexOf(p) + 1) % PERIOD_OPTS.length])} />
    <Pill icon={I.Tag} label="Categoria" value="Todas" />
    <Pill icon={I.Building} label="Contraparte" value="Todas" />
    <Pill icon={I.Bank} label="Conta" value="Itaú PJ · 4521" />

    <div className="ml-auto relative">
      <I.Search size={13} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-stone-400 pointer-events-none" />
      <input
        value={query}
        onChange={(e) => setQuery(e.target.value)}
        placeholder="Filtrar nesta lista…"
        className="h-8 pl-7.5 pr-3 w-[220px] rounded-md border border-stone-200 bg-white text-[12.5px] placeholder:text-stone-400 focus:border-stone-400"
        style={{ paddingLeft: 28 }}
      />
    </div>
  </div>
);

/* ─────────────────────────────────────────────────────────────────────────
 * Unified table
 * ─────────────────────────────────────────────────────────────────────── */
const DirIcon = ({ kind, status, size = 14 }) => {
  const isIn = kind === "receivable";
  const Icon = isIn ? I.ArrowDownLeft : I.ArrowUpRight;
  // Inverted geometry: receivable = arrow IN to wallet (down-left), payable = arrow OUT (up-right)
  const tone = isIn
    ? (status === "recebido" ? "bg-emerald-50 text-emerald-700" : "bg-emerald-50/60 text-emerald-700/80")
    : (status === "pago" ? "bg-rose-50 text-rose-700" : "bg-rose-50/60 text-rose-700/80");
  return (
    <span className={`inline-grid place-items-center rounded ${tone}`} style={{ width: size + 8, height: size + 8 }}>
      <Icon size={size} strokeWidth={2} />
    </span>
  );
};

const Row = ({ row, density, selected, onSelect, onOpen, onMark, dim }) => {
  const isIn = row.kind === "receivable";
  const dens = DENSITY[density];
  const delta = window.FIN_DAYS_FROM_TODAY(row.due);
  const settled = !!row.paid_at;

  return (
    <tr
      className={`border-b border-stone-100 row-hover cursor-pointer ${selected ? "row-selected" : ""} ${dim ? "opacity-55" : ""}`}
      style={{ height: dens.rowH }}
      onClick={() => onOpen(row)}
    >
      <td className={`pl-6 pr-2 ${dens.py}`} onClick={(e) => { e.stopPropagation(); onSelect(row.id); }}>
        <input
          type="checkbox"
          checked={selected}
          readOnly
          className="w-3.5 h-3.5 rounded border-stone-300 accent-stone-900"
        />
      </td>
      <td className={`px-2 ${dens.py} ${dens.text} text-stone-700 num whitespace-nowrap`}>
        <div className="flex items-center gap-2">
          <span className="font-medium">{fmtDate(row.due)}</span>
          <span className={`text-[11px] ${
            settled ? "text-stone-400" :
            row.status === "atrasado" ? "text-rose-600" :
            row.status === "vencendo" ? "text-amber-600" :
            "text-stone-400"
          }`}>
            {settled ? `pago ${fmtDate(row.paid_at)}` : dayLabel(delta)}
          </span>
        </div>
      </td>
      <td className={`px-2 ${dens.py}`}>
        <DirIcon kind={row.kind} status={row.status} size={density === "compact" ? 12 : 14} />
      </td>
      <td className={`px-2 ${dens.py} ${dens.text}`}>
        <div className="flex items-center gap-2">
          <span className="font-medium text-stone-900 truncate max-w-[280px]">{row.desc}</span>
          {row.invoice && <span className="text-[10.5px] text-stone-400 font-mono">{row.invoice}</span>}
        </div>
      </td>
      <td className={`px-2 ${dens.py} ${dens.text} text-stone-700 truncate max-w-[180px]`}>
        {row.party}
      </td>
      <td className={`px-2 ${dens.py} ${dens.text} text-stone-500`}>
        <span className="inline-flex items-center gap-1.5">
          <span className="w-1.5 h-1.5 rounded-full bg-stone-300" />
          {row.category}
        </span>
      </td>
      <td className={`px-2 ${dens.py} ${dens.text}`}>
        <StatusBadge status={row.status} compact={density === "compact"} />
      </td>
      <td className={`px-2 ${dens.py} ${dens.text} text-right num font-medium whitespace-nowrap ${
        isIn ? "text-emerald-700" : "text-stone-900"
      }`}>
        <span className="text-stone-400 mr-0.5">{isIn ? "+" : "−"}</span>
        {fmtBRL(row.amount).replace("R$", "").trim()}
      </td>
      <td className={`pl-2 pr-4 ${dens.py} text-right`} onClick={(e) => e.stopPropagation()}>
        <div className="inline-flex items-center gap-1">
          {!settled && (
            <button
              onClick={() => onMark(row.id)}
              title={isIn ? "Marcar como recebido" : "Marcar como pago"}
              className="h-7 px-2 inline-flex items-center gap-1 rounded text-[11.5px] text-emerald-700 hover:bg-emerald-50 transition-colors duration-150"
            >
              <I.Check size={13} />
              <span>{isIn ? "Recebi" : "Paguei"}</span>
            </button>
          )}
          <button className="w-7 h-7 grid place-items-center rounded text-stone-400 hover:bg-stone-100 hover:text-stone-700">
            <I.More size={14} />
          </button>
        </div>
      </td>
    </tr>
  );
};

const Table = ({ rows, density, selected, setSelected, onOpen, onMark }) => {
  const dens = DENSITY[density];
  // Group by date for subtle date headers — only for comfortable/spacious; compact omits to save vertical space.
  const showDateHeaders = density !== "compact";
  const groups = useMemo(() => {
    const m = new Map();
    for (const r of rows) {
      const key = r.due.toISOString().slice(0, 10);
      if (!m.has(key)) m.set(key, []);
      m.get(key).push(r);
    }
    return [...m.entries()];
  }, [rows]);

  return (
    <div className="mx-6 mt-2 bg-white border border-stone-200 rounded-md shadow-sm overflow-hidden">
      <table className="w-full border-collapse">
        <thead>
          <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/40">
            <th className="pl-6 pr-2 py-2 w-8 text-left">
              <input
                type="checkbox"
                checked={selected.size > 0 && selected.size === rows.length}
                ref={(el) => { if (el) el.indeterminate = selected.size > 0 && selected.size < rows.length; }}
                onChange={() => {
                  if (selected.size === rows.length) setSelected(new Set());
                  else setSelected(new Set(rows.map((r) => r.id)));
                }}
                className="w-3.5 h-3.5 rounded border-stone-300 accent-stone-900"
              />
            </th>
            <th className="px-2 py-2 text-left font-medium">Vencimento</th>
            <th className="px-2 py-2 w-8 text-left font-medium"></th>
            <th className="px-2 py-2 text-left font-medium">Lançamento</th>
            <th className="px-2 py-2 text-left font-medium">Contraparte</th>
            <th className="px-2 py-2 text-left font-medium">Categoria</th>
            <th className="px-2 py-2 text-left font-medium">Status</th>
            <th className="px-2 py-2 text-right font-medium">Valor</th>
            <th className="pl-2 pr-4 py-2 w-[110px] text-right font-medium"></th>
          </tr>
        </thead>
        <tbody>
          {showDateHeaders ? (
            groups.map(([key, gr]) => {
              const date = new Date(key + "T12:00:00");
              const delta = window.FIN_DAYS_FROM_TODAY(date);
              const todayish = delta === 0;
              return (
                <React.Fragment key={key}>
                  <tr className="bg-stone-50/60 border-b border-stone-100">
                    <td colSpan={10} className="px-6 py-1.5">
                      <div className="text-[10.5px] uppercase tracking-widest text-stone-500 font-medium flex items-center gap-2">
                        <span>{fmtDateLong(date)}</span>
                        <span className="text-stone-400 normal-case tracking-normal text-[11px]">· {dayLabel(delta)}{todayish ? " · hoje" : ""}</span>
                      </div>
                    </td>
                  </tr>
                  {gr.map((r) => (
                    <Row
                      key={r.id}
                      row={r}
                      density={density}
                      selected={selected.has(r.id)}
                      onSelect={(id) => setSelected((s) => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; })}
                      onOpen={onOpen}
                      onMark={onMark}
                      dim={!!r.paid_at}
                    />
                  ))}
                </React.Fragment>
              );
            })
          ) : (
            rows.map((r) => (
              <Row
                key={r.id}
                row={r}
                density={density}
                selected={selected.has(r.id)}
                onSelect={(id) => setSelected((s) => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; })}
                onOpen={onOpen}
                onMark={onMark}
                dim={!!r.paid_at}
              />
            ))
          )}
        </tbody>
      </table>

      {rows.length === 0 && (
        <div className="py-16 text-center text-stone-500 text-sm">
          Nenhum lançamento bate com esses filtros.
        </div>
      )}
    </div>
  );
};

/* ─────────────────────────────────────────────────────────────────────────
 * Footer (sticky) — batch actions + summary
 * ─────────────────────────────────────────────────────────────────────── */
const FooterBar = ({ rows, selected, onClearSelected, onMarkAll }) => {
  const selRows = rows.filter((r) => selected.has(r.id));
  const totalIn  = selRows.filter((r) => r.kind === "receivable").reduce((s, r) => s + r.amount, 0);
  const totalOut = selRows.filter((r) => r.kind === "payable").reduce((s, r) => s + r.amount, 0);

  return (
    <div className="sticky bottom-0 z-20 mx-6 mb-4 mt-3 rounded-md border border-stone-200 bg-white shadow-sm flex items-center px-4 h-12 text-[12.5px]">
      {selRows.length === 0 ? (
        <>
          <div className="flex items-center gap-4 text-stone-500">
            <span><span className="text-stone-900 font-medium num">{rows.length}</span> lançamentos</span>
            <span className="h-3 w-px bg-stone-200" />
            <span>Total entrada: <span className="text-emerald-700 font-medium num">{fmtBRL(rows.filter((r) => r.kind === "receivable").reduce((s, r) => s + r.amount, 0))}</span></span>
            <span>Total saída: <span className="text-stone-900 font-medium num">{fmtBRL(rows.filter((r) => r.kind === "payable").reduce((s, r) => s + r.amount, 0))}</span></span>
          </div>
          <div className="ml-auto text-[11.5px] text-stone-400 font-mono flex items-center gap-2">
            <kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-stone-50 text-stone-600">J</kbd>
            <kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-stone-50 text-stone-600">K</kbd>
            <span>navegar</span>
            <span className="text-stone-300">·</span>
            <kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-stone-50 text-stone-600">␣</kbd>
            <span>marcar pago/recebido</span>
            <span className="text-stone-300">·</span>
            <kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-stone-50 text-stone-600">/</kbd>
            <span>buscar</span>
          </div>
        </>
      ) : (
        <>
          <div className="flex items-center gap-3">
            <span className="text-stone-900 font-medium num">{selRows.length} selecionados</span>
            <span className="h-3 w-px bg-stone-200" />
            {totalIn > 0 && <span className="text-emerald-700 num">+ {fmtBRL(totalIn)}</span>}
            {totalOut > 0 && <span className="text-stone-900 num">− {fmtBRL(totalOut)}</span>}
          </div>
          <div className="ml-auto flex items-center gap-2">
            <button onClick={onClearSelected} className="h-8 px-3 rounded-md border border-stone-200 text-stone-600 hover:bg-stone-50 transition-colors duration-150">Limpar</button>
            <button className="h-8 px-3 rounded-md border border-stone-200 text-stone-700 hover:bg-stone-50 transition-colors duration-150">Editar em lote</button>
            <button className="h-8 px-3 rounded-md border border-stone-200 text-stone-700 hover:bg-stone-50 transition-colors duration-150 inline-flex items-center gap-1.5"><I.Download size={13}/>Exportar</button>
            <button onClick={onMarkAll} className="h-8 px-3 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white transition-colors duration-150 inline-flex items-center gap-1.5">
              <I.Check size={13} />
              Liquidar selecionados
            </button>
          </div>
        </>
      )}
    </div>
  );
};

/* ─────────────────────────────────────────────────────────────────────────
 * Drawer — detail of one transaction
 * ─────────────────────────────────────────────────────────────────────── */
const Drawer = ({ row, onClose, onMark }) => {
  if (!row) return null;
  const isIn = row.kind === "receivable";
  const settled = !!row.paid_at;
  const delta = window.FIN_DAYS_FROM_TODAY(row.due);

  return (
    <>
      <div onClick={onClose} className="fixed inset-0 z-40 bg-stone-900/20" />
      <aside className="fixed top-0 right-0 z-50 h-screen w-[440px] bg-white border-l border-stone-200 shadow-md drawer-shown flex flex-col">
        <div className="px-5 h-14 flex items-center gap-3 border-b border-stone-200">
          <DirIcon kind={row.kind} status={row.status} size={16} />
          <div className="flex-1 min-w-0">
            <div className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">{isIn ? "A receber" : "A pagar"} · {row.id}</div>
            <div className="text-[14px] font-semibold truncate">{row.desc}</div>
          </div>
          <button onClick={onClose} className="w-8 h-8 grid place-items-center rounded text-stone-500 hover:bg-stone-100">
            <I.X size={16} />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto nice-scroll px-5 py-4 space-y-5 text-[13px]">
          <div>
            <div className={`text-[11px] uppercase tracking-widest font-medium ${
              row.status === "atrasado" ? "text-rose-700" : row.status === "vencendo" ? "text-amber-700" : "text-stone-500"
            }`}>
              {settled ? "Liquidado" : "Vencimento"}
            </div>
            <div className="mt-1 flex items-baseline gap-2">
              <div className="text-[22px] font-semibold tracking-tight num">{fmtDateLong(row.due)}</div>
              <div className="text-[12px] text-stone-500">{settled ? `pago em ${fmtDateLong(row.paid_at)}` : dayLabel(delta)}</div>
            </div>
            <div className="mt-3 flex items-baseline gap-2">
              <div className={`text-[34px] font-semibold tracking-tight num ${isIn ? "text-emerald-700" : "text-stone-900"}`}>
                {isIn ? "+ " : "− "}{fmtBRL(row.amount)}
              </div>
              <StatusBadge status={row.status} />
            </div>
          </div>

          <div className="border-t border-stone-100 pt-4 grid grid-cols-2 gap-y-3">
            <div>
              <div className="text-[11px] text-stone-500 uppercase tracking-widest font-medium">Contraparte</div>
              <div className="mt-0.5 font-medium text-stone-900">{row.party}</div>
            </div>
            <div>
              <div className="text-[11px] text-stone-500 uppercase tracking-widest font-medium">Categoria</div>
              <div className="mt-0.5 text-stone-700">{row.category}</div>
            </div>
            <div>
              <div className="text-[11px] text-stone-500 uppercase tracking-widest font-medium">Canal</div>
              <div className="mt-0.5 text-stone-700">{row.channel}</div>
            </div>
            <div>
              <div className="text-[11px] text-stone-500 uppercase tracking-widest font-medium">Documento</div>
              <div className="mt-0.5 text-stone-700 font-mono text-[12px]">{row.invoice}</div>
            </div>
            <div className="col-span-2">
              <div className="text-[11px] text-stone-500 uppercase tracking-widest font-medium">Conta</div>
              <div className="mt-0.5 text-stone-700 flex items-center gap-1.5"><I.Bank size={13} className="text-stone-400" />Itaú PJ · ag 0438 · cc 4521-7</div>
            </div>
          </div>

          <div className="border-t border-stone-100 pt-4">
            <div className="text-[11px] text-stone-500 uppercase tracking-widest font-medium">Conciliação extrato</div>
            {settled ? (
              <div className="mt-2 rounded-md border border-emerald-200 bg-emerald-50/60 px-3 py-2.5 flex items-start gap-2.5">
                <I.Link size={14} className="text-emerald-700 mt-0.5" />
                <div className="text-[12.5px]">
                  <div className="text-emerald-800 font-medium">Conciliado com OFX 04392</div>
                  <div className="text-emerald-700/80">{fmtDateLong(row.paid_at)} · {fmtBRL(row.amount)} · 100% match</div>
                </div>
              </div>
            ) : (
              <div className="mt-2 rounded-md border border-stone-200 px-3 py-2.5 text-[12.5px] text-stone-600 flex items-start gap-2.5">
                <I.Sparkles size={14} className="text-stone-500 mt-0.5" />
                <div>
                  Sem match no extrato. Ao liquidar, o sistema procura linhas próximas (±R$ 5,00 e ±2 dias) e sugere conciliação automática.
                </div>
              </div>
            )}
          </div>

          <div className="border-t border-stone-100 pt-4">
            <div className="text-[11px] text-stone-500 uppercase tracking-widest font-medium">Histórico</div>
            <ol className="mt-2 space-y-2.5 text-[12.5px]">
              <li className="flex gap-2.5">
                <span className="mt-1 w-1.5 h-1.5 rounded-full bg-stone-300 shrink-0" />
                <div>
                  <div className="text-stone-700">Lançamento criado a partir da venda <span className="font-mono">#V-2641</span></div>
                  <div className="text-stone-400 text-[11px]">06 mai · Larissa</div>
                </div>
              </li>
              <li className="flex gap-2.5">
                <span className="mt-1 w-1.5 h-1.5 rounded-full bg-stone-300 shrink-0" />
                <div>
                  <div className="text-stone-700">NFe emitida e enviada por e-mail</div>
                  <div className="text-stone-400 text-[11px]">06 mai · automação</div>
                </div>
              </li>
              {settled && (
                <li className="flex gap-2.5">
                  <span className="mt-1 w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0" />
                  <div>
                    <div className="text-stone-900 font-medium">Marcado como {isIn ? "recebido" : "pago"}</div>
                    <div className="text-stone-400 text-[11px]">{fmtDateLong(row.paid_at)} · Eliana</div>
                  </div>
                </li>
              )}
            </ol>
          </div>
        </div>

        <div className="border-t border-stone-200 px-5 h-14 flex items-center gap-2">
          <button className="h-8 px-3 rounded-md border border-stone-200 text-[12.5px] text-stone-700 hover:bg-stone-50 transition-colors duration-150 inline-flex items-center gap-1.5">
            <I.Eye size={13} />Ver NFe
          </button>
          <button className="h-8 px-3 rounded-md border border-stone-200 text-[12.5px] text-stone-700 hover:bg-stone-50 transition-colors duration-150 inline-flex items-center gap-1.5">
            <I.Send size={13} />Cobrar
          </button>
          <div className="ml-auto" />
          {!settled && (
            <button
              onClick={() => { onMark(row.id); onClose(); }}
              className="h-8 px-3.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-[12.5px] inline-flex items-center gap-1.5 transition-colors duration-150"
            >
              <I.Check size={13} />Marcar como {isIn ? "recebido" : "pago"}
            </button>
          )}
        </div>
      </aside>
    </>
  );
};

/* ─────────────────────────────────────────────────────────────────────────
 * Cmd+K palette
 * ─────────────────────────────────────────────────────────────────────── */
const CmdK = ({ open, onClose, rows, onPick }) => {
  const [q, setQ] = useState("");
  const inputRef = useRef(null);
  useEffect(() => { if (open) { setQ(""); setTimeout(() => inputRef.current?.focus(), 0); } }, [open]);

  const ql = q.trim().toLowerCase();
  const matches = !ql ? rows.slice(0, 8) : rows.filter((r) =>
    r.desc.toLowerCase().includes(ql) ||
    r.party.toLowerCase().includes(ql) ||
    r.invoice.toLowerCase().includes(ql) ||
    r.id.toLowerCase().includes(ql) ||
    r.category.toLowerCase().includes(ql)
  ).slice(0, 10);

  if (!open) return null;
  return (
    <div className="fixed inset-0 z-[60] cp-backdrop bg-stone-900/30 grid place-items-start pt-24" onClick={onClose}>
      <div onClick={(e) => e.stopPropagation()} className="w-[560px] bg-white rounded-lg border border-stone-200 shadow-md overflow-hidden">
        <div className="flex items-center gap-3 px-4 h-12 border-b border-stone-200">
          <I.Search size={15} className="text-stone-400" />
          <input
            ref={inputRef}
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Buscar lançamento, cliente, NFe, categoria…"
            className="flex-1 outline-none text-[14px] placeholder:text-stone-400"
          />
          <kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-stone-50 text-[10.5px] text-stone-500 font-mono">Esc</kbd>
        </div>
        <div className="max-h-[360px] overflow-y-auto nice-scroll py-1.5">
          {matches.length === 0 && (
            <div className="px-4 py-8 text-center text-[13px] text-stone-500">Nada encontrado para "{q}".</div>
          )}
          {matches.map((r) => (
            <button
              key={r.id}
              onClick={() => { onPick(r); onClose(); }}
              className="w-full px-4 py-2 flex items-center gap-3 hover:bg-stone-50 text-left transition-colors duration-150"
            >
              <DirIcon kind={r.kind} status={r.status} size={14} />
              <div className="flex-1 min-w-0">
                <div className="text-[13px] font-medium text-stone-900 truncate">{r.desc}</div>
                <div className="text-[11.5px] text-stone-500 truncate">{r.party} · {r.category} · {r.invoice}</div>
              </div>
              <div className={`text-[13px] num font-medium ${r.kind === "receivable" ? "text-emerald-700" : "text-stone-900"}`}>
                {r.kind === "receivable" ? "+" : "−"} {fmtBRL(r.amount)}
              </div>
            </button>
          ))}
        </div>
        <div className="border-t border-stone-200 px-4 h-9 flex items-center justify-between text-[11px] text-stone-500">
          <div className="flex items-center gap-3">
            <span className="flex items-center gap-1"><kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-stone-50 font-mono text-stone-500">↑↓</kbd> navegar</span>
            <span className="flex items-center gap-1"><kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-stone-50 font-mono text-stone-500">↵</kbd> abrir</span>
          </div>
          <span>{matches.length} de {rows.length}</span>
        </div>
      </div>
    </div>
  );
};

/* ─────────────────────────────────────────────────────────────────────────
 * Tweaks panel
 * ─────────────────────────────────────────────────────────────────────── */
const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "density": "comfortable",
  "showWeekend": true,
  "groupByDate": true
}/*EDITMODE-END*/;

const FinTweaks = ({ tweaks, setTweak }) => (
  <TweaksPanel title="Tweaks">
    <TweakSection label="Tabela">
      <TweakRadio
        label="Densidade"
        value={tweaks.density}
        onChange={(v) => setTweak("density", v)}
        options={[
          { value: "compact", label: "Compacta" },
          { value: "comfortable", label: "Confortável" },
          { value: "spacious", label: "Espaçosa" },
        ]}
      />
      <TweakToggle
        label="Agrupar por data"
        value={tweaks.groupByDate}
        onChange={(v) => setTweak("groupByDate", v)}
      />
    </TweakSection>
  </TweaksPanel>
);

/* ─────────────────────────────────────────────────────────────────────────
 * App
 * ─────────────────────────────────────────────────────────────────────── */
const App = () => {
  const [rows, setRows] = useState(() => window.FIN_ROWS);
  const [tab, setTab] = useState("all");
  const [query, setQuery] = useState("");
  const [period, setPeriod] = useState("Maio 2026");
  const [selected, setSelected] = useState(new Set());
  const [drawerRow, setDrawerRow] = useState(null);
  const [cmdkOpen, setCmdkOpen] = useState(false);
  const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);
  const [tela, setTela] = useState("unified");

  // counts per tab
  const counts = useMemo(() => ({
    all: rows.length,
    open: rows.filter((r) => !r.paid_at).length,
    rec: rows.filter((r) => r.kind === "receivable" && !r.paid_at).length,
    pay: rows.filter((r) => r.kind === "payable" && !r.paid_at).length,
    received: rows.filter((r) => r.kind === "receivable" && r.paid_at).length,
    paid: rows.filter((r) => r.kind === "payable" && r.paid_at).length,
    late: rows.filter((r) => !r.paid_at && r.status === "atrasado").length,
  }), [rows]);

  const filtered = useMemo(() => {
    let r = rows;
    if (tab === "open") r = r.filter((x) => !x.paid_at);
    if (tab === "rec") r = r.filter((x) => x.kind === "receivable" && !x.paid_at);
    if (tab === "pay") r = r.filter((x) => x.kind === "payable" && !x.paid_at);
    if (tab === "received") r = r.filter((x) => x.kind === "receivable" && x.paid_at);
    if (tab === "paid") r = r.filter((x) => x.kind === "payable" && x.paid_at);
    if (tab === "late") r = r.filter((x) => !x.paid_at && x.status === "atrasado");
    if (query.trim()) {
      const q = query.toLowerCase();
      r = r.filter((x) =>
        x.desc.toLowerCase().includes(q) ||
        x.party.toLowerCase().includes(q) ||
        x.category.toLowerCase().includes(q) ||
        x.invoice.toLowerCase().includes(q)
      );
    }
    return r;
  }, [rows, tab, query]);

  const handleMark = useCallback((id) => {
    setRows((rs) => rs.map((r) => r.id === id ? { ...r, paid_at: window.FIN_TODAY, status: r.kind === "receivable" ? "recebido" : "pago" } : r));
  }, []);

  const handleMarkAll = useCallback(() => {
    setRows((rs) => rs.map((r) => selected.has(r.id) && !r.paid_at
      ? { ...r, paid_at: window.FIN_TODAY, status: r.kind === "receivable" ? "recebido" : "pago" }
      : r
    ));
    setSelected(new Set());
  }, [selected]);

  // Keyboard: ⌘K, /, Esc
  useEffect(() => {
    const onKey = (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
        e.preventDefault();
        setCmdkOpen(true);
      } else if (e.key === "/" && !["INPUT", "TEXTAREA"].includes(document.activeElement?.tagName)) {
        e.preventDefault();
        setCmdkOpen(true);
      } else if (e.key === "Escape") {
        setCmdkOpen(false);
        setDrawerRow(null);
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  const tableRows = t.groupByDate !== false ? filtered : filtered;
  const density = t.density || "comfortable";

  return (
    <div className="flex min-h-screen">
      <Sidebar tela={tela} setTela={setTela} />
      <main className="flex-1 min-w-0 flex flex-col">
        <Header onCmdK={() => setCmdkOpen(true)} onNew={() => {}} telaTitle={FIN_SUB_TITLES[tela]} />
        {tela === "unified" && <>
          <div className="pt-4">
            <KPIStrip rows={rows} />
          </div>
          <FilterBar tab={tab} setTab={setTab} counts={counts} query={query} setQuery={setQuery} period={period} setPeriod={setPeriod} />
          <Table rows={filtered} density={density} selected={selected} setSelected={setSelected} onOpen={setDrawerRow} onMark={handleMark} />
          <FooterBar rows={filtered} selected={selected} onClearSelected={() => setSelected(new Set())} onMarkAll={handleMarkAll} />
        </>}
        {tela === "fluxo"   && <window.TelaFluxo />}
        {tela === "concil"  && <window.TelaConciliacao />}
        {tela === "dre"     && <window.TelaDRE />}
        {tela === "pcontas" && <window.TelaPContas />}
      </main>

      <Drawer row={drawerRow} onClose={() => setDrawerRow(null)} onMark={handleMark} />
      <CmdK open={cmdkOpen} onClose={() => setCmdkOpen(false)} rows={rows} onPick={setDrawerRow} />
      <FinTweaks tweaks={t} setTweak={setTweak} />
    </div>
  );
};

ReactDOM.createRoot(document.getElementById("root")).render(<App />);
