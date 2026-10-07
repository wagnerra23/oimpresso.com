---
id: resources-js-pages-team-mcp-team-index-casos
casos: Equipe · tokens MCP do time · /team-mcp/team
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-10-07"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente das lanes PHP / Pest (Unit) e PHP / Pest (Forja · MySQL)"
---

# Casos de uso — /team-mcp/team

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Os UC derivam do **contrato** — [`Index.charter.md`](Index.charter.md) (lei: Restrições Tier 0, Anti-hooks, Métricas) + [SPEC do TeamMcp](../../../../../../../memory/requisitos/TeamMcp/SPEC.md) (US-TEAM-002/003) + [SDD do hub](../../../../../../../memory/requisitos/TeamMcp/SDD-tela-hub-team-mcp-v1.0.md) (CU-TEAM-01/02/09) + [ADR 0057](../../../../../../../memory/decisions/0057-tela-team-admin-regras-governanca-tokens-mcp.md) + [ADR 0093](../../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — **nunca** do `.tsx`. O `TeamController` só confirma o comportamento. A tela nasceu Inertia: não há Blade nem Delphi para comparar ([SDD §0.2](../../../../../../../memory/requisitos/TeamMcp/SDD-tela-hub-team-mcp-v1.0.md)).
>
> Persona única: **[W]** com `jana.mcp.usage.all` (charter). É o único ponto de emissão e revogação de credencial MCP (ADR 0057), então quase todo caso aqui é `[T0]`.

> ⚠️ **Todos os UC nascem 🧪, e há duas forças de prova** no [`TeamEquipeContratoTest.php`](../../../../../Tests/Feature/TeamEquipeContratoTest.php):
> - **registro** (rota, middleware) — roda na lane sqlite `PHP / Pest (Unit)`, que **bloqueia merge** (está em `governance/required-checks-baseline.json`);
> - **request** (403, isolamento por business, reveal-once, revogação) — só dá veredito na lane MySQL `PHP / Pest (Forja · MySQL)`, que é **advisory**. Em sqlite essas pernas **pulam**, e skip não é cobertura.
>
> O arquivo entrou no `forja-pest.yml` **failing-first** (Pest local é proibido; o checkout do CT 100 está atrás do `main`). O `✅` vem do manifesto `scripts/casos-test-results.json` — **não se escreve à mão**.
>
> O `TokensListAndRevokeTest.php` já cobria parte disto, mas aceita 403 como resultado válido e só asserta dentro de `if (200)`: sem a permissão no ambiente, passa sem provar nada. Os testes deste contrato criam o operador **com** a permissão e exigem o status exato.

## UC-EQP-01 — A rota abre a tela (a Page existe)
Status: 🧪 (2 testes citam este UC — rota `team-mcp.team.index` → `TeamController@index`, e a string do `Inertia::render` cruzada com o `.tsx` em disco.)
O charter declara `/team-mcp/team` com backend `TeamController`; o SDD §1 lista a mesma rota e controller.
**Pronto quando:** a rota está registrada no controller certo e o componente que ele renderiza existe (sem Inertia 500).

## UC-EQP-02 — Acesso exige login + `jana.mcp.usage.all` em toda ação `[T0]`
Status: 🧪 (2 testes citam este UC — **(a)** toda rota `team-mcp.team.*` (tela, gerar token, gerar .dxt, drill-down, revogar, quota, CSV) carrega `auth` + o `can:` no registro, qualquer driver; **(b)** usuário novo sem a permissão leva 403 na tela e ao tentar gerar token, e nenhum token nasce — só na lane MySQL.)
Charter §Automation hooks: *"Permission gate `jana.mcp.usage.all` no construtor do Controller"*; SPEC US-TEAM-002; SDD CU-TEAM-09.
**Pronto quando:** toda ação da tela exige login + a permissão, e sem ela nada é emitido.

## UC-EQP-03 — O drill-down de tokens não alcança usuário de outro business `[T0]`
Status: 🧪 (1 teste cita este UC — usuário do próprio business devolve 200 (controle positivo) e usuário de outro business devolve 404. Só na lane MySQL.)
Charter §Restrições Tier 0: *"`listTokens` e `revokeToken` fazem `User::where('id', userId)->where('business_id', $sessionBusinessId)->firstOrFail()`"*; §Métricas: *"Wagner em biz=A NÃO consegue ver tokens de user de biz=B (… → 404)"*.
**Pronto quando:** listar os tokens de um usuário de outro business responde 404.

## UC-EQP-04 — Revogar só alcança o token do próprio usuário, no próprio business `[T0]`
Status: 🧪 (1 teste cita este UC — revogar token de outro business dá 404; revogar pela URL de **outro** usuário do mesmo business (manipulação de URL) dá 404; nos dois casos o token segue sem `revoked_at`. Só na lane MySQL.)
Charter §Non-Goals: *"NÃO permite revogar token cross-tenant"*; §Anti-hooks: *"NÃO permite revogar com tokenId vindo do user input sem confirmar `user_id` pertence ao business da sessão"*; SPEC US-TEAM-003.
**Pronto quando:** a rota de revogação por usuário recusa token que não é daquele usuário naquele business, sem efeito colateral.

## UC-EQP-05 — Reveal-once: o token aparece uma vez e nunca mais `[T0]`
Status: 🧪 (1 teste cita este UC — gerar devolve o raw; a linha em `mcp_tokens` guarda o `sha256` dele e o raw não está em coluna nenhuma; o drill-down lista o token sem o raw e sem o hash. Só na lane MySQL.)
Charter §Goals: *"Reveal-once de token raw no momento da criação"*; §Non-Goals: *"NÃO mostra raw de token previamente emitido — apenas hash"*; §Restrições: *"`listTokens` retorna SOMENTE metadados (sem `sha256_token`, sem raw)"*; SPEC US-TEAM-002; SDD CU-TEAM-01.
**Pronto quando:** o raw sai só na resposta de criação, o banco guarda só o hash, e nenhuma listagem devolve um ou outro.

## UC-EQP-06 — Revogar é lógico: o registro fica, com quem e quando
Status: 🧪 (1 teste cita este UC — revogar pela rota do drill-down grava `revoked_at` e `revoked_by` (o operador), a linha continua no banco, e o drill-down segue listando o token, agora com `revoked_at`. Só na lane MySQL.)
Charter §Restrições Tier 0: *"Soft-delete em revoke … grava `revoked_at` + `revoked_by` … Nunca forceDelete"*; §Anti-hooks: *"NÃO faz forceDelete em `mcp_tokens`"*; §Métricas: token revogado aparece como *"Revogado"*; SPEC US-TEAM-003.
**Pronto quando:** depois de revogar, a linha existe com `revoked_at` e `revoked_by`, e o histórico continua visível no drill-down.

## Backlog (sem id — vira UC quando ganhar teste que o cite)

- [BACKLOG] **Status do token no drill-down** (Ativo · Expira em Nd · Expirado · Revogado) e **"Nunca usado"** para `last_used_at` nulo — charter §Goals + §Métricas. É derivação de front sobre campos que o backend já entrega; a prova natural é E2E.
- [BACKLOG] **Quota diária/mensal em BRL e export CSV** — charter §Goals. Contrato em uma fonte só (charter), sem teste neste PR.
- [BACKLOG] **Gerar `.dxt` com o token embutido** — charter §Goals. Mesmo motivo.
- AlertDialog nas ações destrutivas e DS **não são UC**: o juiz é gate e a ratificação visual é [W] (mesma decisão do SDD §6.5).

## Achados `[T0]` registrados (decisão [W], não consertados aqui)

O charter só exige o escopo por business em `listTokens` e `revokeToken`. As outras ações da tela recebem o `userId` (ou o `tokenId`) da URL **sem** conferir o business da sessão. Medido no `TeamController` deste `main`:

| Rota | O que faz com o id da URL | Escopo por business? |
|---|---|---|
| `DELETE /team/token/{token}` (`revogarToken`, "legacy") | `McpToken::findOrFail($tokenId)` e revoga | ❌ — contradiz o Anti-hook *"NÃO permite revogar com tokenId vindo do user input sem confirmar…"* |
| `POST /team/{user}/token` (`gerarToken`) | `User::findOrFail($userId)` e emite | ❌ |
| `POST /team/{user}/dxt` (`gerarDxt`) | `User::findOrFail($userId)` e emite | ❌ |
| `POST /team/{user}/quota` (`atualizarQuota`) | grava quota no `user_id` da URL | ❌ |

A persona declarada da tela é só [W] (charter), o que reduz a exposição se a permissão estiver de fato restrita a ele — isso não foi medido aqui. Mas o Anti-hook da primeira linha é **lei escrita** e o código o contradiz. Por isso nenhuma delas virou UC: um UC aqui nasceria `❌`, e a correção (escopar ou aposentar a rota legada) é decisão [W]. Quando houver decisão, o caso entra neste arquivo no mesmo PR da correção.
