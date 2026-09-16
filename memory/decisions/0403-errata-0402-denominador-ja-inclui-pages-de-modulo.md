---
slug: 0403-errata-0402-denominador-ja-inclui-pages-de-modulo
number: 403
title: "Errata a 0402 - o denominador de tela JA inclui as Pages de modulo"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-09-16"
module: governance
quarter: 2026-Q3
tags: [governanca, gates, tela, errata, cobertura, modulos]
supersedes: []
supersedes_partially: [0402-denominador-de-tela-inclui-pages-de-modulo]
superseded_by: []
related:
  - 0402-denominador-de-tela-inclui-pages-de-modulo
  - 0264-governanca-executavel-trio-dominio-e2e
pii: false
review_triggers: []
---

# ADR 0403 - errata a 0402

A [ADR 0402](0402-denominador-de-tela-inclui-pages-de-modulo.md), aceita e mergeada em
2026-09-16, afirma que as reguas de cobertura **ignoram** as telas em
`Modules/*/Resources/js/Pages/**`. **Isso e falso.** Elas ja estao no denominador, e ja
estao grandfatherizadas no baseline. Esta errata retrata E1 e E2; o resto da 0402 fica.

## O recibo

Rodando o **codigo do proprio relatorio** (`screen-coverage-map.mjs:113` -
`raizesDePages(ROOT).flatMap(raiz => walk(raiz, isScreen))`) contra o disco:

```
screens no DISCO: 220
  de modulo:       37
```

**As 37 ja estao dentro das 220.** Mais:

| medicao | resultado |
|---|---|
| `page-path.mjs` (fonte unica dos dois donos) | `RAIZ_PAGES = /^(?:Modules\/[^/]+\/)?[Rr]esources\/js\/Pages\//` - **ja cobre modulo** |
| `raizesDePages(ROOT)` | descobre os **7** modulos (Cms · Forja · KB · Officeimpresso · PaymentGateway · Superadmin · Whatsapp) |
| `casos-coverage-guard` | mesmas **220** telas · `rc=0` |
| `scripts/casos-coverage-baseline.json` | **29 entradas** `Modules/` - a divida ja esta grandfatherizada |
| 3 telas do Cms sem `casos.md` (`Site/BlogPost`, `Site/Blogs`, `Site/Home`) | presentes no baseline, por isso nao viram violacao |

O `screen-coverage-map.mjs` ainda carrega, num teste, a frase que deveria ter encerrado a
duvida antes da 0402 existir: *"Se isto voltasse a olhar so `resources/js/Pages`, as 80
telas de modulo sumiriam da base"*.

## Como o erro entrou (LC-08)

A 0402 nasceu destas duas sondas:

```bash
npm run screen-coverage:report | grep -c 'Modules/'        # 0
node scripts/casos-coverage-guard.mjs | grep -c 'Modules/' # 0
```

O relatorio **agrupa por nome de modulo** (`Forja 4`, `Whatsapp 3`, `superadmin 6`) e
**nunca imprime path**. Mediu-se a ausencia do path **na saida** e concluiu-se ausencia do
dado **no denominador** - a sonda respondeu outra pergunta, com confianca.

Havia **dois** sinais contrarios, e os dois foram lidos como confirmacao:

1. o relatorio lista `Forja`, `kb`, `superadmin`, `Cms` - e **nenhuma** dessas pastas existe
   em `resources/js/Pages/` (medido: 0 telas cada). Se o denominador fosse so o nucleo,
   esses nomes nao teriam origem;
2. o comentario do teste citado acima foi **lido e citado** na mesma sessao, e tratado como
   se falasse de contagem, quando afirmava a cobertura.

## Decisao

**E1 da 0402 - RETRATADA.** Nao ha uniao a fazer: o denominador ja e a uniao, pela fonte
unica `page-path.mjs`. Nenhum codigo a escrever.

**E2 da 0402 - RETRATADA.** Nao ha 23 telas a grandfatherizar: o baseline do casos-guard ja
tem 29 entradas de modulo. A divida ja e visivel e ja nao reprova.

**E3 e E4 da 0402 - MANTIDAS.** Artefatos seguem ao lado do `.tsx`; nada e movido; nada e
promovido a required; nenhum prazo e fixado.

**O unico achado da 0402 que SOBREVIVE**, e ele foi medido: o
`render-proto-baseline.mjs --gerar` **nao deriva destino** para tela de modulo - sai
`⛔ nao sei derivar o destino (charter sem tela viva .tsx)` e exige `--out` explicito,
porque so procura em `resources/js/Pages/`. Isso e **defeito de UM script**, nao do
denominador de cobertura, e o conserto e um PR pequeno - nao materia de ADR. A 0402
generalizou de um para o outro.

## Consequencias

- nenhum trabalho de implementacao decorre da 0402: quem for implementa-la nao deve;
- o numero canonico de telas segue **220** pela fonte unica, com as 37 de modulo dentro;
- fica registrado que a 0402 nao deve ser citada como prova de que as reguas sao cegas a
  modulo - e o oposto que esta medido.

## Residuo declarado

**Nao medi** por que o relatorio agrupa `Forja` como 4 quando o modulo tem 13 telas pelo
criterio do dono. Pode ser agrupamento por namespace, filtro de rota, ou outra coisa. Isso
**nao** afeta esta errata - as 37 estao no denominador, medido pelo codigo do relatorio -
mas quem for mexer no relatorio deve medir antes de concluir.
