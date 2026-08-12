---
id: requisitos-ads-spec
module: ADS
version: "1.0"
last_updated: "2026-06-13"
owner: wagner
na_justified:
  D6.b: "ADS é meta-sistema dormente (ARQ-0011 aguardando S5 ~jul/2026 — ver ADR 0105 cliente como sinal qualificado). p99 <500ms via OTel N/A enquanto módulo não está em prod ativa — sem tráfego pra medir."
  D6.c: "ADS Brain A roda no CT 100 (Node.js daemon), Brain B é Anthropic API. Hostinger expõe APENAS POST /api/ads/route (síncrono ~50ms). Não há queries paginate/eager-load com risco N+1 — ADS é firewall de decisão, não CRUD."
  D9.b: "ADS pre-S5 não tem jobs assíncronos Horizon — Brain B chamadas via cron `ads:process-brain-b` artisan direto. failed_jobs N/A enquanto pipeline não ativa (ADR 0105 dormant)."
related_adrs: [0105-cliente-como-sinal-guiar-sem-mandar, 0153-module-grade-rubrica-v1, 0154-module-grade-v2-na-justificado]
---
<!-- schema-allowlist: ADS é meta-sistema dormente sem backlog de US (ARQ-0001..0011 são ADRs de arquitetura, não user stories) — aguarda S5 ~jul/2026 (ADR 0105 cliente como sinal). Sem backlog ativo até ativação. -->

# ADS — Adaptive Decision System

> Módulo Laravel: `Modules/ADS/` (a criar)
> ADRs: `memory/requisitos/ADS/adr/arq/`

## O que é

Meta-sistema que orquestra todos os agentes do oimpresso. Não executa código diretamente.
Recebe eventos, decide qual agente age, com qual autoridade, e retroalimenta o sistema.

## ADRs canônicos

| # | Slug | Status |
|---|---|---|
| ARQ-0001 | [ads-escopo-e-papel-unico](adr/arq/ARQ-0001-ads-escopo-e-papel-unico.md) | accepted |
| ARQ-0002 | [dual-brain-papeis](adr/arq/ARQ-0002-dual-brain-papeis.md) | accepted |
| ARQ-0003 | [decision-router-algoritmo](adr/arq/ARQ-0003-decision-router-algoritmo.md) | accepted |
| ARQ-0004 | [risk-engine-formula-e-priors](adr/arq/ARQ-0004-risk-engine-formula-e-priors.md) | accepted |
| ARQ-0005 | [confidence-engine](adr/arq/ARQ-0005-confidence-engine.md) | accepted |
| ARQ-0006 | [policy-engine-firewall](adr/arq/ARQ-0006-policy-engine-firewall.md) | accepted |
| ARQ-0007 | [learning-loop-tres-niveis](adr/arq/ARQ-0007-learning-loop-tres-niveis.md) | accepted |
| ARQ-0008 | [hitl-quatro-niveis](adr/arq/ARQ-0008-hitl-quatro-niveis.md) | accepted |
| ARQ-0009 | [decision-memory-schema](adr/arq/ARQ-0009-decision-memory-schema.md) | accepted |
| ARQ-0010 | [governance-conflito-hierarquia](adr/arq/ARQ-0010-governance-conflito-hierarquia.md) | accepted |
| ARQ-0011 | [topologia-deployment](adr/arq/ARQ-0011-topologia-deployment.md) | accepted |

## Módulos clientes do ADS

O ADS é agnóstico de domínio. Estes módulos submetem eventos a ele:

| Módulo | Papel no ADS |
|---|---|
| `Modules/Jana/` | MCP bus compartilhado; Jana Chat NÃO submete eventos ao ADS |
| `EvolutionAgent/` | Submete eventos de oportunidade de evolução de codebase |
| `Brain A daemon` | Submete eventos de monitoramento (git, logs, métricas) |
| `TaskRegistry/` | Recebe tasks criadas pelo ADS; não submete eventos |

## Topologia de deployment (ARQ-0011)

| Ambiente | Componentes |
|---|---|
| **Hostinger** (app web) | Modules/ADS/ completo · 5 tabelas mcp_dual_brain_* · UI /ads/admin/decisoes · POST /api/ads/route · GET /api/ads/recent-{commits,errors} · cron `ads:process-brain-b` |
| **CT 100 Proxmox** | Brain A daemon (Node.js, systemd) · Ollama qwen2.5-coder:14b · OllamaClient → localhost:11434 · watchers HTTP poll Hostinger |
| **Anthropic API** | Brain B (Sonnet/Opus) — chamado pelo cron Hostinger |

## Stack técnico

| Componente | Tecnologia |
|---|---|
| Brain A | Node.js daemon + Ollama HTTP API |
| Brain B | `BrainBService.php` + `laravel/ai` + claude-sonnet-4-6 |
| Policy Engine | `PolicyEngine.php` — código PHP puro, sem DB |
| Risk Engine | `RiskEngine.php` — código PHP puro |
| Confidence Engine | `ConfidenceEngine.php` + tabela `mcp_confidence_scores` |
| Decision Router | `DecisionRouter.php` + tabela `mcp_file_locks` |
| Decision Memory | Tabela `mcp_dual_brain_decisions` |
| Learning Loop L1/L2 | Laravel Observer + Command semanal |
| Learning Loop L3 | Command mensal usando Brain B |

### US-ADS-001 · Audit Tier 0 — escopar os ~85 DB::table('mcp_*') crus por business_id

> owner: — · priority: p1 · estimate: 8h · status: todo · type: story
> blocked_by: —

**Implementado em:** _pendente_ — US status todo; inventário, fixes por call-site e lint dos DB::table mcp_* crus não iniciados (o fix pontual pré-US no ContextForTaskService já landou via PR #3162, fora do escopo desta US)

**Origem:** rodada adversarial do ADR 0296 (achado S-2). O vazamento cross-tenant pontual em `ContextForTaskService::buildRecentDecisions` já foi corrigido (PR #3162), mas o adversário apontou ~85 `DB::table('mcp_*')` crus fora do global scope.

**Aceite:**
- [ ] Inventariar todos os `DB::table('mcp_dual_brain_decisions')` e `DB::table('mcp_*')` (Grupo B / com business_id, ADR 0280) que leem/mutam sem `->where('business_id', …)`.
- [ ] Classificar cada um: leak real (request per-tenant) vs by-id system worker (ok) vs admin-scoped.
- [ ] Corrigir os leaks reais + teste cross-tenant POR call-site (exercitando o serviço, não só a query crua).
- [ ] Lint/gate que reprova `DB::table('mcp_*')` sem filtro `business_id` em código novo.

Refs: ADR 0093 (Tier 0 IRREVOGÁVEL) · ADR 0296 (S-2) · PR #3162 (fix pontual).

### US-ADS-002 · Elevar tela Admin/Graph a ≥70 (extrair HEX inline p/ tokens + a11y)

> owner: — · priority: p3 · status: todo · type: story
> blocked_by: —

Achado Onda 1 (re-grade telas stale). `ads/Admin/Graph` = **68 (Developing)**. Tela interna (admin ADS).

Gaps (`ads-admin-graph.yaml`):
- **Pre-Flight (médio):** `nodeStyle/MiniMap/Legend` cravam HEX cru (`#3b82f6,#fef3c7,#ef4444`...) em inline style — violação dura AP hex; extrair pra tokens CSS vars.
- **Mobile-fit (médio):** canvas fixo `height:700` + layout absoluto `cx600/cy350` não responsivo; sem fallback lista `<md`.
- **A11y-WCAG (médio):** grafo só-visual sem alternativa textual/tabela equivalente; nodes não focáveis por teclado.

DoD: nota ≥70 + ratchet verde. Charter + gate visual antes de Editar a Page.

### US-ADS-003 · Portfólio de Projects — lista + KPIs estratégicos

> owner: — · priority: p2 · status: done · type: story
> blocked_by: —

**Implementado em:** `Modules/Forja/Http/Controllers/Admin/ProjectsController.php` (`index`, `store`) · rotas `ads.admin.projects.index|store` (`Modules/Forja/Http/routes.php`) · tela `Modules/Forja/Resources/js/Pages/ads/Admin/Projects.tsx` (+ `Projects.charter.md`)

**Origem:** US escrita a posteriori (2026-07-30) pra ancorar duas telas que já rodavam sem US declarada — o `charter-us-lint` acusou `related_us` ausente quando o rename ProjectMgmt→Forja (PR #5089) tocou os charters. Não é feature nova: descreve o que o código já faz.

**Contexto:** o Project é a unidade estratégica do ADS — agrupa decisões, ADRs e a decomposição em parts. O controller vive em `Modules/Forja` (dono do domínio Jira-style, `mcp_jira_projects`) mas serve sob `/ads/*`, porque quem consome é o painel de decisão automatizada.

**Testado em:** `tests/Feature/Ads/AdsProjectsRoutesContratoTest.php`

**Aceite:**
- [x] `GET /ads/admin/projects` lista os Projects via `ProjectService::list()` e devolve KPIs agregados via `calculateKpis()`.
- [x] `POST /ads/admin/projects` cria Project validando por `StoreProjectRequest`.
- [x] Render Inertia em `ads/Admin/Projects` com as props `projects` + `kpis`.

Refs: ADR 0070 (Jira-style) · ADR 0088 + PR #5089 (rename ProjectMgmt→Forja; controller mudou de namespace, URL `/ads/*` preservada).

### US-ADS-004 · Detalhe do Project — parts decompostas + ação de decompose

> owner: — · priority: p2 · status: done · type: story
> blocked_by: US-ADS-003

**Implementado em:** `Modules/Forja/Http/Controllers/Admin/ProjectsController.php` (`show`, `decompose`) · `Modules/Forja/Services/ProjectDecomposerService.php` · rotas `ads.admin.projects.show|decompose` (`Modules/Forja/Http/routes.php`) · tela `Modules/Forja/Resources/js/Pages/ads/Admin/ProjectShow.tsx` (+ `ProjectShow.charter.md`)

> O `DecomposeProjectRequest` saiu desta âncora na parte 6 (ADR 0363): morava no ADS e foi removido com o módulo. Não houve substituto porque o `decompose` do `ProjectsController` da Forja recebe `Illuminate\Http\Request` genérico — conferido ANTES de deletar — e a validação do `id` é da rota (`whereNumber`).

**Origem:** idem US-ADS-003 — ancoragem a posteriori de tela já existente.

**Contexto:** é o único ponto de escrita nas tabelas `mcp_projects`/`mcp_project_parts`. O acoplamento que existia aqui (service do ADS, controller da Forja) **acabou em 2026-07-31**: o `ProjectDecomposerService` passou pra `Modules/Forja/Services/`, junto do consumidor. O que ele ainda importa do ADS — `DecisionLinksService` e `Ai\Agents\ProjectDecomposerAgent` — é resíduo transitório, endereçado na remoção do núcleo do ADS.

**Testado em:** `tests/Feature/Ads/AdsProjectsRoutesContratoTest.php`

**Aceite:**
- [x] `GET /ads/admin/projects/{id}` (`whereNumber`) devolve o detalhe via `findDetail()` e **404 quando o id não existe** (`ModelNotFoundException` → `abort(404)`).
- [x] `POST /ads/admin/projects/{id}/decompose` valida por `DecomposeProjectRequest` e delega ao `ProjectDecomposerService`.
- [x] Render Inertia em `ads/Admin/ProjectShow` com KPIs, parts e decisões geradas.

Refs: ADR 0070 · `memory/requisitos/ADS/DEPRECATION-PLAN.md` (acoplamento ADS↔Forja) · PR #5089.
