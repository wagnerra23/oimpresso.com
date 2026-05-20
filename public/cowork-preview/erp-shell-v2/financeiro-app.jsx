// Financeiro — Cockpit V2 (Refino #1.a)
// ─────────────────────────────────────────────────────────────────────────
// Diferença vs financeiro-app.jsx original:
//   1. Remove .fin-subnav horizontal do topo (FinHero perde a faixa de tabs)
//   2. Layout root agora é .fin-cockpit-body com 3 colunas (220 · 1fr · 360px)
//   3. <FinLeftCol/> nova: sub-rotas no topo (1234) + filtros canônicos + chave-resumo
//   4. Drawer overlay → <DetailPanel/> coluna persistente (sempre visível)
//   5. Sub-rotas reduzidas para 4 (Plano de contas sai e vira modal do DRE)
//   6. Atalhos 1/2/3/4 trocam sub-rota · ⌘E placeholder export
//   7. Botão ghost ⇩ Export sai do .os-page-h · vira item de kebab (placeholder)
//   8. FilterBar simplificada: só tabs + search (período/categoria/conta vão pra esquerda)
//   9. Tabela perde checkbox column por padrão · reaparece só com selected.size > 0
// Persona: Eliana [E]. IIFE: expõe window.FinanceiroPage.
(() => {
const { useState, useMemo, useEffect, useRef, useCallback } = React;
const I = window.FIN_I;

/* ─── Format helpers ──────────────────────────────────────────────────── */
const fmtBRL = (n) =>
  n.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
const fmtBRLshort = (n) => {
  if (Math.abs(n) >= 1000) return "R$ " + (n / 1000).toLocaleString("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + "k";
  return fmtBRL(n);
};
const fmtDate = (d) => d.toLocaleDateString("pt-BR", { day: "2-digit", month: "2-digit" });
const fmtDateLong = (d) => d.toLocaleDateString("pt-BR", { day: "2-digit", month: "short", year: "numeric" });
const dayLabel = (delta) => {
  if (delta === 0) return "hoje";
  if (delta === 1) return "amanhã";
  if (delta === -1) return "ontem";
  if (delta > 0) return `em ${delta} dias`;
  return `há ${-delta} dias`;
};

/* ─── Density ─────────────────────────────────────────────────────────── */
const DENSITY = {
  compact:    { rowH: 32, py: "py-1",   text: "text-[12.5px]", gap: "gap-2", iconBox: 22 },
  comfortable:{ rowH: 44, py: "py-2.5", text: "text-sm",       gap: "gap-3", iconBox: 26 },
  spacious:   { rowH: 56, py: "py-4",   text: "text-sm",       gap: "gap-4", iconBox: 30 },
};

/* ─── Status badge (7 estados canônicos · KB-9.75 R#1.c) ──────────────── */
const STATUS_STYLES = {
  recebido:   { cls: "s-rec",  label: "Recebido" },
  pago:       { cls: "s-pago", label: "Pago" },
  pendente:   { cls: "s-pen",  label: "Pendente" },
  vencendo:   { cls: "s-vend", label: "Vencendo" },
  atrasado:   { cls: "s-venc", label: "Vencido" },
  conciliado: { cls: "s-conc", label: "Conciliado" },
  estornado:  { cls: "s-est",  label: "Estornado" },
};
const StatusBadge = ({ status, compact, title }) => {
  const s = STATUS_STYLES[status];
  if (!s) return null;
  return (
    <span className={`fin-pill-canon ${s.cls}${compact ? " compact" : ""}`} title={title || null}>
      <span className="dot"/>
      {s.label}
    </span>
  );
};

/* ─── Sub-rotas: 4 itens (Plano de contas removido) ───────────────────── */
const FIN_SUB = [
  { id: "lanc",   label: "Lançamentos",     key: "1" },
  { id: "concil", label: "Conciliação",     key: "2" },
];

/* ─── FinHero ─ padrão de tela R#1.e ──────────────────────────────────── */
const FinHero = ({ tela, setTela, onCmdK, onNew, onMore, onConciliar, onJana, counts }) => {
  return (
    <div className="fin-page-h">
      <div className="fin-page-title">
        <h1>Financeiro</h1>
        <p>Maio 2026 · ROTA LIVRE</p>
      </div>
      <nav className="fin-subroutes" aria-label="Sub-rotas">
        {FIN_SUB.map((s) => {
          const n = s.id === "lanc" ? counts.lancN : s.id === "concil" ? counts.concilN : null;
          return (
            <button key={s.id}
                    className={"fin-subroute" + (tela === s.id ? " active" : "")}
                    onClick={() => setTela(s.id)}
                    title={`Atalho: ${s.key}`}>
              <span className="fin-subroute-label">{s.label}</span>
              {n != null && <span className="fin-subroute-n">{n}</span>}
            </button>
          );
        })}
      </nav>
      <div className="fin-page-actions">
        <button className="fin-act ghost" onClick={onCmdK} title="Buscar (⌘K)">
          <I.Search size={13}/>
        </button>
        <button className="fin-act ghost fin-jana-btn" onClick={onJana} title="Jana copiloto (⌘J)">
          <I.Sparkles size={13}/>
        </button>
        <button className="fin-act ghost" onClick={onMore} title="Mais ações (Exportar ⌘E)">
          <I.More size={13}/>
        </button>
        <span className="fin-page-actions-sep"/>
        <button className="fin-act primary" onClick={onNew} title="Novo lançamento (n)">
          <I.Plus size={12}/> Novo lançamento
        </button>
      </div>
    </div>
  );
};

/* ─── FinLeftCol ── sub-rotas + filtros + summary ─────────────────────── */
const CATEGORIES = ["Banner", "Adesivo", "Fachada", "Placa", "Gráfica rápida", "Insumo", "Aluguel", "Utilidade", "Imposto", "Folha", "Serviço"];

const FinLeftCol = ({ allRows, period, setPeriod, categorySel, setCategorySel, accountSel, setAccountSel }) => {
  const counts = useMemo(() => {
    const vencendo  = allRows.filter((r) => r.status === "vencendo").length;
    const vencido   = allRows.filter((r) => r.status === "atrasado").length;
    const conciliado= allRows.filter((r) => r.paid_at).length;
    return { vencendo, vencido, conciliado };
  }, [allRows]);

  const toggleCat = (c) => setCategorySel((s) => {
    const n = new Set(s);
    n.has(c) ? n.delete(c) : n.add(c);
    return n;
  });

  return (
    <aside className="fin-cockpit-left">
      <div className="fin-lc-grp" style={{ paddingTop: 2 }}>Período</div>
      <div className="fin-lc-filts">
        {["Maio 2026", "Abril 2026", "Últimos 30 dias", "Trimestre"].map((p) => (
          <button key={p}
                  className={"fin-filt radio" + (period === p ? " active" : "")}
                  onClick={() => setPeriod(p)}>
            <span className="fin-filt-radio"/>
            <span className="fin-filt-label">{p}</span>
          </button>
        ))}
      </div>

      <div className="fin-lc-grp">Categoria</div>
      <div className="fin-lc-filts">
        {CATEGORIES.slice(0, 6).map((c) => (
          <button key={c}
                  className={"fin-filt" + (categorySel.has(c) ? " active" : "")}
                  onClick={() => toggleCat(c)}>
            <span className="fin-filt-check"/>
            <span className="fin-filt-label">{c}</span>
          </button>
        ))}
      </div>

      <div className="fin-lc-grp">Conta</div>
      <div className="fin-lc-filts">
        {[{ id: "itau", label: "Itaú PJ · 4521" }, { id: "caixa", label: "Caixa interno" }].map((a) => (
          <button key={a.id}
                  className={"fin-filt radio" + (accountSel === a.id ? " active" : "")}
                  onClick={() => setAccountSel(a.id)}>
            <span className="fin-filt-radio"/>
            <span className="fin-filt-label">{a.label}</span>
          </button>
        ))}
      </div>

      <div className="fin-lc-summary">
        <div className="fin-lc-summary-h">Resumo · maio</div>
        <span className="fin-pill-canon s-vend"><span className="dot"/>{counts.vencendo} vencendo</span>
        <span className="fin-pill-canon s-venc"><span className="dot"/>{counts.vencido} vencido</span>
        <span className="fin-pill-canon s-conc"><span className="dot"/>{counts.conciliado} conciliado</span>
      </div>
    </aside>
  );
};

/* ─── KPI Hero ─ padrão "mercury-style" (R#1.g) ───────────────────────── */
const KPIStrip = ({ rows }) => {
  const k = useMemo(() => {
    const recebido = rows.filter((r) => r.kind === "receivable" && r.paid_at).reduce((s, r) => s + r.amount, 0);
    const pago     = rows.filter((r) => r.kind === "payable"    && r.paid_at).reduce((s, r) => s + r.amount, 0);
    const aReceber = rows.filter((r) => r.kind === "receivable" && !r.paid_at).reduce((s, r) => s + r.amount, 0);
    const aPagar   = rows.filter((r) => r.kind === "payable"    && !r.paid_at).reduce((s, r) => s + r.amount, 0);
    const atrasadoN  = rows.filter((r) => !r.paid_at && r.status === "atrasado").length;
    const saldoAtual = recebido - pago;
    const saldoPrevisto = saldoAtual + aReceber - aPagar;
    const aReceberN = rows.filter((r) => r.kind === "receivable" && !r.paid_at).length;
    return { recebido, pago, aReceber, aPagar, atrasadoN, saldoAtual, saldoPrevisto, aReceberN };
  }, [rows]);

  return (
    <div className="fin-hero">
      <div className="fin-hero-label">Saldo previsto · maio</div>
      <div className={`fin-hero-num ${k.saldoPrevisto >= 0 ? "pos" : "neg"} num`}>{fmtBRL(k.saldoPrevisto)}</div>
      <div className="fin-hero-meta">
        <span><b className="num fin-num-pos">{fmtBRLshort(k.recebido)}</b> recebido</span>
        <span className="sep">·</span>
        <span><b className="num">{fmtBRLshort(k.pago)}</b> pago</span>
        <span className="sep">·</span>
        <span><b className="num">{fmtBRLshort(k.aReceber)}</b> a receber <small>({k.aReceberN})</small></span>
        <span className="sep">·</span>
        <span><b className="num">{fmtBRLshort(k.aPagar)}</b> a pagar</span>
        {k.atrasadoN > 0 && <>
          <span className="sep">·</span>
          <span className="fin-hero-warn"><b className="num">{k.atrasadoN}</b> {k.atrasadoN === 1 ? "vencido" : "vencidos"}</span>
        </>}
      </div>
    </div>
  );
};

/* ─── Ageing horizontal — inalterado ──────────────────────────────────── */
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
        {k.pd30 > 0  && <div className="seg s1" style={{ flex: k.pd30 }}>{k.pd30}% 0-30d</div>}
        {k.pd60 > 0  && <div className="seg s2" style={{ flex: k.pd60 }}>{k.pd60}% 31-60d</div>}
        {k.pd90 > 0  && <div className="seg s3" style={{ flex: k.pd90 }}>{k.pd90}% 61d+</div>}
      </div>
    </div>
  );
};

/* ─── FilterBar v2 ── radio quick + 4 checkboxes de tipo ──────────────
 * Hoje:
 *   - "Todas" e "Vencido" funcionam como quick-pills (radio behavior)
 *   - A receber / A pagar / Recebidas / Pagas viram CHECKBOXES (multi-select)
 *   - Default (sem nada marcado) = Todas
 *   - Aberto deixa de existir como tab — vira o que sai naturalmente quando
 *     A receber + A pagar estão marcados (sem Recebidas/Pagas)
 * ────────────────────────────────────────────────────────────────────── */
const TYPE_CHECKS = [
  { id: "rec",      label: "A receber",  desc: "Recebimentos pendentes" },
  { id: "pay",      label: "A pagar",    desc: "Pagamentos pendentes" },
  { id: "received", label: "Recebidas",  desc: "Recebimentos liquidados" },
  { id: "paid",     label: "Pagas",      desc: "Pagamentos liquidados" },
];

const DENSITY_OPTS = [
  { id: "compact",     label: "◻", title: "Compacta" },
  { id: "comfortable", label: "▤", title: "Confortável" },
  { id: "spacious",    label: "▣", title: "Espaçosa" },
];

const FilterBar = ({ typeFilter, setTypeFilter, showVencido, setShowVencido, counts, query, setQuery, density, setDensity }) => {
  const noneActive = typeFilter.size === 0 && !showVencido;
  const toggleType = (id) => setTypeFilter((s) => {
    const n = new Set(s);
    n.has(id) ? n.delete(id) : n.add(id);
    return n;
  });
  return (
    <div className="os-toolbar fin-toolbar">
      <button className={"os-tab" + (noneActive ? " active" : "")}
              onClick={() => { setTypeFilter(new Set()); setShowVencido(false); }}
              title="Mostrar todos os lançamentos do período">
        Todas
        <span className="os-tab-n">{counts.all}</span>
      </button>
      <span className="fin-toolbar-sep"/>
      {TYPE_CHECKS.map((t) => (
        <label key={t.id}
               className={"fin-type-check" + (typeFilter.has(t.id) ? " on" : "")}
               title={t.desc}>
          <input type="checkbox"
                 checked={typeFilter.has(t.id)}
                 onChange={() => toggleType(t.id)}/>
          <span className="fin-type-check-box"/>
          <span className="fin-type-check-label">{t.label}</span>
          <span className="fin-type-check-n">{counts[t.id]}</span>
        </label>
      ))}
      <span className="fin-toolbar-sep"/>
      <button className={"os-tab warn" + (showVencido ? " active" : "")}
              onClick={() => setShowVencido((v) => !v)}
              title="Apenas lançamentos em atraso">
        Vencido
        <span className="os-tab-n">{counts.late}</span>
      </button>
      <div className="os-toolbar-r">
        <div className="fin-density-seg" role="radiogroup" aria-label="Densidade">
          {DENSITY_OPTS.map((d) => (
            <button key={d.id}
                    className={"fin-density-opt" + (density === d.id ? " active" : "")}
                    onClick={() => setDensity(d.id)}
                    title={d.title}
                    role="radio"
                    aria-checked={density === d.id}>
              {d.label}
            </button>
          ))}
        </div>
        <div className="os-search">
          <I.Search size={12}/>
          <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Filtrar nesta lista…"/>
        </div>
      </div>
    </div>
  );
};

/* ─── DirIcon + Row + Table ───────────────────────────────────────────── */
const DirIcon = ({ kind, status, size = 14 }) => {
  const isIn = kind === "receivable";
  const Icon = isIn ? I.ArrowDownLeft : I.ArrowUpRight;
  const tone = isIn
    ? (status === "recebido" ? "bg-emerald-50 text-emerald-700" : "bg-emerald-50/60 text-emerald-700/80")
    : (status === "pago" ? "bg-rose-50 text-rose-700" : "bg-rose-50/60 text-rose-700/80");
  return (
    <span className={`inline-grid place-items-center rounded ${tone}`} style={{ width: size + 8, height: size + 8 }}>
      <Icon size={size} strokeWidth={2} />
    </span>
  );
};

const Row = ({ row, density, selected, isSelectedDrawer, isCursor, onSelect, onOpen, onMark, dim, showCheckbox }) => {
  const isIn = row.kind === "receivable";
  const dens = DENSITY[density];
  const delta = window.FIN_DAYS_FROM_TODAY(row.due);
  const settled = !!row.paid_at;

  return (
    <tr
      data-anomaly={row._anomaly?.type || undefined}
      data-today={row._today ? "1" : undefined}
      title={row._anomaly?.msg || undefined}
      className={`border-b border-stone-100 row-hover cursor-pointer ${isSelectedDrawer ? "row-selected" : ""} ${isCursor ? "row-cursor" : ""} ${dim ? "opacity-55" : ""}`}
      style={{ height: dens.rowH }}
      onClick={() => onOpen(row)}
    >
      {showCheckbox && (
        <td className={`pl-6 pr-2 ${dens.py}`} onClick={(e) => { e.stopPropagation(); onSelect(row.id); }}>
          <input type="checkbox" checked={selected} readOnly className="w-3.5 h-3.5 rounded border-stone-300 accent-stone-900"/>
        </td>
      )}
      <td className={`${showCheckbox ? "" : "pl-6"} px-2 ${dens.py} ${dens.text} text-stone-700 num whitespace-nowrap`}>
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
        <div className="flex items-center gap-2 min-w-0">
          <span className="font-medium text-stone-900 truncate max-w-[260px]">{row.desc}</span>
          {row.invoice && <span className="fin-xlink" data-link={/NFe|NF /i.test(row.invoice) ? "NFE" : (/DAS|DAM|GPS|Fat|Recibo|Folha|OS /i.test(row.invoice) ? "DOC" : "NFE")} title={`Documento: ${row.invoice}`}>{row.invoice}</span>}
          {row.os_ref && <span className="fin-xlink" data-link="OS" title={`Vincula a ${row.os_ref}`}>{row.os_ref}</span>}
          {row.boleto_ref && <span className="fin-xlink" data-link="BOL" title={`Boleto Inter: ${row.boleto_ref}`}>{row.boleto_ref}</span>}
          {row.cc_ref && <span className="fin-xlink" data-link="CC" title={`Centro de custo: ${row.cc_ref}`}>{row.cc_ref}</span>}
        </div>
      </td>
      <td className={`px-2 ${dens.py} ${dens.text} text-stone-700 truncate max-w-[180px]`}>{row.party}</td>
      <td className={`px-2 ${dens.py} ${dens.text} text-stone-500`}>
        <span className="inline-flex items-center gap-1.5">
          <span className="w-1.5 h-1.5 rounded-full bg-stone-300"/>
          {row.category}
        </span>
      </td>
      <td className={`px-2 ${dens.py} ${dens.text}`}>
        <StatusBadge status={row.status} compact={density === "compact"} title={row.conc_ref ? `${row.conc_ref} · 100% match` : null}/>
      </td>
      <td className={`px-2 ${dens.py} ${dens.text} text-right num font-medium whitespace-nowrap ${isIn ? "text-emerald-700" : "text-stone-900"}`}>
        <span className="text-stone-400 mr-0.5">{isIn ? "+" : "−"}</span>
        {fmtBRL(row.amount).replace("R$", "").trim()}
      </td>
      <td className={`pl-2 pr-4 ${dens.py} text-right`} onClick={(e) => e.stopPropagation()}>
        <div className="inline-flex items-center gap-1">
          {!settled && (
            <button onClick={() => onMark(row.id)}
                    title={isIn ? "Marcar como recebido" : "Marcar como pago"}
                    className="h-7 px-2 inline-flex items-center gap-1 rounded text-[11.5px] text-emerald-700 hover:bg-emerald-50 transition-colors duration-150">
              <I.Check size={13}/>
              <span>{isIn ? "Recebi" : "Paguei"}</span>
            </button>
          )}
          <button className="w-7 h-7 grid place-items-center rounded text-stone-400 hover:bg-stone-100 hover:text-stone-700">
            <I.More size={14}/>
          </button>
        </div>
      </td>
    </tr>
  );
};

const Table = ({ rows, density, selected, setSelected, selectedDrawerId, cursorId, onOpen, onMark, groupByDate }) => {
  const showDateHeaders = density !== "compact" && groupByDate;
  const showCheckbox = selected.size > 0;
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
            {showCheckbox && (
              <th className="pl-6 pr-2 py-2 w-8 text-left">
                <input type="checkbox"
                       checked={selected.size > 0 && selected.size === rows.length}
                       ref={(el) => { if (el) el.indeterminate = selected.size > 0 && selected.size < rows.length; }}
                       onChange={() => {
                         if (selected.size === rows.length) setSelected(new Set());
                         else setSelected(new Set(rows.map((r) => r.id)));
                       }}
                       className="w-3.5 h-3.5 rounded border-stone-300 accent-stone-900"/>
              </th>
            )}
            <th className={`${showCheckbox ? "" : "pl-6"} px-2 py-2 text-left font-medium`}>Vencimento</th>
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
                    <Row key={r.id} row={r} density={density}
                         selected={selected.has(r.id)}
                         isSelectedDrawer={selectedDrawerId === r.id}
                         isCursor={cursorId === r.id}
                         showCheckbox={showCheckbox}
                         onSelect={(id) => setSelected((s) => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; })}
                         onOpen={onOpen} onMark={onMark} dim={!!r.paid_at}/>
                  ))}
                </React.Fragment>
              );
            })
          ) : (
            rows.map((r) => (
              <Row key={r.id} row={r} density={density}
                   selected={selected.has(r.id)}
                   isSelectedDrawer={selectedDrawerId === r.id}
                   isCursor={cursorId === r.id}
                   showCheckbox={showCheckbox}
                   onSelect={(id) => setSelected((s) => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; })}
                   onOpen={onOpen} onMark={onMark} dim={!!r.paid_at}/>
            ))
          )}
        </tbody>
      </table>

      {rows.length === 0 && (
        <div className="py-16 text-center text-stone-500 text-sm">Nenhum lançamento bate com esses filtros.</div>
      )}
    </div>
  );
};

/* ─── FooterBar ── inalterado ─────────────────────────────────────────── */
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
            <span className="h-3 w-px bg-stone-200"/>
            <span>Total entrada: <span className="text-emerald-700 font-medium num">{fmtBRL(rows.filter((r) => r.kind === "receivable").reduce((s, r) => s + r.amount, 0))}</span></span>
            <span>Total saída: <span className="text-stone-900 font-medium num">{fmtBRL(rows.filter((r) => r.kind === "payable").reduce((s, r) => s + r.amount, 0))}</span></span>
          </div>
          <div className="ml-auto text-[11.5px] text-stone-400 font-mono flex items-center gap-2">
            <kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-stone-50 text-stone-600">J</kbd>
            <kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-stone-50 text-stone-600">K</kbd>
            <span>navegar</span>
            <span className="text-stone-300">·</span>
            <kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-stone-50 text-stone-600">␣</kbd>
            <span>marcar</span>
            <span className="text-stone-300">·</span>
            <kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-stone-50 text-stone-600">⌘K</kbd>
            <span>buscar</span>
            <span className="text-stone-300">·</span>
            <kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-stone-50 text-stone-600">?</kbd>
            <span>ajuda</span>
          </div>
        </>
      ) : (
        <>
          <div className="flex items-center gap-3">
            <span className="text-stone-900 font-medium num">{selRows.length} selecionados</span>
            <span className="h-3 w-px bg-stone-200"/>
            {totalIn > 0 && <span className="text-emerald-700 num">+ {fmtBRL(totalIn)}</span>}
            {totalOut > 0 && <span className="text-stone-900 num">− {fmtBRL(totalOut)}</span>}
          </div>
          <div className="ml-auto flex items-center gap-2">
            <button onClick={onClearSelected} className="h-8 px-3 rounded-md border border-stone-200 text-stone-600 hover:bg-stone-50 transition-colors duration-150">Limpar</button>
            <button className="h-8 px-3 rounded-md border border-stone-200 text-stone-700 hover:bg-stone-50 transition-colors duration-150">Editar em lote</button>
            <button className="h-8 px-3 rounded-md border border-stone-200 text-stone-700 hover:bg-stone-50 transition-colors duration-150 inline-flex items-center gap-1.5"><I.Download size={13}/>Exportar</button>
            <button onClick={onMarkAll} className="h-8 px-3 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white transition-colors duration-150 inline-flex items-center gap-1.5">
              <I.Check size={13}/>
              Liquidar selecionados
            </button>
          </div>
        </>
      )}
    </div>
  );
};

/* ─── DetailPanel ── coluna persistente, não-overlay ──────────────────── */
const DetailEmpty = () => (
  <div className="fin-detail-empty">
    <div className="fin-detail-empty-h">Nenhum lançamento selecionado</div>
    <div className="fin-detail-empty-sub">
      Clique numa linha ou use <kbd>J</kbd>/<kbd>K</kbd> <small>(R#1.b)</small> para ver o detalhe aqui.
    </div>
    <div className="fin-detail-sct">Atalhos rápidos</div>
    <div className="fin-detail-sct-list">
      <div className="fin-detail-sct-row"><kbd>1</kbd> <kbd>2</kbd> <kbd>3</kbd> <kbd>4</kbd><span>Trocar sub-rota</span></div>
      <div className="fin-detail-sct-row"><kbd>⌘K</kbd><span>Buscar lançamento, conta, fornecedor, OS</span></div>
      <div className="fin-detail-sct-row"><kbd>⌘E</kbd><span>Exportar</span></div>
      <div className="fin-detail-sct-row"><kbd>x</kbd><span>Mostrar checkbox de seleção em lote</span></div>
      <div className="fin-detail-sct-row dim"><kbd>J</kbd> <kbd>K</kbd><span>Navegar linhas <small>(R#1.b)</small></span></div>
      <div className="fin-detail-sct-row dim"><kbd>␣</kbd><span>Marcar pago / recebido <small>(R#1.b)</small></span></div>
      <div className="fin-detail-sct-row dim"><kbd>?</kbd><span>Cheat-sheet completo <small>(R#1.b)</small></span></div>
    </div>
  </div>
);

/* ─── FinDetailJanaClass ─ auto-classificação por histórico do mesmo party R#2 ─ */
const FinDetailJanaClass = ({ row }) => {
  const peers = useMemo(() => {
    if (!row) return [];
    return window.FIN_ROWS.filter((r) => r.party === row.party && r.id !== row.id);
  }, [row]);
  if (peers.length < 1) return null;
  // Categoria predominante
  const tally = {};
  for (const p of peers) tally[p.category] = (tally[p.category] || 0) + 1;
  const sorted = Object.entries(tally).sort((a, b) => b[1] - a[1]);
  const [topCat, topN] = sorted[0];
  const sameCat = row.category === topCat;
  const conf = Math.round((topN / peers.length) * 100);

  return (
    <div className="fin-detail-section">
      <div className="fin-detail-section-h">
        <I.Sparkles size={10} style={{verticalAlign:"-1px",marginRight:4}}/>
        Auto-classificação · Jana
      </div>
      <div className={`fin-detail-jana-class ${sameCat ? "ok" : "warn"}`}>
        <div className="fin-detail-jc-row">
          <span className="fin-detail-jc-l">Categoria sugerida</span>
          <b className="fin-detail-jc-v">{topCat}<span className="fin-detail-jc-conf">{conf}%</span></b>
        </div>
        <div className="fin-detail-jc-row">
          <span className="fin-detail-jc-l">Histórico do mesmo fornecedor</span>
          <b className="fin-detail-jc-v">{peers.length} lançamento{peers.length>1?"s":""}</b>
        </div>
        <div className="fin-detail-jc-foot">
          {sameCat
            ? `✓ Atual classificação bate com ${topN} de ${peers.length} histórico.`
            : `⚠ Atual (${row.category}) diverge do padrão. Jana sugere revisar.`}
        </div>
      </div>
    </div>
  );
};

const DetailPanel = ({ row, onMark, onClose }) => {
  if (!row) return null;
  const isIn = row.kind === "receivable";
  const settled = !!row.paid_at;
  const delta = window.FIN_DAYS_FROM_TODAY(row.due);

  return (
    <div className="fin-detail-back" onClick={onClose}>
      <aside className="fin-detail-drawer" onClick={(e) => e.stopPropagation()}>
      <div className="fin-detail-h">
        <DirIcon kind={row.kind} status={row.status} size={14}/>
        <div className="fin-detail-title">
          <div className="fin-detail-id">{isIn ? "A receber" : "A pagar"} · {row.id}</div>
          <div className="fin-detail-desc">{row.desc}</div>
        </div>
        <button className="fin-detail-close" onClick={onClose} title="Fechar (Esc)">
          <I.X size={14}/>
        </button>
      </div>

      <div className="fin-detail-body">
        <div className="fin-detail-due">
          <div className={`fin-detail-section-h ${row.status === "atrasado" ? "warn" : row.status === "vencendo" ? "amber" : ""}`}>
            {settled ? "Liquidado" : "Vencimento"}
          </div>
          <div className="fin-detail-due-line">
            <div className="fin-detail-due-date num">{fmtDateLong(row.due)}</div>
            <div className="fin-detail-due-rel">{settled ? `pago em ${fmtDateLong(row.paid_at)}` : dayLabel(delta)}</div>
          </div>
          <div className="fin-detail-amount-line">
            <div className={`fin-detail-amount num ${isIn ? "in" : "out"}`}>
              {isIn ? "+ " : "− "}{fmtBRL(row.amount)}
            </div>
            <StatusBadge status={row.status}/>
          </div>
        </div>

        <div className="fin-detail-meta">
          <div className="fin-detail-meta-it"><small>Contraparte</small><b>{row.party}</b></div>
          <div className="fin-detail-meta-it"><small>Categoria</small><b>{row.category}</b></div>
          <div className="fin-detail-meta-it"><small>Canal</small><b>{row.channel}</b></div>
          <div className="fin-detail-meta-it"><small>Documento</small><b className="mono">{row.invoice}</b></div>
          <div className="fin-detail-meta-it span2"><small>Conta</small><b>Itaú PJ · ag 0438 · cc 4521-7</b></div>
        </div>

        <div className="fin-detail-section">
          <div className="fin-detail-section-h">Conciliação extrato</div>
          {settled ? (
            <div className="fin-detail-conc match">
              <I.Link size={13}/>
              <div>
                <b>Conciliado{row.conc_ref ? ` com ${row.conc_ref}` : ""}</b>
                {fmtDateLong(row.paid_at)} · {fmtBRL(row.amount)} · 100% match
              </div>
            </div>
          ) : (
            <div className="fin-detail-conc empty">
              <I.Sparkles size={13}/>
              <div>Sem match no extrato. Ao liquidar, sistema procura ±R$ [redacted Tier 0] e ±2 dias e sugere conciliação automática.</div>
            </div>
          )}
        </div>

        <FinDetailJanaClass row={row}/>

        <div className="fin-detail-section">
          <div className="fin-detail-section-h">Histórico</div>
          <ol className="fin-detail-tl">
            <li><span/><div>Lançamento criado a partir da venda <span className="mono">#V-2641</span><small>06 mai · Larissa</small></div></li>
            <li><span/><div>NFe emitida e enviada por e-mail<small>06 mai · automação</small></div></li>
            {settled && <li><span className="ok"/><div><b>Marcado como {isIn ? "recebido" : "pago"}</b><small>{fmtDateLong(row.paid_at)} · Eliana</small></div></li>}
          </ol>
        </div>
      </div>

      <div className="fin-detail-actions">
        <button className="os-btn ghost sm"><I.Eye size={12}/> Ver NFe</button>
        <button className="os-btn ghost sm"><I.Send size={12}/> Cobrar</button>
        {!settled && (
          <button onClick={() => { onMark(row.id); onClose(); }} className="os-btn primary sm" style={{ marginLeft: "auto" }}>
            <I.Check size={12}/> {isIn ? "Recebi" : "Paguei"}
          </button>
        )}
      </div>
      </aside>
    </div>
  );
};

/* ─── CmdK · 4 escopos (Lançamento · Conta · Fornecedor · OS) R#1.b ──── */
const CMDK_SCOPES = [
  { id: "all",  label: "Tudo",       key: null, hotkey: null },
  { id: "lanc", label: "Lançamento", key: "L",  hotkey: "l" },
  { id: "conta",label: "Conta",      key: "C",  hotkey: "c" },
  { id: "forn", label: "Fornecedor", key: "F",  hotkey: "f" },
  { id: "os",   label: "OS",         key: "O",  hotkey: "o" },
];

const CmdK = ({ open, onClose, rows, onPick, onJanaAsk }) => {
  const [q, setQ] = useState("");
  const [scope, setScope] = useState("all");
  const inputRef = useRef(null);
  useEffect(() => { if (open) { setQ(""); setScope("all"); setTimeout(() => inputRef.current?.focus(), 0); } }, [open]);

  // Scope hotkeys quando ⌘K aberto
  useEffect(() => {
    if (!open) return;
    const onKey = (e) => {
      // Não interfere quando user está digitando — só com Shift+letra OU Cmd+letra
      const inInput = document.activeElement === inputRef.current;
      if (inInput && !e.shiftKey && !e.metaKey && !e.ctrlKey) return;
      const k = e.key.toLowerCase();
      const found = CMDK_SCOPES.find((s) => s.hotkey === k);
      if (found && (e.shiftKey || e.metaKey || e.ctrlKey || !inInput)) {
        e.preventDefault();
        setScope(found.id);
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open]);

  const ql = q.trim().toLowerCase();

  // Resultados por escopo
  const results = useMemo(() => {
    if (!open) return { lanc: [], forn: [], conta: [], os: [] };
    const matchLanc = !ql ? rows.slice(0, 8) : rows.filter((r) =>
      r.desc.toLowerCase().includes(ql) ||
      r.party.toLowerCase().includes(ql) ||
      r.invoice.toLowerCase().includes(ql) ||
      r.id.toLowerCase().includes(ql) ||
      r.category.toLowerCase().includes(ql)
    );
    // Fornecedores únicos
    const fornMap = new Map();
    for (const r of rows) {
      if (r.kind !== "payable") continue;
      if (ql && !r.party.toLowerCase().includes(ql)) continue;
      if (!fornMap.has(r.party)) fornMap.set(r.party, { name: r.party, count: 0, total: 0 });
      const f = fornMap.get(r.party);
      f.count++;
      if (!r.paid_at) f.total += r.amount;
    }
    const forn = [...fornMap.values()].slice(0, 5);
    // Contas
    const contas = ql ? [] : [
      { id: "itau",  label: "Itaú PJ · ag 0438 · cc 4521-7", saldo: 14420 },
      { id: "caixa", label: "Caixa interno",                  saldo: 320 },
    ].filter((c) => !ql || c.label.toLowerCase().includes(ql));
    // OS vinculadas (placeholder mock — R#3 conecta de verdade)
    const os = ql.match(/os.?\d/) ? [
      { id: "os4821", label: "OS-4821 · adesivagem frota · Auto Posto Trevo", status: "Produção" },
    ] : [];
    return { lanc: matchLanc.slice(0, 8), forn, conta: contas, os };
  }, [open, ql, rows]);

  const filteredResults = scope === "all" ? results : {
    lanc:  scope === "lanc"  ? results.lanc  : [],
    forn:  scope === "forn"  ? results.forn  : [],
    conta: scope === "conta" ? results.conta : [],
    os:    scope === "os"    ? results.os    : [],
  };
  const totalMatches = filteredResults.lanc.length + filteredResults.forn.length + filteredResults.conta.length + filteredResults.os.length;
  const totalAll = results.lanc.length + results.forn.length + results.conta.length + results.os.length;

  if (!open) return null;
  return (
    <div className="fixed inset-0 z-[60] cp-backdrop bg-stone-900/30 grid place-items-start pt-24" onClick={onClose}>
      <div onClick={(e) => e.stopPropagation()} className="w-[560px] bg-white rounded-lg border border-stone-200 shadow-md overflow-hidden">
        <div className="flex items-center gap-3 px-4 h-12 border-b border-stone-200">
          <I.Search size={15} className="text-stone-400"/>
          <input ref={inputRef} value={q} onChange={(e) => setQ(e.target.value)}
                 placeholder="Buscar lançamento, conta, fornecedor, OS…"
                 className="flex-1 outline-none text-[14px] placeholder:text-stone-400"/>
          <kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-stone-50 text-[10.5px] text-stone-500 font-mono">Esc</kbd>
        </div>

        <div className="fin-cmdk-scopes">
          {CMDK_SCOPES.map((s) => (
            <button key={s.id}
                    className={"fin-cmdk-scope" + (scope === s.id ? " active" : "")}
                    onClick={() => setScope(s.id)}>
              {s.label}
              {s.key && <kbd>{s.key}</kbd>}
            </button>
          ))}
        </div>

        <div className="max-h-[360px] overflow-y-auto nice-scroll py-1.5">
          {totalMatches === 0 && (
            <div className="fin-cmdk-empty">
              <div className="fin-cmdk-empty-msg">
                {ql ? `Nada encontrado para "${q}"` : "Comece a digitar para buscar"}
              </div>
              <button className="fin-cmdk-jana" onClick={() => { onJanaAsk && onJanaAsk(q); onClose(); }}>
                <I.Sparkles size={13}/>
                Perguntar à Jana sobre "{q || "…"}"
                <small>copiloto</small>
              </button>
            </div>
          )}

          {filteredResults.forn.length > 0 && <>
            <div className="fin-cmdk-sect-h">Fornecedor · {filteredResults.forn.length}</div>
            {filteredResults.forn.map((f) => (
              <button key={f.name} className="w-full px-4 py-2 flex items-center gap-3 hover:bg-stone-50 text-left transition-colors duration-150">
                <span className="inline-grid place-items-center rounded bg-rose-50 text-rose-700" style={{width: 22, height: 22}}>↑</span>
                <div className="flex-1 min-w-0">
                  <div className="text-[13px] font-medium text-stone-900 truncate">{f.name}</div>
                  <div className="text-[11.5px] text-stone-500 truncate">{f.count} lançamento(s) · {fmtBRL(f.total)} em aberto</div>
                </div>
                <span className="text-[11px] text-stone-400">abrir</span>
              </button>
            ))}
          </>}

          {filteredResults.lanc.length > 0 && <>
            <div className="fin-cmdk-sect-h">Lançamentos · {filteredResults.lanc.length}</div>
            {filteredResults.lanc.map((r) => (
              <button key={r.id} onClick={() => { onPick(r); onClose(); }}
                      className="w-full px-4 py-2 flex items-center gap-3 hover:bg-stone-50 text-left transition-colors duration-150">
                <DirIcon kind={r.kind} status={r.status} size={14}/>
                <div className="flex-1 min-w-0">
                  <div className="text-[13px] font-medium text-stone-900 truncate">{r.desc}</div>
                  <div className="text-[11.5px] text-stone-500 truncate">{r.party} · {r.category} · {r.invoice}</div>
                </div>
                <div className={`text-[13px] num font-medium ${r.kind === "receivable" ? "text-emerald-700" : "text-stone-900"}`}>
                  {r.kind === "receivable" ? "+" : "−"} {fmtBRL(r.amount)}
                </div>
              </button>
            ))}
          </>}

          {filteredResults.conta.length > 0 && <>
            <div className="fin-cmdk-sect-h">Conta · {filteredResults.conta.length}</div>
            {filteredResults.conta.map((c) => (
              <button key={c.id} className="w-full px-4 py-2 flex items-center gap-3 hover:bg-stone-50 text-left transition-colors duration-150">
                <span className="inline-grid place-items-center rounded bg-blue-50 text-blue-700" style={{width: 22, height: 22}}><I.Bank size={11}/></span>
                <div className="flex-1 min-w-0">
                  <div className="text-[13px] font-medium text-stone-900 truncate">{c.label}</div>
                  <div className="text-[11.5px] text-stone-500 truncate">saldo {fmtBRL(c.saldo)}</div>
                </div>
                <span className="text-[11px] text-stone-400">abrir</span>
              </button>
            ))}
          </>}

          {filteredResults.os.length > 0 && <>
            <div className="fin-cmdk-sect-h">OS vinculada · {filteredResults.os.length}</div>
            {filteredResults.os.map((o) => (
              <button key={o.id} className="w-full px-4 py-2 flex items-center gap-3 hover:bg-stone-50 text-left transition-colors duration-150">
                <span className="inline-grid place-items-center rounded bg-purple-50 text-purple-700" style={{width: 22, height: 22}}>OS</span>
                <div className="flex-1 min-w-0">
                  <div className="text-[13px] font-medium text-stone-900 truncate">{o.label}</div>
                  <div className="text-[11.5px] text-stone-500 truncate">{o.status}</div>
                </div>
                <span className="text-[11px] text-stone-400">abrir</span>
              </button>
            ))}
          </>}
        </div>

        <div className="border-t border-stone-200 px-4 h-9 flex items-center justify-between text-[11px] text-stone-500">
          <div className="flex items-center gap-3">
            <span className="flex items-center gap-1"><kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-stone-50 font-mono text-stone-500">↑↓</kbd> navegar</span>
            <span className="flex items-center gap-1"><kbd className="px-1.5 py-0.5 rounded border border-stone-200 bg-stone-50 font-mono text-stone-500">↵</kbd> abrir</span>
          </div>
          <span>{totalMatches} de {totalAll}</span>
        </div>
      </div>
    </div>
  );
};

/* ─── localStorage helpers ────────────────────────────────────────────── */
const LS_PREFIX = "oimpresso.fin.";
const lsGet = (key, fallback) => {
  try {
    const v = localStorage.getItem(LS_PREFIX + key);
    if (v == null) return fallback;
    return JSON.parse(v);
  } catch (e) { return fallback; }
};
const lsSet = (key, v) => {
  try { localStorage.setItem(LS_PREFIX + key, JSON.stringify(v)); } catch (e) {}
};

/* ─── CheatSheet · overlay (?) ────────────────────────────────────────── */
const CHEAT_GROUPS = [
  { label: "Telas", rows: [
    { keys: ["1"], desc: "Lançamentos" },
    { keys: ["2"], desc: "Conciliação" },
  ]},
  { label: "Linhas", rows: [
    { keys: ["J", "K"], desc: "linha anterior / próxima" },
    { keys: ["g", "g"], desc: "topo" },
    { keys: ["⇧G"], desc: "fim" },
    { keys: ["⏎"], desc: "abrir detalhe" },
    { keys: ["␣"], desc: "marcar pago / recebido" },
    { keys: ["x"], desc: "selecionar para lote" },
    { keys: ["e"], desc: "editar lançamento" },
    { keys: ["B"], desc: "favoritar", dim: true },
  ]},
  { label: "Busca", rows: [
    { keys: ["⌘K"], desc: "buscar global" },
    { keys: ["⌘J"], desc: "abrir Jana (copiloto)" },
    { keys: ["L", "C", "F", "O"], desc: "filtrar lançamento · conta · fornecedor · OS" },
    { keys: ["/"], desc: "filtrar nesta lista" },
  ]},
  { label: "Saída", rows: [
    { keys: ["⌘E"], desc: "exportar" },
    { keys: ["⇧P"], desc: "modo apresentação", dim: true },
    { keys: ["?"], desc: "alternar este painel" },
  ]},
];

const CheatSheet = ({ open, onClose }) => {
  if (!open) return null;
  return (
    <div className="fin-cheat-back" onClick={onClose}>
      <div className="fin-cheat" onClick={(e) => e.stopPropagation()}>
        <div className="fin-cheat-h">
          <b>Financeiro · atalhos</b>
          <small>Esc fecha · ? alterna</small>
        </div>
        <div className="fin-cheat-grid">
          {CHEAT_GROUPS.map((g) => (
            <div key={g.label} className="fin-cheat-grp">
              <div className="fin-cheat-grp-h">{g.label}</div>
              {g.rows.map((r, i) => (
                <div key={i} className={"fin-cheat-row" + (r.dim ? " dim" : "")}>
                  <span className="fin-cheat-keys">
                    {r.keys.map((k, j) => <kbd key={j}>{k}</kbd>)}
                  </span>
                  <span className="fin-cheat-desc">{r.desc}</span>
                </div>
              ))}
            </div>
          ))}
        </div>
        <div className="fin-cheat-foot">
          Método KB-9.75 · Eliana opera 100% por teclado.
        </div>
      </div>
    </div>
  );
};

/* ─── JanaPanel · Resumir mês + perguntar livre (R#2) ─────────────────── */
const JanaPanel = ({ open, onClose, rows, initialQ }) => {
  const [mode, setMode] = useState("summary"); // summary | ask
  const [loading, setLoading] = useState(false);
  const [response, setResponse] = useState("");
  const [question, setQuestion] = useState("");

  useEffect(() => {
    if (!open) {
      setResponse(""); setQuestion(""); setMode("summary");
    } else if (initialQ) {
      // ⌘K → Jana flow: pré-popular pergunta + pular para aba "Perguntar"
      setMode("ask");
      setQuestion(initialQ);
      setTimeout(() => {
        const el = document.querySelector(".fin-jana-ask textarea");
        if (el) { el.focus(); el.select(); }
      }, 80);
    }
  }, [open, initialQ]);

  const buildContext = () => {
    const recebido = rows.filter((r) => r.kind === "receivable" && r.paid_at).reduce((s, r) => s + r.amount, 0);
    const pago     = rows.filter((r) => r.kind === "payable"    && r.paid_at).reduce((s, r) => s + r.amount, 0);
    const aReceber = rows.filter((r) => r.kind === "receivable" && !r.paid_at).reduce((s, r) => s + r.amount, 0);
    const aPagar   = rows.filter((r) => r.kind === "payable"    && !r.paid_at).reduce((s, r) => s + r.amount, 0);
    const atrasados = rows.filter((r) => !r.paid_at && r.status === "atrasado");
    const top5Forn = {};
    for (const r of rows) {
      if (r.kind !== "payable") continue;
      top5Forn[r.party] = (top5Forn[r.party] || 0) + r.amount;
    }
    const fornArr = Object.entries(top5Forn).sort((a, b) => b[1] - a[1]).slice(0, 5);
    return {
      recebido, pago, aReceber, aPagar,
      saldoRealizado: recebido - pago,
      saldoPrevisto: recebido - pago + aReceber - aPagar,
      atrasados: atrasados.length,
      atrasadosValor: atrasados.reduce((s, r) => s + r.amount, 0),
      total: rows.length,
      top5Forn: fornArr,
    };
  };

  const askJana = async (prompt) => {
    setLoading(true); setResponse("");
    try {
      const ctx = buildContext();
      const fornStr = ctx.top5Forn.map(([n, v]) => `${n}: ${v.toLocaleString("pt-BR",{style:"currency",currency:"BRL"})}`).join("; ");
      const fullPrompt = `Você é Jana, copiloto financeiro do ERP Oimpresso para Eliana (financeiro escritório, gráfica ROTA LIVRE).
Tom: direto, PT-BR, ≤80 palavras. Sempre cite números do livro. Nunca invente. Proponha — humana decide.

DADOS DE MAIO 2026:
• Recebido: R$ ${ctx.recebido.toFixed(2)} · Pago: R$ ${ctx.pago.toFixed(2)}
• Saldo realizado: R$ ${ctx.saldoRealizado.toFixed(2)}
• A receber: R$ ${ctx.aReceber.toFixed(2)} · A pagar: R$ ${ctx.aPagar.toFixed(2)}
• Saldo previsto fim do mês: R$ ${ctx.saldoPrevisto.toFixed(2)}
• Atrasados: ${ctx.atrasados} (R$ ${ctx.atrasadosValor.toFixed(2)})
• Top 5 fornecedores (mês): ${fornStr}
• Total lançamentos: ${ctx.total}

PERGUNTA: ${prompt}`;
      const txt = await window.claude.complete(fullPrompt);
      setResponse(txt);
    } catch (e) {
      setResponse("Não consegui processar agora. Tente novamente em alguns segundos. " + (e?.message || ""));
    } finally { setLoading(false); }
  };

  if (!open) return null;

  return (
    <div className="fin-jana-back" onClick={onClose}>
      <aside className="fin-jana-drawer" onClick={(e) => e.stopPropagation()}>
        <div className="fin-jana-h">
          <div className="fin-jana-icon"><I.Sparkles size={14}/></div>
          <div className="fin-jana-h-title">
            <div className="fin-jana-h-eyebrow">Jana · copiloto financeiro</div>
            <b>Maio 2026 · ROTA LIVRE</b>
          </div>
          <button className="fin-detail-close" onClick={onClose} title="Fechar (Esc)"><I.X size={14}/></button>
        </div>

        <div className="fin-jana-tabs">
          <button className={"fin-jana-tab" + (mode === "summary" ? " active" : "")}
                  onClick={() => setMode("summary")}>Resumo do mês</button>
          <button className={"fin-jana-tab" + (mode === "ask" ? " active" : "")}
                  onClick={() => setMode("ask")}>Perguntar ao livro</button>
        </div>

        <div className="fin-jana-body">
          {mode === "summary" && (
            <>
              <div className="fin-jana-suggest">
                {["Resumir maio comparando com abril",
                  "Quais fornecedores mais consumiram caixa?",
                  "Algum cliente preocupante em atraso?"].map((q) => (
                  <button key={q} onClick={() => askJana(q)} disabled={loading}>{q}</button>
                ))}
              </div>
            </>
          )}
          {mode === "ask" && (
            <div className="fin-jana-ask">
              <textarea value={question}
                        onChange={(e) => setQuestion(e.target.value)}
                        onKeyDown={(e) => { if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) askJana(question); }}
                        placeholder="Ex: Quanto Acme Comércio pagou no total em 2026? Qual fornecedor cresceu mais nos últimos 3 meses?"
                        rows={3}/>
              <div className="fin-jana-ask-foot">
                <span><kbd>⌘</kbd><kbd>↵</kbd> enviar</span>
                <button className="os-btn primary sm" disabled={loading || !question.trim()} onClick={() => askJana(question)}>
                  <I.Sparkles size={12}/> Perguntar
                </button>
              </div>
            </div>
          )}

          {loading && <div className="fin-jana-loading"><I.Sparkles size={14}/> Jana pensando<span>...</span></div>}

          {response && !loading && (
            <div className="fin-jana-response">
              <div className="fin-jana-response-h">
                <I.Sparkles size={11}/>
                <small>Resposta da Jana · cita números do livro</small>
              </div>
              <div className="fin-jana-response-body">{response}</div>
              <div className="fin-jana-response-foot">
                <button className="os-btn ghost sm" onClick={() => { setResponse(""); setQuestion(""); }}>Nova pergunta</button>
              </div>
            </div>
          )}
        </div>

        <div className="fin-jana-foot">
          <small>Jana propõe · humano decide. Dados deste mês só desta empresa.</small>
        </div>
      </aside>
    </div>
  );
};

/* ─── Toast ── feedback discreto ──────────────────────────────────────── */
const Toast = ({ msg, onDone }) => {
  useEffect(() => {
    if (!msg) return;
    const t = setTimeout(onDone, 3000);
    return () => clearTimeout(t);
  }, [msg]);
  if (!msg) return null;
  return <div className="fin-toast">{msg}</div>;
};

/* ─── KB-9.75 R#2 · Jana inline · detecção de anomalias ─────────────── */
const sameDay = (a, b) => a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
const dayDelta = (a, b) => {
  const aa = new Date(a.getFullYear(), a.getMonth(), a.getDate());
  const bb = new Date(b.getFullYear(), b.getMonth(), b.getDate());
  return Math.round((aa - bb) / 86400000);
};

const detectAnomalies = (rows) => {
  const out = new Map();
  const totalByParty = new Map();
  const lateByParty  = new Map();
  const sumByParty   = new Map();
  for (const r of rows) {
    totalByParty.set(r.party, (totalByParty.get(r.party) || 0) + 1);
    if (r.status === "atrasado") lateByParty.set(r.party, (lateByParty.get(r.party) || 0) + 1);
    sumByParty.set(r.party, (sumByParty.get(r.party) || 0) + r.amount);
  }
  // 1º late de fornecedor com histórico ≥2 (único atraso entre os dele)
  for (const r of rows) {
    if (r.status === "atrasado" && (lateByParty.get(r.party) || 0) === 1 && (totalByParty.get(r.party) || 0) >= 2) {
      out.set(r.id, { type: "late-first", msg: `1º atraso de ${r.party} em ${totalByParty.get(r.party)} lançamentos. Jana sugere ligar antes de cobrar.` });
    }
  }
  // Valor fora do padrão (>1.5× média, histórico ≥2)
  for (const r of rows) {
    const n = totalByParty.get(r.party) || 0;
    if (n < 2) continue;
    const avg = (sumByParty.get(r.party) || 0) / n;
    if (avg > 0 && r.amount > 1.5 * avg && !out.has(r.id)) {
      out.set(r.id, { type: "out-of-pattern", msg: `Valor ${(r.amount/avg).toFixed(1)}× acima da média histórica de ${r.party}. Revisar antes de pagar.` });
    }
  }
  // Duplicado suspeito (mesmo party + mesmo valor em ≤7 dias)
  for (let i = 0; i < rows.length; i++) {
    for (let j = i + 1; j < rows.length; j++) {
      const a = rows[i], b = rows[j];
      if (a.party !== b.party) continue;
      if (Math.abs(a.amount - b.amount) > 0.5) continue;
      const days = Math.abs(dayDelta(a.due, b.due));
      if (days > 7) continue;
      if (!out.has(a.id)) out.set(a.id, { type: "duplicate-suspected", msg: `Possível duplicado de ${b.id} (mesmo valor em ${days}d). Confirmar com ${a.party}.` });
    }
  }
  return out;
};

/* ─── KB-9.75 R#3 · Curadoria · 4 trilhas Eliana ─────────────────────── */
const TRAILS = [
  { id: "concil",  icon: "↔",  title: "Conciliar lote",      desc: "Selecione N linhas e siga 3 passos guiados: match OFX · revisão · liquidação." },
  { id: "cc",      icon: "◫",  title: "Criar centro de custo", desc: "Wizard de 4 passos para criar CC + ligar a categorias existentes sem mexer no plano." },
  { id: "close",   icon: "✓",  title: "Fechar mês",            desc: "Checklist sequencial: conciliar tudo · zerar caixinha · DRE preview · trava o mês." },
  { id: "sped",    icon: "↓",  title: "Exportar SPED",         desc: "Gera contábil + fiscal + contribuições do mês fechado. Empacota em ZIP com PDF resumo." },
];
const TrailsLauncher = ({ onOpen }) => (
  <button className="fin-trails-btn" onClick={onOpen} title="Trilhas Eliana (R#3 · KB-9.75)">
    <I.Sparkles size={12}/>
    <b>Trilhas</b>
    <span className="fin-trails-n">{TRAILS.length}</span>
  </button>
);
const TrailsModal = ({ open, onClose, onPick }) => {
  if (!open) return null;
  return (
    <div className="fin-trails-back" onClick={onClose}>
      <div className="fin-trails-modal" onClick={(e) => e.stopPropagation()}>
        <div className="fin-trails-h">
          <div>
            <div className="fin-trails-eyebrow">Curadoria · KB-9.75 R#3</div>
            <b>Trilhas Eliana</b>
          </div>
          <button className="fin-detail-close" onClick={onClose} title="Esc"><I.X size={14}/></button>
        </div>
        <div className="fin-trails-grid">
          {TRAILS.map((t) => (
            <button key={t.id} className="fin-trail-card" onClick={() => onPick(t)}>
              <span className="fin-trail-ico">{t.icon}</span>
              <b>{t.title}</b>
              <small>{t.desc}</small>
              <span className="fin-trail-go">Começar →</span>
            </button>
          ))}
        </div>
        <div className="fin-trails-foot">
          Cada trilha é guiada · Esc cancela a qualquer momento · histórico fica em <kbd>?</kbd>.
        </div>
      </div>
    </div>
  );
};

const JanaTodayBanner = ({ rows }) => {
  const t = useMemo(() => {
    const today = rows.filter(r => !r.paid_at && sameDay(r.due, window.FIN_TODAY));
    const tomorrow = rows.filter(r => !r.paid_at && dayDelta(r.due, window.FIN_TODAY) === 1);
    const late = rows.filter(r => !r.paid_at && r.status === "atrasado");
    return {
      tRec: today.filter(r => r.kind === "receivable"),
      tPay: today.filter(r => r.kind === "payable"),
      tomorrow,
      late,
    };
  }, [rows]);
  if (t.tRec.length === 0 && t.tPay.length === 0 && t.tomorrow.length === 0 && t.late.length === 0) return null;
  return (
    <div className="fin-jana-today">
      <span className="fin-jana-today-ico"><I.Sparkles size={11}/></span>
      <b>Hoje</b>
      {t.tRec.length > 0 && <span className="fin-jana-today-chip in"><b className="num">{t.tRec.length}</b> a receber</span>}
      {t.tPay.length > 0 && <span className="fin-jana-today-chip out"><b className="num">{t.tPay.length}</b> a pagar</span>}
      {t.tomorrow.length > 0 && <span className="fin-jana-today-chip warn"><b className="num">{t.tomorrow.length}</b> vence amanhã</span>}
      {t.late.length > 0 && <span className="fin-jana-today-chip late"><b className="num">{t.late.length}</b> em atraso</span>}
      <span className="fin-jana-today-foot">Jana propõe · você decide</span>
    </div>
  );
};

/* ─── App ─────────────────────────────────────────────────────────────── */
const FinanceiroPage = () => {
  const [rows, setRows] = useState(() => window.FIN_ROWS);
  const [typeFilter, setTypeFilter] = useState(() => new Set(lsGet("typeFilter", [])));
  const [showVencido, setShowVencido] = useState(() => lsGet("showVencido", false));
  const [query, setQuery] = useState("");
  const [period, setPeriod] = useState(() => lsGet("period", "Maio 2026"));
  const [categorySel, setCategorySel] = useState(() => new Set(lsGet("category", [])));
  const [accountSel, setAccountSel] = useState(() => lsGet("account", "itau"));
  const [selected, setSelected] = useState(new Set());
  const [drawerRow, setDrawerRow] = useState(null);
  const [cmdkOpen, setCmdkOpen] = useState(false);
  const [cheatOpen, setCheatOpen] = useState(false);
  const [janaOpen, setJanaOpen] = useState(false);
  const [janaInitialQ, setJanaInitialQ] = useState("");
  const [toast, setToast] = useState("");
  const [density, setDensity] = useState(() => lsGet("density", "compact"));
  const [groupByDate, setGroupByDate] = useState(true);
  const [tela, setTela] = useState(() => lsGet("tela", "lanc"));
  const [cursorId, setCursorId] = useState(null);
  const [gPress, setGPress] = useState(false);
  const [trailsOpen, setTrailsOpen] = useState(false);

  // Persist
  useEffect(() => { lsSet("tela", tela); }, [tela]);
  useEffect(() => { lsSet("typeFilter", [...typeFilter]); }, [typeFilter]);
  useEffect(() => { lsSet("showVencido", showVencido); }, [showVencido]);
  useEffect(() => { lsSet("period", period); }, [period]);
  useEffect(() => { lsSet("category", [...categorySel]); }, [categorySel]);
  useEffect(() => { lsSet("account", accountSel); }, [accountSel]);
  useEffect(() => { lsSet("density", density); }, [density]);

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
    // Filtro tipo (união dos checks)
    if (typeFilter.size > 0) {
      r = r.filter((x) => {
        if (typeFilter.has("rec")      && x.kind === "receivable" && !x.paid_at) return true;
        if (typeFilter.has("pay")      && x.kind === "payable"    && !x.paid_at) return true;
        if (typeFilter.has("received") && x.kind === "receivable" &&  x.paid_at) return true;
        if (typeFilter.has("paid")     && x.kind === "payable"    &&  x.paid_at) return true;
        return false;
      });
    }
    // Filtro vencido (interseção)
    if (showVencido) r = r.filter((x) => !x.paid_at && x.status === "atrasado");
    if (categorySel.size > 0) r = r.filter((x) => categorySel.has(x.category));
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
  }, [rows, typeFilter, showVencido, categorySel, query]);

  // KB-9.75 R#2 · enriquece linhas filtradas com anomaly + today
  const enrichedFiltered = useMemo(() => {
    const anomalies = detectAnomalies(rows);
    return filtered.map(r => ({
      ...r,
      _anomaly: anomalies.get(r.id),
      _today: sameDay(r.due, window.FIN_TODAY),
    }));
  }, [filtered, rows]);

  const handleMark = useCallback((id) => {
    setRows((rs) => rs.map((r) => r.id === id ? { ...r, paid_at: window.FIN_TODAY, status: r.kind === "receivable" ? "recebido" : "pago" } : r));
    const row = rows.find((r) => r.id === id);
    if (row) setToast(`✓ ${row.kind === "receivable" ? "Recebido" : "Pago"} · ${row.party}`);
  }, [rows]);

  const handleMarkAll = useCallback(() => {
    setRows((rs) => rs.map((r) => selected.has(r.id) && !r.paid_at
      ? { ...r, paid_at: window.FIN_TODAY, status: r.kind === "receivable" ? "recebido" : "pago" }
      : r
    ));
    setToast(`✓ ${selected.size} lançamentos liquidados`);
    setSelected(new Set());
  }, [selected]);

  // Keyboard canônico R#1.b ── J/K cursor · Enter/Space/x/e · ? cheat · ⌘K
  useEffect(() => {
    const onKey = (e) => {
      const inInput = ["INPUT", "TEXTAREA"].includes(document.activeElement?.tagName);
      const lower = e.key.toLowerCase();

      // ⌘K · ⌘J · ⌘E · /
      if ((e.metaKey || e.ctrlKey) && lower === "k") {
        e.preventDefault(); setCmdkOpen(true); return;
      }
      if ((e.metaKey || e.ctrlKey) && lower === "j") {
        e.preventDefault();
        setJanaInitialQ("");
        setJanaOpen(true);
        return;
      }
      if ((e.metaKey || e.ctrlKey) && lower === "e") {
        e.preventDefault();
        console.log("[Financeiro] Exportar — placeholder R#4");
        return;
      }
      if (e.key === "/" && !inInput) { e.preventDefault(); setCmdkOpen(true); return; }
      if (e.key === "Escape") {
        setCmdkOpen(false);
        setCheatOpen(false);
        setJanaOpen(false);
        setDrawerRow(null);
        return;
      }
      if (e.key === "?" && !inInput) {
        e.preventDefault();
        setCheatOpen((v) => !v);
        return;
      }
      // Sub-rotas 1-2 (Fluxo e DRE foram para sidebar do shell)
      if (!inInput && !e.metaKey && !e.ctrlKey && ["1","2"].includes(e.key)) {
        e.preventDefault();
        const found = FIN_SUB.find((s) => s.key === e.key);
        if (found) setTela(found.id);
        return;
      }
      // Tudo abaixo só na tela de Lançamentos
      if (tela !== "lanc" || inInput || e.metaKey || e.ctrlKey) return;

      // Cursor J/K · g/G
      const ids = filtered.map((r) => r.id);
      const curIdx = cursorId ? ids.indexOf(cursorId) : -1;
      if (lower === "j") {
        e.preventDefault();
        const next = curIdx < 0 ? 0 : Math.min(ids.length - 1, curIdx + 1);
        setCursorId(ids[next] || null);
        return;
      }
      if (lower === "k") {
        e.preventDefault();
        const next = curIdx < 0 ? 0 : Math.max(0, curIdx - 1);
        setCursorId(ids[next] || null);
        return;
      }
      if (lower === "g" && !e.shiftKey) {
        // gg → topo
        if (gPress) {
          setCursorId(ids[0] || null);
          setGPress(false);
        } else {
          setGPress(true);
          setTimeout(() => setGPress(false), 500);
        }
        return;
      }
      if (e.key === "G" && e.shiftKey) {
        e.preventDefault();
        setCursorId(ids[ids.length - 1] || null);
        return;
      }
      // Enter abre detalhe
      if (e.key === "Enter" && cursorId) {
        e.preventDefault();
        const row = filtered.find((r) => r.id === cursorId);
        if (row) setDrawerRow(row);
        return;
      }
      // Space marca pago/recebido
      if (e.key === " " && cursorId) {
        e.preventDefault();
        const row = filtered.find((r) => r.id === cursorId);
        if (row && !row.paid_at) handleMark(row.id);
        return;
      }
      // x seleciona pro lote
      if (lower === "x" && cursorId) {
        e.preventDefault();
        setSelected((s) => {
          const n = new Set(s);
          n.has(cursorId) ? n.delete(cursorId) : n.add(cursorId);
          return n;
        });
        return;
      }
      // e editar (placeholder)
      if (lower === "e" && cursorId) {
        e.preventDefault();
        const row = filtered.find((r) => r.id === cursorId);
        if (row) setDrawerRow(row);
        console.log("[Financeiro] Editar lançamento — placeholder R#3");
        return;
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [selected, drawerRow, cursorId, gPress, filtered, tela, handleMark]);

  // Drawer aberto pelo cursor: NÃO abre automaticamente (libera leitura);
  // só atualiza row do drawer se já estiver aberto.
  useEffect(() => {
    if (cursorId && drawerRow && drawerRow.id !== cursorId) {
      const row = filtered.find((r) => r.id === cursorId);
      if (row) setDrawerRow(row);
    }
  }, [cursorId]);

  return (
    <div className="os-page fin-root fin-cockpit" data-screen-label="01 Financeiro">
      <FinHero tela={tela}
               setTela={setTela}
               onCmdK={() => setCmdkOpen(true)}
               onJana={() => { setJanaInitialQ(""); setJanaOpen(true); }}
               onNew={() => {}}
               onMore={() => {}}
               onConciliar={() => setTela("concil")}
               counts={{ lancN: rows.length, concilN: rows.filter((r) => !r.paid_at).length }}/>

      <div className="fin-cockpit-body">
        <div className="fin-cockpit-mid">
          {tela === "lanc" && <>
            <KPIStrip rows={rows}/>
            <FilterBar typeFilter={typeFilter} setTypeFilter={setTypeFilter}
                       showVencido={showVencido} setShowVencido={setShowVencido}
                       counts={counts} query={query} setQuery={setQuery}
                       density={density} setDensity={setDensity}/>
            <JanaTodayBanner rows={rows}/>
            <Table rows={enrichedFiltered} density={density}
                   selected={selected} setSelected={setSelected}
                   selectedDrawerId={drawerRow?.id}
                   cursorId={cursorId}
                   onOpen={(r) => { setDrawerRow(r); setCursorId(r.id); }} onMark={handleMark}
                   groupByDate={groupByDate}/>
            <FooterBar rows={filtered} selected={selected}
                       onClearSelected={() => setSelected(new Set())}
                       onMarkAll={handleMarkAll}/>
          </>}
          {tela === "concil" && <window.TelaConciliacao/>}
        </div>

        <DetailPanel row={drawerRow} onMark={handleMark} onClose={() => setDrawerRow(null)}/>
      </div>

      <CmdK open={cmdkOpen} onClose={() => setCmdkOpen(false)} rows={rows} onPick={setDrawerRow} onJanaAsk={(q) => { setJanaInitialQ(q || ""); setJanaOpen(true); setCmdkOpen(false); }}/>
      <CheatSheet open={cheatOpen} onClose={() => setCheatOpen(false)}/>
      <JanaPanel open={janaOpen} onClose={() => setJanaOpen(false)} rows={rows} initialQ={janaInitialQ}/>
      <TrailsLauncher onOpen={() => setTrailsOpen(true)}/>
      <TrailsModal open={trailsOpen} onClose={() => setTrailsOpen(false)} onPick={(t) => { setTrailsOpen(false); setToast(`Trilha · ${t.title} · em breve no R#3.1`); }}/>
      <Toast msg={toast} onDone={() => setToast("")}/>
    </div>
  );
};

window.FinanceiroPage = FinanceiroPage;
})();
