---
id: resources-js-pages-ads-admin-team-scopes-casos
casos: Forja · escopo de escrita por dev × módulo · /ads/admin/team-scopes
irmaos: TeamScopes.charter.md (lei) · TeamScopes.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então)
por_que: "quem pode escrever em qual módulo" é a regra do SERVIDOR que o WriteFileTool consulta antes de deixar um token MCP escrever — o switch na tela pode mudar de forma, o efeito no servidor não.
owner: wagner
last_run: "2026-10-07"
---

# Casos de uso — /ads/admin/team-scopes

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Os UC derivam do **contrato** — nunca do `.tsx`:
> - [`TeamScopes.charter.md`](TeamScopes.charter.md) (lei, `status: draft`) — Mission *"fonte de verdade que o `UserScopeService::canWriteToPath()` consulta no servidor ANTES de deixar um dev escrever"* + Goals (`grant`/`revoke`) + Anti-hook *"ausência de grant = DENY no servidor"*;
> - [`memory/requisitos/Jana/SPEC.md`](../../../../../../../memory/requisitos/Jana/SPEC.md) **US-COPI-079** (demo Maiara) — passo 2 *"tenta tocar arquivo NFSe → bloqueio"*, passo 4 *"Wagner concede acesso a Compras via `/ads/admin/team-scopes`"*, passo 5 *"Maiara repete e funciona"*;
> - [`memory/requisitos/Forja/SCOPE.md`](../../../../../../../memory/requisitos/Forja/SCOPE.md) — `UserScopeService` *"é quem o WriteFileTool consulta ANTES de escrever — regra do servidor vence a regra local"*;
> - [ADR 0053](../../../../../../../memory/decisions/0053-mcp-server-governanca-como-produto.md) §Contexto — skill em git *"sem revogação de acesso após clone"*: governança real exige revogação no **servidor**.
>
> Persona: **[W]**, decidindo quem do time pode escrever onde. Nenhum UC usa biz=4 — usuários do teste nascem no tenant fictício **98** ([ADR 0358](../../../../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).

> ⚖️ **Onde estes UC rodam:** [`Modules/Forja/Tests/Feature/TeamScopesContratoTest.php`](../../../../../Tests/Feature/TeamScopesContratoTest.php), na lane `PHP / Pest (Forja · MySQL)` ([`forja-pest.yml`](../../../../../../../.github/workflows/forja-pest.yml)) — **advisory**: reprova visível, não bloqueia merge (o context não está nas listas de [`governance/required-checks-baseline.json`](../../../../../../../governance/required-checks-baseline.json)). Todos nascem 🧪: Pest local é proibido ([ADR 0062](../../../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)), o `✅` vem do manifesto derivado do JUnit, nunca à mão (G-7).

> 🔎 **Por que o teste mede o `UserScopeService` e não a tabela:** o contrato é *"o servidor reconhece o acesso"* (SCOPE.md + US-COPI-079 passo 5). Assertar a linha em `mcp_user_module_access` provaria que o POST escreveu — não que a escrita tem efeito no ponto que decide. O teste chama o **mesmo** método que o `WriteFileTool` consulta (`canWriteToPath`).

## UC-TSCOPE-01 — Conceder escrita num módulo libera aquele módulo no servidor, e só ele `[must]`
Status: 🧪 (1 teste cita este UC — com **controle**: a concessão é em `Compras`, e o teste exige que `NfeBrasil` continue negado. Assertar só "Compras liberou" deixaria passar um serviço que liberasse tudo.)
*Dado* um dev do business sem acesso a `Compras`; *quando* [W] concede `can_write` em `Compras` (`POST /ads/admin/team-scopes/grant`); *então* `canWriteToPath(dev, 'Modules/Compras/…')` passa a ser verdadeiro, e um módulo não concedido segue falso.
**Pronto quando:** antes do POST o servidor nega `Compras`; depois libera `Compras` e segue negando `NfeBrasil`.

## UC-TSCOPE-02 — Sem concessão de escrita, o servidor nega (DENY por padrão) `[must]`
Status: 🧪 (1 teste cita este UC, com **pré-condição anti-vácuo**: o mesmo caso prova que uma concessão **só-leitura** foi gravada — senão "continua negado" passaria igual com um POST inerte, medindo não-execução e chamando de negação, lápide §5 2026-07-24.)
Ausência de concessão é **DENY** (Anti-hook do charter; US-COPI-079 passo 2). Conceder só leitura (`can_read`) **não** é conceder escrita.
**Pronto quando:** dev sem linha nenhuma é negado; dev com concessão só-leitura em `Compras` tem a linha gravada **e** segue negado pra escrever em `Compras`.

## UC-TSCOPE-03 — Revogar retira o acesso no servidor `[must]`
Status: 🧪 (1 teste cita este UC — começa **concedendo** e confirmando que liberou, pra o "negado depois" não ser o estado inicial disfarçado.)
*Dado* dev com escrita em `Compras`; *quando* [W] revoga (`POST /ads/admin/team-scopes/revoke`); *então* o servidor volta a negar a escrita.
**Pronto quando:** liberado antes do revoke, negado depois, e a concessão some de `mcp_user_module_access`.

---

## [BACKLOG] — declarado no charter, ainda sem teste que o defenda

Prosa honesta: nenhum item abaixo tem teste citando um UC. Vira UC quando ganhar teste — não antes ([`how-trabalhar.md`](../../../../../../../memory/how-trabalhar.md) §Pedido de tela/feature).

- [BACKLOG] **`[T0]` A lista de devs é só do business da sessão** (Non-Goal + Anti-hook do charter; [ADR 0093](../../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)). Tem contrato em 2 fontes, mas **não virou UC por um achado medido**: a prop `users` vem de `UserScopeService::listUsersWithAccess`, que faz `JOIN user_businesses` — e essa tabela **não existe** no schema baseline (`database/schema/mysql-schema.sql`: 0 ocorrências de `CREATE TABLE \`user_businesses\``) **nem no staging do CT 100** (`Schema::hasTable('user_businesses')` = `false`, medido 2026-10-07). **Hipótese, não veredito de teste:** a prop deferida lança `QueryException` e a sidebar de devs nunca carrega. Um teste deste caso nasceria vermelho numa lane compartilhada; consertar o serviço está fora do escopo deste PR e é decisão [W].
- [BACKLOG] KPIs do topo (devs do business, quantos com acesso ativo, total de pares user × módulo) — dependem da mesma prop `users`, logo herdam o achado acima.
- [BACKLOG] **Quem pode conceder.** O charter diz *"dar ao Wagner o controle"*, mas as 3 rotas `/ads/admin/team-scopes*` só exigem `auth` + a stack UltimatePOS — nenhuma permission. Se isso é aceitável ou se falta um `can:` é decisão [W]; não há fonte que fixe qual permission seria.
- [BACKLOG] `grant`/`revoke` validarem que o **user-alvo** é do business da sessão — pendência aberta no próprio charter (§Pendências). Hoje a validação é `exists:users,id`.
- [BACKLOG] Revogar pede `confirm()` antes do POST — comportamento só de front, sem E2E.
- [BACKLOG] Concessão com `expires_at` vencido volta a negar — o serviço honra, mas nenhum documento canônico fixa a expiração como contrato da tela.
