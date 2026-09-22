---
id: requisitos-manufacturing-insumos-visual-comparison
title: "Comparacao design x producao — Manufacturing/Insumos (impacto reverso)"
module: Manufacturing
tela: Manufacturing/Insumos
owner: W
status: rascunho
inertia_target: resources/js/Pages/Manufacturing/Insumos.tsx
last_updated: "2026-09-22"
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

## Cobertura desta tela hoje

| camada | estado |
|---|---|
| charter | ✓ `Insumos.charter.md` — **`status: draft`** |
| casos | ✓ **5 UCs** em `Insumos.casos.md` |
| medição design×prod | ✗ **nunca rodou** |
| e2e / browser | ✗ nenhum |
| scorecard | ✗ nenhum |
| baseline de pixel | ✗ **0 `.snap`** |
| contrato de tela | ✗ nenhum |

## Pendências

1. **Primeira medição** — âncora nova, `sameTheme: true`, sonda nos dois lados.
2. Achados do [LAUDO](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/design/LAUDO-conferencia-fabricacao.md)
   que caem aqui: o `--text-mute` do **SKU** e da **unidade** (*"unidade lida errada troca m² por m
   linear"*) e o vazamento da pílula `.mfg-pill` na coluna "Maior peso" (160px), achado MÉDIA.
3. `status: draft` → `live` só depois de (1).

## Refs

- [`Insumos.charter.md`](../../../resources/js/Pages/Manufacturing/Insumos.charter.md) · [`Insumos.casos.md`](../../../resources/js/Pages/Manufacturing/Insumos.casos.md) · [RUNBOOK-insumos.md](RUNBOOK-insumos.md)
- [README do handoff](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/README.md) — **normativo** · [SDD](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/contexto/SDD-tela-fabricacao-v1.0.md)
