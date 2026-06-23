// forja-mcp.jsx — Forja Refino #2 (MCP + Inteligência)
// IA propõe, [W] decide (P4). Respostas GROUNDED na memória (cita fonte, nunca inventa).
// Exporta: window.ForjaMCPView · window.ForjaIAPanel · window.ForjaNewIssue · window.forjaRag · window.forjaSuggest
const { useState: useStateM, useMemo: useMemoM, useRef: useRefM, useEffect: useEffectM } = React;

// ─── RAG mockado: busca GROUNDED no corpus (issues + changelog + ondas) ───
function forjaRag(query) {
  const q = (query || "").toLowerCase().split(/\s+/).filter(w => w.length > 2);
  if (q.length === 0) return { verdict: "Digite uma pergunta sobre o que já foi decidido ou feito.", kind: "idle", sources: [] };
  const F = window.FORJA;
  const score = (txt) => q.reduce((a, w) => a + ((txt || "").toLowerCase().includes(w) ? 1 : 0), 0);
  const src = [];
  F.CHANGELOG.forEach(e => { const s = score(e.ref + " " + e.resumo + " " + e.modulos.join(" ")); if (s) src.push({ s, kind: e.tipo === "adr" ? "adr" : "log", ref: e.ref, label: e.resumo, when: e.data }); });
  F.ISSUES.forEach(i => { const s = score(i.titulo + " " + i.desc + " " + i.modulo + " " + i.id); if (s) src.push({ s, kind: "issue", ref: i.id, label: i.titulo, when: i.fase }); });
  F.ONDAS.forEach(o => { const s = score(o.id + " " + o.nome); if (s) src.push({ s, kind: "onda", ref: "~" + o.id, label: o.nome, when: o.estado }); });
  src.sort((a, b) => b.s - a.s);
  const top = src.slice(0, 5);
  const hasAdr = top.some(x => x.kind === "adr");
  const hasLog = top.some(x => x.kind === "log");
  let verdict;
  if (hasAdr) verdict = "Já decidido — existe ADR sobre isso. Não reproponha; cite o ADR e siga o vivo (Regra 7).";
  else if (hasLog) verdict = "Já entrou no main — há entrega no changelog. Verifique antes de refazer.";
  else if (top.length) verdict = "Há trabalho relacionado em aberto no backlog — provável duplicata.";
  else verdict = "Nada na memória casa com isso. Pode ser novo — proponha um issue (vira proposta + transporte).";
  return { verdict, kind: hasAdr ? "decidido" : hasLog ? "feito" : top.length ? "aberto" : "novo", sources: top };
}

// ─── Auto-sugest: heurística que IMITA o agente propondo metadados ───
function forjaSuggest(title) {
  const t = (title || "").toLowerCase();
  let tipo = "refino", fase = "F1", assignee = "CC", onda = null;
  if (/gate|lint|ci|e2e|playwright/.test(t)) { tipo = "gate"; assignee = "CL"; fase = "F3"; onda = "Q1"; }
  else if (/adr|token|decis|soberan/.test(t)) { tipo = "adr"; assignee = "W"; fase = "F0"; }
  else if (/bug|corrig|conserta|quebr|fix/.test(t)) { tipo = "bug"; }
  else if (/tela|drawer|p[áa]gina|tabela|kanban|board/.test(t)) { tipo = "tela"; }
  else if (/rename|migra|infra|script|fonte/.test(t)) { tipo = "infra"; assignee = "CL"; fase = "F3"; }
  else if (/doc|registro|manual/.test(t)) { tipo = "doc"; }
  if (/financ|tempero|ramp|fiscal/.test(t)) onda = "FA-1";
  return { tipo, fase, assignee, onda };
}

// ─── IA Panel (slide-over): modo ask (RAG) ou digest (resumir onda) ───
function ForjaIAPanel({ mode, onda, onClose, onHandoff }) {
  const [q, setQ] = useStateM("");
  const [asked, setAsked] = useStateM(null);
  const inRef = useRefM(null);
  useEffectM(() => { if (mode === "ask") inRef.current?.focus(); }, [mode]);

  const digest = useMemoM(() => {
    if (mode !== "digest") return null;
    const F = window.FORJA;
    const o = F.ONDAS.find(x => x.id === onda);
    const issues = F.ISSUES.filter(i => i.onda === onda);
    const byFase = {}; issues.forEach(i => byFase[i.fase] = (byFase[i.fase] || 0) + 1);
    const blockers = issues.filter(i => i.bloqueado_por.length);
    const shipped = F.CHANGELOG.filter(e => e.ref.includes(onda) || (o && e.resumo.toLowerCase().includes((o.milestone||"").toLowerCase().split(" ")[0])));
    return { o, issues, byFase, blockers, shipped };
  }, [mode, onda]);

  const ask = () => setAsked(forjaRag(q));
  const onKey = (e) => { if (e.key === "Enter") { e.preventDefault(); ask(); } if (e.key === "Escape") onClose(); };
  const KindBadge = ({ k }) => <span className={"fj-rag-kind fj-rag-kind-" + k}>{ {adr:"ADR", log:"shippou", issue:"issue", onda:"onda"}[k] || k }</span>;

  return (
    <div className="fj-drawer-back" onClick={onClose}>
      <aside className="fj-drawer fj-ia" onClick={e => e.stopPropagation()}>
        <header className="fj-dr-head">
          <div className="fj-dr-head-l">
            <span className="fj-ia-spark">✦</span>
            <span className="fj-dr-id">{mode === "ask" ? "Perguntar à memória" : "Resumir onda " + onda}</span>
          </div>
          <button className="icon-btn" onClick={onClose} aria-label="Fechar"><I.x size={14}/></button>
        </header>
        <div className="fj-dr-body">
          {mode === "ask" && (
            <React.Fragment>
              <p className="fj-dr-desc">IA grounded no corpus (ADRs · changelog · backlog). Cita fonte, nunca inventa — anti-reinvenção (Regra 7).</p>
              <div className="fj-ia-ask">
                <I.search size={13}/>
                <input ref={inRef} value={q} onChange={e => setQ(e.target.value)} onKeyDown={onKey}
                       placeholder="Ex.: já decidimos a cor do accent? gate de cor crua existe?"/>
                <button className="os-btn primary" onClick={ask}>Perguntar</button>
              </div>
              {asked && (
                <div className="fj-dr-sec" style={{ marginTop: 8 }}>
                  <div className={"fj-rag-verdict fj-rag-" + asked.kind}>{asked.verdict}</div>
                  {asked.sources.length > 0 && (
                    <React.Fragment>
                      <h3>Fontes ({asked.sources.length})</h3>
                      <ul className="fj-rag-src">
                        {asked.sources.map((s, i) => (
                          <li key={i}><KindBadge k={s.kind}/><span className="fj-rag-ref">{s.ref}</span><span className="fj-rag-lbl">{s.label}</span><span className="fj-rag-when">{s.when}</span></li>
                        ))}
                      </ul>
                    </React.Fragment>
                  )}
                </div>
              )}
            </React.Fragment>
          )}

          {mode === "digest" && digest && (
            <React.Fragment>
              <p className="fj-dr-desc">{digest.o ? digest.o.nome : onda} · {digest.o?.estado} · janela {digest.o?.janela}. Resumo computado do backlog real (não inventado).</p>
              <div className="fj-dr-sec">
                <h3>Progresso · {digest.issues.length} issues</h3>
                <div className="fj-digest-fases">
                  {window.FORJA.PHASES.map(p => digest.byFase[p.id] ? (
                    <span key={p.id} className="fj-digest-fase" style={{ "--ph": p.hue }}>{p.id} <b>{digest.byFase[p.id]}</b></span>
                  ) : null)}
                </div>
              </div>
              <div className="fj-dr-sec">
                <h3>Bloqueios · {digest.blockers.length}</h3>
                {digest.blockers.length === 0 ? <p className="fj-dr-desc">Sem bloqueios nesta onda.</p> :
                  <ul className="fj-subtasks">{digest.blockers.map(b => <li key={b.id}><span className="fj-check-box" style={{borderColor:"var(--neg, oklch(0.58 0.21 25))"}}/>{b.id} — bloqueado por {b.bloqueado_por.join(", ")}</li>)}</ul>}
              </div>
              <div className="fj-dr-sec">
                <h3>Já shippou ({digest.shipped.length})</h3>
                {digest.shipped.length === 0 ? <p className="fj-dr-desc">Nada fechado ainda nesta onda.</p> :
                  <ul className="fj-rag-src">{digest.shipped.map((e,i) => <li key={i}><span className="fj-rag-ref">{e.ref}</span><span className="fj-rag-lbl">{e.resumo}</span><span className="fj-rag-when">{e.data}</span></li>)}</ul>}
              </div>
              <button className="fj-ia-foot fj-ia-foot-btn" onClick={() => onHandoff && onHandoff(onda)}><span className="fj-ia-spark">✦</span>Gerar release notes + handoff desta onda →</button>
            </React.Fragment>
          )}
        </div>
      </aside>
    </div>
  );
}

// ─── Handoffs F1→F3 (Cowork→Code via MCP) — superfície do loop na Forja ───
const FJ_HO_STATE = {
  pending: { lbl: "pendente",  cls: "pend", desc: "aguarda o Code puxar via handoff-pending" },
  applied: { lbl: "aplicado",  cls: "appl", desc: "PR aberto · gates rodando" },
  merged:  { lbl: "mergeado",  cls: "merg", desc: "3 gates verdes · auto-merge" },
  blocked: { lbl: "bloqueado", cls: "blok", desc: "gate vermelho · volta pro [CC]" },
  stale:   { lbl: "parado",    cls: "stal", desc: "pending > 3d · alerta no inbox ops" },
};
function HandoffPanel() {
  const [filtro, setFiltro] = useStateM("all");
  const [toast, setToast] = useStateM(null);
  const all = window.FORJA.HANDOFFS;
  const hb = window.FORJA.HANDOFF_HEARTBEAT;
  const list = filtro === "all" ? all : all.filter(h => h.estado === filtro);
  const count = (s) => all.filter(h => h.estado === s).length;
  const tabs = [["all", "todos", all.length]].concat(
    Object.keys(FJ_HO_STATE).map(s => [s, FJ_HO_STATE[s].lbl, count(s)])
  );
  const lever = (h, acao) => { setToast(`${acao} · ${h.slug} v${h.v} — call MCP auditada`); setTimeout(() => setToast(null), 2600); };
  // levers conforme estado (call MCP auditada — roteamento, não [W] operando)
  const leversFor = (h) => {
    if (h.estado === "stale")   return [["re-disparar", "re-disparar"], ["supersede", "supersede"]];
    if (h.estado === "blocked") return [["devolver ao [CC]", "devolver"], ["supersede", "supersede"]];
    if (h.estado === "pending") return [["supersede", "supersede"]];
    return [];
  };
  return (
    <section className="fj-mcp-card fj-ho">
      <div className="fj-ho-head">
        <h3>Handoffs <span className="fj-ho-flow">F1 → F3</span> · Cowork → Code</h3>
        <div className="fj-ho-tabs">
          {tabs.map(([id, lbl, n]) => (
            <button key={id} className={"fj-ho-tab" + (filtro === id ? " on" : "")} onClick={() => setFiltro(id)}>
              {lbl}<span className="fj-ho-tab-n">{n}</span>
            </button>
          ))}
        </div>
      </div>
      <p className="fj-ho-sub">O design sai daqui assinado, o Code puxa via <code>handoff-pending</code>, aplica no escopo e devolve <code>handoff-ack</code>. O <b>gate vem do CI real</b> do PR, não do auto-relato. Travado → você roteia (não opera): re-disparar, devolver, supersede.</p>
      <ul className="fj-ho-list">
        {list.map(h => {
          const st = FJ_HO_STATE[h.estado];
          const levers = leversFor(h);
          return (
            <li key={h.slug + h.v} className={"fj-ho-item fj-ho-" + st.cls}>
              <span className={"fj-ho-dot fj-ho-dot-" + st.cls} title={st.desc}></span>
              <div className="fj-ho-main">
                <div className="fj-ho-l1">
                  <span className="fj-ho-slug mono">{h.slug}</span>
                  <span className="fj-ho-v">v{h.v}</span>
                  <span className="fj-ho-tela">{h.tela}</span>
                  <span className="fj-ho-onda">~{h.onda}</span>
                </div>
                <div className="fj-ho-nota">{h.nota}</div>
                {levers.length > 0 && (
                  <div className="fj-ho-levers">
                    {levers.map(([lbl, act]) => (
                      <button key={act} className={"fj-ho-lever fj-ho-lever-" + act} onClick={() => lever(h, lbl)}>{lbl}</button>
                    ))}
                  </div>
                )}
              </div>
              <div className="fj-ho-meta">
                <span className={"fj-ho-sig fj-ho-sig-" + h.sig} title="assinatura HMAC verificada na ingestão">⚿ {h.sig}</span>
                <span className="fj-ho-files">{h.arquivos} arq</span>
                {h.gateConflito
                  ? <span className="fj-ho-gate fj-ho-gate-conflito" title="ack diz verde, mas o CI do PR não confirma">⚠ conflito</span>
                  : h.gate && <a className={"fj-ho-gate fj-ho-gate-" + h.gate} href="#" onClick={e => e.preventDefault()} title="abre o check que rodou (CI real)">gate {h.gate}</a>}
                {h.pr && <a className="fj-ho-pr mono" href="#" onClick={e => e.preventDefault()} title="abre o PR no GitHub">{h.pr} ↗</a>}
                <span className={"fj-ho-state fj-ho-state-" + st.cls}>{st.lbl}</span>
                <span className="fj-ho-when">{h.quando}</span>
              </div>
            </li>
          );
        })}
        {list.length === 0 && (
          <li className={"fj-ho-empty" + (hb.saudavel ? "" : " alerta")}>
            {hb.saudavel
              ? <><b>Loop ocioso.</b> Nenhum handoff neste estado. Transporte vivo — último ingest {hb.lastIngest}.</>
              : <><b>⚠ Transporte sem sinal.</b> Sem ingest {hb.lastIngest} — o sync pode ter quebrado, não é calmaria.</>}
          </li>
        )}
      </ul>
      <div className={"fj-ho-hb" + (hb.saudavel ? "" : " alerta")}>
        <span className="fj-ho-hb-dot"></span>
        sync Cowork→repo {hb.saudavel ? "vivo" : "sem sinal"} · último ingest <b>{hb.lastIngest}</b>
      </div>
      {toast && <div className="fj-ho-toast">{toast}</div>}
    </section>
  );
}

// ─── MCP View: contrato (recurso×ação) + tokens + auditoria ───
function ForjaMCPView() {
  const RoleBadge = window.FjRoleBadge;
  const permLbl = { ok: "permitido", propoe: "propõe", deny: "negado" };
  return (
    <div className="fj-mcp">
      <div className="fj-mcp-intro">
        <span className="fj-mcp-tag">mockado</span>
        Contrato e auditoria como <b>design</b> — o enforcement real é do servidor TeamMcp ([CL]). Default = <b>read + propose</b>; <code>merge</code> e <code>constituicao.edit</code> negados no contrato, não por convenção.
      </div>

      <HandoffPanel/>

      <div className="fj-mcp-grid">
        <section className="fj-mcp-card">
          <h3>Contrato de ferramentas</h3>
          <table className="fj-mcp-tbl">
            <thead><tr><th>Ferramenta</th><th>Ação</th><th>Permissão</th></tr></thead>
            <tbody>
              {window.FORJA.MCP_TOOLS.map(t => (
                <tr key={t.tool}>
                  <td className="mono">{t.tool}</td>
                  <td>{t.acao}</td>
                  <td><span className={"fj-perm fj-perm-" + t.perm}>{permLbl[t.perm]}</span><span className="fj-perm-nota">{t.nota}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>

        <section className="fj-mcp-card">
          <h3>Tokens ativos</h3>
          <ul className="fj-token-list">
            {window.FORJA.MCP_TOKENS.map(tk => (
              <li key={tk.id}>
                <RoleBadge role={tk.papel}/>
                <span className="fj-token-id mono">{tk.id}</span>
                <span className="fj-token-scope">{tk.escopo}</span>
                <span className="fj-token-meta">exp {tk.exp} · uso {tk.uso}</span>
                <button className="fj-token-revoke">revogar</button>
              </li>
            ))}
          </ul>
        </section>
      </div>

      <section className="fj-mcp-card">
        <h3>Auditoria · toda ação de agente (Regra 6 mecanizada)</h3>
        <ul className="fj-audit">
          {window.FORJA.MCP_AUDIT.map((a, i) => (
            <li key={i} className={a.deny ? "deny" : ""}>
              <span className="fj-audit-ts mono">{a.ts}</span>
              <RoleBadge role={a.ator}/>
              <span className="fj-audit-tool mono">{a.tool}</span>
              <span className="fj-audit-args mono">{a.args}</span>
              <span className={"fj-audit-res" + (a.deny ? " deny" : "")}>{a.res}</span>
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}

// ─── Novo issue: composer com auto-sugest revisável ───
function ForjaNewIssue({ onCreate, onClose }) {
  const [title, setTitle] = useStateM("");
  const sug = useMemoM(() => forjaSuggest(title), [title]);
  const [over, setOver] = useStateM({});
  const inRef = useRefM(null);
  useEffectM(() => { inRef.current?.focus(); }, []);
  const val = (k) => over[k] !== undefined ? over[k] : sug[k];
  const TYPES = window.FORJA.TYPES, PHASES = window.FORJA.PHASES, ACTORS = window.FORJA.ACTORS, ONDAS = window.FORJA.ONDAS;

  const create = () => {
    if (!title.trim()) return;
    onCreate({
      id: "FORJA-" + (143 + Math.floor(Math.random() * 50)),
      frescor: "inferido", titulo: title.trim(), tipo: val("tipo"), prio: "P2", fase: val("fase"), estado: "triagem",
      assignee: val("assignee"), onda: val("onda"), modulo: "Sistema", origem: "agente_mcp",
      vinculos: [], bloqueado_por: [], desc: "Proposto via auto-sugest — vai pra Triagem; analista enriquece, [W] aprova.",
      subtarefas: [], criados: "agente · agora", atualizado: "agente · agora",
      atividade: [{ ator: "CC", t: "issue proposto via auto-sugest (revisado)", quando: "agora" }],
    });
    onClose();
  };

  const Chip = ({ field, options, render }) => (
    <div className="fj-sug-row">
      <span className="fj-sug-lbl">{field}</span>
      <div className="fj-sug-opts">
        {options.map(o => (
          <button key={o.k} className={"fj-sug-chip" + (val(field) === o.k ? " on" : "")} onClick={() => setOver(s => ({ ...s, [field]: o.k }))}>{render(o)}</button>
        ))}
      </div>
    </div>
  );

  return (
    <div className="fj-pal-back" onClick={onClose}>
      <div className="fj-new" onClick={e => e.stopPropagation()}>
        <div className="fj-new-head"><span className="fj-ia-spark">✦</span><b>Novo issue</b><span className="fj-new-note">auto-sugest revisável · IA propõe, você decide</span><button className="icon-btn" onClick={onClose}><I.x size={13}/></button></div>
        <div className="fj-new-body">
          <input ref={inRef} className="fj-new-title" value={title} onChange={e => setTitle(e.target.value)}
                 placeholder="Título do issue…  (ex.: 'gate de cor crua no e2e')" onKeyDown={e => e.key === "Enter" && create()}/>
          <Chip field="tipo" options={Object.entries(TYPES).map(([k, v]) => ({ k, label: v.label }))} render={o => o.label}/>
          <Chip field="fase" options={PHASES.map(p => ({ k: p.id, label: p.id }))} render={o => o.label}/>
          <Chip field="assignee" options={Object.keys(ACTORS).map(k => ({ k }))} render={o => "[" + o.k + "]"}/>
          <Chip field="onda" options={[{ k: null }].concat(ONDAS.map(o => ({ k: o.id })))} render={o => o.k ? "~" + o.k : "sem onda"}/>
        </div>
        <div className="fj-new-foot">
          <span className="fj-foot-note">vai pra Triagem · analista enriquece · [W] aprova</span>
          <button className="os-btn primary" onClick={create} disabled={!title.trim()}>Mandar pra triagem</button>
        </div>
      </div>
    </div>
  );
}

// ─── Trilhas de papel (runbook): o que cada papel faz em cada fase ───
function ForjaRunbook({ onClose }) {
  const RoleBadge = window.FjRoleBadge;
  const steps = [
    { id:"F0",   owner:"W",  does:"Escreve o pedido em COWORK_NOTES.md — dispara o loop.", over:null },
    { id:"F1",   owner:"CC", does:"Gera o protótipo visual (page.jsx) + COMPARISON 15 dimensões aqui no Cowork.", over:null },
    { id:"F1.5", owner:"CD", does:"design-critique → critique-score.json (≥80 passa).", over:"/design-override" },
    { id:"F2",   owner:"W2", does:"Aprovação visual síncrona do screenshot.", over:"/screenshot-override" },
    { id:"F3",   owner:"CL", does:"Traduz o protótipo aprovado pra Inertia/React real no repo.", over:null },
    { id:"F3.5", owner:"CA", does:"accessibility-review (WCAG 2.1 AA) → a11y-report.md.", over:"/a11y-override" },
    { id:"F4",   owner:"W2", does:"Merge do PR — fecha o ciclo.", over:null },
  ];
  const PHASES = window.FORJA.PHASES;
  return (
    <div className="fj-drawer-back" onClick={onClose}>
      <aside className="fj-drawer" onClick={e => e.stopPropagation()}>
        <header className="fj-dr-head">
          <div className="fj-dr-head-l"><span className="fj-dr-id">Trilhas de papel</span></div>
          <button className="icon-btn" onClick={onClose} aria-label="Fechar"><I.x size={14}/></button>
        </header>
        <div className="fj-dr-body">
          <p className="fj-dr-desc">Runbook do cowork loop — quem faz o quê em cada fase. Onboarding de papel novo (humano ou agente). Fonte: PROTOCOL.md §1–§3.</p>
          <div className="fj-dr-sec">
            <h3>6 papéis</h3>
            <ul className="fj-rb-roles">
              {Object.keys(window.FORJA.ACTORS).map(r => {
                const a = window.FORJA.ACTORS[r];
                return <li key={r}><RoleBadge role={r} showName/><span className="fj-rb-kind">{a.kind === "agent" ? "agente · " + (a.model || "") : "humano"}</span><span className="fj-rb-desc">{a.desc}</span></li>;
              })}
            </ul>
          </div>
          <div className="fj-dr-sec">
            <h3>7 fases · F0→F4</h3>
            <ul className="fj-rb-steps">
              {steps.map(s => {
                const ph = PHASES.find(p => p.id === s.id);
                return (
                  <li key={s.id} style={{ "--ph": ph ? ph.hue : 250 }}>
                    <div className="fj-rb-step-top"><span className="fj-rb-fase">{s.id} {ph?.label}</span><RoleBadge role={s.owner}/></div>
                    <p className="fj-rb-does">{s.does}</p>
                    {s.over && <span className="fj-rb-over">escape hatch: <code>{s.over}</code> (registrado)</span>}
                  </li>
                );
              })}
            </ul>
          </div>
        </div>
      </aside>
    </div>
  );
}

// ─── Handoff zero-toque: release notes + prompt pro [CL] (1 paste do [W]) ───
function ForjaHandoff({ onda, onClose }) {
  const F = window.FORJA;
  const o = F.ONDAS.find(x => x.id === onda);
  const issues = F.ISSUES.filter(i => i.onda === onda);
  const shipped = F.CHANGELOG.filter(e => e.ref.includes(onda));
  const [copied, setCopied] = useStateM(false);
  const prompt =
`# Handoff zero-toque — onda ${onda}${o ? " (" + o.nome + ")" : ""}

Claude Code: aplique os issues abaixo no repo wagnerra23/oimpresso.com, abra PRs e
mergeie quando o CI ficar verde. NÃO toque na constituição (ADR/PROTOCOL/BRIEFING).
Merge fechado só por [W2].

## Issues da onda
${issues.map(i => `- [${i.id}] ${i.titulo}\n  fase ${i.fase} · ${i.modulo} · prio ${i.prio} · vínculos: ${i.vinculos.map(v => v.k + ":" + v.v).join(", ") || "—"}`).join("\n")}

## Já no main (não refazer)
${shipped.length ? shipped.map(e => `- ${e.ref} · ${e.resumo}`).join("\n") : "- (nada fechado ainda nesta onda)"}

## Transporte
URLs públicas (get_public_file_url ~1h) das fontes serão coladas aqui no transporte.
Wagner cola UMA vez no Claude Code — não toca em mais nada.`;
  const copy = () => { try { navigator.clipboard.writeText(prompt); setCopied(true); setTimeout(() => setCopied(false), 1600); } catch (e) {} };
  return (
    <div className="fj-pal-back" onClick={onClose}>
      <div className="fj-handoff" onClick={e => e.stopPropagation()}>
        <div className="fj-new-head"><span className="fj-ia-spark">✦</span><b>Handoff · onda {onda}</b><span className="fj-new-note">release notes + prompt pro [CL] · 1 paste do [W]</span><button className="icon-btn" onClick={onClose}><I.x size={13}/></button></div>
        <div className="fj-handoff-body"><pre className="fj-handoff-pre">{prompt}</pre></div>
        <div className="fj-new-foot">
          <span className="fj-foot-note">⚠ [CC]/MCP não commitam — o transporte é o paste do [W]. Não afirmo "commitado".</span>
          <button className="os-btn primary" onClick={copy}>{copied ? "copiado ✓" : "Copiar prompt"}</button>
        </div>
      </div>
    </div>
  );
}

window.ForjaIAPanel = ForjaIAPanel;
window.ForjaMCPView = ForjaMCPView;
window.ForjaNewIssue = ForjaNewIssue;
window.ForjaRunbook = ForjaRunbook;
window.ForjaHandoff = ForjaHandoff;

// ─── Dossiê do Analista [AN]: enriquece o ticket no F0 (triagem) ───
function ForjaDossie({ issue, allIssues, onApprove, onReject, onMerge, onClose }) {
  if (!issue) return null;
  const RoleBadge = window.FjRoleBadge;
  const REQS = {
    Financeiro: "charter Financeiro: conciliação OFX, fiscal NF-e + ISS, cobrança régua, DRE.",
    KB: "charter KB: SOPs, troubleshooter, ⌘K, RAG — busca é substring hoje.",
    Vendas: "charter Vendas: POS de balcão, localStorage per-business (multi-tenant Tier 0).",
    Atendimento: "charter Caixa Unificada: omnichannel, SLA por fila, ACL canal=fila.",
    Sistema: "governança: gates de CI (ui:lint/conformance/foundation-guard), ADRs, soberania [W].",
    Oficina: "charter Oficina: FSM reparo, DVI, fiscal split, aprovação WhatsApp token+PIN.",
  };
  const rag = window.forjaRag(issue.titulo + " " + issue.modulo);
  const decisoes = rag.sources.filter(s => s.kind === "adr" || s.kind === "log");
  const dups = allIssues.filter(i => i.id !== issue.id && i.modulo === issue.modulo && (i.estado || "backlog") === "backlog").slice(0, 3);
  const impacto = issue.prio === "P0" ? "alto" : issue.prio === "P1" ? "médio" : "baixo";
  const esforco = (issue.tipo === "bug" || issue.tipo === "doc") ? "P" : issue.tipo === "tela" ? "G" : "M";
  const sug = window.forjaSuggest(issue.titulo);
  const tier0 = /token|constitui|multi-tenant|\badr\b|segredo|soberan/.test((issue.titulo + " " + issue.desc).toLowerCase());
  const prioSug = impacto === "alto" ? "P0" : impacto === "médio" ? "P1" : esforco === "P" ? "P2" : "P3";

  return (
    <div className="fj-drawer-back" onClick={onClose}>
      <aside className="fj-drawer fj-ia" onClick={e => e.stopPropagation()}>
        <header className="fj-dr-head">
          <div className="fj-dr-head-l"><span className="fj-ia-spark">✦</span><span className="fj-dr-id">Dossiê · {issue.id}</span><RoleBadge role="AN"/></div>
          <button className="icon-btn" onClick={onClose} aria-label="Fechar"><I.x size={14}/></button>
        </header>
        <div className="fj-dr-body">
          <h2 className="fj-dr-title">{issue.titulo}</h2>
          <p className="fj-dr-desc">Enriquecido pelo analista [AN] no F0 — grounded, cita fonte. <b>Você decide</b> a saída.</p>

          <div className="fj-dr-sec"><h3>Requisitos relacionados</h3><p className="fj-dr-desc">{REQS[issue.modulo] || "Sem charter mapeado pro módulo " + issue.modulo + "."}</p></div>

          <div className="fj-dr-sec">
            <h3>Histórico de decisão</h3>
            <div className={"fj-rag-verdict fj-rag-" + rag.kind}>{rag.verdict}</div>
            {decisoes.length > 0 && <ul className="fj-rag-src" style={{ marginTop: 8 }}>{decisoes.map((s, i) => <li key={i}><span className={"fj-rag-kind fj-rag-kind-" + s.kind}>{s.kind === "adr" ? "ADR" : "shippou"}</span><span className="fj-rag-ref">{s.ref}</span><span className="fj-rag-lbl">{s.label}</span></li>)}</ul>}
          </div>

          <div className="fj-dr-sec">
            <h3>Duplicatas / dependências</h3>
            {dups.length === 0 ? <p className="fj-dr-desc">Nada parecido no backlog deste módulo.</p>
              : <ul className="fj-rag-src">{dups.map(d => <li key={d.id}><span className="fj-rag-ref">{d.id}</span><span className="fj-rag-lbl">{d.titulo}</span><button className="fj-fundir" onClick={() => onMerge(issue.id, d.id)}>fundir →</button></li>)}</ul>}
          </div>

          <div className="fj-dr-sec">
            <h3>Valor × esforço</h3>
            <div className="fj-dossie-ve">
              <span className={"fj-ve-pill imp-" + impacto}>impacto {impacto}</span>
              <span className="fj-ve-x">×</span>
              <span className={"fj-ve-pill esf-" + esforco}>esforço {esforco}</span>
            </div>
            <p className="fj-dr-desc" style={{ marginTop: 6 }}>impacto da prioridade + módulo afetado; esforço do tipo ({issue.tipo}).</p>
          </div>

          <div className="fj-dr-sec">
            <h3>Recomendação</h3>
            <dl className="fj-dr-meta">
              <dt>Prioridade</dt><dd><b>{prioSug}</b> <span className="fj-dr-desc">(sugerida)</span></dd>
              <dt>Fase / dono</dt><dd>{sug.fase} · <RoleBadge role={sug.assignee}/></dd>
              <dt>Onda</dt><dd>{sug.onda ? "~" + sug.onda : "sem onda"}</dd>
              <dt>Risco</dt><dd>{tier0 ? <span className="fj-tier0">⚠ Tier-0 — exige decisão [W]</span> : "padrão (reversível)"}</dd>
            </dl>
          </div>
        </div>
        <footer className="fj-dr-foot fj-dossie-foot">
          <button className="os-btn ghost" onClick={() => onReject(issue.id)}>Rejeitar</button>
          <button className="os-btn ghost" onClick={() => onApprove(issue.id, "P3")}>Rebaixar p/ P3</button>
          <span className="fj-foot-spacer"/>
          <button className="os-btn primary" onClick={() => onApprove(issue.id, prioSug)}>Aprovar {prioSug} → backlog</button>
        </footer>
      </aside>
    </div>
  );
}

// ─── Notificações / minha fila ───
function ForjaNotifs({ notifs, seen, onOpen, onTriage, onSeen, onMarkAll, onClose }) {
  const RoleBadge = window.FjRoleBadge;
  const Sec = ({ title, sec, items, onClick }) => items.length === 0 ? null : (
    <div className="fj-notif-sec">
      <h4>{title}<span className="fj-notif-n">{items.length}</span></h4>
      <ul>{items.map(i => {
        const unread = !seen.has(sec + ":" + i.id);
        return (
          <li key={i.id} className={unread ? "unread" : ""} onClick={() => { onSeen(sec + ":" + i.id); onClick(i.id); }}>
            <span className={"fj-notif-dot" + (unread ? " on" : "")}/>
            <span className="fj-id">{i.id}</span>
            <span className="fj-notif-t">{i.titulo}</span>
            <RoleBadge role={i.assignee}/>
          </li>
        );
      })}</ul>
    </div>
  );
  const empty = !notifs.triagem.length && !notifs.mine.length && !notifs.follow.length && !notifs.comments.length && !notifs.inferido.length;
  return (
    <div className="fj-pal-back fj-notif-back" onClick={onClose}>
      <div className="fj-notif-pop" onClick={e => e.stopPropagation()}>
        <div className="fj-notif-head"><b>Minha fila</b><button className="fj-notif-markall" onClick={onMarkAll}>marcar tudo lido</button><button className="icon-btn" onClick={onClose}><I.x size={13}/></button></div>
        <div className="fj-notif-body">
          <Sec title="Aguardando sua aprovação" sec="triagem" items={notifs.triagem} onClick={onTriage}/>
          <Sec title="Atribuídos a você [W]" sec="mine" items={notifs.mine} onClick={onOpen}/>
          <Sec title="Seguindo" sec="follow" items={notifs.follow} onClick={onOpen}/>
          <Sec title="Comentários recentes" sec="comments" items={notifs.comments} onClick={onOpen}/>
          <Sec title="Não-verificados @main" sec="inferido" items={notifs.inferido} onClick={onOpen}/>
          {empty && <div className="fj-notif-empty">Nada pendente. Tudo limpo.</div>}
        </div>
      </div>
    </div>
  );
}

window.ForjaDossie = ForjaDossie;
window.ForjaNotifs = ForjaNotifs;
window.forjaRag = forjaRag;
window.forjaSuggest = forjaSuggest;
