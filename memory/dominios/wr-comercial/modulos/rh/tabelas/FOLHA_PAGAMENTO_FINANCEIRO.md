---
table: FOLHA_PAGAMENTO_FINANCEIRO
module: rh
created_at_version: 310
last_modified_version: 367
target_version: 1468
columns_count: 13
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FOLHA_PAGAMENTO_FINANCEIRO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `rh` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 310;
- **Última mudança:** UPDATE 367;
- **Total colunas (versão 1468):** 13

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v310 | v310 |
| 2 | `CODFOLHA_PAGAMENTO` | `INTEGER` | NOT NULL | v310 | v310 |
| 3 | `CODEMPRESA` | `INTEGER` | NOT NULL | v310 | v310 |
| 4 | `CODPESSOA` | `VARCHAR(10)` | NOT NULL | v310 | v310 |
| 5 | `DESCRICAO` | `VARCHAR(150)` | NULL | v310 | v310 |
| 6 | `REFERENCIA` | `DOUBLE PRECISION` | NULL | v310 | v310 |
| 7 | `VENCTO` | `DOUBLE PRECISION` | NULL | v310 | v310 |
| 8 | `DESCONTO` | `DOUBLE PRECISION` | NULL | v310 | v310 |
| 9 | `CODFOLHA_PAGAMENTO_GRUPO` | `INTEGER` | NULL | v310 | v310 |
| 10 | `SEQUENCIA` | `smallint` | NOT NULL | v360 | v360 |
| 11 | `FIN_CODIGO` | `integer` | NULL | v367 | v367 |
| 12 | `FIN_CODPEDIDO` | `varchar(10)` | NULL | v367 | v367 |
| 13 | `FIN_CODEMPRESA` | `varchar(10)` | NULL | v367 | v367 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 310 | CREATE | CREATE TABLE com 9 colunas |
| 360 | ADD_COL | + SEQUENCIA smallint |
| 367 | ADD_COL | + FIN_CODIGO integer |
| 367 | ADD_COL | + FIN_CODPEDIDO varchar(10) |
| 367 | ADD_COL | + FIN_CODEMPRESA varchar(10) |

