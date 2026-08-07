---
date: "2026-08-05"
hour: "18:35 BRT"
duration: "1.5h"
topic: "Um prompt de duas palavras e dois números virou a 2ª leva dos hooks mudos — e o pré-requisito honesto era medir se a sonda sequer alcança os eventos onde eles vivem"
authors: [C, W]
prs: [5323]
us: []
outcomes:
  - "Não-observáveis 23 → 8: os 15 hooks CONDICIONAIS ganharam tag (13 por prefixo, 2 por ALIAS retroativo). Os 7 banners de SessionStart ficam de fora por argumento próprio, e o diag-pretooluse-trace por não emitir mensagem alguma."
  - "MEDIDO antes de editar, e mudou o escopo de 16 pra 15: a sonda ALCANÇA SessionStart/Stop/UserPromptSubmit (o attachment hook_success carrega a saída em `\"content\"`), stderr é canal contável (block-destructive, stderr puro, tem 279 entregas), e NÃO existe atalho — `hookName` no transcript é `<Evento>:<matcher>` em 6.413 attachments, nunca o nome do script."
  - "`--check-aliases` era PROMETIDO no cabeçalho dos ALIASES e não existia — só o comentário. Implementado, e no primeiro uso achou um alias real quebrado (`mcp-first-nudge`, arquivo inexistente desde o #4587)."
related_adrs: ["0256-knowledge-survival-meia-vida-catraca-sentinela", "0344-two-strikes-cobre-processo", "0130-handoff-append-only-mcp-first"]
---

# 2026-08-05 · a 2ª leva dos hooks mudos (os condicionais)

Continuação direta do [handoff das 14:38](../handoffs/2026-08-05-1438-promocoes-required-e-hooks-observaveis.md),
cujo `next_steps` dizia textualmente: *"23 hooks advisory seguem não-observáveis —
forward-only, cada um ganha tag quando for tocado"*. Esta sessão tocou 15 deles de uma vez,
com um critério que não é "tocar tudo".

## Como o pedido chegou

O prompt foi literalmente **`hooks não-observáveis	34	23`** — duas palavras e dois números
separados por TAB, colados de uma grade. Não havia frase, verbo nem pergunta.

Localizar o referente foi metade do trabalho, e valeu o custo: os números são a linha
`MEDIDO` do [#5314](https://github.com/wagnerra23/oimpresso.com/pull/5314), mergeado poucas
horas antes — *"não-observáveis 34 → 23"*. O corpo daquele commit registra a decisão [W]:
cortar os **11 com sinal de bloqueio** e deixar **os 23 advisory forward-only**.

Ou seja: o pedido tocava num corte deliberado do mesmo dia. Agir direto teria contrariado
uma decisão registrada; perguntar sem dados teria devolvido o trabalho ao [W]. O caminho foi
**medir primeiro, perguntar depois com o custo na mão** — e a pergunta ofereceu 4 opções com
recomendação cravada. [W] escolheu *"os 16 condicionais"*.

## O que a medição mudou — e ela mudou o escopo

Três medições precederam a primeira edição. Duas viraram justificativa; a terceira **cortou
um item do escopo**.

| Pergunta | Como foi medido | Resultado |
|---|---|---|
| A sonda alcança SessionStart/Stop/UserPromptSubmit? | inspeção do JSONL cru num transcript real | **Sim** — o attachment `hook_success` carrega a saída em `"content":"…"`, que é a 3ª sonda. Nenhum dos 11 do #5314 era desses eventos, então isso estava **não-verificado** |
| Existe atalho que dispense a tag? | varredura dos 375 transcripts | **Não** — 6.413 attachments com `hookName`, sempre `<Evento>:<matcher>` (14 valores), **nunca** o nome do script |
| stderr chega ao transcript? | cruzamento canal × entregas dos 26 observáveis | **Sim** — `block-destructive` (stderr puro) tem **279**; `block-mwart-violation` 53; `block-automem` 16 |

A terceira mediu **`diag-pretooluse-trace`** e o tirou do escopo: ele **não emite mensagem
alguma** — só faz `appendFileSync` num log de arquivo e sai 0. O próprio cabeçalho o declara
*"INERTE POR PADRÃO"* e manda des-registrá-lo ao terminar o diagnóstico. Tagá-lo o deixaria
**"observável" na contagem sem nunca poder ser observado** — presence-gate pelo avesso, a
classe LC-11 chegando pela porta dos fundos. **Escopo: 15, não 16.**

## O corte (por que os 7 banners ficaram de fora)

O #5314 separou por **sinal de bloqueio**. Aqui o discriminador é outro, e tem argumento
próprio em vez de carona: **hook condicional que morre é 100% invisível**; **banner de
SessionStart sempre fala**, então o sumiço dele aparece no início da sessão para um humano.

Fica registrada uma hipótese **não medida** que apareceu na análise e não virou conclusão: o
#5314 assumiu que o silêncio do bloqueador é o mais caro, mas para advisory pode ser o
inverso — um bloqueador morto ainda deixa sinal indireto (a ação passa quando devia parar),
enquanto um nudge morto não deixa nenhum.

## As duas formas (herdadas do #5314, não reinventadas)

**ALIAS (2)** — já emitiam tag própria, só faltava registrar. Ganho **retroativo**:
`tema-owner` tem rastro medido (8 emissões, que apareciam como órfãs) e passa a contar sem
perder o histórico. `charter-da-tela` tem 0, e o motivo foi verificado em vez de suposto:
dispara pouco (`PreToolUse:Read` = 32 attachments no corpus inteiro), **não** é canal morto.

**PREFIXO (13)** — a tag entra no **início**, não no meio. A sonda casa
`"content":"[<tag>]` no começo do valor, então uma linha vazia antes zeraria a
observabilidade. Nenhuma mensagem perdeu conteúdo.

## O bite-test — porque contador subindo não prova nada

`tagDe()` decide "observável" lendo o **fonte** (`src.includes('[tag]')`). Logo, uma tag
esquecida num **comentário** contaria como observável com o hook seguindo mudo no mundo — e
da pior espécie, porque o painel diria "coberto".

O [`observabilidade-tags.test.mjs`](../../.claude/hooks/observabilidade-tags.test.mjs) novo
prova o **comportamento**: dispara os 15 (**8 E2E com payload real** + 5 unit + 2 alias) e
exige a tag **no início** da saída, com **4 controles negativos** que provam que o teste sabe
reprovar (tag no meio, sem tag, saída vazia). Entra sozinho no required
`gate selftest (GT-G6)`, que roda `node --test .claude/hooks/*.test.mjs`.

Achar os payloads que **disparam de verdade** foi trabalho real: 3 dos 15 saíram com 0 bytes
na primeira tentativa, e em nenhum caso era a tag faltando — era a condição não satisfeita
(o `audit-creates-tasks`, por exemplo, lê o conteúdo de `tool_input.content`, não do disco).
Um teste que aceitasse os 0 bytes teria dado verde por não-execução (LC-13).

## O achado de tabela: uma promessa que não existia

`--check-aliases` estava **prometido** no cabeçalho dos ALIASES — *"se alguém renomear,
`--check-aliases` acusa"* — e **não existia no código**. A única ocorrência da string em todo
o arquivo era o próprio comentário.

Isso importava porque esta leva **dobrava a dependência** do mecanismo (8 → 10 aliases), e a
garantia prometida era vazia: alias que para de casar não dá erro em lugar nenhum — o hook só
volta, calado, para a lista de não-observáveis. É a lápide §5 2026-07-27 em pessoa
(*"promessa não testada apodrece calada"*).

Implementado com bite-test (fixture boa + 2 modos de quebra). **No primeiro uso achou um
alias real quebrado:** `mcp-first-nudge → [oimpresso-mcp-first]` aponta para um arquivo que
não existe desde a aposentadoria do
[#4587](https://github.com/wagnerra23/oimpresso.com/pull/4587) (2026-07-20). Removido — mantê-lo
faria o modo **nascer vermelho permanente**, que é o alarme que se aprende a ignorar.

## Efeito colateral honesto

**"Zero entrega" subiu 17 → 31.** Tornar observável **não cria** entrega: move de *"não sei se
funciona"* para *"sei que não entregou na janela"*. Os 14 que entraram nessa fila são o ganho,
não o custo — e o 15º (`tema-owner`) já nasce com 8 entregas comprovadas, que é exatamente o
que a forma ALIAS preserva.

## Sobre o ledger

**Não incrementei `LICOES_CODE.md`.** A promessa vazia do `--check-aliases` é da família da
lápide §5 2026-07-27, mas foi **consertada**, não **cometida** nesta sessão. Inflar contador
por afinidade temática é precisamente o erro que aquela mesma lápide registra (a errata do
próprio autor, que contou como "3ª instância" algo que tinha morrido antes de nascer).

## Estado final

- **8 não-observáveis:** os 7 banners de SessionStart (por argumento) + `diag-pretooluse-trace`
  (não emite por design)
- CI do [#5323](https://github.com/wagnerra23/oimpresso.com/pull/5323): **94 pass · 2 skipping
  · 0 fail**; `module-grades-gate` "all clear" (0 regressões, 31 estáveis). Mergeado 18:32 BRT
- Local: 42/42 na suíte de hooks · 20/20 no `hook-bites.test` (5 novos) · 19/19 no teste novo
  · `memory-health` 0 🔴

## Resíduo anotado, não tocado

`diag-pretooluse-trace` segue **wired** com matcher `Skill|DesignSync|design-login` embora o
próprio cabeçalho mande des-registrá-lo ao encerrar o diagnóstico da ADR 0315. Mexer em
`settings.json` é decisão [W] — fica registrado, não executado.
