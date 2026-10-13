---
sessao: "errata-indice"
titulo: "Manufacturing — a 04 segue entregue; a prova-guarda do partial reload envelheceu quando o #8991 acrescentou `kpis`"
executor: "[CL]"
data: 2026-10-13
base: wagnerra23/oimpresso.com@main 37b907474
---
# Errata do `00-INDICE.md` da Manufacturing (pedido ao Cowork)

O Code não edita o `00-INDICE.md` no espelho (§5 2026-09-24). A correção vale quando o Cowork a
fizer e o próximo retorno a trouxer. Este arquivo não é recibo: o placar lê só `_saida-<id>.md`.

## O que o placar mostra

`node scripts/qa/placar.mjs --indice …/manufacturing/playbook/00-INDICE.md --thread 04`:

```
04 [em curso ] … — resources/js/Pages/Manufacturing/Index.tsx (não contém "only: ['productions', 'summary', 'filters']")
```

## Por quê

A 3ª prova da thread 04 é uma **guarda** (`"guarda": true`, nota *"continua partial reload"*) que
procura o literal `only: ['productions', 'summary', 'filters']`. Em **2026-10-07** o #8991
(`816c28b09`, "indicadores das Ordens seguem o filtro de local e período") acrescentou `kpis` às
duas chamadas de partial reload:

```
-    only: ['productions', 'summary', 'filters'],
+    only: ['productions', 'summary', 'kpis', 'filters'],
```

O que a guarda protege continua de pé: o filtro segue em partial reload, agora recarregando também
os indicadores, que passaram a depender do período. O literal é que mudou.

## O resto da 04, medido em `37b907474`

| prova | estado |
|---|---|
| sem `onBlur={applyDateRange}` | passa (0 ocorrências) |
| sem "Aplicar intervalo de datas" | passa (0 ocorrências) |
| partial reload | `Index.tsx:103` e `:257` com `only: ['productions', 'summary', 'kpis', 'filters']` |
| rótulo `htmlFor="mfg-op-data-inicial"` | passa (`Index.tsx:417`) |
| `aplica ao escolher` no casos | passa (`Index.casos.md:120`, UC-OP-06) |

O comportamento da 04 também segue no código: `applyDateRange` é chamado no `onChange` dos dois
campos (`Index.tsx:428` e `:446`) e só aplica com os dois vazios ou as duas datas completas
(`:268-275`). O teste `tests/js/manufacturing-index-datas.test.tsx:72` já espera a forma nova
(`['productions', 'summary', 'kpis', 'filters']`) e está no gatilho do
`manufacturing-jsdom-gate.yml`.

## O que mudar no índice

Na thread `04`, trocar o `padrao` da 3ª prova:

```json
{
  "tipo": "contem",
  "path": "resources/js/Pages/Manufacturing/Index.tsx",
  "padrao": "only: ['productions', 'summary', 'kpis', 'filters']",
  "guarda": true,
  "nota": "continua partial reload (kpis entrou com o #8991, 2026-10-07)"
}
```

Com essa troca as 5 provas passam e a 04 sai como `feito` pelo `_saida-04.md` que já existe.

Alternativa mais resistente, se o Cowork preferir: procurar só `only: ['productions'`, que
continua valendo se outra prop entrar na lista. A desvantagem é que ela deixa de pegar a remoção de
`filters`.
