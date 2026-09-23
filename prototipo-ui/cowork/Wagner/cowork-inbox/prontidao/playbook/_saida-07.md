---
sessao: "07"
titulo: Scorecard · Essentials (Metas, Tipos)
executor: "[CL]"
base: 317e1b4ec33
---
# _saida 07

## Checklist
1. ✅ Pré-Flight: as 2 telas têm charter (`Metas.charter.md`, `Tipos.charter.md`) e casos.md ao lado — PARAR SE "falta charter" **não** acionou
2. ✅ Nota 16-dim por tela, cada dimensão com evidência `arquivo:linha`
3. ✅ YAML com o slug exato da espec · parseia (js-yaml) · 16 dimensões · `baseline_anterior` = nota
4. ✅ Só o prefixo tocado (2 YAMLs + este `_saida`) · `.tsx` e charter intactos · zero git
5. ⏭ E2E, axe e smoke (passos 2-4 do agente) fora, como a espec manda

## Notas — **de leitura de código, sem browser**

Nenhuma dimensão foi observada em runtime. A nota vem de `.tsx` + charter + casos + controller + teste.
Agregação: média simples das 16 dimensões (sem peso de persona aplicado). Persona: `kamila-martinho`
(`personas-por-modulo.yml:77-78`, essentials.primary).

| tela | arquivo | arquétipo | nota | nível | dim. mais baixa |
|---|---|---|---|---|---|
| `Essentials/Metas` | `memory/governance/scorecards/screens/essentials-metas.yaml` | list | **76** | Advanced | performance_perceived 40 · error_recovery 55 |
| `Essentials/Tipos` | `memory/governance/scorecards/screens/essentials-tipos.yaml` | list | **75** | Advanced | performance_perceived 40 · internal_consistency 68 |

## O achado que pesa nas duas — HIPÓTESE forte, não medida em prod

As duas telas têm a assinatura da lápide §5 2026-09-08 (*skeleton eterno, CI verde*):

- prop deferida — `SalesTargetController.php:85` (`paginator`) e `EssentialsLeaveTypeController.php:75` (`tipos`);
- ramo legado `if (request()->ajax())` **sem** `&& ! request()->inertia()` — `SalesTargetController.php:48` e `EssentialsLeaveTypeController.php:56`;
- o cliente Inertia instalado manda `X-Requested-With: XMLHttpRequest` em toda visita (`node_modules/@inertiajs/core/dist/index.js:2955`, v3.6.1, lido no checkout principal);
- e os testes do partial reload montam os headers **sem** `X-Requested-With` (`HrmMetasTest.php:160-163`, `HrmTiposIndexTest.php:90-93`) — logo passam verdes mesmo se a tela ficar presa no skeleton.

A guarda correta é padrão da casa (`EssentialsLeaveController.php:95`). **Não confirmei em runtime** — sem browser nesta thread. Próximo passo antes de qualquer conserto: smoke biz=1 em `/hrm/sales-target` e `/hrm/leave-type`. Se confirmar, as duas telas não mostram a lista em prod, e a espec manda registrar isso aqui.

Segundo achado (só Metas): faixa recusada pelo validador volta como redirect (`SalesTargetController.php:210`), o Inertia trata como sucesso, `onSuccess` fecha o diálogo (`Metas.tsx:250`) e o erro sai só no toast — o que foi digitado se perde.

## Máquinas

`node scripts/qa/prototipo-readiness.mjs` (sem `--json`) → RC=0:
```
  ✅ PRONTAS pra aplicar HOJE (trio + casos+UC + scorecard trava o comportamento): 61
       ...
       [core] Essentials/Metas
       [core] Essentials/Tipos
  🟡 PRECISAM DE 1 CICLO de blindagem antes (o metabolismo MV faz): 33
  Total de telas com protótipo real: 94
```

`node scripts/qa/screen-grades-ratchet.mjs` → RC=0:
```
Catraca screen-grade · 194 telas · ✅ 192 ok/subiu · ✨ 2 novas · 🔻 0 regrediram · 🗑 0 deleção(ões) legítima(s)
✓ CATRACA: nenhuma tela regrediu.
```

## PARAR SE
- Falta charter: **não acionou**.
- Tela não abre em prod: **não medido** (sem browser). Há suspeita concreta por leitura (acima) — registrada, não resolvida. Gaps viram task só com [W].
