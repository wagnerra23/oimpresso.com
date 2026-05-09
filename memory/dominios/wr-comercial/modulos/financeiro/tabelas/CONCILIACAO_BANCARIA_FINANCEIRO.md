---
table: CONCILIACAO_BANCARIA_FINANCEIRO
module: financeiro
created_at_version: 284
last_modified_version: 299
target_version: 1468
columns_count: 11
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CONCILIACAO_BANCARIA_FINANCEIRO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 284;
- **Última mudança:** UPDATE 299;
- **Total colunas (versão 1468):** 11

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v284 | v284 |
| 2 | `CODCONCILIACAO_BANCARIA` | `INTEGER` | NOT NULL | v284 | v284 |
| 3 | `TIPO_MOVIMENTO` | `VARCHAR(10)` | NULL | v284 | v284 |
| 4 | `DT_MOVIMENTO` | `TIMESTAMP` | NULL | v284 | v284 |
| 5 | `VALOR` | `DOUBLE PRECISION` | NULL | v284 | v284 |
| 6 | `DESCRICAO` | `VARCHAR(100)` | NULL | v284 | v284 |
| 7 | `DOCUMENTO` | `VARCHAR(20)` | NULL | v284 | v284 |
| 8 | `CODFINANCEIRO` | `INTEGER` | NULL | v284 | v284 |
| 9 | `CODPEDIDO` | `VARCHAR(10)` | NULL | v284 | v284 |
| 10 | `CODEMPRESA` | `VARCHAR(10)` | NULL | v284 | v284 |
| 11 | `ACAO` | `INTEGER` | NULL | v299 | v299 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 284 | CREATE | CREATE TABLE com 10 colunas |
| 299 | ADD_COL | + ACAO INTEGER |

