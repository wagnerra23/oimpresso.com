// kb-paths.jsx — Subcategorias + Comentários inline + Trilhas de aprendizado
// Exporta: KB_SUBCATS, kbDeriveSub, KB_PATHS, KBPathsDialog, KBCommentBlock, KBCommentsCount, kbAllCommentsCount
(() => {
const { useState, useEffect, useMemo } = React;

// ─────────────────────────────────────────────────────────────────
// SUBCATEGORIAS — uma camada abaixo das categorias
// ─────────────────────────────────────────────────────────────────
const KB_SUBCATS = {
  equip: [
    { id: "roland",   label: "Roland VS-540",     match: a => a.equip === "Roland VS-540" },
    { id: "latex",    label: "HP Latex 365",      match: a => a.equip === "HP Latex 365" },
    { id: "plotter",  label: "Plotter Graphtec",  match: a => a.equip === "Plotter Graphtec" },
    { id: "lamina",   label: "Laminadora Royal",  match: a => a.equip === "Laminadora Royal" },
    { id: "outros",   label: "Outros equipamentos", match: a => false },
  ],
  producao: [
    { id: "impressao",  label: "Impressão",   match: a => (a.tags || []).some(t => /impress|tinta|cor|icc/i.test(t)) },
    { id: "acabamento", label: "Acabamento",  match: a => (a.tags || []).some(t => /acab|lamin|recorte/i.test(t)) },
    { id: "expedicao",  label: "Expedição",   match: a => (a.tags || []).some(t => /motoboy|romaneio|expedi/i.test(t)) },
  ],
  arte: [
    { id: "pdf",      label: "PDF & fontes",     match: a => (a.tags || []).some(t => /pdf|fonte/i.test(t)) },
    { id: "medida",   label: "Medidas & sangria", match: a => (a.tags || []).some(t => /sangria|medida|illustrator|banner/i.test(t)) },
  ],
  atendim: [
    { id: "brief",      label: "Brief & abertura", match: a => (a.tags || []).some(t => /brief|os|atend/i.test(t)) },
    { id: "aprovacao",  label: "Aprovação",        match: a => (a.tags || []).some(t => /aprovaç|arte|fluxo/i.test(t)) },
    { id: "comunic",    label: "Comunicação",      match: a => (a.tags || []).some(t => /comunic|cliente|atraso/i.test(t)) },
  ],
  fiscal: [
    { id: "nf",        label: "Notas fiscais",  match: a => (a.tags || []).some(t => /nf-e|nfs-e|sefaz|rps/i.test(t)) },
    { id: "cobranca",  label: "Cobrança",       match: a => (a.tags || []).some(t => /boleto|inter|cobrança/i.test(t)) },
  ],
  sistema: [
    { id: "atalhos",   label: "Atalhos",       match: a => (a.tags || []).some(t => /atalho|teclado/i.test(t)) },
    { id: "config",    label: "Configurações", match: a => (a.tags || []).some(t => /multi-empresa|config|filtro/i.test(t)) },
    { id: "uso",       label: "Uso geral",     match: a => (a.tags || []).some(t => /custo|margem|os/i.test(t)) },
  ],
  rh: [
    { id: "ponto",     label: "Ponto WR2",     match: a => (a.tags || []).some(t => /ponto|wr2/i.test(t)) },
  ],
};

function kbDeriveSub(article) {
  const subs = KB_SUBCATS[article.cat];
  if (!subs) return null;
  const hit = subs.find(s => s.match(article));
  return hit ? hit.id : null;
}

window.KB_SUBCATS = KB_SUBCATS;
window.kbDeriveSub = kbDeriveSub;

// ─────────────────────────────────────────────────────────────────
// TRILHAS DE APRENDIZADO
// ─────────────────────────────────────────────────────────────────
const KB_PATHS = [
  {
    id: "onb-balcao",
    title: "Onboarding do Balcão",
    audience: "Larissa · primeiro mês",
    desc: "Domínio mínimo para atender no balcão da ROTA LIVRE sem supervisão.",
    hue: 145,
    steps: [
      { articleId: "a7",  type: "leitura",   note: "Brief mínimo — base de tudo" },
      { articleId: "a11", type: "leitura",   note: "Atalhos de teclado essenciais" },
      { articleId: "a8",  type: "leitura",   note: "Quando uma arte está pronta para produção" },
      { articleId: "a13", type: "pratica",   note: "Avisar atraso — pratique uma vez com colega" },
      { articleId: "a5",  type: "leitura",   note: "Como conferir PDF fechado do cliente" },
      { articleId: "a14", type: "leitura",   note: "Fechar margem real lançando custos" },
    ],
  },
  {
    id: "manut-tecnico",
    title: "Manutenção semanal — Técnico",
    audience: "Mateus PCP · toda segunda",
    desc: "Rotinas de produção que evitam quebra de máquina (R$ [redacted Tier 0] reposição da cabeça Roland).",
    hue: 30,
    steps: [
      { articleId: "a3",  type: "leitura",  note: "Limpeza diária da Roland — obrigatório" },
      { articleId: "a1",  type: "leitura",  note: "Calibragem ICC quando trocar bobina" },
      { articleId: "a2",  type: "leitura",  note: "Troca de bobina HP Latex sem desperdício" },
      { articleId: "a6",  type: "leitura",  note: "Plotter Graphtec — pressão e offset" },
      { articleId: "a12", type: "leitura",  note: "Quando usar laminação fosca vs brilho" },
    ],
  },
  {
    id: "emerg-fiscal",
    title: "Emergência fiscal",
    audience: "Eliana Fin. · quando dá problema",
    desc: "Rejeição SEFAZ, boleto travado, NF que não emite — o que olhar primeiro.",
    hue: 60,
    steps: [
      { articleId: "a9",  type: "leitura", note: "Códigos de rejeição SEFAZ mais comuns" },
      { articleId: "a10", type: "leitura", note: "Boleto Inter — fluxo e por que falha" },
      { articleId: "a16", type: "leitura", note: "RPS lote vs síncrono (NFS-e)" },
    ],
  },
];

window.KB_PATHS = KB_PATHS;

// ─────────────────────────────────────────────────────────────────
// PATHS DIALOG — drawer lateral com progresso
// ─────────────────────────────────────────────────────────────────
function KBPathsDialog({ articles, onPick, onClose }) {
  const [activePath, setActivePath] = useState(null);
  const [progress, setProgress] = useState(() => {
    try { return JSON.parse(localStorage.getItem("oimpresso.kb.paths") || "{}"); }
    catch (e) { return {}; }
  });

  useEffect(() => {
    try { localStorage.setItem("oimpresso.kb.paths", JSON.stringify(progress)); } catch (e) {}
  }, [progress]);

  const togglePath = (pathId, stepIdx) => {
    setProgress(p => {
      const cur = p[pathId] || {};
      return { ...p, [pathId]: { ...cur, [stepIdx]: !cur[stepIdx] } };
    });
  };

  const pathProgress = (path) => {
    const done = (progress[path.id] || {});
    const c = Object.values(done).filter(Boolean).length;
    return { done: c, total: path.steps.length, pct: Math.round((c / path.steps.length) * 100) };
  };

  if (activePath) {
    const path = KB_PATHS.find(p => p.id === activePath);
    const done = progress[path.id] || {};
    const p = pathProgress(path);
    return (
      <React.Fragment>
        <div className="kb-modal-back" onClick={onClose}/>
        <aside className="kb-paths-drawer">
          <header className="kb-paths-h">
            <button className="kb-paths-back" onClick={() => setActivePath(null)}>‹ Trilhas</button>
            <button className="kb-x" onClick={onClose}>×</button>
          </header>
          <div className="kb-paths-detail">
            <div className="kb-paths-detail-hero" style={{ background: `oklch(0.96 0.04 ${path.hue})` }}>
              <small style={{ color: `oklch(0.36 0.13 ${path.hue})` }}>Trilha · {path.audience}</small>
              <h2>{path.title}</h2>
              <p>{path.desc}</p>
              <div className="kb-paths-progress">
                <div className="kb-paths-pbar">
                  <div className="kb-paths-pfill" style={{ width: p.pct + "%", background: `oklch(0.52 0.13 ${path.hue})` }}/>
                </div>
                <span className="mono">{p.done}/{p.total} · {p.pct}%</span>
              </div>
            </div>

            <ol className="kb-paths-steps">
              {path.steps.map((step, i) => {
                const a = articles.find(x => x.id === step.articleId);
                if (!a) return null;
                const ok = !!done[i];
                return (
                  <li key={i} className={ok ? "done" : ""}>
                    <button
                      className="kb-paths-check"
                      onClick={() => togglePath(path.id, i)}
                      aria-pressed={ok}>
                      {ok ? "✓" : (i + 1)}
                    </button>
                    <div className="kb-paths-step-c">
                      <button className="kb-paths-step-title" onClick={() => { onPick(a.id); onClose(); }}>
                        <b>{a.title}</b>
                        <span className="kb-paths-step-tag">{step.type}</span>
                      </button>
                      <p>{step.note}</p>
                      <div className="kb-paths-step-meta">
                        {a.readTime} min · {a.author} · {a.updated}
                      </div>
                    </div>
                  </li>
                );
              })}
            </ol>
          </div>
        </aside>
      </React.Fragment>
    );
  }

  return (
    <React.Fragment>
      <div className="kb-modal-back" onClick={onClose}/>
      <aside className="kb-paths-drawer">
        <header className="kb-paths-h">
          <div>
            <small>Aprendizado guiado</small>
            <h3>Trilhas de conhecimento</h3>
          </div>
          <button className="kb-x" onClick={onClose}>×</button>
        </header>
        <div className="kb-paths-list">
          {KB_PATHS.map(path => {
            const p = pathProgress(path);
            return (
              <button key={path.id} className="kb-paths-card" onClick={() => setActivePath(path.id)}
                      style={{ borderLeftColor: `oklch(0.52 0.13 ${path.hue})` }}>
                <small style={{ color: `oklch(0.42 0.13 ${path.hue})` }}>{path.audience}</small>
                <h4>{path.title}</h4>
                <p>{path.desc}</p>
                <div className="kb-paths-progress">
                  <div className="kb-paths-pbar">
                    <div className="kb-paths-pfill" style={{ width: p.pct + "%", background: `oklch(0.52 0.13 ${path.hue})` }}/>
                  </div>
                  <span className="mono">{p.done}/{p.total}</span>
                </div>
              </button>
            );
          })}
        </div>
        <footer className="kb-paths-foot">
          <small>Trilhas são pré-definidas pelo time. Progresso salvo localmente por dispositivo.</small>
        </footer>
      </aside>
    </React.Fragment>
  );
}

window.KBPathsDialog = KBPathsDialog;

// ─────────────────────────────────────────────────────────────────
// COMENTÁRIOS INLINE — render auxiliar
// ─────────────────────────────────────────────────────────────────
// Storage: window.__kbComments = { articleId: { blockIdx: [ {author, text, when} ] } }
// Persistido em localStorage.

function loadComments() {
  try { return JSON.parse(localStorage.getItem("oimpresso.kb.comments") || "{}"); }
  catch (e) { return {}; }
}
function saveComments(m) {
  try { localStorage.setItem("oimpresso.kb.comments", JSON.stringify(m)); } catch (e) {}
}

// Hook simples pra usar dentro do KBPage
function useKBComments() {
  const [m, setM] = useState(loadComments);
  useEffect(() => { saveComments(m); }, [m]);

  const addComment = (articleId, blockIdx, text, author) => {
    setM(prev => {
      const art = prev[articleId] || {};
      const blk = art[blockIdx] || [];
      const next = { ...art, [blockIdx]: [...blk, { author: author || "você", text, when: "agora" }] };
      return { ...prev, [articleId]: next };
    });
  };

  const removeComment = (articleId, blockIdx, commentIdx) => {
    setM(prev => {
      const art = prev[articleId] || {};
      const blk = art[blockIdx] || [];
      const nextBlk = blk.filter((_, i) => i !== commentIdx);
      const next = nextBlk.length ? { ...art, [blockIdx]: nextBlk } : { ...art };
      if (!nextBlk.length) delete next[blockIdx];
      return { ...prev, [articleId]: next };
    });
  };

  const countFor = (articleId) => {
    const art = m[articleId] || {};
    return Object.values(art).reduce((s, b) => s + b.length, 0);
  };

  return { commentsMap: m, addComment, removeComment, countFor };
}

window.useKBComments = useKBComments;

// Componente que renderiza UM bloco + comentários + input inline
function KBCommentBlock({ articleId, blockIdx, comments, onAdd, onRemove, children }) {
  const [open, setOpen] = useState(false);
  const [text, setText] = useState("");
  const list = comments || [];

  const submit = () => {
    const t = text.trim();
    if (!t) return;
    onAdd(t);
    setText("");
    setOpen(false);
  };

  return (
    <div className="kb-block-host">
      <div className="kb-block-content">{children}</div>
      <button
        className="kb-block-add"
        onClick={() => setOpen(o => !o)}
        title="Comentar este parágrafo">
        {open ? "×" : "+"}
      </button>

      {(list.length > 0 || open) && (
        <div className="kb-comments">
          {list.map((c, i) => (
            <div key={i} className="kb-comment">
              <div className="kb-comment-h">
                <b>{c.author}</b>
                <span className="kb-comment-when">{c.when}</span>
                <button className="kb-comment-del" onClick={() => onRemove(i)} title="Remover">×</button>
              </div>
              <p>{c.text}</p>
            </div>
          ))}
          {open && (
            <div className="kb-comment-new">
              <textarea
                autoFocus
                value={text}
                onChange={e => setText(e.target.value)}
                onKeyDown={e => { if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) { e.preventDefault(); submit(); } }}
                placeholder="Comentar este parágrafo... (⌘↵ envia)"
                rows={2}/>
              <div className="kb-comment-new-row">
                <small>⌘↵ enviar · esc cancelar</small>
                <button className="os-btn ghost" onClick={() => { setOpen(false); setText(""); }}>Cancelar</button>
                <button className="os-btn primary" onClick={submit} disabled={!text.trim()}>Comentar</button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

window.KBCommentBlock = KBCommentBlock;

})();
