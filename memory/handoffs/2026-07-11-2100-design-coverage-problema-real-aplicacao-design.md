---
date: "2026-07-11"
topic: "Handoff — design coverage + wave-1 PT + descoberta do problema real (aplicação integral do design) + 7 chips"
authors: [C, W]
---

# Handoff 2026-07-11 21:00 — design→código: o que landou, o problema real, e os 7 chips

> Append-only. Sessão longa (fechou o loop de tokens, construiu a camada de cobertura de design, e no fim descobriu que o problema real é FIDELIDADE, não governança). Narrativa completa: [session log 2026-07-11](../sessions/2026-07-11-design-coverage-adversario-e-problema-real.md).

## Landou no main
- ADR 0335 (loop diff-first tokens, nota honesta) · gates `design-coverage`/`pt-conformance`/`ds-tokens-build-sync`/selftests · **63 telas com Padrão de Tela declarado+verificado** (wave 1, PRs #4104/#4105/#4106/#4108/#4109). Cobertura de design 18→81 (56%).

## O PROBLEMA REAL (o que importa pra próxima sessão)
O Wagner tem **sinal de cliente pago** (ADR 0105): cliente rejeita a tela feia, **aprova o design** ("essa eu uso"). A dor NÃO é governança — é **aplicação integral do design** + **detecção de drift protótipo↔código** ("a máquina não sabe que alterou"). Ele **não confia** em auto-aplicação (quebra) → quer **detecção + documentação**, não Claude editando telas. Isto **supera** boa parte da infra de governança construída hoje. Ler o session log §"Problema real".

## Lição perene registrada
Auto-nota do Claude **infla ~1 grau** — adversário pegou 3× nesta sessão. Rodar o adversário na NOTA (não só no código) antes de canonizar superioridade.

## Chips abertos (rodando em sessões separadas)
- **task_8af136bc** — respeitar o protótipo (detecção drift protótipo↔código + doc) — **o mais importante**.
- task_6c0b1541 máquina nascer-consistente · task_5628d208 4 goldens draft · task_fe4154b3 138 charters · task_11404082 43 live (route-hits) · task_96af0712 gates→required/deletar · task_a53b0dd0 7 atípicas + typo.

## Estado MCP no fechamento (do brief #338, ~14 min antes)
- Cycle: — · HITL pending Wagner: 2 (FIN-004 Atualizar cobrança ROTA LIVRE · runbook on-prem). Brain B 0%. ADRs recentes 24h: 0335 (=4412 no gerador), 0334. Sem incidentes.
- NÃO rodei o checklist MCP-first completo (sessão em worktree temp, fim de dia). Estado vivo real: chamar `brief-fetch` + `my-work` na próxima.

## Próximo passo sugerido
NÃO dispersar nos chips de infra. Focar no **problema real**: pegar UMA tela que o cliente rejeitou + o design aprovado dela, e (via chip task_8af136bc) construir a DETECÇÃO de fidelidade protótipo↔código — nunca auto-aplicação. O que paga é o cliente usar a tela fiel ao design.
