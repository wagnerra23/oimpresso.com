// kb-extras.jsx — extensões do módulo KB
// AI dialog (Resumir/Perguntar), Block editor, Related articles
(() => {
const { useState, useEffect, useMemo, useRef } = React;

// ─── Markdown leve pra renderizar respostas da IA ───────────────
function mdInline(s) {
  return s
    .replace(/\*\*([^*]+)\*\*/g, "<b>$1</b>")
    .replace(/`([^`]+)`/g, "<code>$1</code>")
    .replace(/\[(a\d+|t\d+)\]/g, '<span class="kb-ai-cite">[$1]</span>');
}
function renderMD(text) {
  if (!text) return "";
  const esc = (s) => s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
  const lines = esc(text).split("\n").filter(l => l.trim() !== "");
  let html = "", inList = false;
  for (const ln of lines) {
    const t = ln.trim();
    if (/^[-*•]\s+/.test(t)) {
      if (!inList) { html += "<ul>"; inList = true; }
      html += `<li>${mdInline(t.replace(/^[-*•]\s+/, ""))}</li>`;
    } else {
      if (inList) { html += "</ul>"; inList = false; }
      html += `<p>${mdInline(t)}</p>`;
    }
  }
  if (inList) html += "</ul>";
  return html;
}

// ─── Texto do artigo p/ prompt ──────────────────────────────────
function articleToText(a) {
  return a.body.map(b => {
    if (b.kind === "para") return b.t;
    if (b.kind === "h2") return "## " + b.t;
    if (b.kind === "list") return b.items.map(i => "- " + i).join("\n");
    if (b.kind === "callout") return "> " + (b.tone || "info").toUpperCase() + ": " + b.t;
    return "";
  }).join("\n\n");
}

async function summarizeArticle(article) {
  const text = articleToText(article);
  return await window.claude.complete(
`Resuma este artigo da base de conhecimento de uma gráfica em 3 bullet points objetivos, em português brasileiro, focando no que o operador do balcão precisa lembrar na prática. Sem floreio.

ARTIGO: ${article.title}

${text}

FORMATO:
- bullet 1
- bullet 2
- bullet 3`);
}

async function askKB(question, articles) {
  const corpus = articles.map(a =>
    `[${a.id}] ${a.title} (${a.cat}) — ${a.excerpt}\n${articleToText(a).slice(0, 360)}`
  ).join("\n\n---\n\n");

  return await window.claude.complete(
`Você é a IA da base de conhecimento da gráfica Oimpresso. Use APENAS os artigos abaixo. Se a resposta não estiver lá, diga claramente "Não encontrei isso no KB — talvez seja um artigo novo a criar." Seja conciso, prático, em português brasileiro. Sempre cite os IDs dos artigos usados entre [colchetes] ao final.

ARTIGOS DISPONÍVEIS:
${corpus}

PERGUNTA: ${question}

RESPOSTA (3-6 frases + citações):`);
}

// ─── AI Dialog ──────────────────────────────────────────────────
function KBAIDialog({ mode, article, articles, onClose }) {
  const [query, setQuery] = useState("");
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState("");
  const [err, setErr] = useState(null);
  const [history, setHistory] = useState([]);
  const inputRef = useRef(null);

  useEffect(() => {
    if (mode === "summarize" && article) run();
    if (mode === "ask") setTimeout(() => inputRef.current && inputRef.current.focus(), 80);
  }, []);

  const run = async (q) => {
    setLoading(true); setErr(null); setResult("");
    try {
      let answer;
      if (mode === "summarize") {
        answer = await summarizeArticle(article);
      } else {
        const question = q || query;
        if (!question.trim()) { setLoading(false); return; }
        answer = await askKB(question, articles);
        setHistory(h => [...h, { q: question, a: answer }]);
        setQuery("");
      }
      setResult(answer);
    } catch (e) {
      setErr((e && e.message) || "Falha ao consultar IA. Tente novamente.");
    } finally {
      setLoading(false);
    }
  };

  const onKey = (e) => {
    if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); run(); }
  };

  const title = mode === "summarize" ? "Resumo do artigo" : "Perguntar ao KB";
  const subtitle = mode === "summarize"
    ? (article && article.title)
    : "A resposta usa apenas artigos publicados. Citações entre [colchetes].";

  return (
    <React.Fragment>
      <div className="kb-modal-back" onClick={onClose}/>
      <div className="kb-modal kb-ai" role="dialog">
        <header className="kb-modal-h">
          <div>
            <small>IA · {mode === "summarize" ? "resumir artigo" : "consultar KB"}</small>
            <h3>{title}</h3>
            {subtitle && <p className="kb-ai-sub">{subtitle}</p>}
          </div>
          <button className="kb-x" onClick={onClose}>×</button>
        </header>

        <div className="kb-ai-body">
          {mode === "ask" && history.length > 0 && (
            <div className="kb-ai-history">
              {history.map((h, i) => (
                <div key={i} className="kb-ai-turn">
                  <div className="kb-ai-q"><b>Você:</b> {h.q}</div>
                  <div className="kb-ai-a" dangerouslySetInnerHTML={{__html: renderMD(h.a)}}/>
                </div>
              ))}
            </div>
          )}

          {loading && (
            <div className="kb-ai-loading">
              <div className="kb-ai-dots"><span></span><span></span><span></span></div>
              <small>Consultando IA com base no KB...</small>
            </div>
          )}

          {!loading && mode === "summarize" && result && (
            <div className="kb-ai-result">
              <div className="kb-ai-a" dangerouslySetInnerHTML={{__html: renderMD(result)}}/>
            </div>
          )}

          {!loading && err && <div className="kb-ai-err">{err}</div>}
        </div>

        {mode === "ask" && (
          <div className="kb-ai-input">
            <textarea
              ref={inputRef}
              value={query}
              onChange={e => setQuery(e.target.value)}
              onKeyDown={onKey}
              placeholder="Ex.: como calibrar ICC quando trocar bobina nova?"
              rows={2}/>
            <button
              className="os-btn primary"
              disabled={loading || !query.trim()}
              onClick={() => run()}>
              {loading ? "..." : "Perguntar"}
            </button>
          </div>
        )}

        <footer className="kb-ai-foot">
          <small>
            {mode === "summarize"
              ? "Resumo gerado por IA. Confirme no artigo original."
              : "IA pode errar. Confirme nas citações."}
          </small>
        </footer>
      </div>
    </React.Fragment>
  );
}

// ─── Related — top-3 artigos relacionados por tag overlap + categoria ───
function relatedArticles(article, all, n = 3) {
  if (!article) return [];
  const tagsA = new Set(article.tags || []);
  return all
    .filter(b => b.id !== article.id)
    .map(b => {
      const tagsB = new Set(b.tags || []);
      let overlap = 0;
      tagsA.forEach(t => { if (tagsB.has(t)) overlap++; });
      const catBonus = b.cat === article.cat ? 1.5 : 0;
      const equipBonus = (article.equip && b.equip === article.equip && b.equip !== "—") ? 1 : 0;
      return { a: b, score: overlap * 2 + catBonus + equipBonus };
    })
    .filter(x => x.score > 0)
    .sort((x, y) => y.score - x.score)
    .slice(0, n)
    .map(x => x.a);
}

function KBRelated({ article, articles, onPick }) {
  const items = useMemo(() => relatedArticles(article, articles, 3), [article, articles]);
  if (items.length === 0) return null;
  return (
    <div className="kb-related">
      <small>Relacionados</small>
      <ul>
        {items.map(a => (
          <li key={a.id}>
            <button onClick={() => onPick(a.id)}>
              <span className="kb-rel-t">{a.title}</span>
              <span className="kb-rel-m">{a.readTime} min · {a.author}</span>
            </button>
          </li>
        ))}
      </ul>
    </div>
  );
}

// ─── Block Editor ───────────────────────────────────────────────
// Editor estrutural com 4 tipos: para, h2, list, callout
// Suporta reorder (↑↓), delete, e troca de tipo
function KBBlockEditor({ blocks, onChange }) {
  const addBlock = (kind) => {
    const empty =
      kind === "para"    ? { kind: "para", t: "" } :
      kind === "h2"      ? { kind: "h2", t: "" } :
      kind === "list"    ? { kind: "list", items: [""] } :
      kind === "callout" ? { kind: "callout", tone: "info", t: "" } :
      kind === "image"   ? { kind: "image", src: "", alt: "", caption: "" } : null;
    onChange([...blocks, empty]);
  };

  const update = (i, patch) => {
    const next = blocks.map((b, idx) => idx === i ? { ...b, ...patch } : b);
    onChange(next);
  };

  const move = (i, dir) => {
    const j = i + dir;
    if (j < 0 || j >= blocks.length) return;
    const next = [...blocks];
    [next[i], next[j]] = [next[j], next[i]];
    onChange(next);
  };

  const del = (i) => onChange(blocks.filter((_, idx) => idx !== i));

  return (
    <div className="kb-blocks">
      {blocks.length === 0 && (
        <div className="kb-blocks-empty">
          <p>Sem blocos ainda — adicione o primeiro abaixo.</p>
        </div>
      )}

      {blocks.map((b, i) => (
        <div key={i} className={"kb-block kb-block--" + b.kind}>
          <div className="kb-block-tools">
            <select
              value={b.kind}
              onChange={e => {
                const k = e.target.value;
                if (k === "list") update(i, { kind: k, items: b.items || (b.t ? [b.t] : [""]), t: undefined });
                else if (k === "callout") update(i, { kind: k, tone: b.tone || "info", t: b.t || (b.items ? b.items.join(" ") : "") });
                else if (k === "image") update(i, { kind: k, src: b.src || "", alt: b.alt || "", caption: b.caption || "" });
                else update(i, { kind: k, t: b.t || (b.items ? b.items.join(" ") : "") });
              }}>
              <option value="para">Parágrafo</option>
              <option value="h2">Título</option>
              <option value="list">Lista</option>
              <option value="callout">Aviso</option>
              <option value="image">Imagem</option>
            </select>
            {b.kind === "callout" && (
              <select value={b.tone} onChange={e => update(i, { tone: e.target.value })}>
                <option value="info">info</option>
                <option value="ok">ok</option>
                <option value="warn">atenção</option>
                <option value="bad">crítico</option>
              </select>
            )}
            <span className="kb-block-spacer"/>
            <button onClick={() => move(i, -1)} disabled={i === 0} title="Subir">↑</button>
            <button onClick={() => move(i, +1)} disabled={i === blocks.length - 1} title="Descer">↓</button>
            <button onClick={() => del(i)} title="Remover">×</button>
          </div>

          {b.kind === "para" && (
            <textarea
              value={b.t}
              onChange={e => update(i, { t: e.target.value })}
              placeholder="Texto do parágrafo..."
              rows={3}/>
          )}
          {b.kind === "h2" && (
            <input
              className="kb-block-h"
              value={b.t}
              onChange={e => update(i, { t: e.target.value })}
              placeholder="Título da seção"/>
          )}
          {b.kind === "list" && (
            <div className="kb-block-list">
              {b.items.map((it, j) => (
                <div key={j} className="kb-block-list-row">
                  <span className="kb-block-bullet">•</span>
                  <input
                    value={it}
                    onChange={e => {
                      const next = [...b.items];
                      next[j] = e.target.value;
                      update(i, { items: next });
                    }}
                    placeholder={"Item " + (j + 1)}/>
                  <button onClick={() => {
                    const next = b.items.filter((_, idx) => idx !== j);
                    update(i, { items: next.length ? next : [""] });
                  }}>×</button>
                </div>
              ))}
              <button className="kb-block-add-item" onClick={() => update(i, { items: [...b.items, ""] })}>
                + adicionar item
              </button>
            </div>
          )}
          {b.kind === "callout" && (
            <textarea
              className={"kb-block-callout kb-block-callout--" + b.tone}
              value={b.t}
              onChange={e => update(i, { t: e.target.value })}
              placeholder="Mensagem do aviso..."
              rows={2}/>
          )}
          {b.kind === "image" && window.KBImageBlockEditor && (
            <window.KBImageBlockEditor
              block={b}
              onChange={(patch) => update(i, patch)}/>
          )}
        </div>
      ))}

      <div className="kb-blocks-add">
        <small>Adicionar bloco</small>
        <button onClick={() => addBlock("para")}>Parágrafo</button>
        <button onClick={() => addBlock("h2")}>Título</button>
        <button onClick={() => addBlock("list")}>Lista</button>
        <button onClick={() => addBlock("callout")}>Aviso</button>
        <button onClick={() => addBlock("image")}>Imagem</button>
      </div>
    </div>
  );
}

// ─── Full Composer (com block editor + meta) ────────────────────
function KBComposer({ initial, onClose, onSave }) {
  const [draft, setDraft] = useState(() => initial || {
    title: "", excerpt: "", cat: "producao", nivel: "iniciante", equip: "—",
    tags: "", body: [],
  });
  const [aiLoading, setAiLoading] = useState(false);
  const [aiApplied, setAiApplied] = useState(null);
  const [aiErr, setAiErr] = useState(null);

  const set = (patch) => setDraft({ ...draft, ...patch });

  const suggest = async () => {
    setAiLoading(true); setAiErr(null);
    try {
      const out = await window.kbSuggestMeta(draft);
      setDraft(d => ({
        ...d,
        title: out.title || d.title,
        excerpt: out.excerpt || d.excerpt,
        tags: Array.isArray(out.tags) ? out.tags.join(", ") : d.tags,
      }));
      setAiApplied(out);
      setTimeout(() => setAiApplied(null), 4000);
    } catch (e) {
      setAiErr((e && e.message) || "Falha na sugestão");
    } finally {
      setAiLoading(false);
    }
  };

  return (
    <React.Fragment>
      <div className="kb-modal-back" onClick={onClose}/>
      <div className="kb-modal kb-composer kb-composer-full" role="dialog">
        <header className="kb-modal-h">
          <div>
            <small>{initial ? "Editar artigo" : "Novo artigo"}</small>
            <h3>{draft.title || "Sem título"}</h3>
          </div>
          <button className="kb-x" onClick={onClose}>×</button>
        </header>

        <div className="kb-composer-body kb-composer-full-body">
          <div className="kb-composer-meta">
            <label>
              <small>Título</small>
              <input value={draft.title} onChange={e => set({ title: e.target.value })} placeholder="Ex.: Trocar filtro de ar do compressor"/>
            </label>
            <label>
              <small>Resumo (1 linha)</small>
              <input value={draft.excerpt} onChange={e => set({ excerpt: e.target.value })} placeholder="O que essa pessoa vai aprender"/>
            </label>
            <div className="kb-composer-meta-row">
              <label>
                <small>Categoria</small>
                <select value={draft.cat} onChange={e => set({ cat: e.target.value })}>
                  <option value="producao">Produção</option>
                  <option value="equip">Equipamentos</option>
                  <option value="arte">Pré-impressão</option>
                  <option value="atendim">Atendimento</option>
                  <option value="fiscal">Fiscal &amp; financeiro</option>
                  <option value="sistema">Sistema (ERP)</option>
                  <option value="rh">Pessoas</option>
                </select>
              </label>
              <label>
                <small>Nível</small>
                <select value={draft.nivel} onChange={e => set({ nivel: e.target.value })}>
                  <option value="iniciante">Iniciante</option>
                  <option value="intermediario">Intermediário</option>
                  <option value="avancado">Avançado</option>
                </select>
              </label>
              <label>
                <small>Equipamento</small>
                <input value={draft.equip} onChange={e => set({ equip: e.target.value })} placeholder="—"/>
              </label>
            </div>
            <label>
              <small>Etiquetas (separadas por vírgula)</small>
              <input value={draft.tags} onChange={e => set({ tags: e.target.value })} placeholder="manutenção, compressor, semanal"/>
            </label>

            {window.kbSuggestMeta && (
              <div className="kb-composer-ai">
                <button className="kb-composer-ai-btn" onClick={suggest} disabled={aiLoading || (draft.body || []).length === 0}>
                  <span style={{fontWeight:700}}>✦</span>
                  {aiLoading ? "Sugerindo..." : "Sugerir título, resumo e etiquetas"}
                </button>
                <p className="kb-composer-ai-hint">
                  A IA lê os blocos de conteúdo e propõe meta automaticamente. Adicione conteúdo primeiro.
                </p>
                {aiApplied && (
                  <div className="kb-composer-ai-applied">✓ Sugestões aplicadas — revise antes de publicar</div>
                )}
                {aiErr && (
                  <div className="kb-ai-err" style={{marginTop:6, fontSize:11.5}}>{aiErr}</div>
                )}
              </div>
            )}
          </div>

          <div className="kb-composer-content">
            <small className="kb-composer-section">Conteúdo</small>
            <KBBlockEditor
              blocks={draft.body}
              onChange={(body) => set({ body })}/>
          </div>
        </div>

        <footer className="kb-composer-foot">
          <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
          <button className="os-btn primary" onClick={() => onSave(draft)}>
            {initial ? "Salvar alterações" : "Publicar artigo"}
          </button>
        </footer>
      </div>
    </React.Fragment>
  );
}

// Expor pro window
window.KBAIDialog = KBAIDialog;
window.KBRelated = KBRelated;
window.KBComposer = KBComposer;
window.kbRelatedArticles = relatedArticles;
window.kbRenderMD = renderMD;

})();
