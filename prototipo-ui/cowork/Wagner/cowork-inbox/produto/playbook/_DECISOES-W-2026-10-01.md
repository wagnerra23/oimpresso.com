# _DECISOES-W-2026-10-01 — Produto (Code → Cowork)

> **Estatuto:** pedido de edição do `00-INDICE.md` DO COWORK. O Code não edita o espelho.
> **Fonte:** [W] 2026-10-01, textual: *"Aprove todos"*, em resposta à lista de decisões pendentes dos 10 playbooks do handoff (44).
> **Critério aplicado** (registrado para auditoria): aprovar = a opção que a própria thread já assume no prefixo e na ficha; decisão que não destrava thread fica com a opção conservadora, declarada.

## Resposta de [W]

| id | resposta | destrava |
|---|---|---|
| D1 | **sim**, com os nomes propostos: `variation.*` · `warranty.*` · `print_labels.access` | 01 |
| D2 | **a distribuição proposta**: Balcão = `*.view` + `print_labels.access`; Gerente = tudo menos `*.delete`; importação e preço em lote **continuam** com `product.create` | 01 |
| D3 | **Page parametrizada** (`Produto/Cadastros` com abas — é a premissa da thread 02) | 02 |

## SEGUEM ABERTAS — pedem fato ou direção de produto, "aprovar" não responde
| id | por quê |
|---|---|
| D4 | modelo **real** da folha de etiqueta (marca/medidas) — é dado do piloto, não escolha |
| D7 | rótulos **reais** dos campos personalizados 1–7 do piloto — idem |
| D5 | análises de produto: entra / BI / sai — sem opção-padrão no playbook |
| D6 | sugestão de compra: rascunho em Compras / exportar lista — idem |

## Edição pedida no json
```json
[{ "id": "D1", "respondida": true, "resposta": "sim, nomes propostos" },
 { "id": "D2", "respondida": true, "resposta": "distribuição proposta; lote segue product.create" },
 { "id": "D3", "respondida": true, "resposta": "Page parametrizada Produto/Cadastros" }]
```
