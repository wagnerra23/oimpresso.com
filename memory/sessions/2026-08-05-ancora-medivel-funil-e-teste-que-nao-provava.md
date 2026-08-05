---
date: "2026-08-05"
hour: "07:46 BRT"
duration: "5h"
topic: "Âncora medível por base derivada, funil de admissão virando máquina, e a bateria que passava verde com os gates desligados"
authors: [C]
prs: [5279, 5281, 5282, 5283, 5284, 5287, 5288, 5289, 5291]
us: ["US-GOV-058"]
outcomes:
  - "O eixo temporal da âncora saiu de 67,7% não-medível para 5,9% — derivando a base do git, sem re-carimbar documento nenhum."
  - "O funil de admissão da ADR 0368 deixou de ser prosa: estado próprio no enum, FSM que o alcança, e recusa que exige motivo com prova contrafactual."
  - "Três medições independentes expuseram falso-verde por não-execução (9 testes, 104 UCs, 81 UCs) — e a bateria criada pra caçar isso tinha o mesmo defeito."
related_adrs: ["0368-funil-admissao-feature-pesquisa-propoe-w-admite", "0273-anchor-spec-codigo-formato-canonico-fluxo-novo", "0303-anchor-lint-wired-testado-sa-a2-bis", "0306-strangler-spec-anchored-reconstrucao-sdd", "0264-governanca-executavel-trio-dominio-e2e", "0105-cliente-como-sinal-guiar-sem-mandar"]
---

# Sessão 2026-08-05 — âncora medível, funil com dono, e o teste que não provava nada

## O fio condutor

Cinco formas do mesmo defeito: **afirmar sem medir**. A âncora afirmava frescor sobre um sha que o squash apagava. A ADR 0368 afirmava que a recusa exige motivo, sem máquina cobrando. Três medições acharam testes "verdes" que nunca rodaram. E a bateria que eu escrevi para caçar exatamente isso passava verde com os gates desligados.

## 1. O eixo temporal da âncora (US-GOV-058)

O pedido trazia três saídas aparentes. Duas morreram na medição, e isso foi o mais útil do começo da sessão:

- **"ligar o `--stamp`"** → corpus **disjunto**. `ancora-codigo-sync --measure`: 307 docs, 70 refs, **0 em `SPEC.md`**; e o formato que ele escreve (`arquivo:linha (verificado@sha)`) não casa a `GRAMMAR_RE` da âncora. Moveria **0 das 437**.
- **"falta decidir a receita"** → a receita **já é lei** desde o ADR 0273 §1 (*"sha curto do commit de `origin/main`"*). O que falha é execução: conformidade **0% em junho, 39% em julho**.

A saída real veio de uma observação simples: **o squash come o commit da branch, mas não come o commit em que a linha entrou no `main`** — e esse é ancestral por construção. Base derivada por pickaxe (`git log -S"verificado@<sha>" -- <spec>`).

| | antes | depois |
|---|---|---|
| não-medível | 296 / 437 (**67,7%**) | 26 (**5,9%**) |
| stale / fresco | 42 / 99 | 156 / 255 |

Controle de fidelidade: **139 de 141 concordam (98,6%)**; as 2 divergências a base derivada acerta melhor — o "movimento" que o sha declarado chamava de stale era o próprio PR que fez a verificação.

**O detalhe que quase passou:** o motivo `sha_ausente` não aparece localmente (270/0), mas o run real do `main` mostra **266/4** — em CI a branch já foi deletada. Cobrir só um motivo nasceria cego no único lugar onde o job roda.

## 2. O funil de admissão (ADR 0368)

[W] descreveu o modelo: pesquisa de mercado propõe features, ele admite ou recusa com motivo, e antes de entrar tem que verificar + pesquisar + planejar. Medindo o repo, **o funil já existia em partes que não se conheciam** — a ADR 0089 §7–8 descreve exatamente isso e roda (11 inventários). Faltava o **estado** entre a pergunta e a task: a decisão morria no chat.

Três obstáculos, todos medidos: a ADR 0105 barrava feature de pesquisa (*"hipótese sem sinal"*); a palavra "aprovado" já significava *"existe no sistema"* no mesmo documento; e o enum não tinha estado de espera.

**E o estado que eu criei nasceu quebrado.** Adicionei `pending_approval` ao enum, ao health-check e ao `PlanDrift::OPEN` — e **não à `McpTask::TRANSITIONS`**. Com `canTransition()` fail-closed, ficou **inalcançável e inescapável**. Não apareceu no caminho feliz porque `createAdHoc` seta status direto, sem FSM. Achado por agente, corrigido com prova contrafactual no CT 100: **14 passed** com o código novo × **10 failed** com o do `main`.

## 3. Falso-verde por não-execução — três medições

| onde | número |
|---|---|
| `McpTasksHealthCheckCommandTest` | **9 testes** verdes há meses sem nunca rodar |
| UC só em docblock | **104** |
| UC no título, lane nenhuma executa | **81** |

O agente do `uc-sem-lane` **corrigiu o próprio número (93 → 81)**: o extrator só entendia `vendor/bin/pest`, e o Playwright não passa alvo — quem seleciona é o `testDir` da config. O verificador dele tinha o **mesmo ponto cego**; dois caminhos com o mesmo viés não são duas provas.

Achado estrutural: **80 dos 81 exigem MySQL** (skip condicional) — ancorar na lane errada não resolve. E **`OficinaAuto` (28 UCs) não tem lane que pudesse rodá-los**.

## 4. A bateria de fluxo — e o adversário

Construí a bateria que faltava (*"um item atravessa o fluxo, e pular um elo é detectado?"*). [W] pediu um adversário. Ele **derrubou 11 de 12 asserts**:

- 12/12 **verde com o `casos-gate` cego** — a sonda era regex `/violações/i` e o guard imprime a palavra até em `0 violações`;
- **3 das 5** variações passavam com o elo presente;
- o único que ele não derrubou era `ok(..., true)` literal — não podia reprovar.

O conserto é estrutural: **cada variação roda duas vezes** — íntegra (sonda tem que silenciar) e mutada. Sondas leem campo estruturado, nunca texto. Sonda sem resposta é falha, não "detectou". **12 → 24 asserts**, e o desenho novo pegou mais um vício meu na 1ª execução (`exec_backed` acusava na íntegra porque depende de manifesto inexistente na fixture).

## Erros meus, registrados

1. **`pending_approval` fora da `TRANSITIONS`** — criei o estado e não o liguei à FSM.
2. **A bateria mentia** — e o cabeçalho dela afirmava ter evitado a tautologia que ela cometia um nível abaixo.
3. **Dois erros de medição em miniatura**: li `exit=0` que era do `tail`, não do node; e tratei `telas: 1 · casos.md: 1` (estatística) como contagem de violação, reprovando uma jornada íntegra.
4. **Matei um job de medição** ao limpar o worktree com ele ainda rodando — a medição secundária de "quantas âncoras nascem stale" se perdeu e não foi refeita.

## Limite honesto do que ficou

A classe *"sonda que não discrimina"* só é pega **dentro** do `fluxo-jornada` — provado injetando a sonda viciada (exit 1). Fora dele ninguém pega: o `mutation-gate`, máquina canônica de "teste que não testa", é **PHP-only** (`app/Services/**`), enquanto existem **76 `.test.mjs`** em `scripts/governance/`. Estender para Node é candidato real — com custo e FP medidos antes, não por impulso.
