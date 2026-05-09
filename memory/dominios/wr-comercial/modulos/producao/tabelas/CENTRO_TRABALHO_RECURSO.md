---
table: CENTRO_TRABALHO_RECURSO
module: producao
created_at_version: 609
last_modified_version: 1299
target_version: 1468
columns_count: 13
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CENTRO_TRABALHO_RECURSO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 609;
- **Última mudança:** UPDATE 1299;
- **Total colunas (versão 1468):** 13

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v609 | v609 |
| 2 | `CODCENTRO_TRABALHO` | `INTEGER` | NOT NULL | v609 | v609 |
| 3 | `CODRECURSO` | `INTEGER` | NOT NULL | v609 | v609 |
| 4 | `INSERIR` | `VARCHAR(1)` | NULL | v1002 | v1002 |
| 5 | `EDITAR` | `VARCHAR(1)` | NULL | v1002 | v1002 |
| 6 | `FINALIZAR` | `VARCHAR(1)` | NULL | v1002 | v1002 |
| 7 | `CANCELAR` | `VARCHAR(1)` | NULL | v1002 | v1002 |
| 8 | `REATIVAR` | `VARCHAR(1)` | NULL | v1002 | v1002 |
| 9 | `PESSOA_FUNCIONARIO_CODIGO` | `VARCHAR(15)` | NULL | v1082 | v1082 |
| 10 | `PESSOA_FUNCIONARIO_TIPO` | `VARCHAR(3)` | NULL | v1082 | v1082 |
| 11 | `PESSOA_FUNCIONARIO_SEQUENCIA` | `INTEGER` | NULL | v1082 | v1082 |
| 12 | `CARGA_HORARIA` | `INTEGER` | NULL | v1299 | v1299 |
| 13 | `CARGA_HORARIA_PERC` | `DOUBLE PRECISION` | NULL | v1299 | v1299 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 609 | CREATE | CREATE TABLE com 3 colunas |
| 1002 | ADD_COL | + INSERIR VARCHAR(1) |
| 1002 | ADD_COL | + EDITAR VARCHAR(1) |
| 1002 | ADD_COL | + FINALIZAR VARCHAR(1) |
| 1002 | ADD_COL | + CANCELAR VARCHAR(1) |
| 1002 | ADD_COL | + REATIVAR VARCHAR(1) |
| 1082 | ADD_COL | + PESSOA_FUNCIONARIO_CODIGO VARCHAR(15) |
| 1082 | ADD_COL | + PESSOA_FUNCIONARIO_TIPO VARCHAR(3) |
| 1082 | ADD_COL | + PESSOA_FUNCIONARIO_SEQUENCIA INTEGER |
| 1299 | ADD_COL | + CARGA_HORARIA INTEGER |
| 1299 | ADD_COL | + CARGA_HORARIA_PERC DOUBLE PRECISION |

