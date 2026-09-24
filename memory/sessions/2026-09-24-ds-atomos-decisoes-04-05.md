---
date: "2026-09-24"
topic: "ds-atomos — decisões [W] que destravam as threads 04 (tabela densa) e 05 (StatusBadge kinds)"
authors: ["W", "C"]
---
# ds-atomos · decisões 04 e 05 (2026-09-24)

Base: `origin/main` 4807395dc.

## Placar na abertura
`node scripts/qa/placar.mjs --indice prototipo-ui/cowork/Wagner/cowork-inbox/ds-atomos/playbook/00-INDICE.md`
→ `entregue 1 de 6 · em curso 3 · bloqueada 2`. A 08 está feita (#7849). 01·02·03 aparecem "em curso"
porque a prova `execucao` não tem avaliador (ADR 0397), não por falta de `_saida`.

## D-GRADE (thread 04) — **servidor** ([W] 2026-09-24)
Pergunta: a tabela densa pagina no cliente (DataGrid do bundle) ou segue no servidor (`LengthAwarePaginator`)?

Medido em `4807395dc` (git grep, contado):
- `shared/DataTable.tsx` já pagina no servidor (paginator Inertia + `withQueryString`) — **11** consumidores.
- **66** arquivos PHP (app + Modules, sem testes) chamam `->paginate(`/`->simplePaginate(`.
- **43** Pages leem `last_page`. **128** Pages usam `<table>` cru. `ui/table.tsx` não existe (0 imports; controle positivo `ui/button` = 241).

Decisão: mantém a paginação no servidor. A 04 vira prop **aditiva** `density="dense"` no
`shared/DataTable.tsx` (alvo §Alvo do índice: `th` 11px uppercase `--text-dim`, `td` 12.5px, pad 7/10),
default inalterado. Nenhum controller muda. A densidade é forma; o regime de paginação não muda com ela.

## D-SB-KINDS (thread 05) — **sla + atendimento + frescor, sem token novo** ([W] 2026-09-24)
A 05 estava "não medida". Medida agora:
- Produção (`shared/StatusBadge.tsx`, 15.840 B): **22** kinds. DS (`prototipo-ui/design-system/components/StatusBadge`): **11**.
- Só no DS: `fiscal`, `sla`, `atendimento`, `frescor`, `tipo`. O DS também tem as props `rel` e `tone` (0 na produção).
- `fiscal` fica **fora**: o REGISTRY_DS_COMPONENTES documenta `FiscalStatusBadge` como fonte única do status fiscal (5 consumidores).
- `tipo` (PJ/PF) fica **fora**: exigiria `--color-tipo-pj/-pf`, tokens que não existem no repo (token novo = soberania [W]).
- O espelho do DS ainda pinta preenchimento **sólido**. O AP7 da thread 08 (decisão [W] 2026-09-01) prevalece: os 3 kinds novos entram nos tons soft que o `ui/badge` já tem.
- Uso atual: `FrescorPill` feito à mão em `Cliente/_components/Pills.tsx` = 3 usos (é o consumidor natural de `frescor`).

## Registro
- Índice do Cowork: `D-GRADE.respondida=true`, `D-SB-KINDS` nova e respondida, `bloqueio` de 04/05 trocado para
  "aguarda a ficha". **Não** editado no espelho do repo (ADR 0374). A escrita no Cowork foi bloqueada pelo
  `block-design-sync-without-optin` (não é retorno de canon mergeado, então não isento pela ADR 0412) — pendente de opt-in [W].
- Nenhum `_saida-04/05.md` escrito: `_saida` é o recibo da execução (Lei 2), e a thread não foi executada.
- `/onda` não rodou: 04 e 05 **não** ficam `proximo`. Falta a ficha `NN-*.md` (autoria do Cowork) e o `bloqueio` só sai do índice pelo Cowork.
- 23 recibos `_saida` pendentes subidos ao Cowork (isentos, ADR 0412) e registrados em `state/enviados-cowork.json`.
