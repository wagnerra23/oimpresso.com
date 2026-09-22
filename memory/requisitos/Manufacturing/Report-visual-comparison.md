---
id: requisitos-manufacturing-report-visual-comparison
title: "Comparacao design x producao — Manufacturing/Report (Relatorio do periodo)"
module: Manufacturing
tela: Manufacturing/Report
owner: W
status: rascunho
inertia_target: resources/js/Pages/Manufacturing/Report.tsx
last_updated: "2026-09-22"
---

# Comparação design × produção — `Manufacturing/Report` (Relatório)

> Um dos 5 primeiros registros do módulo (ver [Index-visual-comparison.md](Index-visual-comparison.md)).

## Âncora — computada, não escolhida no olho

```
node scripts/design/ancora.mjs Manufacturing/Report --staging prototipo-ui/cowork
  âncora ✓: [related_prototype (charter)] prototipo-ui/cowork/Felipe/manufacturing-producao.jsx
```

O componente é `MfgRelatorio` (`:268` do protótipo). Passou de `Wagner/` para `Felipe/` em
2026-09-22 ([PR #7696](https://github.com/wagnerra23/oimpresso.com/pull/7696), decisão [W]).

⚠️ **Três telas dividem este arquivo, em componentes diferentes** — `Index` → `MfgProducaoView`
(`:31`), `Report` → `MfgRelatorio` (`:268`), `Settings` → `MfgConfig` (`:330`). A âncora aponta o
**arquivo**; quem diz qual pedaço é esta tela é esta linha. Não leia "mesma âncora" como "mesma
tela".

> ⚠️ **Saída medida com o [#7696](https://github.com/wagnerra23/oimpresso.com/pull/7696)
> aplicado** (branch `claude/fabricacao-prototype-production-8fa4a8`, 2026-09-22). Enquanto ele
> não mergear, `main` ainda resolve esta tela em `Wagner/` — o que este bloco registra é a
> medição daquela branch, não o estado de `main`.

⚠️ **Sem selo de frescor** — `ancora.mjs:93` fixa `LUGAR_FIXO = 'prototipo-ui/cowork/Wagner'`.

## ⚠️ Esta tela NUNCA foi medida

Não existe artefato em `governance/design/targets/medidas/Manufacturing--Report/`. A rodada de
[#7503](https://github.com/wagnerra23/oimpresso.com/pull/7503) (18/09) cobriu **2 das 5** telas da
família (`Index` e `Recipes`); esta ficou de fora. **Ausência de medição não é ausência de
divergência** — e nenhum gate que bloqueia merge mede fidelidade.

## Cobertura desta tela hoje

| camada | estado |
|---|---|
| charter | ✓ `Report.charter.md` — **`status: draft`** |
| casos | ✓ **6 UCs** em `Report.casos.md` |
| medição design×prod | ✗ **nunca rodou** |
| e2e / browser | ✗ nenhum |
| scorecard | ✗ nenhum |
| baseline de pixel | ✗ **0 `.snap`** |
| contrato de tela | ✗ nenhum |

## Pendências

1. **Primeira medição** — âncora nova, `sameTheme: true`, sonda nos dois lados.
2. ⚠️ **Relatório mostra dinheiro.** Se a subida do protótipo mexer em qualquer cálculo exibido
   aqui (custo, custo unitário, margem, perda), vale a **regra mestre de valor/estoque**
   ([proibicoes.md](../../proibicoes.md)): prova por dois caminhos independentes + tabela
   antes→depois + aprovação [W] **antes** do merge. Não é opcional nesta tela.
3. Achados do [LAUDO](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/design/LAUDO-conferencia-fabricacao.md):
   `--text-mute` nomeia a coluna de dinheiro e reprova AA; `--accent` mede **2,64:1** no escuro.
   Token do DS (ADRs 0410/0411) — decisão [W].
4. `status: draft` → `live` só depois de (1).

## Refs

- [`Report.charter.md`](../../../resources/js/Pages/Manufacturing/Report.charter.md) · [`Report.casos.md`](../../../resources/js/Pages/Manufacturing/Report.casos.md) · [RUNBOOK-report.md](RUNBOOK-report.md)
- [README do handoff](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/README.md) — **normativo** · [SDD](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/contexto/SDD-tela-fabricacao-v1.0.md)
