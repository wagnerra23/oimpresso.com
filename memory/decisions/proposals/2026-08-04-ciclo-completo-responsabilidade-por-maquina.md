---
proposal_id: ciclo-completo-responsabilidade-por-maquina
status: proposto
created: 2026-08-04
proposed_by: claude-code
decided_by: _pendente — merge [W] = ratificação_
parent_adr: 0345 (taxonomia de arquivos-tema) + 0314 (lei do required) + 0336 (promoção por mordida)
related_adrs: [0070, 0264, 0273, 0275, 0294, 0306, 0314, 0336, 0345]
type: arquitetura-de-conhecimento
supersedes: []
---

# Ciclo completo do trabalho — responsabilidade por MÁQUINA, não por artefato

- **Status:** proposto em 2026-08-04 por [CC], a pedido de [W]. Merge = ratificação.
- **Origem:** [W] 2026-08-04 — *"como seria o ciclo completo? e os responsáveis?"* → refinado por ele mesmo para *"responsabilidade por máquinas. quem deveria cobrar"*.
- **NÃO é paralelo** (§5 proibicoes 2026-06-05): estende a [ADR 0345](../0345-topicos-vivos-aprendizado-por-critica-revisada.md) (que definiu os artefatos) respondendo a pergunta que ela deixou aberta — *quem cobra cada etapa*. Não abre programa novo.

## Contexto — medições em worktree `claude/jana-fronteira`, 5 atrás / 3 à frente de origin/main, 2026-08-04

### O ciclo tem 8 etapas e nenhuma falta

`pedido` → `requisito` → `contrato da feature` → `aprovação` → `execução` → `contrato da tela` → `gates` → `âncora e produção`, com a âncora reabastecendo o requisito.

### A força de cobrança é bimodal

| Etapa | Máquina | Força |
|---|---|---|
| 1 · pedido | — | não enforçável (entrada humana) |
| 2 · requisito | `SPEC (schema)` · `anchor-lint` · `anchor entry/covers` | **required ×3** |
| **3 · contrato da feature** | `feature-lint` · `plans-index --check` | **advisory** |
| **4 · aprovação** | `plan-health` (**3** invocadores) · `jana:plan-drift` (**0** invocadores) | **assimétrica: advisory com sinal + NÃO-EXECUTADA** |
| **5 · execução** | `mcp:tasks:unassigned` (06:45) · `mcp:tasks:health-check` (06:20) | **advisory, superfície desligada** |
| 6 · contrato da tela | `casos-gate` · `screen-coverage-gate` · `Charter` · `charter status:live` | **required ×4** |
| 7 · gates | `Governance Gate` · `gate selftest` | **required ×2** |
| 8 · âncora | `anchor-lint` · `anchor entry/covers` · `âncora stale` | **required** |

**As pontas mordem; o miolo não — e não por ausência de máquina.** A formulação precisa — a que sobrevive a contraexemplo — **não** é *"as etapas 3, 4 e 5 somam zero required"*, e sim: **nenhum dos 35 required tem como OBJETO o contrato dessas etapas.** Dois required encostam nelas **de raspão** — medidos no fim desta seção. Existem **duas** máquinas de auditoria agregada, e elas são **distintas** (medido no worktree declarado no cabeçalho desta seção; dos 6 arquivos citados abaixo, só o `governance-drift.yml` difere de `origin/main`, e a linha 87 usada aqui é **idêntica** nos dois — `git ls-tree origin/main -- .github/workflows/governance-drift.yml` + `git cat-file -p <blob> | grep -n "daily health-check"` → `87:`):

| | **(a)** `governance:audit --all --notify` (PHP) | **(b)** `scripts/governance/governance-audit.mjs` (Node) |
|---|---|---|
| Onde é invocada | [`app/Console/Kernel.php:1256`](../../../app/Console/Kernel.php) — `dailyAt('06:35')`, `environments(['live'])` · + [`.github/workflows/governance-drift.yml:87`](../../../.github/workflows/governance-drift.yml) (job diário) | **em lugar nenhum** |
| O que executa | os **12** DriftCheckers de [`Modules/Governance/Config/config.php:73-86`](../../../Modules/Governance/Config/config.php) | bateria de **14** entradas |
| Cobre as etapas 3/4? | **não** — não inclui `plan-health` nem `jana:plan-drift` | **sim** — inclui os dois |
| Tem `kind`? | **o conceito não existe** no comando | sim — **2** `kind:'required'` (`memory-health` L33, `gate-selftest` L34) + 12 `advisory` |

Comandos:

- `sed -n '74,85p' Modules/Governance/Config/config.php | grep -c '::class,'` → **12**
- `sed -n '/^const BATTERY/,/^\];/p' scripts/governance/governance-audit.mjs | grep -c 'kind:'` → **14**; `grep -n "kind: 'required'"` no mesmo trecho → **2** (L33, L34)
- `grep -c "kind\|advisory" Modules/Governance/Console/Commands/GovernanceAuditCommand.php` → **0**; `grep -n "plan"` no mesmo arquivo → vazio

**Invocadores de (b) — varredura completa e contada:** `git grep -n "governance-audit" -- .` → **32** ocorrências. Fora de `.md`, do próprio arquivo e do falso-positivo de substring `governance-auditoria` em `governance/doc-id-index.json` → **8** — e **as 8 são comentário/docblock**: `JanaDriftSentinelCommand.php:39,93` · `PlanDriftCommand.php:31,63,70` · `Kernel.php:465` · `plan-health.mjs:15` · `selftest-registry-check.mjs:215`. **Zero** ocorrência em `.github/workflows/`, `package.json`, `.claude/` ou cron (varredura em disco por `*.yml|*.yaml|*.json|*.sh|*.mjs|*.js|*.ps1`, fora de `node_modules`/`vendor`). O próprio [`scripts/governance/selftest-registry-check.mjs:215`](../../../scripts/governance/selftest-registry-check.mjs) **já registra** o caso: *"3 scripts cujo único 'invocador' era um comentário — `governance-audit.mjs` …"*.

Ou seja: **o que roda diário (a) não cobre as etapas 3 e 4; o que cobre (b) não roda.** A máquina do miolo está escrita e testada — o que falta é **invocação**, não código.

#### Mas a etapa 4 não é uniforme: uma metade tem sinal, a outra não existe em runtime

O par da etapa 4 **não** é "duas máquinas advisory". "Advisory" implica que o sinal existe e é reportado — isso vale para uma metade só. `plan-health` **não depende de (b)**: tem invocadores próprios (medido 2026-08-04, varredura contada em disco, fora de `node_modules`/`vendor`):

| invocador de `plan-health` | onde | cadência |
|---|---|---|
| gate de PR | [`.github/workflows/plan-health-gate.yml:54,61`](../../../.github/workflows/plan-health-gate.yml) — `--selftest` (morde) + `--check` (`continue-on-error: true` no job **e** no step) | todo PR que casar o `paths:` |
| Daily Brief | [`PlanHealthBriefLineService.php:120`](../../../Modules/Governance/Services/PlanHealthBriefLineService.php) shell-out `node … --json`, consumido por [`GenerateBriefCommand.php:67`](../../../Modules/Forja/Console/Commands/GenerateBriefCommand.php) | **6×/dia** — `cron('0 7,11,14,17,20,23 * * *')`, `environments(['live'])`, [`Kernel.php:969-973`](../../../app/Console/Kernel.php) |
| sentinela de memória | [`memory-health.mjs:720`](../../../scripts/governance/memory-health.mjs) **Check J** (`kind: 'plan-health'`) | junto do `memory-health` |

Comando do recibo: `grep -rn "plan-health" --include="*" . | grep -v node_modules | grep -v "^./vendor" | grep -v "^./memory/" | grep -v "^./scripts/governance/plan-health.mjs:"` → os 3 invocadores acima + registro/doc (`gates-registry.json`, `MAQUINAS-INVENTARIO.md`) + a linha 38 da bateria (b). O `--selftest` do gate roda verde localmente (**7/7**, incl. bite `em-execução SEM parent_plan → fail` e controle negativo) — logo o sinal do lado `plan-health` não é carimbo.

`jana:plan-drift` tem **zero** — nem gate, nem brief, nem check. Logo a linha da etapa 4 lê-se: **`plan-health` = advisory com sinal real diário; `jana:plan-drift` = não-executada.** Chamar as duas de "advisory" apaga a diferença, e a segunda é a classe que [`proibicoes.md` §Sempre fazer item 2](../../proibicoes.md) nomeia — ***"máquina que existe e NINGUÉM invoca é bug, não neutralidade"***: trate como defeito a corrigir, não como cobertura fraca.

⚠️ **Teto desta medição:** tudo acima é varredura de **texto do repo** (grep contado), não oráculo de runtime — a §5 [2026-07-17](../../proibicoes.md) exige `schedule:list`/`runsInEnvironment()` para afirmar o que roda, e este worktree não roda PHP (artisan/Pest só no CT 100). Portanto: *"0 invocadores no repo"* está **medido**; *"não roda em prod"* segue **por confirmar** com `php artisan schedule:list | grep plan-drift` no CT 100.

#### Os 2 required que encostam no miolo — e por que a ausência é EMPÍRICA

Enumerados os **35** required um a um, **dois** tocam as etapas 3/4/5 — e tocam o **continente**, nunca o **conteúdo**:

| Required transversal | Onde encosta no miolo | O que de fato cobra |
|---|---|---|
| `deadlink-gate (ratchet · integridade referencial)` | `memory/requisitos/*/features/**` cai no escopo **VIVO** do gate (não é dir de história — `HISTORY_RE` cobre só `handoffs\|sessions\|sprints\|audits\|research\|reguas`) | **link markdown morto** — nada sobre o contrato da feature |
| `Governance Gate (índice + memory-health + meta-teste)` | 2 steps **sem** `continue-on-error`: `tasks-index-generate --check` e `ledger-check --enforce` | **índice de BACKLOG** gerado × commitado; e **volume de diff** (`>10` arquivos com `startsWith('memory/requisitos/')`) — nenhum dos dois lê plano, aprovação ou task |

O primeiro foi provado com **fixture hermética** (o script aceita `--root`), nos dois sentidos: arquivo em `features/` com link morto → `exit 1` (*"1 arquivo(s) vivos com MAIS links mortos que o baseline permite"*); o mesmo arquivo com link válido → `exit 0`. Ele morde ali, e só ali. E no **mesmo** job umbrella, o único step que olharia a etapa 3 — `plans-index --check` — está explicitamente `continue-on-error: true` ([`governance-gate-umbrella.yml`](../../../.github/workflows/governance-gate-umbrella.yml)). O terceiro candidato transversal, `ADR 0216 PR scan (governance:audit --diff-only --fail-on=block)`, foi verificado e **não** encosta: ele é a máquina **(a)** da tabela acima, cujos 12 DriftCheckers são supply-chain, multi-tenant, ADR-links, charters, rotas, Meilisearch, deploy/MCP e liveness — nenhum de plano/feature/task.

**Isso torna o achado mais forte, não mais fraco.** Não vale o atalho *"required é etapa 7 por definição, afinal required é check de CI"* — ele é **falso**: os 35 são **todos** checks de CI, e **14 deles têm objeto nas etapas 2 e 6** — 7 no requisito (`SPEC`, `Tópico`, `Modulo backend com BRIEFING`, `anchor-lint`, `anchor entry/covers`, `doneness-lint`, `SDD scorecard ratchet`) e 7 no contrato da tela (`Charter`, `Casos-coverage`, `screen-coverage-gate`, `charter status:live`, `Ancora de design nao-shell`, `visual-regression`, `DS gate`). A alocação foi por **OBJETO**, não por ser CI. Logo o miolo descoberto é fato **medido**, e o achado é atacável por contagem: **quem discordar tem o alvo exato — nomear o required que cobra o contrato de uma feature, a aprovação de um plano, ou a execução de uma task.**

> **Recibo:** `35` = **34** contexts clássicos + **1** de ruleset em [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json) — o dono de *"o que bloqueia merge"*; o número derivado é publicado por `system-map.mjs` em [`PAINEL-SISTEMA.md`](../../reference/PAINEL-SISTEMA.md) (*"Bloqueiam merge — 35 required"*). Medido no worktree declarado no cabeçalho desta seção, mas os 5 arquivos usados aqui (`required-checks-baseline.json` · `governance-gate-umbrella.yml` · `deadlink-gate.yml` · `deadlink-gate.mjs` · `Governance/Config/config.php`) são **idênticos a `origin/main`** — `git diff --name-only origin/main -- <os 5>` → vazio. A classificação por etapa é **minha**, feita sobre a lista inteira (35 de 35) e auditável recontando — **não** é campo do baseline. Os `anchor-*` servem as etapas 2 **e** 8, e estão contados uma vez.

### A etapa 4 não pode cobrar — e a causa é uma palavra

`jana:plan-drift` existe ([`Modules/Jana/Console/Commands/PlanDriftCommand.php`](../../../Modules/Jana/Console/Commands/PlanDriftCommand.php)) e tem teste ([`Modules/Jana/Tests/Feature/Mcp/PlanDriftCommandTest.php`](../../../Modules/Jana/Tests/Feature/Mcp/PlanDriftCommandTest.php)) — mas **não tem invocador nenhum no repo**: `grep -n "plan-drift" app/Console/Kernel.php` → vazio (sem schedule); `grep -rn "plan-drift" --include="*.yml" --include="*.sh" --include="*.json"` → vazio (sem workflow, sem cron). Das **22** ocorrências tracked de `plan-drift` (`git grep -c "plan-drift" -- .` somado), o único invocador **não-teste** é a linha 45 da bateria **(b)** acima — a que, por sua vez, não tem invocador. É a máquina (b) da tabela em pessoa: escrita, testada, não chamada. _(Vale o teto declarado acima: isto é varredura de texto do repo; a confirmação de runtime é `php artisan schedule:list` no CT 100.)_

**Confirmado por runtime em 2026-08-04** (o teto acima está fechado): `php artisan schedule:list` no CT 100 (`oimpresso-staging`) lista **76** comandos agendados; `plan-drift` aparece **0×**. Aqui a defasagem do container (checkout 2026-07-23) **não afeta** — o `main` de hoje também tem 0. Contraste que prova o cuidado: `mcp:tasks:unassigned` também dá 0 no container, mas **roda em prod** — a linha de schedule dele entrou em `Kernel.php` em **2026-07-27**, *depois* do checkout. Sem cruzar a data da linha com a data do checkout, o zero "provaria" o oposto do fato.

Mesmo se rodasse, ele cobra *"o plano diz `em-execução` e não há task"*. **Não cobra o inverso** — *"a task existe sem plano aprovado"*.

### ⛔ Errata — o remédio da 1ª redação estava errado

Esta seção prescrevia *"trocar `?? 'todo'` por `?? 'backlog'` em `TaskCrudService:481` — uma palavra"*. **Caiu**, por três razões independentes, cada uma suficiente (refutação adversarial + verificação independente, 2026-08-04):

1. **O ramo é morto.** Os 2 chamadores não-teste nunca chegam nele: [`TasksCreateTool.php:89`](../../../Modules/Jana/Mcp/Tools/TasksCreateTool.php) **não passa `project`** (nem o expõe no schema) → nunca alcança `createAdHoc`; [`BoardController.php:569`](../../../Modules/Forja/Http/Controllers/BoardController.php) passa `'status' => 'todo'` **explícito**. E `createCanonical` **não cria row nenhuma** — escreve no SPEC com `status: todo` **hardcoded**; a row nasce em `TaskParserService:313`. São **4 defaults de nascimento**, e o 481 não é o que vale.
2. **`parent_plan` não é coluna.** Medido: **0 ocorrências** em migrations de `Modules/*/Database` e `database/`. Ele vive em `custom_fields`/label/regex na `description`. Status e plano são **ortogonais** — não existe aresta a criar virando status.
3. **O flip seria no-op e regressão.** No-op na regra 4 (`OPEN` já inclui `backlog`) e **regressão** na regra 2 (`MOVING` o exclui).

O que segue verdadeiro da observação original: `scopeTriage()` já pega `backlog`, `TRANSITIONS['backlog'] = ['todo','cancelled']` já é aprovar-ou-rejeitar, as rotas `aprovar`/`rejeitar`/`fundir` estão em produção, e a migration de 2026-05-04 já previa `backlog` como estado inicial. **O funil existe — o que não existe é o vínculo plano↔task.** Criar `parent_plan` como coluna é o pré-requisito real; trocar o default não é.

_Lição de método registrada: ancorei um diagnóstico num default sem varrer os chamadores e contar. É a lápide §5 2026-07-15 — achado apresentado como conclusão antes da varredura completa._

### Dois eixos que compartilham a palavra "módulo"

| | Quantos |
|---|---|
| `Modules/<X>` (código) | **32** |
| `memory/requisitos/<X>` (requisito) | **79** |
| em ambos | **32** |
| **doc sem código** | **47** (`Atendimento`, `Cliente`, `Estoque`, `Dashboard`, `BI`, `Copiloto`…) |
| código sem doc | **0** |

Os dois eixos são **legítimos** — negócio tem tema que código não tem. O defeito é que ambos usam `<X>` com sentidos diferentes, e por isso as contagens nunca fecham (SCOPE 32 · SUPERFICIE 37 · SPEC 59 · BRIEFING 78). A [ADR 0345](../0345-topicos-vivos-aprendizado-por-critica-revisada.md) decidiu *"unidade = MÓDULO"* mas **não decidiu qual módulo**.

### A fronteira declarada não é comparada com a árvore

`bin/check-scope.php` (via `scope-guard.yml`) verifica *"controller no código → declarado no SCOPE"*. É **unidirecional**: cego para *"declarado no SCOPE → existe no código"*. Provas medidas:

- `BriefController` ficou em `contains` **~7 semanas** depois de deletado, com o guard rodando
- a Jana hospeda **44 tabelas `mcp_*`** declarando **zero** em `db_tables_owned`
- `mcp_audit_log` e `mcp_usage_diaria` não são reivindicadas por **nenhum** dos 32 SCOPE
- `db_tables_owned` **não tem enforcement nenhum** — o guard cobre controller, não tabela

### Colisão de identificador no próprio canon

**370 arquivos de ADR para 355 números** → **15 números duplicados** (`0102`, `0119`, `0126`, `0141`, `0170`, `0178`, `0180`, `0195`, `0216`, `0235`, `0236`, `0246`, `0294`…), com `0170` e `0236` tendo **três** arquivos cada. Existe uma [`0180-drift-numero-adr-0178-conflito-paralelo`](../0180-drift-numero-adr-0178-conflito-paralelo.md) — o projeto **já registrou** esse defeito e ele cresceu desde então.

## Decisão proposta

### 1. A ordem é: aresta primeiro, gate depois

Trocar `?? 'todo'` por `?? 'backlog'` em `TaskCrudService:481`. Não é "mais um gate" — é o que **cria a aresta** que três máquinas já instaladas passam a poder medir, sem alterar uma linha delas.

⚠️ Muda o caminho vivo do time inteiro: toda task passa a nascer aguardando triagem. É por isso que é decisão [W] e não conserto de agente.

### 2. Promoção a required só com mordida provada — a lei da 0336 vale aqui

Nenhuma das etapas 3/4/5 vira `required` nesta proposta. A [ADR 0336](../0336-gates-design-promocao-por-mordida-provada-emenda-0314.md) exige **≥2 mordidas reais**; a infra de coleta (`design-gate-bites.jsonl`) foi medida como **inexistente** em 2026-07-17. Sem trilho, promover é o anti-padrão `foundation-ratchet` (§5 2026-07-01).

### 3. Ligar a superfície do que já detecta (etapa 5)

`mcp:tasks:unassigned` e `mcp:tasks:health-check` já detectam órfã e `review` parado há >5d. Rodam **sem** `--auto-comment` — o sinal morre no log. Ligar a superfície é subtração de ruído, não gate novo.

### 4. Emenda à 0345 — declarar QUAL módulo

Cada artefato declara seu eixo:

- **eixo CÓDIGO**, unidade `Modules/<X>` (32, fechado): `SCOPE.md` + `SUPERFICIE.md`
- **eixo REQUISITO**, unidade área de negócio (79, aberto): `SPEC.md` + `BRIEFING.md`

Isto **não move arquivo** — declara o eixo. Mover a `SUPERFICIE.md` para junto do código é decisão separada (37 arquivos + o gerador).

### 5. O lado que falta do `check-scope` — medir FP antes de armar

O reverso (`declarado → existe`) é a mesma varredura ao contrário e mataria o fantasma. **Não armar antes de medir o falso-positivo** nos 32 módulos: `drift_alerts[]` existe justamente para abrigar exceção conhecida. Estender o dono (`check-scope.php`/`module-surface`), **nunca gate novo** — o §5 tem 4 lápides de guard sintático que reprovava o legítimo.

## O que esta proposta NÃO supersede — e por quê

`supersedes: []`. [W] pediu *"revogue os anteriores"*; medi o custo e trago a evidência contra:

| ADR | Arquivos de máquina que citam | Revogar custaria |
|---|---|---|
| 0275 · scorecard SDD | **65** | órfãos em massa |
| 0314 · lei do required | **65** | órfãos em massa |
| 0264 · trio-de-tela | **25** | os 4 required da etapa 6 |
| 0336 · mordida provada | **18** | o critério de promoção |
| 0294 · dual-track/planos | **15** | `plan-health` + `plans-index` |
| 0070 · Jira-style tasks | **11** | o modelo de task inteiro |
| 0273 · âncora | **10** | os required da etapa 2 e 8 |
| 0345 · taxonomia | **3** | — (esta proposta a **emenda**) |

**Nenhuma medição desta sessão mostrou que alguma delas está errada.** O miolo sem `required` é a lei da 0314 **funcionando como escrita**, não um defeito. O `?? 'todo'` é defeito de código. A 0345 tem um vão (não disse qual módulo), que o item 4 fecha por emenda — o caminho que ela mesma prescreve.

Revogar canon correto para reescrevê-lo é o anti-padrão que a §5 chama de *roadmap paralelo* (2026-06-05). Se [W] quiser revogação mesmo assim, ela precisa nomear **quais** ADRs e aceitar o custo desta tabela — e isso é ato dele, não interpretação de agente.

## Consequências

**Positivo:** a pergunta *"quem cobra?"* passa a ter resposta por etapa. O item 1 destrava três máquinas já instaladas sem escrever máquina nova. O item 4 mata a ambiguidade que faz as contagens não fecharem.

**Negativo, declarado:** o item 1 muda o fluxo de todo o time — toda task nasce aguardando triagem, e com **519 US sem dono** hoje, a fila de triagem fica visivelmente cheia no dia 1. Isso é o problema aparecendo, não sendo criado; mas vai doer e o custo é de [W].

**Não muda:** nenhum gate vira required. Nenhum arquivo é movido. Nenhuma ADR é revogada.

## Gate de reversão

- Se, 30 dias após o item 1, a fila de triagem **não** for consumida (mediana de idade da órfã subindo), o portão virou burocracia sem dono → reverter o default e registrar por quê.
- Se o item 3 (`--auto-comment`) gerar comentário repetido na mesma task, é não-idempotente → desligar e consertar antes de religar.
- Se o item 5 medir FP >5% nos 32 módulos, o lado reverso do `check-scope` **não** é armável como está → fica advisory permanente com a medição registrada.

## O que é decisão [W] (nenhuma tomada aqui)

1. Virar `?? 'todo'` → `?? 'backlog'` (muda caminho vivo do time)
2. Ligar `--auto-comment` nos dois crons
3. Ratificar a emenda de eixo (item 4) — e, separadamente, se a `SUPERFICIE.md` muda de casa
4. Autorizar a medição de FP do item 5
5. Revogar alguma ADR — **e quais**, contra a tabela de custo acima
6. Os **15 números de ADR duplicados**: reconciliar, ou aceitar e registrar

## Rollback

Proposta em `proposals/`. Se [W] recusar, `status: rejected` (append-only, não deleta). Se ratificada e algum item der ruim, cada um reverte isolado — eles não dependem entre si, exceto que o item 1 é pré-requisito de qualquer cobrança futura da etapa 4.
