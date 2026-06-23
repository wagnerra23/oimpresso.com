---
date: "2026-06-23"
time: "17:35 BRT"
slug: modo-suporte-adr0305-backend-fase-b
tldr: "Ratificação do ADR 0305 (Modo Suporte) + backend completo da fase B read-only no main em 6 PRs; UI parqueada (design-gated, drafts no stash local). Backend aditivo e dormente — 0 rotas usam, deploy não muda runtime."
decided_by: [W]
cycle: "CYCLE-08"
prs: [3260, 3261, 3263, 3264, 3266, 3267]
related_adrs: ["0305-modo-suporte-cross-tenant-exceto-operador", "0093-multi-tenant-isolation-tier-0"]
next_steps: ["retomar suporte → PR-C2 (UI design-gated: .tsx cópia-literal do screenshot aprovado + controller+rotas+menu juntos)", "fase A atuar: switch de contexto, com auditoria de scoping antes", "PR-D conceder/revogar capability", "Wagner: aprovar mockup + decidir trigger MySQL no support_access_logs"]
---

# Handoff — Modo Suporte: ratificação ADR 0305 + backend fase B no main (UI design-gated, parqueada)

## Estado MCP no momento
**MCP Oimpresso DESCONECTADO** nesta sessão (tools `mcp__Oimpresso_MCP__*` caíram) — usei **git/gh como fonte de verdade**. CYCLE não consultável via MCP. Webhook GitHub→MCP propaga o ADR/docs em ~2min. Tasks NÃO criadas (MCP off) — backlog de continuação está aqui + na SPEC.

## O que aconteceu
Pedido: implementar o **Modo Suporte** (capability cross-tenant a TODAS empresas-cliente EXCETO a operadora biz=1, via config, auditado, sem escalonamento). **Pré-requisitos não estavam cumpridos** (PR #3260 OPEN + proposta não-ratificada) → **parei e perguntei** (regra: não implementar decisão não-aceita). Wagner: "preparo ADR 0305 + plano".
- **#3261** ratifica a proposta → **ADR canon 0305** (`status: aceito`) + índice regenerado. Wagner mergeou.
- **#3263 (PR-A)** resolução: `config/constants.php operator_business_id` + `App\Services\Support\SupportAccessService` (único ponto que exclui a operadora) + bridge `App\SupportAgent` + migration `support_agents` + Pest Tier 0.
- **#3264 (PR-B)** auditoria append-only: `App\SupportAccessLog` (update/delete barrados no boot) + `App\Services\Support\SupportAuditService` + migration `support_access_logs` + Pest.
- **#3266 (PR-C1)** middleware `App\Http\Middleware\EnsureSupportAccess` (+ alias `support.access`). **MERGEOU QUEBRADO**: trazia controller+rotas que renderizavam Inertia pra pages inexistentes → `OrphanRenderGate` (não-required) vermelho, auto-merge passou nos required mesmo assim.
- **#3267 (fix)** removeu o controller+rotas órfãos (mantendo o middleware, testado via rota sintética) → **OrphanRenderGate verde no main**.
- **#3270 (docs)** charter + mockup visual da tela — **fechada a pedido do Wagner** (reduzir pontas); branch deletada; **drafts preservados** em `~/.claude/oimpresso-local/suporte-drafts/`.

## Artefatos gerados (no `main`)
`app/Services/Support/{SupportAccessService,SupportAuditService}.php` · `app/{SupportAgent,SupportAccessLog}.php` · `app/Http/Middleware/EnsureSupportAccess.php` + alias no Kernel · 2 migrations (`support_agents`, `support_access_logs`) · 3 testes `tests/Feature/Support/*` · ADR `memory/decisions/0305-modo-suporte-cross-tenant-exceto-operador.md` · SPEC `memory/requisitos/Suporte/SPEC.md` (via #3260). **Backend aditivo e DORMENTE**: 0 rotas usam → deploy do main não muda runtime; migrations idempotentes (tabelas novas).

## Persistência
- **git canon**: 6 PRs mergeados no main (#3260/3261/3263/3264/3266/3267).
- **MCP**: desconectado na sessão — webhook sincroniza ADR/SPEC ao propagar.
- **local (ADR 0131)**: charter + mockup em `~/.claude/oimpresso-local/suporte-drafts/`.

## Próximos passos pra retomar
Dizer **"retomar suporte"** → eu recupero charter+mockup do stash local, e faço a **PR-C2** como unidade design-gated: `.tsx` (`Suporte/Empresas` + `Suporte/Visao`) **cópia literal do screenshot aprovado pelo Wagner (R2/R7)** + re-adiciona controller+rotas+menu JUNTO (page+render nascem juntos → OrphanRender fica verde). Depois: **fase A "atuar"** (switch de contexto de sessão — exige **auditoria das vias de scoping** `$user->business_id` vs `session` antes, senão vaza tenant) + **PR-D** (UI conceder/revogar capability). Aberto p/ Wagner: aprovar o mockup; trigger MySQL no `support_access_logs` (hoje Model-level).

## Lições catalogadas
- **`gh pr merge --auto` ignora gates NÃO-required** → #3266 entrou com `OrphanRenderGate` vermelho. Pra PR que toca UI/rotas, conferir TODOS os gates (incl. não-required) antes de armar auto-merge, ou não auto-mergear UI.
- **UI é design-gated, não autônoma**: `OrphanRenderGate` exige a page existir junto do `Inertia::render`; + aprovação de SCREENSHOT do Wagner (R2/R7). Tela = unidade única (controller+rotas+`.tsx`), não fatia backend-first.
- **`Gate::before` (AuthServiceProvider) dá `true` a qualquer `Admin#<business>`** pra abilities não-superadmin → decidir acesso de suporte por Gate vazaria a operadora. Autoridade ficou **service-direct** (`SupportAccessService`), com teste de regressão (Admin#biz não alcança a operadora).
- **Sessões paralelas**: o WIP da PR-A já estava no worktree (sessão irmã) → **reaproveitei + commitei** (disciplina "commit cedo c/ lista explícita") em vez de duplicar.
- **#3260 mergeou no meio** (squash) — pré-requisito 1 virou cumprido em voo; re-homei a entrega na branch certa.

## Pointers detalhados
[ADR 0305](../decisions/0305-modo-suporte-cross-tenant-exceto-operador.md) · [SPEC Suporte](../requisitos/Suporte/SPEC.md) · [ADR 0093 multi-tenant Tier 0](../decisions/0093-multi-tenant-isolation-tier-0.md) · drafts em `~/.claude/oimpresso-local/suporte-drafts/`.
