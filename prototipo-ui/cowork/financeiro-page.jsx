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
  // FX-4 — zero nunca leva sinal (“−0,00” é mentira visual).
  const amtSign = (kind, amount) => amount === 0 ? "" : kind === "receivable" ? "+" : "−";
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
   * Date-field + period (paridade @main: data_campo + presets Dia/Semana/Mês/Ano)
   * ─────────────────────────────────────────────────────────────────────── */
  const MESES = ["jan", "fev", "mar", "abr", "mai", "jun", "jul", "ago", "set", "out", "nov", "dez"];
  const DATE_FIELDS = [
  { id: "venc", label: "Vencimento", get: (r) => r.due || null },
  { id: "emissao", label: "Emissão", get: (r) => r.emissao || null },
  { id: "pgto", label: "Pagamento", get: (r) => r.paid_at || null },
  { id: "comp", label: "Competência", get: (r) => r.competencia || null }];

  const dateFieldGet = (r, id) => (DATE_FIELDS.find((f) => f.id === id) || DATE_FIELDS[0]).get(r);
  const dateFieldLabel = (id) => (DATE_FIELDS.find((f) => f.id === id) || DATE_FIELDS[0]).label;
  const PERIODS = [
  { id: "dia", label: "Dia" },
  { id: "semana", label: "Semana" },
  { id: "mes", label: "Mês" },
  { id: "ano", label: "Ano" },
  { id: "tudo", label: "Tudo" },
  { id: "custom", label: "Personalizado" }];

  function periodWindow(mode, anchor, custom) {
    if (mode === "tudo") return null;
    if (mode === "custom") {
      if (!custom || !custom.from || !custom.to) return null;
      const s = new Date(custom.from + "T00:00:00");
      const e = new Date(custom.to + "T00:00:00");
      if (isNaN(s) || isNaN(e)) return null;
      return [s, new Date(e.getFullYear(), e.getMonth(), e.getDate() + 1)];
    }
    const y = anchor.getFullYear(),m = anchor.getMonth(),d = anchor.getDate();
    if (mode === "dia") return [new Date(y, m, d), new Date(y, m, d + 1)];
    if (mode === "semana") {const wd = (anchor.getDay() + 6) % 7;return [new Date(y, m, d - wd), new Date(y, m, d - wd + 7)];}
    if (mode === "mes") return [new Date(y, m, 1), new Date(y, m + 1, 1)];
    if (mode === "ano") return [new Date(y, 0, 1), new Date(y + 1, 0, 1)];
    return null;
  }
  function shiftAnchor(anchor, mode, dir) {
    const y = anchor.getFullYear(),m = anchor.getMonth(),d = anchor.getDate();
    if (mode === "dia") return new Date(y, m, d + dir);
    if (mode === "semana") return new Date(y, m, d + 7 * dir);
    if (mode === "mes") return new Date(y, m + dir, Math.min(d, 28));
    if (mode === "ano") return new Date(y + dir, m, Math.min(d, 28));
    return anchor;
  }
  function periodLabel(mode, anchor, custom) {
    const y = anchor.getFullYear();
    if (mode === "tudo") return "Todo o período";
    if (mode === "custom") {
      const fmt = (s) => {const dt = new Date(s + "T00:00:00");return isNaN(dt) ? "—" : dt.toLocaleDateString("pt-BR", { day: "2-digit", month: "2-digit", year: "numeric" });};
      if (custom && custom.from && custom.to) return `${fmt(custom.from)} – ${fmt(custom.to)}`;
      return "Selecione as datas";
    }
    if (mode === "ano") return String(y);
    if (mode === "mes") {const mn = MESES[anchor.getMonth()];return mn.charAt(0).toUpperCase() + mn.slice(1) + " " + y;}
    if (mode === "semana") {const w = periodWindow("semana", anchor);const s = w[0];const e = new Date(w[1] - 1);return `Semana ${s.getDate()}–${e.getDate()} ${MESES[e.getMonth()]} ${y}`;}
    if (mode === "dia") return anchor.toLocaleDateString("pt-BR", { day: "2-digit", month: "short", year: "numeric" });
    return "";
  }
  const periodLabelShort = (mode, anchor) => {
    if (mode === "tudo") return "todo período";
    if (mode === "custom") return "personalizado";
    if (mode === "ano") return String(anchor.getFullYear());
    if (mode === "mes") return MESES[anchor.getMonth()] + " " + anchor.getFullYear();
    return periodLabel(mode, anchor).toLowerCase();
  };

  /* ─────────────────────────────────────────────────────────────────────────
   * Density spec — driven by tweak
   * ─────────────────────────────────────────────────────────────────────── */
  const DENSITY = {
    compact: { rowH: 32, py: "py-1", text: "text-[length:var(--fs-3)]", gap: "gap-2", iconBox: 22 },
    comfortable: { rowH: 44, py: "py-2.5", text: "text-[length:var(--fs-4)]", gap: "gap-3", iconBox: 26 },
    spacious: { rowH: 56, py: "py-4", text: "text-[length:var(--fs-4)]", gap: "gap-4", iconBox: 30 }
  };

  /* ─────────────────────────────────────────────────────────────────────────
   * Status badge
   * ─────────────────────────────────────────────────────────────────────── */
  const STATUS_STYLES = {
    recebido: { bg: "bg-[var(--pos-soft)]", fg: "text-[var(--pos)]", dot: "bg-[var(--pos)]", c: "var(--pos)", label: "Recebido" },
    pago: { bg: "bg-[var(--pos-soft)]", fg: "text-[var(--pos)]", dot: "bg-[var(--pos)]", c: "var(--pos)", label: "Pago" },
    pendente: { bg: "bg-[var(--sunken)]", fg: "text-[var(--text-2)]", dot: "bg-[var(--text-3)]", c: "var(--text-3)", label: "Pendente" },
    vencendo: { bg: "bg-[var(--warn-soft)]", fg: "text-[var(--warn)]", dot: "bg-[var(--warn)]", c: "var(--warn)", label: "Vencendo" },
    atrasado: { bg: "bg-[var(--neg-soft)]", fg: "text-[var(--neg)]", dot: "bg-[var(--neg)]", c: "var(--neg)", label: "Atrasado" }
  };
  const StatusBadge = ({ status, compact }) => {
    // Blindagem ([W] fluxo 2026-06-10): status desconhecido NUNCA derruba a tela — cai em tom muted.
    const s = STATUS_STYLES[status] || { bg: "bg-[var(--sunken)]", fg: "text-[var(--text-3)]", dot: "bg-[var(--text-4)]", c: "var(--text-4)", label: status || "—" };
    return (
      <span className={`inline-flex items-center gap-1.5 ${compact ? "px-1.5 py-px" : "px-2 py-0.5"} rounded-full ${s.bg} ${s.fg} text-[length:var(--fs-2)] font-semibold`}
      style={{ border: `1px solid color-mix(in oklch, ${s.c} 22%, transparent)` }}>
      <span className={`w-1.5 h-1.5 rounded-full ${s.dot}`} />
      {s.label}
    </span>);

  };

  /* ─────────────────────────────────────────────────────────────────────────
   * Sidebar (Cockpit V2)
   * ─────────────────────────────────────────────────────────────────────── */
  const NAV = [
  { id: "dash", label: "Dashboard", icon: I.LayoutDashboard },
  { id: "sells", label: "Vendas", icon: I.ShoppingBag },
  { id: "repair", label: "Repair", icon: I.Wrench },
  { id: "fin", label: "Financeiro", icon: I.Wallet, active: true },
  { id: "clients", label: "Clientes", icon: I.Users },
  { id: "catalog", label: "Catálogo", icon: I.Box },
  { id: "fiscal", label: "Fiscal", icon: I.FileText }];

  const FIN_SUB = [
  { id: "unified", label: "Visão unificada" },
  { id: "fluxo", label: "Fluxo de caixa" },
  { id: "concil", label: "Conciliação" },
  { id: "dre", label: "DRE / Relatórios" },
  { id: "pcontas", label: "Plano de contas" },
  { id: "impostos", label: "Impostos & obrigações" }];

  // US-FIN-029 — 3 lentes do header (direção [W] 2026-05-31): dirigem o filtro; chips refinam DENTRO da lente.
  const FIN_LENTES = [
  { id: "caixa", label: "Caixa" },
  { id: "receber", label: "A receber" },
  { id: "pagar", label: "A pagar" }];

  const FIN_SUB_TITLES = {
    unified: "Visão unificada",
    fluxo: "Fluxo de caixa",
    concil: "Conciliação",
    dre: "DRE / Relatórios",
    pcontas: "Plano de contas",
    impostos: "Impostos & obrigações"
  };

  // ──────────────────────────────────────────────────────────────────────────
  // FinHero — usa o padrão `.os-page` (canonical do shell) ao invés de Tailwind:
  //   .os-page-h  (H1 + p + ações)  +  faixa de sub-rotas com border-bottom.
  // ──────────────────────────────────────────────────────────────────────────
  // Menu "···" — ações secundárias suspensas. O header fica só com as lentes + Novo.
  const FinOverflowMenu = ({ items }) => {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    useEffect(() => {
      if (!open) return;
      const onDoc = (e) => {if (ref.current && !ref.current.contains(e.target)) setOpen(false);};
      const onKey = (e) => {if (e.key === "Escape") setOpen(false);};
      document.addEventListener("mousedown", onDoc);
      document.addEventListener("keydown", onKey);
      return () => {document.removeEventListener("mousedown", onDoc);document.removeEventListener("keydown", onKey);};
    }, [open]);
    return (
      <div className="fin-overflow" ref={ref}>
      <button className={"os-btn ghost fin-overflow-btn" + (open ? " on" : "")}
        onClick={() => setOpen((o) => !o)}
        title="Mais ações" aria-haspopup="menu" aria-expanded={open}>
        <I.More size={16} />
      </button>
      {open &&
        <div className="fin-overflow-menu" role="menu">
          {items.map((it, i) => it.sep ?
          <div key={i} className="fin-overflow-sep" /> :

          <button key={i} className="fin-overflow-item" role="menuitem"
          onClick={() => {setOpen(false);it.onClick && it.onClick();}}>
                {it.icon && <it.icon size={15} />}
                <span>{it.label}</span>
                {it.kbd && <kbd className="fin-overflow-kbd">{it.kbd}</kbd>}
              </button>
          )}
        </div>
        }
    </div>);

  };

  // ──────────────────────────────────────────────────────────────────────────
  // FinHero — header enxuto (Wagner 2026-05-31): título + 3 lentes
  // (Caixa · A receber · A pagar) + "Novo lançamento" + menu ···.
  // Sub-páginas (Conciliação, Plano de contas, Fluxo, DRE) saíram daqui pro
  // SIDEBAR. R5 golden: `.os-page-h` reflua sem esmagar o título.
  // ──────────────────────────────────────────────────────────────────────────
  const FinHero = ({ tela, periodText, lente, onLente, onCmdK, onNew, onDigest, onFechamento, onPresent, onExport, onOcr }) =>
  <div className="os-page-h fin-hero">
    <div className="os-page-h-l">
      <h1>Financeiro <span className="fin-hero-title-sub">· {FIN_SUB_TITLES[tela] || "Visão unificada"}</span></h1>
      <p>{periodText} · ROTA LIVRE · caixa unificado</p>
    </div>

    <div className="os-page-h-r">
      {tela === "unified" &&
      <div className="fin-lens-seg" role="group" aria-label="Lente">
          {FIN_LENTES.map((l) =>
        <button key={l.id} className={"fin-lens-btn" + (lente === l.id ? " on" : "")}
        aria-pressed={lente === l.id} onClick={() => onLente(l.id)}>{l.label}</button>
        )}
        </div>
      }
      <button className="os-btn primary" onClick={onNew}><I.Plus size={12} /> Novo título</button>
      <FinOverflowMenu items={[
      { icon: I.Search, label: "Buscar", kbd: "⌘K", onClick: onCmdK },
      { icon: I.Receipt, label: "Ler boleto (OCR)", onClick: onOcr },
      { sep: true },
      { icon: I.Sparkles, label: "Resumir mês", onClick: onDigest },
      { icon: I.Check, label: "Fechamento mensal", onClick: onFechamento },
      { icon: I.Eye, label: "Apresentar", onClick: onPresent },
      { sep: true },
      { icon: I.Printer, label: "Imprimir", onClick: () => {try {window.print();} catch (e) {}} },
      { icon: I.Download, label: "Exportar CSV", onClick: onExport }]
      } />
    </div>
  </div>;


  // ─── (mantido apenas como referência; não usado mais no shell unificado) ───
  const _FinSidebarStandalone = ({ tela, setTela }) =>
  <aside className="w-[220px] shrink-0 bg-[var(--surface)] border-r border-[var(--border)] flex flex-col h-screen sticky top-0">
    <div className="px-4 h-14 flex items-center gap-2 border-b border-[var(--border)]">
      <div className="w-7 h-7 rounded-md bg-[var(--accent)] text-white grid place-items-center font-semibold text-[length:var(--fs-4)] tracking-tight">o</div>
      <div className="flex-1">
        <div className="text-[length:var(--fs-4)] font-semibold leading-tight">oimpresso</div>
        <div className="text-[length:var(--fs-2)] text-[var(--text-2)] leading-tight">ROTA LIVRE</div>
      </div>
      <button className="w-6 h-6 grid place-items-center text-[var(--text-3)] hover:text-[var(--text)] rounded">
        <I.ChevronLeft size={14} />
      </button>
    </div>

    <nav className="flex-1 nice-scroll overflow-y-auto py-2 text-[length:var(--fs-4)]">
      {NAV.map((n) => {
        const Icon = n.icon;
        return (
          <div key={n.id}>
            <a
              href="#"
              className={`mx-2 px-2.5 h-8 flex items-center gap-2.5 rounded-md transition-colors duration-150 ${
              n.active ?
              "bg-[var(--sunken)] text-[var(--text)] font-medium" :
              "text-[var(--text-2)] hover:bg-[var(--sunken)] hover:text-[var(--text)]"}`
              }>
              
              <Icon size={16} className={n.active ? "text-[var(--text)]" : "text-[var(--text-2)]"} />
              <span>{n.label}</span>
              {n.id === "sells" && <span className="ml-auto text-[length:var(--fs-1)] text-[var(--text-3)] num">12</span>}
              {n.id === "repair" && <span className="ml-auto text-[length:var(--fs-1)] text-[var(--text-3)] num">3</span>}
            </a>
            {n.active &&
            <div className="mt-0.5 mb-1.5 ml-7 mr-2 border-l border-[var(--border)]">
                {FIN_SUB.map((s) =>
              <button
                key={s.id}
                onClick={() => setTela(s.id)}
                className={`w-full text-left pl-3 pr-2 h-7 flex items-center rounded-r-md text-[length:var(--fs-3)] transition-colors duration-150 ${
                tela === s.id ?
                "text-[var(--text)] font-medium border-l-2 -ml-px border-[var(--accent)] bg-[var(--sunken)]" :
                "text-[var(--text-2)] hover:text-[var(--text)]"}`
                }>
                
                    {s.label}
                  </button>
              )}
              </div>
            }
          </div>);

      })}
    </nav>

    <div className="border-t border-[var(--border)] p-3 flex items-center gap-2.5">
      <div className="w-7 h-7 rounded-full bg-[var(--border)] grid place-items-center text-[length:var(--fs-2)] font-semibold text-[var(--text)]">EL</div>
      <div className="flex-1 min-w-0">
        <div className="text-[length:var(--fs-3)] font-medium truncate">Eliana Lopes</div>
        <div className="text-[length:var(--fs-2)] text-[var(--text-2)] truncate">Financeiro</div>
      </div>
      <button className="w-7 h-7 grid place-items-center rounded text-[var(--text-2)] hover:bg-[var(--sunken)]">
        <I.Settings size={14} />
      </button>
    </div>
  </aside>;


  /* ─────────────────────────────────────────────────────────────────────────
   * Header (sticky)
   * ─────────────────────────────────────────────────────────────────────── */
  const Header = ({ onCmdK, onNew, telaTitle }) =>
  <header className="sticky top-0 z-30 bg-[var(--surface)]/85 backdrop-blur border-b border-[var(--border)]">
    <div className="px-6 h-14 flex items-center gap-4">
      <div className="flex items-center gap-1.5 text-[length:var(--fs-3)] text-[var(--text-2)] whitespace-nowrap">
        <span>Financeiro</span>
        <I.ChevronRight size={12} className="text-[var(--text-3)]" />
        <span className="text-[var(--text)] font-medium">{telaTitle}</span>
      </div>

      <div className="flex-1" />

      <button
        onClick={onCmdK}
        className="h-8 px-3 flex items-center gap-2 rounded-md border border-[var(--border)] bg-[var(--surface)] text-[length:var(--fs-3)] text-[var(--text-2)] hover:text-[var(--text)] hover:border-[var(--text-3)] transition-colors duration-150 w-[200px]">
        
        <I.Search size={14} />
        <span className="truncate">Buscar lançamento…</span>
        <span className="ml-auto flex items-center gap-1 text-[length:var(--fs-2)] text-[var(--text-3)] font-mono">
          <kbd className="px-1.5 py-0.5 rounded border border-[var(--border)] bg-[var(--sunken)]">⌘</kbd>
          <kbd className="px-1.5 py-0.5 rounded border border-[var(--border)] bg-[var(--sunken)]">K</kbd>
        </span>
      </button>

      <button className="h-8 w-8 grid place-items-center rounded-md text-[var(--text-2)] hover:bg-[var(--sunken)] relative shrink-0">
        <I.Bell size={16} />
        <span className="absolute top-1.5 right-1.5 w-1.5 h-1.5 rounded-full bg-[var(--neg-soft)]0" />
      </button>

      <div className="h-5 w-px bg-[var(--border)] shrink-0" />

      <button className="h-8 px-3 flex items-center gap-1.5 rounded-md border border-[var(--border)] text-[length:var(--fs-3)] text-[var(--text)] hover:bg-[var(--sunken)] transition-colors duration-150 shrink-0 whitespace-nowrap">
        <I.Refresh size={14} />
        Conciliar
      </button>
      <button className="h-8 w-8 grid place-items-center rounded-md border border-[var(--border)] text-[var(--text)] hover:bg-[var(--sunken)] transition-colors duration-150 shrink-0" title="Exportar">
        <I.Download size={14} />
      </button>
      <button
        onClick={onNew}
        className="h-8 px-3 flex items-center gap-1.5 rounded-md bg-[var(--accent)] text-white text-[length:var(--fs-3)] hover:bg-[var(--accent-hi)] transition-colors duration-150 shrink-0 whitespace-nowrap">
        
        <I.Plus size={14} />
        Novo
      </button>
    </div>

    <div className="px-6 pt-4 pb-3 flex items-baseline gap-3">
      <h1 className="text-[length:var(--fs-7)] font-semibold tracking-tight leading-none whitespace-nowrap">Financeiro</h1>
      <span className="text-[length:var(--fs-2)] uppercase tracking-widest text-[var(--text-2)] font-medium whitespace-nowrap">Maio 2026 · ROTA LIVRE</span>
      <div className="ml-auto text-[length:var(--fs-3)] text-[var(--text-2)] flex items-center gap-1.5 whitespace-nowrap shrink-0">
        <I.Calendar size={13} />
        <button className="text-[var(--text)] hover:text-[var(--text)] underline-offset-2 hover:underline">09 mai 2026</button>
      </div>
    </div>
  </header>;


  /* ─────────────────────────────────────────────────────────────────────────
   * KPI strip
   * ─────────────────────────────────────────────────────────────────────── */
  // KPIStrip refeito no padrão `.os-stats` do shell.
  // US-FIN-029: KPI-click seta a lente (hero→caixa · recebíveis→receber · pagáveis→pagar).
  const KPIStrip = ({ rows, periodText, lente, onLente }) => {
    const k = useMemo(() => {
      const recebido = rows.filter((r) => r.kind === "receivable" && r.paid_at).reduce((s, r) => s + r.amount, 0);
      const pago = rows.filter((r) => r.kind === "payable" && r.paid_at).reduce((s, r) => s + r.amount, 0);
      const aReceber = rows.filter((r) => r.kind === "receivable" && !r.paid_at).reduce((s, r) => s + r.amount, 0);
      const aPagar = rows.filter((r) => r.kind === "payable" && !r.paid_at).reduce((s, r) => s + r.amount, 0);
      const atrasadoRec = rows.filter((r) => r.kind === "receivable" && !r.paid_at && r.status === "atrasado").reduce((s, r) => s + r.amount, 0);
      const saldoAtual = recebido - pago;
      const saldoPrevisto = saldoAtual + aReceber - aPagar;
      // próximo pagamento em aberto (por vencimento) — honesto com o período selecionado
      const proxPagar = rows.
      filter((r) => r.kind === "payable" && !r.paid_at).
      sort((a, b) => a.due - b.due)[0] || null;
      const nReceberAberto = rows.filter((r) => r.kind === "receivable" && !r.paid_at).length;
      return { recebido, pago, aReceber, aPagar, atrasadoRec, saldoAtual, saldoPrevisto, proxPagar, nReceberAberto };
    }, [rows]);

    // KPI clicável — mesmo contrato visual .os-stat, com estado de lente ativa
    const statBtn = (lid) => ({
      role: "button", tabIndex: 0,
      onClick: () => onLente && onLente(lid),
      onKeyDown: (e) => {if (e.key === "Enter" || e.key === " ") {e.preventDefault();onLente && onLente(lid);}}
    });
    const on = (lid) => lente === lid ? " fin-stat-on" : "";

    return (
      <div className="os-stats fin-stats">
      <div className={"os-stat fin-stat-hero fin-stat-click" + on("caixa")} {...statBtn("caixa")} title="Lente Caixa — tudo">
        <small>Saldo previsto · {periodText}{k.saldoPrevisto < 0 && <span className="fin-hero-alarm">projeção negativa</span>}</small>
        <b className={"mono" + (k.saldoPrevisto < 0 ? " fin-num-neg" : "")}>{fmtBRL(k.saldoPrevisto)}</b>
        <span className="fin-stat-hint">
          <b className="mono">{fmtBRL(k.saldoAtual)}</b> realizado · {fmtBRLshort(k.aReceber - k.aPagar)} pendente
        </span>
        {/* Sparkline 30d — saldo projetado. Caso 07 (O Adversário): a COR diz a verdade
                           do sinal (verde sobe / vermelho déficit), igual ao @main e ao Caso 03 — não
                           mais verde fixo num saldo negativo. */}
        {(() => {
            const sTone = k.saldoPrevisto < 0 ? "var(--neg)" : "var(--pos)";
            return (
              <svg className="fin-spark" viewBox="0 0 200 36" preserveAspectRatio="none" aria-hidden="true">
              <defs>
                <linearGradient id="finSparkG" x1="0" x2="0" y1="0" y2="1">
                  <stop offset="0%" stopColor={sTone} stopOpacity="0.5" />
                  <stop offset="100%" stopColor={sTone} stopOpacity="0" />
                </linearGradient>
              </defs>
              <path d="M0,30 L15,26 L30,22 L45,20 L60,18 L75,22 L90,16 L105,18 L120,14 L135,12 L150,16 L165,10 L180,12 L200,8 L200,36 L0,36 Z" fill="url(#finSparkG)" />
              <path d="M0,30 L15,26 L30,22 L45,20 L60,18 L75,22 L90,16 L105,18 L120,14 L135,12 L150,16 L165,10 L180,12 L200,8" stroke={sTone} strokeWidth="1.5" fill="none" />
              <line x1="0" y1="24" x2="200" y2="24" stroke="var(--text-4)" strokeWidth="0.5" strokeDasharray="2 3" opacity="0.4" />
            </svg>);

          })()}
      </div>
      <div className={"os-stat fin-stat-click fin-stat-in" + on("receber")} {...statBtn("receber")} title="Lente A receber">
        <small>Recebido</small>
        <b className="mono fin-num-pos">{fmtBRL(k.recebido)}</b>
        <span className="fin-stat-hint">{rows.filter((r) => r.kind === "receivable" && r.paid_at).length} entradas confirmadas</span>
      </div>
      <div className={"os-stat fin-stat-click fin-stat-in" + on("receber")} {...statBtn("receber")} title="Lente A receber">
        <small>A receber</small>
        <b className="mono">{fmtBRL(k.aReceber)}</b>
        <span className="fin-stat-hint">{k.atrasadoRec > 0 ?
            <><span className="fin-num-neg mono">{fmtBRL(k.atrasadoRec)}</span> em atraso</> :
            `${k.nReceberAberto} ${k.nReceberAberto === 1 ? "título" : "títulos"}`}</span>
      </div>
      <div className={"os-stat fin-stat-click fin-stat-out" + on("pagar")} {...statBtn("pagar")} title="Lente A pagar">
        <small>Pago</small>
        <b className="mono fin-num-neg">{fmtBRL(k.pago)}</b>
        <span className="fin-stat-hint">{rows.filter((r) => r.kind === "payable" && r.paid_at).length} saídas liquidadas</span>
      </div>
      <div className={"os-stat fin-stat-click fin-stat-out" + on("pagar")} {...statBtn("pagar")} title="Lente A pagar">
        <small>A pagar</small>
        <b className="mono">{fmtBRL(k.aPagar)}</b>
        <span className="fin-stat-hint">{(() => {
              // FX-3 — “próx.” só para obrigação FUTURA; vencida fala a verdade em tom destructive.
              if (!k.proxPagar) return "nada em aberto";
              const d = window.FIN_DAYS_FROM_TODAY(k.proxPagar.due);
              return d < 0 ?
              <><span className="fin-num-neg">vencida há {-d}{-d === 1 ? " dia" : " dias"}</span> · {k.proxPagar.party}</> :
              <>próx. <b>{fmtDate(k.proxPagar.due)} · {k.proxPagar.party}</b></>;
            })()}</span>
      </div>
    </div>);

  };

  /* ─────────────────────────────────────────────────────────────────────────
   * Filters
   * ─────────────────────────────────────────────────────────────────────── */
  /* Ageing visual de "A receber" — barra empilhada por janela de vencimento */
  const FinAgeing = ({ rows }) => {
    const k = useMemo(() => {
      const open = rows.filter((r) => r.kind === "receivable" && !r.paid_at);
      const total = open.reduce((s, r) => s + r.amount, 0);
      const buckets = { d30: 0, d60: 0, d90: 0, late: 0 };
      for (const r of open) {
        const delta = window.FIN_DAYS_FROM_TODAY(r.due);
        if (delta < 0) buckets.late += r.amount;else
        if (delta <= 30) buckets.d30 += r.amount;else
        if (delta <= 60) buckets.d60 += r.amount;else
        buckets.d90 += r.amount;
      }
      const pct = (v) => total > 0 ? Math.round(v / total * 100) : 0;
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
    </div>);

  };

  const TABS = [
  { id: "all", label: "Todas" },
  { id: "open", label: "Aberto" },
  { id: "rec", label: "Receber" },
  { id: "pay", label: "Pagar" },
  { id: "received", label: "Recebidas" },
  { id: "paid", label: "Pagas" },
  { id: "late", label: "Atraso" }];


  // 3 dimensões do FilterBar refatorado (substitui o tabs antigo)
  const FILTER_KIND = [
  { id: "all", label: "Todas" },
  { id: "rec", label: "A receber" },
  { id: "pay", label: "A pagar" }];

  const FILTER_STATE = [
  { id: "any", label: "Qualquer" },
  { id: "open", label: "Em aberto" },
  { id: "received", label: "Recebidas" },
  { id: "paid", label: "Pagas" }];


  const PERIOD_OPTS = ["Maio 2026", "Abril 2026", "Últimos 30 dias", "Trimestre", "Personalizado"];
  const CATEGORIES = ["Banner", "Adesivo", "Fachada", "Placa", "Gráfica rápida", "Insumo", "Aluguel", "Utilidade", "Imposto", "Folha", "Serviço"];
  // Plano de contas (Onda 12.7 git: substitui o filtro de Categoria livre) — ÁRVORE hierárquica BR (grupo › subconta › conta).
  const PLANO_CONTAS = [
  { code: "3", label: "Receitas", subs: [
    { code: "3.1", label: "Serviços gráficos", leaves: ["Banner", "Adesivo", "Fachada", "Placa", "Gráfica rápida"] },
    { code: "3.2", label: "Outros serviços", leaves: ["Serviço"] }] },
  { code: "4", label: "Despesas", subs: [
    { code: "4.1", label: "Custos diretos", leaves: ["Insumo"] },
    { code: "4.2", label: "Operacionais", leaves: ["Aluguel", "Utilidade", "Folha"] },
    { code: "4.3", label: "Tributos", leaves: ["Imposto"] }] }];
  // Contas bancárias (filtro "Contas" do git). Atribuição determinística por id no mock.
  const CONTAS = [
  { id: "itau", label: "Itaú PJ", detail: "Itaú PJ · ag 0438 · cc 4521-7" },
  { id: "bradesco", label: "Bradesco PJ", detail: "Bradesco PJ · ag 1234 · cc 5678-9" },
  { id: "caixa", label: "Caixa", detail: "Caixa Econ. · ag 0042 · cc 1102-3" },
  { id: "dinheiro", label: "Dinheiro", detail: "Caixa interno · dinheiro" }];
  const contaOf = (r) => CONTAS[[...(r && r.id || "x")].reduce((a, c) => a + c.charCodeAt(0), 0) % CONTAS.length];
  // Forma de pagamento (coluna FORMA, paridade produção). Usa cobrança quando há; senão determinístico.
  const formaOf = (r) => {
    if (r && r.cobranca && r.cobranca.tipo) return r.cobranca.tipo === "pix" ? "PIX" : "Boleto";
    const h = [...(r && r.id || "x")].reduce((a, c) => a + c.charCodeAt(0), 0);
    return r && r.kind === "receivable" ? h % 2 ? "Boleto" : "PIX" : h % 2 ? "Dinheiro" : "Transferência";
  };

  // FilterBar refeito no padrão `.os-toolbar` do shell + pills de filtro.
  const FilterPill = ({ icon: Icon, label, value }) =>
  <button className="os-btn ghost" style={{ height: 30 }}>
    {Icon && <Icon size={11} />}
    <span style={{ color: "var(--text-mute)" }}>{label}</span>
    <b style={{ fontWeight: 500, color: "var(--text)" }}>· {value}</b>
    <I.ChevronDown size={10} style={{ color: "var(--text-mute)" }} />
  </button>;


  const ContasFilter = ({ value, onChange }) => {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    useEffect(() => {
      if (!open) return;
      const onDoc = (e) => {if (ref.current && !ref.current.contains(e.target)) setOpen(false);};
      const onKey = (e) => {if (e.key === "Escape") setOpen(false);};
      document.addEventListener("mousedown", onDoc);
      document.addEventListener("keydown", onKey);
      return () => {document.removeEventListener("mousedown", onDoc);document.removeEventListener("keydown", onKey);};
    }, [open]);
    const count = value.size;
    const label = count === 0 ? "Todas as contas" : count === 1 ? (CONTAS.find((c) => value.has(c.id)) || {}).label : count + " contas";
    const toggle = (id) => {const n = new Set(value);n.has(id) ? n.delete(id) : n.add(id);onChange(n);};
    return (
      <div className="fin-contas-filter" ref={ref}>
        <button type="button" className={"fin-filter-select fin-contas-btn" + (count ? " on" : "")} onClick={() => setOpen((o) => !o)} aria-expanded={open} title="Filtrar por conta bancária (múltipla)">
          <span>{label}</span>
        </button>
        {open &&
        <div className="fin-contas-pop">
          {CONTAS.map((c) =>
          <label key={c.id} className={"fin-contas-opt" + (value.has(c.id) ? " on" : "")}>
            <input type="checkbox" checked={value.has(c.id)} onChange={() => toggle(c.id)} />
            <span className="fin-contas-box" />
            <span className="fin-contas-lbl">{c.label}</span>
          </label>
          )}
          {count > 0 && <button type="button" className="fin-contas-clear" onClick={() => onChange(new Set())}>Limpar seleção</button>}
        </div>
        }
      </div>);

  };

  const FilterBar = ({ lente = "caixa", states, setStates, late, setLate, query, setQuery, period, setPeriod, density, setDensity, counts, planoConta = "all", setPlanoConta = () => {}, contas = new Set(), setContas = () => {} }) => {
    // US-FIN-029: chips refinam DENTRO da lente — fora da lente Caixa só os 2 chips do lado ativo.
    const FILTER_LIFECYCLE = [
    { id: "rec", label: "A receber", hue: 145 },
    { id: "received", label: "Recebidas", hue: 145 },
    { id: "pay", label: "A pagar", hue: 25 },
    { id: "paid", label: "Pagas", hue: 240 }].
    filter((s) =>
    lente === "caixa" ? true :
    lente === "receber" ? s.id === "rec" || s.id === "received" :
    s.id === "pay" || s.id === "paid");
    const allOn = states.size === 0 || states.size === FILTER_LIFECYCLE.length;
    const toggle = (id) => {
      setStates((prev) => {
        const next = new Set(prev);
        if (next.has(id)) next.delete(id);else next.add(id);
        return next;
      });
    };
    const clear = () => setStates(new Set());

    return (
      <div className="os-toolbar fin-toolbar" data-comment-anchor="d563f8837b-div-554-7">
    <div className="fin-filter-group" role="group" aria-label="Estado do lançamento">
      {FILTER_LIFECYCLE.map((s) => {
            const on = states.has(s.id);
            return (
              <label key={s.id} className={"fin-filter-cb" + (on ? " on" : "")} style={{ ["--cb-hue"]: s.hue }}>
            <input type="checkbox" checked={on} onChange={() => toggle(s.id)} />
            <span className="fin-filter-cb-box" />
            <span>{s.label}</span>
            {counts[s.id] != null && <span className="fin-filter-ct">{counts[s.id]}</span>}
          </label>);

          })}
      {!allOn &&
          <button className="fin-filter-clear" onClick={clear} title="Mostrar todas">Limpar</button>
          }
    </div>

    <span className="fin-filter-sep" />

    <label className={"fin-filter-toggle" + (late ? " on warn" : "")} style={{ ["--cb-hue"]: 25 }}>
      <input type="checkbox" checked={late} onChange={(e) => setLate(e.target.checked)} />
      <span>Só atrasados</span>
      {counts.late != null && counts.late > 0 && <span className="fin-filter-ct">{counts.late}</span>}
    </label>

    <span className="fin-filter-sep" />

    <ContasFilter value={contas} onChange={setContas} />

    <select className={"fin-filter-select" + (planoConta !== "all" ? " on" : "")} value={planoConta}
    onChange={(e) => setPlanoConta(e.target.value)} aria-label="Plano de contas" title="Filtrar por plano de contas (árvore)">
      <option value="all">Todo o plano de contas</option>
      {PLANO_CONTAS.map((g) =>
      <optgroup key={g.code} label={g.code + " · " + g.label}>
        {g.subs.map((sub) => [
        <option key={sub.code} disabled>{sub.code + " " + sub.label}</option>,
        ...sub.leaves.map((l) => <option key={l} value={l}>{"\u2002\u2002" + l}</option>)]
        )}
      </optgroup>
      )}
    </select>

    <div className="os-toolbar-r">
      <div className="fin-density" role="group" aria-label="Densidade">
        <button className={density === "compact" ? "on" : ""} onClick={() => setDensity("compact")} title="Compacta — mais linhas visíveis" aria-label="Compacta">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"><line x1="3" y1="4" x2="13" y2="4" /><line x1="3" y1="7" x2="13" y2="7" /><line x1="3" y1="10" x2="13" y2="10" /><line x1="3" y1="13" x2="13" y2="13" /></svg>
        </button>
        <button className={density === "comfortable" ? "on" : ""} onClick={() => setDensity("comfortable")} title="Confortável" aria-label="Confortável">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"><line x1="3" y1="4.5" x2="13" y2="4.5" /><line x1="3" y1="8" x2="13" y2="8" /><line x1="3" y1="11.5" x2="13" y2="11.5" /></svg>
        </button>
      </div>
      <div className="os-search">
        <I.Search size={12} />
        <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Filtrar nesta lista…" />
      </div>
    </div>
  </div>);

  };

  /* ─────────────────────────────────────────────────────────────────────────
   * PeriodBar — campo de data + presets + navegação de período (âncora de ano)
   * ─────────────────────────────────────────────────────────────────────── */
  const PeriodBar = ({ dateField, setDateField, period, setPeriod, anchor, setAnchor, count, fields = DATE_FIELDS, countLabel = "lanç.", customRange = { from: "", to: "" }, setCustomRange = () => {} }) =>
  <div className="fin-periodbar" data-comment-anchor="9f1216ceb6-div-590-3">
    <div className="fin-pb-field">
      <span className="fin-pb-cap">Filtrar por</span>
      <div className="fin-pb-seg" role="group" aria-label="Campo de data">
        {fields.map((f) =>
        <button key={f.id} className={"fin-pb-segbtn" + (dateField === f.id ? " on" : "")}
        onClick={() => setDateField(f.id)} aria-pressed={dateField === f.id}>{f.label}</button>
        )}
      </div>
    </div>

    <div className="fin-pb-nav">
      {period === "custom" ?
      <div className="fin-pb-custom">
        <input type="date" className="fin-pb-date" value={customRange.from} max={customRange.to || undefined}
        onChange={(e) => setCustomRange((r) => ({ ...r, from: e.target.value }))} aria-label="Data inicial" />
        <span className="fin-pb-date-sep">até</span>
        <input type="date" className="fin-pb-date" value={customRange.to} min={customRange.from || undefined}
        onChange={(e) => setCustomRange((r) => ({ ...r, to: e.target.value }))} aria-label="Data final" />
      </div> :
      <>
        <button className="fin-pb-arrow" disabled={period === "tudo"}
        onClick={() => setAnchor((a) => shiftAnchor(a, period, -1))} aria-label="Período anterior"><I.ChevronLeft size={15} /></button>
        <span className="fin-pb-label">{periodLabel(period, anchor, customRange)}</span>
        <button className="fin-pb-arrow" disabled={period === "tudo"}
        onClick={() => setAnchor((a) => shiftAnchor(a, period, 1))} aria-label="Próximo período"><I.ChevronRight size={15} /></button>
      </>
      }
      {count != null && <span className="fin-pb-count">{count} {countLabel}</span>}
    </div>

    <div className="fin-pb-presets" role="group" aria-label="Período">
      {PERIODS.map((p) =>
      <button key={p.id} className={"fin-pb-preset" + (period === p.id ? " on" : "")}
      onClick={() => setPeriod(p.id)} aria-pressed={period === p.id}>{p.label}</button>
      )}
    </div>
  </div>;


  // Exporta o PeriodBar + helpers de período pra reuso cross-módulo (ex: Cobrança recorrente).
  window.FinPeriodBar = PeriodBar;
  window.finPeriodWindow = periodWindow;
  window.finPeriodLabel = periodLabel;

  /* ─────────────────────────────────────────────────────────────────────────
   * Unified table
   * ─────────────────────────────────────────────────────────────────────── */
  const DirIcon = ({ kind, status, size = 14 }) => {
    const isIn = kind === "receivable";
    const Icon = isIn ? I.ArrowDownLeft : I.ArrowUpRight;
    // Inverted geometry: receivable = arrow IN to wallet (down-left), payable = arrow OUT (up-right)
    const c = isIn ? "var(--pos)" : "var(--neg)";
    const bg = isIn ? "var(--pos-soft)" : "var(--neg-soft)";
    return (
      <span className="inline-grid place-items-center rounded-full" style={{ width: size + 8, height: size + 8, background: bg, color: c, border: `1px solid color-mix(in oklch, ${c} 22%, transparent)`, boxShadow: `0 1px 3px -1px color-mix(in oklch, ${c} 28%, transparent)` }}>
      <Icon size={size - 1} strokeWidth={2} />
    </span>);

  };

  const Row = ({ row, density, selected, onSelect, onOpen, onMark, dim, dateField = "venc" }) => {
    const isIn = row.kind === "receivable";
    const dens = DENSITY[density];
    const delta = window.FIN_DAYS_FROM_TODAY(row.due);
    const settled = !!row.paid_at;
    const primary = dateFieldGet(row, dateField) || row.due;
    const showYear = primary.getFullYear() !== window.FIN_TODAY.getFullYear();
    // ONDA 2 P2 (Victor/Saarinen) — acento de AÇÃO na linha: a Eliana acha os que pedem
    // ação entre 23 sem abrir cada um. Atrasado = neg · vencendo (≤3d) = warn · resto = nada.
    const actCls = settled ? "" : row.status === "atrasado" ? " fin-row-act-neg" : delta <= 3 ? " fin-row-act-warn" : "";

    return (
      <tr
        className={`border-b border-[var(--hairline)] row-hover cursor-pointer ${selected ? "row-selected" : ""} ${dim ? "opacity-55" : ""}${actCls}`}
        style={{ height: dens.rowH }}
        onClick={() => onOpen(row)}>
        
      <td className={`pl-6 pr-2 ${dens.py}`} onClick={(e) => {e.stopPropagation();onSelect(row.id);}}>
        <input
            type="checkbox"
            checked={selected}
            readOnly
            className="w-3.5 h-3.5 rounded border-[var(--border)] accent-[var(--accent)]" />
          
      </td>
      <td className={`px-2 ${dens.py} ${dens.text} text-[var(--text)] num whitespace-nowrap`}>
        <div className="flex items-center gap-2">
          <span className="font-medium">{fmtDate(primary)}{showYear ? <span className="text-[var(--text-3)]">/{String(primary.getFullYear()).slice(2)}</span> : null}</span>
          <span className={`text-[length:var(--fs-2)] ${
            settled ? "text-[var(--text-3)]" :
            row.status === "atrasado" ? "text-[var(--neg)]" :
            row.status === "vencendo" ? "text-[var(--warn)]" :
            "text-[var(--text-3)]"}`
            }>
            {settled ?
              `pago ${fmtDate(row.paid_at)}` :
              dateField === "venc" ? dayLabel(delta) : `vence ${fmtDate(row.due)}`}
          </span>
        </div>
      </td>
      <td className={`px-2 ${dens.py}`}>
        <DirIcon kind={row.kind} status={row.status} size={density === "compact" ? 12 : 14} />
      </td>
      <td className={`px-2 ${dens.py} ${dens.text}`}>
        <div className="flex items-center gap-2">
          <span className="font-medium text-[var(--text)] truncate max-w-[280px]">{row.desc}</span>
          {row.invoice && <span className="text-[length:var(--fs-1)] text-[var(--text-3)] font-mono">{row.invoice}</span>}
          {row.cobranca && !settled &&
            <span className="fin-cob-tag" title={`Cobrança emitida · ${row.cobranca.tipo === "pix" ? "PIX" : "boleto"}`}>
              <span className="fin-cob-dot" />{row.cobranca.tipo === "pix" ? "PIX" : "Boleto"}
            </span>
            }
        </div>
      </td>
      <td className={`px-2 ${dens.py} ${dens.text} text-[var(--text)] truncate max-w-[180px]`}>
        {row.party}
      </td>
      <td className={`px-2 ${dens.py} ${dens.text} text-[var(--text-2)]`}>
        <span className="inline-flex items-center gap-1.5">
          <span className="w-1.5 h-1.5 rounded-full bg-[var(--border)]" />
          {row.category}
        </span>
      </td>
      <td className={`px-2 ${dens.py} ${dens.text} text-[var(--text-2)] whitespace-nowrap`}>
        <span className="inline-flex items-center gap-1.5">
          <span className="w-1.5 h-1.5 rounded-full bg-[var(--text-4)]" />
          {formaOf(row)}
        </span>
      </td>
      <td className={`px-2 ${dens.py} ${dens.text} text-[var(--text-2)] truncate max-w-[150px]`}>
        <span title={contaOf(row).detail}>{contaOf(row).label}</span>
      </td>
      <td className={`px-2 ${dens.py} ${dens.text} num text-[var(--text-3)] whitespace-nowrap`}>
        {settled ? fmtDate(row.paid_at) : "—"}
      </td>
      <td className={`px-2 ${dens.py} ${dens.text}`}>
        <StatusBadge status={row.status} compact={density === "compact"} />
      </td>
      <td className={`px-2 ${dens.py} ${dens.text} text-right num font-medium whitespace-nowrap ${
        isIn ? "text-[var(--pos)]" : "text-[var(--text)]"}`
        }>
        <span className="text-[var(--text-3)] mr-0.5">{amtSign(row.kind, row.amount)}</span>
        {fmtBRL(row.amount).replace("R$", "").trim()}
      </td>
      <td className={`pl-2 pr-4 ${dens.py} text-right`} onClick={(e) => e.stopPropagation()}>
        <div className="inline-flex items-center gap-1">
          {!settled &&
            <button
              onClick={() => onMark(row.id)}
              title={isIn ? "Marcar como recebido" : "Marcar como pago"}
              className="h-7 px-2 inline-flex items-center gap-1 rounded text-[length:var(--fs-2)] text-[var(--accent)] hover:bg-[var(--accent-soft)] transition-colors duration-150">
              
              <I.Check size={13} />
              <span>{isIn ? "Recebi" : "Paguei"}</span>
            </button>
            }
          <button className="w-7 h-7 grid place-items-center rounded text-[var(--text-3)] hover:bg-[var(--sunken)] hover:text-[var(--text)]">
            <I.More size={14} />
          </button>
        </div>
      </td>
    </tr>);

  };

  const Table = ({ rows, density, selected, setSelected, onOpen, onMark, dateField = "venc", emptyPeriod, onShowAll }) => {
    const dens = DENSITY[density];
    // Group by the selected date field for subtle date headers (year is the anchor).
    const showDateHeaders = density !== "compact";
    const groups = useMemo(() => {
      const m = new Map();
      for (const r of rows) {
        const dv = dateFieldGet(r, dateField) || r.due;
        const key = dv.toISOString().slice(0, 10);
        if (!m.has(key)) m.set(key, []);
        m.get(key).push(r);
      }
      return [...m.entries()].sort((a, b) => a[0] < b[0] ? -1 : 1);
    }, [rows, dateField]);

    return (
      <div className="fin-table-card mx-6 mt-2 bg-[var(--surface)] border border-[var(--border)] rounded-[12px] overflow-hidden">
      <table className="w-full border-collapse">
        <thead>
          <tr className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] border-b border-[var(--border)] bg-[var(--sunken)]">
            <th className="pl-6 pr-2 py-2 w-8 text-left">
              <input
                  type="checkbox"
                  checked={selected.size > 0 && selected.size === rows.length}
                  ref={(el) => {if (el) el.indeterminate = selected.size > 0 && selected.size < rows.length;}}
                  onChange={() => {
                    if (selected.size === rows.length) setSelected(new Set());else
                    setSelected(new Set(rows.map((r) => r.id)));
                  }}
                  className="w-3.5 h-3.5 rounded border-[var(--border)] accent-[var(--accent)]" />
                
            </th>
            <th className="px-2 py-2 text-left font-medium">{dateFieldLabel(dateField)}</th>
            <th className="px-2 py-2 w-8 text-left font-medium"></th>
            <th className="px-2 py-2 text-left font-medium">Lançamento</th>
            <th className="px-2 py-2 text-left font-medium">Contraparte</th>
            <th className="px-2 py-2 text-left font-medium">Categoria</th>
            <th className="px-2 py-2 text-left font-medium">Forma</th>
            <th className="px-2 py-2 text-left font-medium">Conta</th>
            <th className="px-2 py-2 text-left font-medium">Baixa</th>
            <th className="px-2 py-2 text-left font-medium">Status</th>
            <th className="px-2 py-2 text-right font-medium">Valor</th>
            <th className="pl-2 pr-4 py-2 w-[110px] text-right font-medium"></th>
          </tr>
        </thead>
        <tbody>
          {showDateHeaders ?
            groups.map(([key, gr]) => {
              const date = new Date(key + "T12:00:00");
              const delta = window.FIN_DAYS_FROM_TODAY(date);
              const todayish = delta === 0;
              return (
                <React.Fragment key={key}>
                  <tr className="bg-[var(--sunken)] border-b border-[var(--hairline)]">
                    <td colSpan={10} className="px-6 py-1.5">
                      <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium flex items-center gap-2">
                        <span>{fmtDateLong(date)}</span>
                        <span className="text-[var(--text-3)] normal-case tracking-normal text-[length:var(--fs-2)]">· {dayLabel(delta)}{todayish ? " · hoje" : ""}</span>
                      </div>
                    </td>
                  </tr>
                  {gr.map((r) =>
                  <Row
                    key={r.id}
                    row={r}
                    density={density}
                    selected={selected.has(r.id)}
                    onSelect={(id) => setSelected((s) => {const n = new Set(s);n.has(id) ? n.delete(id) : n.add(id);return n;})}
                    onOpen={onOpen}
                    onMark={onMark}
                    dim={!!r.paid_at}
                    dateField={dateField} />

                  )}
                </React.Fragment>);

            }) :

            rows.map((r) =>
            <Row
              key={r.id}
              row={r}
              density={density}
              selected={selected.has(r.id)}
              onSelect={(id) => setSelected((s) => {const n = new Set(s);n.has(id) ? n.delete(id) : n.add(id);return n;})}
              onOpen={onOpen}
              onMark={onMark}
              dim={!!r.paid_at}
              dateField={dateField} />

            )
            }
        </tbody>
      </table>

      {rows.length === 0 &&
        <div className="py-16 text-center">
          <div className="text-[var(--text-2)] text-[length:var(--fs-4)]">
            Nenhum lançamento{emptyPeriod ? <> em <b className="text-[var(--text)]">{emptyPeriod}</b></> : null} com os filtros atuais.
          </div>
          {onShowAll &&
          <button onClick={onShowAll}
          className="mt-3 h-8 px-3 inline-flex items-center gap-1.5 rounded-md border border-[var(--border)] text-[length:var(--fs-3)] text-[var(--text)] hover:bg-[var(--sunken)] transition-colors duration-150">
              <I.Calendar size={13} /> Ver todo o período
            </button>
          }
        </div>
        }
    </div>);

  };

  /* ─────────────────────────────────────────────────────────────────────────
   * Footer (sticky) — batch actions + summary
   * ─────────────────────────────────────────────────────────────────────── */
  const FooterBar = ({ rows, selected, onClearSelected, onMarkAll }) => {
    const selRows = rows.filter((r) => selected.has(r.id));
    const totalIn = selRows.filter((r) => r.kind === "receivable").reduce((s, r) => s + r.amount, 0);
    const totalOut = selRows.filter((r) => r.kind === "payable").reduce((s, r) => s + r.amount, 0);

    return (
      <div className="fin-footbar sticky bottom-0 z-20 mx-6 mb-4 mt-3 rounded-[12px] border border-[var(--border)] bg-[var(--surface)] flex items-center px-4 h-12 text-[length:var(--fs-3)]">
      {selRows.length === 0 ?
        <>
          <div className="flex items-center gap-4 text-[var(--text-2)]">
            <span><span className="text-[var(--text)] font-medium num">{rows.length}</span> lançamentos</span>
            <span className="h-3 w-px bg-[var(--border)]" />
            <span>Total entrada: <span className="text-[var(--pos)] font-medium num">{fmtBRL(rows.filter((r) => r.kind === "receivable").reduce((s, r) => s + r.amount, 0))}</span></span>
            <span>Total saída: <span className="text-[var(--text)] font-medium num">{fmtBRL(rows.filter((r) => r.kind === "payable").reduce((s, r) => s + r.amount, 0))}</span></span>
          </div>
          <div className="ml-auto text-[length:var(--fs-2)] text-[var(--text-3)] font-mono flex items-center gap-2">
            <kbd className="px-1.5 py-0.5 rounded border border-[var(--border)] bg-[var(--sunken)] text-[var(--text-2)]">J</kbd>
            <kbd className="px-1.5 py-0.5 rounded border border-[var(--border)] bg-[var(--sunken)] text-[var(--text-2)]">K</kbd>
            <span>navegar</span>
            <span className="text-[var(--text-4)]">·</span>
            <kbd className="px-1.5 py-0.5 rounded border border-[var(--border)] bg-[var(--sunken)] text-[var(--text-2)]">␣</kbd>
            <span>marcar pago/recebido</span>
            <span className="text-[var(--text-4)]">·</span>
            <kbd className="px-1.5 py-0.5 rounded border border-[var(--border)] bg-[var(--sunken)] text-[var(--text-2)]">/</kbd>
            <span>buscar</span>
          </div>
        </> :

        <>
          <div className="flex items-center gap-3">
            <span className="text-[var(--text)] font-medium num">{selRows.length} selecionados</span>
            <span className="h-3 w-px bg-[var(--border)]" />
            {totalIn > 0 && <span className="text-[var(--pos)] num">+ {fmtBRL(totalIn)}</span>}
            {totalOut > 0 && <span className="text-[var(--text)] num">− {fmtBRL(totalOut)}</span>}
          </div>
          <div className="ml-auto flex items-center gap-2">
            <button onClick={onClearSelected} className="h-8 px-3 rounded-md border border-[var(--border)] text-[var(--text-2)] hover:bg-[var(--sunken)] transition-colors duration-150">Limpar</button>
            <button className="h-8 px-3 rounded-md border border-[var(--border)] text-[var(--text)] hover:bg-[var(--sunken)] transition-colors duration-150">Editar em lote</button>
            <button className="h-8 px-3 rounded-md border border-[var(--border)] text-[var(--text)] hover:bg-[var(--sunken)] transition-colors duration-150 inline-flex items-center gap-1.5"><I.Download size={13} />Exportar</button>
            <button onClick={onMarkAll} className="h-8 px-3 rounded-md bg-[var(--accent)] hover:bg-[var(--accent-hi)] text-white transition-colors duration-150 inline-flex items-center gap-1.5">
              <I.Check size={13} />
              Liquidar selecionados
            </button>
          </div>
        </>
        }
    </div>);

  };

  /* ─────────────────────────────────────────────────────────────────────────
   * Drawer — lentes de domínio (Vínculos · Conciliação · Fiscal · Cobrança)
   * ─────────────────────────────────────────────────────────────────────── */
  // Linha digitável sintética (determinística pelo id+valor) — só pra demo de boleto.
  function linhaDigitavel(row) {
    const n = String(row.id).replace(/\D/g, "").padStart(5, "0").slice(0, 5);
    const v = Math.round(row.amount * 100).toString().padStart(7, "0").slice(0, 7);
    return `34191.79001 01043.510047 ${n}.150008 1 9877${v}`;
  }

  // PIX copia-e-cola sintético (determinístico) — só pra demo.
  function pixCopiaECola(row) {
    const n = String(row.id).replace(/\D/g, "").padStart(4, "0").slice(0, 4);
    const v = row.amount.toFixed(2);
    return `00020126580014br.gov.bcb.pix0136oimpresso-${n}-rota-livre52040000530398654${String(v.length).padStart(2, "0")}${v}5802BR5909ROTALIVRE6009SAO PAULO62070503***6304A1F2`;
  }

  const LensSection = ({ icon: Icon, title, status, tone = "muted", hue = "accent", children }) =>
  <section className="fin-lens py-4">
    <header className="flex items-center gap-2 mb-2.5">
      {Icon && <span className={`fin-lens-ic fin-lens-ic-${hue}`}><Icon size={12} /></span>}
      <h4 className={`text-[length:var(--fs-3)] font-semibold text-[var(--text)] fin-lens-h4 fin-lens-h4-${hue}`}>{title}</h4>
      {status && <span className="ml-auto"><StatusChip tone={tone}>{status}</StatusChip></span>}
    </header>
    {children}
  </section>;


  // Chip de status mini — consistente em todas as lentes (tom semântico, com VIDA: chroma via color-mix).
  const StatusChip = ({ children, tone = "muted" }) => {
    const cls = {
      pos: "fin-chip-pos",
      neg: "fin-chip-neg",
      warn: "fin-chip-warn",
      info: "fin-chip-info",
      muted: "fin-chip-muted"
    }[tone] || "fin-chip-muted";
    return <span className={`inline-flex items-center h-[19px] px-2 rounded-full text-[length:var(--fs-1)] font-semibold ${cls}`}>{children}</span>;
  };

  // R1 (Stripe) — valor copiável: ⧉ aparece no hover, 1 clique copia, feedback ✓.
  const CopyVal = ({ text, children }) => {
    const [ok, setOk] = useState(false);
    return (
      <button type="button" className={"fin-copyval" + (ok ? " ok" : "")} title="Copiar"
      onClick={(e) => {
        e.stopPropagation();
        try {navigator.clipboard && navigator.clipboard.writeText(String(text));} catch (err) {}
        setOk(true);setTimeout(() => setOk(false), 1400);
      }}>
      <span className="fin-copyval-txt">{children || text}</span>
      <span className="fin-copyval-ic">{ok ? "✓" : "⧉"}</span>
    </button>);

  };

  // Linha chave-valor — backbone de densidade (gabarito Prova Viva 9.75: label mudo EM CIMA,
  // valor firme embaixo — empilhado rende 2× mais densidade no grid do que label-esq/valor-dir).
  const KV = ({ label, children, mono, copy }) =>
  <div className="min-w-0 py-[3px]">
    <div className="text-[length:var(--fs-1)] uppercase tracking-[0.08em] text-[var(--text-3)] leading-[1.5]">{label}</div>
    {copy != null ?
    <div className={`text-[length:var(--fs-4)] text-[var(--text)] font-medium leading-[22px] ${mono ? "num tabular-nums" : ""}`}><CopyVal text={copy}>{children}</CopyVal></div> :
    <div className={`text-[length:var(--fs-4)] text-[var(--text)] font-medium leading-[22px] truncate ${mono ? "num tabular-nums" : ""}`}>{children}</div>}
  </div>;


  // R2 (Attio/Linear) — campo editável NO LUGAR: select vestido de valor; hover revela a borda.
  // O painel ✎ continua existindo pra edição em massa; isto cobre o gesto de 1 campo.
  const KVEdit = ({ label, value, was, options, onChange }) => {
    const changed = was != null && value !== was;
    const opts = options.includes(value) ? options : [value, ...options];
    return (
      <div className="min-w-0 py-[3px] fin-kvedit">
      <div className="text-[length:var(--fs-1)] uppercase tracking-[0.08em] text-[var(--text-3)] leading-[1.5]">{label}{changed && <span className="fin-kvedit-dot" title={`era ${was}`}> ✎</span>}</div>
      {/* Espelho invisível: dimensiona o wrapper pelo VALOR ATUAL — select width:100% por cima.
                         (width:auto em <select> mede pela opção mais larga da lista → chevron voava. Verifier 06-11.) */}
      <span className="fin-kvedit-wrap">
        <span aria-hidden="true" className="fin-kvedit-mirror">{value}</span>
        <select value={value} onChange={(e) => onChange(e.target.value)} title={`Editar ${label.toLowerCase()} — salva no lançamento`}>
          {opts.map((o) => <option key={o} value={o}>{o}</option>)}
        </select>
      </span>
      {changed && <small className="fin-kvedit-was">era {was}</small>}
    </div>);

  };

  // Cross-link ESTRUTURADO — chips navegáveis derivados de row.links (não regex no texto).
  const XLINK_META = {
    venda: { icon: I.ShoppingBag, label: (n) => `Venda #${n}` },
    os: { icon: I.Wrench, label: (n) => `OS #${n}` },
    compra: { icon: I.Box, label: (n) => `Compra #${n}` },
    boleto: { icon: I.Receipt, label: (n) => `Boleto #${n}` }
  };
  const CrossLinkChips = ({ row, onPick }) => {
    const links = row.links || {};
    const chips = [];
    ["venda", "os", "compra", "boleto"].forEach((k) => {if (links[k]) chips.push({ k, ...XLINK_META[k], n: links[k] });});
    if (row.invoice) chips.push({ k: "nf", icon: I.FileText, label: () => row.invoice, n: null });
    if (chips.length === 0) {
      return <div className="text-[length:var(--fs-3)] text-[var(--text-3)]">Sem documentos de origem vinculados.</div>;
    }
    return (
      <div className="flex flex-wrap gap-1.5">
      {chips.map((c) =>
        <button key={c.k} onClick={() => onPick && onPick(c.k, c.n)}
        className={`fin-xchip fin-xchip-${c.k} inline-flex items-center gap-1.5 h-7 pl-2 pr-2.5 rounded-md border text-[length:var(--fs-2)] text-[var(--text)] transition-colors duration-150`}>
          <c.icon size={12} />
          <span>{c.label(c.n)}</span>
        </button>
        )}
    </div>);

  };

  // Régua de cobrança (desenho da automação — disparo real fica no módulo Cobrança).
  const ReguaCobranca = ({ row }) => {
    const late = row.status === "atrasado";
    const steps = [
    { d: "D+0", label: "Boleto emitido", state: "done" },
    { d: "D+3", label: "Lembrete por e-mail", state: late ? "done" : "pending" },
    { d: "D+8", label: "Aviso de vencido", state: late ? "cur" : "pending" }];

    return (
      <div className="space-y-1">
      {steps.map((s, i) =>
        <div key={i} className="flex items-center gap-2 text-[length:var(--fs-3)]">
          <span className="w-8 font-mono text-[var(--text-3)]">{s.d}</span>
          <span className={`w-2 h-2 rounded-full shrink-0 ${s.state === "done" ? "bg-[var(--pos)]" : s.state === "cur" ? "bg-[var(--neg)]" : "bg-[var(--border)]"}`} />
          <span className={s.state === "pending" ? "text-[var(--text-3)]" : "text-[var(--text)]"}>{s.label}</span>
        </div>
        )}
      <div className="text-[length:var(--fs-1)] text-[var(--text-3)] pt-1 italic">Automação proposta — o disparo real (e-mail/PIX) fica no módulo Cobrança.</div>
    </div>);

  };

  // Lente FISCAL — NF + impostos estimados (Simples Nacional). Estimativa visual; apuração real no módulo Fiscal.
  const ISS_RATE = 0.05; // serviços gráficos — alíquota municipal típica
  const LenteFiscal = ({ row }) => {
    const isIn = row.kind === "receivable";
    const hasNf = !!row.invoice;
    const iss = isIn ? row.amount * ISS_RATE : 0;
    const das = isIn ? row.amount * 0.06 : 0;
    return (
      <div>
      {/* Mesma ficha-cartão da identificação — label nunca flutua em branco ([W] 06-11) */}
      <div className="fin-kv-card">
        <div className="grid grid-cols-2 gap-x-5">
          <KV label={isIn ? "NF-e de saída" : "Documento fiscal"} copy={hasNf ? row.invoice : null}>
            {hasNf ? <span className="num">{row.invoice}</span> : <span className="text-[var(--warn)]">não emitida</span>}
          </KV>
          <KV label="Regime">Simples Nacional</KV>
        </div>
        {isIn &&
          <div className="mt-1.5 border-t border-[var(--hairline)]">
            <div className="flex items-baseline justify-between py-[5px] border-b border-[var(--hairline)]">
              <span className="text-[length:var(--fs-3)] text-[var(--text-2)]">ISS retido · 5%</span>
              <span className="text-[length:var(--fs-3)] num tabular-nums font-medium">{fmtBRL(iss)}</span>
            </div>
            <div className="flex items-baseline justify-between py-[5px]">
              <span className="text-[length:var(--fs-3)] text-[var(--text-2)]">No DAS do mês · ≈ 6%</span>
              <span className="text-[length:var(--fs-3)] num tabular-nums font-medium text-[var(--warn)]">{fmtBRL(das)}</span>
            </div>
          </div>
          }
      </div>
      <p className="text-[length:var(--fs-1)] text-[var(--text-3)] pt-1.5 leading-relaxed">Estimativa — apuração e guia na sub-tela <b className="font-medium">Impostos & obrigações</b> · oficial no módulo Fiscal.</p>
    </div>);

  };

  // Lente COBRANÇA — ciclo título⇄cobrança: a gerar → emitida (boleto/PIX) → paga.
  const LenteCobranca = ({ row, onCobranca, onMark }) => {
    const isIn = row.kind === "receivable";
    const settled = !!row.paid_at;
    const cob = row.cobranca || null;
    const [copied, setCopied] = useState("");
    const copy = (text, which) => {
      try {navigator.clipboard && navigator.clipboard.writeText(String(text).replace(/\s/g, ""));} catch (e) {}
      setCopied(which);setTimeout(() => setCopied(""), 1600);
    };

    // Encerrada (pago) — reflete como o ciclo fechou.
    if (settled) {
      const via = cob ? cob.tipo === "pix" ? "PIX" : "boleto" : null;
      return (
        <div className="flex items-center gap-2 text-[length:var(--fs-3)]">
        <span className="w-1.5 h-1.5 rounded-full bg-[var(--pos)] shrink-0" />
        <span className="text-[var(--text)]">{via ? <>Pago via <b className="font-medium">{via}</b> — cobrança encerrada.</> : "Título liquidado — cobrança encerrada."}</span>
      </div>);

    }

    // Saída (a pagar) — agendar, não cobrar.
    if (!isIn) {
      return (
        <div className="flex flex-wrap gap-1.5">
        <button onClick={() => onMark && onMark(row.id)} className="fin-cob-btn fin-cob-btn--primary"><I.Calendar size={13} /> Agendar pagamento</button>
      </div>);

    }

    // A receber, sem cobrança emitida → gerar (costura título→cobrança).
    if (!cob) {
      return (
        <div>
        <p className="text-[length:var(--fs-3)] text-[var(--text-2)] leading-relaxed mb-2.5">Nenhuma cobrança emitida. Gere um boleto ou PIX — o status volta pra esta linha quando o cliente pagar.</p>
        <div className="flex flex-wrap gap-1.5">
          <button onClick={() => onCobranca && onCobranca(row.id, "boleto")} className="fin-cob-btn fin-cob-btn--primary"><I.FileText size={13} /> Gerar boleto</button>
          <button onClick={() => onCobranca && onCobranca(row.id, "pix")} className="fin-cob-btn fin-cob-btn--ghost"><I.Sparkles size={13} /> Gerar PIX</button>
        </div>
      </div>);

    }

    // Cobrança emitida → mostra código + ações (reenviar · registrar pagamento).
    const isPix = cob.tipo === "pix";
    const codigo = isPix ? pixCopiaECola(row) : linhaDigitavel(row);
    return (
      <div className="space-y-3">
      <ReguaCobranca row={row} />
      <div className="rounded-lg bg-[var(--sunken)] px-3 py-2.5">
        <div className="flex items-center justify-between gap-2 mb-1.5">
          <span className="text-[length:var(--fs-1)] uppercase tracking-[0.07em] text-[var(--text-3)] font-medium">{isPix ? "PIX copia e cola" : "Linha digitável"}</span>
          <button onClick={() => copy(codigo, "cod")} title={isPix ? "Copiar código PIX" : "Copiar linha digitável"}
            className="inline-flex items-center gap-1 text-[length:var(--fs-2)] text-[var(--text-2)] hover:text-[var(--accent)] transition-colors duration-150">
            {copied === "cod" ? <><I.Check size={12} /> copiado</> : <><I.Copy size={12} /> copiar</>}
          </button>
        </div>
        <code className="block text-[length:var(--fs-2)] font-mono text-[var(--text-2)] break-all leading-snug">{codigo}</code>
      </div>
      <div className="flex flex-wrap gap-1.5">
        <button onClick={() => onMark && onMark(row.id)} className="fin-cob-btn fin-cob-btn--primary"><I.Check size={13} /> Registrar pagamento</button>
        <button className="fin-cob-btn fin-cob-btn--ghost"><I.Send size={13} /> Reenviar ao cliente</button>
      </div>
    </div>);

  };

  /* ─────────────────────────────────────────────────────────────────────────
   * Novo lançamento — CTA primário REAL ([W] "roda o fluxo" 2026-06-10: era stub).
   * Mínimo honesto: tipo · descrição · contraparte · valor · vencimento → entra no ledger.
   * ───────────────────────────────────────────────────────────────────────── */
  const FinNovoLancamento = ({ open, onClose, onCreate }) => {
    const [kind, setKind] = useState("receivable");
    const [desc, setDesc] = useState("");
    const [party, setParty] = useState("");
    const [valor, setValor] = useState("");
    const [venc, setVenc] = useState("");
    useEffect(() => {if (open) {setDesc("");setParty("");setValor("");setVenc("");}}, [open]);
    if (!open) return null;
    const amount = parseFloat(String(valor).replace(/\./g, "").replace(",", ".")) || 0;
    const can = desc.trim() && party.trim() && amount > 0;
    const submit = () => {
      if (!can) return;
      onCreate({ kind, desc: desc.trim(), party: party.trim(), amount, due: venc ? new Date(venc + "T12:00:00") : new Date(window.FIN_TODAY.getTime() + 7 * 864e5) });
      onClose();
    };
    return (
      <div className="fixed inset-0 z-[60] bg-black/30 grid place-items-start pt-24" onClick={onClose}>
      <div onClick={(e) => e.stopPropagation()} className="fin-cmdk-card w-[480px] mx-auto bg-[var(--surface)] rounded-lg border border-[var(--border)] overflow-hidden"
        role="dialog" aria-label="Novo lançamento"
        onKeyDown={(e) => {if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) submit();}}>
        <div className="px-4 h-12 flex items-center gap-2 border-b border-[var(--border)]">
          <b className="text-[length:var(--fs-4)] font-semibold">Novo título</b>
          <div className="fin-lens-seg ml-auto" role="group" aria-label="Tipo">
            <button className={"fin-lens-btn" + (kind === "receivable" ? " on" : "")} onClick={() => setKind("receivable")}>A receber</button>
            <button className={"fin-lens-btn" + (kind === "payable" ? " on" : "")} onClick={() => setKind("payable")}>A pagar</button>
          </div>
        </div>
        <div className="p-4 grid grid-cols-2 gap-x-4 gap-y-3">
          <label className="col-span-2 grid gap-1">
            <span className="text-[length:var(--fs-1)] uppercase tracking-[0.08em] text-[var(--text-3)]">Descrição</span>
            <input autoFocus value={desc} onChange={(e) => setDesc(e.target.value)} placeholder={kind === "receivable" ? "Banner 3×1m — evento" : "Aluguel galpão junho"}
              className="h-9 px-3 rounded-md border border-[var(--border)] bg-[var(--surface)] text-[length:var(--fs-4)] outline-none focus:border-[var(--accent)]" />
          </label>
          <label className="grid gap-1">
            <span className="text-[length:var(--fs-1)] uppercase tracking-[0.08em] text-[var(--text-3)]">{kind === "receivable" ? "Cliente" : "Fornecedor"}</span>
            {window.FinClienteCombobox ?
              <window.FinClienteCombobox kind={kind} value={party} onChange={setParty} /> :
              <input value={party} onChange={(e) => setParty(e.target.value)}
                className="h-9 px-3 rounded-md border border-[var(--border)] bg-[var(--surface)] text-[length:var(--fs-4)] outline-none focus:border-[var(--accent)]" />}
          </label>
          <label className="grid gap-1">
            <span className="text-[length:var(--fs-1)] uppercase tracking-[0.08em] text-[var(--text-3)]">Valor (R$)</span>
            <input inputMode="decimal" value={valor} onChange={(e) => setValor(e.target.value)} placeholder="0,00"
              className="h-9 px-3 rounded-md border border-[var(--border)] bg-[var(--surface)] text-[length:var(--fs-4)] num tabular-nums text-right outline-none focus:border-[var(--accent)]" />
          </label>
          <label className="grid gap-1 col-span-2">
            <span className="text-[length:var(--fs-1)] uppercase tracking-[0.08em] text-[var(--text-3)]">Vencimento <i className="not-italic text-[var(--text-4)] normal-case tracking-normal">· vazio = +7 dias</i></span>
            <input type="date" value={venc} onChange={(e) => setVenc(e.target.value)}
              className="h-9 px-3 rounded-md border border-[var(--border)] bg-[var(--surface)] text-[length:var(--fs-4)] outline-none focus:border-[var(--accent)]" />
          </label>
        </div>
        <div className="px-4 h-14 border-t border-[var(--border)] flex items-center justify-end gap-2">
          <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
          <button className="os-btn primary" disabled={!can} onClick={submit}>Adicionar ao caixa</button>
        </div>
      </div>
    </div>);

  };

  /* Método 9.75 · P5/S3 — saída no mundo real: recibo imprimível com identidade Oimpresso. */
  const printRecibo = (r) => {
    const isIn = r.kind === "receivable";
    const f = document.createElement("iframe");
    f.style.cssText = "position:fixed;right:0;bottom:0;width:0;height:0;border:0;";
    document.body.appendChild(f);
    const d = f.contentDocument;
    d.open();
    d.write(`<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Recibo ${r.id}</title>
  <style>
    body{ font: 12pt/1.5 Georgia, serif; color:#111; margin:48px; }
    .brand{ display:flex; justify-content:space-between; align-items:baseline; border-bottom:2px solid #111; padding-bottom:12px; }
    .brand b{ font-size:16pt; letter-spacing:.04em; }
    .brand span{ font-size:10pt; color:#555; }
    h1{ font-size:13pt; margin:24px 0 4px; text-transform:uppercase; letter-spacing:.08em; }
    .valor{ font-size:24pt; font-family:ui-monospace,monospace; margin:8px 0 20px; }
    table{ width:100%; border-collapse:collapse; font-size:11pt; }
    td{ padding:6px 0; border-bottom:1px solid #ddd; vertical-align:top; }
    td:first-child{ color:#555; width:160px; }
    .foot{ margin-top:36px; font-size:9pt; color:#777; }
  </style></head><body>
    <div class="brand"><b>OIMPRESSO</b><span>Comunicação Visual</span></div>
    <h1>${r.paid_at ? "Recibo" : isIn ? "Cobrança" : "Aviso de pagamento"} · ${r.id}</h1>
    <div class="valor">${r.amount === 0 ? "" : isIn ? "+ " : "− "}${fmtBRL(r.amount)}</div>
    <table>
      <tr><td>Descrição</td><td>${r.desc}</td></tr>
      <tr><td>Contraparte</td><td>${r.party}</td></tr>
      <tr><td>Categoria</td><td>${r.category} · ${r.channel}</td></tr>
      <tr><td>${r.paid_at ? "Liquidado em" : "Vencimento"}</td><td>${fmtDateLong(r.paid_at || r.due)}</td></tr>
      ${r.invoice ? `<tr><td>Nota fiscal</td><td>${r.invoice}</td></tr>` : ""}
    </table>
    <div class="foot">Emitido pelo Oimpresso ERP em ${fmtDateLong(window.FIN_TODAY)} · documento sem valor fiscal.</div>
  </body></html>`);
    d.close();
    setTimeout(() => {try {f.contentWindow.focus();f.contentWindow.print();} catch (e) {}setTimeout(() => f.remove(), 800);}, 60);
  };

  const Drawer = ({ row, onClose, onMark, onCobranca, pos, onNav, allRows = [] }) => {
    const comments = window.useFinComments ? window.useFinComments() : null;
    const conferido = window.useFinConferido ? window.useFinConferido() : null;
    const edits = window.useFinEdits ? window.useFinEdits() : null;
    const anexos = window.useFinAnexos ? window.useFinAnexos() : null;
    const aprovacao = window.useFinAprovacao ? window.useFinAprovacao() : null;
    const [tab, setTab] = useState("detalhes");
    // reset tab when changing row
    useEffect(() => {setTab("detalhes");}, [row?.id]);
    // Método 9.75 · P2+N3 — J/K navega títulos SEM fechar o drawer · R liquida. Teclas visíveis no footer.
    useEffect(() => {
      if (!row) return;
      const onKey = (e) => {
        const t = document.activeElement;
        if (e.metaKey || e.ctrlKey || e.altKey) return;
        if (t && (["INPUT", "TEXTAREA", "SELECT"].includes(t.tagName) || t.isContentEditable)) return;
        const k = e.key.toLowerCase();
        if (k === "j") {e.preventDefault();onNav && onNav(1);} else
        if (k === "k") {e.preventDefault();onNav && onNav(-1);} else
        if (k === "r" && !row.paid_at) {e.preventDefault();onMark(row.id);}
      };
      window.addEventListener("keydown", onKey);
      return () => window.removeEventListener("keydown", onKey);
    }, [row, onNav, onMark]);
    if (!row) return null;
    const rawRow = row;
    // aplica edições se houver — render usa eff em vez de row
    const eff = edits ? edits.applied(row.id, row) : row;
    const isIn = eff.kind === "receivable";
    const settled = !!eff.paid_at;
    const delta = window.FIN_DAYS_FROM_TODAY(eff.due);
    const Linkify = window.VdLinkify;
    const isConferido = conferido && conferido.has(row.id);
    const hasEdits = edits && edits.hasEdits(row.id);
    const commentsCount = comments ? comments.countFor(row.id) : 0;
    // Frescor refinado: vira UMA linha calma (data + urgência em palavras), não pill redundante.
    const fStatusCls = eff.status === "atrasado" ? "text-[var(--neg)]" : eff.status === "vencendo" ? "text-[var(--warn)]" : "text-[var(--text-3)]";
    const relText = settled ? null :
    delta < 0 ? `${-delta} ${-delta === 1 ? "dia" : "dias"} em atraso` :
    delta === 0 ? "vence hoje" :
    `em ${delta} ${delta === 1 ? "dia" : "dias"}`;
    const relCls = settled ? "" : delta < 0 ? "text-[var(--neg)] font-medium" : delta <= 3 ? "text-[var(--warn)] font-medium" : "text-[var(--text-2)]";

    return (
      <>
      <div onClick={onClose} className="fixed inset-0 z-40 bg-black/20" />
      <aside className="fixed top-0 right-0 z-50 h-screen w-[560px] max-w-[92vw] bg-[var(--surface)] border-l border-[var(--border)] drawer-shown flex flex-col fin-drawer-wide">
        <div className="px-5 h-14 flex items-center gap-3 border-b border-[var(--border)]">
          <DirIcon kind={row.kind} status={row.status} size={16} />
          <div className="flex-1 min-w-0">
            <div className="text-[length:var(--fs-2)] uppercase tracking-widest text-[var(--text-2)] font-medium flex items-center gap-2">
              {isIn ? "A receber" : "A pagar"} · <CopyVal text={row.id}>{row.id}</CopyVal>
              {isConferido && <span className="fin-conf-pill-inline">✓ conferido</span>}
              {hasEdits && <span className="fin-edit-pill-inline">✎ editado</span>}
            </div>
            <div className="text-[length:var(--fs-4)] font-semibold truncate">
              {Linkify ? <Linkify text={eff.desc} onPick={(id) => console.log("→", id)} /> : eff.desc}
            </div>
          </div>
          {pos && pos.total > 0 &&
            <div className="fin-dw-nav" title="Navegar entre títulos (J/K)">
              <button className="fin-dw-nav-btn" disabled={pos.idx <= 1} onClick={() => onNav(-1)} aria-label="Título anterior (K)">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="18 15 12 9 6 15" data-comment-anchor="90e5201daa-polyline-1296-162"></polyline></svg>
              </button>
              <span className="fin-dw-pos num tabular-nums">{pos.idx > 0 ? pos.idx : "–"}<i>/</i>{pos.total}</span>
              <button className="fin-dw-nav-btn" disabled={pos.idx <= 0 || pos.idx >= pos.total} onClick={() => onNav(1)} aria-label="Próximo título (J)">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
              </button>
            </div>
            }
          <button onClick={onClose} className="w-8 h-8 grid place-items-center rounded text-[var(--text-2)] hover:bg-[var(--sunken)]">
            <I.X size={16} />
          </button>
        </div>

        {/* Camada 1 — O FATO (gabarito Prova Viva 9.75): quanto · quando · onde no ciclo.
                           Fixa fora do scroll; centavos/prefixo pequenos, inteiro 38px mono. */}
        {(() => {
            const [intPart, decPart] = eff.amount.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).split(",");
            return (
              <div className="shrink-0 px-5 pt-3 pb-3.5 border-b border-[var(--border)] fin-dw-hero">
              <div className="flex items-end justify-between gap-3">
                <div className="min-w-0">
                  <div className={`text-[length:var(--fs-1)] uppercase tracking-[0.1em] font-semibold ${fStatusCls}`}>
                    {settled ? "Liquidado" : isIn ? "A receber" : "A pagar"}
                  </div>
                  <div className="mt-0.5 flex items-baseline">
                    <span className="text-[length:var(--fs-4)] text-[var(--text-2)] num mr-1 whitespace-nowrap">{eff.amount === 0 ? "R$" : isIn ? "+ R$" : "− R$"}</span>
                    <span className={`text-[length:var(--fs-9)] leading-none font-semibold tracking-tight num tabular-nums ${isIn ? "text-[var(--pos)]" : "text-[var(--text)]"}`}>{intPart}</span>
                    <span className="text-[length:var(--fs-4)] text-[var(--text-2)] num">,{decPart}</span>
                  </div>
                  {/* ONDA 2 P1 · cadeira Tufte — tira o número do isolamento: compara com a média
                                    REAL da categoria (só aparece com ≥2 pares; tom neutro, sem falsa valência). */}
                  {(() => {
                      const peers = (allRows || []).filter((r) => r.id !== eff.id && r.kind === row.kind && r.category === eff.category && r.amount > 0);
                      if (peers.length < 2) return null;
                      const avg = peers.reduce((s, r) => s + r.amount, 0) / peers.length;
                      if (!avg) return null;
                      const pct = Math.round((eff.amount - avg) / avg * 100);
                      return (
                        <div className="fin-vs-avg">
                        <span className="fin-vs-pct">{pct > 0 ? "↑" : pct < 0 ? "↓" : "≈"} {pct > 0 ? "+" : ""}{pct}%</span> vs média em {eff.category}
                        <span className="fin-vs-n"> · {peers.length + 1} títulos</span>
                      </div>);

                    })()}
                </div>
                <div className="flex flex-col items-end gap-1.5 shrink-0 pb-0.5">
                  {/* R3 — estado dito 1× (label do hero); aqui só o TEMPO, como chip de frescor */}
                  {!settled && relText &&
                    <span className={`fin-rel-chip ${delta < 0 ? "neg" : delta <= 3 ? "warn" : "mut"}`}>{relText}</span>
                    }
                  <div className="text-[length:var(--fs-3)] text-[var(--text-2)] num tabular-nums whitespace-nowrap">
                    {settled ?
                      <>liq. <b className="font-medium text-[var(--text)]">{fmtDateLong(eff.paid_at)}</b></> :
                      <>vence <b className="font-medium text-[var(--text)]">{fmtDateLong(eff.due)}</b></>}
                  </div>
                </div>
              </div>

              {window.FsmStepper && window.finFsmStage && (
                settled ? (
                /* Caso 04/05 (O Adversário): item terminal não repete "Liquidado" 5×
                   nem gasta 80px com stepper completo. Resumo de 1 linha. */
                (() => {
                  const dAtraso = eff.paid_at && eff.due ? Math.round((eff.paid_at - eff.due) / 86400000) : 0;
                  return (
                    <div className="mt-2.5 fin-fsm-done">
                        <span className="fin-fsm-done-ic"><I.Check size={11} /></span>
                        <span>Emitido → Liquidado · <b>4 etapas</b> · {dAtraso > 0 ? <span className="text-[var(--neg)]">{dAtraso}d de atraso</span> : "no prazo"}</span>
                      </div>);

                })()) :

                <div className="mt-2.5 fin-fsm-wrap fin-fsm-compact">
                    <window.FsmStepper
                    domain="financeiro"
                    variant="full-stepper"
                    current={window.finFsmStage(eff, conferido && [...(conferido.set || []), ...(isConferido ? [row.id] : [])])} />
                  </div>)

                }

              {conferido && window.FinConferidoToggle &&
                <div className="mt-2 fin-toggles-row">
                  <window.FinConferidoToggle row={row} conferido={conferido} />
                  {edits && window.FinEditPanel && <window.FinEditPanel row={row} edits={edits} />}
                </div>
                }
            </div>);

          })()}

        <nav className="fin-drawer-tabs">
          <button className={"fin-drawer-tab" + (tab === "detalhes" ? " on" : "")}
            onClick={() => setTab("detalhes")}>
            Detalhes
            {commentsCount > 0 && <span className="fin-drawer-tab-ct">💬 {commentsCount}</span>}
            {hasEdits && <span className="fin-drawer-tab-tag" title="Lançamento editado">·</span>}
          </button>
          <button className={"fin-drawer-tab fin-drawer-tab-ai" + (tab === "ia" ? " on" : "")}
            onClick={() => setTab("ia")}>
            ✦ IA
          </button>
        </nav>

        <div className="flex-1 overflow-y-auto nice-scroll px-5 pb-5 pt-0.5 space-y-0 text-[length:var(--fs-4)]">
          {tab === "detalhes" && <>
          {/* ONDA 2 · cadeira Victor — a tela LIDERA com a conclusão inferida do estado,
                                      em vez de obrigar a Eliana a varrer 7 seções e concluir sozinha. */}
          {(() => {
                const hasNF = !!eff.invoice;
                let v;
                if (settled && hasNF) v = { tone: "pos", t: "Nada pendente.", s: "Pago, conciliado e com NF vinculada." };else
                if (settled && !hasNF) v = { tone: "warn", t: "Pago, mas sem NF.", s: "Falta vincular o documento fiscal." };else
                if (!settled && delta < 0) v = { tone: "neg", t: `Vencida há ${-delta} ${-delta === 1 ? "dia" : "dias"} — cobrar.`, s: eff.party };else
                if (!settled && delta <= 3) v = { tone: "warn", t: delta <= 0 ? "Vence hoje — preparar cobrança." : `Vence em ${delta} ${delta === 1 ? "dia" : "dias"}.`, s: eff.party };else
                v = { tone: "muted", t: `Em aberto — vence em ${delta} dias.`, s: "Nada urgente por agora." };
                return (
                  <div className={`fin-verdict fin-verdict-${v.tone}`} role="status">
                <span className="fin-verdict-ic">{v.tone === "pos" ? <I.Check size={13} /> : v.tone === "muted" ? "·" : "!"}</span>
                <div className="min-w-0">
                  <b>{v.t}</b>
                  {v.s && <span className="fin-verdict-sub">{v.s}</span>}
                </div>
              </div>);

              })()}
          {window.FinAiAnomaliaBanner && <window.FinAiAnomaliaBanner row={row} />}

          {/* Vínculos COMPACTO — chips na mesma linha do título (sem banda vazia; o chip JÁ diz "origem rastreável") */}
          <section className="fin-lens py-3.5">
            <div className="flex items-center gap-2 flex-wrap">
              <span className="fin-lens-ic fin-lens-ic-accent"><I.Link size={12} /></span>
              <h4 className="text-[length:var(--fs-3)] font-semibold text-[var(--text)] mr-1.5 fin-lens-h4 fin-lens-h4-accent">Vínculos</h4>
              <CrossLinkChips row={eff} onPick={(k, n) => console.log("→ abrir", k, n)} />
            </div>
          </section>
          <div className="fin-lens py-4" data-comment-anchor="b7ed73daef-div-1432-11">
            <div className="fin-kv-card grid grid-cols-2 gap-x-5">
              <div className="col-span-2"><KV label="Contraparte" copy={eff.party}>{eff.party}</KV></div>
              {edits && window.FIN_EDIT_OPTIONS ?
                  <KVEdit label="Categoria" value={eff.category} was={row.category} options={window.FIN_EDIT_OPTIONS.categories} onChange={(v) => edits.set(row.id, { category: v })} /> :
                  <KV label="Categoria">{eff.category}</KV>}
              {edits && window.FIN_EDIT_OPTIONS ?
                  <KVEdit label="Canal" value={eff.channel} was={row.channel} options={window.FIN_EDIT_OPTIONS.channels} onChange={(v) => edits.set(row.id, { channel: v })} /> :
                  <KV label="Canal">{eff.channel}</KV>}
              <KV label="Competência">{eff.competencia ? eff.competencia.toLocaleDateString("pt-BR", { month: "short", year: "numeric" }) : "—"}</KV>
              <KV label="Conta" copy={contaOf(row).detail}>{contaOf(row).detail}</KV>
            </div>
          </div>

          <LensSection icon={I.Bank} title="Conciliação" hue={settled ? "pos" : "muted"}
              status={settled ? null : "aguardando"}
              tone={settled ? "pos" : "muted"}>
            {settled ?
                <div className="rounded-md px-3 py-2 flex items-start gap-2.5 fin-concil-ok">
                <span className="w-[18px] h-[18px] rounded-full grid place-items-center bg-[var(--pos)] text-white shrink-0 mt-px"><I.Check size={11} /></span>
                <div className="text-[length:var(--fs-3)] min-w-0">
                  <div className="font-medium text-[var(--text)]">Conciliado · extrato OFX 04392</div>
                  <div className="text-[var(--text-2)] num tabular-nums">{fmtDateLong(row.paid_at)} · {fmtBRL(row.amount)} · ±R$ 0,00 · ±0 dias</div>
                </div>
              </div> :

                <div className="rounded-md border border-[var(--border)] px-3 py-2.5 text-[length:var(--fs-3)] text-[var(--text-2)] flex items-start gap-2.5">
                <I.Sparkles size={14} className="text-[var(--text-2)] mt-0.5" />
                <div>Sem match no extrato. Ao liquidar, o sistema procura linhas próximas (±R$ 5,00 e ±2 dias) e sugere conciliação automática.</div>
              </div>
                }
          </LensSection>

          <LensSection icon={I.Percent} title="Fiscal" hue="warn"
              status={eff.invoice ? null : "sem NF"}
              tone={eff.invoice ? "pos" : "warn"}>
            <LenteFiscal row={eff} />
          </LensSection>

          {(() => {
                const cob = eff.cobranca;
                const cobStatus = settled ? null : eff.status === "atrasado" ? "em atraso" : cob ? cob.tipo === "pix" ? "PIX gerado" : "boleto emitido" : "a gerar";
                const cobTone = settled ? "pos" : eff.status === "atrasado" ? "neg" : cob ? "info" : "muted";
                return (
                  <LensSection icon={I.Send} title="Cobrança" status={cobStatus} tone={cobTone}
                  hue={{ pos: "pos", neg: "neg", info: "accent", muted: "accent" }[cobTone] || "accent"}>
                <LenteCobranca row={eff} onCobranca={onCobranca} onMark={onMark} />
              </LensSection>);

              })()}

          {window.FinAprovacaoPanel && <window.FinAprovacaoPanel row={eff} aprovacao={aprovacao} />}
          {window.FinAnexosPanel && <window.FinAnexosPanel row={eff} anexos={anexos} />}

          <div className="fin-lens py-4">
            {window.FinAuditTrail ?
                <window.FinAuditTrail row={row} /> :

                <>
                  <div className="text-[length:var(--fs-2)] text-[var(--text-2)] uppercase tracking-widest font-medium">Histórico</div>
                  <ol className="mt-2 space-y-2.5 text-[length:var(--fs-3)]">
                    <li>Sem registros</li>
                  </ol>
                </>
                }
          </div>

          {comments && window.FinCommentsThread &&
              <div className="fin-lens py-4">
              <window.FinCommentsThread rowId={row.id} comments={comments} />
            </div>
              }
          </>}

          {tab === "ia" && window.FinAiPanel &&
            <window.FinAiPanel row={row} />
            }
        </div>

        <div className="border-t border-[var(--border)] px-5 h-14 flex items-center gap-2 fin-drawer-footer">
          {window.FinTroubleButton && <window.FinTroubleButton row={row} />}
          <span className="fin-dw-hint" title="Atalhos: J/K navegam entre títulos · R liquida · Esc fecha">
            <kbd className="fin-kbd">J</kbd><kbd className="fin-kbd">K</kbd><em>título</em>
          </span>
          <button className="fin-foot-icon-btn" title="Ver NFe">
            <I.Eye size={14} />
            <span>Ver NFe</span>
          </button>
          <button className="fin-foot-icon-btn" title="Imprimir recibo com identidade Oimpresso" onClick={() => printRecibo(eff)}>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            <span>Recibo</span>
          </button>
          {!settled &&
            <button
              onClick={() => onMark(row.id)}
              className="fin-foot-mark-btn"
              title={isIn ? "Marcar como recebido (R)" : "Marcar como pago (R)"}>
              <I.Check size={14} />
              <span>{isIn ? "Recebi" : "Paguei"}</span>
              <kbd className="fin-kbd fin-kbd-acc">R</kbd>
            </button>
            }
        </div>
      </aside>
    </>);

  };

  /* ─────────────────────────────────────────────────────────────────────────
   * Cmd+K palette
   * ─────────────────────────────────────────────────────────────────────── */
  const CmdK = ({ open, onClose, rows, onPick }) => {
    const [q, setQ] = useState("");
    const inputRef = useRef(null);
    useEffect(() => {if (open) {setQ("");setTimeout(() => inputRef.current?.focus(), 0);}}, [open]);

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
      <div className="fixed inset-0 z-[60] cp-backdrop bg-black/30 grid place-items-start pt-24" onClick={onClose}>
      <div onClick={(e) => e.stopPropagation()} className="fin-cmdk-card w-[560px] bg-[var(--surface)] rounded-lg border border-[var(--border)] overflow-hidden">
        <div className="flex items-center gap-3 px-4 h-12 border-b border-[var(--border)]">
          <I.Search size={15} className="text-[var(--text-3)]" />
          <input
              ref={inputRef}
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar lançamento, cliente, NFe, categoria…"
              className="flex-1 outline-none text-[length:var(--fs-4)] placeholder:text-[var(--text-3)]" />
            
          <kbd className="px-1.5 py-0.5 rounded border border-[var(--border)] bg-[var(--sunken)] text-[length:var(--fs-1)] text-[var(--text-2)] font-mono">Esc</kbd>
        </div>
        <div className="max-h-[360px] overflow-y-auto nice-scroll py-1.5">
          {matches.length === 0 &&
            <div className="px-4 py-8 text-center text-[length:var(--fs-4)] text-[var(--text-2)]">Nada encontrado para "{q}".</div>
            }
          {matches.map((r) =>
            <button
              key={r.id}
              onClick={() => {onPick(r);onClose();}}
              className="w-full px-4 py-2 flex items-center gap-3 hover:bg-[var(--sunken)] text-left transition-colors duration-150">
              
              <DirIcon kind={r.kind} status={r.status} size={14} />
              <div className="flex-1 min-w-0">
                <div className="text-[length:var(--fs-4)] font-medium text-[var(--text)] truncate">{r.desc}</div>
                <div className="text-[length:var(--fs-2)] text-[var(--text-2)] truncate">{r.party} · {r.category} · {r.invoice}</div>
              </div>
              <div className={`text-[length:var(--fs-4)] num font-medium ${r.kind === "receivable" ? "text-[var(--pos)]" : "text-[var(--text)]"}`}>
                {amtSign(r.kind, r.amount)} {fmtBRL(r.amount)}
              </div>
            </button>
            )}
        </div>
        <div className="border-t border-[var(--border)] px-4 h-9 flex items-center justify-between text-[length:var(--fs-2)] text-[var(--text-2)]">
          <div className="flex items-center gap-3">
            <span className="flex items-center gap-1"><kbd className="px-1.5 py-0.5 rounded border border-[var(--border)] bg-[var(--sunken)] font-mono text-[var(--text-2)]">↑↓</kbd> navegar</span>
            <span className="flex items-center gap-1"><kbd className="px-1.5 py-0.5 rounded border border-[var(--border)] bg-[var(--sunken)] font-mono text-[var(--text-2)]">↵</kbd> abrir</span>
          </div>
          <span>{matches.length} de {rows.length}</span>
        </div>
      </div>
    </div>);

  };

  /* ─────────────────────────────────────────────────────────────────────────
   * Tweaks panel
   * ─────────────────────────────────────────────────────────────────────── */
  const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
    "density": "comfortable",
    "showWeekend": true,
    "groupByDate": true
  } /*EDITMODE-END*/;

  const FinTweaks = ({ tweaks, setTweak }) =>
  <TweaksPanel title="Tweaks">
    <TweakSection label="Tabela">
      <TweakRadio
        label="Densidade"
        value={tweaks.density}
        onChange={(v) => setTweak("density", v)}
        options={[
        { value: "compact", label: "Compacta" },
        { value: "comfortable", label: "Confortável" },
        { value: "spacious", label: "Espaçosa" }]
        } />
      
      <TweakToggle
        label="Agrupar por data"
        value={tweaks.groupByDate}
        onChange={(v) => setTweak("groupByDate", v)} />
      
    </TweakSection>
  </TweaksPanel>;


  /* ─────────────────────────────────────────────────────────────────────────
   * App
   * ─────────────────────────────────────────────────────────────────────── */
  const FinanceiroPage = ({ initialTela = "unified" }) => {
    const [rows, setRows] = useState(() => window.FIN_ROWS);
    const [tab, setTab] = useState("all");
    // Refino 2026-05-18: 4 checkboxes lifecycle + toggle late (substitui tab)
    const [states, setStates] = useState(() => new Set(["rec", "received", "pay", "paid"]));
    const [late, setLate] = useState(false);
    // Refino #2 IA — digest do mês
    const [digestOpen, setDigestOpen] = useState(false);
    // Refino #3 Guia + Saída
    const [fechamentoOpen, setFechamentoOpen] = useState(false);
    const [presentOpen, setPresentOpen] = useState(false);
    const [query, setQuery] = useState("");
    const [dateField, setDateField] = useState("venc");
    const [periodMode, setPeriodMode] = useState("mes");
    const [anchor, setAnchor] = useState(() => new Date(window.FIN_TODAY));
    const [customRange, setCustomRange] = useState({ from: "", to: "" });
    const [planoConta, setPlanoConta] = useState("all");
    const [contas, setContas] = useState(new Set());
    const [selected, setSelected] = useState(new Set());
    const [drawerRow, setDrawerRow] = useState(null);
    const [cmdkOpen, setCmdkOpen] = useState(false);
    // CTA primário REAL ([W] "roda o fluxo"): novo lançamento entra no ledger + persiste no mock global
    const [novoOpen, setNovoOpen] = useState(false);
    const handleCreate = useCallback(({ kind, desc, party, amount, due }) => {
      const seq = window.__finNovoSeq = (window.__finNovoSeq || 0) + 1;
      const row = {
        id: (kind === "receivable" ? "R-N" : "P-N") + (100 + seq),
        kind, desc, party, amount, due,
        category: "Manual", channel: "—", invoice: "",
        paid_at: null, emissao: window.FIN_TODAY, competencia: window.FIN_TODAY,
        links: {}, descClean: desc
      };
      // status pelo MESMO pipeline dos mocks (statusFor exposto) — "aberto" inventado derrubava o StatusBadge
      row.status = window.FIN_STATUS_FOR ? window.FIN_STATUS_FOR(row) : due < window.FIN_TODAY ? "atrasado" : "vencendo";
      setRows((rs) => [row, ...rs]);
      try {window.FIN_ROWS.unshift(row);} catch (e) {}
      window.vdToast?.((kind === "receivable" ? "A receber" : "A pagar") + " " + row.id + " · " + fmtBRL(amount) + " adicionado ao caixa", "ok", 3600);
    }, []);
    // Onda 4 — leitura de boleto (OCR) abre a sheet; cria título a pagar via handleCreate.
    const [ocrOpen, setOcrOpen] = useState(false);
    // Onda 1 — diálogo de baixa (substitui a baixa instantânea).
    const [baixaRow, setBaixaRow] = useState(null);
    const baixas = window.useFinBaixas ? window.useFinBaixas() : null;
    const openBaixa = (id) => setBaixaRow(rows.find((r) => r.id === id) || null);
    // useState local em vez de useTweaks: o shell unificado já tem painel Tweaks próprio.
    const [density, setDensity] = useState("compact");
    const [groupByDate, setGroupByDate] = useState(true);
    const t = { density, groupByDate };
    const [tela, setTela] = useState(initialTela);
    // refletir mudança de rota
    useEffect(() => {setTela(initialTela);}, [initialTela]);
    // US-FIN-029 — lente ativa (clamp caixa). Trocar de lente re-arma os chips do lado.
    const [lente, setLente] = useState("caixa");
    const applyLente = useCallback((id) => {
      const safe = id === "receber" || id === "pagar" ? id : "caixa";
      setLente(safe);
      setStates(safe === "receber" ? new Set(["rec", "received"]) :
      safe === "pagar" ? new Set(["pay", "paid"]) :
      new Set(["rec", "received", "pay", "paid"]));
    }, []);

    // Linhas dentro do período + campo de data selecionados (paridade @main data_campo).
    // KPIs e contadores seguem o período; os filtros de ciclo/atraso/busca refinam por cima.
    const periodRows = useMemo(() => {
      const win = periodWindow(periodMode, anchor, customRange);
      if (!win) return rows;
      const [start, end] = win;
      return rows.filter((x) => {const dv = dateFieldGet(x, dateField);return dv && dv >= start && dv < end;});
    }, [rows, periodMode, anchor, dateField, customRange]);

    // counts per tab
    const counts = useMemo(() => ({
      all: periodRows.length,
      open: periodRows.filter((r) => !r.paid_at).length,
      rec: periodRows.filter((r) => r.kind === "receivable" && !r.paid_at).length,
      pay: periodRows.filter((r) => r.kind === "payable" && !r.paid_at).length,
      received: periodRows.filter((r) => r.kind === "receivable" && r.paid_at).length,
      paid: periodRows.filter((r) => r.kind === "payable" && r.paid_at).length,
      late: periodRows.filter((r) => !r.paid_at && r.status === "atrasado").length
    }), [periodRows]);

    const agingCounts = useMemo(() => {
      return {};
    }, []);

    const filtered = useMemo(() => {
      let r = periodRows;
      // US-FIN-029 — a lente restringe o lado ANTES dos chips
      if (lente === "receber") r = r.filter((x) => x.kind === "receivable");else
      if (lente === "pagar") r = r.filter((x) => x.kind === "payable");
      // 4 checkboxes lifecycle: rec/received/pay/paid
      if (states.size > 0 && states.size < 4) {
        r = r.filter((x) => {
          const k = x.kind === "receivable" ?
          x.paid_at ? "received" : "rec" :
          x.paid_at ? "paid" : "pay";
          return states.has(k);
        });
      } else if (states.size === 0) {
        r = []; // nada marcado = nada exibido
      }
      if (late) r = r.filter((x) => !x.paid_at && x.status === "atrasado");
      if (planoConta !== "all") r = r.filter((x) => x.category === planoConta);
      if (contas.size > 0) r = r.filter((x) => contas.has(contaOf(x).id));
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
    }, [periodRows, tab, query, states, late, lente, planoConta, contas]);

    // Exportar CSV REAL — linhas filtradas viram arquivo (era stub; definido APÓS `filtered` — gotcha Babel var-hoisting)
    const handleExport = useCallback(() => {
      const esc = (s) => '"' + String(s == null ? "" : s).replace(/"/g, '""') + '"';
      const head = ["id", "tipo", "descricao", "contraparte", "categoria", "valor", "vencimento", "pago_em", "status"];
      const lines = filtered.map((r) => [r.id, r.kind === "receivable" ? "receber" : "pagar", r.descClean || r.desc, r.party, r.category, String(r.amount).replace(".", ","), r.due ? r.due.toLocaleDateString("pt-BR") : "", r.paid_at ? r.paid_at.toLocaleDateString("pt-BR") : "", r.status].map(esc).join(";"));
      const blob = new Blob(["\uFEFF" + [head.join(";"), ...lines].join("\n")], { type: "text/csv;charset=utf-8" });
      const a = document.createElement("a");
      a.href = URL.createObjectURL(blob);
      a.download = "financeiro-" + new Date().toISOString().slice(0, 10) + ".csv";
      a.click();setTimeout(() => URL.revokeObjectURL(a.href), 4000);
      window.vdToast?.(filtered.length + " lançamento(s) exportados em CSV", "ok", 3000);
    }, [filtered]);

    const handleMark = useCallback((id) => {
      setRows((rs) => rs.map((r) => r.id === id ? {
        ...r,
        paid_at: window.FIN_TODAY,
        status: r.kind === "receivable" ? "recebido" : "pago",
        // se havia cobrança emitida, o pagamento a encerra (espelha hook OnCobrancaPaga)
        cobranca: r.cobranca ? { ...r.cobranca, status: "paga", paga_at: window.FIN_TODAY } : r.cobranca
      } : r));
    }, []);

    // Onda 1 — confirma a baixa do diálogo: registra (parcial soma) e, se quitar, liquida o título.
    const handleBaixa = useCallback((id, info) => {
      if (baixas) baixas.push(id, { valor: info.valor, conta: info.conta, forma: info.forma, plano: info.plano, data: info.data });
      if (info.parcial) {
        window.vdToast?.("Baixa parcial de " + fmtBRL(info.valor) + " registrada · " + id, "ok", 3200);
        return;
      }
      setRows((rs) => rs.map((r) => r.id === id ? {
        ...r,
        paid_at: window.FIN_TODAY,
        status: r.kind === "receivable" ? "recebido" : "pago",
        channel: info.forma || r.channel,
        cobranca: r.cobranca ? { ...r.cobranca, status: "paga", paga_at: window.FIN_TODAY } : r.cobranca
      } : r));
      window.vdToast?.((id[0] === "R" ? "Recebimento" : "Pagamento") + " de " + fmtBRL(info.valor) + " via " + info.forma + " · " + id, "ok", 3200);
    }, [baixas]);

    // Costura Unificada → Cobrança: emite boleto/PIX a partir do título. O estado
    // volta pra linha e pro drawer; ao pagar, handleMark encerra o ciclo.
    const handleCobranca = useCallback((id, tipo) => {
      setRows((rs) => rs.map((r) => r.id === id ? {
        ...r, cobranca: { tipo, status: "emitida", emitida_at: window.FIN_TODAY }
      } : r));
    }, []);

    const handleMarkAll = useCallback(() => {
      setRows((rs) => rs.map((r) => selected.has(r.id) && !r.paid_at ?
      { ...r, paid_at: window.FIN_TODAY, status: r.kind === "receivable" ? "recebido" : "pago" } :
      r
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
      <FinHero tela={tela}
        periodText={periodLabel(periodMode, anchor)}
        lente={lente}
        onLente={applyLente}
        onCmdK={() => setCmdkOpen(true)}
        onNew={() => setNovoOpen(true)}
        onExport={handleExport}
        onDigest={() => setDigestOpen(true)}
        onFechamento={() => setFechamentoOpen(true)}
        onPresent={() => setPresentOpen(true)}
        onOcr={() => setOcrOpen(true)} />
      {window.PageHeaderNav && <window.PageHeaderNav route={window.__route} />}

      <div className="fin-body">
        {tela === "unified" && <>
          <KPIStrip rows={periodRows} periodText={periodLabelShort(periodMode, anchor)} lente={lente} onLente={applyLente} />
          <PeriodBar dateField={dateField} setDateField={setDateField} period={periodMode} setPeriod={setPeriodMode} anchor={anchor} setAnchor={setAnchor} count={filtered.length} customRange={customRange} setCustomRange={setCustomRange} />
          <FilterBar lente={lente} states={states} setStates={setStates} late={late} setLate={setLate} counts={counts} query={query} setQuery={setQuery} density={density} setDensity={setDensity} planoConta={planoConta} setPlanoConta={setPlanoConta} contas={contas} setContas={setContas} />
          <Table rows={filtered} density={tableRowsDensity} selected={selected} setSelected={setSelected} onOpen={setDrawerRow} onMark={openBaixa} dateField={dateField} emptyPeriod={periodLabel(periodMode, anchor)} onShowAll={periodMode === "tudo" ? null : () => setPeriodMode("tudo")} />
          <FooterBar rows={filtered} selected={selected} onClearSelected={() => setSelected(new Set())} onMarkAll={handleMarkAll} />
        </>}
        {tela === "fluxo" && <window.TelaFluxo onBack={() => setTela("unified")} />}
        {tela === "concil" && <window.TelaConciliacao onBack={() => setTela("unified")} />}
        {tela === "dre" && <window.TelaDRE onBack={() => setTela("unified")} />}
        {tela === "pcontas" && <window.TelaPContas onBack={() => setTela("unified")} />}
        {tela === "impostos" && window.TelaImpostos && <window.TelaImpostos onBack={() => setTela("unified")} />}
      </div>

      <Drawer row={drawerRow ? rows.find((r) => r.id === drawerRow.id) || drawerRow : null} allRows={rows} onClose={() => setDrawerRow(null)} onMark={openBaixa} onCobranca={handleCobranca}
        pos={drawerRow ? { idx: filtered.findIndex((r) => r.id === drawerRow.id) + 1, total: filtered.length } : null}
        onNav={(d) => {
          const i = filtered.findIndex((r) => r.id === drawerRow?.id);
          if (i < 0) return;
          const n = filtered[i + d];
          if (n) setDrawerRow(n);
        }} />
      <FinNovoLancamento open={novoOpen} onClose={() => setNovoOpen(false)} onCreate={handleCreate} />
      {window.FinBaixaSheet && <window.FinBaixaSheet row={baixaRow} open={!!baixaRow} aberto={baixaRow ? window.finValorAberto(baixaRow, baixas) : 0} onClose={() => setBaixaRow(null)} onConfirm={handleBaixa} />}
      {window.FinOcrBoletoSheet && <window.FinOcrBoletoSheet open={ocrOpen} onClose={() => setOcrOpen(false)} onCreate={handleCreate} />}
      <CmdK open={cmdkOpen} onClose={() => setCmdkOpen(false)} rows={rows} onPick={setDrawerRow} />
      {window.FinAiMonthDigest && <window.FinAiMonthDigest open={digestOpen} onClose={() => setDigestOpen(false)} />}
      {window.FinFechamentoTrilha && <window.FinFechamentoTrilha open={fechamentoOpen} onClose={() => setFechamentoOpen(false)} onNavigate={setTela} />}
      {window.FinPresentationMode && <window.FinPresentationMode open={presentOpen} onClose={() => setPresentOpen(false)} />}
    </div>);

  };

  window.FinanceiroPage = FinanceiroPage;
})();