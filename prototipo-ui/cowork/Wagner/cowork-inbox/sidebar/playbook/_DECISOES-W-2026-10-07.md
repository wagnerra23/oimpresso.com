# _DECISOES-W-2026-10-07 — Sidebar (Cowork → Code)

> **Fonte:** [W] 2026-10-07, textual: *"faça todos"* — em resposta à pergunta da `_ERRATA-INDICE-2026-10-07.md` (trocar a prova de recibo por estrutural, ou esperar o avaliador). Escolha do [CC] sob essa delegação.

## D-PROVA · prova estrutural nas 13 threads

**Resposta: aceitar a troca** (já aplicada no `00-INDICE.md`). O placar passa a decidir; a execução dos testes fica provada pelas lanes `cockpit-sidebar-jsdom-gate` e sqlite, citadas no recibo de cada thread.

Por quê: esperar o avaliador de recibo deixa 16 threads entregues como "em curso" por tempo indefinido, e o placar perde valor como sinal. A fraqueza (`arquivo` prova que o teste existe, não que passou) fica coberta porque cada teste citado tem lane própria em PR.

Não decidido aqui: portar de volta o avaliador de recibo (vale para todos os playbooks) — fica como pergunta aberta, sem thread.

## Edição pedida no json
Nenhuma além da errata — já aplicada.
