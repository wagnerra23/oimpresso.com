# 2026-05-14 — Pivot governança: fecha CYCLE-05 + abre CYCLE-06 alinhado ao trabalho real

> **Sessão curta de governança** (worktree filho `musing-wilbur-3da897`) — não tocou código de produção. Preparou os blocos copy-paste pra Wagner disparar `cycles-close` + `cycles-create` no harness principal.

## Por que pivotar

Brief #51 de hoje 14h sinalizou cycle drift 100%:

- **CYCLE-05** ativo nomeado "Inter PJ prod + WhatsApp governança" · 9d restantes
- **18/18 PRs** dos últimos 7d **NÃO** tocaram nenhum dos 2 goals originais
- Goals originais ainda não-tocados: Inter PJ (bloqueado lado banco "Aplicações não existe"), WhatsApp FICHA v2 + audit shell (superado pelas Ondas 1-3)
- Trabalho real foi excelente — só não cabia no rótulo

## Diagnóstico (1 linha)

O cycle não estava errado — o **rótulo** estava. Sem `cycles-close` formal, brief continuou mentindo e HITL ficou irrelevante.

## O que CYCLE-05 entregou de verdade (últimos 7d)

| Item | PRs | Sinal qualificado |
|---|---|---|
| FSM pipeline LIVE biz=1 ([ADR 0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) | #618-#706+ (11 agents · 4 waves) | sim — fundação técnica destrava Sells/Repair em todos verticais |
| Jana V2 amendment block renderer + protótipo navegável | #838/#839 | parcial — sem cliente pagando ainda |
| Boletos F3 visual Cockpit V2 | #845 | sim — Larissa/ROTA LIVRE usa |
| Fluxo de caixa projetado US-FIN-014 | #838 | sim — pedido por dor cliente |
| WhatsApp Ondas 1-3 (Grafana + OTel + history.sync 90d anti-burst + circadian) | #823/#825/#827/#828/#831-#836 | sim — destravou pareamento real |
| Martinho Caçambas em prod biz=164 (Wave A + 11 users + 18.845 contacts + 44k tx) | maratona 2026-05-14 (handoff 18:00) | **🎉 Jair endossou · Kamila pausou Highsoft** |
| Cleanup módulos prototipos (Grow, IProduction removed) | #842 | sim — higiene |
| ComVis revert (4 P0 órfãs cancelled) | #804/#806 | correto — sem sinal qualificado (ADR 0105) |
| Inter PJ Banking canary | ❌ 0 PRs | bloqueado externo |
| WhatsApp FICHA v2 + audit shell | ❌ 0 PRs | superado |

## Proposta CYCLE-06

| Campo | Valor |
|---|---|
| **Slug** | `CYCLE-06-martinho-fsm-jana-v2` |
| **Nome** | Martinho prod + FSM rollout + Jana V2 demo |
| **Duração** | 14d (até ~2026-05-28) |
| **WIP Wagner** | 2 (força foco) |

| ID | Goal | Métrica |
|---|---|---|
| G1 | **Martinho Caçambas em produção paga** (1º OficinaAuto pago — sinal qualificado ADR 0105) | venda paga registrada biz=164 + 1 OS no Kanban Producao Oficina + NFSe emitida |
| G2 | **FSM rollout 162 vendas legadas biz=1** | `fsm:bulk-start-pipeline 1` executado + `fsm:scan-drift transactions` verde 14d consecutivos |
| G3 | **Jana V2 demo navegável apresentável a 1 cliente piloto** | protótipo V2 deploy staging + 1 demo agendada |
| G4 | **Inter PJ destrava** (opcional — só se banco responder) | Aplicação criada lado Inter OU goal removido do cycle |

**Rollover automático** (3 tasks DOING no CYCLE-05):
- US-OFICINA-014 — Cleanup tools cliente legacy migrado
- US-WA-058 — Múltiplos números por business
- US-COPI-100 — NarrarSaudeEcosistemaJob

## Comandos preparados pra Wagner colar no Claude Code principal

Disponíveis no [handoff de 21:00](../handoffs/2026-05-14-2100-cycle-pivot-05-to-06-martinho-fsm-jana.md) (5 blocos · ordem absoluta 1→2→3→4→5).

## O que NÃO foi feito nesta sessão

- ❌ Disparar `cycles-close` / `cycles-create` — worktree filho sem MCP tools carregadas
- ❌ Tocar produção (DELETE WhatsApp staging + import 90d pendentes — Wagner aprovou escopo biz=1 mas execução fica pra próxima sessão no harness principal)
- ❌ Editar handoffs antigos (append-only ADR 0130)
- ❌ Resolver os 4 HITL detalhadamente (só nomeei top 2: COPI-23 + CMS-1, sugeri fechar como duplicada/feature-wish)

## Lições

1. Cycle goals devem refletir **o que vai acontecer**, não o que pareceu prioritário 14d atrás
2. Pivots informais sem `cycles-close` geram brief mentindo + HITL irrelevante
3. 4 waves × 11 agents paralelos do CYCLE-05 → pattern canônico (validado em [how-trabalhar.md](../how-trabalhar.md) §Paralelização)
4. Worktree filho sem `vendor/` é limitado a documentação/preparação — execução de tools MCP fica no harness principal

## Próximo passo

Wagner cola os 5 blocos no Claude Code principal (`D:\oimpresso.com`) → brief de 2026-05-15 8h vai refletir CYCLE-06 alinhado.
