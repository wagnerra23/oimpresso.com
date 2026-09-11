// inbox-v2-out.jsx — Refino #4 da Caixa Unificada (Distribuição/Saída)
// Transcript PDF · Modo apresentação · Lightbox mídia · Variáveis live no composer
(() => {
const { useState, useEffect, useMemo, useRef } = React;

// ─────────────────────────────────────────────────────────────────
// VARIÁVEIS LIVE NO COMPOSER
// Substitui {{nome}}, {{empresa}}, {{os}}, {{saldo}}, {{lastTouch}}
// ─────────────────────────────────────────────────────────────────
function resolveVars(text, conv) {
  if (!text || !conv) return text;
  const map = {
    nome: conv.name || "",
    empresa: conv.company || "",
    os: conv.ctx?.os || "",
    saldo: conv.ctx?.saldo || "",
    handle: conv.handle || "",
    operador: "você", // placeholder — pode vir do current user
  };
  return text.replace(/\{\{(\w+)\}\}/g, (_, key) => {
    const v = map[key.toLowerCase()];
    return v !== undefined ? v : `{{${key}}}`;
  });
}
window.resolveVars = resolveVars;

// Detecta variáveis no draft pra mostrar preview
function ComposerVarPreview({ draft, conv }) {
  if (!draft || !/\{\{\w+\}\}/.test(draft)) return null;
  const resolved = resolveVars(draft, conv);
  // Highlight de variáveis preenchidas (em verde) e faltantes (em vermelho)
  const parts = [];
  let lastIdx = 0;
  const re = /\{\{(\w+)\}\}/g;
  let m, idx = 0;
  const map = {
    nome: conv.name, empresa: conv.company,
    os: conv.ctx?.os, saldo: conv.ctx?.saldo,
    handle: conv.handle, operador: "você",
  };
  while ((m = re.exec(draft)) !== null) {
    if (m.index > lastIdx) parts.push({ k: "txt", t: draft.slice(lastIdx, m.index) });
    const key = m[1].toLowerCase();
    const v = map[key];
    parts.push({ k: v ? "ok" : "miss", key: m[1], val: v });
    lastIdx = m.index + m[0].length;
  }
  if (lastIdx < draft.length) parts.push({ k: "txt", t: draft.slice(lastIdx) });

  return (
    <div className="om-var-preview">
      <small>Preview</small>
      <div className="om-var-preview-text">
        {parts.map((p, i) => {
          if (p.k === "txt") return <span key={i}>{p.t}</span>;
          if (p.k === "ok")  return <span key={i} className="om-var-pill ok" title={`{{${p.key}}}`}>{p.val}</span>;
          return <span key={i} className="om-var-pill miss" title={`Variável {{${p.key}}} não tem valor`}>{`{{${p.key}}}`}</span>;
        })}
      </div>
    </div>
  );
}
window.ComposerVarPreview = ComposerVarPreview;

// Botão de inserção de variáveis (popover acima do composer)
function VarMenu({ onInsert, onClose }) {
  const vars = [
    { key: "nome",      label: "Nome do cliente" },
    { key: "empresa",   label: "Empresa" },
    { key: "os",        label: "OS vinculada" },
    { key: "saldo",     label: "Saldo a receber" },
    { key: "handle",    label: "Telefone/contato" },
    { key: "operador",  label: "Seu nome" },
  ];
  return (
    <React.Fragment>
      <div className="om-palette-back" onClick={onClose}/>
      <div className="om-var-menu" role="dialog">
        <header>
          <small>Inserir variável</small>
        </header>
        <ul>
          {vars.map(v => (
            <li key={v.key}>
              <button onClick={() => { onInsert("{{" + v.key + "}}"); onClose(); }}>
                <span className="mono">{`{{${v.key}}}`}</span>
                <span className="om-var-label">{v.label}</span>
              </button>
            </li>
          ))}
        </ul>
        <footer>
          <small>Atalho: digite <span className="om-kbd">{`{{`}</span> no input</small>
        </footer>
      </div>
    </React.Fragment>
  );
}
window.VarMenu = VarMenu;

// ─────────────────────────────────────────────────────────────────
// TRANSCRIPT — Imprimir conversa em PDF com header Oimpresso
// (Reuse do pattern KBPrintSOP)
// ─────────────────────────────────────────────────────────────────
function InboxTranscriptDialog({ conv, queue, channel, account, onClose }) {
  const today = new Date().toLocaleDateString("pt-BR", { day:"2-digit", month:"2-digit", year:"numeric" });

  useEffect(() => {
    document.body.classList.add("kb-printing");
    return () => document.body.classList.remove("kb-printing");
  }, []);

  if (!conv) return null;

  return (
    <React.Fragment>
      <div className="om-palette-back" onClick={onClose}/>
      <div className="om-print-modal" role="dialog">
        <header className="om-print-modal-h kb-no-print">
          <div>
            <small>Transcript de conversa — modo jurídico/auditoria</small>
            <h3>Pré-visualização</h3>
          </div>
          <button className="om-x" onClick={onClose}>✕</button>
        </header>

        <div className="om-print-body">
          <div className="om-print-sheet">
            <header className="om-print-head">
              <div className="om-print-brand">
                <div className="om-print-logo">OI</div>
                <div>
                  <b>Oimpresso ERP</b>
                  <small>Transcript de Atendimento</small>
                </div>
              </div>
              <div className="om-print-id">
                <small>Conversa</small>
                <b className="mono">#{conv.id}</b>
              </div>
            </header>

            <div className="om-print-title-wrap">
              <h1 className="om-print-title">{conv.name}{conv.company ? ` · ${conv.company}` : ""}</h1>
              <div className="om-print-meta">
                <span><b>Canal:</b> {channel?.label}</span>
                <span><b>Conta:</b> {account?.label}</span>
                <span><b>Telefone:</b> {conv.handle}</span>
                <span><b>Fila:</b> {queue?.label}</span>
                <span><b>Status:</b> {conv.status}</span>
                <span><b>Mensagens:</b> {conv.msgs.length}</span>
              </div>
              {conv.ctx?.os && (
                <div className="om-print-meta" style={{marginTop:6}}>
                  <span><b>OS vinculada:</b> {conv.ctx.os}</span>
                  <span><b>Saldo:</b> {conv.ctx.saldo}</span>
                </div>
              )}
            </div>

            <div className="om-print-thread">
              {conv.msgs.map((m, i) => {
                const who = m.internal ? "NOTA INTERNA" : m.who === "me" ? "Atendente" : conv.name;
                return (
                  <div key={i} className={"om-print-msg " + (m.internal ? "internal" : m.who)}>
                    <div className="om-print-msg-h">
                      <b>{who}</b>
                      <span className="mono">{m.d === "hoje" ? "Hoje" : m.d === "ontem" ? "Ontem" : m.d} · {m.time}</span>
                    </div>
                    <p>{m.t}</p>
                  </div>
                );
              })}
            </div>

            <footer className="om-print-foot">
              <span>Transcript impresso em {today}</span>
              <span>Oimpresso ERP · Caixa Unificada</span>
              <span>Página <span className="mono">1</span></span>
            </footer>
          </div>
        </div>

        <footer className="om-print-actions kb-no-print">
          <small>Documento com header Oimpresso pra processo trabalhista, compliance LGPD ou auditoria fiscal.</small>
          <div style={{display:"flex", gap:8}}>
            <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
            <button className="os-btn primary" onClick={() => window.print()}>Imprimir / Salvar PDF</button>
          </div>
        </footer>
      </div>
    </React.Fragment>
  );
}
window.InboxTranscriptDialog = InboxTranscriptDialog;

// ─────────────────────────────────────────────────────────────────
// MODO APRESENTAÇÃO — read-only fullscreen do thread (reuniões/screenshare)
// Esconde IDs internos, foco no conteúdo. Esc fecha.
// ─────────────────────────────────────────────────────────────────
function InboxPresenterMode({ conv, channel, account, onClose }) {
  useEffect(() => {
    const onKey = (e) => { if (e.key === "Escape") onClose(); };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);

  if (!conv) return null;

  return (
    <div className="om-presenter" role="dialog">
      <header className="om-presenter-h">
        <div>
          <small>Modo apresentação · read-only</small>
          <h2>{conv.name}{conv.company ? ` · ${conv.company}` : ""}</h2>
          <p>{channel?.short} · {account?.label}</p>
        </div>
        <button className="om-presenter-exit" onClick={onClose} title="Sair (Esc)">Sair</button>
      </header>
      <div className="om-presenter-stage">
        <div className="om-presenter-thread">
          {conv.msgs.filter(m => !m.internal).map((m, i) => (
            <div key={i} className={"om-presenter-msg " + m.who}>
              <div className="om-presenter-bub">
                <p>{m.t}</p>
                <small>{m.time}</small>
              </div>
            </div>
          ))}
        </div>
      </div>
      <footer className="om-presenter-foot">
        <small>Notas internas e dados internos do ERP foram ocultados. Tecle <span className="om-kbd">Esc</span> pra sair.</small>
      </footer>
    </div>
  );
}
window.InboxPresenterMode = InboxPresenterMode;

// ─────────────────────────────────────────────────────────────────
// MEDIA LIGHTBOX — placeholder, simula mídia recebida
// ─────────────────────────────────────────────────────────────────
function InboxMediaLightbox({ media, onClose }) {
  useEffect(() => {
    const onKey = (e) => { if (e.key === "Escape") onClose(); };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);

  if (!media) return null;

  return (
    <div className="om-lightbox" onClick={onClose} role="dialog">
      <button className="om-lightbox-close" onClick={onClose}>✕</button>
      <figure className="om-lightbox-frame" onClick={(e) => e.stopPropagation()}>
        {media.kind === "image" ? (
          <img src={media.src} alt={media.caption || ""}/>
        ) : (
          <div className="om-lightbox-placeholder">
            <span className="om-lightbox-ico">{media.kind === "pdf" ? "PDF" : media.kind === "video" ? "▶" : "?"}</span>
            <b>{media.name}</b>
            <small>{media.size}</small>
          </div>
        )}
        <figcaption>
          <span>{media.caption || media.name}</span>
          <a href={media.src} download={media.name} onClick={(e) => e.stopPropagation()}>
            ↓ Baixar
          </a>
        </figcaption>
      </figure>
    </div>
  );
}
window.InboxMediaLightbox = InboxMediaLightbox;

})();
