// inbox-v2-extras.jsx — Refino #1 da Caixa Unificada (Método KB-9.75)
// Pílula SLA · Command palette ⌘K · Cheat-sheet (?) · Mobile 3-tabs · Atalhos J/K
(() => {
const { useState, useEffect, useMemo, useRef } = React;

// ─────────────────────────────────────────────────────────────────
// PÍLULA DE SLA — fresco / atrasando / estourado / expirado
// ─────────────────────────────────────────────────────────────────
// Recebe conversa e fila. Calcula minutos desde último contato do cliente
// e classifica contra o SLA da fila. Render consistente em qualquer lugar.

function parseSLA(sla) {
  if (!sla) return 60;
  if (sla.includes("min")) return parseInt(sla, 10);
  if (sla.includes("h"))   return parseInt(sla, 10) * 60;
  return 60;
}

function computeSLA(conv, queue) {
  if (!conv || !queue || conv.lastFrom === "me") return { level: "ok", label: "respondido", mins: 0 };
  // mins desde última msg do cliente (mock baseado em lastTouch text)
  const last = conv.ctx?.lastTouch || "";
  let mins = 30;
  if (/preview/.test(last))   return { level: "na", label: "preview", mins: 0 };
  if (/min/.test(last))       mins = parseInt(last, 10) || 5;
  else if (/ontem/.test(last)) mins = 18 * 60;
  else if (/^\d+/.test(last)) {
    const m = last.match(/(\d+):(\d+)/);
    if (m) {
      const now = new Date();
      const h = parseInt(m[1], 10), mm = parseInt(m[2], 10);
      mins = Math.max(0, (now.getHours() * 60 + now.getMinutes()) - (h * 60 + mm));
    }
  }
  const slaMins = parseSLA(queue.sla);
  const ratio = mins / slaMins;
  if (ratio < 0.5) return { level: "fresh",   label: "no SLA",    mins, slaMins };
  if (ratio < 1)   return { level: "aging",   label: "atrasando", mins, slaMins };
  if (ratio < 2)   return { level: "late",    label: "estourado", mins, slaMins };
  return                  { level: "expired", label: "expirado",  mins, slaMins };
}

function formatMins(m) {
  if (m < 60) return m + "min";
  const h = Math.floor(m / 60);
  if (h < 24) return h + "h";
  return Math.floor(h / 24) + "d";
}

function SLAPill({ conv, queue, size = "sm" }) {
  const s = computeSLA(conv, queue);
  if (s.level === "na" || s.level === "ok") return null;
  return (
    <span className={"om-sla-pill " + s.level + " " + size}>
      <span className="om-sla-dot"/>
      {s.label}
      {s.mins > 0 && <span className="om-sla-mono mono">{formatMins(s.mins)}</span>}
    </span>
  );
}

window.computeSLA = computeSLA;
window.SLAPill = SLAPill;

// ─────────────────────────────────────────────────────────────────
// COMMAND PALETTE ⌘K — cross-resource
// Busca conversas + contatos + OS + artigos KB
// ─────────────────────────────────────────────────────────────────
function InboxPalette({ convs, accounts, channels, queues, operators, kbArticles, onPickConv, onAskAI, onClose, inputRef }) {
  const [q, setQ] = useState("");
  const [idx, setIdx] = useState(0);

  // Fontes de resultado — cada uma com kind, label
  const results = useMemo(() => {
    if (!q.trim()) {
      // Default: 5 conversas mais recentes + 3 KB pinados + ações
      const top = [
        ...convs.slice(0, 5).map(c => ({ kind: "conv", id: c.id, conv: c })),
        ...(kbArticles || []).filter(a => a.pinned).slice(0, 3).map(a => ({ kind: "kb", id: a.id, art: a })),
      ];
      return top;
    }
    const qq = q.toLowerCase();
    const out = [];
    // Conversas
    for (const c of convs) {
      const blob = (c.name + " " + (c.company||"") + " " + (c.preview||"") + " " + (c.handle||"")).toLowerCase();
      if (qq.split(/\s+/).every(t => blob.includes(t))) {
        out.push({ kind: "conv", id: c.id, conv: c });
      }
      if (out.length >= 10) break;
    }
    // KB
    for (const a of (kbArticles || [])) {
      const blob = (a.title + " " + a.excerpt + " " + (a.tags||[]).join(" ")).toLowerCase();
      if (qq.split(/\s+/).every(t => blob.includes(t))) {
        out.push({ kind: "kb", id: a.id, art: a });
      }
      if (out.length >= 16) break;
    }
    // Operadores (atalho atribuir)
    for (const op of Object.values(operators || {})) {
      if (op.name.toLowerCase().includes(qq) || op.init.toLowerCase().includes(qq)) {
        out.push({ kind: "op", id: op.id, op });
      }
    }
    return out.slice(0, 16);
  }, [q, convs, kbArticles, operators]);

  useEffect(() => { setIdx(0); }, [q]);

  const onKey = (e) => {
    if (e.key === "ArrowDown") { e.preventDefault(); setIdx(i => Math.min(results.length - 1, i + 1)); }
    if (e.key === "ArrowUp")   { e.preventDefault(); setIdx(i => Math.max(0, i - 1)); }
    if (e.key === "Enter") {
      e.preventDefault();
      const r = results[idx];
      if (!r) return;
      if (r.kind === "conv") onPickConv(r.id);
      else if (r.kind === "kb") {
        // muda rota pro KB
        try { localStorage.setItem("oimpresso.route", "kb"); window.location.reload(); } catch(e){}
      }
      onClose();
    }
  };

  return (
    <React.Fragment>
      <div className="om-palette-back" onClick={onClose}/>
      <div className="om-palette" role="dialog" aria-label="Busca rápida">
        <div className="om-palette-input">
          <span className="om-palette-hint">Buscar</span>
          <input
            ref={inputRef}
            autoFocus
            value={q}
            onChange={e => setQ(e.target.value)}
            onKeyDown={onKey}
            placeholder="Conversas, contatos, OS, artigos KB…"/>
          <span className="om-kbd">esc</span>
        </div>
        <div className="om-palette-list">
          {results.length === 0 ? (
            <div className="om-palette-empty-ai">
              <p>Nenhuma conversa, contato ou artigo bate com <b>"{q}"</b>.</p>
              {onAskAI && (
                <button className="om-palette-ask-btn" onClick={() => { onClose(); onAskAI(q); }}>
                  <span style={{fontWeight:700}}>✦</span> Perguntar à IA: "{q}"
                </button>
              )}
              <p style={{fontSize:11, color:"var(--text-mute)"}}>A IA lê thread atual, histórico do cliente e KB.</p>
            </div>
          ) : results.map((r, i) => {
            const active = i === idx;
            if (r.kind === "conv") {
              const c = r.conv;
              const acc = accounts[c.account];
              const ch = acc && channels[acc.channel];
              return (
                <button key={"c"+c.id}
                        className={"om-palette-row " + (active ? "active" : "")}
                        onMouseEnter={() => setIdx(i)}
                        onClick={() => { onPickConv(c.id); onClose(); }}>
                  <span className="om-palette-icon" style={{ background: ch ? `oklch(0.62 0.14 ${ch.hue})` : "var(--text-mute)" }}>{ch?.glyph || "?"}</span>
                  <div className="om-palette-r">
                    <b>{c.name}</b>
                    <span>{c.company} · {c.preview ? c.preview.slice(0, 50) : ""}</span>
                  </div>
                  <span className="om-palette-kind">conv</span>
                </button>
              );
            }
            if (r.kind === "kb") {
              const a = r.art;
              return (
                <button key={"k"+a.id}
                        className={"om-palette-row " + (active ? "active" : "")}
                        onMouseEnter={() => setIdx(i)}
                        onClick={() => { try { localStorage.setItem("oimpresso.route", "kb"); window.location.reload(); } catch(e){}; onClose(); }}>
                  <span className="om-palette-icon" style={{ background: "oklch(0.55 0.13 240)" }}>≡</span>
                  <div className="om-palette-r">
                    <b>{a.title}</b>
                    <span>KB · {a.author} · {a.readTime} min</span>
                  </div>
                  <span className="om-palette-kind">artigo</span>
                </button>
              );
            }
            if (r.kind === "op") {
              const op = r.op;
              return (
                <button key={"o"+op.id}
                        className={"om-palette-row " + (active ? "active" : "")}
                        onMouseEnter={() => setIdx(i)}>
                  <span className="om-palette-icon" style={{ background: `oklch(0.55 0.12 ${op.avc})` }}>{op.init}</span>
                  <div className="om-palette-r">
                    <b>{op.name}</b>
                    <span>Atribuir conversa atual para este operador</span>
                  </div>
                  <span className="om-palette-kind">→ atribuir</span>
                </button>
              );
            }
            return null;
          })}
        </div>
        <div className="om-palette-foot">
          <span><span className="om-kbd">↑↓</span> navegar</span>
          <span><span className="om-kbd">↵</span> abrir</span>
          <span><span className="om-kbd">esc</span> fechar</span>
          <span style={{marginLeft:"auto", color:"var(--text-mute)"}}>cross-module · KB + conversas + operadores</span>
        </div>
      </div>
    </React.Fragment>
  );
}
window.InboxPalette = InboxPalette;

// ─────────────────────────────────────────────────────────────────
// CHEAT-SHEET (?) — overlay com todos os atalhos
// ─────────────────────────────────────────────────────────────────
function InboxCheatSheet({ onClose }) {
  const groups = [
    { label: "Global", items: [
      { keys: ["⌘K"], desc: "Busca cross-module" },
      { keys: ["?"], desc: "Esta ajuda" },
      { keys: ["Esc"], desc: "Fechar overlay" },
    ]},
    { label: "Conversas", items: [
      { keys: ["J", "↓"], desc: "Próxima conversa" },
      { keys: ["K", "↑"], desc: "Conversa anterior" },
      { keys: ["B"], desc: "Favoritar (pessoal)" },
      { keys: ["R"], desc: "Foco no input de resposta" },
      { keys: ["E"], desc: "Resolver conversa" },
    ]},
    { label: "Composer", items: [
      { keys: ["⌘⇧N"], desc: "Alternar Resp / Nota" },
      { keys: ["⌘T"], desc: "Templates do canal" },
      { keys: ["/"], desc: "Macros (slash)" },
      { keys: ["↵"], desc: "Enviar" },
    ]},
  ];

  return (
    <React.Fragment>
      <div className="om-palette-back" onClick={onClose}/>
      <div className="om-cheat" role="dialog">
        <header className="om-cheat-h">
          <div>
            <small>Atalhos · Caixa unificada</small>
            <h3>Teclado</h3>
          </div>
          <button className="om-x" onClick={onClose}>✕</button>
        </header>
        <div className="om-cheat-body">
          {groups.map((g, i) => (
            <section key={i} className="om-cheat-group">
              <small>{g.label}</small>
              <dl>
                {g.items.map((it, j) => (
                  <React.Fragment key={j}>
                    <dt>{it.keys.map(k => <span key={k} className="om-kbd">{k}</span>)}</dt>
                    <dd>{it.desc}</dd>
                  </React.Fragment>
                ))}
              </dl>
            </section>
          ))}
        </div>
        <footer className="om-cheat-foot">
          <small>Tecle <span className="om-kbd">?</span> a qualquer momento.</small>
        </footer>
      </div>
    </React.Fragment>
  );
}
window.InboxCheatSheet = InboxCheatSheet;

// ─────────────────────────────────────────────────────────────────
// MOBILE TABS — abaixo de 1100px
// ─────────────────────────────────────────────────────────────────
function InboxMobileTabs({ view, setView, counts, hasSelected }) {
  return (
    <div className="om-mobile-tabs" role="tablist" aria-label="Navegação mobile">
      <button className={"om-mobile-tab" + (view === "list" ? " active" : "")}
              onClick={() => setView("list")} role="tab" aria-selected={view === "list"}>
        Conversas <span className="om-mtab-n mono">{counts.list}</span>
      </button>
      <button className={"om-mobile-tab" + (view === "thread" ? " active" : "")}
              onClick={() => setView("thread")} role="tab" aria-selected={view === "thread"}
              disabled={!hasSelected}>
        Thread
      </button>
    </div>
  );
}
window.InboxMobileTabs = InboxMobileTabs;

// ─────────────────────────────────────────────────────────────────
// HOOK: ATALHOS DE TECLADO
// ─────────────────────────────────────────────────────────────────
function useInboxKeyboard({ onPalette, onCheat, onNext, onPrev, onResolve, onToggleFav, onFocusInput, onEsc, deps = [] }) {
  useEffect(() => {
    const onKey = (e) => {
      const tag = (document.activeElement || {}).tagName;
      const inField = tag === "INPUT" || tag === "TEXTAREA";
      const mod = e.metaKey || e.ctrlKey;
      if (mod && e.key.toLowerCase() === "k") { e.preventDefault(); onPalette && onPalette(); return; }
      if (e.key === "Escape") { onEsc && onEsc(); return; }
      if (inField) return;
      if (e.key === "?")  { e.preventDefault(); onCheat && onCheat(); }
      else if (e.key === "j" || e.key === "ArrowDown") { e.preventDefault(); onNext && onNext(); }
      else if (e.key === "k" || e.key === "ArrowUp")   { e.preventDefault(); onPrev && onPrev(); }
      else if (e.key === "b") { e.preventDefault(); onToggleFav && onToggleFav(); }
      else if (e.key === "r") { e.preventDefault(); onFocusInput && onFocusInput(); }
      else if (e.key === "e") { e.preventDefault(); onResolve && onResolve(); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
    // eslint-disable-next-line
  }, deps);
}
window.useInboxKeyboard = useInboxKeyboard;

// ─────────────────────────────────────────────────────────────────
// FAVORITOS (reuso do hook do KB)
// ─────────────────────────────────────────────────────────────────
function useInboxFavs() {
  const [favs, setFavs] = useState(() => {
    try { return JSON.parse(localStorage.getItem("oimpresso.inbox.favs") || "[]"); }
    catch (e) { return []; }
  });
  useEffect(() => {
    try { localStorage.setItem("oimpresso.inbox.favs", JSON.stringify(favs)); } catch (e) {}
  }, [favs]);
  const isFav = (id) => favs.includes(id);
  const toggleFav = (id) => setFavs(f => f.includes(id) ? f.filter(x => x !== id) : [id, ...f]);
  return { favs, isFav, toggleFav };
}
window.useInboxFavs = useInboxFavs;

})();
