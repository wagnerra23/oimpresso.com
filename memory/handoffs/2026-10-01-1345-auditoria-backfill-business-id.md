---
date: "2026-10-01"
time: "13:45 BRT"
slug: auditoria-backfill-business-id
tldr: "Backfill do activity_log.business_id NULL concluído em produção: núcleo já aplicado antes desta sessão, mais 6.322 linhas de outros módulos preenchidas e 146 logs de licença limpos para NULL, com aprovação [W]. A conferência deu 0 divergentes. Registrado no BRIEFING da Auditoria (#8440)."
decided_by: [W]
cycle: null
prs: [8406, 8440]
us: []
next_steps:
  - "Opcional: investigar os 16 NULL que a contagem direta enxerga e o comando não (93.248 x 93.232); a hipótese é subject_type vazio, não verificada."
related_adrs: ["0093-multi-tenant-isolation-tier-0", "0127-modules-auditoria-undo-activity-log"]
---

# Handoff 2026-10-01 13:45 BRT — backfill do business_id NULL no activity_log

## TL;DR

O `auditoria:backfill-business-id` (#8406) foi rodado em produção e o resultado conferido. Um novo dry-run dá 0 resolvíveis, e o que segue NULL é por desenho (licenças da plataforma, tipos da Jana sem tenant, registros apagados).

## Cronologia desta sessão

| Quando (UTC) | Evento |
|---|---|
| ~12:40 | #8406 ainda aberto: parei no passo 1, sem executar nada |
| 12:49 | #8406 mergeado (`4399254d94e`); outra sessão avisou |
| ~13:00 | Hostinger HEAD `e7420c15fcf` contém o merge; o comando aparece no `artisan list` |
| ~13:05 | Dry-run: 99.408 NULL, 6.322 resolvíveis. O núcleo já tinha sido aplicado antes; o que sobrou NULL bate com o "não resolvível" do corpo do PR |
| ~13:10 | Conferência do núcleo: 30.619 logs, 0 divergentes |
| ~16:30 | Ok [W] ("sim 1 e 2"): `--type='App\Unit' --apply` (30 linhas, 0 divergentes), depois o `--apply` geral (6.292 linhas + 146 licenças limpas) |
| ~16:35 | Pós-apply: 40.284 logs conferidos, 0 divergentes; 0 licenças com tenant; dry-run com 0 resolvíveis |
| 16:44 | #8440 (BRIEFING) mergeado (`c66180be76f`) |

## PRs

| PR | Status | Conteúdo |
|---|---|---|
| #8406 | merged (outra sessão) | comando `auditoria:backfill-business-id` |
| #8440 | merged | BRIEFING da Auditoria com o desfecho datado |

## Decisões tomadas

| Pergunta | Decisão [W] | Referência |
|---|---|---|
| Limpar as licenças que vazaram com tenant? | Sim (item 1) | corpo do #8406 |
| Preencher as classes fora do núcleo (6.322)? | Sim (item 2), começando por `App\Unit` | esta sessão |

## Estado MCP no momento do fechamento

- `cycles-active`: nenhum cycle ativo em COPI.
- `my-work`: sem tasks ativas para @wr23.
- `sessions-recent limit:3`: os três últimos são de 2026-09-16 (token/worktrees, pares/revogação, protocolo do último importado); nenhum toca a Auditoria.

## Bloqueios / pendências

- [ ] 16 NULL que o comando não conta (93.248 na contagem direta × 93.232 no comando). Hipótese: `subject_type` vazio, não aberta. Owner: qualquer pessoa, baixa prioridade.
- Quem rodou o `--apply` do núcleo não ficou registrado nesta sessão. O resultado foi conferido e está correto.

## Próximos passos (ordem)

1. Nada obrigatório. O backfill está fechado.
