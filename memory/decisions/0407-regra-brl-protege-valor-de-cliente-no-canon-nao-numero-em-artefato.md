---
slug: 0407-regra-brl-protege-valor-de-cliente-no-canon-nao-numero-em-artefato
number: 407
title: "A regra BRL protege valor de CLIENTE no canon — não todo número com R$ no repo"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-18"
module: governance
tags: [lgpd, valores, brl, governanca, gate, escopo]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0224-hooks-block-vs-advisory-claude-4.8-aware
  - 0314-required-so-tier-0
pii: false
---

# ADR 0407 — a regra BRL protege valor de CLIENTE no canon, não todo número com R$

## Contexto

A regra nasceu em **2026-06-08**, palavras de [W]: *"maiara felipe e luiz não podem ter acesso
a nada de valores no git. pode saber o que migrou mas não saber o conteúdo (valores)"*. O eixo
é **controle de acesso do time**: quem lê o git vê escopo, contagem e estrutura, não o dinheiro
do cliente. A reincidência que a motivou custou `git filter-repo` em **5.033 commits** mais uma
janela de force-push em `main` (registrado em [`proibicoes.md`](../proibicoes.md)).

O texto da regra delimita **`memory/`, `*.md` canon, PR body e commit message**. A máquina
(`brl-scan-diff.mjs`) varre as **linhas novas do PR no repo inteiro**, menos uma pasta isenta
(`prototipo-ui/`). Ou seja: a máquina responde *"onde a linha está"*; a regra fala de *"o que o
número é e quem pode lê-lo"*. São perguntas diferentes, e a distância entre elas aparece toda
vez que um artefato de medição entra no repo.

**O caso que forçou a decisão** ([PR #7503](https://github.com/wagnerra23/oimpresso.com/pull/7503)):
as 77 telas medidas contra o staging trouxeram valores em 97 linhas novas — 77 no `design.json`
(mockup do protótipo, com SKUs fictícios) e 20 no `prod.json` (clone **anonimizado**). No mesmo
PR, `PII scan` e `gitleaks` passaram. [W] 2026-09-18, textual: *"não é só aí que devem ser as
exceções, a regra deve ser reinterpretada no sistema inteiro"*.

## Medição (arquivos versionados com o padrão monetário)

| classe | arquivos | o que o número é |
|---|---|---|
| `governance/design/targets` | **73** (73 de 73 da pasta `governance/`) | medição de tela — ilustração de layout |
| `prototipo-ui/` | 70 | mockup de design — **já isento** |
| `memory/` | 40 | **canon — é aqui que a regra morde** |
| código de produto (`Modules`, `resources`, `app`) | 33 | string de UI e rótulo de validação |
| testes/fixtures | 21 | vetor de asserção verificável |

Os 40 de `memory/` se espalham por `requisitos` (14), `handoffs` (12), `sessions` (6),
`reference` (4), `governance` (2) e dois arquivos soltos.

Amostra do que são os hits fora do canon — descrita **sem os dígitos**, e a razão disso é ela
mesma um achado (ver o aviso adiante):

```
Modules/Financeiro/.../UnificadoController.php   mensagem de validação: "valor abaixo do mínimo de <moeda>"
tests/Feature/Calculo/CalculoPaymentGatewayTest  chave de data provider: "<moeda> (mínimo)"
Modules/Financeiro/Tests/.../ImpostosGuardTest   comentário do vetor: "6% de <moeda> = <moeda>"
```

Rótulo de validação e vetor de teste. Nenhum é saldo de cliente.

> ⚠️ **Achado colateral, ao escrever esta ADR.** O **hook** `block-brl-values-in-memory` isenta
> exemplo didático dentro de bloco de código — a mensagem dele ensina isso textualmente. O
> **scanner de CI** `brl-scan-diff` **não tem essa regra**: ele acusou as três linhas acima
> mesmo cercadas por crases. As duas máquinas que aplicam a mesma regra divergem no predicado,
> e quem seguir a instrução do hook escreve um arquivo que o CI reprova. Medido nesta sessão:
> o hook liberou o `Write`, o CI acusou o mesmo texto. Não conserto aqui — é intent separado —
> mas fica registrado para não ser redescoberto como novidade.

## Decisão

**A regra tem um eixo, e ele não é o path: é _valor de cliente na camada de canon e
comunicação_.** Vale integralmente em **`memory/`, PR body e commit message** — os três canais
por onde o time lê o estado do negócio.

**Fora desse eixo, por construção, estão os artefatos cujo número é ilustração ou vetor:**

1. **Design** — `prototipo-ui/` (já isento desde a decisão anterior de [W], com o raciocínio
   escrito no próprio scanner) e **`governance/design/`**, que é a medição daquele design.
   Medido: 73 de 73 arquivos com o padrão na pasta `governance/` estão sob `design/targets`.
2. **Vetor de asserção** — fixture e teste. O `.github/brl-scan-allowlist.txt` já nomeia esse
   caso, e registra o precedente doloroso: uma passada de redação **corrompeu assertions de
   Pest** (handoff 2026-06-13).
3. **String de UI no código de produto** — rótulo de mínimo é texto de interface, não saldo.

## O que esta ADR NÃO faz

**Não desliga o gate no código e nos testes.** O eixo diz que eles estão fora, mas é justamente
ali que um valor real de cliente pode aterrissar sem parecer (um seed com saldo verdadeiro, um
teste escrito a partir de um caso de produção). Para esses dois, a isenção continua sendo
**por substring, caso a caso**, no allowlist — que existe e foi desenhado assim de propósito,
para que isentar um arquivo inteiro não esconda um vazamento novo no meio de fixtures antigas.

A isenção **de pasta** vale só para as duas classes de design, onde a medição é 100% e a
natureza do artefato é homogênea.

## Consequências

- `governance/design/` entra em `PASTAS_ISENTAS` no `brl-scan-diff.mjs`. Medido no diff real do
  PR #7503 com a mudança aplicada: **25.830 de 25.946 linhas isentas, 116 varridas**. Toda
  rodada futura do `design-diff-lote` deixa de avermelhar o BRL por construção — o que hoje
  aconteceria sempre, porque medir tela de ERP captura dinheiro.
- `memory/`, PR body e commit message seguem varridos e mordendo, sem mudança. Os controles
  disso estão no `--selftest` do scanner (30/30), incluindo um caso que prova que o resto de
  `governance/` **não** ficou isento e outro que prova que `Modules/` segue varrido.
- O texto da regra em `proibicoes.md` ganha emenda **datada e aditiva**, apontando para cá. O
  fato de 2026-06-08 fica preservado como era; o que muda é o escopo daqui pra frente.

## Residual honesto

O mesmo que a isenção do `prototipo-ui/` já declarou, agora estendido: **protótipo e medição às
vezes nascem com número copiado de produção para parecer real.** A partir daqui o gate não pega
esse caso dentro de `governance/design/`. Quem levar dado de cliente para lá está fora do
alcance da defesa — é decisão consciente de [W], registrada aqui para não virar surpresa.

E o limite de honestidade desta ADR: ela reinterpreta o **escopo**, não cria capacidade de
distinguir *valor de cliente* de *número ilustrativo* dentro de um mesmo arquivo. Essa distinção
é semântica ([ADR 0224](0224-hooks-block-vs-advisory-claude-4.8-aware.md)) e nenhum regex a
decide — motivo pelo qual as duas classes que sobram ficam no allowlist por substring, e não
numa pasta isenta.
