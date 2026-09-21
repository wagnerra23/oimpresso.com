// Cobrança Recorrente — página canônica (Refino #1 do Método KB-9.75).
// Layout 3-col fixo (Filtros · Lista · Detalhe), J/K, ⌘K, RecStatusBadge, responsive 3-tabs.
// Sub-rotas: assinaturas | planos | faturas | config (controladas via prop `tab`).
// IIFE: expõe window.RecurringPage.
(() => {
const { useState, useMemo, useEffect, useRef, useCallback } = React;
const I = window.REC_I;

const fmtBRL = (n) => n.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
const fmtBRLshort = (n) => {
  if (Math.abs(n) >= 1000) return "R$ " + (n / 1000).toLocaleString("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + "k";
  return fmtBRL(n);
};

// Avatar gradient — derivado do nome (hash simples → hue 0-360)
function hueFor(name) {
  let h = 0;
  for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) >>> 0;
  return h % 360;
}
function Avatar({ name, size = 28 }) {
  const h = hueFor(name);
  const initials = (name || "").split(/\s+/).slice(0, 2).map(w => w[0] || "").join("").toUpperCase().slice(0, 2);
  return (
    <span className="rec-av"
          style={{
            width: size, height: size, fontSize: size * 0.42,
            background: `linear-gradient(135deg, oklch(0.70 0.10 ${h}), oklch(0.50 0.13 ${h}))`,
          }}>
      {initials}
    </span>
  );
}

// Ícone do método de pagamento
function MethodIcon({ method, size = 12 }) {
  const Icon = method === "pix" ? I.pix : method === "boleto" ? I.boleto : I.card;
  const cls = "rec-method-ic rec-method-ic--" + method;
  return <span className={cls} title={ {pix:"Pix",boleto:"Boleto",card:"Cartão"}[method] }><Icon size={size}/></span>;
}

// Frescor: dias desde uma data ISO ou string
function daysAgo(iso) {
  if (!iso || !iso.includes("-")) return null;
  const d = new Date(iso);
  if (isNaN(d.getTime())) return null;
  const diff = Math.floor((Date.now() - d.getTime()) / 86400000);
  return diff;
}
function freshLabel(iso) {
  const d = daysAgo(iso);
  if (d == null) return null;
  if (d <= 0) return "hoje";
  if (d === 1) return "ontem";
  if (d < 30) return `há ${d}d`;
  if (d < 365) return `há ${Math.floor(d/30)}m`;
  return `há ${Math.floor(d/365)}a`;
}

/* ─── RecStatusBadge — pílula de status canônica (5 estados) ─── */
const STATUS_SPEC = {
  em_dia:      { label: "em dia",      cls: "rec-st--ok"     },
  retentando:  { label: "retentando",  cls: "rec-st--retry"  },
  falhou:      { label: "falhou",      cls: "rec-st--fail"   },
  pausada:     { label: "pausada",     cls: "rec-st--pause"  },
  cancelada:   { label: "cancelada",   cls: "rec-st--cancel" },
};
function RecStatusBadge({ status, retry, retryMax }) {
  const s = STATUS_SPEC[status] || STATUS_SPEC.em_dia;
  if (status === "retentando" && retry != null) {
    const max = retryMax || 3;
    return (
      <span className={"rec-st " + s.cls}>
        <span className="rec-st-pips">
          {Array.from({ length: max }, (_, i) => (
            <span key={i} className={"rec-st-pip" + (i < retry ? " done" : "")}/>
          ))}
        </span>
        retentando {retry}/{max}
      </span>
    );
  }
  if (status === "falhou" && retry != null) {
    return <span className={"rec-st " + s.cls}>falhou {retry}x</span>;
  }
  return <span className={"rec-st " + s.cls}>{s.label}</span>;
}

/* ─── KPI strip ─── */
function KpiStrip({ kpis, mrrSpark, onClickKpi }) {
  const cards = [
    { key: "mrr",     label: "MRR · receita recorrente", value: fmtBRL(kpis.mrr),
      delta: `↑ ${fmtBRLshort(kpis.mrrDelta)} vs abr`, deltaCls: "ok", hero: true, spark: mrrSpark },
    { key: "churn",   label: "Churn maio", value: `${kpis.churnCount} cancelamento`,
      delta: `taxa ${kpis.churnRate}%`, deltaCls: "warn" },
    { key: "next",    label: "Próxima cobrança", value: kpis.nextChargeWhen,
      delta: `${fmtBRL(kpis.nextChargeValue)} · ${kpis.nextChargeCount} boletos`, deltaCls: "" },
    { key: "failed",  label: "Retentado falhos", value: kpis.failedCount,
      delta: "requer ação", deltaCls: "bad" },
  ];
  return (
    <div className="rec-kpis">
      {cards.map(c => (
        <button key={c.key}
                className={"rec-kpi" + (c.hero ? " rec-kpi--hero" : "")}
                onClick={() => onClickKpi?.(c.key)}>
          <div className="rec-kpi-top">
            <small>{c.label}</small>
            {c.spark && (
              <svg className="rec-spark" viewBox="0 0 80 24" preserveAspectRatio="none">
                <defs>
                  <linearGradient id="rec-spark-g" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stopColor="oklch(0.75 0.13 145)" stopOpacity="0.45"/>
                    <stop offset="100%" stopColor="oklch(0.75 0.13 145)" stopOpacity="0"/>
                  </linearGradient>
                </defs>
                <path d={c.spark.area} fill="url(#rec-spark-g)"/>
                <path d={c.spark.line} stroke="oklch(0.75 0.13 145)" strokeWidth="1.5" fill="none" strokeLinecap="round" strokeLinejoin="round"/>
              </svg>
            )}
          </div>
          <b>{c.value}</b>
          <span className={"rec-kpi-delta rec-kpi-delta--" + c.deltaCls}>{c.delta}</span>
        </button>
      ))}
    </div>
  );
}

/* ─── Cheat-sheet flutuante (atalho `?`) ─── */
function CheatSheet({ onClose }) {
  const items = [
    ["J / K",     "navegar lista"],
    ["↵",         "abrir detalhe"],
    ["B",         "favoritar / desfavoritar"],
    ["R",         "retentar cobrança"],
    ["P",         "pausar / reativar"],
    ["E",         "editar plano"],
    ["N",         "nova assinatura"],
    ["/",         "focar busca"],
    ["⌘K",        "command palette"],
    ["⇧P",        "modo apresentação"],
    ["⇧E",        "imprimir extrato"],
    ["1 2 3 4",   "alternar sub-rotas"],
    ["Esc",       "fechar"],
  ];
  return (
    <div className="rec-cheat-overlay" onClick={onClose}>
      <div className="rec-cheat" onClick={e => e.stopPropagation()}>
        <div className="rec-cheat-h">
          <I.keyboard size={14}/>
          <b>Atalhos · Cobrança recorrente</b>
          <button className="rec-cheat-x" onClick={onClose}><I.close size={12}/></button>
        </div>
        <ul>
          {items.map(([k, l]) => (
            <li key={k}><kbd>{k}</kbd><span>{l}</span></li>
          ))}
        </ul>
        <div className="rec-cheat-foot">Pressione <kbd>?</kbd> para abrir · <kbd>Esc</kbd> para fechar</div>
      </div>
    </div>
  );
}

/* ─── Command palette (⌘K) — escopada em assinaturas, planos, próximas ─── */
function CmdPalette({ onClose, onPick, subs, plans }) {
  const [q, setQ] = useState("");
  const [iaState, setIaState] = useState({ loading: false, text: "" });
  const inputRef = useRef(null);
  useEffect(() => { inputRef.current?.focus(); }, []);

  const results = useMemo(() => {
    const norm = (s) => (s || "").toLowerCase();
    const t = norm(q);
    if (!t) return [
      ...subs.slice(0, 5).map(s => ({ kind: "sub", id: s.id, label: s.client, sub: "Assinante · " + s.plan })),
      ...plans.map(p => ({ kind: "plan", id: p.id, label: p.name, sub: `Plano · ${fmtBRL(p.price)}/${p.cycle === "mensal" ? "mês" : "trim"}` })),
    ];
    const subHits = subs.filter(s => norm(s.client).includes(t) || norm(s.cnpj).includes(t) || (s.os && norm(s.os).includes(t)))
      .slice(0, 8)
      .map(s => ({ kind: "sub", id: s.id, label: s.client, sub: `Assinante · ${s.os || "—"} · ${STATUS_SPEC[s.status].label}` }));
    const planHits = plans.filter(p => norm(p.name).includes(t))
      .map(p => ({ kind: "plan", id: p.id, label: p.name, sub: `Plano · ${fmtBRL(p.price)}/mês` }));
    return [...subHits, ...planHits];
  }, [q, subs, plans]);

  const askIa = useCallback(async () => {
    setIaState({ loading: true, text: "" });
    const ctx = `Lista total de assinantes (${subs.length}):\n` +
      subs.slice(0, 30).map(s => `- ${s.client} (${s.cnpj}) · plano ${s.plan} · status ${s.status} · LTV ${fmtBRL(s.ltv)}`).join("\n");
    const ask = `Responda como Jana, copiloto de cobrança recorrente do ERP Oimpresso. Pergunta da Eliana (operadora de financeiro): "${q}". Use os dados abaixo. Se não houver dado suficiente, diga "preciso consultar X". Tom direto, ≤80 palavras, em português brasileiro.\n\n${ctx}`;
    try {
      const txt = await window.claude.complete(ask);
      setIaState({ loading: false, text: txt });
    } catch (e) {
      setIaState({ loading: false, text: "(Não consegui consultar a Jana agora.)" });
    }
  }, [q, subs]);

  const [sel, setSel] = useState(0);
  useEffect(() => { setSel(0); setIaState({ loading: false, text: "" }); }, [q]);
  useEffect(() => {
    const onKey = (e) => {
      if (e.key === "Escape") onClose();
      if (e.key === "ArrowDown") { e.preventDefault(); setSel(s => Math.min(s + 1, results.length - 1)); }
      if (e.key === "ArrowUp")   { e.preventDefault(); setSel(s => Math.max(s - 1, 0)); }
      if (e.key === "Enter")     { e.preventDefault(); results[sel] && onPick(results[sel]); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [results, sel, onClose, onPick]);

  return (
    <div className="rec-cmd-overlay" onClick={onClose}>
      <div className="rec-cmd" onClick={e => e.stopPropagation()}>
        <div className="rec-cmd-h">
          <I.search size={14}/>
          <input ref={inputRef} value={q} onChange={e => setQ(e.target.value)}
                 placeholder="Buscar assinante, CNPJ, plano, OS — ou perguntar à Jana"/>
          <kbd className="rec-cmd-esc">Esc</kbd>
        </div>
        <ul className="rec-cmd-list">
          {results.length === 0 && q.trim() && !iaState.text && !iaState.loading && (
            <li className="rec-cmd-empty">
              <p style={{margin: "0 0 8px"}}>Nada encontrado para "<b>{q}</b>".</p>
              <button className="rec-cmd-ia" onClick={askIa}>
                <span className="jana-spark">✦</span> Perguntar à Jana →
              </button>
            </li>
          )}
          {iaState.loading && (
            <li className="rec-cmd-empty">
              <span className="jana-dots"><i/><i/><i/></span>
              <span style={{marginLeft: 8}}>Jana pensando…</span>
            </li>
          )}
          {iaState.text && (
            <li className="rec-cmd-ia-resp">
              <div className="rec-cmd-ia-h"><span className="jana-spark">✦</span>Jana</div>
              <div className="jana-output">{renderJanaText(iaState.text)}</div>
            </li>
          )}
          {results.map((r, i) => (
            <li key={r.kind + r.id} className={"rec-cmd-row" + (i === sel ? " sel" : "")}
                onMouseEnter={() => setSel(i)}
                onClick={() => onPick(r)}>
              <span className={"rec-cmd-kind rec-cmd-kind--" + r.kind}>{r.kind === "sub" ? "assin." : "plano"}</span>
              <b>{r.label}</b>
              <small>{r.sub}</small>
              {i === sel && <kbd>↵</kbd>}
            </li>
          ))}
        </ul>
        <div className="rec-cmd-foot">
          <span><kbd>↑</kbd><kbd>↓</kbd> navegar</span>
          <span><kbd>↵</kbd> selecionar</span>
          <span><kbd>Esc</kbd> fechar</span>
        </div>
      </div>
    </div>
  );
}

/* ════════════════════════════════════════════════════════════════
 * REFINO #4 · SAÍDA · Presentation + Print + Tour
 * ──────────────────────────────────────────────────────────────── */

/* ─── Bloco Documento Fiscal ─── */
const FISCAL_LABELS = {
  nfe:  { label: "NFe",   long: "NFe · Nota Fiscal Eletrônica (produto)",     hue: 220 },
  nfse: { label: "NFS-e", long: "NFS-e · Nota Fiscal de Serviços Eletrônica", hue: 145 },
  none: { label: "Não emite", long: "Sem emissão de nota fiscal",             hue: 50  },
};

function FiscalBlock({ sub, plan }) {
  const f = sub.fiscal || { type: "none", channels: [] };
  const meta = FISCAL_LABELS[f.type] || FISCAL_LABELS.none;
  const hasNf = f.type !== "none";
  const channels = f.channels || [];

  return (
    <div className={"rec-fiscal rec-fiscal--" + f.type} style={{ "--fiscal-hue": meta.hue }}>
      <div className="rec-fiscal-l">
        <span className="rec-fiscal-tag">{meta.label}</span>
        <small>{meta.long}</small>
      </div>
      {hasNf && (
        <div className="rec-fiscal-r">
          <div className="rec-fiscal-ch">
            <span className="rec-fiscal-ch-l">Envio:</span>
            {channels.includes("whatsapp") && <span className="rec-fiscal-pill rec-fiscal-pill--wa">WhatsApp</span>}
            {channels.includes("email") && <span className="rec-fiscal-pill rec-fiscal-pill--mail">E-mail</span>}
            {channels.length === 0 && <span className="rec-fiscal-pill rec-fiscal-pill--off">— não enviar</span>}
          </div>
          {f.lastNf && (
            <div className="rec-fiscal-last">
              <small>Última:</small>
              <b className="rec-mono">{f.lastNf}</b>
              <button className="rec-fiscal-resend" title="Reenviar nota fiscal">
                <I.refresh size={10}/>Reenviar
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

/* ─── Modo apresentação · overlay full com dashboard limpo ─── */
function PresentationMode({ onClose, kpis, subs, plans }) {
  const activeSubs = subs.filter(s => s.status !== "cancelada");
  const byPlan = plans.map(p => {
    const n = activeSubs.filter(s => s.plan === p.id).length;
    const mrr = activeSubs.filter(s => s.plan === p.id && s.status === "em_dia").reduce((a, s) => a + (s.nextValue || p.price), 0);
    return { ...p, n, mrr };
  }).sort((a, b) => b.mrr - a.mrr);
  const maxMrr = Math.max(...byPlan.map(p => p.mrr), 1);

  useEffect(() => {
    const onKey = (e) => { if (e.key === "Escape") onClose(); };
    window.addEventListener("keydown", onKey);
    document.body.style.overflow = "hidden";
    return () => { window.removeEventListener("keydown", onKey); document.body.style.overflow = ""; };
  }, [onClose]);

  return (
    <div className="rec-present">
      <button className="rec-present-close" onClick={onClose}>Sair · Esc</button>
      <div className="rec-present-inner">
        <header>
          <div className="rec-present-brand">
            <b>Oimpresso</b>
            <small>Cobrança Recorrente · ROTA LIVRE Matriz</small>
          </div>
          <span className="rec-present-date">{new Date().toLocaleDateString("pt-BR", { day: "2-digit", month: "long", year: "numeric" })}</span>
        </header>
        <div className="rec-present-grid">
          <div className="rec-present-hero">
            <small>MRR · receita recorrente mensal</small>
            <b>{fmtBRL(kpis.mrr)}</b>
            <span className="rec-present-delta">↑ {fmtBRL(kpis.mrrDelta)} vs abril · +16.6%</span>
          </div>
          <div className="rec-present-stat">
            <small>Ativas</small>
            <b>{kpis.activeCount}</b>
          </div>
          <div className="rec-present-stat">
            <small>Churn mai</small>
            <b style={{color: "oklch(0.70 0.13 60)"}}>{kpis.churnCount}</b>
            <span>taxa {kpis.churnRate}%</span>
          </div>
          <div className="rec-present-stat">
            <small>LTV total</small>
            <b>{fmtBRLshort(kpis.totalLtv)}</b>
          </div>
        </div>
        <section className="rec-present-section">
          <h3>Distribuição por plano</h3>
          <ul className="rec-present-plans">
            {byPlan.map((p, i) => {
              const hues = [295, 250, 60, 145, 200];
              const hue = hues[i % hues.length];
              return (
                <li key={p.id} style={{ "--plan-hue": hue }}>
                  <span className="rec-present-plan-name">{p.name}</span>
                  <span className="rec-present-plan-n">{p.n} assin.</span>
                  <div className="rec-present-plan-track">
                    <div className="rec-present-plan-fill" style={{ width: (p.mrr / maxMrr) * 100 + "%" }}/>
                  </div>
                  <span className="rec-present-plan-mrr">{fmtBRL(p.mrr)}<small>/mês</small></span>
                </li>
              );
            })}
          </ul>
        </section>
        <footer>
          <span>Modo apresentação · dados sensíveis ocultos · {kpis.failedCount} requer ação interna</span>
        </footer>
      </div>
    </div>
  );
}

/* ─── Print extrato — adiciona class + chama window.print ─── */
function printSubDetail(sub) {
  document.body.classList.add("rec-printing");
  document.body.setAttribute("data-print-sub-id", sub.id);
  setTimeout(() => {
    window.print();
    setTimeout(() => {
      document.body.classList.remove("rec-printing");
      document.body.removeAttribute("data-print-sub-id");
    }, 100);
  }, 80);
}

/* ─── Tour onboarding · 4 passos sobre features-tipo (G3) ─── */
const TOUR_STEPS = [
  {
    target: "rec-page-h",
    title: "1 · Navegação canônica",
    body: "Sub-rotas no topo (Assinaturas · Planos · Faturas · Configurações) com atalhos 1/2/3/4. Botão de busca abre command palette (⌘K) e atalhos visíveis com tecla `?`.",
  },
  {
    target: "rec-col-filters",
    title: "2 · Filtros + favoritos",
    body: "Filtre por próxima cobrança (Hoje · Amanhã · Semana), status, ou só favoritos (★). Tecla `B` favorita a assinatura selecionada. MRR filtrado aparece no rodapé.",
  },
  {
    target: "jana-panel",
    title: "3 · Jana · copiloto IA",
    body: "Em retentativas falhas, a Jana já sugere ação automaticamente. Em em-dia, peça resumo ou pergunte sobre histórico. Sempre cita números, nunca inventa.",
  },
  {
    target: "rec-tl",
    title: "4 · Histórico + troubleshooters",
    body: "Notas humanas e eventos automáticos numa linha do tempo única. Use #os4821 ou #cli23 nas notas pra criar links. Para retentativas problemáticas, o botão Diagnosticar abre wizards de fluxo.",
  },
];

function OnboardingTour({ onClose, step, setStep }) {
  const total = TOUR_STEPS.length;
  const cur = TOUR_STEPS[step];

  useEffect(() => {
    const onKey = (e) => {
      if (e.key === "Escape") onClose();
      if (e.key === "ArrowRight") setStep(s => Math.min(s + 1, total - 1));
      if (e.key === "ArrowLeft")  setStep(s => Math.max(s - 1, 0));
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [setStep, onClose, total]);

  // highlight do target via classe
  useEffect(() => {
    document.querySelectorAll(".rec-tour-spot").forEach(el => el.classList.remove("rec-tour-spot"));
    const el = document.querySelector("." + cur.target);
    if (el) el.classList.add("rec-tour-spot");
    return () => {
      const el2 = document.querySelector("." + cur.target);
      if (el2) el2.classList.remove("rec-tour-spot");
    };
  }, [cur.target]);

  return (
    <div className="rec-tour-overlay">
      <div className="rec-tour-card">
        <header>
          <span className="rec-tour-count">passo {step + 1} de {total}</span>
          <button className="rec-tour-x" onClick={onClose}><I.close size={12}/></button>
        </header>
        <h4>{cur.title}</h4>
        <p>{cur.body}</p>
        <div className="rec-tour-bar">
          {TOUR_STEPS.map((_, i) => (
            <span key={i} className={"rec-tour-pip" + (i <= step ? " done" : "")}/>
          ))}
        </div>
        <div className="rec-tour-actions">
          {step > 0 && <button className="rec-btn" onClick={() => setStep(s => s - 1)}>← Anterior</button>}
          {step < total - 1
            ? <button className="rec-btn rec-btn--primary" onClick={() => setStep(s => s + 1)}>Próximo →</button>
            : <button className="rec-btn rec-btn--primary" onClick={onClose}>Concluir</button>}
        </div>
        <small className="rec-tour-kbd">↑↓ ← → para navegar · Esc para fechar</small>
      </div>
    </div>
  );
}

/* ════════════════════════════════════════════════════════════════
 * CROSS-LINK · #osXXXX, #refXX viram links clicáveis (G4)
 * ──────────────────────────────────────────────────────────────── */
function linkifyRefs(text, onClick) {
  if (typeof text !== "string") return text;
  const parts = text.split(/(\s|[,.;:!?\(\)])/);
  return parts.map((p, i) => {
    const m = p.match(/^#(os|a|sub|cli)(\d+)$/i);
    if (!m) return p;
    const kind = m[1].toLowerCase();
    const id = m[2];
    return <a key={i} className={"rec-ref rec-ref--" + kind} onClick={(e) => { e.stopPropagation(); onClick?.(kind, id); }}>{p}</a>;
  });
}

/* ════════════════════════════════════════════════════════════════
 * NOTES TIMELINE · Comentários + eventos (C2 + C5)
 * ──────────────────────────────────────────────────────────────── */
const EVENT_META = {
  "event-create": { label: "Criada",         cls: "create" },
  "event-charge": { label: "Cobrança",       cls: "charge" },
  "event-retry":  { label: "Retentativa",    cls: "retry"  },
  "event-status": { label: "Mudança status", cls: "status" },
  "event-plan":   { label: "Mudança plano",  cls: "plan"   },
  "event-nf":     { label: "NF Fiscal",      cls: "nf"     },
  "note":         { label: "Nota interna",   cls: "note"   },
};

function fmtTimelineDate(iso) {
  const d = new Date(iso);
  if (isNaN(d.getTime())) return iso;
  const now = Date.now();
  const diffD = Math.floor((now - d.getTime()) / 86400000);
  const hhmm = d.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" });
  if (diffD < 1) return `hoje ${hhmm}`;
  if (diffD < 2) return `ontem ${hhmm}`;
  if (diffD < 7) return `${diffD}d atrás · ${hhmm}`;
  return d.toLocaleDateString("pt-BR", { day: "2-digit", month: "short" }) + " · " + hhmm;
}

function NotesTimeline({ subId, onRefClick }) {
  const items = (window.REC_DATA.TIMELINES?.[subId] || []).slice().reverse();
  const [draft, setDraft] = useState("");
  const [localNotes, setLocalNotes] = useState([]);

  useEffect(() => { setLocalNotes([]); setDraft(""); }, [subId]);

  const all = [
    ...localNotes,
    ...items,
  ];

  const submit = (e) => {
    e?.preventDefault?.();
    if (!draft.trim()) return;
    setLocalNotes(n => [{
      kind: "note",
      at: new Date().toISOString(),
      by: "Eliana",
      text: draft.trim(),
      isNew: true,
    }, ...n]);
    setDraft("");
  };

  return (
    <div className="rec-tl">
      <header className="rec-tl-h">
        <h4>Notas &amp; eventos</h4>
        <small>{all.length} {all.length === 1 ? "item" : "itens"}</small>
      </header>

      {/* Composer */}
      <form className="rec-tl-composer" onSubmit={submit}>
        <Avatar name="Eliana" size={22}/>
        <input value={draft} onChange={e => setDraft(e.target.value)}
               placeholder="Anotar internamente… (use #os4821, #cli23, #a3)"/>
        <button type="submit" disabled={!draft.trim()}>Anotar</button>
      </form>

      {/* Timeline */}
      <ul className="rec-tl-list">
        {all.length === 0 && (
          <li className="rec-tl-empty">Sem histórico ainda. Adicione a primeira nota acima.</li>
        )}
        {all.map((it, i) => {
          const meta = EVENT_META[it.kind] || EVENT_META.note;
          const isHuman = it.kind === "note";
          return (
            <li key={i} className={"rec-tl-item rec-tl-item--" + meta.cls + (it.isNew ? " is-new" : "")}>
              <div className="rec-tl-rail">
                <span className={"rec-tl-dot rec-tl-dot--" + meta.cls}/>
              </div>
              <div className="rec-tl-body">
                <header>
                  {isHuman ? <Avatar name={it.by} size={18}/> : <span className="rec-tl-sys">⚙</span>}
                  <b>{it.by}</b>
                  <span className="rec-tl-kind">{meta.label}</span>
                  <small>{fmtTimelineDate(it.at)}</small>
                </header>
                <p>{linkifyRefs(it.text, onRefClick)}</p>
              </div>
            </li>
          );
        })}
      </ul>
    </div>
  );
}

/* ════════════════════════════════════════════════════════════════
 * TROUBLESHOOTER · Wizard de fluxos de cobrança problemática (G1)
 * ──────────────────────────────────────────────────────────────── */
function TroubleshooterModal({ onClose, presetId, sub }) {
  const list = window.REC_DATA.TROUBLESHOOTERS;
  const [selectedId, setSelectedId] = useState(presetId || null);
  const selected = selectedId ? list.find(t => t.id === selectedId) : null;
  const [stepIdx, setStepIdx] = useState(0);
  const [path, setPath] = useState([]); // {q, answer}
  const step = selected ? selected.steps[stepIdx] : null;

  useEffect(() => { setStepIdx(0); setPath([]); }, [selectedId]);

  const pickOpt = (opt) => {
    setPath(p => [...p, { q: step.q, answer: opt.label }]);
    setStepIdx(opt.next);
  };

  const reset = () => { setSelectedId(null); setStepIdx(0); setPath([]); };

  return (
    <div className="rec-tr-overlay" onClick={onClose}>
      <div className="rec-tr" onClick={e => e.stopPropagation()}>
        <header className="rec-tr-h">
          <I.keyboard size={14}/>
          <b>Troubleshooters · cobrança</b>
          {sub && <small>· {sub.client}</small>}
          <button className="rec-tr-x" onClick={onClose}><I.close size={12}/></button>
        </header>

        {!selected && (
          <div className="rec-tr-list">
            <p className="rec-tr-intro">Escolha o problema. Cada wizard guia você até uma ação recomendada.</p>
            {list.map(t => {
              const Icon = I[t.icon] || I.refresh;
              return (
                <button key={t.id} className="rec-tr-card" onClick={() => setSelectedId(t.id)}
                        style={{ "--tr-hue": t.hue }}>
                  <Icon size={16}/>
                  <b>{t.title}</b>
                  <small>{t.steps.length - 1} passo{t.steps.length > 2 ? "s" : ""}</small>
                </button>
              );
            })}
          </div>
        )}

        {selected && (
          <div className="rec-tr-flow">
            <header className="rec-tr-flow-h" style={{ "--tr-hue": selected.hue }}>
              <button className="rec-tr-back" onClick={reset}>← Outros fluxos</button>
              <b>{selected.title}</b>
              <span className="rec-tr-progress">passo {Math.min(stepIdx + 1, selected.steps.length)} / {selected.steps.length}</span>
            </header>

            {path.length > 0 && (
              <ol className="rec-tr-path">
                {path.map((p, i) => (
                  <li key={i}>
                    <small>{p.q}</small>
                    <span>{p.answer}</span>
                  </li>
                ))}
              </ol>
            )}

            {step && !step.final && (
              <div className="rec-tr-step">
                <h5>{step.q}</h5>
                <div className="rec-tr-opts">
                  {step.opts.map((o, i) => (
                    <button key={i} className="rec-tr-opt" onClick={() => pickOpt(o)}>
                      {o.label}
                      <I.chevR size={11}/>
                    </button>
                  ))}
                </div>
              </div>
            )}

            {step && step.final && (
              <div className="rec-tr-final">
                <div className="rec-tr-final-tag">Ação recomendada</div>
                <p>{step.final}</p>
                <div className="rec-tr-final-actions">
                  <button className="rec-btn" onClick={reset}>← Recomeçar</button>
                  <button className="rec-btn rec-btn--primary" onClick={onClose}>Aplicar e fechar</button>
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

/* ════════════════════════════════════════════════════════════════
 * JANA — IA dentro do fluxo (Refino #2)
 * ──────────────────────────────────────────────────────────────── */

const JANA_PERSONA = `Você é Jana, copiloto interno de cobrança recorrente do ERP Oimpresso.
Persona-alvo: Eliana, financeiro escritório, gráfica de comunicação visual.
Tom: direto, em português brasileiro, ≤80 palavras. Cite dados específicos da assinatura.
Nunca invente: se faltar dado, diga "não sei" ou "consultar histórico completo".
Sempre proponha — humano decide. Use bullets curtos se houver 2+ ações.`;

function describeSub(sub, plan) {
  return [
    `Assinante: ${sub.client} (${sub.cnpj}).`,
    `Plano: ${plan?.name || "—"} · ${fmtBRL(plan?.price || 0)}/${plan?.cycle || "mês"} · método ${sub.method}.`,
    `Status atual: ${STATUS_SPEC[sub.status]?.label || sub.status}` + (sub.retry != null ? ` (${sub.retry}/${sub.retryMax || 3} tentativas)` : "") + ".",
    `Desde: ${new Date(sub.since).toLocaleDateString("pt-BR")} · pagas ${sub.paid}, falhas ${sub.missed}, LTV ${fmtBRL(sub.ltv)}.`,
    sub.notes ? `Nota interna: "${sub.notes}".` : null,
    sub.churn ? `Motivo cancelamento: ${sub.churn}.` : null,
    sub.contact ? `Contato: ${sub.contact.nome} · ${sub.contact.fone}.` : null,
  ].filter(Boolean).join("\n");
}

async function callJana(systemAddon, userMsg, ctxBlock) {
  const full = `${JANA_PERSONA}\n${systemAddon || ""}\n\nDados da assinatura:\n${ctxBlock}\n\nPergunta/tarefa:\n${userMsg}`;
  try {
    return await window.claude.complete(full);
  } catch (e) {
    return "(Não foi possível consultar a Jana agora. Tente novamente em alguns segundos.)";
  }
}

/* ─── Sugerir ação (auto-load quando falhou/retentando) ─── */
function JanaSugerir({ sub, plan }) {
  const [state, setState] = useState({ loading: false, text: "", error: null });
  const [autoRan, setAutoRan] = useState(false);

  const run = useCallback(async () => {
    setState({ loading: true, text: "", error: null });
    const sysAddon = `Tarefa: a cobrança está com problema. Proponha 2-3 ações concretas em bullets, com prioridade. Para cada ação, justifique em 1 frase com base no histórico do assinante (LTV, falhas anteriores, nota interna). Termine com a recomendação principal.`;
    const ask = `Status: ${STATUS_SPEC[sub.status]?.label}. ${sub.retry != null ? `Tentativa ${sub.retry}/${sub.retryMax || 3}.` : ""} Qual ação tomar agora?`;
    const txt = await callJana(sysAddon, ask, describeSub(sub, plan));
    setState({ loading: false, text: txt, error: null });
  }, [sub, plan]);

  // Auto-run uma vez quando o sub fica falhou/retentando
  useEffect(() => {
    if (autoRan) return;
    if (sub.status === "falhou" || sub.status === "retentando") {
      setAutoRan(true);
      run();
    }
  }, [sub.id, sub.status, autoRan, run]);

  // Reset autoRan ao trocar de sub
  useEffect(() => { setAutoRan(false); setState({ loading: false, text: "", error: null }); }, [sub.id]);

  return (
    <div className="jana-panel-content">
      {!state.text && !state.loading && (
        <button className="jana-btn" onClick={run}>
          <span className="jana-spark">✦</span>
          {sub.status === "falhou" || sub.status === "retentando"
            ? "Pedir sugestão de ação à Jana"
            : "Pedir análise da assinatura à Jana"}
        </button>
      )}
      {state.loading && (
        <div className="jana-loading">
          <span className="jana-dots"><i/><i/><i/></span>
          <span>Jana analisando histórico…</span>
        </div>
      )}
      {state.text && (
        <>
          <div className="jana-output">{renderJanaText(state.text)}</div>
          <div className="jana-actions">
            <button className="jana-mini" onClick={run}><I.retry size={10}/>Refazer</button>
            <button className="jana-mini">Aplicar ação</button>
          </div>
        </>
      )}
    </div>
  );
}

/* ─── Resumir histórico ─── */
function JanaResumir({ sub, plan }) {
  const [state, setState] = useState({ loading: false, text: "", error: null });

  const run = useCallback(async () => {
    setState({ loading: true, text: "", error: null });
    const sysAddon = `Tarefa: resumir a saúde dessa assinatura em até 4 bullets para um operador que precisa decidir em 30 segundos. Cite números específicos. Termine com "Saúde geral: [boa/atenção/crítica]".`;
    const ask = `Resuma esta assinatura.`;
    const txt = await callJana(sysAddon, ask, describeSub(sub, plan));
    setState({ loading: false, text: txt, error: null });
  }, [sub, plan]);

  useEffect(() => { setState({ loading: false, text: "", error: null }); }, [sub.id]);

  return (
    <div className="jana-panel-content">
      {!state.text && !state.loading && (
        <button className="jana-btn" onClick={run}>
          <span className="jana-spark">✦</span>Resumir saúde da assinatura
        </button>
      )}
      {state.loading && (
        <div className="jana-loading">
          <span className="jana-dots"><i/><i/><i/></span>
          <span>Jana lendo histórico…</span>
        </div>
      )}
      {state.text && (
        <>
          <div className="jana-output">{renderJanaText(state.text)}</div>
          <div className="jana-actions">
            <button className="jana-mini" onClick={run}><I.retry size={10}/>Refazer</button>
          </div>
        </>
      )}
    </div>
  );
}

/* ─── Perguntar livre ─── */
function JanaPerguntar({ sub, plan }) {
  const [q, setQ] = useState("");
  const [thread, setThread] = useState([]); // {role, text}
  const [loading, setLoading] = useState(false);
  const inputRef = useRef(null);

  useEffect(() => { setThread([]); setQ(""); }, [sub.id]);

  const ask = async (e) => {
    e?.preventDefault?.();
    const text = q.trim();
    if (!text || loading) return;
    setThread(t => [...t, { role: "user", text }]);
    setQ("");
    setLoading(true);
    const sysAddon = `Tarefa: responder pergunta do operador sobre a assinatura. Se a pergunta puder ser respondida pelos dados da assinatura, responda diretamente. Se exigir dados externos (ex: outras assinaturas do mesmo CNPJ, histórico de OS), diga "preciso consultar [X]" — não invente.`;
    const txt = await callJana(sysAddon, text, describeSub(sub, plan));
    setThread(t => [...t, { role: "jana", text: txt }]);
    setLoading(false);
  };

  const suggestions = [
    "Por que este cliente teve falhas?",
    "Vale a pena fazer dunning?",
    "Risco de churn nos próximos 30 dias?",
  ];

  return (
    <div className="jana-panel-content jana-ask">
      <div className="jana-thread">
        {thread.length === 0 && (
          <div className="jana-thread-empty">
            <small>Faça uma pergunta sobre <b>{sub.client}</b>. Sugestões:</small>
            <div className="jana-sugg">
              {suggestions.map(s => (
                <button key={s} className="jana-sugg-chip" onClick={() => { setQ(s); setTimeout(() => inputRef.current?.focus(), 0); }}>
                  {s}
                </button>
              ))}
            </div>
          </div>
        )}
        {thread.map((m, i) => (
          <div key={i} className={"jana-msg jana-msg--" + m.role}>
            {m.role === "jana" && <span className="jana-spark">✦</span>}
            <div>{m.role === "jana" ? renderJanaText(m.text) : m.text}</div>
          </div>
        ))}
        {loading && (
          <div className="jana-msg jana-msg--jana">
            <span className="jana-spark">✦</span>
            <span className="jana-dots"><i/><i/><i/></span>
          </div>
        )}
      </div>
      <form className="jana-input" onSubmit={ask}>
        <input ref={inputRef} value={q} onChange={e => setQ(e.target.value)}
               placeholder="Pergunte à Jana sobre essa assinatura…"
               disabled={loading}/>
        <button type="submit" className="jana-send" disabled={!q.trim() || loading}>↑</button>
      </form>
    </div>
  );
}

/* Render texto da Jana com bullets simples (linhas começando com - ou •) */
function renderJanaText(text) {
  if (!text) return null;
  const lines = text.split("\n").map(l => l.trim()).filter(Boolean);
  const out = [];
  let bulletBuf = [];
  const flushBullets = () => {
    if (bulletBuf.length) {
      out.push(<ul key={"u" + out.length} className="jana-ul">{bulletBuf.map((b, i) => <li key={i}>{b}</li>)}</ul>);
      bulletBuf = [];
    }
  };
  lines.forEach((l) => {
    if (/^[-•*]\s+/.test(l) || /^\d+[.)]\s+/.test(l)) {
      bulletBuf.push(l.replace(/^[-•*]\s+/, "").replace(/^\d+[.)]\s+/, ""));
    } else {
      flushBullets();
      out.push(<p key={"p" + out.length}>{l}</p>);
    }
  });
  flushBullets();
  return out;
}

/* ─── JanaPanel: orquestra as 3 tabs ─── */
function JanaPanel({ sub, plan }) {
  const isCritical = sub.status === "falhou" || sub.status === "retentando";
  const [tab, setTab] = useState(isCritical ? "sugerir" : "resumir");

  useEffect(() => {
    setTab(sub.status === "falhou" || sub.status === "retentando" ? "sugerir" : "resumir");
  }, [sub.id]);

  if (sub.status === "cancelada") return null; // sem sentido em assinatura morta

  return (
    <div className={"jana-panel" + (isCritical ? " jana-panel--critical" : "")}>
      <header className="jana-panel-h">
        <span className="jana-badge"><span className="jana-spark">✦</span>Jana · IA</span>
        <nav className="jana-tabs">
          <button className={tab === "sugerir" ? "active" : ""} onClick={() => setTab("sugerir")}>
            {isCritical && <span className="jana-dot-warn"/>}Sugerir
          </button>
          <button className={tab === "resumir" ? "active" : ""} onClick={() => setTab("resumir")}>Resumir</button>
          <button className={tab === "perguntar" ? "active" : ""} onClick={() => setTab("perguntar")}>Perguntar</button>
        </nav>
      </header>
      {tab === "sugerir"   && <JanaSugerir   sub={sub} plan={plan}/>}
      {tab === "resumir"   && <JanaResumir   sub={sub} plan={plan}/>}
      {tab === "perguntar" && <JanaPerguntar sub={sub} plan={plan}/>}
    </div>
  );
}

/* ════════════════════════════════════════════════════════════════
 * SUB-TAB · ASSINATURAS · 3-col canônico
 * ══════════════════════════════════════════════════════════════ */
const FILTERS = [
  { key: "all",        label: "Todas",       cls: "" },
  { key: "em_dia",     label: "Em dia",      cls: "ok" },
  { key: "retentando", label: "Retentando",  cls: "warn" },
  { key: "falhou",     label: "Falharam",    cls: "bad" },
  { key: "pausada",    label: "Pausadas",    cls: "mute" },
  { key: "cancelada",  label: "Canceladas",  cls: "mute" },
];

function AssinaturasView({ subs, plans, kpis, onOpenCmd, onOpenCheat }) {
  const [filter, setFilter] = useState(() => {
    try { return localStorage.getItem("oimpresso.rec.filter") || "all"; } catch { return "all"; }
  });
  const [whenFilter, setWhenFilter] = useState("any"); // any | today | tomorrow | week | month
  const [q, setQ] = useState("");
  const [activeId, setActiveId] = useState(() => {
    try { return localStorage.getItem("oimpresso.rec.active") || subs[0].id; } catch { return subs[0].id; }
  });
  const [mobileTab, setMobileTab] = useState("list");
  const [pinned, setPinned] = useState(() => {
    try { return new Set(JSON.parse(localStorage.getItem("oimpresso.rec.pinned") || "[\"s002\",\"s003\"]")); }
    catch { return new Set(["s002","s003"]); }
  });
  const [onlyPinned, setOnlyPinned] = useState(false);
  const searchRef = useRef(null);

  useEffect(() => { try { localStorage.setItem("oimpresso.rec.filter", filter); } catch {} }, [filter]);
  useEffect(() => { try { localStorage.setItem("oimpresso.rec.active", activeId); } catch {} }, [activeId]);
  useEffect(() => { try { localStorage.setItem("oimpresso.rec.pinned", JSON.stringify([...pinned])); } catch {} }, [pinned]);

  const togglePin = useCallback((id) => {
    setPinned(p => {
      const n = new Set(p);
      n.has(id) ? n.delete(id) : n.add(id);
      return n;
    });
  }, []);

  // Verifica se uma assinatura cai dentro do filtro temporal
  const matchWhen = (sub) => {
    if (whenFilter === "any") return true;
    if (sub.status === "cancelada") return false;
    if (typeof sub.nextAt !== "string") return false;
    // Casos textuais ("hoje 14:00", "amanhã 08:00")
    if (sub.nextAt.startsWith("hoje"))   return whenFilter === "today" || whenFilter === "week" || whenFilter === "month";
    if (sub.nextAt.startsWith("amanhã")) return whenFilter === "tomorrow" || whenFilter === "week" || whenFilter === "month";
    if (sub.nextAt === "manual")         return false;
    if (!sub.nextAt.includes("-"))       return false;
    const d = new Date(sub.nextAt);
    if (isNaN(d.getTime())) return false;
    const diff = Math.ceil((d.getTime() - Date.now()) / 86400000);
    if (whenFilter === "today")    return diff === 0;
    if (whenFilter === "tomorrow") return diff === 1;
    if (whenFilter === "week")     return diff >= 0 && diff <= 7;
    if (whenFilter === "month")    return diff >= 0 && diff <= 30;
    return true;
  };

  const filtered = useMemo(() => {
    const norm = (s) => (s || "").toLowerCase();
    const t = norm(q);
    const out = subs.filter(s => {
      if (onlyPinned && !pinned.has(s.id)) return false;
      if (filter !== "all" && s.status !== filter) return false;
      if (!matchWhen(s)) return false;
      if (t && !(norm(s.client).includes(t) || norm(s.cnpj).includes(t) || (s.os && norm(s.os).includes(t)))) return false;
      return true;
    });
    // Pinned ficam no topo
    return out.sort((a, b) => {
      const pa = pinned.has(a.id) ? 0 : 1;
      const pb = pinned.has(b.id) ? 0 : 1;
      return pa - pb;
    });
  }, [subs, filter, q, whenFilter, onlyPinned, pinned]);

  const counts = useMemo(() => {
    const c = { all: subs.length };
    subs.forEach(s => { c[s.status] = (c[s.status] || 0) + 1; });
    return c;
  }, [subs]);

  const whenCounts = useMemo(() => {
    const c = { any: subs.length, today: 0, tomorrow: 0, week: 0, month: 0 };
    subs.forEach(s => {
      const saved = whenFilter; let ok;
      ["today","tomorrow","week","month"].forEach(k => {
        // reusa o matcher pontual
        const temp = { ...s };
        const isMatch = (() => {
          if (temp.status === "cancelada") return false;
          if (typeof temp.nextAt !== "string") return false;
          if (temp.nextAt.startsWith("hoje"))   return k === "today" || k === "week" || k === "month";
          if (temp.nextAt.startsWith("amanhã")) return k === "tomorrow" || k === "week" || k === "month";
          if (temp.nextAt === "manual")         return false;
          if (!temp.nextAt.includes("-"))       return false;
          const d = new Date(temp.nextAt);
          if (isNaN(d.getTime())) return false;
          const diff = Math.ceil((d.getTime() - Date.now()) / 86400000);
          if (k === "today")    return diff === 0;
          if (k === "tomorrow") return diff === 1;
          if (k === "week")     return diff >= 0 && diff <= 7;
          if (k === "month")    return diff >= 0 && diff <= 30;
          return false;
        })();
        if (isMatch) c[k]++;
      });
    });
    return c;
  }, [subs]);

  // MRR filtrado (soma do nextValue/plan.price das assinaturas filtradas e não-canceladas)
  const filteredMrr = useMemo(() => {
    return filtered.reduce((acc, s) => {
      if (s.status === "cancelada") return acc;
      const p = plans.find(pp => pp.id === s.plan);
      return acc + (s.nextValue || p?.price || 0);
    }, 0);
  }, [filtered, plans]);

  const active = subs.find(s => s.id === activeId) || filtered[0];
  const plan = active ? plans.find(p => p.id === active.plan) : null;

  // J/K + atalhos
  useEffect(() => {
    const onKey = (e) => {
      const tgt = e.target;
      const inField = tgt.tagName === "INPUT" || tgt.tagName === "TEXTAREA" || tgt.isContentEditable;
      if (inField && e.key !== "Escape") return;
      const idx = filtered.findIndex(s => s.id === activeId);
      if (e.key === "j") { e.preventDefault(); const next = filtered[Math.min(idx + 1, filtered.length - 1)]; if (next) setActiveId(next.id); }
      else if (e.key === "k") { e.preventDefault(); const prev = filtered[Math.max(idx - 1, 0)]; if (prev) setActiveId(prev.id); }
      else if (e.key === "b" || e.key === "B") { e.preventDefault(); if (activeId) togglePin(activeId); }
      else if (e.key === "/") { e.preventDefault(); searchRef.current?.focus(); }
      else if (e.key === "Escape") { tgt.blur && tgt.blur(); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [filtered, activeId, togglePin]);

  const WHEN_FILTERS = [
    { key: "any",       label: "Qualquer data" },
    { key: "today",     label: "Hoje" },
    { key: "tomorrow",  label: "Amanhã" },
    { key: "week",      label: "Esta semana" },
    { key: "month",     label: "Próx. 30 dias" },
  ];

  return (
    <div className="rec-3col">
      {/* COLUNA 1 · FILTROS */}
      <aside className="rec-col rec-col-filters">
        <button className={"rec-fav-toggle" + (onlyPinned ? " active" : "")}
                onClick={() => setOnlyPinned(p => !p)}>
          <span className="rec-star">★</span>
          <span>{onlyPinned ? "Mostrando favoritos" : "Mostrar só favoritos"}</span>
          <small>{pinned.size}</small>
        </button>

        <div className="rec-col-h">Próxima cobrança</div>
        <ul className="rec-filter-list">
          {WHEN_FILTERS.map(f => (
            <li key={f.key}
                className={"rec-filter rec-filter--when" + (whenFilter === f.key ? " active" : "")}
                onClick={() => setWhenFilter(f.key)}>
              <span className="rec-filter-l">{f.label}</span>
              <span className="rec-filter-c">{whenCounts[f.key] || 0}</span>
            </li>
          ))}
        </ul>

        <div className="rec-col-h">Status</div>
        <ul className="rec-filter-list">
          {FILTERS.map(f => (
            <li key={f.key}
                className={"rec-filter" + (filter === f.key ? " active" : "")}
                onClick={() => setFilter(f.key)}>
              <span className={"rec-dot rec-dot--" + (f.cls || "default")}></span>
              <span className="rec-filter-l">{f.label}</span>
              <span className="rec-filter-c">{counts[f.key] || 0}</span>
            </li>
          ))}
        </ul>

        <div className="rec-col-h">Plano</div>
        <ul className="rec-plan-mini">
          {plans.map(p => {
            const n = subs.filter(s => s.plan === p.id && s.status !== "cancelada").length;
            return (
              <li key={p.id}>
                <b>{p.name}</b>
                <small>{fmtBRL(p.price)} · {n} ativ.</small>
              </li>
            );
          })}
        </ul>

        {/* Rodapé com MRR filtrado */}
        <div className="rec-filter-foot">
          <small>MRR filtrado</small>
          <b className="rec-mono">{fmtBRL(filteredMrr)}</b>
          <span>{filtered.filter(s => s.status !== "cancelada").length} ativ. de {subs.length}</span>
        </div>
      </aside>

      {/* COLUNA 2 · LISTA */}
      <section className={"rec-col rec-col-list" + (mobileTab === "list" ? " is-mobile-active" : "")}>
        <div className="rec-list-h">
          <div className="rec-search">
            <I.search size={12}/>
            <input ref={searchRef}
                   value={q} onChange={e => setQ(e.target.value)}
                   placeholder="Buscar (/) — cliente, CNPJ, OS"/>
            <kbd>/</kbd>
          </div>
          <span className="rec-list-count">{filtered.length} / {subs.length}</span>
        </div>
        <div className="rec-list-body">
          {filtered.length === 0 && (
            <div className="rec-empty">
              <b>Nada por aqui.</b>
              <p>Nenhuma assinatura com este filtro + busca.</p>
              <button className="rec-empty-cta" onClick={onOpenCmd}>Buscar globalmente ⌘K</button>
            </div>
          )}
          {filtered.map(s => {
            const p = plans.find(pp => pp.id === s.plan);
            const isPin = pinned.has(s.id);
            return (
              <div key={s.id}
                   className={"rec-row" + (s.id === activeId ? " sel" : "") + (isPin ? " is-pinned" : "")}
                   onClick={() => { setActiveId(s.id); setMobileTab("detail"); }}>
                <Avatar name={s.client}/>
                <div className="rec-row-main">
                  <b>
                    {isPin && <span className="rec-pin-mini" title="Favorito">★</span>}
                    {s.client}
                  </b>
                  <small>{p?.name || "—"} · desde {freshLabel(s.since) || "—"}</small>
                </div>
                <div className="rec-row-side">
                  <RecStatusBadge status={s.status} retry={s.retry} retryMax={s.retryMax}/>
                  {s.status !== "cancelada" && (
                    <span className="rec-row-amount">
                      <MethodIcon method={s.method} size={11}/>
                      <span className="rec-mono">{fmtBRL(s.nextValue || p?.price || 0)}</span>
                    </span>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </section>

      {/* COLUNA 3 · DETALHE */}
      <section className={"rec-col rec-col-detail" + (mobileTab === "detail" ? " is-mobile-active" : "")}>
        <button className="rec-mobile-back" onClick={() => setMobileTab("list")}>← Voltar à lista</button>
        {!active && <div className="rec-detail-empty">Selecione uma assinatura</div>}
        {active && <Detail sub={active} plan={plan} kpis={kpis}/>}
      </section>

      {/* Tabs mobile ≤1100 */}
      <nav className="rec-mobile-tabs">
        <button className={mobileTab === "list" ? "active" : ""} onClick={() => setMobileTab("list")}>Lista</button>
        <button className={mobileTab === "detail" ? "active" : ""} onClick={() => setMobileTab("detail")}>Detalhe</button>
      </nav>
    </div>
  );
}

/* ─── Detail · 3ª coluna fixa ─── */
function Detail({ sub, plan, kpis }) {
  const methodLabel = { pix: "Pix", boleto: "Boleto · Inter", card: "Cartão" }[sub.method] || sub.method;
  const sinceDate = new Date(sub.since);
  const nextIsIso = typeof sub.nextAt === "string" && sub.nextAt.includes("-");
  const nextDate = nextIsIso ? new Date(sub.nextAt) : null;
  const daysToNext = nextDate ? Math.ceil((nextDate.getTime() - Date.now()) / 86400000) : null;
  const nextLabel = nextIsIso
    ? (daysToNext === 0 ? "hoje" : daysToNext === 1 ? "amanhã" : daysToNext < 0 ? `há ${-daysToNext}d` : `em ${daysToNext} dias`)
    : sub.nextAt;
  const nextDateLabel = nextDate ? nextDate.toLocaleDateString("pt-BR", { day: "2-digit", month: "short" }) : null;

  const ticket = plan?.price || sub.nextValue || 0;
  const ltvMonths = ticket > 0 ? Math.round(sub.ltv / ticket) : 0;

  const [trouble, setTrouble] = useState(null); // null | "boleto-recusado" | ...
  const isCritical = sub.status === "falhou" || sub.status === "retentando";
  // Sugere troubleshooter pelo método em status crítico
  const suggestTrouble = isCritical
    ? (sub.method === "card" ? "cartao-expirado" : sub.missed >= 3 ? "suspensao" : "boleto-recusado")
    : null;

  return (
    <div className="rec-detail">
      <header className="rec-detail-h">
        <Avatar name={sub.client} size={36}/>
        <div className="rec-detail-h-info">
          <h3>{sub.client}</h3>
          <small>{sub.cnpj}</small>
        </div>
        <button className="rec-detail-print" title="Imprimir extrato (⇧E)" onClick={() => printSubDetail(sub)}>
          <I.file size={11}/>PDF
        </button>
        <RecStatusBadge status={sub.status} retry={sub.retry} retryMax={sub.retryMax}/>
      </header>

      {/* Card próxima cobrança · destaque */}
      {sub.status !== "cancelada" && (
        <div className={"rec-next" + (sub.status === "falhou" ? " rec-next--bad" : sub.status === "retentando" ? " rec-next--warn" : "")}>
          <div className="rec-next-l">
            <span className="rec-next-label">{sub.status === "falhou" ? "Ação manual" : "Próxima cobrança"}</span>
            <b className="rec-next-when">{nextLabel}</b>
            {nextDateLabel && <small>{nextDateLabel} · {plan?.cycle === "trimestral" ? "ciclo trimestral" : "ciclo mensal"}</small>}
          </div>
          <div className="rec-next-r">
            <span className="rec-next-amount">{fmtBRL(sub.nextValue || plan?.price || 0)}</span>
            <span className="rec-next-method"><MethodIcon method={sub.method} size={11}/>{methodLabel}</span>
          </div>
        </div>
      )}

      <div className="rec-detail-kv">
        <dl>
          <dt>Plano</dt><dd>{plan?.name || "—"}</dd>
          <dt>Ciclo</dt><dd>{plan?.cycle || "—"} · dia {sinceDate.getDate()}</dd>
          <dt>Desde</dt><dd>{sinceDate.toLocaleDateString("pt-BR")} <small className="rec-fresh">· {freshLabel(sub.since)}</small></dd>
          <dt>Cobranças pagas</dt><dd className="rec-mono">{sub.paid}</dd>
          <dt>Falhas</dt><dd className="rec-mono" style={{color: sub.missed > 0 ? "var(--rec-bad)" : "inherit"}}>{sub.missed}</dd>
          <dt>LTV</dt><dd className="rec-mono">{fmtBRL(sub.ltv)} {ltvMonths > 0 && <small className="rec-fresh">· {ltvMonths}× ticket</small>}</dd>
          <dt>Contato</dt><dd>{sub.contact?.nome} · <span className="rec-mono">{sub.contact?.fone}</span></dd>
          <dt>OS recente</dt><dd>{sub.os || "—"}</dd>
        </dl>
      </div>

      {sub.notes && (
        <div className="rec-detail-note">
          <span className="rec-note-l">Nota pinada</span>
          <p>{linkifyRefs(sub.notes)}</p>
        </div>
      )}

      {/* Bloco Documento Fiscal */}
      <FiscalBlock sub={sub} plan={plan}/>

      <div className="rec-detail-actions">
        {sub.status === "retentando" && (
          <>
            <button className="rec-btn rec-btn--primary"><I.retry size={12}/> Retentar agora <kbd>R</kbd></button>
            <button className="rec-btn"><I.pause size={12}/> Pausar <kbd>P</kbd></button>
            <button className="rec-btn rec-btn--guide" onClick={() => setTrouble(suggestTrouble)}>
              <I.keyboard size={12}/> Diagnosticar
            </button>
          </>
        )}
        {sub.status === "falhou" && (
          <>
            <button className="rec-btn rec-btn--danger"><I.bell size={12}/> Enviar dunning <kbd>D</kbd></button>
            <button className="rec-btn"><I.retry size={12}/> Tentar novamente <kbd>R</kbd></button>
            <button className="rec-btn"><I.pause size={12}/> Suspender <kbd>P</kbd></button>
            <button className="rec-btn rec-btn--guide" onClick={() => setTrouble(suggestTrouble)}>
              <I.keyboard size={12}/> Diagnosticar
            </button>
          </>
        )}
        {sub.status === "em_dia" && (
          <>
            <button className="rec-btn"><I.edit size={12}/> Editar plano <kbd>E</kbd></button>
            <button className="rec-btn"><I.pause size={12}/> Pausar <kbd>P</kbd></button>
          </>
        )}
        {sub.status === "pausada" && (
          <button className="rec-btn rec-btn--primary"><I.play size={12}/> Reativar <kbd>P</kbd></button>
        )}
        {sub.status === "cancelada" && (
          <div className="rec-detail-churn">
            <span className="rec-note-l">Motivo do cancelamento</span>
            <b>{sub.churn || "—"}</b>
            <small>em {sub.cancelAt ? new Date(sub.cancelAt).toLocaleDateString("pt-BR") : "—"}</small>
          </div>
        )}
      </div>

      <JanaPanel sub={sub} plan={plan}/>

      <NotesTimeline subId={sub.id}/>

      <div className="rec-detail-block">
        <h4>Histórico de pagamentos</h4>
        <div className="rec-hist-grid">
          {Array.from({ length: 12 }, (_, i) => {
            const isPaid = i < (sub.paid - sub.missed);
            const isFailed = sub.missed > 0 && i < sub.missed;
            const cls = isFailed ? "bad" : isPaid ? "ok" : "future";
            return <span key={i} className={"rec-hist-cell rec-hist-cell--" + cls} title={`m-${i}`}/>;
          })}
        </div>
        <div className="rec-hist-legend">
          <span><i className="rec-dot--ok"/>pago ({sub.paid - sub.missed})</span>
          <span><i className="rec-dot--bad"/>falhou ({sub.missed})</span>
          <span><i className="rec-dot--future"/>futuro</span>
        </div>
      </div>

      {trouble && <TroubleshooterModal presetId={trouble} sub={sub} onClose={() => setTrouble(null)}/>}
    </div>
  );
}

/* ════════════════════════════════════════════════════════════════
 * SUB-TAB · PLANOS
 * ══════════════════════════════════════════════════════════════ */
function PlanosView({ plans, subs }) {
  const totalActive = subs.filter(s => s.status !== "cancelada").length;
  return (
    <div className="rec-plans-grid">
      {plans.map((p, idx) => {
        const active = subs.filter(s => s.plan === p.id && s.status !== "cancelada").length;
        const mrr = subs.filter(s => s.plan === p.id && s.status === "em_dia").reduce((a, s) => a + (s.nextValue || p.price), 0);
        const share = totalActive > 0 ? (active / totalActive) * 100 : 0;
        // hue distribuída pelos 5 planos (295 / 250 / 60 / 145 / 200)
        const hues = [295, 250, 60, 145, 200];
        const hue = hues[idx % hues.length];
        return (
          <div key={p.id} className="rec-plan-card" style={{ "--plan-hue": hue }}>
            <div className="rec-plan-h">
              <h4>{p.name}</h4>
              <span className="rec-plan-cycle">{p.cycle}</span>
            </div>
            <div className="rec-plan-price">
              <b>{fmtBRL(p.price)}</b>
              <small>/ {p.cycle === "mensal" ? "mês" : "trim"}</small>
            </div>
            <p className="rec-plan-items">{p.items}</p>
            <div className="rec-plan-stats">
              <div><small>Ativos</small><b>{active}</b></div>
              <div><small>MRR</small><b className="rec-mono">{fmtBRLshort(mrr)}</b></div>
              <div><small>Share</small><b>{share.toFixed(0)}%</b></div>
            </div>
            <div className="rec-plan-share">
              <div className="rec-plan-share-track">
                <div className="rec-plan-share-fill" style={{ width: share + "%" }}/>
              </div>
              <small className="rec-plan-share-l">{active} de {totalActive} assinantes</small>
            </div>
            <div className="rec-plan-actions">
              <button className="rec-btn"><I.edit size={12}/>Editar</button>
              <button className="rec-btn"><I.archive size={12}/>Arquivar</button>
            </div>
          </div>
        );
      })}
      <div className="rec-plan-card rec-plan-card--new">
        <I.plus size={20}/>
        <b>Novo plano</b>
        <small>Definir ciclo, valor, itens</small>
      </div>
    </div>
  );
}

/* ════════════════════════════════════════════════════════════════
 * SUB-TAB · FATURAS
 * ══════════════════════════════════════════════════════════════ */
function FaturasView({ subs, upcoming, plans }) {
  const total = upcoming.reduce((a, u) => a + u.total, 0);
  return (
    <div className="rec-faturas">
      <div className="rec-faturas-h">
        <h3>Próximas cobranças · junho 2026</h3>
        <span className="rec-faturas-sub">{upcoming.length} dias · {upcoming.reduce((a,u) => a + u.subs.length, 0)} cobranças</span>
        <span className="rec-mono rec-faturas-total">{fmtBRL(total)}</span>
      </div>
      <div className="rec-fat-body">
        {upcoming.map(u => {
          const dayDate = new Date(2026, 5, u.day);
          const dow = ["dom","seg","ter","qua","qui","sex","sáb"][dayDate.getDay()];
          return (
            <section key={u.day} className="rec-fat-day">
              <header className="rec-fat-day-h">
                <div className="rec-fat-day-date">
                  <b>{String(u.day).padStart(2,"0")}</b>
                  <small>{dow}</small>
                </div>
                <span className="rec-fat-day-count">{u.subs.length} {u.subs.length === 1 ? "cobrança" : "cobranças"}</span>
                <span className="rec-mono rec-fat-day-total">{fmtBRL(u.total)}</span>
              </header>
              <ul className="rec-fat-rows">
                {u.subs.map(sid => {
                  const s = subs.find(x => x.id === sid);
                  const p = s ? plans.find(pp => pp.id === s.plan) : null;
                  if (!s) return null;
                  return (
                    <li key={sid} className="rec-fat-row">
                      <Avatar name={s.client} size={26}/>
                      <div className="rec-fat-row-main">
                        <b>{s.client}</b>
                        <small>{p?.name}</small>
                      </div>
                      <MethodIcon method={s.method} size={12}/>
                      <RecStatusBadge status={s.status} retry={s.retry} retryMax={s.retryMax}/>
                      <span className="rec-mono rec-fat-row-val">{fmtBRL(s.nextValue || p?.price || 0)}</span>
                    </li>
                  );
                })}
              </ul>
            </section>
          );
        })}
      </div>
    </div>
  );
}

/* ════════════════════════════════════════════════════════════════
 * SUB-TAB · CONFIG (placeholder R#1)
 * ══════════════════════════════════════════════════════════════ */
function ConfigView() {
  return (
    <div className="rec-config">
      <div className="rec-config-card">
        <h4>Política de retentativa</h4>
        <p className="rec-config-sub">Quando uma cobrança falha, a regra automática:</p>
        <ul>
          <li>Tentativa 1: <b>+2 dias</b> após vencimento</li>
          <li>Tentativa 2: <b>+5 dias</b></li>
          <li>Tentativa 3 (última): <b>+7 dias</b> · após falhar → status "falhou", requer ação manual</li>
        </ul>
        <button className="rec-btn"><I.edit size={12}/>Editar regra</button>
      </div>

      <div className="rec-config-card">
        <h4>Notificações</h4>
        <p className="rec-config-sub">Quem recebe alerta quando uma cobrança falha:</p>
        <ul>
          <li><b>Eliana</b> · WhatsApp + e-mail · <small>imediato</small></li>
          <li><b>Wagner</b> · e-mail · <small>resumo diário</small></li>
        </ul>
        <button className="rec-btn"><I.user size={12}/>Adicionar destinatário</button>
      </div>

      <div className="rec-config-card">
        <h4>Integração de gateway</h4>
        <p className="rec-config-sub">Banco/processador configurado por método:</p>
        <ul>
          <li><b>Boleto</b>: Inter (PJ · 0058) <span className="rec-tag-ok">conectado</span></li>
          <li><b>Pix</b>: Inter (PJ · 0058) <span className="rec-tag-ok">conectado</span></li>
          <li><b>Cartão</b>: Pagar.me <span className="rec-tag-ok">conectado</span></li>
        </ul>
        <button className="rec-btn"><I.edit size={12}/>Reconfigurar</button>
      </div>

      <div className="rec-config-card">
        <h4>Integração fiscal · NFe / NFS-e</h4>
        <p className="rec-config-sub">Emissão automática após cobrança paga:</p>
        <ul>
          <li>
            <b>NFe</b> (produtos) ·
            <span className="rec-fiscal-pill rec-fiscal-pill--nfe" style={{marginLeft: 6}}>SEFAZ-SP</span>
            <span className="rec-tag-ok">autorizada</span>
          </li>
          <li>
            <b>NFS-e</b> (serviços) ·
            <span className="rec-fiscal-pill rec-fiscal-pill--nfse" style={{marginLeft: 6}}>Prefeitura Campinas</span>
            <span className="rec-tag-ok">autorizada</span>
          </li>
          <li style={{borderTop: "1px solid var(--border)", paddingTop: 10, marginTop: 6}}>
            <small style={{display: "block", color: "var(--text-mute)", marginBottom: 4, fontSize: 10.5}}>
              Envio padrão da nota ao cliente:
            </small>
            <span className="rec-fiscal-pill rec-fiscal-pill--mail">E-mail</span>{" "}
            <span className="rec-fiscal-pill rec-fiscal-pill--wa">WhatsApp</span>
            <small style={{marginLeft: 8, color: "var(--text-mute)"}}>configurável por assinatura</small>
          </li>
        </ul>
        <button className="rec-btn"><I.edit size={12}/>Editar fluxo fiscal</button>
      </div>

      <div className="rec-config-card rec-config-card--later">
        <h4>Atalhos avançados</h4>
        <ul className="rec-config-later">
          <li><b>R#2 Jana:</b> ✓ resumir, perguntar, sugerir ação · empty-state IA no ⌘K</li>
          <li><b>R#3 Curadoria/Guia:</b> ✓ timeline · troubleshooters · cross-link · NFe/NFSe emissão</li>
          <li><b>R#4 Saída:</b> ✓ favoritos · apresentação · imprimir extrato · tour · envio NF via WhatsApp</li>
        </ul>
      </div>
    </div>
  );
}

/* ════════════════════════════════════════════════════════════════
 * MAIN — RecurringPage
 * ══════════════════════════════════════════════════════════════ */
function RecurringPage({ tab = "assinaturas", onTab }) {
  const { SUBS, PLANS, UPCOMING, KPIS } = window.REC_DATA;
  const [showCmd, setShowCmd] = useState(false);
  const [showCheat, setShowCheat] = useState(false);
  const [showPresent, setShowPresent] = useState(false);
  const [tourStep, setTourStep] = useState(null); // null = closed; 0..3 = open

  // Mini sparkline para o card MRR hero — 6 meses de crescimento estilizado
  const mrrSpark = useMemo(() => {
    // 6 pontos: dez/jan/fev/mar/abr/mai (em ‰ relativo ao máx)
    const pts = [0.55, 0.50, 0.62, 0.70, 0.82, 1.00]; // crescimento
    const W = 80, H = 24, PAD = 2;
    const xs = pts.map((_, i) => PAD + (i * (W - PAD * 2)) / (pts.length - 1));
    const ys = pts.map(v => PAD + (1 - v) * (H - PAD * 2));
    const line = xs.map((x, i) => (i === 0 ? "M" : "L") + x.toFixed(1) + " " + ys[i].toFixed(1)).join(" ");
    const area = line + ` L${xs[xs.length-1].toFixed(1)} ${H} L${xs[0].toFixed(1)} ${H} Z`;
    return { line, area };
  }, []);

  // ⌘K + ? globais + Shift+P (apresentar) + Shift+E (export PDF)
  useEffect(() => {
    const onKey = (e) => {
      const mod = e.metaKey || e.ctrlKey;
      const tgt = e.target;
      const inField = tgt.tagName === "INPUT" || tgt.tagName === "TEXTAREA" || tgt.isContentEditable;

      if (mod && e.key.toLowerCase() === "k") { e.preventDefault(); setShowCmd(s => !s); return; }
      if (!inField && e.key === "?") { e.preventDefault(); setShowCheat(s => !s); return; }
      if (!inField && e.shiftKey && (e.key === "P" || e.key === "p")) { e.preventDefault(); setShowPresent(s => !s); return; }
      if (!inField && e.shiftKey && (e.key === "E" || e.key === "e")) {
        e.preventDefault();
        const activeId = localStorage.getItem("oimpresso.rec.active");
        const sub = SUBS.find(s => s.id === activeId) || SUBS[0];
        if (sub) printSubDetail(sub);
        return;
      }
      if (!inField && ["1","2","3","4"].includes(e.key) && !mod) {
        e.preventDefault();
        const map = { "1":"assinaturas", "2":"planos", "3":"faturas", "4":"config" };
        onTab?.(map[e.key]);
      }
      if (e.key === "Escape") { setShowCmd(false); setShowCheat(false); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onTab, SUBS]);

  const handlePick = (r) => {
    setShowCmd(false);
    if (r.kind === "sub") {
      onTab?.("assinaturas");
      try { localStorage.setItem("oimpresso.rec.active", r.id); } catch {}
      // força re-render setando state (hack: pequeno timeout pra esperar tab change)
      setTimeout(() => window.dispatchEvent(new Event("storage")), 0);
    } else if (r.kind === "plan") {
      onTab?.("planos");
    }
  };

  return (
    <div className="rec-page">
      <header className="rec-page-h">
        <div className="rec-page-h-l">
          <div className="rec-page-h-title">
            <h2>Cobrança recorrente</h2>
            <small className="rec-crumb">{KPIS.activeCount} ativas · MRR {fmtBRL(KPIS.mrr)} · churn {KPIS.churnRate}%</small>
          </div>
        </div>
        <nav className="rec-subnav" aria-label="Sub-rotas">
          {[
            { key: "assinaturas", label: "Assinaturas", hint: "1" },
            { key: "planos",      label: "Planos",      hint: "2" },
            { key: "faturas",     label: "Faturas",     hint: "3" },
            { key: "config",      label: "Configurações", hint: "4" },
          ].map(t => (
            <button key={t.key}
                    className={"rec-subnav-link" + (tab === t.key ? " active" : "")}
                    onClick={() => onTab?.(t.key)}>
              {t.label}
              <kbd className="rec-subnav-kbd">{t.hint}</kbd>
            </button>
          ))}
        </nav>
        <div className="rec-page-h-r">
          <button className="rec-btn rec-btn--primary"><I.plus size={12}/>Nova assinatura <kbd>N</kbd></button>
          <button className="rec-btn rec-btn--icon" title="Novo plano"><I.plus size={12}/></button>
          <button className="rec-btn rec-btn--icon" title="Apresentar (⇧P)" onClick={() => setShowPresent(true)}><I.play size={12}/></button>
          <button className="rec-btn rec-btn--icon" title="Tour das features" onClick={() => setTourStep(0)}><I.bell size={12}/></button>
          <button className="rec-btn rec-btn--icon" title="Logs"><I.file size={12}/></button>
          <span className="rec-h-sep"/>
          <button className="rec-btn rec-btn--icon" title="Buscar (⌘K)" onClick={() => setShowCmd(true)}><I.cmd size={12}/></button>
          <button className="rec-btn rec-btn--icon" title="Atalhos (?)" onClick={() => setShowCheat(true)}><I.keyboard size={12}/></button>
        </div>
      </header>

      <KpiStrip kpis={KPIS} mrrSpark={mrrSpark} onClickKpi={(k) => {
        if (k === "failed") onTab?.("assinaturas");
        if (k === "next")   onTab?.("faturas");
      }}/>

      <main className="rec-page-body">
        {tab === "assinaturas" && <AssinaturasView subs={SUBS} plans={PLANS} kpis={KPIS} onOpenCmd={() => setShowCmd(true)} onOpenCheat={() => setShowCheat(true)}/>}
        {tab === "planos"      && <PlanosView plans={PLANS} subs={SUBS}/>}
        {tab === "faturas"     && <FaturasView subs={SUBS} upcoming={UPCOMING} plans={PLANS}/>}
        {tab === "config"      && <ConfigView/>}
      </main>

      {showCmd   && <CmdPalette onClose={() => setShowCmd(false)} onPick={handlePick} subs={SUBS} plans={PLANS}/>}
      {showCheat && <CheatSheet onClose={() => setShowCheat(false)}/>}
      {showPresent && <PresentationMode onClose={() => setShowPresent(false)} kpis={KPIS} subs={SUBS} plans={PLANS}/>}
      {tourStep != null && <OnboardingTour onClose={() => setTourStep(null)} step={tourStep} setStep={setTourStep}/>}
    </div>
  );
}

window.RecurringPage = RecurringPage;
})();
