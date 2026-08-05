---
date: "2026-08-05"
time: "0746 BRT"
slug: "ancora-medivel-funil-e-o-teste-que-nao-provava"
tldr: "Eixo temporal da âncora saiu de 67,7% cego para 5,9% (base derivada do git, sem re-carimbar nada). O funil de admissão da ADR 0368 virou máquina: estado próprio, FSM alcançável, recusa exige motivo. E três medições independentes expuseram falso-verde por NÃO-EXECUÇÃO — inclusive a minha própria bateria de fluxo, que passava verde com os gates desligados até um adversário derrubar 11 de 12 asserts."
decided_by: [W]
cycle: null
prs: [5279, 5281, 5282, 5283, 5284, 5287, 5288, 5289, 5291]
us: ["US-GOV-058"]
next_steps:
  - "[W] decide o critério de entrada dos 553 gaps de dimensão → feature (sem filtro = 1.659 arquivos)"
  - "[W] decide o escopo do /documentacao: charter + casos + scorecards (326 arquivos) entram ou não"
  - "81 UCs sem lane: dívida arquivo-a-arquivo, mas 80 exigem MySQL — ancorar na lane errada NÃO resolve"
  - "test-lane-coverage.mjs subconta órfãos: só enxerga pest, não Playwright (o dono do tema medindo menos que deveria)"
  - "Classe 'sonda que não discrimina' só é pega DENTRO do fluxo-jornada; mutation-gate é PHP-only e não cobre os 76 .test.mjs"
related_adrs: ["0368-funil-admissao-feature-pesquisa-propoe-w-admite", "0273-anchor-spec-codigo-formato-canonico-fluxo-novo", "0303-anchor-lint-wired-testado-sa-a2-bis", "0306-strangler-spec-anchored-reconstrucao-sdd", "0264-governanca-executavel-trio-dominio-e2e", "0105-cliente-como-sinal-guiar-sem-mandar", "0089-capterra-driven-module-evolution"]
---

# Handoff 2026-08-05 07:46 BRT — âncora medível, funil com dono, e o teste que não provava nada

## TL;DR

O tema da sessão foi um só, em cinco formas: **afirmar sem medir**. A âncora afirmava frescor sobre um sha que o squash-merge apagava; a ADR 0368 afirmava que a recusa exige motivo sem nenhuma máquina cobrando; três medições independentes acharam testes "verdes" que nunca rodaram; e a bateria que eu criei justamente para caçar isso **passava verde com os gates desligados**.

Nada aqui está pendente de merge — os 9 PRs entraram. O que sobra é decisão de [W] (2 itens) e dívida medida sem dono (2 itens).

## O eixo temporal da âncora (US-GOV-058)

| | antes | depois |
|---|---|---|
| não-medível | **296 de 437 (67,7%)** | **26 (5,9%)** |
| stale / fresco | 42 / 99 | 156 / 255 |

**Causa** (medida, não inferida): `git log -50 --format=%p origin/main` → **50 de 50 com 1 parent**. O squash descarta o commit da branch, e o `verificado@<sha7>` aponta um estado que nunca esteve na história do `main`. 270 âncoras caíam nisso, sobre 16 SHAs.

**Saída — o que o squash NÃO come:** o commit em que a *linha* entrou no `main` é ancestral por construção. A base passa a ser ele (`git log -S"verificado@<sha>" -- <spec>`, 1ª ocorrência ancestral). **270 de 270 recuperadas, 0 falhas.**

Controle de fidelidade: nas 141 já medíveis, os dois caminhos concordam em **139 (98,6%)**; as 2 divergências a base derivada acerta *melhor* (o "movimento" que o sha declarado chamava de stale era o próprio PR que fez a verificação).

**Duas premissas do enunciado morreram na medição:**
1. *"ligar o `--stamp` do `ancora-codigo-sync`"* — corpus **disjunto**: 307 docs, 70 refs, **0 em `SPEC.md`**, e o formato que ele escreve não casa a gramática da âncora. Moveria 0 das 437.
2. *"falta decidir a receita"* — a receita **já é lei** (ADR 0273 §1: *"sha do commit de `origin/main`"*). Conformidade real: **jun 0%, jul 39%**. Lei em prosa, em 5 sites, e só um dá o comando.

⚠️ **`sha_ausente` só apareceu na fonte certa:** local dá `270/0`; o run real do `main` em 03/08 dá **`266/4`** (em CI a branch já foi deletada). Cobrir só um motivo nasceria cego onde o job roda.

## O funil de admissão (ADR 0368) — de prosa a máquina

A ADR nasceu desta sessão e fechou o modelo que [W] descreveu. Três obstáculos que ela teve de resolver, todos medidos:

1. **ADR 0105 barrava feature de pesquisa** (*"hipótese sem sinal não entra"*) → emenda: a admissão de [W] vira o 5º critério, com as três peças (verificar+pesquisar+plano) como pré-condição.
2. **"aprovado" já estava ocupado** — no `CAPTERRA-INVENTARIO`, `✅ APROVADO` = *"existe no sistema"*, não *"[W] aprovou"* → vocabulário `admitida`/`recusada` separado, forward-only nos 11 inventários.
3. **Não existia estado de espera** → `pending_approval` no enum, distinto de `blocked` (que é trava técnica).

**E o estado nasceu quebrado — defeito meu, achado por agente:** eu adicionei `pending_approval` ao enum, ao health-check e ao `PlanDrift::OPEN`, mas **não à `McpTask::TRANSITIONS`**. Com `canTransition()` fail-closed, o estado ficou **inalcançável e inescapável** pelo chokepoint. Não apareceu no caminho feliz porque `createAdHoc` seta status direto, sem FSM.

Corrigido no #5288, com prova contrafactual no CT 100: código novo = **14 passed (35 assertions)**; código do `main` + os mesmos testes = **10 failed, 5 passed** — e entre os 5 que passam nos dois lados está o controle negativo `todo→cancelled NÃO exige motivo`, que prova que a trava é da *recusa de candidata*, não de todo cancelamento.

O motivo da recusa coube em `custom_fields['motivo_recusa']`, **sem migration**. Sutileza que só aparece lendo o código: o update faz *assignment*, não *merge* — a recusa poderia se apoiar num motivo que ela mesma apaga. Tem caso cobrindo.

## Falso-verde por NÃO-EXECUÇÃO — três medições independentes

| onde | número |
|---|---|
| `McpTasksHealthCheckCommandTest` | **9 testes** "passando" há meses **sem nunca terem rodado** (pula em MySQL por `dropIfExists`; não estava na allowlist sqlite) |
| UC citados só em docblock | **104** — nunca viram `✅` como estão |
| UC com id no título mas **lane nenhuma executa** | **81** (`uc-sem-lane`, 0 FP) |

O agente do `uc-sem-lane` **corrigiu o próprio número de 93 → 81** e a razão vale mais que o número: o extrator só entendia `vendor/bin/pest`, e o Playwright não passa alvo (quem seleciona é o `testDir` da config) — 12 UCs de `e2e/*.spec.ts` apareciam órfãos *com a pasta inteira rodando*. O verificador "independente" dele tinha o **mesmo ponto cego**: dois caminhos com o mesmo viés não são duas provas.

**O achado estrutural é pior que o número:** **80 dos 81 têm `markTestSkipped` condicional** (exigem MySQL) — ancorar na lane errada não resolve. E **`OficinaAuto` (28 UCs, maior cluster) não está na matrix do `modules-pest` nem tem lane própria**: não existe lane que *pudesse* rodá-los.

## A bateria de fluxo — e por que ela mesma mentia

Criada para responder o que nenhuma bateria respondia (*"um item atravessa o fluxo, e pular um elo é detectado?"* — busca no repo deu zero). Revisão adversarial **derrubou 11 dos 12 asserts**:

- **12/12 verde com o `casos-gate` cego.** A sonda era `/viola[çc][õo]es/i` sobre o stdout — e o guard imprime a palavra **sempre**, inclusive em `0 violações`.
- **3 das 5 variações passavam com o elo PRESENTE** (`mut: {}` e seguiam `[OK]`).
- **A4 era `ok(..., true)` literal** — o único que o adversário não derrubou, porque não podia reprovar.

**Conserto estrutural:** cada variação roda **duas vezes** — íntegra (a sonda TEM que silenciar) e mutada. Sonda viciada reprova na hora. Sondas passam a ler campo estruturado (`req_sem_aceite`, `dead[].us`, `stats.missing_casos`, `stats.orphan_ucs`), nunca texto; sonda sem resposta é **falha**, não "detectou". **12 → 24 asserts.**

O desenho novo pegou **mais um vício meu na 1ª execução**: a sonda de "UC sem teste" olhava `exec_backed_ucs < ucs_declared` e acusava na jornada íntegra — `exec_backed` depende do manifesto, inexistente na fixture (`0 < 1` sempre).

## PRs (todos MERGEADOS)

| PR | Conteúdo |
|---|---|
| [#5279](https://github.com/wagnerra23/oimpresso.com/pull/5279) | eixo temporal da âncora: base derivada do git (67,7% → 5,9% cego) |
| [#5281](https://github.com/wagnerra23/oimpresso.com/pull/5281) | UC-id no título do `it()` — 12 UCs saem do teto morto (chip) |
| [#5282](https://github.com/wagnerra23/oimpresso.com/pull/5282) | **ADR 0368** — funil de admissão (a política) |
| [#5283](https://github.com/wagnerra23/oimpresso.com/pull/5283) | estado `pending_approval`, distinto de `blocked` |
| [#5284](https://github.com/wagnerra23/oimpresso.com/pull/5284) | trio de feature entra no acervo da `/documentacao` (chip) |
| [#5287](https://github.com/wagnerra23/oimpresso.com/pull/5287) | bateria do fluxo (v1 — a que mentia) |
| [#5288](https://github.com/wagnerra23/oimpresso.com/pull/5288) | funil alcança `pending_approval` + recusa exige motivo |
| [#5289](https://github.com/wagnerra23/oimpresso.com/pull/5289) | `uc-sem-lane` — 81 UCs que lane nenhuma executa |
| [#5291](https://github.com/wagnerra23/oimpresso.com/pull/5291) | conserto da bateria: controle negativo por variação (12 → 24) |

## O que ficou aberto

**Decisão de [W]** (sem default seguro):
1. **Critério de entrada dos 553 gaps** de dimensão dos scorecards → feature. Sem filtro: 553 × 3 = **1.659 arquivos**. Sugestão registrada: `peso_real` da tela + piso de dimensão.
2. **Escopo do `/documentacao`**: o trio de feature entrou; **charter (209) + casos (85) + scorecards (195)** seguem fora dos globs do indexador.

**Dívida medida, sem dono:**
3. Os **81 UCs sem lane** — arquivo-a-arquivo, com run no CT 100 antes de ancorar (ligar lane revela dívida pré-existente, foi o que aconteceu com os 9 do health-check).
4. **`test-lane-coverage.mjs` subconta órfãos** (só pest, não Playwright) — o dono do tema medindo menos que deveria.

**Limite honesto do que foi construído:** a classe *"sonda que não discrimina"* só é pega **dentro** do `fluxo-jornada` (provado: injetei a sonda viciada e ela reprova, exit 1). Fora dele, ninguém pega — o `mutation-gate` é a máquina canônica para "teste que não testa" e é **PHP-only** (`app/Services/**`), enquanto existem **76 `.test.mjs`** em `scripts/governance/`. Estender o `mutation-gate` para Node é candidato real, mas exige medir custo e FP antes.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → **12 tasks**, todas em `REVIEW` (1 p0: `US-COPI-123` mock no `/ia/dashboard`; 7 p1; 4 p2)
- Último handoff anterior: `2026-08-04-1730-ciclo-maquinas-templates-verificacao.md`
- `main` no fechamento: `017eac4d925` — os 9 PRs desta sessão confirmados um a um (`git log origin/main | grep "(#NNNN)"` = 1 para cada)
- Verificação pós-merge no `main`: `fluxo-jornada.test.mjs` → **24 OK · 0 FAIL · exit 0**; `uc-sem-lane.mjs` roda e reporta (23 lanes, 288 alvos, 435 UCs)

## Como retomar

Ler primeiro o TL;DR e os 2 itens de decisão. Se o assunto for **âncora**, o eixo temporal agora é confiável — `anchor-lint --stale` reporta o número real. Se for **funil**, a ADR 0368 é a lei e o #5288 é a máquina. Se for **teste que não prova**, o `fluxo-jornada` é o padrão a imitar (controle negativo por variação) — e o buraco conhecido é que ele não se aplica fora do próprio arquivo.
