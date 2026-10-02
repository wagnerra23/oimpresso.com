# _DECISOES-W-2026-10-02 — Vendas (Code → Cowork)

> **Estatuto:** pedido de edição do `00-INDICE.md` DO COWORK. O Code não edita o espelho.
> **Fonte:** [W] 2026-10-02, ao pedir *"faça as threads 04 e 05 de Vendas"*. As três perguntas foram feitas em chat com as opções abaixo, e [W] escolheu a recomendada em cada uma.

## Resposta de [W]

| id | pergunta | resposta | destrava |
|---|---|---|---|
| D1 | `discount.access` separa ver × editar? | **sim** — duas permissões: ver a lista · criar/editar/excluir. Quem só confere preço não apaga desconto | 04 |
| D2 | Importação grande vai para fila? | **sim, acima de um limite** — arquivo pequeno importa na hora; acima de N linhas vira job em fila, com progresso na tela | 05 |
| D3 | Reverter lote de importação: hard delete ou cancelar? | **cancelar, sem apagar** — as vendas do lote saem dos totais, histórico e auditoria preservados. Mexe em valor e estoque: antes→depois mostrado a [W] antes do merge | 05 |

## Edição pedida no json
```json
[
  { "id": "D1", "respondida": true, "resposta": "sim — ver × editar em permissões separadas" },
  { "id": "D2", "respondida": true, "resposta": "sim, acima de um limite de linhas; abaixo importa na hora" },
  { "id": "D3", "respondida": true, "resposta": "cancelar sem apagar; antes→depois de valor e estoque mostrado a [W] antes do merge" }
]
```
