---
id: cowork-jana-casos-emenda-permissao
casos: Emenda — camada de permissão do módulo Jana · /ia · PR-0 nome
irmaos: Index.casos.md · Chat.casos.md · Memoria.casos.md · Pro.casos.md (destinos) · JANA-ERRATA-CAMADA-ESQUECIDA-2026-08-27.md (achado)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
origem: Cowork [CC] 2026-08-27 · lido no main árvore b5cef0050046
---

# Emenda de casos — a camada de permissão (e o nome)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> **Estes UCs nascem ⬜ e nenhum tem teste ainda.** O G-2 exige que todo UC seja citado por ≥1 teste,
> então **cada UC abaixo entra no mesmo PR do seu teste** — nunca antes. Quem só colar os UCs quebra o gate,
> e prometer teste que não existe é pior que a ausência (regra já escrita no `Memoria.casos.md`).
>
> **Derivados de:** `Modules/Jana/Resources/permissions.php` (24 permissões, grupo "Copiloto") +
> `Modules/Jana/Http/routes.php` (grupo `prefix => 'ia'` com `can:jana.access`) + o comentário da
> linha 28 do mesmo arquivo — *"a permissão que EXISTIA e não valia nada (2026-07-27)"*. **Não** do `.tsx`:
> derivar do código seria tautológico (§5 2026-06-05).
>
> **Por que existem:** a rodada 1 do pedido "refazer o Jana" desenhou 4 telas **sem** a camada de
> permissão. Reescrever tela gated sem UC de permissão é como o caso do charter que não é lei porque
> ninguém a executa: a regra fica no `routes.php` e o rewrite não sabe que ela existe. O precedente é o
> próprio `jana.access`, que passou meses declarado e aplicado em zero.
>
> **Destino de cada UC** está marcado no título da seção — são emendas, e cada uma vai pro `casos.md`
> da sua tela. Este arquivo é ponte, não morada.

---

## → `Index.casos.md` (Painel · /ia)

### UC-JPERM-01 — Sem `jana.access`, `/ia` não abre
Status: ⬜ (teste a escrever: `Modules/Jana/Tests/Feature/Http/IaPermissaoGrupoTest.php`)
Usuário autenticado do business, **sem** a permissão base, pede `/ia`. O grupo inteiro é barrado no
middleware — não é tela vazia nem menu escondido: é 403 antes do controller. Âncora: `routes.php:50`
(`'can:jana.access'` no `middleware` do grupo) + o comentário da linha 28, que registra que essa
permissão já esteve declarada e **aplicada em zero rotas**.
**Pronto quando:** `GET /ia` devolve 403 sem a permissão e 200 com ela, e o teste cobre **as quatro**
rotas de tela (`jana.index`, `jana.chat.index`, `jana.pro.index`, `jana.memoria.index`) — uma rota que
escape do grupo é exatamente a regressão de 2026-07-27. _(Anti-vácuo: asserir 200 no caso positivo,
senão um 500 faz o 403 passar por engano.)_

### UC-JPERM-02 — `jana.access` não dá chat de graça
Status: ⬜ (`IaPermissaoGrupoTest`)
Quem tem só a permissão base **vê** o Painel e **não** conversa. A UI esconder o CTA "Conversar com a
Jana" é conveniência; o que garante é o servidor. Âncora: `permissions.php` — `jana.chat` é permissão
própria, com `requires: ['jana.access']`.
**Pronto quando:** sem `jana.chat`, `POST /ia/conversas/{id}/mensagens` volta 403 e o payload do Painel
traz `podeConversar: false`; com ela, 200 e `true`. **A tela lê a prop — não recalcula permissão no front.**

### UC-JPERM-03 — Sem `jana.metas.manage`, meta é leitura
Status: ⬜ (`Modules/Jana/Tests/Feature/Http/MetasPermissaoTest.php`)
O Painel mostra metas e farol pra quem só tem `jana.access`; **criar, editar, reapurar e trocar fonte**
exigem a permissão `medium`. O `JanaMetaDrawer` abre **em modo leitura** — não desaparece: esconder o
drawer ensina que a meta não existe, e a meta existe. Âncora: `permissions.php` (`jana.metas.manage`,
risk `medium`) + as rotas `jana.metas.reapurar` / `jana.fontes.update`.
**Pronto quando:** sem a permissão, `POST /ia/metas`, `PATCH /ia/metas/{id}`, `POST /ia/metas/{id}/reapurar`
e `PATCH /ia/metas/{id}/fonte` voltam 403; `GET /ia` segue 200 **com** as metas no payload e
`podeGerenciarMetas: false`.

### UC-JPERM-04 — Custo administrativo não vaza no Painel do cliente
Status: ⬜ (`Modules/Jana/Tests/Feature/Http/CustosVazamentoTest.php`)
`jana.admin.custos.view` é `high` e é de plataforma. Custo de IA não entra no payload de quem não a tem —
**ausência no payload**, não `display:none`. Âncora: `permissions.php` (risk `high`, `requires: []`) +
`Services/CustosService.php`.
**Pronto quando:** o JSON de `GET /ia` de um usuário comum não contém nenhuma chave de custo/consumo, e
contém quando o usuário tem a permissão. _(Assertiva sobre o payload, não sobre o HTML: o que não vaza é o dado.)_

### UC-JPERM-05 — Tier 0: meta de outro business não aparece
Status: ⬜ (`MetasPermissaoTest`)
Dois businesses com metas. O Painel de cada um mostra **só** as suas — permissão e tenancy são eixos
diferentes, e `jana.superadmin` não é passe livre pra ler dado de tenant no Painel do cliente.
**Pronto quando:** com o global scope ativo, o payload de `GET /ia` do biz A não traz nenhum id de meta do
biz B, inclusive para um superadmin logado em A.

---

## → `Chat.casos.md` (/ia/conversa)

### UC-JPERM-06 — Conversa de outro usuário não abre pelo id
Status: ⬜ (`Modules/Jana/Tests/Feature/Http/ConversaAcessoTest.php`)
`jana.chat` autoriza **conversar**, não **ler conversa alheia**. Trocar o id na URL é o teste mais barato
que existe. Âncora: `routes.php` (`jana.conversas.show`, `jana.conversas.update`) + `Entities/Conversa.php`.
**Pronto quando:** `GET /ia/conversas/{id}` de conversa de outro usuário do mesmo business volta 403/404
(o que o dono decidir — mas **o mesmo** nas duas rotas), e o `PATCH` idem.

---

## → `Memoria.casos.md` (/ia/memoria)

### UC-JPERM-07 — Esquecer fato é ação `critical`, e a trilha não depende da permissão
Status: ⬜ (`Modules/Jana/Tests/Feature/Http/MemoriaPermissaoTest.php`)
`jana.mcp.memory.manage` é a única permissão `critical` do grupo (soft-delete LGPD). Este UC **não
substitui** o UC-MEM-05 (que prova a trilha): prova que **quem não pode, não apaga** — e que, quando
apaga, a trilha nasce igual. Âncora: `permissions.php` (`critical`, `requires: []`) + `retention.php`
(*"activity_log é AUDITORIA — NUNCA purgada"*).
**Pronto quando:** sem a permissão, `DELETE /ia/memoria/{id}` volta 403 **e** `activity_log` não cresce;
com ela, 302 e cresce 1 sob `jana_memoria_fato_esquecido`.

---

## → `Pro.casos.md` (/ia/pro)

### UC-JPERM-08 — São duas telas: a do cliente e o preview admin
Status: ⬜ (`Modules/Jana/Tests/Feature/Http/ProPreviewPermissaoTest.php`)
`/ia/pro` é a tela comercial do cliente (ADR 0140). O preview de outro business
(`jana.admin.jana_pro.preview`) é **outra** tela, sob `can:jana.superadmin`. A rodada 1 do pedido tratou
as duas como uma — quem reescrever "a tela Pro" sem este UC funde as duas ou expõe o preview.
**Pronto quando:** usuário comum: `GET /ia/pro` 200, `GET /copiloto/admin/jana-pro/preview?business_id=X`
403. Superadmin: as duas 200, e o preview de um `business_id` alheio **não** altera o business da sessão.

---

## → `Index.casos.md`, seção de naming (o que [W] apontou em 2026-08-27)

### UC-JNAME-01 — A tela de permissões não oferece um módulo chamado "Copiloto"
Status: ⬜ (teste a escrever: `tests/Feature/Permissions/JanaPermissionGroupNomeTest.php`)
O rename Copiloto→Jana (ADR 0088/0092) foi **PHP-only por decisão** — a skill `migrar-modulo` registra
que a fachada ficaria pra "PR-3+ posterior". Esses PRs não vieram, e o `permissions.php` ainda declara
`group: 'Copiloto'` com as **24 labels** começando por `"Copiloto: …"`. Essas labels **são** o que a tela
de permissões mostra a quem monta um perfil de acesso: o cliente configura acesso a um módulo que, na
tela dele, se chama Jana.
**⚠️ As `key` NÃO se tocam.** `copiloto.mcp.*` → `jana.mcp.*` já foi feito (#4853) e deixou cicatriz —
um `givePermissionTo('copiloto.mcp.use')` sobrou apontando pra permissão inexistente e **quebrou todo o
onboarding** (`governance-script-tests.yml:540`). `group` e `label` são copy; `key` é incidente.
**Pronto quando:** o registry devolve `group: 'Jana'`, nenhuma das 24 labels contém `Copiloto`, e as 24
`key` seguem **byte-idênticas** — a asserção das keys é a parte que impede o conserto de virar o incidente.

---

## Fora do alcance do Pest de Controller (⬜ contrato visual / copy — decisão [W])

> Copy é soberania de [W]; entra em contrato de tela, não em UC.

- ⬜ **"Falar com Copiloto →"** — `Pages/Cliente/Index.tsx:2017`, com `title="Abre o Copiloto (Jana)…"`:
  a UI explica o nome novo pelo nome velho. Também em `_drawer/IATab.tsx` ("Copiloto de cliente") e 4× no
  `Cliente/Index.charter.md`. **Não é tela do Jana**, mas é onde o cliente lê. Se mexer, é o contrato de
  `Cliente/Index` que manda — conferir se a copy está pinada antes.
- ⬜ **Ponteiros podres nos cabeçalhos** — `Pages/Jana/Chat.tsx` declara `tela: /copiloto` e
  `module: Copiloto`; `Cliente/Index.charter.md:83` manda pra `/jana/chat?context=cliente:{id}`, **rota que
  nunca existiu** (o `.tsx` já usa `/ia/chat`). Registrado porque foi **daí que saiu o `/jana` errado** do
  pedido da rodada 1: o Cowork leu o vocabulário podre e o repetiu. Comentário podre não é inerte — ele
  ensina errado ao próximo executor, humano ou não.
- ⬜ **Frontmatter das skills** — `.claude/skills/jana-arch/SKILL.md` ainda declara `name: copiloto-arch`.
  **Não renomear aqui**: frontmatter de skill é invocação, não texto. PR próprio, com o dono da skill.

## Ainda ABERTAS (sem decisão) — já registradas no repo, não abertas por mim

- ❓ **"O Copiloto lembra de você"** — h1 + título do shell da aba Memória. **Já está escrito** como ABERTA
  no `Memoria.casos.md` (§Ainda ABERTAS) desde 2026-08-07, com o motivo de não ser edição de 1 linha:
  mexer no título toca o breadcrumb do shell. O `RUNBOOK-chat.md` já manda *"em texto novo sempre Jana"* —
  o que falta é despacho pro texto **velho**. O achado de [W] de 2026-08-27 é o mesmo item, agora com dono.
- ❓ **Título do Painel** — "Jana — Dashboard" no `AppShellV2` + breadcrumb "Dashboard" vs aba "Painel"
  (US-COPI-148). É o `_pendente_w` nº 1 do `jana-painel.contract.json`; **não** re-abrir aqui.
