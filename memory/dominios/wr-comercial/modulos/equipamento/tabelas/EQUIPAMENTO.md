---
table: EQUIPAMENTO
module: equipamento
created_at_version: 47
last_modified_version: 1459
target_version: 1468
columns_count: 6
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EQUIPAMENTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `equipamento` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 47;
- **Última mudança:** UPDATE 1459;
- **Total colunas (versão 1468):** 6

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1217 | v1217 |
| 2 | `TODOS_ATIVOS` | `VARCHAR(1)` | NULL |  | v532 | v532 |
| 3 | `DT_OCORRENCIA_INICIO` | `TIMESTAMP` | NULL |  | v1122 | v1122 |
| 4 | `DT_OCORRENCIA_FIM` | `TIMESTAMP` | NULL |  | v1129 | v1129 |
| 5 | `VALOR_CONTRIBUICAO_ASSOCIADO` | `DOUBLE PRECISION` | NULL |  | v1454 | v1454 |
| 6 | `VALOR_OUTRAS_CONTRIBUICOES` | `DOUBLE PRECISION` | NULL |  | v1459 | v1459 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 47 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 68 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 144 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 532 | ADD_COL | + TODOS_ATIVOS VARCHAR(1) |
| 1122 | ADD_COL | + DT_OCORRENCIA_INICIO TIMESTAMP |
| 1129 | ADD_COL | + DT_OCORRENCIA_FIM TIMESTAMP |
| 1217 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 1454 | ADD_COL | + VALOR_CONTRIBUICAO_ASSOCIADO DOUBLE PRECISION |
| 1459 | ADD_COL | + VALOR_OUTRAS_CONTRIBUICOES DOUBLE PRECISION |

