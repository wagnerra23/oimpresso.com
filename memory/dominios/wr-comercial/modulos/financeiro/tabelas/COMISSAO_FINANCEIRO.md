---
table: COMISSAO_FINANCEIRO
module: financeiro
created_at_version: 12
last_modified_version: 1218
target_version: 1468
columns_count: 22
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `COMISSAO_FINANCEIRO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 12;
- **Última mudança:** UPDATE 1218;
- **Total colunas (versão 1468):** 22

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v12 | v12 |
| 2 | `CODPEDIDO` | `VARCHAR(10)` | NOT NULL | v12 | v12 |
| 3 | `CODEMPRESA` | `VARCHAR(10)` | NOT NULL | v12 | v12 |
| 4 | `CODCOMISSAO` | `INTEGER` | NOT NULL | v12 | v12 |
| 5 | `CODRESPONSAVEL` | `VARCHAR(10)` | NOT NULL | v12 | v12 |
| 6 | `TIPO_RESPONSAVEL` | `VARCHAR(3)` | NOT NULL | v12 | v12 |
| 7 | `VALOR` | `DOUBLE PRECISION` | NULL | v12 | v12 |
| 8 | `COMISSAO` | `DOUBLE PRECISION` | NULL | v12 | v12 |
| 9 | `VALOR_COMISSAO` | `DOUBLE PRECISION` | NULL | v12 | v12 |
| 10 | `STATUS` | `VARCHAR(15)` | NULL | v12 | v12 |
| 11 | `VALOR_AGENCIA` | `double precision` | NULL | v319 | v319 |
| 12 | `VALOR_FRETE` | `double precision` | NULL | v319 | v319 |
| 13 | `GERA_COMISSAO` | `VARCHAR(1)` | NULL | v486 | v486 |
| 14 | `DT_VENCIMENTO` | `TIMESTAMP` | NULL | v488 | v488 |
| 15 | `DT_PAGAMENTO` | `TIMESTAMP` | NULL | v488 | v488 |
| 16 | `FATOR_COMERCIAL` | `DOUBLE PRECISION` | NULL | v500 | v500 |
| 17 | `VALOR_PARCELA` | `DOUBLE PRECISION` | NULL | v689 | v689 |
| 18 | `CODVENDA_PRODUTO` | `INTEGER` | NULL | v689 | v689 |
| 19 | `COMISSAO_STATUS` | `VARCHAR(20)` | NULL | v1118 | v1118 |
| 20 | `CODCOMISSAO_ANTIGO` | `INTEGER` | NULL | v1216 | v1216 |
| 21 | `VALOR_COMISSAO_PENDENTE` | `DOUBLE PRECISION` | NULL | v1218 | v1218 |
| 22 | `IS_PAGAR` | `VARCHAR(1)` | NULL | v1218 | v1218 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 12 | CREATE | CREATE TABLE com 10 colunas |
| 319 | ADD_COL | + VALOR_AGENCIA double precision |
| 319 | ADD_COL | + VALOR_FRETE double precision |
| 486 | ADD_COL | + GERA_COMISSAO VARCHAR(1) |
| 488 | ADD_COL | + DT_VENCIMENTO TIMESTAMP |
| 488 | ADD_COL | + DT_PAGAMENTO TIMESTAMP |
| 498 | ADD_COL | + FATOR_COMERCIAL DOUBLE PRECISION |
| 500 | ADD_COL | + FATOR_COMERCIAL DOUBLE PRECISION |
| 689 | ADD_COL | + VALOR_PARCELA DOUBLE PRECISION |
| 689 | ADD_COL | + CODVENDA_PRODUTO INTEGER |
| 1118 | ADD_COL | + COMISSAO_STATUS VARCHAR(20) |
| 1216 | ADD_COL | + CODCOMISSAO_ANTIGO INTEGER |
| 1218 | ADD_COL | + VALOR_COMISSAO_PENDENTE DOUBLE PRECISION |
| 1218 | ADD_COL | + IS_PAGAR VARCHAR(1) |

