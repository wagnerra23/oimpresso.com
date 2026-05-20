// fiscal-page.jsx — Módulo Fiscal · Refino #1 (KB-9.75 aplicado)
// ─────────────────────────────────────────────────────────────────
// FxShell (sub-nav + cheat-sheet + ⌘K + drawer slide-in) envelopa
// as 7 páginas. SEM tri-pane — adaptação pro fiscal:
//   - Sub-nav horizontal (substitui o topnav global)
//   - Drawer slide-in à direita (sob demanda, não persistente)
//   - Filtros como chips no topo (não como coluna fixa)
//
// Refino #1 entregue:
//   ✓ ⌘K palette com busca em notas + ações rápidas
//   ✓ J/K + setas pra navegar listas
//   ✓ Atalhos: E emitir · R reenviar · X cancelar · M manifestar · ? help
//   ✓ Pílulas temporais (cancelar/CC-e/manifestar com prazo)
//   ✓ Cross-link (V-* · OS-* · CNPJ vira link)
//   ✓ Cheat-sheet sticky no rodapé
//   ✓ Slide-in drawer com mapa SEFAZ guiado (substitui IA real do R#2)
//   ✓ Mini-sparklines nos KPIs
//   ✓ Pulse animation em rejeitadas críticas
//
// Persona: Eliana (contadora) + Wagner (operador fiscal)

(() => {
const { useState, useMemo, useEffect, useRef, useCallback } = React;
const D  = window.FISCAL_DATA;
const II = window.I || {};

/* ╔════════════════════════════════════════════════════════════════╗
   ║ 1) HELPERS                                                     ║
   ╚════════════════════════════════════════════════════════════════╝ */
const brl       = (n) => (n || 0).toLocaleString("pt-BR", { style:"currency", currency:"BRL" });
const truncKey  = (k) => k ? `${k.slice(0,4)}…${k.slice(-6)}` : "—";
const formatDoc = (cnpj, cpf) => cnpj || cpf || "—";

const NOW_TS = D ? new Date(D.MOCK_NOW).getTime() : Date.now();
const hoursBetween = (futureIso) => (new Date(futureIso).getTime() - NOW_TS) / 36e5;
const daysBetween  = (futureIso) => hoursBetween(futureIso) / 24;

/** Calcula janela de cancelamento NF-e (24h da emissão). Retorna {h, m, urgency} ou null. */
function prazoCancel(nota) {
  if (!nota?.emittedAtIso || ![55, 65].includes(nota.modelo) || nota.status !== 100) return null;
  const emitTs = new Date(nota.emittedAtIso).getTime();
  const deadline = emitTs + 24 * 36e5;
  const msLeft = deadline - NOW_TS;
  if (msLeft <= 0) return null;
  const h = Math.floor(msLeft / 36e5);
  const m = Math.floor((msLeft % 36e5) / 6e4);
  const urgency = h < 6 ? "crit" : h < 12 ? "warn" : "ok";
  return { h, m, urgency };
}
/** Calcula janela de CC-e (30d da emissão). */
function prazoCCe(nota) {
  if (!nota?.emittedAtIso || nota.status !== 100) return null;
  const emitTs = new Date(nota.emittedAtIso).getTime();
  const deadline = emitTs + 30 * 24 * 36e5;
  const dLeft = Math.floor((deadline - NOW_TS) / (24 * 36e5));
  if (dLeft <= 0) return null;
  const urgency = dLeft < 3 ? "crit" : dLeft < 7 ? "warn" : "ok";
  return { d: dLeft, urgency };
}

const Ic = ({ name, size = 14 }) => {
  const C = II[name];
  return C ? <C size={size}/> : <span style={{ width: size, height: size, display: "inline-block" }}/>;
};

/* ╔════════════════════════════════════════════════════════════════╗
   ║ 2) ÁTOMOS · pílulas, linkify, sparkline                        ║
   ╚════════════════════════════════════════════════════════════════╝ */

function SefazPill({ code, compact }) {
  const meta = D.SEFAZ_CODES[code] || { tone:"warn", label:"Status "+code, hint:"" };
  return (
    <span className={`fx-sefaz ${meta.tone}${compact ? " compact" : ""}`} title={meta.hint}>
      <span className="code">{code}</span>
      {!compact && <span className="lbl">{meta.label}</span>}
    </span>
  );
}

function TimePill({ kind, hours, days, urgency = "ok", compact }) {
  const ICONS  = { cancel: "refresh", cce: "doc", manifest: "audit" };
  const LABELS = { cancel: "cancelar em", cce: "CC-e em", manifest: "manifestar em" };
  const value =
    hours != null
      ? hours >= 1
        ? `${Math.floor(hours)}h${hours%1 ? Math.round((hours%1)*60).toString().padStart(2,"0") : ""}`
        : `${Math.round(hours*60)}min`
      : `${days}d`;
  return (
    <span className={`fx-timepill u-${urgency}${compact ? " compact" : ""}`}>
      <Ic name={ICONS[kind]} size={10}/>
      <span className="lbl">{LABELS[kind]} <b>{value}</b></span>
    </span>
  );
}

/** Cross-link: detecta V-NNNN, OS-NNNN, OS #NNNN, CNPJ XX.XXX… e devolve nó com <a> */
function Linkify({ children, onNavigate }) {
  if (typeof children !== "string") return <>{children}</>;
  const re = /(V-\d{3,5}|OS\s?#?\d{3,5}|\b\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}\b)/g;
  const parts = [];
  let last = 0;
  let m;
  while ((m = re.exec(children)) !== null) {
    if (m.index > last) parts.push(children.slice(last, m.index));
    const token = m[0];
    let to = null;
    if (/^V-/.test(token))            to = { module:"vendas", label:"abrir venda" };
    else if (/^OS/i.test(token))      to = { module:"os",     label:"abrir OS"   };
    else                              to = { module:"clientes", label:"abrir cliente" };
    parts.push(
      <a key={m.index} className="fx-link" title={to.label}
         onClick={(e) => { e.preventDefault(); onNavigate?.(to.module); }}>
        {token}
      </a>
    );
    last = re.lastIndex;
  }
  if (last < children.length) parts.push(children.slice(last));
  return <>{parts}</>;
}

function MiniSparkline({ data, width = 76, height = 24, color = "currentColor", strokeWidth = 1.5, fill = true }) {
  if (!data || !data.length) return null;
  const max = Math.max(...data, 1);
  const min = Math.min(...data, 0);
  const range = max - min || 1;
  const pad = 2;
  const innerW = width - pad * 2;
  const innerH = height - pad * 2;
  const step = innerW / (data.length - 1);
  const pts = data.map((v, i) => [pad + i * step, pad + innerH - ((v - min) / range) * innerH]);
  const path = pts.map((p, i) => (i === 0 ? "M" : "L") + p[0].toFixed(1) + "," + p[1].toFixed(1)).join(" ");
  const area = path + ` L${pad + innerW},${pad + innerH} L${pad},${pad + innerH} Z`;
  return (
    <svg className="fx-spark" viewBox={`0 0 ${width} ${height}`} width={width} height={height} aria-hidden="true">
      {fill && <path d={area} fill={color} fillOpacity={0.12}/>}
      <path d={path} stroke={color} strokeWidth={strokeWidth} fill="none" strokeLinecap="round" strokeLinejoin="round"/>
      <circle cx={pts[pts.length-1][0]} cy={pts[pts.length-1][1]} r={2.2} fill={color}/>
    </svg>
  );
}

function EnvBadge({ tone = "ok", label }) {
  return <span className={`fx-env ${tone}`}>{label}</span>;
}

/* ╔════════════════════════════════════════════════════════════════╗
   ║ 3) NAVEGAÇÃO INTERNA · sub-nav + cheat-sheet                   ║
   ╚════════════════════════════════════════════════════════════════╝ */

const FX_PAGES = [
  { id:"fiscal",          label:"Cockpit",        icon:"chart",   short:"1" },
  { id:"nfe",             label:"NF-e · NFC-e",   icon:"receipt", short:"2" },
  { id:"nfse",            label:"NFS-e",          icon:"doc",     short:"3" },
  { id:"dfe",             label:"Manifesto DF-e", icon:"audit",   short:"4" },
  { id:"fiscal_eventos",  label:"Eventos",        icon:"refresh", short:"5" },
  { id:"fiscal_config",   label:"Certif. & Cfg.", icon:"shield",  short:"6" },
  { id:"sped",            label:"SPED & Livros",  icon:"archive", short:"7" },
];

function SubNav({ active, onNavigate, counts = {} }) {
  return (
    <nav className="fx-subnav" aria-label="Páginas do módulo Fiscal">
      {FX_PAGES.map(p => {
        const n = counts[p.id];
        return (
          <button key={p.id}
                  className={"fx-subnav-chip" + (active === p.id ? " active" : "")}
                  onClick={() => onNavigate?.(p.id)}
                  title={`Atalho: ${p.short}`}>
            <Ic name={p.icon} size={13}/>
            <span>{p.label}</span>
            {n != null && <span className="n">{n}</span>}
            <kbd>{p.short}</kbd>
          </button>
        );
      })}
    </nav>
  );
}

function CheatSheet({ items, onOpenHelp }) {
  return (
    <div className="fx-cheatsheet" role="region" aria-label="Atalhos de teclado">
      {items.map((it, i) => (
        <span key={i} className="fx-cs-item">
          {it.keys.map((k, j) => (
            <kbd key={j}>{k}</kbd>
          ))}
          <span>{it.label}</span>
        </span>
      ))}
      <button className="fx-cs-help" onClick={onOpenHelp} title="Ver todos os atalhos">
        <Ic name="search" size={11}/> Mais
      </button>
    </div>
  );
}

/* ╔════════════════════════════════════════════════════════════════╗
   ║ 4) COMMAND PALETTE ⌘K                                          ║
   ╚════════════════════════════════════════════════════════════════╝ */

const CMD_QUICK_ACTIONS = [
  { id:"q-rej",  label:"Ir pra rejeitadas (NF-e)",      hint:"abre filtro · 2 notas", route:"nfe",   filter:"rejeitadas", icon:"audit"   },
  { id:"q-dfe",  label:"DF-e aguardando manifestação",  hint:"5 notas · prazo legal 90d", route:"dfe",                  icon:"audit"   },
  { id:"q-cert", label:"Renovar certificado A1",         hint:"vence em 47 dias",        route:"fiscal_config",          icon:"shield"  },
  { id:"q-sped", label:"Exportar SPED · maio (parcial)", hint:"competência em curso",     route:"sped",                   icon:"archive" },
  { id:"q-emit", label:"Emitir nova NF-e",               hint:"abre drawer de emissão",   route:"nfe",   action:"emit",   icon:"plus"    },
];

function CmdKPalette({ open, onClose, onNavigate, onFocusNota }) {
  const [q, setQ] = useState("");
  const [cursor, setCursor] = useState(0);
  const inputRef = useRef(null);

  useEffect(() => {
    if (open) {
      setQ(""); setCursor(0);
      setTimeout(() => inputRef.current?.focus(), 30);
    }
  }, [open]);

  const results = useMemo(() => {
    const query = q.trim().toLowerCase();
    const notas = D.NOTAS_SAIDA.map(n => ({
      id: n.id, kind: "nfe",
      title: `${n.modelo === 65 ? "NFC-e" : "NFe"} ${n.num} · ${n.dest}`,
      sub: `${truncKey(n.key)} · ${formatDoc(n.cnpj, n.cpf)}`,
      status: n.status,
      raw: n,
    }));
    const nfse = D.NOTAS_NFSE.map(n => ({
      id: n.id, kind: "nfse",
      title: `NFS-e ${n.num} · ${n.tomador}`,
      sub: `${n.municipio} · cód. serviço ${n.codServ}`,
      raw: n,
    }));
    const dfe = D.DFE_PENDENTE.map(d => ({
      id: d.id, kind: "dfe",
      title: `DF-e · ${d.emitente}`,
      sub: `${d.desc} · ${d.ddays}d restantes`,
      raw: d,
    }));

    const filterText = (item) =>
      !query ||
      item.title.toLowerCase().includes(query) ||
      item.sub.toLowerCase().includes(query) ||
      (item.raw?.key || "").includes(query.replace(/\D/g, "")) ||
      (item.raw?.num || "").includes(query) ||
      (String(item.raw?.status || "").includes(query));

    const matchQuick = CMD_QUICK_ACTIONS.filter(a =>
      !query || a.label.toLowerCase().includes(query)
    );
    return {
      quick: matchQuick,
      notas: notas.filter(filterText).slice(0, 6),
      nfse:  nfse.filter(filterText).slice(0, 4),
      dfe:   dfe.filter(filterText).slice(0, 4),
    };
  }, [q]);

  // Lista linear pra navegação por seta/J/K
  const flat = useMemo(() => {
    const arr = [];
    results.quick.forEach(a => arr.push({ kind:"quick", item: a }));
    results.notas.forEach(n => arr.push({ kind:"nota", item: n }));
    results.nfse.forEach(n  => arr.push({ kind:"nfse", item: n }));
    results.dfe.forEach(d   => arr.push({ kind:"dfe",  item: d }));
    return arr;
  }, [results]);

  useEffect(() => { if (cursor >= flat.length) setCursor(Math.max(0, flat.length - 1)); }, [flat, cursor]);

  const trigger = useCallback((entry) => {
    if (!entry) return;
    if (entry.kind === "quick") {
      onNavigate?.(entry.item.route, { filter: entry.item.filter, action: entry.item.action });
    } else if (entry.kind === "nota") {
      onNavigate?.("nfe", { focus: entry.item.id });
      onFocusNota?.(entry.item.raw);
    } else if (entry.kind === "nfse") {
      onNavigate?.("nfse", { focus: entry.item.id });
    } else if (entry.kind === "dfe") {
      onNavigate?.("dfe", { focus: entry.item.id });
    }
    onClose?.();
  }, [onNavigate, onFocusNota, onClose]);

  if (!open) return null;

  const onKey = (e) => {
    if (e.key === "ArrowDown" || (e.key === "j" && e.ctrlKey === false && e.metaKey === false && e.target === inputRef.current && q === "" )) {
      e.preventDefault(); setCursor(c => Math.min(flat.length - 1, c + 1));
    } else if (e.key === "ArrowUp") {
      e.preventDefault(); setCursor(c => Math.max(0, c - 1));
    } else if (e.key === "Enter") {
      e.preventDefault(); trigger(flat[cursor]);
    } else if (e.key === "Escape") {
      e.preventDefault(); onClose?.();
    }
  };

  let cursorIdx = -1;

  return (
    <div className="fx-cmdk-bg" onClick={onClose}>
      <div className="fx-cmdk" onClick={(e) => e.stopPropagation()} onKeyDown={onKey}>
        <div className="fx-cmdk-search">
          <Ic name="search" size={15}/>
          <input
            ref={inputRef}
            placeholder="Buscar nota, chave (últimos 6), destinatário, código SEFAZ · ⏎ abre · ESC fecha"
            value={q}
            onChange={(e) => { setQ(e.target.value); setCursor(0); }}
          />
          <kbd>esc</kbd>
        </div>

        <div className="fx-cmdk-results">

          {results.quick.length > 0 && (
            <>
              <div className="fx-cmdk-grp">Ações rápidas</div>
              {results.quick.map(a => {
                cursorIdx++;
                const sel = cursorIdx === cursor;
                return (
                  <div key={a.id} className={"fx-cmdk-item" + (sel ? " sel" : "")}
                       onMouseEnter={() => setCursor(cursorIdx)}
                       onClick={() => trigger({ kind:"quick", item:a })}>
                    <span className="fx-cmdk-ic"><Ic name={a.icon} size={13}/></span>
                    <div className="fx-cmdk-body">
                      <b>{a.label}</b>
                      <small>{a.hint}</small>
                    </div>
                    <span className="fx-cmdk-tag">→</span>
                  </div>
                );
              })}
            </>
          )}

          {results.notas.length > 0 && (
            <>
              <div className="fx-cmdk-grp">Notas · {results.notas.length}</div>
              {results.notas.map(n => {
                cursorIdx++;
                const sel = cursorIdx === cursor;
                const sefazTone = D.SEFAZ_CODES[n.status]?.tone || "warn";
                return (
                  <div key={n.id} className={"fx-cmdk-item" + (sel ? " sel" : "")}
                       onMouseEnter={() => setCursor(cursorIdx)}
                       onClick={() => trigger({ kind:"nota", item:n })}>
                    <span className={`fx-cmdk-badge ${sefazTone}`}>{n.raw.modelo === 65 ? "NFCe" : "NFe"}</span>
                    <div className="fx-cmdk-body">
                      <b>{n.title}</b>
                      <small>{n.sub}</small>
                    </div>
                    <span className="fx-cmdk-tag mono">{n.raw.num}</span>
                  </div>
                );
              })}
            </>
          )}

          {results.nfse.length > 0 && (
            <>
              <div className="fx-cmdk-grp">NFS-e · {results.nfse.length}</div>
              {results.nfse.map(n => {
                cursorIdx++;
                const sel = cursorIdx === cursor;
                return (
                  <div key={n.id} className={"fx-cmdk-item" + (sel ? " sel" : "")}
                       onMouseEnter={() => setCursor(cursorIdx)}
                       onClick={() => trigger({ kind:"nfse", item:n })}>
                    <span className="fx-cmdk-badge">NFSe</span>
                    <div className="fx-cmdk-body"><b>{n.title}</b><small>{n.sub}</small></div>
                    <span className="fx-cmdk-tag mono">{n.raw.num}</span>
                  </div>
                );
              })}
            </>
          )}

          {results.dfe.length > 0 && (
            <>
              <div className="fx-cmdk-grp">DF-e · {results.dfe.length}</div>
              {results.dfe.map(d => {
                cursorIdx++;
                const sel = cursorIdx === cursor;
                return (
                  <div key={d.id} className={"fx-cmdk-item" + (sel ? " sel" : "")}
                       onMouseEnter={() => setCursor(cursorIdx)}
                       onClick={() => trigger({ kind:"dfe", item:d })}>
                    <span className="fx-cmdk-badge warn">DFe</span>
                    <div className="fx-cmdk-body"><b>{d.title}</b><small>{d.sub}</small></div>
                  </div>
                );
              })}
            </>
          )}

          {flat.length === 0 && (
            <div className="fx-cmdk-empty">
              <Ic name="search" size={20}/>
              <b>Nenhum resultado</b>
              <small>Tente buscar pelos últimos 6 dígitos da chave de acesso, ou pelo nº da nota.</small>
            </div>
          )}
        </div>

        <div className="fx-cmdk-foot">
          <span><kbd>↑</kbd><kbd>↓</kbd> navegar</span>
          <span><kbd>⏎</kbd> abrir</span>
          <span><kbd>esc</kbd> fechar</span>
          <span className="ml-auto">⌘K busca tudo do Fiscal</span>
        </div>
      </div>
    </div>
  );
}

/* ╔════════════════════════════════════════════════════════════════╗
   ║ 5) SLIDE-IN DRAWER (nota detalhe + mapa SEFAZ guiado)         ║
   ╚════════════════════════════════════════════════════════════════╝ */

function SefazActionCard({ nota }) {
  if (nota.status === 100) return null;
  const recipe = D.SEFAZ_ACTIONS[nota.status];
  if (!recipe) return null;
  return (
    <div className="fx-action-card">
      <div className="fx-action-h">
        <span className="fx-action-spark">
          <Ic name="bot" size={14}/>
        </span>
        <div>
          <b>Jana sugere · SEFAZ {nota.status}</b>
          <small>{recipe.headline}</small>
        </div>
      </div>
      <ol className="fx-action-steps">
        {recipe.steps.map((s, i) => (
          <li key={i}>
            <span className="fx-action-n">{i + 1}</span>
            <span>{s}</span>
          </li>
        ))}
      </ol>
      <div className="fx-action-btns">
        {recipe.primary && (
          <button className={`fx-btn ${recipe.primary.kind}`}>
            {recipe.primary.label}
            <kbd className="fx-kbd-inline">⏎</kbd>
          </button>
        )}
        {recipe.secondary && (
          <button className="fx-btn ghost">{recipe.secondary.label}</button>
        )}
      </div>
      <small className="fx-action-foot">fonte: receita SEFAZ-SP · revisada por contadora</small>
    </div>
  );
}

function NotaDrawer({ nota, onClose, onNavigate }) {
  // ESC fecha
  useEffect(() => {
    if (!nota) return;
    const h = (e) => { if (e.key === "Escape") onClose?.(); };
    window.addEventListener("keydown", h);
    return () => window.removeEventListener("keydown", h);
  }, [nota, onClose]);

  if (!nota) return null;
  const cancel = prazoCancel(nota);
  const cce    = prazoCCe(nota);
  const sefaz  = D.SEFAZ_CODES[nota.status];

  return (
    <>
      <div className="fx-drawer-bg" onClick={onClose} aria-hidden="true"/>
      <aside className="fx-drawer" role="dialog" aria-label="Detalhe da nota">
        <header className="fx-drawer-h">
          <div>
            <small>{nota.modelo === 65 ? "NFC-e" : "NF-e"} · série {nota.serie}</small>
            <h2>{nota.modelo === 65 ? "NFC-e " : "NFe "}{nota.num}</h2>
            <code className="fx-drawer-key">{nota.key}</code>
          </div>
          <button className="fx-drawer-x" onClick={onClose} aria-label="Fechar (ESC)">
            <span>×</span>
            <kbd>esc</kbd>
          </button>
        </header>

        <div className="fx-drawer-body">

          {/* status */}
          <section className="fx-drawer-sec">
            <h4>Status SEFAZ</h4>
            <div className="fx-drawer-status-row">
              <SefazPill code={nota.status}/>
              {cancel && <TimePill kind="cancel" hours={cancel.h + cancel.m / 60} urgency={cancel.urgency}/>}
              {cce && <TimePill kind="cce" days={cce.d} urgency={cce.urgency}/>}
            </div>
            <p className="fx-drawer-hint">{sefaz?.hint}</p>
            {nota.rejMsg && (
              <div className="fx-drawer-rej">↳ {nota.rejMsg}</div>
            )}
          </section>

          {/* mapa SEFAZ guiado — só em rejeitadas */}
          <SefazActionCard nota={nota}/>

          {/* destinatário */}
          <section className="fx-drawer-sec">
            <h4>Destinatário</h4>
            <dl className="fx-kv">
              <dt>Nome</dt><dd>{nota.dest}</dd>
              <dt>{nota.cnpj ? "CNPJ" : "CPF"}</dt>
              <dd>
                <Linkify onNavigate={onNavigate}>{formatDoc(nota.cnpj, nota.cpf)}</Linkify>
              </dd>
              <dt>UF</dt><dd>{nota.uf}</dd>
              <dt>Itens</dt><dd>{nota.items}</dd>
            </dl>
          </section>

          {/* operação */}
          <section className="fx-drawer-sec">
            <h4>Operação</h4>
            <dl className="fx-kv">
              <dt>Venda</dt>
              <dd><Linkify onNavigate={onNavigate}>{nota.venda || "—"}</Linkify></dd>
              <dt>Emissão</dt><dd>{nota.when}</dd>
              <dt>Valor</dt><dd className="fx-strong">{brl(nota.value)}</dd>
              <dt>Modelo</dt><dd>{nota.modelo} · {nota.modelo === 65 ? "consumidor" : "B2B"}</dd>
            </dl>
          </section>

        </div>

        <footer className="fx-drawer-f">
          <button className="fx-btn ghost"><Ic name="search" size={12}/> Reconsultar SEFAZ <kbd className="fx-kbd-inline">R</kbd></button>
          <div className="fx-drawer-f-r">
            <button className="fx-btn">XML</button>
            <button className="fx-btn">DANFE</button>
            {nota.status === 100 && cancel && (
              <button className="fx-btn danger">Cancelar <kbd className="fx-kbd-inline">X</kbd></button>
            )}
            {[110, 204, 220, 539, 691, 778].includes(nota.status) && (
              <button className="fx-btn primary">Retransmitir <kbd className="fx-kbd-inline">⏎</kbd></button>
            )}
          </div>
        </footer>
      </aside>
    </>
  );
}

/* ╔════════════════════════════════════════════════════════════════╗
   ║ 6) FxShell — wrapper das 7 páginas                             ║
   ╚════════════════════════════════════════════════════════════════╝ */

function FxShell({ route, onNavigate, title, crumb, env, envTone = "ok", actions, cheats = [], children }) {
  const [cmdkOpen, setCmdkOpen] = useState(false);
  const [helpOpen, setHelpOpen] = useState(false);

  // ⌘K + atalhos de página (1-7)
  useEffect(() => {
    const h = (e) => {
      const isCmd = e.metaKey || e.ctrlKey;
      const target = e.target;
      const isTyping = target && (target.tagName === "INPUT" || target.tagName === "TEXTAREA" || target.isContentEditable);

      if (isCmd && (e.key === "k" || e.key === "K")) {
        e.preventDefault();
        setCmdkOpen(true);
      } else if (!isTyping) {
        const page = FX_PAGES.find(p => p.short === e.key);
        if (page) { e.preventDefault(); onNavigate?.(page.id); }
        if (e.key === "?") { e.preventDefault(); setHelpOpen(true); }
      }
    };
    window.addEventListener("keydown", h);
    return () => window.removeEventListener("keydown", h);
  }, [onNavigate]);

  // Contadores pro sub-nav
  const counts = useMemo(() => {
    const rej = D.NOTAS_SAIDA.filter(n => [110,204,220,539,691,778].includes(n.status)).length;
    const dfe = D.DFE_PENDENTE.length;
    return {
      nfe: rej > 0 ? rej : null,
      dfe: dfe > 0 ? dfe : null,
    };
  }, []);

  const navigateFromCmdk = (r, opts) => {
    if (opts?.filter) window.__fxFilter = opts.filter;
    if (opts?.focus)  window.__fxFocus  = opts.focus;
    onNavigate?.(r);
  };

  return (
    <div className="fx-page" data-screen-label={"00 " + route}>
      <header className="fx-hero">
        <div className="fx-hero-l">
          <h1>{title}</h1>
          {crumb && <span className="fx-hero-crumb">{crumb}</span>}
        </div>
        <div className="fx-hero-r">
          {env && <EnvBadge tone={envTone} label={env}/>}
          <button className="fx-btn ghost fx-cmdk-btn" onClick={() => setCmdkOpen(true)}>
            <Ic name="search" size={13}/>
            <span>Buscar</span>
            <kbd>⌘K</kbd>
          </button>
          {actions}
        </div>
      </header>

      <SubNav active={route} onNavigate={onNavigate} counts={counts}/>

      <div className="fx-body">{children}</div>

      <footer className="fx-shell-foot">
        <CheatSheet items={cheats.length ? cheats : DEFAULT_CHEATS} onOpenHelp={() => setHelpOpen(true)}/>
      </footer>

      <CmdKPalette
        open={cmdkOpen}
        onClose={() => setCmdkOpen(false)}
        onNavigate={navigateFromCmdk}
      />

      {helpOpen && <HelpModal onClose={() => setHelpOpen(false)}/>}
    </div>
  );
}

const DEFAULT_CHEATS = [
  { keys:["⌘","K"], label:"buscar tudo" },
  { keys:["1"],     label:"cockpit" },
  { keys:["2"],     label:"NF-e" },
  { keys:["J","K"], label:"navegar lista" },
  { keys:["?"],     label:"todos os atalhos" },
];

function HelpModal({ onClose }) {
  useEffect(() => {
    const h = (e) => { if (e.key === "Escape") onClose?.(); };
    window.addEventListener("keydown", h);
    return () => window.removeEventListener("keydown", h);
  }, [onClose]);

  return (
    <div className="fx-modal-bg" onClick={onClose}>
      <div className="fx-help" onClick={(e) => e.stopPropagation()}>
        <header className="fx-help-h">
          <h2>Atalhos do módulo Fiscal</h2>
          <button className="fx-drawer-x" onClick={onClose} aria-label="Fechar"><span>×</span></button>
        </header>
        <div className="fx-help-grid">
          <section>
            <h4>Navegação</h4>
            <ul>
              <li><kbd>⌘</kbd><kbd>K</kbd> · busca global no Fiscal</li>
              <li><kbd>1</kbd>–<kbd>7</kbd> · pular pra cada sub-página</li>
              <li><kbd>J</kbd> / <kbd>K</kbd> · próximo / anterior na lista</li>
              <li><kbd>⏎</kbd> · abrir item focado</li>
              <li><kbd>esc</kbd> · fechar drawer / palette</li>
            </ul>
          </section>
          <section>
            <h4>Ações em uma nota</h4>
            <ul>
              <li><kbd>E</kbd> · emitir nova</li>
              <li><kbd>R</kbd> · reconsultar SEFAZ</li>
              <li><kbd>X</kbd> · cancelar (se na janela 24h)</li>
              <li><kbd>C</kbd> · CC-e (até 30d)</li>
              <li><kbd>M</kbd> · manifestar (DF-e)</li>
            </ul>
          </section>
          <section>
            <h4>Pílulas temporais</h4>
            <ul>
              <li><span className="fx-timepill u-ok"><b>3d</b></span> · janela confortável</li>
              <li><span className="fx-timepill u-warn"><b>14h</b></span> · atenção 6–12h</li>
              <li><span className="fx-timepill u-crit"><b>2h</b></span> · &lt; 6h restantes</li>
            </ul>
          </section>
          <section>
            <h4>Mapa SEFAZ</h4>
            <p style={{margin:0, color:"var(--text-mute)", fontSize:12, lineHeight:1.5}}>
              Toda nota rejeitada exibe o card <b>Jana sugere</b> com a receita
              determinística pro código (220, 539, 691, 778…). Cada passo é acionável.
            </p>
          </section>
        </div>
      </div>
    </div>
  );
}

/* ╔════════════════════════════════════════════════════════════════╗
   ║ 7) Hook · navegação por J/K em listas                          ║
   ╚════════════════════════════════════════════════════════════════╝ */
function useListNav(items, onOpen, enabled = true) {
  const [cursor, setCursor] = useState(0);
  useEffect(() => {
    if (!enabled) return;
    const h = (e) => {
      const target = e.target;
      const isTyping = target && (target.tagName === "INPUT" || target.tagName === "TEXTAREA" || target.isContentEditable);
      if (isTyping) return;
      if (e.key === "j" || e.key === "ArrowDown") {
        e.preventDefault(); setCursor(c => Math.min(items.length - 1, c + 1));
      } else if (e.key === "k" || e.key === "ArrowUp") {
        e.preventDefault(); setCursor(c => Math.max(0, c - 1));
      } else if (e.key === "Enter") {
        e.preventDefault(); onOpen?.(items[cursor]);
      }
    };
    window.addEventListener("keydown", h);
    return () => window.removeEventListener("keydown", h);
  }, [items, cursor, onOpen, enabled]);
  useEffect(() => { if (cursor >= items.length) setCursor(Math.max(0, items.length - 1)); }, [items, cursor]);
  return { cursor, setCursor };
}

/* ╔════════════════════════════════════════════════════════════════╗
   ║ 8) PÁGINA 1 · COCKPIT FISCAL                                   ║
   ╚════════════════════════════════════════════════════════════════╝ */

function FiscalCockpit({ onNavigate }) {
  const k = D.KPIS;
  const navigate = (r, opts) => {
    if (opts?.focus) window.__fxFocus = opts.focus;
    onNavigate?.(r);
  };

  return (
    <FxShell
      route="fiscal"
      onNavigate={onNavigate}
      title="Cockpit fiscal"
      crumb="Maio 2026 · Eliana contadora · 9 dias trabalhados"
      env="SEFAZ-SP operacional"
      envTone="ok"
      actions={<>
        <button className="fx-btn">Exportar SPED</button>
        <button className="fx-btn primary"><Ic name="plus" size={12}/> Emitir <kbd className="fx-kbd-inline">E</kbd></button>
      </>}
      cheats={[
        { keys:["⌘","K"], label:"buscar" },
        { keys:["2"],     label:"NF-e" },
        { keys:["4"],     label:"DF-e (5)" },
        { keys:["?"],     label:"mais atalhos" },
      ]}
    >
      {/* KPIs com sparklines */}
      <div className="fx-kpis fx-kpis-cockpit">
        <div className="fx-kpi hero">
          <div className="fx-kpi-top">
            <small>Emitidas · maio</small>
            <MiniSparkline data={D.SPARKLINES.emitidas} color="#ffffff"/>
          </div>
          <b>{k.emitidas.value}</b>
          <span className="delta">{k.emitidas.deltaLabel}</span>
        </div>
        <div className="fx-kpi ok">
          <div className="fx-kpi-top">
            <small>Autorizadas</small>
            <MiniSparkline data={D.SPARKLINES.autorizadas} color="var(--ok, oklch(0.55 0.13 145))"/>
          </div>
          <b>{k.autorizadas.value}</b>
          <span className="delta">{k.autorizadas.pct}% do total</span>
        </div>
        <div className={`fx-kpi bad${k.rejeitadas.value > 0 ? " pulse" : ""}`}>
          <div className="fx-kpi-top">
            <small>Rejeitadas</small>
            <MiniSparkline data={D.SPARKLINES.rejeitadas} color="var(--bad, oklch(0.55 0.18 25))" fill={false}/>
          </div>
          <b>{k.rejeitadas.value}</b>
          <span className="delta" style={{ color:"var(--bad, oklch(0.55 0.18 25))" }}>{k.rejeitadas.label}</span>
        </div>
        <div className="fx-kpi">
          <div className="fx-kpi-top">
            <small>Faturado fiscal</small>
            <MiniSparkline data={D.SPARKLINES.faturamento} color="var(--accent)"/>
          </div>
          <b>{brl(k.faturamentoFiscal).replace("R$", "R$ ")}</b>
          <span className="delta">↑ 14% vs abr</span>
        </div>
        <div className="fx-kpi warn">
          <small>DF-e p/ manifestar</small>
          <b>{k.dfeAguardando.value}</b>
          <span className="delta">{k.dfeAguardando.label}</span>
        </div>
        <div className="fx-kpi">
          <small>Certificado A1</small>
          <b>{k.certificadoValidadeDias}d</b>
          <span className="delta" style={{ color:"oklch(0.45 0.12 60)" }}>vence em 26/06</span>
        </div>
      </div>

      {/* Alertas */}
      <div className="fx-alerts">
        <div className="fx-alerts-h">
          <Ic name="audit"/>
          <h3>Pendências fiscais</h3>
          <span className="count">{D.ALERTS.length} · 2 críticos · 2 atenção · 1 info</span>
        </div>
        {D.ALERTS.map((a, i) => (
          <div key={i} className={`fx-alert ${a.level}`} onClick={() => navigate(a.goto, { focus: a.focus })}>
            <div className="fx-alert-ic"><Ic name={a.icon} size={14}/></div>
            <div className="fx-alert-body">
              <b>{a.title}</b>
              <span className="sub">{a.sub}</span>
            </div>
            <span className="fx-alert-act">{a.action} →</span>
          </div>
        ))}
      </div>

      {/* Quick links */}
      <div className="fx-quick">
        <div className="fx-quick-card" onClick={() => navigate("nfe")}>
          <div className="top"><Ic name="receipt"/> NF-e · NFC-e (saída)</div>
          <b>{D.NOTAS_SAIDA.length} no mês</b>
          <small>Modelo 55 + 65 · 2 rejeitadas requerem ação</small>
          <kbd className="fx-quick-kbd">2</kbd>
        </div>
        <div className="fx-quick-card" onClick={() => navigate("nfse")}>
          <div className="top"><Ic name="doc"/> NFS-e (serviço)</div>
          <b>{D.NOTAS_NFSE.length} no mês</b>
          <small>Sistema Nacional LC 214/2025 · 1 rejeitada</small>
          <kbd className="fx-quick-kbd">3</kbd>
        </div>
        <div className="fx-quick-card" onClick={() => navigate("dfe")}>
          <div className="top"><Ic name="audit"/> Manifesto destinatário</div>
          <b>{D.DFE_PENDENTE.length} aguardando ciência</b>
          <small>NF-e emitidas contra nosso CNPJ · prazo 90d</small>
          <kbd className="fx-quick-kbd">4</kbd>
        </div>
        <div className="fx-quick-card" onClick={() => navigate("fiscal_eventos")}>
          <div className="top"><Ic name="refresh"/> Eventos</div>
          <b>{D.EVENTOS.length} esta semana</b>
          <small>CC-e · cancelamento · inutilização</small>
          <kbd className="fx-quick-kbd">5</kbd>
        </div>
        <div className="fx-quick-card" onClick={() => navigate("fiscal_config")}>
          <div className="top"><Ic name="shield"/> Certificado & config.</div>
          <b>A1 vence em {k.certificadoValidadeDias}d</b>
          <small>3 séries · produção · Lucro Presumido</small>
          <kbd className="fx-quick-kbd">6</kbd>
        </div>
        <div className="fx-quick-card" onClick={() => navigate("sped")}>
          <div className="top"><Ic name="archive"/> SPED & livros</div>
          <b>Abril pronto · maio em curso</b>
          <small>EFD ICMS/IPI · PIS/COFINS · prazo 15/06</small>
          <kbd className="fx-quick-kbd">7</kbd>
        </div>
      </div>
    </FxShell>
  );
}

/* ╔════════════════════════════════════════════════════════════════╗
   ║ 9) PÁGINA 2 · NF-e · NFC-e                                     ║
   ╚════════════════════════════════════════════════════════════════╝ */

function FiscalNFePage({ onNavigate }) {
  const [tab, setTab] = useState("saida_nfe");
  const [filter, setFilter] = useState(() => window.__fxFilter || "todas");
  const [search, setSearch] = useState("");
  const [opened, setOpened] = useState(null);

  // Consumir focus vindo do cockpit/cmdk
  useEffect(() => {
    if (window.__fxFocus) {
      const nota = D.NOTAS_SAIDA.find(n => n.id === window.__fxFocus);
      if (nota) {
        setOpened(nota);
        setTab(nota.modelo === 65 ? "saida_nfce" : "saida_nfe");
      }
      window.__fxFocus = null;
    }
    if (window.__fxFilter) { setFilter(window.__fxFilter); window.__fxFilter = null; }
  }, []);

  const base = D.NOTAS_SAIDA;
  const filtered = useMemo(() => {
    let rows = base;
    if (tab === "saida_nfe")  rows = rows.filter(n => n.modelo === 55);
    if (tab === "saida_nfce") rows = rows.filter(n => n.modelo === 65);
    if (filter === "autorizadas") rows = rows.filter(n => n.status === 100);
    if (filter === "rejeitadas")  rows = rows.filter(n => [110,204,220,539,691,778].includes(n.status));
    if (filter === "processando") rows = rows.filter(n => n.status === 999);
    if (filter === "cancelaveis") rows = rows.filter(n => prazoCancel(n));
    if (search) {
      const s = search.toLowerCase();
      rows = rows.filter(n =>
        n.num.includes(s) ||
        n.dest.toLowerCase().includes(s) ||
        n.key.includes(s.replace(/\D/g, "")) ||
        (s.length >= 3 && (formatDoc(n.cnpj, n.cpf).replace(/\D/g, "")).includes(s.replace(/\D/g, "")))
      );
    }
    return rows;
  }, [tab, filter, search]);

  const { cursor, setCursor } = useListNav(filtered, setOpened, tab !== "entrada");

  const counts = {
    nfe: base.filter(n => n.modelo === 55).length,
    nfce: base.filter(n => n.modelo === 65).length,
    autorizadas: base.filter(n => n.status === 100).length,
    rejeitadas:  base.filter(n => [110,204,220,539,691,778].includes(n.status)).length,
    processando: base.filter(n => n.status === 999).length,
    cancelaveis: base.filter(n => prazoCancel(n)).length,
  };

  return (
    <FxShell
      route="nfe"
      onNavigate={onNavigate}
      title="NF-e · NFC-e"
      crumb={`Modelos 55 + 65 · ${counts.nfe + counts.nfce} no mês · ${counts.rejeitadas} requerem ação`}
      env={`${counts.autorizadas} autorizadas · ${counts.processando} processando`}
      envTone={counts.rejeitadas > 0 ? "warn" : "ok"}
      actions={<>
        <button className="fx-btn ghost">Importar XML entrada</button>
        <button className="fx-btn primary"><Ic name="plus" size={12}/> Emitir <kbd className="fx-kbd-inline">E</kbd></button>
      </>}
      cheats={[
        { keys:["⌘","K"], label:"buscar" },
        { keys:["J","K"], label:"navegar" },
        { keys:["⏎"],     label:"abrir" },
        { keys:["R"],     label:"reconsultar SEFAZ" },
        { keys:["X"],     label:"cancelar" },
      ]}
    >
      {/* Tabs internas: modelo */}
      <div className="fx-subtabs">
        <button className={"fx-subtab" + (tab === "saida_nfe" ? " active" : "")}  onClick={() => setTab("saida_nfe")}>
          NF-e (55) <span className="n">{counts.nfe}</span>
        </button>
        <button className={"fx-subtab" + (tab === "saida_nfce" ? " active" : "")} onClick={() => setTab("saida_nfce")}>
          NFC-e (65) <span className="n">{counts.nfce}</span>
        </button>
        <button className={"fx-subtab" + (tab === "entrada" ? " active" : "")}    onClick={() => setTab("entrada")}>
          Entrada (XML) <span className="n">0</span>
        </button>
      </div>

      {tab === "entrada" ? (
        <div className="fx-empty">
          <b>Importação de XML de fornecedor</b>
          Arraste um XML aqui para vincular a um pedido em <Linkify onNavigate={onNavigate}>Compras</Linkify>,
          baixar estoque e gerar título a pagar.
          <small>Backlog F2 · depende de Modules/NfeBrasil expor o endpoint de importação.</small>
        </div>
      ) : (
        <>
          {/* Filtros chip-row */}
          <div className="fx-filters">
            <div className="fx-search">
              <Ic name="search" size={13}/>
              <input
                placeholder="Buscar nº, destinatário, CNPJ, ou últimos dígitos da chave…"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </div>
            <button className={"fx-chip" + (filter === "todas" ? " active" : "")}        onClick={() => setFilter("todas")}>Todas <span>{filtered.length}</span></button>
            <button className={"fx-chip" + (filter === "autorizadas" ? " active" : "")}  onClick={() => setFilter("autorizadas")}>Autorizadas <span>{counts.autorizadas}</span></button>
            <button className={"fx-chip danger" + (filter === "rejeitadas" ? " active" : "")} onClick={() => setFilter("rejeitadas")}>
              <Ic name="audit" size={11}/> Rejeitadas <span>{counts.rejeitadas}</span>
            </button>
            <button className={"fx-chip warn" + (filter === "cancelaveis" ? " active" : "")} onClick={() => setFilter("cancelaveis")}>
              <Ic name="refresh" size={11}/> Janela 24h <span>{counts.cancelaveis}</span>
            </button>
            <button className={"fx-chip" + (filter === "processando" ? " active" : "")}  onClick={() => setFilter("processando")}>Processando <span>{counts.processando}</span></button>
          </div>

          <div className="fx-table" data-keyboard="true">
            <table>
              <thead>
                <tr>
                  <th style={{ width:60 }}></th>
                  <th style={{ width:88 }}>Número</th>
                  <th>Chave / destinatário</th>
                  <th style={{ width:200 }}>Status SEFAZ</th>
                  <th style={{ width:200 }}>Prazo legal</th>
                  <th className="num" style={{ width:110 }}>Valor</th>
                </tr>
              </thead>
              <tbody>
                {filtered.map((n, i) => {
                  const cancel = prazoCancel(n);
                  const cce    = prazoCCe(n);
                  const focused = i === cursor;
                  const rejected = [110,204,220,539,691,778].includes(n.status);
                  return (
                    <tr key={n.id}
                        className={(opened?.id === n.id ? "sel" : "") + (focused ? " focus" : "") + (rejected ? " row-rej" : "")}
                        onClick={() => { setCursor(i); setOpened(n); }}
                        onMouseEnter={() => setCursor(i)}>
                      <td className="fx-row-marker">{focused ? "▸" : ""}</td>
                      <td>
                        <b>{n.modelo === 65 ? "NFC-e " : "NFe "}{n.num}</b>
                        <div className="mut">série {n.serie}</div>
                      </td>
                      <td>
                        <code className="fx-ch" title={n.key}>{truncKey(n.key)}</code>
                        <div><b>{n.dest}</b></div>
                        <div className="mut">
                          <Linkify onNavigate={onNavigate}>{formatDoc(n.cnpj, n.cpf)}</Linkify> · {n.uf} · <Linkify onNavigate={onNavigate}>{n.venda || ""}</Linkify>
                        </div>
                      </td>
                      <td>
                        <SefazPill code={n.status}/>
                        {n.rejMsg && <div className="fx-rej-mini">↳ {n.rejMsg}</div>}
                      </td>
                      <td>
                        {cancel && <TimePill kind="cancel" hours={cancel.h + cancel.m / 60} urgency={cancel.urgency} compact/>}
                        {cce && !cancel && <TimePill kind="cce" days={cce.d} urgency={cce.urgency} compact/>}
                        {!cancel && !cce && rejected && <span className="fx-prazo-act">ação requerida</span>}
                      </td>
                      <td className="num">{brl(n.value)}</td>
                    </tr>
                  );
                })}
                {filtered.length === 0 && (
                  <tr><td colSpan="6" className="fx-empty-row">Nenhuma nota para os filtros atuais.</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </>
      )}

      <NotaDrawer nota={opened} onClose={() => setOpened(null)} onNavigate={onNavigate}/>
    </FxShell>
  );
}

/* ╔════════════════════════════════════════════════════════════════╗
   ║ 10) PÁGINA 3 · NFS-e                                           ║
   ╚════════════════════════════════════════════════════════════════╝ */
function FiscalNFSePage({ onNavigate }) {
  const rows = D.NOTAS_NFSE;
  const [opened, setOpened] = useState(null);
  const { cursor, setCursor } = useListNav(rows, setOpened);

  return (
    <FxShell
      route="nfse"
      onNavigate={onNavigate}
      title="NFS-e · nota fiscal de serviço"
      crumb={`Sistema Nacional LC 214/2025 · ${rows.length} em maio`}
      env="Prefeituras SP / Guarulhos · ok"
      actions={<>
        <button className="fx-btn ghost">Códigos LC 116</button>
        <button className="fx-btn primary"><Ic name="plus" size={12}/> Emitir NFS-e</button>
      </>}
      cheats={[
        { keys:["⌘","K"], label:"buscar" },
        { keys:["J","K"], label:"navegar" },
        { keys:["⏎"],     label:"abrir" },
      ]}
    >
      <div className="fx-kpis fx-kpis-4">
        <div className="fx-kpi hero"><small>Emitidas · maio</small><b>{rows.length}</b><span className="delta">↑ 2 vs abr</span></div>
        <div className="fx-kpi ok"><small>Autorizadas</small><b>{rows.filter(r => r.status === "autorizada").length}</b><span className="delta">{Math.round(rows.filter(r=>r.status==="autorizada").length/rows.length*100)}%</span></div>
        <div className="fx-kpi warn"><small>Processando</small><b>{rows.filter(r => r.status === "processando").length}</b><span className="delta">aguardando prefeitura</span></div>
        <div className="fx-kpi bad"><small>Rejeitadas</small><b>{rows.filter(r => r.status === "rejeitada").length}</b><span className="delta" style={{ color:"var(--bad)" }}>requer ação</span></div>
      </div>

      <div className="fx-table">
        <table>
          <thead>
            <tr>
              <th style={{ width: 60 }}></th>
              <th style={{ width: 88 }}>Número</th>
              <th>Tomador</th>
              <th style={{ width: 140 }}>Município · ISS</th>
              <th style={{ width: 90 }}>Cód. serviço</th>
              <th style={{ width: 150 }}>Status</th>
              <th className="num" style={{ width: 100 }}>Valor</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r, i) => {
              const focused = i === cursor;
              return (
                <tr key={r.id} className={focused ? "focus" : ""}
                    onClick={() => setCursor(i)}
                    onMouseEnter={() => setCursor(i)}>
                  <td className="fx-row-marker">{focused ? "▸" : ""}</td>
                  <td>
                    <b>NFSe {r.num}</b>
                    <div className="mut">{r.competencia}</div>
                  </td>
                  <td>
                    <b>{r.tomador}</b>
                    <div className="mut">
                      <Linkify onNavigate={onNavigate}>{formatDoc(r.cnpj, r.cpf)}</Linkify> · <Linkify onNavigate={onNavigate}>{r.ref}</Linkify>
                    </div>
                  </td>
                  <td>
                    <div>{r.municipio}</div>
                    <div className="mut">{r.iss}% ISS</div>
                  </td>
                  <td><code className="fx-ch">{r.codServ}</code></td>
                  <td>
                    {r.status === "autorizada"  && <span className="fx-env ok">100 · autorizada</span>}
                    {r.status === "processando" && <span className="fx-env warn">999 · processando</span>}
                    {r.status === "rejeitada"   && <span className="fx-env bad">rejeitada</span>}
                    {r.rejMsg && <div className="fx-rej-mini">↳ {r.rejMsg}</div>}
                  </td>
                  <td className="num">{brl(r.value)}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </FxShell>
  );
}

/* ╔════════════════════════════════════════════════════════════════╗
   ║ 11) PÁGINA 4 · DF-e                                            ║
   ╚════════════════════════════════════════════════════════════════╝ */
function FiscalDFePage({ onNavigate }) {
  const [tab, setTab] = useState("pendente");
  return (
    <FxShell
      route="dfe"
      onNavigate={onNavigate}
      title="Manifesto do destinatário"
      crumb={`${D.DFE_PENDENTE.length} aguardando ciência · busca diária SEFAZ`}
      env="DF-e auto · diária 06:00"
      envTone="ok"
      actions={<>
        <button className="fx-btn ghost">Configurar busca</button>
        <button className="fx-btn primary">Manifestar selecionadas <kbd className="fx-kbd-inline">M</kbd></button>
      </>}
      cheats={[
        { keys:["⌘","K"], label:"buscar" },
        { keys:["M"],     label:"manifestar" },
        { keys:["?"],     label:"o que é DF-e?" },
      ]}
    >
      <div className="fx-callout">
        <Ic name="audit" size={18}/>
        <div>
          <b className="fx-callout-lead">O que é manifestação?</b>
          <small>
            Toda NF-e emitida com seu CNPJ no destinatário precisa ser manifestada em até <b>90 dias</b>.
            4 respostas: <b>ciência</b> (vi, não confirmo), <b>confirmação</b> (recebi e bate),
            <b> desconhecimento</b> (não reconheço) ou <b>não realizada</b> (operação não aconteceu).
            Sem manifestar, escrita fiscal e CIAP ficam inconsistentes.
          </small>
        </div>
      </div>

      <div className="fx-subtabs">
        <button className={"fx-subtab" + (tab === "pendente" ? " active" : "")}  onClick={() => setTab("pendente")}>
          Aguardando ciência <span className="n">{D.DFE_PENDENTE.length}</span>
        </button>
        <button className={"fx-subtab" + (tab === "historico" ? " active" : "")} onClick={() => setTab("historico")}>
          Histórico <span className="n">{D.DFE_HISTORICO.length}</span>
        </button>
      </div>

      {tab === "pendente" ? (
        <div className="fx-table">
          <table>
            <thead>
              <tr>
                <th style={{ width:36 }}><input type="checkbox" aria-label="Selecionar todas"/></th>
                <th>Emitente</th>
                <th style={{ width:180 }}>Chave</th>
                <th style={{ width:120 }}>Recebida · prazo</th>
                <th className="num" style={{ width:110 }}>Valor</th>
                <th style={{ width:300 }}>Ação</th>
              </tr>
            </thead>
            <tbody>
              {D.DFE_PENDENTE.map(d => (
                <tr key={d.id}>
                  <td><input type="checkbox" aria-label={`Selecionar ${d.id}`}/></td>
                  <td>
                    <b>{d.emitente}</b>
                    <div className="mut">
                      <Linkify onNavigate={onNavigate}>{d.cnpj}</Linkify> · {d.desc}
                    </div>
                  </td>
                  <td><code className="fx-ch" title={d.key}>{truncKey(d.key)}</code></td>
                  <td>
                    <div className="mut">{d.when}</div>
                    <TimePill kind="manifest" days={d.ddays} urgency={d.ddays < 7 ? "crit" : d.ddays < 21 ? "warn" : "ok"} compact/>
                  </td>
                  <td className="num">{brl(d.value)}</td>
                  <td>
                    <div className="fx-dfe-actions">
                      <button className="fx-dfe-act">Ciência</button>
                      <button className="fx-dfe-act ok">Confirmar</button>
                      <button className="fx-dfe-act bad">Desconhecer</button>
                      <button className="fx-dfe-act">Não realizada</button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="fx-table">
          <table>
            <thead>
              <tr>
                <th>Emitente</th>
                <th style={{ width:100 }}>Quando</th>
                <th style={{ width:160 }}>Manifestação</th>
                <th style={{ width:100 }}>Por</th>
                <th>Observação</th>
                <th className="num" style={{ width:110 }}>Valor</th>
              </tr>
            </thead>
            <tbody>
              {D.DFE_HISTORICO.map(d => (
                <tr key={d.id}>
                  <td><b>{d.emitente}</b></td>
                  <td className="mut">{d.when}</td>
                  <td>
                    {d.ack === "confirmada"      && <span className="fx-env ok">✓ confirmada</span>}
                    {d.ack === "ciencia"          && <span className="fx-env warn">~ ciência</span>}
                    {d.ack === "desconhecimento" && <span className="fx-env bad">✗ desconhecida</span>}
                  </td>
                  <td className="mut">{d.actor}</td>
                  <td className="mut">{d.obs || "—"}</td>
                  <td className="num">{brl(d.value)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </FxShell>
  );
}

/* ╔════════════════════════════════════════════════════════════════╗
   ║ 12) PÁGINA 5 · EVENTOS                                          ║
   ╚════════════════════════════════════════════════════════════════╝ */
function FiscalEventosPage({ onNavigate }) {
  return (
    <FxShell
      route="fiscal_eventos"
      onNavigate={onNavigate}
      title="Eventos fiscais"
      crumb={`${D.EVENTOS.length} eventos esta semana · janelas legais aplicadas`}
      env="SEFAZ-SP ok"
      actions={<>
        <button className="fx-btn ghost">Inutilizar faixa</button>
        <button className="fx-btn primary"><Ic name="plus" size={12}/> Novo evento</button>
      </>}
      cheats={[
        { keys:["⌘","K"], label:"buscar" },
        { keys:["C"],     label:"nova CC-e" },
        { keys:["X"],     label:"cancelar nota" },
      ]}
    >
      <div className="fx-callout">
        <Ic name="refresh" size={18}/>
        <div>
          <b className="fx-callout-lead">Janelas legais que o sistema valida</b>
          <small>
            <b>CC-e:</b> até 30 dias da emissão · máx 20 por nota · não corrige valor/CFOP/qtd.{" "}
            <b>Cancelamento:</b> até 24h da autorização (NF-e) · 168h se a UF permitir.{" "}
            <b>Inutilização:</b> só para faixas de numeração não usadas.
          </small>
        </div>
      </div>

      <div className="fx-table">
        <table>
          <thead>
            <tr>
              <th style={{ width:120 }}>Tipo</th>
              <th style={{ width:130 }}>Documento</th>
              <th>Descrição</th>
              <th style={{ width:110 }}>Emissão</th>
              <th style={{ width:100 }}>Autor</th>
              <th style={{ width:160 }}>SEFAZ</th>
            </tr>
          </thead>
          <tbody>
            {D.EVENTOS.map(e => (
              <tr key={e.id}>
                <td>
                  {e.tipo === "CCe"           && <span className="fx-evt-type cce">CC-e {e.sequencia ? `seq ${e.sequencia}` : ""}</span>}
                  {e.tipo === "Cancelamento"  && <span className="fx-evt-type cancel">Cancelamento</span>}
                  {e.tipo === "Inutilização"  && <span className="fx-evt-type inut">Inutilização</span>}
                </td>
                <td><b>{e.nota}</b></td>
                <td><Linkify onNavigate={onNavigate}>{e.descricao}</Linkify></td>
                <td className="mut">{e.emit}</td>
                <td className="mut">{e.autor}</td>
                <td><SefazPill code={e.sefaz}/></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </FxShell>
  );
}

/* ╔════════════════════════════════════════════════════════════════╗
   ║ 13) PÁGINA 6 · CERTIFICADO & CONFIG                            ║
   ╚════════════════════════════════════════════════════════════════╝ */
function FiscalConfigPage({ onNavigate }) {
  const cfg = D.CONFIG;
  const c = cfg.certificado;
  const validPct = Math.max(0, Math.min(100, (c.diasRestantes / 365) * 100));
  return (
    <FxShell
      route="fiscal_config"
      onNavigate={onNavigate}
      title="Certificado & configuração"
      crumb={`A1 vence em ${c.diasRestantes}d · 3 séries · ambiente PRODUÇÃO`}
      env="MemCofre · senha protegida"
      actions={<>
        <button className="fx-btn ghost">Testar SEFAZ</button>
        <button className="fx-btn">Importar certificado</button>
      </>}
      cheats={[
        { keys:["⌘","K"], label:"buscar" },
        { keys:["?"],     label:"atalhos" },
      ]}
    >
      <div className="fx-cfg-grid">
        <div className="fx-card">
          <h3>Certificado digital</h3>
          <p className="lead">A1 instalado em MemCofre. SEFAZ exige renovação anual.</p>
          <div className="fx-cert">
            <div className="fx-cert-top">
              <div className="fx-cert-ic"><Ic name="shield" size={22}/></div>
              <div className="fx-cert-info">
                <b>{c.arquivo}</b>
                <small>Tipo {c.tipo} · {c.emissor}</small>
                <small>{c.titular} · {c.cnpj}</small>
              </div>
            </div>
            <dl className="fx-cert-validade">
              <div><dt>Emitido</dt><dd>{c.emitidoEm}</dd></div>
              <div><dt>Válido até</dt><dd>{c.validade}</dd></div>
              <div><dt>Restam</dt><dd style={{ color:"oklch(0.45 0.12 60)" }}>{c.diasRestantes} dias</dd></div>
            </dl>
            <div className="fx-cert-bar"><div style={{ width:`${validPct}%` }}/></div>
            <dl className="fx-kv" style={{ gridTemplateColumns:"120px 1fr", marginTop:6 }}>
              <dt>Senha</dt><dd><code>{c.senhaCofre}</code></dd>
              <dt>Auto-renovação</dt><dd>{c.autoRenovar ? "Sim" : "Não · agendar"}</dd>
            </dl>
            <div style={{ display:"flex", gap:6, marginTop:8 }}>
              <button className="fx-btn warn">Renovar certificado</button>
              <button className="fx-btn ghost">Trocar para A3 (token)</button>
            </div>
          </div>
        </div>

        <div className="fx-card">
          <h3>Ambiente, séries e regime</h3>
          <p className="lead">Produção × homologação, numeração ativa, regime tributário.</p>

          <h4 className="fx-card-sub">Ambiente</h4>
          <dl className="fx-kv">
            <dt>NF-e / NFC-e</dt>
            <dd>
              <span className="fx-amb">{cfg.ambiente.nfe.atual}</span>
              <small className="fx-amb-test">último teste homolog: {cfg.ambiente.nfe.homologUltimoTeste}</small>
            </dd>
            <dt>NFS-e</dt>
            <dd>
              <span className="fx-amb">{cfg.ambiente.nfse.atual}</span>
              <small className="fx-amb-test">último teste homolog: {cfg.ambiente.nfse.homologUltimoTeste}</small>
            </dd>
          </dl>

          <h4 className="fx-card-sub">Séries ativas</h4>
          <div className="fx-table" style={{ margin: 0 }}>
            <table>
              <thead>
                <tr><th>Modelo</th><th>Série</th><th>Próx. nº</th><th>Filial</th><th>Estado</th></tr>
              </thead>
              <tbody>
                {cfg.series.map((s,i) => (
                  <tr key={i}>
                    <td><b>{s.modelo}</b> ({s.modelo === 65 ? "NFC-e" : "NF-e"})</td>
                    <td><code className="fx-ch">série {s.serie}</code></td>
                    <td className="num">{s.proxima}</td>
                    <td>{s.filial}</td>
                    <td>
                      {s.ativo
                        ? <span className="fx-env ok">ativa</span>
                        : <span className="fx-env warn">inativa</span>}
                      {s.obs && <div className="mut" style={{ fontSize:10.5 }}>{s.obs}</div>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <h4 className="fx-card-sub">Regime tributário</h4>
          <dl className="fx-kv">
            <dt>Regime</dt><dd><b>{cfg.regime}</b></dd>
            <dt>Regras ativas</dt><dd>
              <ul style={{ margin:0, paddingLeft:18, fontSize:12, lineHeight:1.7 }}>
                {cfg.regimeAcoes.map((r,i) => <li key={i}>{r}</li>)}
              </ul>
            </dd>
            <dt>NFS-e SP</dt><dd>{cfg.nfse.tomadorMunicipal}</dd>
          </dl>
        </div>
      </div>
    </FxShell>
  );
}

/* ╔════════════════════════════════════════════════════════════════╗
   ║ 14) PÁGINA 7 · SPED & LIVROS                                   ║
   ╚════════════════════════════════════════════════════════════════╝ */
function FiscalSpedPage({ onNavigate }) {
  return (
    <FxShell
      route="sped"
      onNavigate={onNavigate}
      title="SPED & livros fiscais"
      crumb="EFD ICMS/IPI · PIS/COFINS · ECF · conciliação SEFAZ × ERP"
      env="0 divergências · abril"
      envTone="ok"
      actions={<>
        <button className="fx-btn ghost">Validar com PVA</button>
        <button className="fx-btn primary">Exportar competência</button>
      </>}
      cheats={[
        { keys:["⌘","K"], label:"buscar" },
        { keys:["?"],     label:"atalhos" },
      ]}
    >
      <div className="fx-kpis fx-kpis-4">
        <div className="fx-kpi hero"><small>Competência</small><b>05 / 2026</b><span className="delta">prazo 15/06</span></div>
        <div className="fx-kpi ok"><small>Última entregue</small><b>03 / 2026</b><span className="delta">protoc. 2026.0341.8821</span></div>
        <div className="fx-kpi"><small>Conciliação SEFAZ × ERP</small><b>0 divergências</b><span className="delta">178 / 178 abril</span></div>
        <div className="fx-kpi warn"><small>Atenção</small><b>ECF</b><span className="delta">prazo julho</span></div>
      </div>

      <div className="fx-table">
        <table>
          <thead>
            <tr>
              <th style={{ width:100 }}>Competência</th>
              <th style={{ width:130 }}>Status</th>
              <th>EFD ICMS/IPI</th>
              <th>PIS/COFINS</th>
              <th>ECF</th>
              <th>Observação</th>
            </tr>
          </thead>
          <tbody>
            {D.SPED_PERIODOS.map(p => (
              <tr key={p.mes}>
                <td><b>{p.mes}</b></td>
                <td><span className={"fx-sped-status " + p.status}>{p.status}</span></td>
                <td className="mut">{p.icms || "—"}</td>
                <td className="mut">{p.pis || "—"}</td>
                <td className="mut">{p.ecf || "—"}</td>
                <td className="mut">{p.obs}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="fx-sub-h">Livros · maio (parcial)</div>

      <div className="fx-table">
        <table>
          <thead>
            <tr>
              <th>Livro</th>
              <th style={{ width:130 }}>Período</th>
              <th className="num">Saídas / Base</th>
              <th className="num">Entradas / Imposto</th>
              <th className="num">Saldo</th>
              <th style={{ width:110 }}>Status</th>
            </tr>
          </thead>
          <tbody>
            {D.LIVROS.map(l => (
              <tr key={l.id}>
                <td><b>{l.nome}</b></td>
                <td className="mut">{l.periodo}</td>
                <td className="num">{l.saidas != null ? brl(l.saidas) : l.base != null ? brl(l.base) : (l.notasErp != null ? `${l.notasErp} notas` : "—")}</td>
                <td className="num">{l.entradas != null ? brl(l.entradas) : l.iss != null ? brl(l.iss) : (l.notasSefaz != null ? `${l.notasSefaz} SEFAZ` : "—")}</td>
                <td className="num">{l.saldo != null ? brl(l.saldo) : (l.divergencias != null ? `${l.divergencias} diverg.` : "—")}</td>
                <td>
                  {l.status === "parcial" && <span className="fx-sped-status aberto">parcial</span>}
                  {l.status === "ok"      && <span className="fx-sped-status entregue">ok</span>}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </FxShell>
  );
}

/* ╔════════════════════════════════════════════════════════════════╗
   ║ 15) EXPOR                                                       ║
   ╚════════════════════════════════════════════════════════════════╝ */
window.FiscalCockpit     = FiscalCockpit;
window.FiscalNFePage     = FiscalNFePage;
window.FiscalNFSePage    = FiscalNFSePage;
window.FiscalDFePage     = FiscalDFePage;
window.FiscalEventosPage = FiscalEventosPage;
window.FiscalConfigPage  = FiscalConfigPage;
window.FiscalSpedPage    = FiscalSpedPage;

})();
