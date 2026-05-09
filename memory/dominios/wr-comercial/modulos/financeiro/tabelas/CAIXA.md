---
table: CAIXA
module: financeiro
created_at_version: 11
last_modified_version: 1195
target_version: 1468
columns_count: 4
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CAIXA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 11;
- **Última mudança:** UPDATE 1195;
- **Total colunas (versão 1468):** 4

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `DESCONTO_VENDAS` | `DOUBLE PRECISION` | NULL |  | v11 | v11 |
| 2 | `SANGRIA` | `DOUBLE PRECISION` | NULL |  | v11 | v11 |
| 3 | `DT_ALTERACAO` | `timestamp` | NULL |  | v168 | v168 |
| 4 | `VALOR_CREDITO` | `DOUBLE PRECISION` | NULL |  | v1195 | v1195 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 11 | ADD_COL | + DESCONTO_VENDAS DOUBLE PRECISION |
| 11 | ADD_COL | + SANGRIA DOUBLE PRECISION |
| 71 | DROP_COL | - TOTAL_DINHEIRO |
| 71 | DROP_COL | - TOTAL_CHEQUE |
| 71 | DROP_COL | - TOTAL_BOLETO |
| 71 | DROP_COL | - TOTAL_CARTAODECREDITO |
| 71 | DROP_COL | - TOTAL_CARTAODEDEBITO |
| 71 | DROP_COL | - TOTAL_CREDIARIO |
| 71 | DROP_COL | - TOTAL_DEPOSITO |
| 71 | DROP_COL | - TOTAL_NOTASIMPLES |
| 71 | DROP_COL | - TOTAL_NOTAPROMISSORIA |
| 71 | DROP_COL | - TOTAL_PERMUTA |
| 71 | DROP_COL | - TOTAL_CREDITO |
| 71 | DROP_COL | - TOTAL_CARTEIRA |
| 168 | ADD_COL | + DT_ALTERACAO timestamp |
| 1195 | ADD_COL | + VALOR_CREDITO DOUBLE PRECISION |

