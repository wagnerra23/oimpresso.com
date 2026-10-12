---
date: "2026-10-09"
time: "15:22 BRT"
slug: notificacoes-baseline-fixtures
tldr: "Sistema/06: baseline corrigido em fixtures e ligado à lane MySQL; CT 100 mediu 20 passes, 52 assertions e dois todo."
---

# Modelos de notificação — baseline

## Estado MCP no momento do fechamento
whats-active havia avisado ingest sem heartbeat fresco; my-work não tinha task ativa. Foi usado worktree próprio; o checkout staging sujo não foi atualizado. Nenhuma task foi fechada.

## Prova e próxima etapa
Commit 2bebc37623 passou no CT 100 isolado com 20 testes e 52 assertions. Os dois todo não foram promovidos. A lane acessos-pest recebeu o baseline e seus caminhos de código. Aguardar CI e merge deste recorte e F1 (#9105), depois preparar ação dual com flag OFF. Page/cutover e Contas não foram entregues neste baseline.
