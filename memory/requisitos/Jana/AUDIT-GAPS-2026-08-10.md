---
id: requisitos-jana-audit-gaps-2026-08-10
title: "Inventário de GAPS do módulo Jana — código morto e contrato quebrado (2026-08-10)"
type: auditoria
status: draft
authority: tecnico
lifecycle: ativo
quarter: Q3-2026
decided_at: 2026-08-10
decided_by: [claude]
module: Jana
tier: TECHNICAL_AUDIT
trust_level: advise
related_adrs: [0093, 0104, 0264, 0344]
parent_artifacts:
  - memory/proibicoes.md
  - memory/requisitos/Jana/SPEC.md
authors: [claude]
---

# Inventário de GAPS — módulo Jana

> **O que este doc é:** a lista COMPLETA dos arquivos com problema medido no módulo Jana,
> um por linha, com o defeito descrito e a prova.
>
> **O que este doc NÃO é:** ordem de deleção. Nada aqui foi apagado. Remover exige decisão
> [W] arquivo a arquivo — e a lápide de hoje em [`proibicoes.md` §5 2026-08-10](../../proibicoes.md)
> existe justamente porque uma afirmação de "morto"/"não pode apagar" mal-provada custou 3 meses.
>
> **Tema distinto** do [`GAP-ANALYSIS-91-100-2026-05-13.md`](GAP-ANALYSIS-91-100-2026-05-13.md),
> que trata de maturidade 91→100. Aqui é inventário de morto e de contrato quebrado.

## Como foi medido

Base: `origin/main` @ `58b177ed790`, clone completo (`--is-shallow-repository=false`).
Dois adversários read-only independentes (backend / frontend+rotas) + verificação minha por cima.

### ⚠️ COBERTURA REAL — 54%, e as lacunas não são rodapé

`git ls-files Modules/Jana` = **555 arquivos**. O que foi auditado:

| Área | Arquivos | Auditado? |
|---|---:|---|
| Código de produção | **305** | ✅ **sim** — 291 classes uma a uma + 14 não-classe (9 blades, 3 configs, `permissions.php`, `topnav.php`) |
| `Tests/` | **159** | ✅ **sim** — ver Cluster H (157 arquivos de teste, 1.274 casos) |
| `Database/` (81 migrations + 4 seeders) | **85** | ✅ **sim** — ver Cluster I (59 tabelas, 16 órfãs) |
| Raiz (`SCOPE.md`, `CHANGELOG.md`, `LICOES-OPERACAO.md`, `module.json`, `composer.json`, `start.php`) | **6** | ✅ **sim** — ver Clusters E-bis e E-ter |

> ✅ **555 de 555 — o módulo inteiro foi lido** ([W] 2026-08-10: *"leia o modulo inteiro"*).

**Por que as duas primeiras lacunas importam mais do que o número sugere:**

- **`Tests/` (159)** — este doc prova **código morto com teste verde** (4 casos nomeados). Auditar
  produção e não auditar teste é medir **metade do laço**: o eixo LC-13 (teste que não roda, ou que
  cobre código morto) está inteiro aqui dentro, não medido.
- **`Database/` (85)** — as migrations de `mcp_skill_approvals`, `mcp_automation_runs` e
  `mcp_skill_test_runs` estão nesse conjunto. São exatamente as tabelas que este doc prova
  **sem escritor**. A dívida de schema correspondente **não foi levantada**.

Universo frontend: **4 Pages + 6 componentes + rotas + charters/casos** — completo.

**Confiança por linha:**
- ✅ **verificado por mim**, independente do agente, com controle positivo no instrumento
- 🔶 **reportado pelo adversário**, não re-verificado por mim

Regra que vale pra este doc inteiro: *"morto" errado custa mais que dez "incerto"*.

---

## O que SOBREVIVE (contexto — o inventário abaixo é a minoria)

**Backend: 271 de 291 classes VIVAS.** 10 controllers · 39 MCP tools · 14 agentes IA ·
41 comandos registrados · 13 entities · 7 jobs · 68 de 74 services · 9 blades — todos com
mecanismo provado (rota, `view()`, `new X`, registro).

**Frontend: 4 de 4 Pages e 6 de 6 componentes VIVOS.** Zero rota órfã, zero import quebrado,
zero Page sem rota. O `JanaCockpitV2` era o único morto e saiu no [#5515](https://github.com/wagnerra23/oimpresso.com/pull/5515).

---

## CLUSTER A — 5 comandos artisan que existem no disco e NÃO estão registrados

**Medido ✅:** `Modules/Jana/Console/Commands/` tem **46** arquivos; o
`JanaServiceProvider::commands([...])` (L52-100) registra **41**. Os 5 abaixo são o diff exato.
Cada um tem **0 arquivos fora do próprio** que o referenciem — não há registro alternativo.
`app/Console/Kernel.php` só faz `$this->load()` de `app/Console/Commands`, nunca de `Modules/`.

**Consequência:** `php artisan <assinatura>` **falha em qualquer host**. Não é "não roda em prod" —
o comando não existe no registry do artisan.

| # | Arquivo | Assinatura | Linhas | Problema |
|---|---|---|---:|---|
| 1 | `Modules/Jana/Console/Commands/AutomationsSyncCommand.php` | `jana:automations:sync` | 63 | ✅ Não registrado. **É o único escritor** da tabela que a `AutomationsListTool` (viva) lê — ver Cluster B |
| 2 | `Modules/Jana/Console/Commands/CacheStatsCommand.php` | `copiloto:cache:stats` | 102 | ✅ Não registrado. Assinatura ainda com prefixo morto `copiloto:` |
| 3 | `Modules/Jana/Console/Commands/ContextualizeBackfillCommand.php` | `jana:contextualize-backfill` | 217 | ✅ Não registrado |
| 4 | `Modules/Jana/Console/Commands/McpGenerateDxtCommand.php` | `copiloto:mcp:generate-dxt` | 250 | ✅ Não registrado. Prefixo morto `copiloto:`. **`MEMORY_TEAM_ONBOARDING.md:29` manda rodar este comando** 🔶 |
| 5 | `Modules/Jana/Console/Commands/MetricasReflexivasCommand.php` | `copiloto:metricas-reflexivas` | 113 | ✅ Não registrado. Prefixo morto `copiloto:` |

> ⚠️ **3 dos 5 ainda usam o prefixo `copiloto:`** — o módulo virou Jana pela ADR 0088. Mesmo que
> alguém os registrasse hoje, a assinatura está no vocabulário morto.

---

## CLUSTER B — Automation Registry: a tool lê uma tabela que ninguém consegue popular

**O achado de maior valor do inventário**, e é a família **LC-15** (mecanismo anuncia saída que
não implementa) — com o agravante de que a mensagem falsa **vai para o usuário do produto**.

| # | Arquivo | Linhas | Problema |
|---|---|---:|---|
| 6 | `Modules/Jana/Mcp/Tools/AutomationsListTool.php` | — | ✅ **A tool está VIVA e registrada** no `OimpressoMcpServer`. A linha `:75` devolve ao usuário: *"(Rode `php artisan jana:automations:sync` se o registry estiver vazio.)"* — **comando que não existe**. É afordância anunciada e não implementada, entregue a quem usa |
| 7 | `Modules/Jana/Services/Mcp/AutomationRegistrySync.php` | **675** | 🔶 MORTO transitivo — o único caller é o `AutomationsSyncCommand` (morto). Fora dele, só docblock |
| 8 | `Modules/Jana/Entities/Mcp/McpAutomationRun.php` | 51 | 🔶 MORTO — referenciada só por `McpAutomation` (`hasMany`) e por baseline. **Zero writers** de `mcp_automation_runs`; a tool lê a coluna `last_run_at`, nunca a relação `->runs` |

**A entity `McpAutomation` fica VIVA** — a tool a consulta. Morto é só quem a **escreve**.

---

## CLUSTER C — Alertas: 4 classes mortas, disfarçadas de vivas pelo ServiceProvider

**Medido ✅:** `git grep -- "->avaliar("` no repo inteiro → **rc=1, zero hits**
(controle positivo `->handle(` → rc=0, **104 arquivos** — o instrumento funciona).

`AlertaService::avaliar()` é o **único** ponto que dispara o evento (`:66`). Ninguém o chama.

**Por que engana:** o `JanaServiceProvider` faz `Event::listen(CopilotoDesvioDetectado::class, ...)`
na L40 e `singleton(AlertaService::class)` na L106. Lidas isoladamente, as duas linhas parecem
wiring ativo. **Registro no container não é uso** — é a mesma armadilha do `app(X::class)`, pelo
lado do provedor.

| # | Arquivo | Linhas | Problema |
|---|---|---:|---|
| 9 | `Modules/Jana/Services/AlertaService.php` | 95 | ✅ MORTO. Única ref de código é o `singleton()` do provider; as outras 2 são **comentário** |
| 10 | `Modules/Jana/Events/CopilotoDesvioDetectado.php` | 30 | ✅ MORTO — nenhum dispatcher alcançável |
| 11 | `Modules/Jana/Listeners/NotificarDesvioListener.php` | 34 | ✅ MORTO — registrado em `Event::listen` para evento que nunca ocorre |
| 12 | `Modules/Jana/Notifications/MetaDesvioNotification.php` | 58 | 🔶 MORTO — só o listener morto o referencia |

**Coerente com o próprio código:** `AlertasController::updateConfig` é stub que diz
*"Persistir… e ligar no `AlertaService` é a US-COPI-061"* e devolve *"nada foi alterado"*
(honestidade instalada na onda 5, [#5496](https://github.com/wagnerra23/oimpresso.com/pull/5496)).

---

## CLUSTER D — 8 órfãos isolados

| # | Arquivo | Linhas | Problema |
|---|---|---:|---|
| 13 | `Modules/Jana/Services/Scorecard/AiScorecardJudge.php` | **344** | 🔶 MORTO — 2 refs, **ambas comentário** (`@see` numa migration) |
| 14 | `Modules/Jana/Services/Skills/PublicarSkillNoGitService.php` | 212 | 🔶 MORTO — 2 refs, ambas comentário (`{@see}` em `Forja/GitMainResolver` e bloco `\|` em `config/services.php`). **Só o filtro de comentário de bloco revelou** — sem ele contava 1 ref falsa |
| 15 | `Modules/Jana/Services/Peso/RelevanciaMetaInferer.php` | 210 | 🔶 MORTO — 2 refs, ambas comentário `@see` em `Config/config.php` |
| 16 | `Modules/Jana/Services/Skills/SkillTestRunnerService.php` | 184 | 🔶 MORTO — 0 refs de código |
| 17 | `Modules/Jana/Services/Memoria/BiTemporalResolver.php` | 58 | 🔶 MORTO em prod — 3 refs, todas comentário em `HistoricoMemoriaService`, que se autodenuncia: *"aqui ele vira SQL"*. **A lógica migrou pra SQL, o objeto ficou** — e tem Unit test rodando em lane própria |
| 18 | `Modules/Jana/Http/Requests/StoreMensagemRequest.php` | 58 | 🔶 MORTO — `ChatController` type-hinta `SendChatMessageRequest`, não este |
| 19 | `Modules/Jana/Http/Requests/StoreSugestaoRequest.php` | 55 | 🔶 MORTO — `escolher`/`rejeitar` usam `Request` cru |
| 20 | `Modules/Jana/Entities/Mcp/McpSkillApproval.php` | 47 | 🔶 MORTO — tabela `mcp_skill_approvals` sem leitor nem escritor de produção |
| 21 | `Modules/Jana/Entities/Mcp/McpSkillTestRun.php` | 51 | 🔶 MORTO transitivo — 4 refs, todas dentro do `SkillTestRunnerService` morto |

**Total dos clusters A-D: 2.907 linhas em 20 arquivos** (contado ✅).

> ⚠️ **Código morto com teste VERDE** — para calibrar quanto CI verde prova:
> `AutomationRegistrySyncTest`, `BiTemporalResolverTest` (lane própria), `AiScorecardJudgeTest`,
> e o `Wave14GovernanceV3Test` cobrindo o `AlertaService`.

---

## CLUSTER E — Contrato quebrado (o registro está errado, o código não)

Nenhum destes é código morto. São **artefatos de governança afirmando algo sobre um alvo que
ninguém reabriu** — a mesma forma do `JanaCockpitV2`.

| # | Arquivo | Problema |
|---|---|---|
| 22 | `memory/governance/scorecards/screens/jana-painel.yaml` | ✅ Scorecard de tela **que não existe** — aponta pra `Pages/Jana/Painel.tsx`, removida em 06/08. **Zero referenciadores** (`rc=1`; controle positivo `jana.access` → 20 arquivos). Pior: há **193 scorecards = as 193 telas** que o `screen-grades-ratchet` conta, e ele reporta `✅ 193 ok`. É **nota 74 congelada que nunca pode regredir porque não há arquivo pra graduar** |
| 23 | `resources/js/Pages/Jana/Index.charter.md` | ✅ L15: `related_charters` aponta pra `Cockpit.charter.md`, **apagado**. E o motivo de ninguém ver: **nenhum script valida `related_charters`** (0 hits em `scripts/`, `.github/`, `tests/`), e o `deadlink-gate` varre só `memory/` + `.md` da raiz — **`Pages/**/*.charter.md` está FORA do corpus**. 🔶 L21 declara `permissao: copiloto.access`; a key real é `jana.access` |
| 24 | `resources/js/Pages/Jana/Memoria.charter.md` | ✅ Declara `permissao: copiloto.memoria.manage` e o anti-hook *"⛔ Permitir edit sem `copiloto.memoria.manage`"*. Essa permissão **não existe**: o registry tem **22 keys, todas `jana.*`, zero com "memoria"**; os 2 únicos hits do repo são **dentro do próprio charter**. 🔶 E o `MemoriaController` não tem checagem nenhuma — a única defesa é o `can:jana.access` do grupo. **Contrato de acesso que só existe no papel, numa tela LGPD** |
| 25 | `resources/js/Pages/Jana/components/FabJana.tsx` | 🔶 Monta `/ia/conversa?context=<rota>` e **ninguém lê** o parâmetro (`ChatController@index` não toca `query('context')`; zero `URLSearchParams` em `Pages/Jana/**`, com controle positivo de 20 arquivos no resto do JS). Agravante: `Index.tsx` passa `contextRoute="/ia/dashboard"`, URL que virou **301 → /ia** |
| 26 | `memory/requisitos/Jana/RUNBOOK-governanca-mcp.md` | 🔶 `status: ativo` descrevendo tela que **saiu pro Governance** em 05/08 (rota hoje é 301). Cita `/copiloto/admin/governanca` |
| 27 | `memory/requisitos/Jana/RUNBOOK-qualidade-admin.md` | 🔶 Idem — `status: ativo`, tela migrada, cita `/copiloto/admin/qualidade`. **Assimetria**: os irmãos `RUNBOOK-cockpit.md` e `RUNBOOK-custos-admin.md` foram corretamente marcados `arquivado` com lápide |

---

## CLUSTER E-bis — a FONTE PRIMÁRIA do módulo aponta pra um módulo deletado

| # | Arquivo | Problema |
|---|---|---|
| 30 | `Modules/Jana/SCOPE.md` | ✅ O `not_contains` roteia **duas capacidades** pra um destino que não existe: *"Skills governance → **Modules/ADS**"* e *"Decision flow → **Modules/ADS**"*. `Modules/ADS` foi **removido** pela [ADR 0363](../../decisions/0363-governance-incorpora-ads-nucleo-sem-receptor.md) (§5 2026-08-02) — o núcleo foi pro Governance, partes pra Forja/Jana. O `contains` está **correto** (10 de 10 controllers existem, conferido um a um) |

**Por que ninguém viu — duas cegueiras estruturais sobre o arquivo que a
[`.claude/rules/modules.md`](../../../.claude/rules/modules.md) chama de FONTE PRIMÁRIA:**

1. **O corpus do `knowledge-drift` é `memory/`** (medido: `REQ = join(ROOT,'memory','requisitos')`,
   `MEM = join(ROOT,'memory')`). **`Modules/*/SCOPE.md` está fora dele por construção** — o gate
   anti-ghost não pode ver esta citação, nem em princípio.
2. **Nenhuma máquina compara `contains` com a árvore.** Isso não é dedução minha — **o próprio
   `SCOPE.md` confessa**, num comentário sobre o `BriefController`: *"Ficou listado aqui por ~7
   semanas depois de deixar de existir — nenhuma máquina compara `contains` com a árvore, então o
   SCOPE apodreceu calado."*

**Escala medida (fora do escopo deste doc, registrado pra não virar "descoberta" futura):**
**4 SCOPE.md** citam `Modules/ADS` — `Forja`, `Governance`, `Jana`, `KB`. É a mesma classe,
repo-wide, e o gate está verde em todos.

---

## CLUSTER E-ter — raiz do módulo (5 arquivos restantes, lidos 2026-08-10)

| # | Arquivo | Problema |
|---|---|---|
| 31 | `Modules/Jana/module.json` | ✅ O campo `description` — **o primeiro texto que qualquer pessoa ou agente lê sobre o módulo** — afirma: *"URL/permissions/config keys mantêm prefixo legacy `copiloto.*` por compatibilidade."* **Falso em 2 de 3:** o prefixo de URL é **`ia`** (`Http/routes.php:51`, ADR 0180) · as permissões são **22 keys, todas `jana.*`**, zero `copiloto` (a migration `2026_05_09_140000_rename_copiloto_permissions_to_jana` já renomeou). **Só `config keys` procede** — medido: **201** usos de `config('copiloto.` × **28** de `config('jana.` |
| 32 | `Modules/Jana/CHANGELOG.md` | ✅ Última entrada de conteúdo é **Wave 25, 2026-05-16**. A política declarada no próprio arquivo diz *"Toda US/feature significativa que tocar `Modules/Jana/` ganha entry aqui"*. Medido (clone completo): **246 commits** tocaram `Modules/Jana` desde 17/05; o CHANGELOG foi tocado **2 vezes**. Ficaram de fora, entre outras, as 4 ondas da `US-COPI-148` (fusão das telas), a onda 5 e a remoção do `JanaCockpitV2` |

**Sem defeito:** `start.php` (15 ln, faz só o `require` das rotas, declarado em `module.json "files"`)
· `composer.json` · e as declarações **estruturais** do `module.json`, que conferi uma a uma e
estão **corretas**: `d4_audit.service` → `JanaAuditService` existe; as **18** entities de
`d1_overrides.cross_tenant_by_design` existem todas.

`LICOES-OPERACAO.md` não é gap — é working doc **proposto** (§10.4), aguardando aprovação [W]
por desenho próprio.

> **A cadeia causal que o #31 fecha:** os gaps **#23** e **#24** são charters declarando
> `copiloto.access` e `copiloto.memoria.manage`. Isso é **exatamente o que o `module.json`
> afirma**. A descrição stale é a origem plausível das permissões fantasma — mesmo padrão do
> `JanaCockpitV2`, onde cada doc herdou do anterior sem reabrir a fonte.

---

## CLUSTER H — `Tests/` (157 arquivos, 1.274 casos) — auditado 2026-08-10

> ⚠️ **Correção de premissa, antes dos números.** A versão anterior deste doc tratava
> "teste fora da lane de PR" como **vermelho invisível**. **Está errado neste repo**, e quem
> corrigiu foi o adversário, indo à porta viva
> [`test-lane-coverage.mjs`](../../../scripts/governance/test-lane-coverage.mjs), cujo cabeçalho
> crava: *"FORA DO CI DE PR ≠ NUNCA RODA"*. A nightly `ct100-fullsuite.sh` roda
> `--roots tests,Modules` shardado. Fora-do-PR mede **latência de feedback**, não ausência.
> Contexto que desinfla: **64,9% do repo inteiro** está fora do PR; a Jana (64,3%) é a norma.

| Superfície | Arquivos | Casos |
|---|---:|---:|
| Roda no **PR** | **56** | 422 |
| └ `ci.yml:112` (sqlite, lê `.github/ci-sqlite-pest.list`) | 30 | — |
| └ `jana-pest.yml:123-136` (MySQL, allowlist literal) | 14 | — |
| └ `jana-logica-pura-pest.yml:155-166` (sqlite, path-scoped) | 12 | — |
| Só na **nightly** (latência, não ausência) | 101 | 852 |

### 🔴 O achado — 29 arquivos / 211 casos que NUNCA executam, em lugar nenhum

Não é "fora do PR". É **ausência absoluta**, e sai do cruzamento **guard × driver**:
o arquivo tem `if (DB::connection()->getDriverName() !== 'sqlite') markTestSkipped(...)` em
`beforeEach` top-level · a nightly é **MySQL** (`scripts/tests/ct100-fullsuite.sh:122` grava
`DB_CONNECTION=mysql`) ⇒ pula lá · e o arquivo **não está** na única superfície sqlite.
Resultado: **0 assertions, sempre.** Inclui LGPD (`DsrServiceTest`,
`LgpdEsquecerTitularToolTest`), `Memoria/Freshness/StalenessDetectorTest` (16 casos) e
`Mcp/AutomationRegistrySyncTest` (12 — teste do serviço morto do Cluster B).

### 🔴🔴 O pior caso, verificado por mim linha a linha

| # | Arquivo | Problema |
|---|---|---|
| 33 | `Modules/Jana/Tests/Feature/Ai/BriefDiarioAgentTest.php` | ✅ Está em `jana-pest.yml:136`, **última linha do bloco rotulado `ALLOWLIST VERDE (catraca)`** — lane que roda `DB_CONNECTION: mysql` (`:113`). Mas o arquivo faz `markTestSkipped` quando o driver **não é sqlite** (`:32-33`). **Não está** em `.github/ci-sqlite-pest.list` (arquivo existe, 29KB; grep rc=1). A nightly também é MySQL. ⇒ **nunca roda, em nenhuma superfície, e sai verde.** Seus **6 casos** incluem `R-COPI-202-003 — Tier 0 cross-tenant: 5 Tools(biz=1) NUNCA expoem dados de biz=99` — **trava multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md), IRREVOGÁVEL) que nunca correu**, dentro de um bloco que se chama "catraca" |

### Outros achados do eixo

| # | Item | Problema |
|---|---|---|
| 34 | `phpunit.xml:20,26` | 🔶 Registra `Jana/Tests/Feature` **e** `Unit`, com comentário celebrando o fim da "falsa cobertura". Mas **nenhuma lane usa `--testsuite`** (zero no repo; o único hit é um comentário dizendo isso) — o registro **não alcança o CI**, e o comentário faz o problema parecer resolvido |
| 35 | `Modules/Jana/Tests/Unit/.../BiTemporalResolverTest` | 🔶 **Roda** (`jana-logica-pura`) sobre classe com **zero consumidores** (gap #17). É a catraca **travando o cadáver** |

**Zero defeitos** no eixo "teste apontando pra arquivo inexistente": todos são fixtures de runtime
ou **contrato de ausência deliberado** — o `CockpitMockRemovidoTest` asserta que o `Cockpit.tsx`
foi apagado, com controle negativo explícito. Correto, não é gap.

### O que o adversário NÃO conseguiu provar (e eu também não)

- **Se os 101 da nightly passam.** Pest é proibido fora do CT 100; sabemos que são *alcançados*,
  não o veredito.
- **Assertions por caso.** O discriminador honesto de LC-13 é `assertions`, não `0 failed`. Os
  sumários JUnit existem mas são artefatos de CI, inacessíveis daqui. **A prova dos 29 é estática**
  (guard + driver), não observada.
- **Há quanto tempo** os 29 estão mudos — não medido.
- **Deadness transitiva exaustiva** — o script transitivo teve falso-negativo conhecido; os 5
  comandos e os 2 zero-consumidor estão provados, **pode haver mais**.

---

## CLUSTER I — `Database/` (81 migrations + 4 seeders) — auditado 2026-08-10

> ⚠️⚠️ **LEIA ANTES DE COGITAR QUALQUER `DROP`.** "Sem escritor" aqui significa, em vários casos,
> **feature não construída** — não *feature morta*. Cinco das órfãs são **reivindicadas pelo
> `Modules/Forja/SCOPE.md`** (`mcp_views`, `mcp_issue_templates`, `mcp_task_attachments`,
> `mcp_task_memory_links`, `mcp_epics`) + `mcp_components`: são **schema à frente do código com
> dono declarado**. E a **`US-COPI-147` já decidiu ❌ NÃO dropar 4 delas**, por serem pré-condição
> de roadmap ativo da Forja (`PMG-012` pressupõe `mcp_views`; `mcp_task_memory_links` é o
> diferencial D1 do TaskRegistry). **Esta auditoria não contradiz aquela decisão — a confirma pelo
> eixo código.** A distinção *não-construída × morta* é decisão [W], não de varredura.

**Contado:** 81 migrations, das quais **59 fazem `Schema::create`** → **59 tabelas**
(13 `jana_*` renomeadas + 2 `jana_*` diretas + **44 `mcp_*`**) + **13 views legacy `copiloto_*`**.
**16 de 59 órfãs (27%)**.

### 🔴 8 órfãs TOTAIS — sem escritor E sem leitor

| # | Tabela | Prova |
|---|---|---|
| 36 | `jana_negative_cache` | 🔶 **Zero** ocorrências do nome em qualquer `.php`. Sem Entity. O `NegativeCacheService` (vivo) implementou com a facade `Cache::` |
| 37 | `mcp_issue_templates` | 🔶 Zero ocorrências fora da migration |
| 38 | `mcp_views` | 🔶 Zero fora da migration — ⚠️ **reivindicada pela Forja** |
| 39 | `mcp_task_attachments` | 🔶 Zero fora da migration — ⚠️ **reivindicada pela Forja** |
| 40 | `mcp_task_memory_links` | 🔶 Zero fora da migration — ⚠️ **reivindicada pela Forja** |
| 41 | `mcp_skill_approvals` | 🔶 Confirma o gap #20 pelo eixo schema |
| 42 | `mcp_automation_runs` | 🔶 Confirma o gap #7 pelo eixo schema |
| 43 | `mcp_skill_test_runs` | 🔶 Escritor **existe mas é inalcançável** (`SkillTestRunnerService`, gap #16). Zero leitores |

**As 3 do briefing confirmadas — e apareceram MAIS 5.**

### 🔴 5 órfãs DE ESCRITA — a tabela é lida, nada a preenche

| # | Tabela | Problema |
|---|---|---|
| 44 | `jana_sugestoes` | ✅ **A única com fachada de USUÁRIO.** O `ChatController` **lista** (`:120`), **aceita** (`:590`) e **rejeita** (`:630`) sugestões — e `Sugestao::create` / `new Sugestao` / `sugestoes()->create()` dá **rc=1 no repo inteiro** (controle positivo: `Conversa::create` → 4 arquivos). O `StoreSugestaoRequest` (gap #19) é o FormRequest morto desse fluxo |
| 45 | `mcp_usage_diaria` | ✅ **O escritor tem NOME e nunca foi construído.** `mcp:agregacao-diaria` aparece em **4 lugares, todos citando, nenhum implementando** — e o `SystemAuditCommand:281` admite por escrito: *"referenciado nas migrations mas não implementado"*. Consequência: o check `cost_dashboard_aggregation` é **estruturalmente incapaz de ficar verde**, e o `Governance/SCOPE.md` afirma um "cron 23:55" que não existe (o das 23:55 no `Kernel` é outro comando, gravando outra tabela) |
| 46 | `mcp_user_scopes` | 🔶 Lida por `Usuario360Controller:180` e `UserLockoutService:207`; zero escrita, nenhum seeder a preenche |
| 47 | `mcp_epics` | 🔶 Lida por **5 controllers da Forja** + validação `exists:mcp_epics,id`. `McpEpic::create` só em teste — ⚠️ **reivindicada pela Forja** |
| 48 | `mcp_components` | 🔶 Lida por `TaskParserService:652`. **Zero escritas em lugar nenhum, nem em teste** |

### 🟠 3 SÓ-ESCRITA — grava e ninguém lê

| # | Tabela | Problema |
|---|---|---|
| 49 | `mcp_alertas` | 🔶 2 escritores. O único `SELECT` do repo está **comentado** (`Governance/DriftAlertService.php:131`). Os alertas gravados **nunca chegam a lugar nenhum** |
| 50 | `mcp_scorecard_ai_suggestions` | 🔶 Escrito pelo `AiScorecardJudge` — que é **código morto** (gap #13). Nenhuma leitura em produção |
| 51 | `mcp_handoff_drafts` | 🔶 A `US-COPI-147` criou a tabela pra fechar um `insert` fantasma; fechou a escrita, **o custo gravado não é consumido** |

### Drift de declaração

| # | Item | Problema |
|---|---|---|
| 52 | `Modules/Jana/SCOPE.md` → `db_tables_owned` | 🔶 Declara **13** tabelas; as migrations criam **59**. As 44 `mcp_*` aparecem só em **prosa** no `purpose`, não no campo estruturado (que alimenta o `catalog.json`). E **`jana_ui_judge_runs`** + **`jana_health_write_canary`** estão ausentes **do campo E da prosa** — duas `jana_*` sem dono declarado em lugar nenhum |
| 53 | `mcp_workflows` | 🔶 **Sem dono declarado** — `git grep mcp_workflows -- 'Modules/*/SCOPE.md'` vazio. A `US-COPI-147` o atribuía a `TeamMcp`, módulo que **não existe mais** |
| 54 | 13 views `copiloto_*` | 🔶 Ainda criadas por `2026_05_06_120000`. O `SCOPE.md` diz *"Drop planejado: **2026-06-05** (ADR 0092)"* — hoje é 2026-08-10 e **não existe migration de drop**. Zero código lê os nomes `copiloto_*`: superfície morta viva no schema |
| 55 | `Modules/Jana/Console/Commands/BackfillTasksFromMarkdownCommand.php:53` | 🔶 Manda rodar `db:seed --class=Modules\Copiloto\Database\Seeders\McpDefaultsSeeder` — **namespace pré-rename**, classe que não existe. Mesma família do gap #6 (instrução que não roda) |

### INCERTO — a contradição que não consegui resolver

| # | Item | Por que INCERTO |
|---|---|---|
| 56 | 9 migrations com DDL cru MySQL-only sem gate de driver | 🔶 `MODIFY COLUMN` / `CREATE TRIGGER … SIGNAL SQLSTATE` não existem em SQLite, e nenhuma das 9 tem `getDriverName`. Isso **contradiz** o docblock de `2026_06_15_140000:25`, que afirma *"a lane per-PR roda **todas as migrations** contra SQLite `:memory:`"*. Outras 3 migrations do mesmo módulo **sabem disso e se protegem** — o padrão existe e não foi aplicado nestas 9. **As duas afirmações não podem ser verdadeiras ao mesmo tempo**, e sem rodar migration não sei qual cai |

### ✅ Sem defeito nestes eixos (verificado)

**Zero duplicatas** — nenhuma das 59 tabelas é criada duas vezes (`Schema::create` fora do módulo → rc=1).
**Todas as 81 têm `down()`**, e todo `drop*` está no `down()`, nenhum no `up()`.
**A cadeia de 4 migrations que alarga o ENUM de `mcp_memory_documents.type` é cumulativa e correta** — nenhum valor se perde.
**`business_id` (Tier 0 · ADR 0093): nenhuma violação encontrada.** 17 das 59 têm a coluna; as ausências estão documentadas uma a uma no próprio código como *repo-wide by-design* (ADR 0280), e o grupo que depende de escopo por parent é coberto pelo `ScopeByBusinessViaParent`.

### O que NÃO foi provado

- **Nada sobre linhas em produção** — sem banco. Tudo aqui é sobre **código alcançável**.
  Quem decidir DROP tem que cruzar com `COUNT(*)` em prod.
- **Se as 9 migrations do #56 quebram a lane SQLite** — não é possível rodar migration aqui.
- **Se os seeders manuais já rodaram em prod.** Nenhum dos 4 está em `DatabaseSeeder` nem no
  `deploy.yml` — só `db:seed --class=` manual. Se nunca rodaram, `mcp_scopes`, `mcp_workflows` e
  `jana_memoria_gabarito` também estão vazias na prática.
- **Consumidores fora do repositório** (cliente externo lendo a tabela direto no banco).

---

## CLUSTER F — Já declarado em canon (confirmado, não é achado novo)

| # | Arquivo | Problema |
|---|---|---|
| 28 | `resources/css/sells-cowork-insights.css` | ✅ **776 linhas** com `@import` global em `inertia.css:13` e **zero consumidor JS** — o `JanaCockpitV2` era o único (83 ocorrências). Remover exige aposentar os `it()` vivos do `SellsTabsViewModeTest` que guardam sua existência, e o prefixo `.vd-*` é partilhado com o bundle do Financeiro (138 classes, com teste de contagem). **Ato de governança, decisão [W]** |
| 29 | `memory/governance/exposicao-tier0-baseline.json` | ✅ L669 tem entrada stale de `Jana/components/JanaCockpitV2.tsx`. O script **já não a emite** (filtro corrigido em 07-27) e o `--check` **não lê** aquele array. Regenerar o arquivo inteiro varreria drift de terceiros |

## CLUSTER G — Cobertura de contrato incompleta (porta viva `npm run screen:files`)

| Tela | Falta |
|---|---|
| `resources/js/Pages/Jana/Index.tsx` | 🔶 **sem `.casos.md`** — trio incompleto |
| `resources/js/Pages/Jana/Chat.tsx` | 🔶 **sem `.casos.md`** — trio incompleto |

🔶 A porta acusou 2 ambiguidades a resolver por declaração no charter: `Chat` tem **2**
visual-comparison candidatos; `Pro` tem **2** RUNBOOK candidatos.

---

## O que NÃO está provado (declarado, não escondido)

1. **Se algum dos 20 mortos é invocado por caminho fora do repo** (cron do hPanel, `Artisan::call`
   em script no CT 100, `.env`). Varredura de repositório não fecha claim sobre execução em
   servidor. **Para os 5 comandos isso é irrelevante:** sem registro, falham em qualquer host.
2. **Cluster Metas** (`MetasController`, `ApuracaoService`, `ApurarMetaJob`, 6 blades): **VIVO no
   código** — rota + `view()` + refs provados. Mas o §5 de 09/08 registra **0 metas cadastradas em
   prod**. *Liveness de código ≠ liveness de função.* Não há acesso a DB aqui.
3. **Superfície MCP** (39 tools + 2 resources + 1 prompt): viva e registrada, mas a exposição
   depende de `config('mcp.tools_exposed')` — env, não medido.
4. **157 dos 159 arquivos de teste**: não medido se cada um roda em alguma lane (eixo LC-13).
   Sabemos que há **código morto com teste verde** — ver aviso no Cluster D.
5. **81 migrations + 4 seeders**: fora do escopo, zero medição.
6. **Estado das tabelas em prod** (`mcp_skill_approvals`, `mcp_automation_runs`,
   `mcp_skill_test_runs`, `jana_metas`): sem acesso a DB. *"Sem escritor no código"* é o que está
   provado; *"vazia em prod"* é outra afirmação, que este doc **não faz**.
7. **`outcome-metrics.mjs:91`** (`{id:'jana-cockpit'}`): parece stale, mas um dos tokens ainda casa
   o protótipo vivo `chat-jana.jsx`. **INCERTO** — não medi o efeito no output.

---

## O padrão, que é o achado acima dos achados

Os gaps do Cluster E e o `JanaCockpitV2` têm a **mesma assinatura**:

> **um artefato de governança afirma algo sobre um alvo que ninguém reabriu — e nenhum gate
> cobre aquele eixo.**

Os do Cluster A e C têm a assinatura **irmã**:

> **presença de registro ≠ execução.** Arquivo de comando no disco sem `commands([...])`;
> `Event::listen` + `singleton()` para uma cadeia que ninguém invoca.

Em nenhum dos 29 casos o código está errado. **O que está errado é o registro sobre o código.**

⚠️ **Não proponha gate novo pra isso sem FP medido antes.** A forma óbvia (validar
`related_charters`, exigir scorecard com tela viva) é critério sintático, e este §5 já tem
**cinco** lápides de guard sintático que reprovava o legítimo. O caminho honesto é consertar e
ver se a classe reincide (two-strikes, [ADR 0344](../../decisions/0344-two-strikes-cobre-processo.md)).
