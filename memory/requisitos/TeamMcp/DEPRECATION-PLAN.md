---
id: requisitos-teammcp-deprecation-plan
---

# DEPRECATION-PLAN — Modules/TeamMcp

> **Status:** 📋 Planejado · **Owner:** [W] · **Decisão:** [W] 2026-07-30 (*"todos esses eu vou deletar"*)
> **Ordem no conjunto:** **5º de 6** — [proposal da ordem topológica](../../decisions/proposals/2026-07-30-deprecar-6-modulos-governanca-ordem-topologica.md)
> 🔴 **Duas travas duras:** guarda o **acesso do time ao MCP**, e teve um gate promovido a **required 3 dias antes** desta decisão.

> ⚠️ **Leia antes das Fases 3 e 4:** a seção [§E1 — resultado da medição](#e1--resultado-da-medição-2026-07-30)
> **corrige quatro afirmações** deste plano (acopladores "que morrem", dono de `mcp_tokens`, frescor do
> heartbeat e custo da migration). O que está escrito abaixo era o melhor palpite antes de medir.

## Decisão [W] — 2026-07-30

> *"aprovo tudo · flip · nada pode ser perdido · migrate · volta"*

| # | Decisão | Efeito no plano |
|---|---|---|
| 1 | **Aprovado** commit/PR das etapas prontas | E2 e E2b saem em PR |
| 2 | **ADR 0361 ratificada** (`aceito`). O flip de branch protection da 0354 **não** será feito | R2 encerrado |
| 3 | ⛔ **NADA PODE SER PERDIDO** | **Zero DROP. Zero função aposentada por conveniência.** Todo papel do §Destino — inclusive os ~13 sem dono declarado — ganha receptor. Os 6 acopladores de ADS/Governance são **repontados nesta deprecação**, não deixados pro 6º |
| 4 | `mcp_ingest_heartbeat` = **MIGRATE**, e **o watcher volta** | Fase 4 atualizada; `CcIngest*` + `IngestLiveness*` migram inteiros, sem poda |
| 5 | ⚠️ **RECEPTOR CORRIGIDO: o MCP vai pra `Modules/Forja`, não pra Jana** — [W] *"Mcp vai para forja"* (depois do #5089 renomear `ProjectMgmt`→`Forja`) | Supersede a §Destino abaixo, que dizia *"7 de 8 vão pra Jana"*. A [proposal MCP-é-Forja](../../decisions/proposals/2026-07-30-mcp-e-forja-jana-e-usuario.md) já pedia isso e deixava `SyncMemoryWebhookController` em **aberto #2** (*"escreve em `mcp_memory_documents`, que é tabela da Jana e tem `business_id`"*). **Medido e resolvido:** as **2082 linhas são TODAS `business_id=1`** — a coluna é nominal, o canon é conteúdo de plataforma. Vai pra Forja. |

**Consequência da #3 na ordem:** o TeamMcp deixa de depender da morte de ADS/Governance. Ele sai
**primeiro**, e leva junto o trabalho de repontar quem o consome.

> ⚰️ **EXECUTADO — `Modules/TeamMcp` foi APAGADO em 2026-07-31.** As 7 etapas fecharam com
> PR e CI verde; **89 → 0 arquivos**. Nada foi perdido: o destino de cada peça está na
> lápide [`SUPERFICIE.md`](SUPERFICIE.md). URLs `/api/mcp/*`, `/api/cc/ingest`,
> `/team-mcp/*`, `/forja/*` e `/ads/admin/*` seguem **idênticas**; as 4 tabelas
> **não foram tocadas** (CT 100 e Hostinger leem o mesmo banco — a migração foi troca
> de dono no código, nunca DDL).
>
> **Fica pendente e NÃO bloqueia nada:** as 4 abas do `/forja` sobrepõem telas da Forja.
> Foram **movidas, não fundidas** — fundir deleta uma implementação, e isso é decisão [W].

## Fase 1 — Inventário

**Gerado:** [`SUPERFICIE.md`](SUPERFICIE.md) — **105 arquivos em 15 papéis** (`module-surface.mjs TeamMcp --write`). Frescor 2026-07-30: `--check` **exit 0**.

Contornos: **0** telas em `Pages/TeamMcp/` (as telas vivem em `Pages/team-mcp/` — kebab, com `Forja/Cockpit.charter.md`) · **0** arquivos em `Routes/` · **4 tools MCP** em `Mcp/Tools/` · **0** cron em `Kernel.php`.

## Fase 2 — Estado em produção (medido ANTES de planejar)

**Sistema medido:** `APP_ENV=live` · `u906587222_oimpresso` · 385 tabelas · **2026-07-30**.
**Controle positivo:** `business=82` · `users=124` · `transactions=75.255`.

| Tabela | Linhas | Escrita mais recente |
|---|---:|---|
| **`mcp_tokens`** | **26** | 2026-06-17 21:12 |
| `mcp_ingest_heartbeat` | 28 | 2026-07-21 17:55 |
| `mcp_actors` | 6 | 2026-05-05 23:25 |
| `cowork_handoffs` | 1 | 2026-06-18 12:13 |

**Consequência 1 — volume desprezível, criticidade máxima.** 61 linhas somadas. Mas `mcp_tokens` (26) é **o que autentica o time nas tools MCP** e `mcp_actors` (6) é o identity mesh ([ADR 0081](../../decisions/0081-identity-mesh-mcp-actors.md)). Poucas linhas, dano alto.

**Consequência 2 — o heartbeat parou há 9 dias.** Última escrita em `mcp_ingest_heartbeat` foi **2026-07-21**; hoje é 30/07. Ou o watcher de ingest morreu, ou já não é usado. **Isso é achado, não conclusão** — não medi o produtor.

⚠️ CT 100 **não medido** — e aqui é o mais grave do conjunto: o MCP server é `mcp.oimpresso.com`, no CT 100.

## Fase 3 — Acoplamento externo

`git grep -lF 'Modules\TeamMcp\'` fora da pasta → **10 arquivos de código** (11 com o charter):

| Acoplador | Sobrevive? |
|---|---|
| `Modules/Jana/Mcp/OimpressoMcpServer.php` | ✅ **registra as 4 tools** — patch obrigatório |
| `Modules/Jana/Http/routes.php` | ✅ sim |
| `Modules/Jana/Mcp/Tools/WhatsActiveTool.php` | ✅ sim |
| `Modules/Jana/Services/TaskRegistry/HitlEscalationService.php` | ✅ sim |
| `Modules/Governance/Http/Middleware/ActionGate.php` | ❌ morre (6º) |
| `Modules/Governance/Providers/GovernanceServiceProvider.php` | ❌ morre (6º) |
| `Modules/Governance/Services/Checkers/IngestLivenessChecker.php` | ❌ morre (6º) |
| `Modules/Governance/Tests/Feature/IngestLivenessCheckerTest.php` | ❌ morre (6º) |
| `Modules/ADS/Http/Requests/ExecuteToolRequest.php` | ❌ morre (4º) |
| `Modules/ADS/Routes/web.php` | ❌ morre (4º) |
| `resources/js/Pages/team-mcp/Forja/Cockpit.charter.md` | ❌ sai com o módulo |

**4 sobrevivem — todos no Jana.** É o Jana que herda ou perde as tools.

## Fase 4 — Decisão por tabela

| Tabela | Decisão | Por quê |
|---|---|---|
| **`mcp_tokens`** | ⛔ **MIGRATE obrigatório** — nunca DROP | 26 tokens = acesso do time. DROP = Felipe/Maiara/Luiz cegos. Receptor natural: `Modules/Jana` (já dono de `mcp_tokens` nas migrations dele). |
| `mcp_actors` | **MIGRATE** | identity mesh, [ADR 0081](../../decisions/0081-identity-mesh-mcp-actors.md) |
| `mcp_ingest_heartbeat` | **MIGRATE** — decisão [W] 2026-07-30 | §E1 mediu: o produtor é o watcher local, **ocioso, não morto**. [W]: *"nada pode ser perdido · migrate · volta"* — o watcher volta a rodar |
| `cowork_handoffs` | **ARCHIVE** (1 linha) | append-only + HMAC ([ADR 0283](../../decisions/0283-handoff-loop-zero-paste.md)) — 1 linha cabe em seeder |

**Ordem:** MIGRATE/DROP **depois** do refactor (lição E3 do SRS).

## Destino por função — realocação

> Medido 2026-07-30. Dos 10 acopladores de código, **4 sobrevivem e os 4 são da Jana** — inclusive o `OimpressoMcpServer`. Isso não é coincidência de acoplamento: em cada linha abaixo, **o dado já é da Jana ou o servidor que consome já é da Jana**.

| Peça | Módulo dono correto | Base da decisão |
|---|---|---|
| `Services/McpTokenIssuer` · `ActorResolver` · `McpActorRepository` + `Entities/McpActor` + `mcp_tokens` (26) + `mcp_actors` (6) | **Jana** ⛔ Tier 0 | quem autentica e registra as tools é o `OimpressoMcpServer` da Jana ([ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md)); o emissor de token não pode viver fora do dono do servidor. ⚠️ **Contradiz o [`SCOPE.md` do Governance](../Governance/SCOPE.md)**, que declara *"tokens MCP + Identity Mesh → Modules/TeamMcp"* — essa fronteira **caduca** com o módulo, e a errata vai no mesmo PR. |
| 4 tools `Handoff*` (`Ack`/`Lever`/`Pending`/`Submit`) | **Jana** | já são **registradas** pelo `OimpressoMcpServer`. Mudam de pasta, não de servidor. |
| `HandoffIngestService` · `HandoffLeverService` + `Entities/CoworkHandoff` + `cowork_handoffs` (1) | **Jana** | [ADR 0283](../../decisions/0283-handoff-loop-zero-paste.md); o consumidor sobrevivente é `Jana/Services/TaskRegistry/HitlEscalationService` |
| `CcIngestService` · `IngestLivenessService` + `McpIngestHeartbeat` + `mcp_ingest_heartbeat` (28) | **Jana** | as tabelas irmãs (`mcp_cc_sessions`/`_messages`/`_blobs`) **já são da Jana**. ⚠️ decidir junto com a Fase 4: se o produtor estiver morto (parado desde 21/07), é DROP e não move nada. |
| **`Http/Controllers/Mcp/SyncMemoryWebhookController`** | **Jana** ⛔ **Tier 0 — o mais silencioso** | é o webhook GitHub → `mcp_memory_documents` (tabela da Jana) que faz o **canon chegar ao time**. Se sair sem receptor, Felipe/Maiara/Luiz param de ver doc novo por busca e **nada alarma**: a busca velha responde. Sintoma sem sinal. |
| `GitMainResolver` · `PrChecksResolver` | **Jana** | servem o loop de handoff; sem consumidor fora dele |
| `TeamUsageAggregator` · `UsageCsvExporter` + tela `team-mcp/CcSessions` | **Jana** | consumo/quota por ator pareia com `mcp_quotas`/`mcp_usage_diaria`, já da Jana |
| **`ScorecardBuilderService` + `Services/Forja/*` + tela `team-mcp/Forja/Cockpit`** (+ `charter` + `casos.md`) | 🔴 **BURACO — decisão [W]** | é o cockpit do loop Cowork↔Code. **Não há módulo receptor natural** e é o único conteúdo genuinamente do TeamMcp. Registro como buraco: inventar dono aqui seria pior que declarar ausência. |

**Leitura honesta:** 7 de 8 linhas vão pra **Jana**. Medido, o TeamMcp é uma **pasta de peças da Jana** com nome próprio — e a Forja é a única coisa que é dele de fato.

## Fase 5 — Riscos Tier 0

| # | Risco | Severidade | Mitigação |
|---|---|---|---|
| **R1** | **Time perde acesso ao MCP.** `mcp_tokens` autentica as tools que Felipe/Maiara/Luiz usam. | **ALTA** | MIGRATE antes de remover código; smoke com token real do time |
| **R2** | ~~Gate required órfão travando `main`~~ → **REBAIXADO por medição.** A [ADR 0354](../../decisions/0354-teammcp-pest-required-emenda-0314.md) (`decided_at: 2026-07-27`, `[W]`) declara `teammcp-pest` promovido a **required**, mas **o flip nunca chegou na proteção viva** (ver §Achado). Não trava merge. Sobra um problema de **canon**, não de CI. | ~~ALTA~~ → **média** | Errata da 0354 — append-only, sucessora, nunca editar. **NÃO é bloqueador da E4.** |
| **R3** | 4 tools MCP registradas no `OimpressoMcpServer` param de resolver | **ALTA** | Migrar as 4 pro Jana ou aposentar declaradamente |
| **R4** | Telas `/forja` + charter somem | média | Decidir receptor antes da E4 |
| **R5** | cross-tenant | **atenção** | `cowork_handoffs` é **sem `business_id` por design** (cross-tenant intencional, ADR 0093/0283) — não "consertar" no receptor |
| **R6** | **Canon para de sincronizar em silêncio.** `SyncMemoryWebhookController` morre → webhook GitHub sem destino → `mcp_memory_documents` congela. **Sem alarme:** a busca velha responde, só não tem doc novo — o time não descobre por erro, descobre por ausência. | **CRÍTICA — a mais grave deste módulo** | Realocar **primeiro** (E2b), antes de qualquer remoção. Validar com commit de teste em `memory/` + conferir chegada em `mcp_memory_documents`. |

### Achado — o required da ADR 0354 nunca existiu na proteção viva

Medido 2026-07-30 contra a **autoridade** (a proteção viva), não contra o baseline:

```bash
gh api repos/wagnerra23/oimpresso.com/branches/main/protection --jq '.required_status_checks.contexts[]'
```

→ **34 contexts**, e nenhum é `teammcp-pest`. Os únicos com `Pest` são `(Unit)`, `(Financeiro · MySQL)` e `(NfeBrasil · MySQL)`. O `governance/required-checks-baseline.json` também não o lista.

**Consequência boa:** deletar o TeamMcp **não deadlockará o `main`** — nenhum context required aponta pra ele. O R2 sai do caminho crítico.
**Consequência ruim:** a [ADR 0354](../../decisions/0354-teammcp-pest-required-emenda-0314.md) é uma promoção que **a máquina nunca executou** — canon afirmando enforcement inexistente, a classe [LC-10](../../LICOES_CODE.md). Isso vale **independente** da deprecação: mesmo se [W] mudar de ideia sobre deletar, ou o flip acontece ou a ADR ganha errata. Não deixar morrer junto com o módulo, senão o registro fica dizendo que houve um gate que nunca houve.

## Roadmap

| Etapa | O que | Gate [W] |
|---|---|---|
| **E1** | ✅ **FEITO 2026-07-30** — CT 100, banco, tokens, heartbeat e semântica de migration medidos (§E1) | — |
| **E2** | ✅ **ESCRITA** — [ADR 0361](../../decisions/0361-errata-0354-teammcp-pest-required-nunca-executado.md) (`proposto`) | ✋ [W] ratifica — **não bloqueia a E4** |
| **E2b** | ✅ **FEITO E PROVADO EM PROD.** [#5083](https://github.com/wagnerra23/oimpresso.com/pull/5083) tirou `SyncMemoryWebhook` + `Health` do TeamMcp; [#5101](https://github.com/wagnerra23/oimpresso.com/pull/5101) corrigiu o receptor pra **Forja** (decisão #5), levando junto o **route group `/api/mcp`** — deixar a rota na Jana apontando pra controller da Forja recriaria o drift que a Fase 3.7 causou. URLs e names `jana.mcp.*` inalterados. **Prova do R6 abaixo.** | ✅ R6 provado |
| **E3** | MIGRATE `mcp_tokens` + `mcp_actors` pro receptor · smoke com token real do time (R1) | ✋ [W] confere |
| **E4** | Migrar as 4 tools MCP + patch nos 4 acopladores do Jana | ✋ [W] aprova |
| **E5** | Remover `Modules/TeamMcp/` + telas `/forja` + permissions + `modules_statuses.json` | ✋ [W] aprova |
| **E6** | Migration final (DROP/ARCHIVE conforme Fase 4) | ✋ [W] aprova |
| **E7** | Smoke real: tools respondem pelo receptor com token do time · rotas em 301/410 | ✋ [W] confere |
| **E8** | Lápide §5 + `BRIEFING` final | — |

## E1 — resultado da medição (2026-07-30)

Tudo abaixo foi **rodado**, não lido. Onde contradiz as Fases 2–4, vale isto.

### CT 100 — medido (era o resíduo mais grave)

`tailscale ssh root@ct100-mcp` → container `oimpresso-mcp` **healthy**, e dentro dele:

| O que | Valor |
|---|---|
| commit servido | `14b0b8506` — **exatamente `origin/main`** |
| `DB_HOST` / `DB_DATABASE` | `srv1818.hstgr.io` / `u906587222_oimpresso` — **o banco do Hostinger** |
| `MCP_TOOLS_EXPOSED` | `true` |
| `Modules/TeamMcp` | presente, 89 arquivos |

**Não existe banco separado.** Logo *"migrar pra Jana"* é **troca de dono no código**, nunca migração de
dados. E o CT 100 não é um ambiente a tratar à parte: ele serve o `main`, então remover o módulo do
`main` o remove de lá no próximo deploy.

### Correção 1 — ADS, Governance, Brief e Vestuario estão **VIVOS**

A Fase 3 marca 6 acopladores como *"❌ morre (4º)"* / *"❌ morre (6º)"*. Medido em `origin/main` hoje:

| Módulo | Estado |
|---|---|
| `Modules/SRS` | **AUSENTE** — já deprecado |
| `Modules/ADS` | **VIVO** (119 arquivos) |
| `Modules/Governance` | **VIVO** (138) |
| `Modules/Brief` | **VIVO** (38) |
| `Modules/Vestuario` | **VIVO** (39) |
| `Modules/TeamMcp` | **VIVO** (89) |

Consequência dura: **o TeamMcp sai ANTES deles**, então os 6 acopladores não podem ser ignorados — têm
que ser **repontados**. E são `use` de verdade, não comentário:

- `Modules/ADS/Routes/web.php` → `use ...TeamMcp\Http\Controllers\Admin\{ToolsController,TeamScopesController}`
- `Modules/Governance/Http/Middleware/ActionGate.php` → `use ...TeamMcp\Services\ActorResolver`
- `Modules/Governance/Providers/GovernanceServiceProvider.php` → idem
- `Modules/Governance/Services/Checkers/IngestLivenessChecker.php` → `use ...TeamMcp\Services\IngestLivenessService`

### Correção 2 — `mcp_tokens` **já é da Jana**; não há MIGRATE de tabela

A Fase 4 pede *"MIGRATE obrigatório"* de `mcp_tokens`. Medido, a tabela **nunca foi do TeamMcp**:

| Peça | Onde vive hoje |
|---|---|
| migration que **cria** | `Modules/Jana/Database/Migrations/2026_04_29_100003_create_mcp_tokens_table.php` |
| Entity | `Modules/Jana/Entities/Mcp/McpToken.php` |
| middleware que autentica | `Modules/Jana/Http/Middleware/McpAuthMiddleware.php` |

O TeamMcp só **acrescenta a coluna `actor_id`** e cria `mcp_actors`. O R1 continua real — mas o trabalho
é mover **arquivos de migration**, não dados.

### Correção 3 — mover migration entre módulos é transparente pro Laravel

A tabela `migrations` em prod guarda **só o nome do arquivo**, sem path de módulo:

```
2026_05_05_240001_create_mcp_actors_and_link_tokens   (batch 85)
2026_05_07_140000_update_actor_display_name_maiara    (batch 99)
2026_06_15_100000_create_mcp_ingest_heartbeat_table   (batch 182)
2026_06_17_120000_create_cowork_handoffs_table        (batch 183)
```

Mover os 4 arquivos `TeamMcp/Database/Migrations/*` → `Jana/Database/Migrations/*` **preservando o nome**
não re-executa nada. A **E6 é muito mais barata** do que o plano supunha.

### Correção 4 — `mcp_tokens` está QUENTE, e o heartbeat não morreu

A Fase 2 reporta *"escrita mais recente 2026-06-17"* pra `mcp_tokens`. Esse campo era `updated_at`. Por
`last_used_at`, das 26 linhas **6 foram usadas hoje (2026-07-30)**, a mais recente às **18:24**. O R1 não
é teórico: há gente do time autenticando agora.

E o heartbeat não é código morto — as 28 linhas são **por `host`**, e os hosts são worktrees da máquina
do [W] (`D:\oimpresso.com\.claude\worktrees\...`). O produtor é o **watcher local** (`scripts/cc-watcher`),
que postava em `/api/cc/ingest`. Parou porque o watcher não está rodando, não porque o código morreu.
**DROP por morte do produtor está descartado** — a decisão vira [W]: *o watcher volta ou não?*

### Achado novo — um teste da Jana aponta pra classe inexistente há ~3 meses (LC-13)

`Modules/Jana/Tests/Feature/TaskRegistry/SyncMemoryWebhookTasksTest.php` instancia
`\Modules\Jana\Http\Controllers\Mcp\SyncMemoryWebhookController` — classe que **não existe** desde a
Fase 3.7 (2026-05-06), quando o controller foi pro TeamMcp. O diretório nem existia.

Por que ninguém viu: o arquivo tem **9 `it()`, só 3 com `->skip`** — os 4 que instanciam a classe **não
são pulados**. Ele está no testsuite do `phpunit.xml` (`Modules/Jana/Tests/Feature`) mas **fora da
allowlist** `.github/ci-sqlite-pest.list` → nunca roda. Verde por não-execução.

**A E2b conserta sozinha:** o teste voltou a resolver quando o controller saiu do TeamMcp. Ele agora aponta pra
`Modules\Forja\Http\Controllers\Mcp` (receptor final, decisão #5). O que o teste prova, de qualquer forma, é que o
controller **nunca foi do TeamMcp**: foi escrito quando ele morava fora de lá.

### Buraco no §Destino por função — ele cobre ~metade do módulo

A tabela de destino mapeia 8 grupos. O inventário tem **15 papéis / 89 arquivos**. **Não têm destino
declarado:** `Admin/ToolsController` e `Admin/TeamScopesController` (consumidos pelo **ADS vivo**),
`TeamController`, `TasksAdminController`, `CcSessionsController`, `ScorecardController`, `ForjaController`,
`DataController`, `InstallController`, `Mcp/HealthController`, os 4 `Console/Commands`, os 2 seeders e
`Config/retention.php`. Sem isso a **E5 não é executável** — registro como buraco em vez de inventar dono.

*(`Mcp/HealthController` resolvido na E2b: mesmo route group do webhook. As dependências são entidades `Mcp\*`
— que a decisão #5 move pra Forja junto com o resto do MCP, na fase F4 da proposal.)*

---

## Resíduo honesto

> Os três primeiros resíduos da redação original **foram resolvidos** pela §E1 (CT 100 medido · produtor do
> heartbeat identificado · banco único confirmado). Ficam registrados lá, não apagados. O que segue aberto:

- **Pest não rodado.** Tier 0 manda rodar no CT 100, e lá o container `oimpresso-mcp` aponta pro **banco de
  produção** — rodar suite nele está fora de cogitação. O `oimpresso-staging` serve, mas seu checkout está
  em 2026-07-23 com alterações não-commitadas de outra sessão. **A verificação real da E2b é o CI.** O que
  rodou local: `php -l` nos 6 arquivos PHP tocados (lint, não teste — permitido) + os gates de memória.
- **O smoke do R6 FOI FEITO — recibo abaixo (medido 2026-07-31, prod em `e2f861e69`).**
  | Prova | Resultado |
  |---|---|
  | Filesystem Hostinger | `Modules/Forja/Http/Controllers/Mcp/` com os 2 · `Modules/Jana/.../Mcp/` **vazio** · `TeamMcp/.../Mcp/` só `CcIngest` |
  | `GET /api/mcp/health` | `200` + `{"status":"ok","service":"oimpresso-mcp",...}` |
  | `POST /api/mcp/sync-memory` token errado | `401` |
  | `GET /api/mcp/health/auth` sem Bearer | `401` |
  | `GET /api/mcp/version` | `500` + `{"error":"Misconfigured"}` — resposta **do próprio controller**, por desenho (`MCP_DRIFT_TOKEN` só existe no CT 100) |
  | **Webhook GitHub (o que fecha o R6)** | re-entrega real de `push` às **01:31:43Z → `200`**, já com o deploy concluído |
  
  ⚠️ **Lição do próprio smoke (LC-08):** a 1ª rodada deu `500` em 4 rotas e eu declarei *"regressão em produção"*.
  Era **deploy em curso** — o `/login`, meu controle, estava `503` junto e eu não olhei antes de concluir. Com o site
  de pé, tudo respondeu certo. **Smoke durante deploy não é medição**: confira um controle fora do escopo primeiro.
- **504 no webhook é PRÉ-EXISTENTE, não regressão.** Medido antes×depois do #5083: **25% → 18%** de `504` nas entregas
  de `push`. O handler faz `git fetch` + `reset --hard` + reindex **síncrono** e estoura os 10s do GitHub; o próprio
  código confessa (*"vira queue se ficar lento"*, auditoria 2026-05-14). O cron de 5min cobre, mas ~1 em 4 pushes
  cai no fallback. **Não é desta deprecação** — fica nomeado pra não virar surpresa de quem tocar o webhook.
- **`§Destino por função` cobre ~metade do módulo** — a E5 não é executável até [W] decidir o destino dos
  papéis listados no buraco da §E1.
- **A ordem do conjunto mudou na prática.** O plano assume ADS/Governance já mortos; estão vivos. Ou os 6
  acopladores são repontados nesta deprecação, ou o TeamMcp espera os outros — decisão [W].
