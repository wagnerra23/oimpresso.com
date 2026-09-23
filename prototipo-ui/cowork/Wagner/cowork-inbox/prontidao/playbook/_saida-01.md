---
sessao: "01"
titulo: prototipo-readiness troca o ponto do slug por hífen (kb/Index.v2)
executor: "[C]"
base: dd380c33a374
---
# _saida 01

## Checklist
1. ✅ regex `/[\/.]/g` em `scorecardSlug()` · 2. ✅ bite-test pelo `coleta()` (o caminho que decide o veredito) e pela função
3. ✅ controle: `Essentials/Todo/Index` → `essentials-todo-index`, igual a antes · 4. ✅ só o prefixo tocado

## Antes → depois (`node scripts/qa/prototipo-readiness.mjs`, sem `--json`)

| | antes | depois |
|---|---|---|
| prontas | 59 | **60** |
| 1-ciclo | 35 | **34** |
| total | 94 | 94 |

Diff das linhas de tela: **uma só** mudou de balde, `kb/Index.v2` (saiu de `1-ciclo · falta: scorecard`, entrou em prontas). Nenhum outro scorecard deixou de casar.

## Mordida provada por mutação
Revertido o regex para `/[\/]/g`: 2 asserts caem (`slug troca o ponto` → obteve `kb-index.v2`; `BITE` → `status: 1-ciclo, falta: scorecard`). Restaurado conferindo o hash do arquivo antes de ler resultado.

## PARAR SE — conferido
- **Slug compartilhado com seed/vital-signs?** Não há fonte única. `vital-signs.mjs` casa por `path:` do YAML (não usa slug). `screen-grade-seed.mjs:45` e `screen-coverage-map.mjs:305` têm cada um **a sua cópia** da mesma regra, sem ponto. Não mexi (fora do prefixo). Consequência medida: só existe **uma** Page com ponto no nome (`resources/js/Pages/kb/Index.v2.tsx`), então as duas cópias erram só nela — o `screen-coverage-map` também deve estar dizendo "sem scorecard" para `kb/Index.v2`. Fica para quem for unificar o slug num lugar só.
- **Contagem caiu?** Não: 59 → 60.
