// forja-issue-drawer.jsx — drawer único do issue (PT-02): pipeline F0→F4, gates, vínculos,
// atividade/comentários e o ✓ lido @main. Transversal a lista/quadro/gantt. Onda 1.
const { useState: useStateD, useMemo: useMemoD, useEffect: useEffectD, useRef: useRefD } = React;
// ─── Drawer do issue ───
function FjIssueDrawer({ issue, relations, following, onFollow, rules, onClose, onPatch, onReverify, onComment, onReact, onLink }) {
  const PHASES = window.FORJA.PHASES, GATES = window.FORJA.GATES;
  const curIdx = PHASES.findIndex(p => p.id === issue.fase);
  const prio = FJ_PRIO[issue.prio];
  const nextPhase = PHASES[curIdx + 1];
  const phaseGates = GATES.filter(g => g.fase === issue.fase);
  const redGate = phaseGates.some(g => g.estado === "red");
  const needReverify = rules && rules.reverifyF1 && issue.fase === "F1" && issue.frescor !== "lido";
  const advBlock = (rules && rules.gateBlock && redGate) || needReverify;
  const advWhy = (rules && rules.gateBlock && redGate) ? "gate vermelho" : needReverify ? "exige ✓ lido @main" : null;
  const [editing, setEditing] = useStateD(false);
  const [draft, setDraft] = useStateD(issue.desc);
  const [comment, setComment] = useStateD("");
  const [lightbox, setLightbox] = useStateD(null);
  const [replyTo, setReplyTo] = useStateD(null);
  const [replyText, setReplyText] = useStateD("");
  const [reacted, setReacted] = useStateD(() => new Set());
  useEffectD(() => { setEditing(false); setDraft(issue.desc); setReplyTo(null); setLightbox(null); }, [issue.id]);

  const saveDesc = () => { onPatch(issue.id, { desc: draft, proposto: true }, { ator: "W", t: "descrição editada (proposta)", quando: "agora" }); setEditing(false); };
  const toggleSub = (i) => { const subs = issue.subtarefas.map((s, j) => j === i ? { ...s, done: !s.done } : s); onPatch(issue.id, { subtarefas: subs, proposto: true }); };
  const submitComment = () => { if (!comment.trim()) return; onComment(issue.id, comment.trim()); setComment(""); };
  const submitReply = () => { if (!replyText.trim()) return; onComment(issue.id, replyText.trim(), replyTo); setReplyText(""); setReplyTo(null); };
  const react = (cid) => { if (!cid || reacted.has(cid)) return; setReacted(s => new Set(s).add(cid)); onReact(issue.id, cid); };
  const addAnexo = (name, url) => onPatch(issue.id, { anexos: [...(issue.anexos || []), { name, url }] }, { ator: "W", t: "anexou " + name, quando: "agora" });
  const onFile = (e) => { const f = e.target.files && e.target.files[0]; if (!f) return; const r = new FileReader(); r.onload = () => addAnexo(f.name, r.result); r.readAsDataURL(f); e.target.value = ""; };
  const onPaste = (e) => { const items = (e.clipboardData && e.clipboardData.items) || []; for (const it of items) { if (it.type && it.type.indexOf("image") === 0) { const f = it.getAsFile(); const r = new FileReader(); r.onload = () => addAnexo("colado-" + Date.now() + ".png", r.result); r.readAsDataURL(f); } } };
  const renderMentions = (t) => (t || "").split(/(@[A-Za-z0-9]+|\[[A-Za-z0-9]+\])/).map((part, i) => {
    const m = part.match(/^@([A-Za-z0-9]+)$/) || part.match(/^\[([A-Za-z0-9]+)\]$/);
    const role = m && m[1].toUpperCase();
    if (role && window.FORJA.ACTORS[role]) return <span key={i} className="fj-mention">[{role}]</span>;
    return part;
  });
  const comments = issue.atividade.filter(a => a.comment);
  const nonComments = issue.atividade.filter(a => !a.comment);
  const roots = comments.filter(c => !c.replyTo);
  const repliesOf = (cid) => comments.filter(c => c.replyTo === cid);
  const rx = issue.reactions || {};

  return (
    <div className="fj-drawer-back" onClick={onClose}>
      <aside className="fj-drawer" onClick={(e) => e.stopPropagation()} onPaste={onPaste}>
        <header className="fj-dr-head">
          <div className="fj-dr-head-l">
            <span className="fj-dr-id">{issue.id}</span>
            <div className="fj-dr-chips">
              <FjTypeChip tipo={issue.tipo}/>
              <span className="fj-prio-chip" style={{ "--pc": prio.hue }}>{prio.label}</span>
              {issue.onda && <span className="fj-onda-chip">~{issue.onda}</span>}
              {issue.frescor && <FjFrescorPill issue={issue} full/>}
            </div>
          </div>
          <div className="fj-dr-head-r">
            <button className={"fj-follow" + (following ? " on" : "")} onClick={() => onFollow(issue.id)} title={following ? "Seguindo" : "Seguir"}>{following ? "seguindo" : "+ seguir"}</button>
            <button className="icon-btn" onClick={onClose} aria-label="Fechar"><I.x size={14}/></button>
          </div>
        </header>

        <div className="fj-dr-body">
          {issue.proposto && <div className="fj-proposto-banner">⚠ Alterações não-salvas = <b>proposta</b>. Vira patch espelho + transporte zero-toque ([W] aplica). A tela nunca grava no git.</div>}
          <h2 className="fj-dr-title">{issue.titulo}</h2>

          {!editing ? (
            <div className="fj-dr-desc-row">
              <p className="fj-dr-desc">{issue.desc}</p>
              <button className="fj-mini-edit" onClick={() => setEditing(true)} title="Editar descrição"><I.pencil size={11}/></button>
            </div>
          ) : (
            <div className="fj-dr-edit">
              <textarea value={draft} onChange={e => setDraft(e.target.value)} rows={4}/>
              <div className="fj-dr-edit-act">
                <button className="os-btn ghost" onClick={() => { setEditing(false); setDraft(issue.desc); }}>Cancelar</button>
                <button className="os-btn primary" onClick={saveDesc}>Salvar (proposta)</button>
              </div>
            </div>
          )}

          {issue.frescor && (
          <div className="fj-reverify">
            <FjFrescorPill issue={issue} full/>
            {issue.frescor !== "lido"
              ? <button className="fj-reverify-btn" onClick={() => onReverify(issue.id)}><I.check size={11}/>Conferir contra @main</button>
              : <span className="fj-reverify-ok">conferido nesta sessão · Portão 1 ✓</span>}
          </div>
          )}

          {issue.fase ? (
          <div className="fj-dr-sec">
            <h3>Fase · F0→F4 (pipeline de tela)</h3>
            <div className="fj-phasebar">
              {PHASES.map((p, i) => (
                <div key={p.id} className={"fj-phstep" + (i < curIdx ? " done" : i === curIdx ? " cur" : "")} style={{ "--ph": p.hue }} title={`${p.id} ${p.label} · ${p.owner}`}>
                  <span className="fj-phstep-dot">{i < curIdx ? <I.check size={9}/> : null}</span>
                  <span className="fj-phstep-lbl">{p.id}</span>
                </div>
              ))}
            </div>
            {nextPhase && (
              <div className="fj-transition">
                <div className="fj-trans-gates">
                  {phaseGates.length === 0 && <span className="fj-trans-none">sem gate nesta fase</span>}
                  {phaseGates.map(g => (<span key={g.id} className={"fj-gate fj-gate-" + g.estado}><span className="fj-gate-dot"/>{g.id}</span>))}
                </div>
                <div className="fj-trans-adv">
                  <button className="os-btn primary" disabled={advBlock} title={advWhy || ""}>Avançar p/ {nextPhase.id} →</button>
                  {advBlock && <span className="fj-block-why">trava: {advWhy}</span>}
                </div>
              </div>
            )}
          </div>
          ) : (
          <div className="fj-dr-sec">
            <h3>Execução</h3>
            <div className="fj-exec-row"><FjStatusPill s={issue.exec}/><span className="fj-exec-note">fora do pipeline de telas — infra, gate e ADR seguem o status canon (sem F1.5 Critique / F2 Screenshot)</span></div>
          </div>
          )}

          <div className="fj-dr-sec">
            <h3>Atribuição</h3>
            <dl className="fj-dr-meta">
              <dt>Responsável</dt><dd><FjOwnerSeal issue={issue} showName/></dd>
              <dt>Módulo</dt><dd>{issue.modulo}</dd>
              <dt>Esforço</dt><dd className="mono">{issue.tam || (issue.estimate_h ? issue.estimate_h + "h" : "—")}</dd>
              <dt>Origem</dt><dd className="mono">{issue.origem}</dd>
              <dt>Atualizado</dt><dd className="mono">{issue.atualizado}</dd>
              {issue.bloqueado_por.length > 0 && (
                <React.Fragment><dt>Bloqueado por</dt><dd>{issue.bloqueado_por.map((b, i) => <FjVincChip key={i} k="issue" v={b} onClick={() => onLink(b)}/>)}</dd></React.Fragment>
              )}
            </dl>
          </div>

          {issue.subtarefas.length > 0 && (
            <div className="fj-dr-sec">
              <h3>Subtarefas · {issue.subtarefas.filter(s => s.done).length}/{issue.subtarefas.length}</h3>
              <ul className="fj-subtasks">
                {issue.subtarefas.map((s, i) => (
                  <li key={i} className={s.done ? "done" : ""}><button className="fj-check-box" onClick={() => toggleSub(i)}>{s.done && <I.check size={10}/>}</button>{s.t}</li>
                ))}
              </ul>
            </div>
          )}

          {issue.vinculos.length > 0 && (
            <div className="fj-dr-sec">
              <h3>Vínculos</h3>
              <div className="fj-vinc-row">{issue.vinculos.map((v, i) => <FjVincChip key={i} k={v.k} v={v.v} onClick={() => onLink(v.v)}/>)}</div>
            </div>
          )}

          {relations && (relations.parent || relations.children.length > 0 || relations.bloqueia.length > 0 || relations.relacionados.length > 0 || issue.bloqueado_por.length > 0) && (
            <div className="fj-dr-sec">
              <h3>Relações</h3>
              {relations.parent && <div className="fj-rel-row"><span className="fj-rel-lbl">épico</span><FjVincChip k="issue" v={relations.parent} onClick={() => onLink(relations.parent)}/></div>}
              {relations.children.length > 0 && (
                <div className="fj-rel-row fj-rel-kids-row"><span className="fj-rel-lbl">sub-issues</span>
                  <div className="fj-rel-kids">{relations.children.map(c => <button key={c.id} className="fj-kid" onClick={() => onLink(c.id)}><span className="fj-id">{c.id}</span><span className="fj-kid-t">{c.titulo}</span><FjPhaseBadge fase={c.fase}/></button>)}</div>
                </div>
              )}
              {(issue.bloqueado_por.length > 0 || relations.bloqueia.length > 0) && (
                <div className="fj-depgraph">
                  <div className="fj-dep-col">{issue.bloqueado_por.length ? issue.bloqueado_por.map(id => <button key={id} className="fj-dep-node" onClick={() => onLink(id)}>{id}</button>) : <span className="fj-dep-none">—</span>}</div>
                  <span className="fj-dep-arrow">→</span>
                  <div className="fj-dep-node self">{issue.id}</div>
                  <span className="fj-dep-arrow">→</span>
                  <div className="fj-dep-col">{relations.bloqueia.length ? relations.bloqueia.map(id => <button key={id} className="fj-dep-node" onClick={() => onLink(id)}>{id}</button>) : <span className="fj-dep-none">—</span>}</div>
                </div>
              )}
              {relations.relacionados.length > 0 && <div className="fj-rel-row"><span className="fj-rel-lbl">relacionados</span>{relations.relacionados.map(id => <FjVincChip key={id} k="issue" v={id} onClick={() => onLink(id)}/>)}</div>}
            </div>
          )}

          <div className="fj-dr-sec">
            <h3>Anexos · {(issue.anexos || []).length}</h3>
            <div className="fj-anexos">
              {(issue.anexos || []).map((a, i) => (<button key={i} className="fj-anexo" onClick={() => setLightbox(a.url)} title={a.name}><img src={a.url} alt={a.name}/></button>))}
              <label className="fj-anexo-add"><input type="file" accept="image/*" style={{ display: "none" }} onChange={onFile}/><I.plus size={13}/>anexar</label>
            </div>
            <p className="fj-dr-desc fj-anexo-hint">cole uma imagem (⌘/Ctrl V) ou clique em anexar</p>
          </div>

          {nonComments.length > 0 && (
            <div className="fj-dr-sec">
              <h3>Atividade</h3>
              <ul className="fj-activity">
                {nonComments.map((a, i) => (<li key={i}><FjRoleBadge role={a.ator}/><span className="fj-act-t">{a.t}</span><span className="fj-act-when">{a.quando}</span></li>))}
              </ul>
            </div>
          )}

          <div className="fj-dr-sec">
            <h3>Comentários · {comments.length}</h3>
            <ul className="fj-comments">
              {roots.map((c, ci) => (
                <li key={c.cid || ci}>
                  <div className="fj-cm"><FjRoleBadge role={c.ator}/>
                    <div className="fj-cm-body">
                      <span className="fj-cm-t">{renderMentions(c.t)}</span>
                      <div className="fj-cm-foot">
                        <button className="fj-cm-react" onClick={() => react(c.cid)}>▲{rx[c.cid] ? " " + rx[c.cid] : ""}</button>
                        <button className="fj-cm-reply" onClick={() => { setReplyTo(c.cid); setReplyText(""); }}>responder</button>
                        <span className="fj-act-when">{c.quando}</span>
                      </div>
                      {repliesOf(c.cid).map((r, j) => (
                        <div key={j} className="fj-cm reply"><FjRoleBadge role={r.ator}/><div className="fj-cm-body"><span className="fj-cm-t">{renderMentions(r.t)}</span><span className="fj-act-when">{r.quando}</span></div></div>
                      ))}
                      {replyTo === c.cid && (
                        <div className="fj-comment-box reply"><input autoFocus value={replyText} onChange={e => setReplyText(e.target.value)} onKeyDown={e => e.key === "Enter" && submitReply()} placeholder="Responder…"/><button className="os-btn ghost" onClick={submitReply} disabled={!replyText.trim()}><I.send size={11}/></button></div>
                      )}
                    </div>
                  </div>
                </li>
              ))}
            </ul>
            <div className="fj-comment-box">
              <input value={comment} onChange={e => setComment(e.target.value)} onKeyDown={e => e.key === "Enter" && submitComment()} placeholder="Comentar… use @CC @W pra mencionar"/>
              <button className="os-btn ghost" onClick={submitComment} disabled={!comment.trim()}><I.send size={11}/></button>
            </div>
          </div>
        </div>

        <footer className="fj-dr-foot">
          <button className="os-btn ghost"><I.paperclip size={11}/>Anexar</button>
          <span className="fj-foot-spacer"/>
          <span className="fj-foot-note">projeção do git · escrita = proposta</span>
        </footer>
        {lightbox && <div className="fj-lightbox" onClick={(e) => { e.stopPropagation(); setLightbox(null); }}><img src={lightbox} alt="anexo"/></div>}
      </aside>
    </div>
  );
}
window.FjIssueDrawer = FjIssueDrawer;
