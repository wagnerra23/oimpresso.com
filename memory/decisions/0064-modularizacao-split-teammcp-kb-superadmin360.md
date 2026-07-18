---
slug: 0064-modularizacao-split-teammcp-kb-superadmin360
number: 64
title: "Modularização — split TeamMcp + KB + Superadmin 360°"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-04"
module: null
quarter: 2026-Q2
tags: [modularização, copiloto, teammcp, kb, superadmin, governance, iam]
supersedes: []
supersedes_partially: [0055-self-host-team-plan-equivalente-anthropic, 0057-tela-team-admin-regras-governanca-tokens-mcp, 0059-governanca-memoria-estilo-anthropic-team, 0061-conhecimento-canonico-git-mcp-zero-automem]
superseded_by: []
related: [0027-gestao-memoria-roles-claros, 0053-mcp-server-governanca-como-produto, 0040-policy-publicacao-claude-supervisiona, 0065-permission-registry-contract]
pii: false
review_triggers:
  - "Quando 3+ engines de governança disputarem a mesma tabela de regras → reconsiderar módulo Governance separado"
  - "Quando TeamMcp ganhar entidades próprias (não depender mais de Modules\\Jana\\Entities\\Mcp) → revisitar dependência inversa"
---

# ADR 0064 — Modularização — split TeamMcp + KB + Superadmin 360°

## Contexto

Em sessão de design 2026-05-03/04, Wagner identificou que `Modules/Copiloto` e `Modules/ADS` estavam acumulando responsabilidades que conceitualmente pertenciam a 3 contextos diferentes:

1. **Copiloto** = agente IA *interno* (Larissa/operadores conversam dentro do ERP)
2. **Agentes externos** = Claude Code do time (Maíra/Felipe/Luiz/Eliana) acessando via MCP
3. **Knowledge Base** = biblioteca de ADRs/sessions/runbooks/comparativos (consumida por todos)

Telas que viviam em `/copiloto/admin/*` mas conceitualmente eram de "Team MCP" (governança de agentes externos): `/copiloto/admin/team` (tokens), `/copiloto/admin/tasks` (kanban backlog), `/copiloto/admin/cc-sessions` (auditoria sessões CC). Misturadas com chat IA + custos + governança Copiloto-real.

Existiam **2 telas duplicadas de KB**: `/copiloto/admin/memoria` (canônica, 352 docs) e `/ads/admin/kb` (versão simples redundante). Wagner sentiu que "ADS e Copiloto estão competindo por funções".

Adicionalmente, Wagner relatou dor histórica de **roubo de funcionário**: "não conseguir ver o todo deixou os funcionários roubar recursos precisos da empresa. quando dá problema com usuário e tem que trancar por um roubo, ele gosta de ver tudo sobre o usuário. não é legal ficar pulando de galho em galho pra saber o que tem de permissão". Sem visualização IAM unificada por usuário.

Avaliou-se criar módulo Governance separado pra centralizar regras (RBAC + Policy + DSL meta-skills + lifecycle KB). Foi descartado nesta etapa por over-engineering (5 pessoas no time, sem 3+ engines disputando regras hoje).

## Decisão

**4 PRs mergeados na main em 2026-05-04** que estabelecem fronteira clara entre os módulos:

1. **`feat/split-teammcp`** — `Modules/TeamMcp/` novo: TeamController + TasksAdminController + CcSessionsController movidos do Copiloto. URLs `/team-mcp/{team,tasks,cc-sessions}`. Redirects 301 GET das URLs antigas.
2. **`feat/split-kb`** — `Modules/KB/` novo: `MemoriaKbController` → `KbController`. URL `/kb`. Redirects 301 das URLs antigas em `/copiloto/admin/memoria*`. Tabela `mcp_memory_documents` permanece como única fonte (não duplicar).
3. **`feat/usuario-360`** — `Modules/Superadmin/Http/Controllers/Usuario360Controller`: tela `/superadmin/usuarios/{id}/360` agrega num lugar: roles Spatie + permissions efetivas (com risk colorido) + scopes ADS + tokens MCP + quotas + sessões + audit. Botão Trancar/Destrancar com snapshot JSON (`user_lockouts`).
4. **`feat/delete-ads-kb-duplicate`** — remove `KnowledgeBaseController` do ADS + duas Pages duplicadas. `/ads/admin/kb` vira redirect 301 pra `/kb`.

Fronteira de responsabilidade resultante:

| Módulo | Verbo | Responsabilidade |
|---|---|---|
| **Copiloto** | interage | agente IA interno (chat Larissa, custos, governança Copiloto, qualidade) |
| **TeamMcp** | coordena | agentes externos (tokens, quotas, kanban backlog, auditoria CC) |
| **ADS** | decide | Dual Brain decision + skills + meta-skills + scopes per-user×module + risk |
| **KB** | armazena | 352 docs (ADRs/sessions/runbooks/comparativos) + soft-delete + history |
| **Superadmin** | governa per-user | tela 360° + Permission Registry + lockout |

Permissions Spatie **não foram renomeadas** nesta etapa (`copiloto.mcp.memory.manage` continua mesmo no KB) — rename precisa migration de update + ADR próprio. Dívida técnica documentada.

## Justificativa

**Por que não Governance separado agora?** ADR descarta criar `Modules/Governance/` por enquanto:

1. Wagner descreveu dor real só **per-user** (visualizar permissions de Maíra pra trancar no caso de roubo). Sistêmico (lifecycle ADR, constituição) é problema teórico, não dor de R$.
2. Hoje os "engines de governança" estão isolados por contexto: Spatie cuida de auth, ADS cuida de decisão, KB cuida de armazenamento — não há conflito real disputando uma tabela.
3. Equipe atual (5 pessoas) não justifica overhead de módulo magro. Princípio: **criar módulo quando a dor existe**, não quando o livro recomenda.

**Trigger pra criar Governance no futuro** (review_triggers documentado):
- 3+ engines disputando a mesma tabela de regras
- RBAC Spatie + Policy hardcoded + DSL meta-skills + lifecycle KB ficarem em 4 lugares conflitantes

**Por que dependência inversa TeamMcp → Copiloto é aceitável?** TeamMcp usa `Modules\Jana\Entities\Mcp\McpToken/McpQuota/McpTask/McpCcSession/McpCcMessage` e tabelas `mcp_*` (migrations no Copiloto). Não é módulo standalone. Aceitável nesta etapa porque:

- Schema único de tabelas mcp_* serve os 3 módulos (Copiloto + TeamMcp + ADS)
- Mover Entities pro TeamMcp criaria churn sem ganho claro (rename + redirect classes)
- Trigger pra revisitar: quando TeamMcp tiver entidades **próprias** (não-mcp_*)

## Consequências

**Positivas:**

- Cada módulo tem propósito claro — onboarding mais rápido, menu mais simples
- KB passa a ser fonte neutra (consumida por Copiloto + TeamMcp + ADS sem dono semântico de produção)
- Tela Usuário 360° resolve dor real de R$ (incidente de roubo histórico)
- Permission Registry (ver ADR 0065) vira fundação pra qualquer módulo declarar permissions auto-discovered
- Redirects 301 mantêm bookmarks/links externos vivos durante a transição

**Negativas / Trade-offs:**

- TeamMcp depende inversamente do Copiloto (não-standalone) — desabilitar Copiloto quebra TeamMcp
- 4 ADRs anteriores ficaram com URLs antigas no texto (0055/0057/0059/0061 + _SCHEMA) — atualização cosmética em US-COPI-076
- Permissions Spatie ainda têm namespace `copiloto.*` mesmo em telas movidas — dívida técnica que vira ADR próprio quando necessário
- 4 worktrees auxiliares foram criados durante a transição (limpos pós-merge)

**Riscos mitigados:**

- Conflito de merge `Modules/Copiloto/Http/routes.php` e `topnav.php` foi resolvido manualmente nos PRs sequenciais — esperado, trivial
- Build Vite atualizado em prod (`npm run build:inertia`) pra resolver Pages novos `kb/Index`, `team-mcp/{Team,Tasks,CcSessions}/Index`, `superadmin/Usuario360/{Index,Show}` — sem rebuild quebra com "null.component"
- ContextForTaskService passou a consumir mcp_tasks (US-COPI-077) eliminando a dependência circular do CURRENT.md filesystem que rotineiramente ficava desatualizado

## Referências

- ADR 0027 — Gestão memória, papéis claros (formaliza fronteira KB↔outros)
- ADR 0053 — MCP server governança como produto (origem da KB cache governada)
- ADR 0040 — Claude supervisiona, não pergunta sobre rotineira reversível (autorizou modularização sem PR-by-PR)
- ADR 0061 — Conhecimento canônico = git → MCP (KB neutra reforça esse fluxo)
- ADR 0065 — Permission Registry contract (subordinada a esta — declarativo per-módulo)
- PRs `feat/split-teammcp`, `feat/usuario-360`, `feat/split-kb`, `feat/delete-ads-kb-duplicate` (mergeados em main 2026-05-04 commits 980d218f / 7bf5099e / 9f097adb / 9793da2d)
