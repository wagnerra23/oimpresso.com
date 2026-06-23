// forja-integra.jsx — Integrador: mapeia a Forja ↔ TeamMcp real (git) e sugere telas impactadas.
// Ancorado na investigação @main (wagnerra23/oimpresso.com · Modules/TeamMcp · SCOPE.md · routes.php).
const { useState: useStateI } = React;

// (A) Absorção — cada visão da Forja casa com algo que JÁ EXISTE no TeamMcp
const FORJA_ABSORB = [
  { forja:"Backlog / Quadro", rota:"/team-mcp/tasks", ctrl:"TasksAdminController", tabela:"mcp_tasks · mcp_epics · mcp_cycles", estado:"existe", acao:"absorver", nota:"Kanban Jira-style já no git — a Forja vira a UI dele." },
  { forja:"MCP (tokens · contrato · auditoria)", rota:"/team-mcp/team + /ads/admin/team-scopes + /ads/admin/tools", ctrl:"TeamController · TeamScopesController · ToolsController", tabela:"mcp_tokens · mcp_scopes · mcp_user_scopes · mcp_audit_log", estado:"existe", acao:"absorver", nota:"Token issuer, RBAC e tools registry reais — meu painel é só a casca." },
  { forja:"Atores [W][CC][CL]…", rota:"/team-mcp/team", ctrl:"McpActor (Identity Mesh)", tabela:"mcp_actors", estado:"existe", acao:"absorver", nota:"Identity Mesh (ADR 0081) — humano vs agente já modelado." },
  { forja:"Changelog / atividade", rota:"/team-mcp/cc-sessions", ctrl:"CcSessionsController · CcIngest", tabela:"mcp_cc_sessions · mcp_cc_messages", estado:"existe", acao:"alinhar", nota:"Changelog = projeção do ingest de sessões + PR/ADR (parte vem do git)." },
  { forja:"Saúde / sparkline", rota:"/team-mcp/scorecard", ctrl:"ScorecardController", tabela:"(facts+checks)", estado:"existe", acao:"alinhar", nota:"Scorecard de maturidade por ator — minhas métricas mapeiam aqui." },
  { forja:"Frescor ✓lido@main", rota:"(ingest)", ctrl:"IngestLivenessService · McpIngestHeartbeat", tabela:"mcp_ingest_heartbeat", estado:"existe", acao:"alinhar", nota:"Frescor = liveness do heartbeat de ingest. Reusar, não recriar." },
  { forja:"Triagem + Analista [AN]", rota:"(não há rota)", ctrl:"—", tabela:"mcp_tasks (estado F0)", estado:"falta", acao:"criar", nota:"Único gap real: intake/dossiê não existe. Encaixa como estado F0 das mcp_tasks." },
  { forja:"Handoff zero-toque", rota:"(parcial)", ctrl:"SyncMemoryWebhook", tabela:"mcp_memory_documents", estado:"parcial", acao:"alinhar", nota:"Webhook git→DB existe; falta o gerador de release notes/prompt." },
];

// (B) Telas do sistema que MUDAM ao absorver a Forja no TeamMcp
const FORJA_IMPACT = [
  { modulo:"TeamMcp", tela:"/team-mcp/team · /tasks · /cc-sessions · /scorecard", mudanca:"Re-skin pro DS v6 (roxo canon, drawer, frescor, atores) — recebe o F1 da Forja", acao:"absorver", conf:"alta" },
  { modulo:"ProjectMgmt", tela:"/projects (stub)", mudanca:"Decidir: fundir em mcp_tasks (TeamMcp) ou aposentar. Hoje é stub — provável dedup.", acao:"dedup", conf:"alta" },
  { modulo:"Shell / Sidebar", tela:"sidebar + topnav (data.jsx)", mudanca:"Item 'Forja/Projetos' aponta pra /team-mcp/team; consolidar com 'Team MCP'", acao:"alinhar", conf:"alta" },
  { modulo:"ADS", tela:"/ads/admin/tools · /ads/admin/team-scopes", mudanca:"UIs de tools/scopes alinham visual com o painel MCP da Forja (mesma gramática)", acao:"alinhar", conf:"média" },
  { modulo:"Copiloto", tela:"permissões copiloto.mcp.*", mudanca:"Permissions herdadas (não renomeadas) — rename é task futura, risco de quebrar usuários", acao:"alinhar", conf:"baixa" },
  { modulo:"Governance", tela:"audit UI (Fase 5)", mudanca:"Dashboard de mcp_audit_log vive aqui (não na Forja) — a Forja só lê o resumo", acao:"alinhar", conf:"média" },
  { modulo:"KB", tela:"browse ADRs/sessões", mudanca:"NÃO absorver — browse de conhecimento fica no KB; a Forja só cross-linka", acao:"manter", conf:"alta" },
];

const ACAO_META = {
  absorver: { hue: 295, label: "absorver" },
  alinhar:  { hue: 195, label: "alinhar" },
  criar:    { hue: 60,  label: "criar" },
  dedup:    { hue: 25,  label: "dedup" },
  manter:   { hue: 150, label: "manter" },
};
const EST_META = { existe: "existe", parcial: "parcial", falta: "falta" };

function AcaoPill({ acao }) {
  const m = ACAO_META[acao] || { hue: 250, label: acao };
  return <span className="fj-int-acao" style={{ "--ah": m.hue }}>{m.label}</span>;
}

function ForjaIntegrador() {
  const [tab, setTab] = useStateI("absorb");
  const counts = {
    absorver: FORJA_ABSORB.filter(r => r.acao === "absorver").length,
    criar: FORJA_ABSORB.filter(r => r.acao === "criar").length,
    alinhar: FORJA_ABSORB.filter(r => r.acao === "alinhar").length,
  };
  return (
    <div className="fj-integra">
      <div className="fj-int-verdict">
        <b>Veredito:</b> a Forja <b>não é módulo novo</b> — é o <b>F1 (re-skin) das telas <code>/team-mcp/*</code></b> que já existem no git.
        {" "}<b>{counts.absorver}</b> visões absorvem direto, <b>{counts.alinhar}</b> alinham, e só <b>{counts.criar}</b> é gap real (Triagem/Analista).
        <span className="fj-int-src">✓ lido @main · wagnerra23/oimpresso.com · Modules/TeamMcp</span>
      </div>

      <div className="fj-int-tabs">
        <button className={tab === "absorb" ? "active" : ""} onClick={() => setTab("absorb")}>Forja ↔ TeamMcp ({FORJA_ABSORB.length})</button>
        <button className={tab === "impact" ? "active" : ""} onClick={() => setTab("impact")}>Telas impactadas ({FORJA_IMPACT.length})</button>
      </div>

      {tab === "absorb" && (
        <div className="fj-int-table">
          <div className="fj-int-row fj-int-head">
            <span>Visão da Forja</span><span>Rota / controller real</span><span>Estado</span><span>Ação</span>
          </div>
          {FORJA_ABSORB.map((r, i) => (
            <div key={i} className="fj-int-row">
              <span className="fj-int-forja"><b>{r.forja}</b><small>{r.nota}</small></span>
              <span className="fj-int-rota"><code>{r.rota}</code><small className="mono">{r.ctrl}</small><small className="mono fj-int-tab">{r.tabela}</small></span>
              <span><span className={"fj-int-est fj-est-" + r.estado}>{EST_META[r.estado]}</span></span>
              <span><AcaoPill acao={r.acao}/></span>
            </div>
          ))}
        </div>
      )}

      {tab === "impact" && (
        <div className="fj-int-table">
          <div className="fj-int-row fj-int-head imp">
            <span>Módulo</span><span>Tela</span><span>O que muda</span><span>Ação</span><span>Conf.</span>
          </div>
          {FORJA_IMPACT.map((r, i) => (
            <div key={i} className="fj-int-row imp">
              <span className="fj-int-mod">{r.modulo}</span>
              <span className="mono fj-int-tela">{r.tela}</span>
              <span className="fj-int-mud">{r.mudanca}</span>
              <span><AcaoPill acao={r.acao}/></span>
              <span className={"fj-int-conf c-" + r.conf}>{r.conf}</span>
            </div>
          ))}
        </div>
      )}

      <div className="fj-int-foot">
        Próximo passo sugerido: <b>fundir <code>projects</code> + <code>teammcp</code></b> no shell apontando pra <code>/team-mcp/team</code>, e entregar o F1 da Forja como re-skin das 4 telas existentes — a única tela nova é <b>Triagem/Analista</b>. Handoff vira o gerador do prompt pro [CL].
      </div>
    </div>
  );
}

window.ForjaIntegrador = ForjaIntegrador;
