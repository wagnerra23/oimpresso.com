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
| `Tests/` | **159** | ❌ **NÃO** |
| `Database/` (81 migrations + 4 seeders) | **85** | ❌ **NÃO** |
| Raiz (`SCOPE.md`, `CHANGELOG.md`, `LICOES-OPERACAO.md`, `module.json`, `composer.json`, `start.php`) | **6** | ❌ **NÃO** (exceto `SCOPE.md`, coberto depois — ver #30) |

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
