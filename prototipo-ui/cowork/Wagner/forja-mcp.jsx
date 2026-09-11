// forja-mcp.jsx — view MCP: Handoffs F1→F3 (Cowork→Code) + contrato de ferramentas +
// tokens ativos + auditoria. Tela da Onda 8 da paridade. Overlays e RAG saíram daqui (Onda 3).
const { useState: useStateM, useMemo: useMemoM, useRef: useRefM, useEffect: useEffectM } = React;
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
window.ForjaMCPView = ForjaMCPView;
window.HandoffPanel = HandoffPanel;
