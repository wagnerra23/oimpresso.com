---
title: "Índice de Planos Vivos"
owner: W
reviewed_at: "2026-06-20"
generated: false   # v1 escrito à mão; deve virar gerado (ver §Como manter vivo)
related_adrs: ["0256-knowledge-survival-meia-vida-catraca-sentinela", "0070-jira-style-task-management-current-md-removed", "0130-handoff-append-only-mcp-first"]
---

# Índice de Planos Vivos

> **O que é:** registro fonte-única de **todos** os PLANOs estratégicos do projeto, com status real, frescor e ligação com a execução (MCP). Resolve "meus planos estão se perdendo": um lugar só pra ver o que está vivo, parado, ou morto sem ninguém ter avisado.
> **Princípio:** plano é artefato de 1ª classe na máquina de sobrevivência ([ADR 0256](../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)). Execução mora no MCP, não no markdown ([ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md)).
> **Legenda status:** `proposto` (escrito, não aprovado) · `ativo` (aprovado, sem execução corrente) · `em-execução` · `pausado` · `concluído` · `abandonado` · `superseded`.

## 🚩 Saúde (snapshot 2026-06-20)

- **15 planos** · `reviewed_at` preenchido: **1/15** (este índice é o 1º a marcar) · vinculados a task MCP (`parent_plan`): **0/15**.
- **Achado:** nenhum plano declara `reviewed_at` e nenhum liga formalmente ao backlog — por isso "se perdem". Ações de triagem na coluna 🚩.
- **A revisar com urgência:** 4 planos com estado provavelmente desatualizado (migração auto-mem, demo Martinho, conciliação Fase 1/2) — confirmar se já concluíram e arquivar.

## Registro

| Plano | Módulo | Status | Owner | Última data | reviewed_at | Tasks MCP | 🚩 Próxima ação |
|---|---|---|---|---|---|---|---|
| [PLANO-ATENDIMENTO-AUTOMATICO](../Whatsapp/PLANO-ATENDIMENTO-AUTOMATICO.md) | Whatsapp | proposto | W | 2026-06-20 | 2026-06-20 | — | aprovar ordem ROI (E1+E2) e criar tasks `parent_plan` |
| [JANA-PRO-PRODUCT-PLAN](../Copiloto/JANA-PRO-PRODUCT-PLAN.md) | Copiloto | ativo (aprovado) | W | 2026-05-11 | nunca | — | é o E1 do plano acima; iniciar JANA-A ou marcar pausado |
| [PLANO_DETALHADO](../Financeiro/PLANO_DETALHADO.md) | Financeiro | em-execução | W | — | nunca | — | "Onda 1 quase fechada" — fechar categorias/plano-contas ou marcar concluído |
| [PLANO-ONDA5-SIMPLIFICADA](../PaymentGateway/PLANO-ONDA5-SIMPLIFICADA.md) | PaymentGateway | em-execução | W | — | nunca | — | status diz "executado-aguardando-smoke" → rodar smoke e fechar |
| [ONDA-1-VENDAS-PDV-CAIXA-PLANO](../Mwart/ONDA-1-VENDAS-PDV-CAIXA-PLANO.md) | Mwart | em-execução (F1) | W | — | nunca | — | confirmar fase MWART atual vs doc |
| [PLANO-FASE1-CONCILIACAO-LE-EXTRATO-API](../Financeiro/PLANO-FASE1-CONCILIACAO-LE-EXTRATO-API.md) | Financeiro | revisar | W | 2026-05-31 | nunca | — | ADR 0236 proposto — confirmar se executou; risco de colisão ADR 0236/0246 |
| [PLANO-FASE2-MIGRACAO-EXTRATO-UNIFICADO](../Financeiro/PLANO-FASE2-MIGRACAO-EXTRATO-UNIFICADO.md) | Financeiro | proposto | W | 2026-05-31 | — | ADR 0236 aceita — agendar ou pausar |
| [PLAN-MWART-metas](../Jana/PLAN-MWART-metas.md) | Jana | proposto | W | 2026-05-09 | nunca | — | "draft aguardando aprovação" há ~6 semanas → decidir ou abandonar |
| [PLANO-TESTES-FISCAL](../Fiscal/PLANO-TESTES-FISCAL.md) | Fiscal | proposto (draft) | W | — | nunca | — | promover a ativo ou arquivar |
| [PLANO-MIGRACAO-VARGAS](../Autopecas/PLANO-MIGRACAO-VARGAS.md) | Autopecas | proposto (draft) | W | 2026-05-10 | nunca | — | depende de sinal de cliente (ADR 0105) |
| [PLANO-MIGRACAO-6-SAUDAVEIS](../ComunicacaoVisual/PLANO-MIGRACAO-6-SAUDAVEIS.md) | ComunicacaoVisual | proposto (draft) | W | 2026-05-10 | nunca | — | depende de sinal de cliente (ADR 0105) |
| [DEPRECATION-PLAN (SRS)](../SRS/DEPRECATION-PLAN.md) | SRS | proposto | W | 2026-05-17 | nunca | — | decidir deprecação SRS |
| [DEPRECATION-PLAN (Accounting)](../Accounting/DEPRECATION-PLAN.md) | Accounting | proposto | W | 2026-05-20 | nunca | — | decidir deprecação Accounting |
| [PLANO-MIGRACAO-AUTOMEM](../Infra/PLANO-MIGRACAO-AUTOMEM.md) | Infra | revisar (provável concluído) | W | — | nunca | — | migração auto-mem rodou 2026-05-13 → confirmar e marcar `concluído` |
| [plano-paralelizacao (demo Martinho)](../OficinaAuto/demo-martinho-2026-05-13/plano-paralelizacao.md) | OficinaAuto | revisar (demo passou) | W | 2026-05-13 | nunca | — | demo de 2026-05-13 passou → marcar `concluído` e arquivar |

## A seção `## Status vivo` (em cada plano)

Pra um plano entrar/permanecer neste índice, ele carrega no topo:

```
## Status vivo
status: <enum acima>
owner: W
criado: YYYY-MM-DD · reviewed_at: YYYY-MM-DD · próxima-revisão: YYYY-MM-DD
cycle: CYCLE-NN · execução: parent_plan=<slug-do-plano>   # liga às tasks MCP
gate-de-saída (DoD): <quando este plano fecha>
kill-condition: <quando abandona>
verdade-viva: (este doc)   # se superseded → link pro novo
```

`reviewed_at` (frescor) + `execução=parent_plan` (% real via MCP) + `gate-de-saída` + `kill-condition` são os 4 campos que matam o "se perdendo".

## Como manter vivo (3 máquinas — ADR 0256)

1. **Gerado, não à mão** — este índice deve virar saída de um gerador `plans-index` (mesmo padrão do índice de ADR / modelo Log4brains), lendo o `## Status vivo` de cada plano. Fonte-única: o plano é a verdade, o índice é derivado.
2. **Sentinela `plan-health`** — estende `scripts/governance/memory-health.mjs` (ADR 0256 Onda 1): flaga `status` ausente, `reviewed_at` > 30d, plano sem `parent_plan` (órfão), **drift** (status ≠ realidade das tasks), `superseded` sem ponteiro. Sai no **Daily Brief** + gate advisory no CI pra `requisitos/**/*PLAN*.md`.
3. **Cadência** — revisão mensal (skill `brief-update` estendida): poda/funde/fecha/atualiza `reviewed_at`. Catraca: fechar/abandonar exige mudar `status` com motivo.

## Formalizar

ADR estendendo a [0256](../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md) ("Plano como artefato vivo") + template `## Status vivo` aqui em `_processo/` + generalizar `parent_audit → parent_plan` (skill `audit-to-backlog`). É mudança de convenção de processo → entra via ADR + aprovação [W].

## Refs

- [ADR 0256](../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md) Knowledge Survival · [ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md) Tasks no MCP · [ADR 0130](../../decisions/0130-handoff-append-only-mcp-first.md) Handoff append-only · [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) Cliente como sinal
- Skills: `brief-update` · `audit-to-backlog` · `sync-mem`
