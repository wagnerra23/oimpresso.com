---
sessao: "errata-indice"
modulo: ds-atomos
dono: "[CL]"
data: 2026-10-07
natureza: ERRATA DO ÍNDICE — prova errada, a voltar ao Cowork
base_lida: wagnerra23/oimpresso.com@main c8777ae00a (testes) · ea4b4f0985 (recibo; 0 diff nos arquivos medidos)
---
# Errata do `00-INDICE.md` do ds-atomos (pedido ao Cowork) — threads 01–05

Este arquivo **não** é recibo de thread: o placar lê só `_saida-<id>.md`, então ele não muda
estado nenhum. Ele registra por que as threads 01–05 aparecem `em curso (indecidível)` e o que o
Cowork precisa corrigir no `00-INDICE.md`. **Não editei o índice** — é arquivo verificado do
espelho; editá-lo derruba o check required "espelho — mexeu depois de verificar" em todos os PRs
(§5 2026-09-24 · `PEDIDO-CL-ordem-pendencias-playbooks-2026-10-06` §5).

## 1 · Placar antes

`node scripts/qa/placar.mjs --indice` em `c8777ae00a`:

```
ds-atomos: entregue 2 de 7 · próximo 0 · em curso 5 · pendente 0 · bloqueada 0
  01 [em curso ] (indecidível) ui/card.tsx — badge · note · flush — ${UI}/card.tsx (prova "execucao" precisa do avaliador de recibo …)
  02 … 03 … 04 … 05 — idem, cada uma com a sua prova "execucao"
```

Em cada thread, todas as provas estruturais passam. A única que falta é a `execucao`.

## 2 · Classificação

| thread | entregue? | evidência | classe |
|---|---|---|---|
| 01 `ui/card.tsx` | sim — PR #7253 | `_saida-01.md`; teste roda na lane `card-anatomia-gate` | (a) prova errada |
| 02 `KpiCard variant=filter` | sim — PR #7251 | `_saida-02.md` | (a) prova errada |
| 03 `shared/Toolbar.tsx` | sim — PR #7252 | `_saida-03.md` | (a) prova errada |
| 04 `DataTable density` | sim | `_saida-04.md` | (a) prova errada |
| 05 `StatusBadge kinds` | sim | `_saida-05.md` | (a) prova errada |

Nenhuma thread tem resto executável nem resto que dependa de decisão [W] **dentro do próprio
escopo**. Os achados para decisão que o `_saida-05` lista (Atrasado × Vencido quase iguais no
dark; kinds sem cor fora do `.cockpit`) seguem como estavam, fora do prefixo.

## 3 · Por que a prova está errada

`execucao` é prova de recibo. O avaliador dela saiu do repo com a ADR 0397, e o placar a marca
**não medida** por desenho (`scripts/qa/placar-indice.mjs`, `TIPOS_ESTRUTURAIS`). Thread com prova
não medida nunca vira `feito`. As cinco ficam presas em `em curso`, embora entregues.

Os comandos das provas **passam** quando rodados à mão — medido em `c8777ae00a`, com o vitest do
repo:

| comando da prova | arquivo que roda | resultado |
|---|---|---|
| `npm run test -- card` | `card-anatomia` + `kpicard-variant-filter` + `janaMetaCardRodape` | 3 arquivos · 47 passed |
| `npm run test -- KpiCard` | `tests/js/kpicard-variant-filter.test.tsx` | 24 passed |
| `npm run test -- Toolbar` | `tests/js/toolbar.test.tsx` | 13 passed |
| `npm run test -- datatable-density` | `tests/js/datatable-density.test.tsx` | 8 passed |
| `npm run test -- statusbadge-kinds` | `tests/js/statusbadge-kinds.test.tsx` | 46 passed |

## 4 · Correção proposta para o Cowork aplicar no `00-INDICE.md`

Trocar a prova `execucao` de cada thread por provas estruturais. Elas provam que o teste de
guarda existe; não provam que ele passa. O "passa" fica medido acima, com data e commit.

| thread | sai | entra |
|---|---|---|
| 01 | `execucao` `npm run test -- card` | `contem` `tests/js/card-anatomia.test.tsx` → `GUARDA de default` · `contem` `.github/workflows/card-anatomia-gate.yml` → `test:card-anatomia` |
| 02 | `execucao` `npm run test -- KpiCard` | `contem` `tests/js/kpicard-variant-filter.test.tsx` → `guarda do default` |
| 03 | `execucao` `npm run test -- Toolbar` | `contem` `tests/js/toolbar.test.tsx` → `guarda: PageFilters segue intacto` |
| 04 | `execucao` `npm run test -- datatable-density` | `contem` `tests/js/datatable-density.test.tsx` → `guarda — sem \`density\` nada muda` (a prova `arquivo` do teste já existe) |
| 05 | `execucao` `npm run test -- statusbadge-kinds` | `contem` `tests/js/statusbadge-kinds.test.tsx` → `guarda — kinds que já existiam não mudam` (a prova `arquivo` do teste já existe) |

Conferi os seis padrões por `grep` no `main`: todos casam hoje. O JSON completo está na §7. Com essa troca, as cinco threads
passam a `feito` — os `_saida-01..05.md` já existem.

## 5 · Achado fora do escopo das threads — 4 testes não rodam no CI

Só o `card-anatomia.test.tsx` tem lane (`card-anatomia-gate.yml`). Os outros quatro não aparecem
em nenhum workflow (`git grep` pelo nome do arquivo em `.github/`: 0 resultados para cada um), e
nenhuma lane roda `vitest run` sem apontar o arquivo. Na prática, a guarda de "default
inalterado" desses átomos — que protege as telas consumidoras de Backup, Financeiro, Ponto e as
11 do `DataTable` — só roda quando alguém a chama à mão:

- `tests/js/kpicard-variant-filter.test.tsx`
- `tests/js/toolbar.test.tsx`
- `tests/js/datatable-density.test.tsx`
- `tests/js/statusbadge-kinds.test.tsx`

As threads não pediam lane, então isto não reabre nenhuma delas. Fica como trabalho separado:
uma lane advisory por teste (o molde é o `card-anatomia-gate.yml`, com a entrada no
`gates-registry.json` no mesmo commit).

## 6 · Placar depois — medido com a troca aplicada

Apliquei a §7 só no working tree (sem commit), rodei `node scripts/qa/placar.mjs --indice` e
restaurei o índice com `git restore` (`git status` limpo depois). Resultado:

```
ds-atomos: entregue 7 de 7 · próximo 0 · em curso 0 · pendente 0 · bloqueada 0
  01 [feito    ] ui/card.tsx — badge · note · flush
  02 [feito    ] shared/KpiCard.tsx — variant=filter
  03 [feito    ] shared/Toolbar.tsx — CRIAR (3 zonas)
  04 [feito    ] shared/DataTable.tsx — density="dense" aditivo (D-GRADE: servidor)
  05 [feito    ] shared/StatusBadge.tsx — kinds sla · frescor · atendimento + rel/tone (tokens --sla-*/--canal-* existentes)
  06 [feito    ] Widget/CardTitle: nível de título (as h2|h3) + ícone warn anônimo
  08 [feito    ] StatusBadge: tirar o fill sólido (AP7) — decisão [W] 2026-09-01
```

Antes: `entregue 2 de 7` (§1). Neste PR o placar real segue em 2 de 7, porque o índice não muda
aqui — ele muda quando o Cowork aplicar a §7 e o retorno for importado.

## 7 · O array `provas` de cada thread, como deve ficar

Gerado por script a partir do índice: tira só o objeto `"tipo": "execucao"` e acrescenta as
provas da §4. As demais provas de cada thread ficam como estão. É este o JSON que produziu o
placar da §6.

```json
{
  "01": [
    {
      "tipo": "contem",
      "path": "${UI}/card.tsx",
      "padrao": "badge"
    },
    {
      "tipo": "contem",
      "path": "${UI}/card.tsx",
      "padrao": "flush"
    },
    {
      "tipo": "contem",
      "path": "tests/js/card-anatomia.test.tsx",
      "padrao": "GUARDA de default"
    },
    {
      "tipo": "contem",
      "path": ".github/workflows/card-anatomia-gate.yml",
      "padrao": "test:card-anatomia"
    }
  ],
  "02": [
    {
      "tipo": "contem",
      "path": "${SH}/KpiCard.tsx",
      "padrao": "filter"
    },
    {
      "tipo": "arquivo",
      "path": "resources/js/Pages/Backup/Index.tsx",
      "guarda": true
    },
    {
      "tipo": "contem",
      "path": "tests/js/kpicard-variant-filter.test.tsx",
      "padrao": "guarda do default"
    }
  ],
  "03": [
    {
      "tipo": "arquivo",
      "path": "${SH}/Toolbar.tsx"
    },
    {
      "tipo": "contem",
      "path": "${SH}/Toolbar.tsx",
      "padrao": "right"
    },
    {
      "tipo": "arquivo",
      "path": "${SH}/PageFilters.tsx",
      "guarda": true
    },
    {
      "tipo": "contem",
      "path": "tests/js/toolbar.test.tsx",
      "padrao": "guarda: PageFilters segue intacto"
    }
  ],
  "04": [
    {
      "tipo": "contem",
      "path": "${SH}/DataTable.tsx",
      "padrao": "density"
    },
    {
      "tipo": "arquivo",
      "path": "tests/js/datatable-density.test.tsx"
    },
    {
      "tipo": "contem",
      "path": "tests/js/datatable-density.test.tsx",
      "padrao": "guarda — sem `density` nada muda"
    }
  ],
  "05": [
    {
      "tipo": "contem",
      "path": "${SH}/StatusBadge.tsx",
      "padrao": "frescor:"
    },
    {
      "tipo": "nao_contem",
      "path": "${SH}/StatusBadge.tsx",
      "padrao": "tipo-pj",
      "guarda": true
    },
    {
      "tipo": "arquivo",
      "path": "tests/js/statusbadge-kinds.test.tsx"
    },
    {
      "tipo": "contem",
      "path": "${SH}/StatusBadge.tsx",
      "padrao": "--sla-expired"
    },
    {
      "tipo": "nao_contem",
      "path": "${SH}/StatusBadge.tsx",
      "padrao": "canal-email-bg",
      "guarda": true
    },
    {
      "tipo": "contem",
      "path": "tests/js/statusbadge-kinds.test.tsx",
      "padrao": "guarda — kinds que já existiam não mudam"
    }
  ]
}
```
