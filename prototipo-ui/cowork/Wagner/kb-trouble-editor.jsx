// kb-trouble-editor.jsx — Editor visual de árvore de troubleshoot
// + Histórico de versões + Auto-tag IA no composer
(() => {
const { useState, useEffect, useMemo, useRef } = React;

// ─────────────────────────────────────────────────────────────────
// EDITOR VISUAL — cria/edita árvore de decisão sem mexer em código
// ─────────────────────────────────────────────────────────────────
// Cada step: { q, yes, no } onde yes/no = índice numérico OU { fix: "texto" }
// O editor mostra um fluxograma visual com cards conectados.

function KBTroubleEditor({ initial, onSave, onClose }) {
  const [meta, setMeta] = useState(() => initial ? {
    id: initial.id, title: initial.title, equip: initial.equip, hue: initial.hue, when: initial.when,
  } : {
    id: "tr-" + Date.now(), title: "", equip: "—", hue: 240, when: "",
  });
  const [steps, setSteps] = useState(() => initial ? [...initial.steps] : [
    { q: "Primeira pergunta?", yes: { fix: "Solução se sim." }, no: { fix: "Solução se não." } }
  ]);
  const [editingStep, setEditingStep] = useState(null);

  const addStep = () => {
    setSteps(s => [...s, { q: "Nova pergunta?", yes: { fix: "Solução A" }, no: { fix: "Solução B" } }]);
    setEditingStep(steps.length);
  };

  const removeStep = (i) => {
    if (steps.length <= 1) return;
    // Remap referências
    const newSteps = steps.filter((_, idx) => idx !== i).map(s => ({
      ...s,
      yes: typeof s.yes === "number" ? (s.yes === i ? { fix: "(referência removida)" } : s.yes > i ? s.yes - 1 : s.yes) : s.yes,
      no:  typeof s.no  === "number" ? (s.no  === i ? { fix: "(referência removida)" } : s.no  > i ? s.no  - 1 : s.no)  : s.no,
    }));
    setSteps(newSteps);
    setEditingStep(null);
  };

  const updateStep = (i, patch) => {
    setSteps(s => s.map((step, idx) => idx === i ? { ...step, ...patch } : step));
  };

  const setBranch = (i, branch, type, value) => {
    // type: "next" (vai pra outro step) | "fix" (mostra solução)
    if (type === "next") {
      updateStep(i, { [branch]: parseInt(value, 10) });
    } else {
      updateStep(i, { [branch]: { fix: value } });
    }
  };

  const save = () => {
    const payload = {
      id: meta.id,
      title: meta.title || "Troubleshoot sem título",
      equip: meta.equip || "—",
      hue: parseInt(meta.hue, 10) || 240,
      when: meta.when || "—",
      steps,
    };
    onSave(payload);
  };

  return (
    <React.Fragment>
      <div className="kb-modal-back" onClick={onClose}/>
      <div className="kb-modal kb-trouble-editor" role="dialog">
        <header className="kb-modal-h">
          <div>
            <small>{initial ? "Editar troubleshoot" : "Novo troubleshoot"}</small>
            <h3>{meta.title || "Sem título"}</h3>
          </div>
          <button className="kb-x" onClick={onClose}>×</button>
        </header>

        <div className="kb-te-body">
          {/* Meta */}
          <div className="kb-te-meta">
            <label>
              <small>Título</small>
              <input value={meta.title} onChange={e => setMeta({...meta, title: e.target.value})}
                     placeholder="Ex.: Laminadora superaquecendo"/>
            </label>
            <div className="kb-te-meta-row">
              <label>
                <small>Equipamento</small>
                <input value={meta.equip} onChange={e => setMeta({...meta, equip: e.target.value})}/>
              </label>
              <label>
                <small>Cor (hue)</small>
                <input type="number" value={meta.hue} onChange={e => setMeta({...meta, hue: e.target.value})}
                       min="0" max="360" step="10"/>
                <span className="kb-te-hue-preview" style={{ background: `oklch(0.55 0.13 ${meta.hue})` }}/>
              </label>
            </div>
            <label>
              <small>Quando usar</small>
              <input value={meta.when} onChange={e => setMeta({...meta, when: e.target.value})}
                     placeholder="Ex.: filme de laminação fica esbranquiçado"/>
            </label>
          </div>

          {/* Fluxograma */}
          <div className="kb-te-tree">
            <div className="kb-te-tree-h">
              <small>Árvore de decisão</small>
              <button className="os-btn ghost" onClick={addStep}>+ Adicionar pergunta</button>
            </div>

            <div className="kb-te-flow">
              {steps.map((step, i) => (
                <div key={i} className={"kb-te-node" + (editingStep === i ? " editing" : "")}>
                  <div className="kb-te-node-h">
                    <span className="kb-te-node-n" style={{ background: `oklch(0.94 0.06 ${meta.hue})`, color: `oklch(0.36 0.13 ${meta.hue})` }}>
                      {i + 1}
                    </span>
                    <input
                      className="kb-te-node-q"
                      value={step.q}
                      onChange={e => updateStep(i, { q: e.target.value })}
                      placeholder="Pergunta diagnóstica..."
                      onFocus={() => setEditingStep(i)}/>
                    {steps.length > 1 && (
                      <button className="kb-te-del" onClick={() => removeStep(i)} title="Remover">×</button>
                    )}
                  </div>

                  <div className="kb-te-branches">
                    <BranchEditor
                      label="Sim"
                      tone="yes"
                      branch={step.yes}
                      stepCount={steps.length}
                      currentIdx={i}
                      onChange={(type, val) => setBranch(i, "yes", type, val)}/>
                    <BranchEditor
                      label="Não"
                      tone="no"
                      branch={step.no}
                      stepCount={steps.length}
                      currentIdx={i}
                      onChange={(type, val) => setBranch(i, "no", type, val)}/>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Preview da árvore */}
          <div className="kb-te-preview">
            <small>Preview</small>
            <div className="kb-te-preview-card" style={{ borderLeftColor: `oklch(0.55 0.13 ${meta.hue})` }}>
              <small style={{ color: `oklch(0.42 0.13 ${meta.hue})` }}>{meta.equip}</small>
              <b>{meta.title || "Sem título"}</b>
              <p>Use quando {meta.when || "—"}.</p>
              <span className="kb-te-preview-n">{steps.length} pergunta{steps.length > 1 ? "s" : ""}</span>
            </div>
          </div>
        </div>

        <footer className="kb-te-foot">
          <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
          <button className="os-btn primary" onClick={save} disabled={!meta.title.trim()}>
            {initial ? "Salvar alterações" : "Publicar troubleshoot"}
          </button>
        </footer>
      </div>
    </React.Fragment>
  );
}

function BranchEditor({ label, tone, branch, stepCount, currentIdx, onChange }) {
  const isFix = branch && typeof branch === "object" && "fix" in branch;
  const isNext = typeof branch === "number";
  const type = isFix ? "fix" : "next";

  return (
    <div className={"kb-te-branch kb-te-branch--" + tone}>
      <div className="kb-te-branch-h">
        <span className="kb-te-branch-l">{label}</span>
        <div className="kb-te-branch-toggle">
          <button
            className={"kb-te-toggle" + (type === "next" ? " active" : "")}
            onClick={() => onChange("next", currentIdx + 1 < stepCount ? currentIdx + 1 : 0)}>
            ir para…
          </button>
          <button
            className={"kb-te-toggle" + (type === "fix" ? " active" : "")}
            onClick={() => onChange("fix", isFix ? branch.fix : "Solução…")}>
            solução
          </button>
        </div>
      </div>

      {type === "next" ? (
        <select
          value={branch}
          onChange={e => onChange("next", e.target.value)}>
          {Array.from({length: stepCount}).map((_, i) => (
            <option key={i} value={i} disabled={i === currentIdx}>
              {i === currentIdx ? "(esta pergunta)" : `Pergunta ${i + 1}`}
            </option>
          ))}
        </select>
      ) : (
        <textarea
          value={isFix ? branch.fix : ""}
          onChange={e => onChange("fix", e.target.value)}
          placeholder="Descreva a solução. Você pode citar artigos com #a1, #a3..."
          rows={2}/>
      )}
    </div>
  );
}

window.KBTroubleEditor = KBTroubleEditor;

// ─────────────────────────────────────────────────────────────────
// HISTÓRICO DE VERSÕES — snapshots por artigo
// ─────────────────────────────────────────────────────────────────
// Storage: oimpresso.kb.versions = { articleId: [ {when, author, snapshot:{title,body,tags}} ] }

function loadVersions() {
  try { return JSON.parse(localStorage.getItem("oimpresso.kb.versions") || "{}"); }
  catch (e) { return {}; }
}
function saveVersions(m) {
  try { localStorage.setItem("oimpresso.kb.versions", JSON.stringify(m)); } catch (e) {}
}

function useKBVersions() {
  const [m, setM] = useState(loadVersions);
  useEffect(() => { saveVersions(m); }, [m]);

  const snapshot = (article, author) => {
    if (!article) return;
    setM(prev => {
      const list = prev[article.id] || [];
      const snap = {
        when: new Date().toLocaleString("pt-BR", { day: "2-digit", month: "2-digit", year: "2-digit", hour: "2-digit", minute: "2-digit" }),
        author: author || article.author || "você",
        snapshot: {
          title: article.title,
          excerpt: article.excerpt,
          body: JSON.parse(JSON.stringify(article.body || [])),
          tags: [...(article.tags || [])],
          status: article.status,
        },
      };
      return { ...prev, [article.id]: [snap, ...list].slice(0, 12) };
    });
  };

  const versionsFor = (id) => m[id] || [];

  return { versionsMap: m, snapshot, versionsFor };
}

window.useKBVersions = useKBVersions;

function KBVersionsDialog({ articleId, articles, versions, onRestore, onClose }) {
  const article = articles.find(a => a.id === articleId);
  const [selected, setSelected] = useState(null);

  if (!article) return null;

  const blockSig = (b) => {
    if (!b) return "";
    if (b.kind === "para" || b.kind === "h2" || b.kind === "callout") return b.kind + ":" + (b.t || "");
    if (b.kind === "list") return "list:" + (b.items || []).join("|");
    return "";
  };

  const diffBlocks = (oldBody, newBody) => {
    const oldSigs = (oldBody || []).map(blockSig);
    const newSigs = (newBody || []).map(blockSig);
    const all = [];
    let oi = 0, ni = 0;
    while (oi < oldSigs.length || ni < newSigs.length) {
      if (oi < oldSigs.length && ni < newSigs.length && oldSigs[oi] === newSigs[ni]) {
        all.push({ kind: "keep", block: newBody[ni] }); oi++; ni++;
      } else if (ni < newSigs.length && !oldSigs.includes(newSigs[ni])) {
        all.push({ kind: "add", block: newBody[ni] }); ni++;
      } else if (oi < oldSigs.length && !newSigs.includes(oldSigs[oi])) {
        all.push({ kind: "remove", block: oldBody[oi] }); oi++;
      } else if (oi < oldSigs.length && ni < newSigs.length) {
        all.push({ kind: "change", oldBlock: oldBody[oi], block: newBody[ni] }); oi++; ni++;
      } else if (oi < oldSigs.length) { all.push({ kind: "remove", block: oldBody[oi] }); oi++; }
      else if (ni < newSigs.length) { all.push({ kind: "add", block: newBody[ni] }); ni++; }
    }
    return all;
  };

  const renderBlockText = (b) => {
    if (!b) return "";
    if (b.kind === "h2") return "## " + b.t;
    if (b.kind === "para") return b.t;
    if (b.kind === "list") return (b.items || []).map(i => "• " + i).join("\n");
    if (b.kind === "callout") return "▸ " + (b.tone || "info").toUpperCase() + ": " + b.t;
    return "";
  };

  return (
    <React.Fragment>
      <div className="kb-modal-back" onClick={onClose}/>
      <div className="kb-modal kb-versions" role="dialog">
        <header className="kb-modal-h">
          <div>
            <small>Histórico do artigo</small>
            <h3>{article.title}</h3>
          </div>
          <button className="kb-x" onClick={onClose}>×</button>
        </header>

        <div className="kb-versions-body">
          <aside className="kb-versions-list">
            <div className="kb-versions-current">
              <span className="kb-versions-dot current"/>
              <div>
                <b>Versão atual</b>
                <small>{article.updated} · {article.author}</small>
              </div>
            </div>
            {versions.length === 0 ? (
              <p className="kb-versions-empty">Sem versões salvas ainda. Cada edição grava um snapshot.</p>
            ) : (
              <ul>
                {versions.map((v, i) => (
                  <li key={i}>
                    <button
                      className={"kb-versions-row" + (selected === i ? " active" : "")}
                      onClick={() => setSelected(i)}>
                      <span className="kb-versions-dot"/>
                      <div>
                        <b>{i === 0 ? "Versão anterior" : `Versão −${i + 1}`}</b>
                        <small>{v.when} · {v.author}</small>
                      </div>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </aside>

          <main className="kb-versions-detail">
            {selected === null ? (
              <div className="kb-versions-pick">
                <p>Selecione uma versão à esquerda para ver o diff em relação ao texto atual.</p>
              </div>
            ) : (() => {
              const v = versions[selected];
              const diff = diffBlocks(v.snapshot.body, article.body);
              const tagDiff = {
                added: (article.tags || []).filter(t => !(v.snapshot.tags || []).includes(t)),
                removed: (v.snapshot.tags || []).filter(t => !(article.tags || []).includes(t)),
              };
              return (
                <React.Fragment>
                  <div className="kb-versions-summary">
                    <div>
                      <small>Snapshot de</small>
                      <b>{v.when}</b>
                      <span>por {v.author}</span>
                    </div>
                    <button className="os-btn primary" onClick={() => onRestore(v.snapshot)}>Restaurar esta versão</button>
                  </div>

                  {v.snapshot.title !== article.title && (
                    <div className="kb-diff-block kb-diff-change">
                      <small>Título mudou</small>
                      <div className="kb-diff-old">{v.snapshot.title}</div>
                      <div className="kb-diff-new">{article.title}</div>
                    </div>
                  )}

                  {(tagDiff.added.length > 0 || tagDiff.removed.length > 0) && (
                    <div className="kb-diff-block kb-diff-change">
                      <small>Etiquetas</small>
                      <div>
                        {tagDiff.removed.map(t => <span key={t} className="kb-diff-tag removed">- {t}</span>)}
                        {tagDiff.added.map(t => <span key={t} className="kb-diff-tag added">+ {t}</span>)}
                      </div>
                    </div>
                  )}

                  <div className="kb-diff-blocks">
                    {diff.map((d, i) => {
                      if (d.kind === "keep") return null; // hide unchanged
                      if (d.kind === "add") return (
                        <div key={i} className="kb-diff-block kb-diff-add">
                          <small>+ adicionado</small>
                          <pre>{renderBlockText(d.block)}</pre>
                        </div>
                      );
                      if (d.kind === "remove") return (
                        <div key={i} className="kb-diff-block kb-diff-remove">
                          <small>− removido</small>
                          <pre>{renderBlockText(d.block)}</pre>
                        </div>
                      );
                      if (d.kind === "change") return (
                        <div key={i} className="kb-diff-block kb-diff-change">
                          <small>~ alterado</small>
                          <pre className="kb-diff-old">{renderBlockText(d.oldBlock)}</pre>
                          <pre className="kb-diff-new">{renderBlockText(d.block)}</pre>
                        </div>
                      );
                      return null;
                    })}
                    {diff.every(d => d.kind === "keep") && (
                      <p className="kb-versions-empty">Conteúdo idêntico — só meta mudou.</p>
                    )}
                  </div>
                </React.Fragment>
              );
            })()}
          </main>
        </div>
      </div>
    </React.Fragment>
  );
}

window.KBVersionsDialog = KBVersionsDialog;

// ─────────────────────────────────────────────────────────────────
// AI HELPERS PARA O COMPOSER
// ─────────────────────────────────────────────────────────────────
async function suggestMeta(draft) {
  const blocks = (draft.body || []).map(b => {
    if (b.kind === "para" || b.kind === "h2") return b.t;
    if (b.kind === "list") return (b.items || []).join(" / ");
    if (b.kind === "callout") return b.t;
    return "";
  }).filter(Boolean).join("\n");

  if (!blocks.trim()) throw new Error("Adicione conteúdo antes de pedir sugestão.");

  const prompt =
`Você é o editor da base de conhecimento de uma gráfica brasileira (Oimpresso ERP). Analise o conteúdo abaixo e gere JSON com:
- title: título curto e direto, máx 80 caracteres, em português brasileiro
- excerpt: resumo de uma linha, máx 140 caracteres, focado em utilidade prática
- tags: 3-6 etiquetas relevantes (substantivos, palavras-chave do domínio gráfica)

CONTEÚDO:
${blocks}

Responda APENAS o JSON, sem markdown, no formato:
{"title": "...", "excerpt": "...", "tags": ["...", "..."]}`;

  const answer = await window.claude.complete(prompt);
  // Extrai JSON da resposta
  const match = answer.match(/\{[\s\S]*\}/);
  if (!match) throw new Error("IA retornou formato inesperado.");
  return JSON.parse(match[0]);
}

window.kbSuggestMeta = suggestMeta;

})();
