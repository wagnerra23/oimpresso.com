// forja-dossie.jsx — dossiê do Analista [AN]: enriquece o ticket no F0 (triagem) e leva a
// decisão aprovar/rejeitar/fundir. Extraído de forja-mcp.jsx (Onda 3).
const { useState: useStateDo, useMemo: useMemoDo, useRef: useRefDo, useEffect: useEffectDo } = React;
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
window.ForjaDossie = ForjaDossie;
