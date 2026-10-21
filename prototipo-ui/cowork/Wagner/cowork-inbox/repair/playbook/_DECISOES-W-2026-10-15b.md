---
sessao: "decisoes-w"
titulo: "Repair — D-APROVAR: aprovar ou recusar orçamento pelo link"
executor: "[CC]"
data: 2026-10-15
base: wagnerra23/oimpresso.com@main 93ff8a960b74
---
# _DECISOES-W-2026-10-15b — Repair (2ª rodada do dia)

> **Fonte:** [W] 2026-10-15, no chat: *"pode fazer aprovado"*. Já aplicado no `00-INDICE.md`.

| id | pergunta | resposta | destrava | UC |
|---|---|---|---|---|
| D-APROVAR | O cliente pode aprovar ou recusar o orçamento pelo link? | **Sim.** Sem valor na página (ADR ARQ-0002 mantida). Ligado por padrão; a loja pode desligar. | 08 | UC-COS-28 |

## Efeito
- **Charter do ConsultaOs:** o Non-Goal "NÃO permite editar/cancelar (read-only)" ganha uma exceção escrita: *uma decisão de orçamento por OS, pelo código, sem valor na página*. O Anti-hook "NÃO grava nada em GET" segue: a decisão é POST.
- **UC-COS-28** sai de `could` para `must`. **UC-COS-06** ("só leitura") precisa de uma linha: as rotas públicas de GET continuam só GET; a rota nova de decisão é POST, com throttle e uma decisão por orçamento.
- Thread 08 destravada (depende da 06).
