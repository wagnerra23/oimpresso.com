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

## Contexto — as medições (2026-08-04, `origin/main` fresco)

### O ciclo tem 8 etapas e nenhuma falta

`pedido` → `requisito` → `contrato da feature` → `aprovação` → `execução` → `contrato da tela` → `gates` → `âncora e produção`, com a âncora reabastecendo o requisito.

### A força de cobrança é bimodal

| Etapa | Máquina | Força |
|---|---|---|
| 1 · pedido | — | não enforçável (entrada humana) |
| 2 · requisito | `SPEC (schema)` · `anchor-lint` · `anchor entry/covers` | **required ×3** |
| **3 · contrato da feature** | `feature-lint` · `plans-index --check` | **advisory** |
| **4 · aprovação** | `plan-health` · `jana:plan-drift` | **advisory** |
| **5 · execução** | `mcp:tasks:unassigned` (06:45) · `mcp:tasks:health-check` (06:20) | **advisory, superfície desligada** |
| 6 · contrato da tela | `casos-gate` · `screen-coverage-gate` · `Charter` · `charter status:live` | **required ×4** |
| 7 · gates | `Governance Gate` · `gate selftest` | **required ×2** |
| 8 · âncora | `anchor-lint` · `anchor entry/covers` · `âncora stale` | **required** |

**As pontas mordem; o miolo não.** As etapas 3, 4 e 5 — onde o trabalho acontece — somam **zero** checks required. Não por ausência de máquina: o `governance:audit --all --notify` roda **diariamente** e executa **14 máquinas**, incluindo `plan-health` e `jana:plan-drift`, todas com `kind: 'advisory'` — que o próprio arquivo define como *"só reporta"*.

### A etapa 4 não pode cobrar — e a causa é uma palavra

`jana:plan-drift` existe ([`Modules/Jana/Console/Commands/PlanDriftCommand.php`](../../../Modules/Jana/Console/Commands/PlanDriftCommand.php)), tem teste, roda diário. Ele cobra *"o plano diz `em-execução` e não há task"*. **Não cobra o inverso** — *"a task existe sem plano aprovado"* — e não é falha dele: **não há aresta para medir**.

`TaskCrudService.php:481` faz `'status' => $data['status'] ?? 'todo'`. A task nasce **afirmando que foi aprovada**, sem `parent_plan` obrigatório. O funil existe inteiro e é atravessado em silêncio:

- `McpTask::scopeTriage()` já pega `status = 'backlog'`
- `McpTask::TRANSITIONS['backlog'] = ['todo', 'cancelled']` já é literalmente aprovar-ou-rejeitar
- as rotas `aprovar` / `rejeitar` / `fundir` estão em produção
- a migration de 2026-05-04 já dizia *"adiciona `backlog` como state inicial"*

O default nunca foi virado.

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
