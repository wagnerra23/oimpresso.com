# PROMPT PARA CODE — Forja → absorção em TeamMcp (re-skin + 1 tela nova)

> **Cole isto UMA vez no Claude Code.** Não toca em mais nada.
> Repo: `wagnerra23/oimpresso.com` · branch base `main`.
> Origem do design (F1, Cowork): protótipo **Forja** — URLs públicas abaixo (~1h; pedir regenerar se expirar).
> **Soberania:** NÃO editar constituição (ADR/PROTOCOL/BRIEFING). Merge só [W2]. Token raw nunca persistido/logado (Tier 0 ADR 0081).

---

## 0) Premissa (✓ lido @main 2026-06-16)

A **Forja NÃO é módulo novo.** É o **F1 (re-skin DS v6)** das telas que **já existem** em `Modules/TeamMcp` + **1 tela genuinamente nova** (Triagem/Analista). Backend, rotas, controllers, tabelas `mcp_*` e Inertia Pages **já estão em prod**. O trabalho é **front-end (Inertia/React Pages)** conformando ao DS v6 + preservando 100% das features atuais.

**Design source (Cowork — ler como referência visual/gramática, NÃO copiar CSS cru):**
- `forja-page.jsx` — https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/forja-page.jsx?t=db27cf3a538d905df86ed506a5892deab81520f18127d2b55faab40609dc1094.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781615907.fp&direct=1
- `forja-mcp.jsx` — https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/forja-mcp.jsx?t=da1d21f49ca9fad063121e55787877ad32dc2723d3621a95e9004598120ad674.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781615908.fp&direct=1
- `forja-data.jsx` — https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/forja-data.jsx?t=097edfc8a53d7b116f63490c8c134bdb88551a6d2697d709794f574e1264099d.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781615909.fp&direct=1
- `forja-page.css` — https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/forja-page.css?t=2a9b0dedd84dae2f09e6537baa764494d3d0e991dbe764acc299c23fd07fc065.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781615909.fp&direct=1
- `forja-integra.jsx` (mapa de absorção) — https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/forja-integra.jsx?t=4f251de988498038019edf0676d63adb712dbe1b5b8af5aeffa30932d4c438e5.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781615910.fp&direct=1

---

## 1) Features ATUAIS por tela (✓ lido @main — PRESERVAR no re-skin)

### `/team-mcp/tasks` — `TasksAdminController` → `team-mcp/Tasks/Index`
- **Kanban** status `todo/doing/review/done`; **drag-drop** → `PATCH /team-mcp/tasks/{taskId}/status` (válidos: todo/doing/review/done/blocked/cancelled) via `TaskCrudService`.
- **Backlog** 200 itens, ordenação `status` (doing→review→todo→blocked→done→cancelled) × `priority` (p0→p3).
- **Filtros**: `module`, `owner`, `sprint` (dropdowns distinct).
- **KPIs**: total, p0 abertas, doing, blocked, done, cancelled, `total_h` (soma estimate_h).
- Campos `McpTask`: `task_id, title, module, owner, sprint, priority(p0-p3), estimate_h, blocked_by[], status`.
- Perf: `Inertia::defer` em kanban/backlog/kpis/modulos/owners/sprints.

### `/team-mcp/cc-sessions` — `CcSessionsController` → `team-mcp/CcSessions/Index`
- **Paginator** 25/pg; filtros `user_id, from, to, q (FULLTEXT summary_auto), status, project_path`.
- **KPIs**: sessions_hoje, sessions_total, custo_hoje_brl, custo_30d_brl, devs_ativos_hoje, tools_top (5).
- **Detalhe** `show` (JSON): sessão + thread (até 500 msgs, `truncated` flag) + metadata.
- **Search** FULLTEXT cross-dev em `mcp_cc_messages.content_text` (`/cc-sessions/search`).
- **RBAC**: `jana.cc.read.all` (todos) vs próprio; `jana.cc.curate`.
- Dropdowns devs + projects. Campos sessão: project_path, git_branch, cc_version, entrypoint, started/ended, total_messages/tokens/cost_brl, status, summary_auto.

### `/team-mcp/scorecard` — `ScorecardController` (→ `ScorecardBuilderService`) → `team-mcp/Scorecard/Index`
- Pattern **Facts + Checks** (separa dado de juízo).
- **Facts**: tokens ativos, calls 7d, cost 7d, top tools, drift detectado.
- **Checks** (ok/fail): Tier-0 multi-tenant, governance gate verde, brief recente, audit sem PII.
- `meta`: generated_at, period_days=7, source `mcp_audit_log + mcp_tokens + mcp_briefs`.

### `/team-mcp/team` — `TeamController` → `team-mcp/Team/Index`
- **Tokens MCP**: gerar (`gerarToken`), **listar por dev** (`/team/{user}/tokens`), **revoke individual** (`/team/{user}/token/{tokenId}`), legacy revoke, **rotate** (`McpTokenIssuer::rotate`, raw 1× — Tier 0).
- **DXT** 1-clique (`gerarDxt`), **quota** (`atualizarQuota`), **export CSV** (`/team/export.csv`).
- **Identity Mesh**: `mcp_actors` (humano vs agente, manifest). Tabelas: `mcp_tokens, mcp_scopes, mcp_user_scopes, mcp_quotas`.
- Admin irmãos: `Admin/ToolsController` (tools registry) + `Admin/TeamScopesController` (RBAC).

---

## 2) Ondas (PRs) — ordem e critério (protocolo F3; cada PR para no gate visual)

> Cada PR = re-skin de **uma** Page Inertia pro DS v6 (roxo canon `oklch(0.55 0.15 295)`, tokens `ds-v6`, drawer lateral, sem cor crua, sem `rounded-xl+`, sem select/checkbox/radio nativo — usar Components/ui). **Preservar todas as props/rotas/features acima.** UI-Judge + `ui:lint` R1 + `conformance-gate` + `foundation-guard` precisam passar.

- **PR-1 · Tasks (Backlog + Quadro)** — `team-mcp/Tasks/Index.tsx` reescrito na gramática Forja: lista densa agrupável (onda=sprint / fase=status / papel=owner / prioridade / módulo) **+** aba Quadro (kanban drag→status, mantém o PATCH). Drawer do issue (fases, vínculos `blocked_by`, atividade). Teclado j/k/↵, ⌘K. Totalizador = KPIs atuais.
- **PR-2 · CcSessions (Changelog/Atividade)** — `team-mcp/CcSessions/Index.tsx` re-skin: feed cronológico + filtros existentes + drawer de detalhe (thread `show`) + busca FULLTEXT. Selo **agente vs humano** por sessão. Preservar KPIs e RBAC.
- **PR-3 · Scorecard (Saúde)** — `team-mcp/Scorecard/Index.tsx` re-skin: cards de métrica com **sparkline** + Facts/Checks (semáforo) + drill. Sem inventar métrica — só Facts+Checks atuais.
- **PR-4 · Team (painel MCP)** — `team-mcp/Team/Index.tsx` re-skin: contrato recurso×ação (tools), tokens por ator (gerar/rotate/revoke/quota/CSV/DXT), auditoria (`mcp_audit_log` read-only), Identity Mesh. Negar no contrato `git.merge`/`constituicao.edit` (display).
- **PR-5 · NOVA: Triagem/Analista [AN]** — única tela nova. Rota `GET /team-mcp/triagem` + `TriagemController`. Modela como **estado F0** das `mcp_tasks` (proposto→enriquecido→aprovado). Dossiê do analista: requisitos (charter do módulo), histórico de decisão (RAG em `mcp_memory_documents` + ADRs + cc-sessions), duplicatas (mesmo module), valor×esforço (prioridade × tipo), prioridade sugerida, fase/dono, risco Tier-0. Ações: Aprovar→backlog · Rebaixar · Fundir · Rejeitar. **Agente propõe, [W] aprova** (nada vira task oficial sem aprovação).
- **PR-6 · Shell/consolidação** — sidebar/topnav: item único apontando `/team-mcp/team` (fundir `projects`+`teammcp`); **aposentar/dedup o stub `Modules/ProjectMgmt`** (decisão [W]); alinhar visual de `Admin/Tools` e `Admin/TeamScopes`.

---

## 3) Transversais (todas as PRs)
- DS v6 tokens (cores/raio/sombra/foco/type ramp `--fs-*`). Roxo canon intacto. Teal só em **selo** de proveniência → propor token `--origin-DEV` via **_PROPOSTA** (não criar `--accent` por módulo).
- Atores: humano vs agente sempre marcado (regra: agente nunca se disfarça de humano).
- Frescor `✓ lido @main` / `⚠ inferido`: mapear pro **liveness do ingest** (`IngestLivenessService` / `mcp_ingest_heartbeat`).
- Sem dado fantasma: tela é projeção; escrita relevante = registro real (PATCH status já existe).
- Cada PR: advisory → 2 verdes → required. ADR só como **_PROPOSTA** (CL numera sob OK de [W]).

## 4) O que NÃO fazer
- ❌ Não criar módulo "Forja" novo — re-skin do TeamMcp.
- ❌ Não absorver browse de ADR/sessão pro painel (fica no **KB**); só cross-link.
- ❌ Não renomear permissões `copiloto.mcp.*` agora (quebra usuários — task futura).
- ❌ Não persistir/logar token raw. ❌ Não editar constituição. ❌ Merge só [W2].
