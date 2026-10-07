---
id: resources-js-pages-team-mcp-cc-sessions-index-casos
casos: Sessões CC · feed + thread · /team-mcp/cc-sessions
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-10-07"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente das lanes PHP / Pest (Unit) e PHP / Pest (Forja · MySQL)"
---

# Casos de uso — /team-mcp/cc-sessions

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Os UC derivam do **contrato** — [`Index.charter.md`](Index.charter.md) (lei) + [`cc-sessions-visual-comparison.md`](../../../../../../../memory/requisitos/TeamMcp/cc-sessions-visual-comparison.md) (aprovado [W] 2026-06-16) + [SDD do hub](../../../../../../../memory/requisitos/TeamMcp/SDD-tela-hub-team-mcp-v1.0.md) (§3, §5.1, CU-TEAM-09/13) + [SPEC-cc-sessions](../../../../../../../memory/requisitos/Jana/SPEC-cc-sessions.md) — **nunca** do `.tsx`. O `CcSessionsController` só confirma o comportamento. Não há Blade nem Delphi para esta tela: ela nasceu Inertia ([SDD §0.2](../../../../../../../memory/requisitos/TeamMcp/SDD-tela-hub-team-mcp-v1.0.md)), logo nenhum caso aqui se justifica por "o legado fazia assim".
>
> Persona: **[W]** (`jana.cc.read.all`, vê o time) e **cada dev** (`jana.cc.read.team`, vê só as próprias). Tela read-only.

> ⚠️ **Todos os UC nascem 🧪, e há duas forças de prova** no [`CcSessionsContratoTest.php`](../../../../../Tests/Feature/CcSessionsContratoTest.php):
> - **registro** (rota, middleware, verbo, escopo de query) — roda na lane sqlite `PHP / Pest (Unit)`, que **bloqueia merge** (está em `governance/required-checks-baseline.json`);
> - **request** (403, 404 de sessão alheia, thread truncada, paginação) — só dá veredito na lane MySQL `PHP / Pest (Forja · MySQL)`, que é **advisory** (reprova visível, não bloqueia). Em sqlite essas pernas **pulam**, e skip não é cobertura.
>
> O arquivo entrou no `forja-pest.yml` **failing-first** (Pest local é proibido; o checkout do CT 100 está atrás do `main`). O `✅` vem do manifesto `scripts/casos-test-results.json`, derivado do JUnit — **não se escreve à mão**.

## UC-CCS-01 — A rota abre a tela (a Page existe)
Status: 🧪 (2 testes citam este UC — rota `team-mcp.cc.index` → `CcSessionsController@index`, e a string do `Inertia::render` cruzada com o `.tsx` em disco.)
O charter declara `/team-mcp/cc-sessions` → `CcSessionsController` → `team-mcp/CcSessions/Index`; o SDD §1 lista a mesma tríade.
**Pronto quando:** a rota está registrada no controller certo e o componente que ele renderiza existe (sem Inertia 500).

## UC-CCS-02 — Acesso exige login + `jana.cc.read.team` `[T0]`
Status: 🧪 (2 testes citam este UC — **(a)** toda rota `team-mcp.cc.*` (index, show, search) carrega `auth` + o `can:` no registro, qualquer driver; **(b)** usuário novo, sem `Admin#` e sem a permissão, leva 403 — só na lane MySQL. Usuário novo de propósito: com admin o `Gate::before` libera tudo e o 403 seria falso-verde.)
Charter §Restrições Tier 0: *"Permissão `copiloto.cc.read.team` no construtor"* — hoje com o nome `jana.cc.read.team` ([SDD §5.1](../../../../../../../memory/requisitos/TeamMcp/SDD-tela-hub-team-mcp-v1.0.md): a permissão mudou de nome no #4853; a fonte da verdade é o construtor). Esta é a **única** tela do hub que não pede `jana.mcp.usage.all`.
**Pronto quando:** as rotas da tela exigem login + a permissão, e quem não a tem recebe 403.

## UC-CCS-03 — Sem `jana.cc.read.all`, o dev vê só as próprias sessões `[T0]`
Status: 🧪 (2 testes citam este UC — **(a)** o escopo `acessivelPara` filtra por `user_id` quando falta `read.all`, com **controle** (com `read.all` não filtra) e o caso nulo (`1=0`), qualquer driver; **(b)** na lane MySQL, abrir a thread de um colega devolve 404 e a própria abre.)
Charter §Restrições Tier 0: *"`jana.cc.read.all` libera ver todos, senão só próprias (backend `acessivelPara`)"*; visual-comparison §Matriz: *"RBAC read_all vs próprio — preservado"*.
**Pronto quando:** sem `read.all`, sessão de outro dev não aparece no feed nem abre no drawer (404).

## UC-CCS-04 — Sessão de outro business nunca aparece, nem para quem tem `read.all` `[T0]`
Status: 🧪 (1 teste cita este UC — usuário com `read.team`+`read.all` no tenant de teste (98, [ADR 0358](../../../../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)); sessão do próprio business abre (controle positivo) e a de outro business dá 404. Só na lane MySQL.)
`read.all` libera o **time**, nunca outro tenant — [ADR 0093](../../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) + charter §Restrições Tier 0 (*"scope de acesso é do backend"*). `mcp_cc_sessions` tem `business_id` próprio ([SDD §5.2](../../../../../../../memory/requisitos/TeamMcp/SDD-tela-hub-team-mcp-v1.0.md)), diferente de `mcp_tasks`.
**Pronto quando:** a thread de sessão de outro business responde 404 mesmo com `read.all`.

## UC-CCS-05 — A thread do drawer vem limitada a 500 mensagens, com aviso
Status: 🧪 (1 teste cita este UC — sessão com 503 mensagens chega com 500 e `truncated=true`; **controle**: sessão com 3 chega inteira e `truncated=false`. Só na lane MySQL.)
Charter §Goals: *"mensagens (≤500, flag truncated)"*; visual-comparison §Matriz: *"Detalhe show (thread ≤500 + truncated flag)"*.
**Pronto quando:** thread acima de 500 é cortada em 500 e marcada, e thread curta vem inteira sem a marca.

## UC-CCS-06 — Tela de leitura: nenhuma rota escreve
Status: 🧪 (1 teste cita este UC — toda rota `team-mcp.cc.*` é GET-only, lido do registro. Qualquer driver.)
Charter §Anti-hooks: *"NÃO escreve nada (tela 100% read-only)"*; Non-Goal: *"Editar/curar sessão ❌"*; SDD CU-TEAM-13.
**Pronto quando:** nenhuma rota da tela aceita verbo de escrita.

## UC-CCS-07 — Feed cronológico paginado em 25
Status: 🧪 (1 teste cita este UC — partial reload de `sessions` (é `Inertia::defer`), `per_page=25`, e a sessão mais recente vem antes da antiga. Só na lane MySQL.)
Charter §Goals: *"Feed cronológico"* + *"Paginator 25/pg"*; visual-comparison §Matriz: *"Paginator 25/pg + links"*; SPEC US-COPI-CC-001: *"paginação 25/page"*.
**Pronto quando:** o feed vem em páginas de 25, do mais recente para o mais antigo.

## Backlog (sem id — vira UC quando ganhar teste que o cite)

- [BACKLOG] **KPIs respeitam o mesmo recorte do feed** — sem `read.all`, sessões/custo/devs/top tools contam só as próprias. Contrato em 2 fontes (charter §Goals KPIs + SPEC US-COPI-CC-002), mas este PR não o cobre por teste; promover a UC sem teste criaria órfão no G-2.
- [BACKLOG] **Filtros (busca FULLTEXT, dev, status, projeto) restringem o feed** — charter §Goals + visual-comparison §Matriz. Mesmo motivo.
- Atalhos (J/K/Enter/`/`/Esc), drawer 640px e DS v6 **não são UC**: o juiz é gate (`conformance-gate`, eslint `ds/*`) e a ratificação visual é [W] — mesma decisão do SDD §6.5 para o `UC-FORJA-06`.

## Divergência aberta (decisão [W], não consertada aqui)

- **`cc.read.team` = ver o time, ou só as próprias?** A [SPEC-cc-sessions §2](../../../../../../../memory/requisitos/Jana/SPEC-cc-sessions.md) diz que `cc.read.team` *"ver sessões do time (Felipe, Maiara)"* e que Felipe busca *"como Wagner fez X"*. O charter (lei) e o código dizem o contrário: sem `read.all`, só as próprias. Pela [precedência](../../../../../../../memory/proibicoes.md) (*casos > charter > SPEC*) este arquivo segue o charter, e o UC-CCS-03 trava esse comportamento. Se o pretendido é o da SPEC, a mudança é de produto e de permissão — decisão [W], e o UC-CCS-03 muda no mesmo PR.
