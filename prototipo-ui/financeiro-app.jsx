// Financeiro — unified ledger app.
// Single screen: KPI strip → segmented filter → unified table (entrada ↑ / saída ↓ intermixed by date) → drawer detail.
// Density tweak: compact / comfortable / spacious. Persona: Eliana [E].
// IIFE: encapsula tudo, expõe só window.FinanceiroPage.
(() => {
const { useState, useMemo, useEffect, useRef, useCallback } = React;
const I = window.FIN_I; // ícones próprios do Financeiro (coexistem com `I` global do shell)

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

// ──────────────────────────────────────────────────────────────────────────
// FinHero — usa o padrão `.os-page` (canonical do shell) ao invés de Tailwind:
//   .os-page-h  (H1 + p + ações)  +  faixa de sub-rotas com border-bottom.
// ──────────────────────────────────────────────────────────────────────────
const FinHero = ({ tela, setTela, onCmdK, onNew, onDigest, onFechamento, onPresent }) => (
  <>
    <div className="os-page-h">
      <div className="os-page-h-l">
        <h1>Financeiro <span className="fin-hero-title-sub">· {FIN_SUB_TITLES[tela] || "Visão unificada"}</span></h1>
        <p>Maio 2026 · ROTA LIVRE · caixa unificado</p>
      </div>
      <div className="os-page-h-r">
        <button className="os-btn ghost" onClick={onCmdK}>
          <I.Search size={12}/> Buscar
          <kbd style={{ fontFamily: "var(--font-mono)", fontSize: 10, color: "var(--text-mute)", marginLeft: 4 }}>⌘K</kbd>
        </button>
        <button
          className="os-btn ghost fin-btn-ai"
          onClick={onDigest}
          title="Resumo executivo do mês — IA + snapshot">
          ✦ Resumir mês
        </button>
        <button
          className="os-btn ghost fin-btn-trilha"
          onClick={onFechamento}
          title="Trilha de fechamento mensal — 12 passos">
          ☑ Fechamento
        </button>
        <button
          className="os-btn ghost fin-btn-present"
          onClick={onPresent}
          title="Modo apresentação — reunião com sócio">
          ▶ Apresentar
        </button>
        <button
          className={"os-btn ghost" + (tela === "concil" ? " on" : "")}
          onClick={() => setTela(tela === "concil" ? "unified" : "concil")}
          title="Conciliação bancária (extrato Inter)">
          <I.Refresh size={12}/> Conciliar
        </button>
        <button
          className={"os-btn ghost" + (tela === "pcontas" ? " on" : "")}
          onClick={() => setTela(tela === "pcontas" ? "unified" : "pcontas")}
          title="Plano de contas — categorias contábeis">
          <I.Folder size={12}/> Plano de contas
        </button>
        <button className="os-btn ghost" title="Exportar"><I.Download size={12}/></button>
        <button className="os-btn primary" onClick={onNew}><I.Plus size={12}/> Novo lançamento</button>
      </div>
    </div>
  </>
);

// ─── (mantido apenas como referência; não usado mais no shell unificado) ───
const _FinSidebarStandalone = ({ tela, setTela }) => (
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
// KPIStrip refeito no padrão `.os-stats` do shell.
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
    <div className="os-stats fin-stats">
      <div className="os-stat fin-stat-hero">
        <small>Saldo previsto · maio</small>
        <b className="mono">{fmtBRL(k.saldoPrevisto)}</b>
        <span className="fin-stat-hint">
          <b className="mono">{fmtBRL(k.saldoAtual)}</b> realizado · {fmtBRLshort(k.aReceber - k.aPagar)} pendente
        </span>
        {/* Sparkline 30d — saldo projetado até fim do mês */}
        <svg className="fin-spark" viewBox="0 0 200 36" preserveAspectRatio="none" aria-hidden="true">
          <defs>
            <linearGradient id="finSparkG" x1="0" x2="0" y1="0" y2="1">
              <stop offset="0%" stopColor="oklch(0.78 0.13 145)" stopOpacity="0.5"/>
              <stop offset="100%" stopColor="oklch(0.78 0.13 145)" stopOpacity="0"/>
            </linearGradient>
          </defs>
          <path d="M0,30 L15,26 L30,22 L45,20 L60,18 L75,22 L90,16 L105,18 L120,14 L135,12 L150,16 L165,10 L180,12 L200,8 L200,36 L0,36 Z" fill="url(#finSparkG)"/>
          <path d="M0,30 L15,26 L30,22 L45,20 L60,18 L75,22 L90,16 L105,18 L120,14 L135,12 L150,16 L165,10 L180,12 L200,8" stroke="oklch(0.78 0.13 145)" strokeWidth="1.5" fill="none"/>
          <line x1="0" y1="24" x2="200" y2="24" stroke="oklch(0.65 0.01 80)" strokeWidth="0.5" strokeDasharray="2 3" opacity="0.4"/>
        </svg>
      </div>
      <div className="os-stat">
        <small>Recebido</small>
        <b className="mono fin-num-pos">{fmtBRL(k.recebido)}</b>
        <span className="fin-stat-hint">{rows.filter((r) => r.kind === "receivable" && r.paid_at).length} entradas confirmadas</span>
      </div>
      <div className="os-stat">
        <small>A receber</small>
        <b className="mono">{fmtBRL(k.aReceber)}</b>
        <span className="fin-stat-hint"><span className="fin-num-neg mono">{fmtBRL(k.atrasadoRec)}</span> em atraso</span>
      </div>
      <div className="os-stat">
        <small>Pago</small>
        <b className="mono fin-num-neg">{fmtBRL(k.pago)}</b>
        <span className="fin-stat-hint">{rows.filter((r) => r.kind === "payable" && r.paid_at).length} saídas liquidadas</span>
      </div>
      <div className="os-stat">
        <small>A pagar</small>
        <b className="mono">{fmtBRL(k.aPagar)}</b>
        <span className="fin-stat-hint">próx. <b>10 mai · Suprigraf</b></span>
      </div>
    </div>
  );
};

/* ─────────────────────────────────────────────────────────────────────────
 * Filters
 * ─────────────────────────────────────────────────────────────────────── */
/* Ageing visual de "A receber" — barra empilhada por janela de vencimento */
const FinAgeing = ({ rows }) => {
  const k = useMemo(() => {
    const open = rows.filter(r => r.kind === "receivable" && !r.paid_at);
    const total = open.reduce((s, r) => s + r.amount, 0);
    const buckets = { d30: 0, d60: 0, d90: 0, late: 0 };
    for (const r of open) {
      const delta = window.FIN_DAYS_FROM_TODAY(r.due);
      if (delta < 0)         buckets.late += r.amount;
      else if (delta <= 30)  buckets.d30 += r.amount;
      else if (delta <= 60)  buckets.d60 += r.amount;
      else                   buckets.d90 += r.amount;
    }
    const pct = (v) => total > 0 ? Math.round((v / total) * 100) : 0;
    return { total, ...buckets, pd30: pct(buckets.d30), pd60: pct(buckets.d60), pd90: pct(buckets.d90), plate: pct(buckets.late) };
  }, [rows]);
  if (k.total === 0) return null;
  return (
    <div className="fin-ageing">
      <div className="fin-ageing-l">
        <small>A receber · ageing</small>
        <b className="mono">{fmtBRL(k.total)}</b>
      </div>
      <div className="fin-ageing-bar">
        {k.plate > 0 && <div className="seg s4" style={{ flex: k.plate }}>{k.plate}% atraso</div>}
        {k.pd30 > 0 && <div className="seg s1" style={{ flex: k.pd30 }}>{k.pd30}% 0-30d</div>}
        {k.pd60 > 0 && <div className="seg s2" style={{ flex: k.pd60 }}>{k.pd60}% 31-60d</div>}
        {k.pd90 > 0 && <div className="seg s3" style={{ flex: k.pd90 }}>{k.pd90}% 61d+</div>}
      </div>
    </div>
  );
};

const TABS = [
  { id: "all",      label: "Todas" },
  { id: "open",     label: "Aberto" },
  { id: "rec",      label: "Receber" },
  { id: "pay",      label: "Pagar" },
  { id: "received", label: "Recebidas" },
  { id: "paid",     label: "Pagas" },
  { id: "late",     label: "Atraso" },
];

// 3 dimensões do FilterBar refatorado (substitui o tabs antigo)
const FILTER_KIND = [
  { id: "all", label: "Todas" },
  { id: "rec", label: "A receber" },
  { id: "pay", label: "A pagar" },
];
const FILTER_STATE = [
  { id: "any",      label: "Qualquer" },
  { id: "open",     label: "Em aberto" },
  { id: "received", label: "Recebidas" },
  { id: "paid",     label: "Pagas" },
];

const PERIOD_OPTS = ["Maio 2026", "Abril 2026", "Últimos 30 dias", "Trimestre", "Personalizado"];
const CATEGORIES = ["Banner", "Adesivo", "Fachada", "Placa", "Gráfica rápida", "Insumo", "Aluguel", "Utilidade", "Imposto", "Folha", "Serviço"];

// FilterBar refeito no padrão `.os-toolbar` do shell + pills de filtro.
const FilterPill = ({ icon: Icon, label, value }) => (
  <button className="os-btn ghost" style={{ height: 30 }}>
    {Icon && <Icon size={11}/>}
    <span style={{ color: "var(--text-mute)" }}>{label}</span>
    <b style={{ fontWeight: 500, color: "var(--text)" }}>· {value}</b>
    <I.ChevronDown size={10} style={{ color: "var(--text-mute)" }}/>
  </button>
);

const FilterBar = ({ states, setStates, late, setLate, query, setQuery, period, setPeriod, density, setDensity, counts }) => {
  const FILTER_LIFECYCLE = [
    { id: "rec",      label: "A receber",  hue: 145 },
    { id: "received", label: "Recebidas",  hue: 145 },
    { id: "pay",      label: "A pagar",    hue: 25  },
    { id: "paid",     label: "Pagas",      hue: 240 },
  ];
  const allOn = states.size === 0 || states.size === FILTER_LIFECYCLE.length;
  const toggle = (id) => {
    setStates(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id); else next.add(id);
      return next;
    });
  };
  const clear = () => setStates(new Set());

  return (
  <div className="os-toolbar fin-toolbar">
    <div className="fin-filter-group" role="group" aria-label="Estado do lançamento">
      {FILTER_LIFECYCLE.map(s => {
        const on = states.has(s.id);
        return (
          <label key={s.id} className={"fin-filter-cb" + (on ? " on" : "")} style={on ? { ["--cb-hue"]: s.hue } : null}>
            <input type="checkbox" checked={on} onChange={() => toggle(s.id)}/>
            <span className="fin-filter-cb-box"/>
            <span>{s.label}</span>
            {counts[s.id] != null && <span className="fin-filter-ct">{counts[s.id]}</span>}
          </label>
        );
      })}
      {!allOn && (
        <button className="fin-filter-clear" onClick={clear} title="Mostrar todas">Limpar</button>
      )}
    </div>

    <span className="fin-filter-sep"/>

    <label className={"fin-filter-toggle" + (late ? " on warn" : "")}>
      <input type="checkbox" checked={late} onChange={e => setLate(e.target.checked)}/>
      <span>Só atrasados</span>
      {counts.late != null && counts.late > 0 && <span className="fin-filter-ct">{counts.late}</span>}
    </label>

    <div className="os-toolbar-r">
      <div className="fin-density" role="group" aria-label="Densidade">
        <button className={density === "compact"     ? "on" : ""} onClick={() => setDensity("compact")}     title="Compacta — mais linhas visíveis" aria-label="Compacta">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"><line x1="3" y1="4" x2="13" y2="4"/><line x1="3" y1="7" x2="13" y2="7"/><line x1="3" y1="10" x2="13" y2="10"/><line x1="3" y1="13" x2="13" y2="13"/></svg>
        </button>
        <button className={density === "comfortable" ? "on" : ""} onClick={() => setDensity("comfortable")} title="Confortável" aria-label="Confortável">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"><line x1="3" y1="4.5" x2="13" y2="4.5"/><line x1="3" y1="8" x2="13" y2="8"/><line x1="3" y1="11.5" x2="13" y2="11.5"/></svg>
        </button>
        <button className={density === "spacious"    ? "on" : ""} onClick={() => setDensity("spacious")}    title="Espaçosa" aria-label="Espaçosa">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"><line x1="3" y1="5" x2="13" y2="5"/><line x1="3" y1="11" x2="13" y2="11"/></svg>
        </button>
      </div>
      <div className="os-search">
        <I.Search size={12}/>
        <input value={query} onChange={e => setQuery(e.target.value)} placeholder="Filtrar nesta lista…"/>
      </div>
    </div>
  </div>
  );
};

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
  const comments = window.useFinComments ? window.useFinComments() : null;
  const conferido = window.useFinConferido ? window.useFinConferido() : null;
  if (!row) return null;
  const isIn = row.kind === "receivable";
  const settled = !!row.paid_at;
  const delta = window.FIN_DAYS_FROM_TODAY(row.due);
  const Linkify = window.VdLinkify;
  const isConferido = conferido && conferido.has(row.id);

  return (
    <>
      <div onClick={onClose} className="fixed inset-0 z-40 bg-stone-900/20" />
      <aside className="fixed top-0 right-0 z-50 h-screen w-[440px] bg-white border-l border-stone-200 shadow-md drawer-shown flex flex-col">
        <div className="px-5 h-14 flex items-center gap-3 border-b border-stone-200">
          <DirIcon kind={row.kind} status={row.status} size={16} />
          <div className="flex-1 min-w-0">
            <div className="text-[11px] uppercase tracking-widest text-stone-500 font-medium flex items-center gap-2">
              {isIn ? "A receber" : "A pagar"} · {row.id}
              {isConferido && <span className="fin-conf-pill-inline">✓ conferido</span>}
            </div>
            <div className="text-[14px] font-semibold truncate">
              {Linkify ? <Linkify text={row.desc} onPick={(id) => console.log("→", id)}/> : row.desc}
            </div>
          </div>
          <button onClick={onClose} className="w-8 h-8 grid place-items-center rounded text-stone-500 hover:bg-stone-100">
            <I.X size={16} />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto nice-scroll px-5 py-4 space-y-5 text-[13px]">
          {window.FinAiAnomaliaBanner && <window.FinAiAnomaliaBanner row={row}/>}
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
              {window.FinPillFrescor && <window.FinPillFrescor row={row}/>}
            </div>
            {conferido && window.FinConferidoToggle && (
              <div className="mt-3">
                <window.FinConferidoToggle row={row} conferido={conferido}/>
              </div>
            )}
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
              <div className="mt-0.5 text-stone-700 font-mono text-[12px]">
                {Linkify ? <Linkify text={row.invoice} onPick={(id) => console.log("→", id)}/> : row.invoice}
              </div>
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
            {window.FinAuditTrail
              ? <window.FinAuditTrail row={row}/>
              : (
                <>
                  <div className="text-[11px] text-stone-500 uppercase tracking-widest font-medium">Histórico</div>
                  <ol className="mt-2 space-y-2.5 text-[12.5px]">
                    <li>Sem registros</li>
                  </ol>
                </>
              )}
          </div>

          {comments && window.FinCommentsThread && (
            <div className="border-t border-stone-100 pt-4">
              <window.FinCommentsThread rowId={row.id} comments={comments}/>
            </div>
          )}

          {window.FinAiPanel && (
            <div className="border-t border-stone-100 pt-4">
              <window.FinAiPanel row={row}/>
            </div>
          )}
        </div>

        <div className="border-t border-stone-200 px-5 h-14 flex items-center gap-2">
          {window.FinTroubleButton && <window.FinTroubleButton row={row}/>}
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
const FinanceiroPage = ({ initialTela = "unified" }) => {
  const [rows, setRows] = useState(() => window.FIN_ROWS);
  const [tab, setTab] = useState("all");
  // Refino 2026-05-18: 4 checkboxes lifecycle + toggle late (substitui tab)
  const [states, setStates] = useState(() => new Set(["rec","received","pay","paid"]));
  const [late, setLate] = useState(false);
  // Refino #2 IA — digest do mês
  const [digestOpen, setDigestOpen] = useState(false);
  // Refino #3 Guia + Saída
  const [fechamentoOpen, setFechamentoOpen] = useState(false);
  const [presentOpen, setPresentOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [period, setPeriod] = useState("Maio 2026");
  const [selected, setSelected] = useState(new Set());
  const [drawerRow, setDrawerRow] = useState(null);
  const [cmdkOpen, setCmdkOpen] = useState(false);
  // useState local em vez de useTweaks: o shell unificado já tem painel Tweaks próprio.
  const [density, setDensity] = useState("compact");
  const [groupByDate, setGroupByDate] = useState(true);
  const t = { density, groupByDate };
  const [tela, setTela] = useState(initialTela);
  // refletir mudança de rota
  useEffect(() => { setTela(initialTela); }, [initialTela]);

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
    // 4 checkboxes lifecycle: rec/received/pay/paid
    if (states.size > 0 && states.size < 4) {
      r = r.filter(x => {
        const k = x.kind === "receivable"
          ? (x.paid_at ? "received" : "rec")
          : (x.paid_at ? "paid" : "pay");
        return states.has(k);
      });
    } else if (states.size === 0) {
      r = []; // nada marcado = nada exibido
    }
    if (late) r = r.filter(x => !x.paid_at && x.status === "atrasado");
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
  }, [rows, tab, query, states, late]);

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

  const tableRowsDensity = density;

  return (
    <div className="os-page fin-root" data-screen-label="01 Financeiro">
      <FinHero tela={tela} setTela={setTela}
               onCmdK={() => setCmdkOpen(true)}
               onNew={() => {}}
               onDigest={() => setDigestOpen(true)}
               onFechamento={() => setFechamentoOpen(true)}
               onPresent={() => setPresentOpen(true)} />

      <div className="fin-body">
        {tela === "unified" && <>
          <KPIStrip rows={rows} />
          <FilterBar states={states} setStates={setStates} late={late} setLate={setLate} counts={counts} query={query} setQuery={setQuery} period={period} setPeriod={setPeriod} density={density} setDensity={setDensity} />
          <Table rows={filtered} density={tableRowsDensity} selected={selected} setSelected={setSelected} onOpen={setDrawerRow} onMark={handleMark} />
          <FooterBar rows={filtered} selected={selected} onClearSelected={() => setSelected(new Set())} onMarkAll={handleMarkAll} />
        </>}
        {tela === "fluxo"   && <window.TelaFluxo onBack={() => setTela("unified")} />}
        {tela === "concil"  && <window.TelaConciliacao onBack={() => setTela("unified")} />}
        {tela === "dre"     && <window.TelaDRE onBack={() => setTela("unified")} />}
        {tela === "pcontas" && <window.TelaPContas onBack={() => setTela("unified")} />}
      </div>

      <Drawer row={drawerRow} onClose={() => setDrawerRow(null)} onMark={handleMark} />
      <CmdK open={cmdkOpen} onClose={() => setCmdkOpen(false)} rows={rows} onPick={setDrawerRow} />
      {window.FinAiMonthDigest && <window.FinAiMonthDigest open={digestOpen} onClose={() => setDigestOpen(false)} />}
      {window.FinFechamentoTrilha && <window.FinFechamentoTrilha open={fechamentoOpen} onClose={() => setFechamentoOpen(false)} onNavigate={setTela} />}
      {window.FinPresentationMode && <window.FinPresentationMode open={presentOpen} onClose={() => setPresentOpen(false)} />}
    </div>
  );
};

window.FinanceiroPage = FinanceiroPage;
})();
