# _DECISOES-W-2026-10-01 — Officeimpresso (Code → Cowork)

> **Estatuto:** pedido de edição do `00-INDICE.md` DO COWORK. O Code não edita o espelho.
> **Fonte:** [W] 2026-10-01, textual: *"Aprove todos"*, em resposta à lista de decisões pendentes dos 10 playbooks do handoff (44).
> **Critério aplicado** (registrado para auditoria): aprovar = a opção que a própria thread já assume no prefixo e na ficha; decisão que não destrava thread fica com a opção conservadora, declarada.

## Resposta de [W]

| id | resposta | destrava |
|---|---|---|
| D1 | **aposentar** o painel OAuth do Officeimpresso e redirecionar pro do Connector (o clone expõe `secret` e `/regenerate`) | 05 |
| D2 | **tela nova no Officeimpresso** (`Officeimpresso/Licencas`, prefixo da thread 06) | A1 · 06 |
| D3 | **ProductCatalogue sobrevive** (módulo dono do tema); as 3 views do Officeimpresso redirecionam | 08 |
| D4 | **sim** — dropar `senha`/`contra_senha`, só depois da 02 parar de gravar e por migration | 03 |
| D5 | **fora desta tela** — cobrança por equipamento fica no Financeiro/Superadmin | — |

## Edição pedida no json
```json
[{ "id": "D1", "respondida": true, "resposta": "aposentar, redirecionar pro Connector" },
 { "id": "D2", "respondida": true, "resposta": "tela nova Officeimpresso/Licencas" },
 { "id": "D3", "respondida": true, "resposta": "ProductCatalogue sobrevive" },
 { "id": "D4", "respondida": true, "resposta": "sim, dropar após a 02" },
 { "id": "D5", "respondida": true, "resposta": "fora desta tela (Financeiro/Superadmin)" }]
```
