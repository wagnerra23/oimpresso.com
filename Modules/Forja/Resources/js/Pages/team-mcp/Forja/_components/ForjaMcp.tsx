// Forja — aba MCP do cockpit. Réplica da view `mcp` do protótipo (PARIDADE §11 Onda 8).
//
// @memcofre
//   tela: /forja/mcp
//   module: Forja
//   adrs: 0388 (réplica primeiro) · 0283 (loop de handoff) · 0081 (token raw nunca exposto) · UI-0013
//   fonte visual: prototipo-ui/cowork/forja-mcp.jsx `function ForjaMCPView`
//   paridade: memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md
//
// ORDEM DO PROTÓTIPO, que é o contrato de layout desta onda:
//   intro (selo "mockado") → HANDOFFS F1→F3 → grid [contrato | tokens] → auditoria
//
// Os HANDOFFS voltaram pra cá (eram tela própria desde 2026-08-08). O componente é o
// MESMO que /forja/handoffs renderiza ({@link ./ForjaHandoffs.tsx}) — uma projeção,
// dois pontos de render. O motivo e o que se perdeu/manteve estão no cabeçalho de lá.
//
// CONTRATO / TOKENS / AUDITORIA seguem **MOCKADOS por design** — é o que o protótipo
// desenha (selo `fj-mcp-tag` "mockado" é copy do contrato visual) e o que o charter
// declara: vitrine do contrato, cujo enforce real é do servidor TeamMcp ([CL]).
// Existem fontes vivas equivalentes (`mcp_tokens`, `mcp_scopes`, `mcp_audit_log`);
// ligá-las MUDA a natureza da aba e a copy do selo — decisão [W], registrada em
// memory/requisitos/Forja/INCONSISTENCIAS-replica.md, não desta onda de paridade.
//
// Tier 0 (ADR 0081): NUNCA exibir/logar token raw — só o nome LÓGICO do token.
// Tier 0 (ADR 0093): `mcp_*` é repo-wide, sem business_id por design — nenhum scope
// é inventado aqui (esta aba não consulta nada; os handoffs vêm do ForjaMcpService).

import { Deferred } from '@inertiajs/react';
import ForjaHandoffs, { HandoffsSkeleton, type HandoffItem, type HeartbeatInfo } from './ForjaHandoffs';
import ForjaRoleBadge from './ForjaRoleBadge';

// --- Contrato de ferramentas (estático, do protótipo aprovado) ----------------

type Perm = 'ok' | 'propoe' | 'deny';

const PERM_LABEL: Record<Perm, string> = {
  ok: 'permitido',
  propoe: 'propõe',
  deny: 'negado',
};

interface Tool {
  tool: string;
  acao: string;
  perm: Perm;
  nota: string;
}

// VERBATIM de prototipo-ui/cowork/forja-data.jsx `FORJA_MCP_TOOLS`.
const TOOLS: Tool[] = [
  { tool: 'backlog.read', acao: 'ler issues / filtros', perm: 'ok', nota: 'leitura livre' },
  { tool: 'changelog.read', acao: 'o que shippou', perm: 'ok', nota: 'leitura livre' },
  { tool: 'issue.transition', acao: 'mover fase', perm: 'propoe', nota: 'propõe → [W] aprova' },
  { tool: 'changelog.append', acao: 'registrar entrega', perm: 'propoe', nota: 'propõe → transporte' },
  { tool: 'adr.propose', acao: 'cria _PROPOSTA', perm: 'propoe', nota: 'nunca decisions/NNNN' },
  { tool: 'git.merge', acao: 'fechar PR', perm: 'deny', nota: 'só [W2]' },
  { tool: 'constituicao.edit', acao: 'ADR/PROTOCOL/BRIEFING', perm: 'deny', nota: 'só [W]' },
  { tool: 'handoff-pending', acao: 'puxar handoff F1→F3', perm: 'ok', nota: 'Code lê, assinado' },
  { tool: 'handoff-ack', acao: 'confirmar aplicado + gate', perm: 'propoe', nota: '422 sem gate verde' },
];

// --- Tokens ativos (estático) -------------------------------------------------
// Tier 0 (ADR 0081): só o nome LÓGICO do token — nunca o valor raw.

interface Token {
  id: string;
  papel: string;
  escopo: string;
  exp: string;
  uso: string;
}

// VERBATIM de `FORJA_MCP_TOKENS`.
const TOKENS: Token[] = [
  { id: 'frj_cc_live', papel: 'CC', escopo: 'read + propose', exp: '30d', uso: 'há 2 min' },
  { id: 'frj_cl_ci', papel: 'CL', escopo: 'read + propose', exp: '90d', uso: 'há 1 h' },
  { id: 'frj_cd_rev', papel: 'CD', escopo: 'read', exp: '30d', uso: 'há 3 h' },
];

// --- Auditoria (estático) — toda ação de agente, regra 6 mecanizada -----------

interface AuditRow {
  ts: string;
  ator: string;
  tool: string;
  args: string;
  res: string;
  deny: boolean;
}

// VERBATIM de `FORJA_MCP_AUDIT`.
const AUDIT: AuditRow[] = [
  { ts: '14:21', ator: 'CC', tool: 'backlog.read', args: 'onda=FA-1', res: 'ok · 3 issues', deny: false },
  { ts: '14:19', ator: 'CC', tool: 'adr.propose', args: '--origin-DEV', res: 'proposta criada', deny: false },
  { ts: '13:50', ator: 'CL', tool: 'issue.transition', args: 'FORJA-141 →F3', res: 'aguarda [W]', deny: false },
  { ts: '12:30', ator: 'CC', tool: 'git.merge', args: '#2417', res: 'NEGADO — só [W2]', deny: true },
  { ts: '11:05', ator: 'CD', tool: 'changelog.read', args: 'desde 09/06', res: 'ok · 8 entradas', deny: false },
  { ts: '10:02', ator: 'CC', tool: 'constituicao.edit', args: 'ADR 0235', res: 'NEGADO — só [W]', deny: true },
];

export default function ForjaMcp({
  handoffs,
  heartbeat,
}: {
  handoffs?: HandoffItem[];
  heartbeat?: HeartbeatInfo;
}) {
  return (
    <div data-testid="forja-mcp" className="fj-mcp">
      <div className="fj-mcp-intro">
        <span className="fj-mcp-tag">mockado</span>
        Contrato e auditoria como <b>design</b> — o enforcement real é do servidor TeamMcp ([CL]).
        Default = <b>read + propose</b>; <code>merge</code> e <code>constituicao.edit</code> negados no
        contrato, não por convenção.
      </div>

      {/* HANDOFFS F1→F3 — o único dado VIVO desta aba (ForjaMcpService). */}
      <Deferred data={['handoffs', 'heartbeat']} fallback={<HandoffsSkeleton />}>
        <ForjaHandoffs handoffs={handoffs} heartbeat={heartbeat} />
      </Deferred>

      <div className="fj-mcp-grid">
        <section className="fj-mcp-card">
          <h3>Contrato de ferramentas</h3>
          <table className="fj-mcp-tbl">
            <thead>
              <tr>
                <th>Ferramenta</th>
                <th>Ação</th>
                <th>Permissão</th>
              </tr>
            </thead>
            <tbody>
              {TOOLS.map((t) => (
                <tr key={t.tool}>
                  <td className="mono">{t.tool}</td>
                  <td>{t.acao}</td>
                  <td>
                    <span className={`fj-perm fj-perm-${t.perm}`} data-testid="forja-mcp-perm">
                      {PERM_LABEL[t.perm]}
                    </span>
                    <span className="fj-perm-nota">{t.nota}</span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>

        <section className="fj-mcp-card">
          <h3>Tokens ativos</h3>
          <ul className="fj-token-list">
            {TOKENS.map((tk) => (
              <li key={tk.id} data-testid="forja-mcp-token">
                <ForjaRoleBadge role={tk.papel} />
                {/* Nome LÓGICO do token — NUNCA o valor raw (Tier 0 · ADR 0081). */}
                <span className="fj-token-id mono">{tk.id}</span>
                <span className="fj-token-scope">{tk.escopo}</span>
                <span className="fj-token-meta">
                  exp {tk.exp} · uso {tk.uso}
                </span>
                <button type="button" className="fj-token-revoke" data-testid="forja-mcp-revogar">
                  revogar
                </button>
              </li>
            ))}
          </ul>
        </section>
      </div>

      <section className="fj-mcp-card">
        <h3>Auditoria · toda ação de agente (Regra 6 mecanizada)</h3>
        <ul className="fj-audit">
          {AUDIT.map((a, i) => (
            <li key={`${a.ts}-${a.tool}-${i}`} className={a.deny ? 'deny' : ''} data-testid="forja-mcp-audit-row">
              <span className="fj-audit-ts mono">{a.ts}</span>
              <ForjaRoleBadge role={a.ator} />
              <span className="fj-audit-tool mono">{a.tool}</span>
              <span className="fj-audit-args mono">{a.args}</span>
              <span className={`fj-audit-res${a.deny ? ' deny' : ''}`}>{a.res}</span>
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
