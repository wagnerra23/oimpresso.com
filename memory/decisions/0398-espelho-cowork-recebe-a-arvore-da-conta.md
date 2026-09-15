---
slug: 0398-espelho-cowork-recebe-a-arvore-da-conta
number: 398
title: "O espelho Cowork recebe a árvore da conta, documentação incluída (emenda à 0397 D3)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-13"
module: governance
tags: [design, cowork, prototipo, ssot, importacao, handoff, playbook]
supersedes: []
superseded_by: []
related:
  - 0397-prototipo-minimo-por-dono-e-ds-direto
  - 0374-emenda-0315-espelho-cowork-e-rota-prevista
  - 0379-bundle-design-transacao-manifesto-delta-staging
pii: false
---

# ADR 0398 — o espelho Cowork recebe a árvore da conta

> **Emenda à [ADR 0397](0397-prototipo-minimo-por-dono-e-ds-direto.md) D3.** Não a revoga: D1
> (árvore mínima), D2 (procedência por dono), D4 (DS sem cópia), D5 (histórico só no Git), D6
> (caminho literal) e D7 (prova não atravessa mudança de identidade) ficam inteiras. O que muda
> é **uma cláusula** da D3 — a que mandava toda documentação sair de `prototipo-ui/`.

## Contexto

`prototipo-ui/cowork/<dono>/` é descrito como o espelho da conta Cowork daquele dono. Medido em
2026-09-13 contra o pacote de 2026-09-11, ele não era:

| | |
|---|---|
| arquivos no `project/` do pacote | **816** |
| pousavam no espelho | **400** |
| descartados por pasta de ruído | 72 |
| descartados por extensão | **344**, dos quais **337 `.md`** |

Descartar 41% do pacote — e justamente a camada de documentação — não é filtrar ruído: é receber
outra coisa. O efeito concreto foi perder o **`cowork-inbox/`**, que é o canal por onde a conta
Cowork manda ordem de serviço (playbooks com índice máquina-legível, threads e recibos).

Três mecanismos independentes produziam isso, e eles se contradiziam entre si:

1. **`cowork-ssot-guard.mjs` R3** aceitava `.md` só em `cowork/<dono>/handoffs/<nome>.md`, **flat**.
   A 0397 D3 dizia "documentação … em `handoffs/`" e **não** dizia flat — o `[^/]+` era escolha da
   implementação. Pior: o projeto Cowork **não tem** `handoffs/` (sua raiz tem `cowork-inbox/`,
   `contrato/`, `sync/`, `prototipos/`…), então o destino flat era um formato que só existia deste
   lado.
2. **`importar-bundle.mjs`** varria todo `.md` como junk — inclusive os que a própria R3 permitia.
   O defeito estava reportado no docblock e pinado em teste, não consertado.
3. **`aplicar-payload.mjs` / `bundle-contract.mjs`** recusavam o **lote inteiro** ao ver um `.md`,
   e o caso de exemplo do teste era literalmente `cowork-inbox/LEIAME.md`. Enquanto isso,
   `destinoDoBundle` tinha uma perna que mandava `.md` para `…/handoffs/<p>` — um destino que a R3
   proibia. Código morto roteando para um lugar ilegal.

O resultado acumulado: a pasta `cowork-inbox/` da conta chegava **partida por extensão** (o
payload `.mjs` em `handoffs/payloads/`, o `00-INDICE.md` irmão em lugar nenhum) e, na
reorganização de 2026-09-11, a metade `.md` foi apagada junto com `design-docs/` — 323 arquivos,
incluindo 21 recibos `_saida-NN.md` de threads concluídas. O programa de playbooks ficou sem
endereço no repo e o placar passou a ler "0 feito" para 67 threads.

[W] 2026-09-13, textual: *"porque so aceita lá isso é errado … deve ser igual ao cowork, não
poderia mudar assim facilita muito mais. na importação ou leitura lá"*.

## Decisão

**D1 — o espelho preserva a árvore da conta.** O que a conta Cowork manda pousa em
`prototipo-ui/cowork/<dono>/` **no mesmo caminho relativo**, sem renomear pasta e sem repartir por
extensão. Espelho que muda a forma do original não é espelho.

**D2 — `.md` é conteúdo do espelho.** A R3 deixa de proibir documentação sob `cowork/<dono>/`. O
que sobra dela é a regra que protege algo real: `.md` vive **dentro de um dono**, nunca solto em
`cowork/`. Documentação **canon** do Code (política, runbook, referência) segue em
`memory/reference/prototipo-ui/` — a 0397 D3 continua valendo para ela. O que passa a caber no
espelho é a documentação **da conta**, que é parte do pacote e não canon nosso.

**D3 — um caminho só por arquivo.** `handoffs/payloads/` volta a ser `cowork-inbox/`: ele era o
`cowork-inbox/` da conta renomeado, e manter os dois nomes mantinha a âncora ambígua.
`handoffs/` segue existindo como canal próprio do dono, para o que nasce deste lado.

**D4 — a R4 não foi afrouxada, e ela morde a fonte.** Duplicata de bytes dentro de `prototipo-ui/`
continua proibida. Medido em 2026-09-13, o pacote traz **10 pares byte-idênticos** entre
`cowork-inbox/sidebar/playbook/` e `entrega-sidebar-code/playbook/`. Duplicata na **fonte** não
vira duplicata no espelho: o import falha e nomeia o par. Quem desduplica é o lado Cowork.

**D5 — mudança de regra em gate required nasce com bite-test.** O `cowork-ssot-guard` era required
e não tinha teste nenhum. Passa a ter, exercitando o **CLI de fora** com fixture por cwd, e com
controles negativos para o que **não** afrouxou (R2, R4, `.md` sem dono).

## Consequências

- o `cowork-inbox/` desembarca inteiro, e o programa de playbooks volta a ter endereço — os
  recibos `_saida-NN.md` param de ficar sem casa e o placar volta a significar algo;
- as duas rotas de importação (ZIP e bundle DesignSync) passam a entregar o mesmo conjunto, em vez
  de uma recusar o que a outra aceitava pela metade;
- `destinoDoBundle` perde a perna que separava `.md` — a pasta do Cowork para de chegar partida;
- o próximo import **vai falhar** enquanto os 10 pares duplicados existirem na origem; isso é o
  comportamento desejado, e o recado é para o lado Cowork;
- a árvore restaurada nesta leva (347 arquivos) vem do Git (`4f51a9ec78^`) fundida com o pacote de
  2026-09-11; onde o pacote trazia versão **mais pobre** do mesmo arquivo — os `00-INDICE.md` do
  cache local do Cowork, medidos até **2,4× menores** — prevaleceu a do repo.

## Alternativas descartadas

- **Manter a R3 flat e re-homear o programa em `memory/reference/prototipo-ui/`.** Resolveria o
  endereço dos recibos e deixaria os três mecanismos de importação como estão — ou seja, o pacote
  continuaria chegando pela metade, e a cada ciclo alguém reconciliaria à mão. Foi o caminho que
  eu propus antes de medir; [W] recusou.
- **Afrouxar a R4 junto.** Faria o espelho aceitar a duplicata que a própria fonte traz, criando
  dois donos para os mesmos bytes — exatamente o que a 0397 D5 e a R4 existem para impedir.
- **Rotear `.md` para um balde próprio (`handoffs/`, `docs/`).** É o que `destinoDoBundle` fazia, e
  é a causa da pasta partida: o `.md` e o irmão de build da mesma pasta iam para lugares
  diferentes.
