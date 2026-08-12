---
date: "2026-08-12"
hour: "16:17 UTC"
topic: "Pedir adversário antes de consertar — como 2 agentes read-only derrubaram 3 claims minhas sobre a arquitetura React/Laravel"
authors: [C, W]
prs: [5673]
outcomes:
  - "3 claims minhas refutadas por medição, uma delas repetindo canon errado"
  - "O conserto encolheu de 'arrumar a bagunça' para 'o doc parar de mentir' — e cresceu em 6 chips"
  - "Registro de método: o denominador errado é o vetor, não a medida errada"
---

# Pedir adversário antes de consertar

## O que aconteceu, em ordem

[W] perguntou onde ficam os React dos módulos e se estava uma bagunça. Medi, respondi **"não está"**,
e ele insistiu que notava falta de lógica clara. Eu ofereci um conserto de dez linhas no
`.claude/rules/components.md`. **Ele pediu adversário antes.**

Os dois adversários (eixo Laravel modular, eixo React/Inertia) derrubaram três afirmações minhas. O
conserto que sobrou é o mesmo arquivo — mas por razões diferentes das que eu tinha, e com cinco
frentes que eu não tinha visto virando chip.

## A lição central: o vetor é o DENOMINADOR, não a medida

Nenhuma das minhas contagens estava errada. O adversário refez `Components/` e bateu **9 de 9**.

O erro foi **sobre o que** eu contei: medi a pasta que **tem gate** (82 arquivos) e concluí sobre a
árvore inteira (527). Escolhi, sem perceber, o pedaço onde a resposta era boa — e é justamente o
pedaço onde alguém já tinha trabalhado. `Pages/`, 445 arquivos, é onde [W] passa o dia, e é onde
estão as 7 convenções de pasta concorrendo.

Isso ecoa a lápide de 2026-07-27 (*denominador que nenhuma decisão estabeleceu*), num eixo novo: lá o
denominador era inventado; aqui ele era **real, porém não era o da pergunta**.

## O erro que mais dói: repetir canon sem medir

Afirmei que o local das Pages era *"imposição do Inertia"*. O adversário mostrou: dois globs, não um;
`resolve` é callback arbitrário; e há um Pest que **crava a string exata** do glob — ninguém fixa por
teste o que o framework impõe.

E a frase já estava em canon (`sessions/2026-05-15-wave3-b6-repair.md:26`). Li, aceitei, repeti a [W]
como fato de arquitetura. **Canon é insumo, não oráculo** — o oráculo custava um `rg`.

## Onde os adversários também erraram (e por que valeu)

Os dois erraram e se corrigiram **sozinhos, antes de publicar**:

- O do React rodou `rg -l "@/Components/PageHeader"` → **0**, quase reportando *"nenhuma tela usa o
  canon"*; com `-F` deu **31**. Publicou os dois comandos.
- O do Laravel usou `rc=$?` depois de pipe **duas vezes** (a lápide §5 2026-07-17) e refez com
  redirect.
- O do Laravel voltou **depois de entregar** para corrigir a largura da própria prova: tinha afirmado
  *"0 importadores"* do `_cowork-bundle` varrendo só `.tsx`/`.ts`; refez sobre o repo inteiro. Mesmo
  resultado, e escreveu: *"a prova não era do tamanho da alegação quando escrevi"*.

Essa última frase é o resumo do que eu errei três vezes hoje.

## O achado que só apareceu na segunda passada

`Pages/Financeiro/_cowork-bundle/README.md:11` afirma que **underscore exclui do auto-discovery**.
Falso: o glob é `*.tsx`, então `.tsx` sob pasta `_` **entra**; o que exclui aqueles 10 arquivos é
serem `.jsx` — exatamente o que o Pest ao lado asserta.

O teste guarda o mecanismo **certo**; o README ao lado descreve o **errado**. É pior que o erro do
session log, porque aquele mente passivamente e este **instrui uma prática que falha**: criar
`_rascunho/Foo.tsx` acreditando estar fora do discovery.

## Método que funcionou, e vale repetir

1. **[W] pedir adversário antes do conserto.** Se eu tivesse escrito o doc primeiro, o exercício
   seria decorativo — o adversário mudaria um texto já mergeado.
2. **Mandato explícito de refutar**, com as armadilhas conhecidas no prompt (`rg --hidden`, `*` do
   pathspec atravessando `/`, `rc` depois de pipe). Os três apareceram no trabalho deles.
3. **Dois eixos independentes** em vez de um revisor genérico: cada um achou o que o outro não viu —
   o Laravel achou os 430 Blades e os 18 configs; o React achou as 7 convenções e os 27 imports.
4. **Verificar link antes de escrever.** Ia linkar `0189-pageheader-v3.md` e `0253-layout-primitives.md`;
   os dois nomes estavam errados.

## O que NÃO virou chip, de propósito

Os 231 endpoints Blade. Têm dono e fila (`migracao:report`). Abrir chip ali seria autorar processo
paralelo a tema com dono — LC-19, que o ledger já alarma com 3 ocorrências.
