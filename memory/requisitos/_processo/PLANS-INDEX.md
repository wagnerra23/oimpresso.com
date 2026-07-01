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
> **Legenda status:** `proposto` (escrito, não aprovado) · `ativo` (aprovado, sem execução corrente) · `em-execução` · `pausado` · `concluído` · `abandonado` · `superseded` · `revisar` (estado provavelmente desatualizado — triar).

## 🚩 Saúde (snapshot 2026-06-20)

- **16 planos** · `reviewed_at` no frontmatter: **1/16** (só o SDD, revisado 2026-06-20 pela avaliação adversarial 30-threads) · vinculados a task MCP (`parent_plan`): **0/16** (o SDD liga via US-GOV-016/017/018, não via `parent_plan` formal).
- **Achado:** nenhum plano declara `reviewed_at` e nenhum liga formalmente ao backlog — por isso "se perdem". Ações de triagem na coluna 🚩.
- **A revisar com urgência:** 3 planos com estado provavelmente desatualizado (migração auto-mem, demo Martinho, conciliação Fase 1) — confirmar se já concluíram e arquivar.

## Registro

| Plano | Módulo | Status | Owner | Última data | reviewed_at | Tasks MCP | 🚩 Próxima ação |
|---|---|---|---|---|---|---|---|
| [Reestruturação SDD (ondas paralelas)](../../sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md) | Governance | em-execução | W | 2026-06-12 | 2026-06-20 | US-GOV-016/017/018 | **programa mais ativo do projeto.** Revisão adversarial 30-threads (2026-06-20): composto **65/100**, ~2/3 construído mas **0 gates SDD required**. Caminho crítico: transporte floor CT100→main + pcov. Ver session log `../../sessions/2026-06-20-sdd-avaliacao-30threads.md` (PR #3066) |
| [PLANO-ATENDIMENTO-AUTOMATICO](../Whatsapp/PLANO-ATENDIMENTO-AUTOMATICO.md) | Whatsapp | proposto | W | 2026-06-20 | — | — | aprovar ordem ROI (E1+E2) e criar tasks `parent_plan` |
| [JANA-PRO-PRODUCT-PLAN](../Jana/JANA-PRO-PRODUCT-PLAN.md) | Jana | ativo (aprovado) | W | 2026-05-11 | nunca | — | é o E1 do plano acima; iniciar JANA-A ou marcar pausado |
| [PLANO_DETALHADO](../Financeiro/PLANO_DETALHADO.md) | Financeiro | em-execução | W | — | nunca | — | "Onda 1 quase fechada" — fechar categorias/plano-contas ou marcar concluído |
| [PLANO-ONDA5-SIMPLIFICADA](../PaymentGateway/PLANO-ONDA5-SIMPLIFICADA.md) | PaymentGateway | em-execução | W | — | nunca | — | status diz "executado-aguardando-smoke" → rodar smoke e fechar |
| [ONDA-1-VENDAS-PDV-CAIXA-PLANO](../Mwart/ONDA-1-VENDAS-PDV-CAIXA-PLANO.md) | Mwart | em-execução (F1) | W | — | nunca | — | confirmar fase MWART atual vs doc |
| [PLANO-FASE1-CONCILIACAO-LE-EXTRATO-API](../Financeiro/PLANO-FASE1-CONCILIACAO-LE-EXTRATO-API.md) | Financeiro | revisar | W | 2026-05-31 | nunca | — | ADR `0236-extrato-conciliacao` **aceita** (2026-05-31) — confirmar execução; nº 0236 tem **colisão tripla registrada** (extrato/governanca/scorecard) → citar por slug |
| [PLANO-FASE2-MIGRACAO-EXTRATO-UNIFICADO](../Financeiro/PLANO-FASE2-MIGRACAO-EXTRATO-UNIFICADO.md) | Financeiro | proposto | W | 2026-05-31 | nunca | — | ADR `0236-extrato-conciliacao` aceita — agendar ou pausar |
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
2. **Sentinela em 2 partes** (ambas advisory no agregador `governance-audit`, saem no **Daily Brief**):
   - **`plan-health`** (Node — `scripts/governance/plan-health.mjs`, ADR 0294 **Onda 1**) — checagens **estruturais**, determinísticas, sem rede: `status` ausente/fora-do-enum, `reviewed_at` > 30d, órfão (em-execução **sem** `parent_plan`), `superseded` sem ponteiro, índice dangling, plano sem bloco `## Status vivo`.
   - **`jana:plan-drift`** (PHP — `Modules/Jana/Console/Commands/PlanDriftCommand.php`, ADR 0294 **Onda 2**) — **drift** status-declarado ≠ realidade das tasks MCP. Mora em PHP porque tasks vivem no MCP ([ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md)) e `plan-health` é Node-sem-rede. Flaga: em-execução com 0 tasks (ligação fantasma), em-execução sem nenhuma task em todo/doing (parou?), concluído com tasks abertas, proposto/ativo com tasks abertas (cruzou a membrana sem virar em-execução), e órfão reverso (task aponta pra slug sem plano no índice). **Skip gracioso** se o MCP/DB estiver offline (estava em 2026-06-20). _Não cria_ defeito quando não há o que verificar._

   **Transporte (decisão — ADR 0294 Onda 2):** escolhido **(ii) comando PHP que o agregador chama**, não (i) artisan exporta JSON pro `.mjs` ler. Razão: manter `plan-health` determinístico/sem-rede (um sentinela Node lendo JSON possivelmente velho = o anti-padrão "ghost canary" que a auditoria de sentinelas PR #3098 matou); o PHP lê o DB ao vivo, sem janela de staleness.

   **Contrato `parent_plan`** (alinhado com `feat/backlog-plano-perdido-34`, que materializou ~22 US): slug **kebab-case minúsculo**. Lado **plano** declara `execução: parent_plan=<slug>` no bloco `## Status vivo`. Lado **task** carrega em `custom_fields['parent_plan']` — **alvo canônico**, alimentado pelo `TaskParserService` a partir de uma meta-line `> parent_plan: <slug>` (chave não-canônica → default→`custom_fields`; o parser aceita `:` e `=`). _Coordenação pendente:_ os 22 US do backlog-34 hoje põem `parent_plan` numa **linha de corpo** (`` `parent_plan: <slug>` ``), fora da meta-line `>` — logo **não** chegam em `custom_fields` no sync. O `jana:plan-drift` os resolve por ora via fallback na `description`, mas o fechamento limpo é **mover esses `parent_plan` pra meta-line `>`** (zero mudança de parser).
3. **Cadência** — revisão mensal (skill `brief-update` estendida): poda/funde/fecha/atualiza `reviewed_at`. Catraca: fechar/abandonar exige mudar `status` com motivo.

## Formalizar

ADR estendendo a [0256](../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md) ("Plano como artefato vivo") + template `## Status vivo` aqui em `_processo/` + generalizar `parent_audit → parent_plan` (skill `audit-to-backlog`). É mudança de convenção de processo → entra via ADR + aprovação [W].

## Refs

- [ADR 0256](../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md) Knowledge Survival · [ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md) Tasks no MCP · [ADR 0130](../../decisions/0130-handoff-append-only-mcp-first.md) Handoff append-only · [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) Cliente como sinal
- Skills: `brief-update` · `audit-to-backlog` · `sync-mem`
