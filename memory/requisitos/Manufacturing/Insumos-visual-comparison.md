---
id: requisitos-manufacturing-insumos-visual-comparison
title: "Comparacao design x producao — Manufacturing/Insumos (impacto reverso)"
module: Manufacturing
tela: Manufacturing/Insumos
owner: W
status: rascunho
inertia_target: resources/js/Pages/Manufacturing/Insumos.tsx
last_updated: "2026-10-05"
---

# Comparação design × produção — `Manufacturing/Insumos`

> Um dos 5 primeiros registros do módulo (ver [Index-visual-comparison.md](Index-visual-comparison.md)).

## Âncora — computada, não escolhida no olho

```
node scripts/design/ancora.mjs Manufacturing/Insumos --staging prototipo-ui/cowork
  âncora ✓: [related_prototype (charter)] prototipo-ui/cowork/Felipe/manufacturing-insumos.jsx
```

`MfgInsumosView` (`:109` do protótipo) — *"impacto reverso: quais receitas usam cada insumo e o que
acontece com custo/margem quando o preço de compra varia"*. Passou de `Wagner/` para `Felipe/` em
2026-09-22 ([PR #7696](https://github.com/wagnerra23/oimpresso.com/pull/7696), decisão [W]).

> ⚠️ **Saída medida com o [#7696](https://github.com/wagnerra23/oimpresso.com/pull/7696)
> aplicado** (branch `claude/fabricacao-prototype-production-8fa4a8`, 2026-09-22). Enquanto ele
> não mergear, `main` ainda resolve esta tela em `Wagner/` — o que este bloco registra é a
> medição daquela branch, não o estado de `main`.

⚠️ **Sem selo de frescor** — `ancora.mjs:93` fixa `LUGAR_FIXO = 'prototipo-ui/cowork/Wagner'`.

## ⚠️ Esta tela NUNCA foi medida

Não existe artefato em `governance/design/targets/medidas/Manufacturing--Insumos/`. A rodada de
[#7503](https://github.com/wagnerra23/oimpresso.com/pull/7503) (18/09) mediu **2 das 5** telas da
família — `Index` e `Recipes`. Esta ficou de fora.

**Ausência de medição não é ausência de divergência.** Não há aqui um "está ok" — há um buraco. O
custo é que a tela pode divergir do protótipo por tempo indeterminado sem que nada fique vermelho:
nenhum gate que bloqueia merge mede fidelidade (os 6 required de tela medem **existência e forma** —
âncora, charter, casos, cobertura, PageHeader).

## Re-medicao 2026-09-22 — segue **NAO MEDIDA**, agora com a causa nomeada

A rodada de hoje mediu **4 das 5** telas da familia. Esta ficou de fora, e desta vez a razao esta
medida em vez de suposta — o `--dry` devolve `rota do shell: sem rota derivavel` e
`executaveis: 0/1`.

**O espelho nao expoe rota para a aba Insumos.** O `app.jsx` do espelho do Felipe tem exatamente
quatro rotas da familia (`:848-851`): `manufacturing` (aba receitas), `mfg-producao`,
`mfg-relatorio` e `mfg-config`. Nao existe `mfg-insumos`, embora a aba exista no `ABAS` do
`manufacturing-page.jsx`.

**Por que NAO foi resolvido com override.** O override de token so escolhe entre rotas que
**existem**. Apontar esta tela para `manufacturing` faria o lado design abrir a aba **Receitas**, e
o veredito sairia comparando telas diferentes — foi exatamente o acidente que aconteceu com o
`Manufacturing/Index` na primeira rodada de hoje, e que **so foi pego porque o token fica gravado
no `resultado.json`**. Medir a tela errada e pior do que nao medir: produz um veredito com
aparencia de valido.

**O conserto pertence ao lado do design** — a rota `mfg-insumos` precisa existir no shell do Cowork
e descer no proximo bundle. Ate la, esta tela permanece sem paridade medida, e isso e estado
declarado, nao silencio.

## Medição 2026-10-01 e grade nova 2026-10-04

A tela FOI medida em 2026-10-01 (empresa 1, tema escuro, 1440px), com a sonda do `style-fingerprint`
e a complementar de controles e células, a mesma régua nos dois lados. As seções acima ("nunca foi
medida") ficam como registro do que valia até ali. O que a medição mostrou na **grade**, e o que o PR
de 2026-10-04 faz com isso (a grade passa a ser o `shared/DataTable`, `density="grid"`):

| item | protótipo (DataGrid) | produção em 10-01 | depois deste PR |
|---|---|---|---|
| estrutura | `<table>`, linhas listradas | div-grid local | `<table>`, linhas listradas |
| nome do insumo | texto normal, 12,5px | negrito 600 | texto normal |
| código | mono 12px, cor do texto | mono 10,5px, apagado | mono 12px, cor do texto |
| custo / estoque / receitas | à direita, mono 12px | à esquerda, 10,5px | à direita, mono 12px |
| maior peso | `StatusBadge` | pílula local `.mfg-pill` | `StatusBadge` (suave com ponto, regra AP7) |
| clique | foco em toda linha, abre só com receita | igual | igual |

**Medido em produção em 2026-10-05** (deploy `37270930132`, commit `f79b5f3f27`, que contém o #8628; biz=1, tema escuro, 1440 px; mesmas sondas do protótipo — `style-fingerprint --snippet` e a complementar de células/controles — e arquivos conferidos por hash). A produção tem 1 insumo (o protótipo, 22):

| item | protótipo | produção 2026-10-05 | veredito |
|---|---|---|---|
| estrutura | `<table>` | `<table>`, nenhuma `.mfg-table` | IGUAL |
| cabeçalho | 10px, 27px de altura, fundo `--bg-2` | 10px, 27px, mesmo fundo | IGUAL |
| nome | 12,5px/400, cor do texto | 12,5px/400, mesma cor | IGUAL |
| código, custo, estoque, receitas | mono 12px, custo/estoque/receitas à direita | igual | IGUAL |
| cor do texto do cabeçalho | `--text-mute` (0,58) | `--text-dim` (0,72) | troca de contraste AA (ADR 0410) — a mesma da Receitas (R19 de [Recipes-visual-comparison](Recipes-visual-comparison.md)) |
| selo "% do custo" | sólido, texto branco, 11,5px | tintado com ponto, 12px, 22px de altura | o protótipo usa o mesmo `StatusBadge` do DS, num pacote compilado anterior à regra AP7 (selo de estado é tintado com ponto, nunca sólido — `prototipo-ui/design-system/README.md`). Faixas de cor iguais: ≥50 vermelho, ≥25 amarelo, abaixo verde |
| altura da linha | 34px | 33px | consequência do selo acima (22px + 5+5 + borda) |
| listras | linhas alternadas | — | não verificável: 1 linha só |

**Fora da grade, segue igual:** a busca (protótipo 360×34 com ícone; produção 384×30 sem ícone).

## Cobertura desta tela hoje

| camada | estado |
|---|---|
| charter | ✓ `Insumos.charter.md` — **`status: draft`** |
| casos | ✓ **5 UCs** em `Insumos.casos.md` |
| medição design×prod | ✓ 2026-10-01 e 2026-10-05 (seções acima) |
| e2e / browser | ✗ nenhum |
| scorecard | ✗ nenhum |
| baseline de pixel | ✗ **0 `.snap`** |
| contrato de tela | ✗ nenhum |

## Pendências

1. **Primeira medicao — TENTADA em 2026-09-22 e BLOQUEADA** (secao acima). O bloqueio nao e
   desta tela nem do driver: falta a rota `mfg-insumos` no shell do Cowork. Enquanto ela nao
   descer no bundle, nao ha lado design para sondar.
2. **Calibrar o `__DD_ROLES`** desta familia (override em [`governance/design/targets/roles/`](../../../governance/design/targets/roles/)). E o que tira as regioes de `SEM-DADO` e faz o veredito falar da tela, nao de 4 celulas. O molde pronto e o [`Compras--Index.json`](../../../governance/design/targets/roles/Compras--Index.json), que ja mede papel no DOM dos dois lados em vez de adivinhar.
2. Achados do [LAUDO](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/design/LAUDO-conferencia-fabricacao.md)
   que caem aqui: o `--text-mute` do **SKU** e da **unidade** (*"unidade lida errada troca m² por m
   linear"*) e o vazamento da pílula `.mfg-pill` na coluna "Maior peso" (160px), achado MÉDIA.
3. `status: draft` → `live` só depois de (1).

## Refs

- [`Insumos.charter.md`](../../../resources/js/Pages/Manufacturing/Insumos.charter.md) · [`Insumos.casos.md`](../../../resources/js/Pages/Manufacturing/Insumos.casos.md) · [RUNBOOK-insumos.md](RUNBOOK-insumos.md)
- [README do handoff](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/README.md) — **normativo** · [SDD](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/contexto/SDD-tela-fabricacao-v1.0.md)
