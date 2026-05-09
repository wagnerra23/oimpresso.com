---
table: PRODUCAO_STATUS
module: producao
created_at_version: 810
last_modified_version: 904
target_version: 1468
columns_count: 13
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUCAO_STATUS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 810;
- **Última mudança:** UPDATE 904;
- **Total colunas (versão 1468):** 13

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v810 | v810 |
| 2 | `CODPRODUCAO` | `INTEGER` | NOT NULL | v56 | v56 |
| 3 | `CODUSUARIO` | `INTEGER` | NULL | v810 | v810 |
| 4 | `CODSTATUS_ANTERIOR` | `INTEGER` | NULL | v810 | v810 |
| 5 | `CODSTATUS` | `INTEGER` | NULL | v810 | v810 |
| 6 | `DATA` | `TIMESTAMP` | NULL | v810 | v810 |
| 7 | `OBSERVACAO` | `VARCHAR(150)` | NULL | v810 | v810 |
| 8 | `DESCRICAO` | `VARCHAR(50)` | NULL | v904 | v904 |
| 9 | `ICONE` | `INTEGER` | NULL | v810 | v810 |
| 10 | `CHAMA_AJUDA` | `VARCHAR(1)` | NULL | v810 | v810 |
| 11 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v810 | v810 |
| 12 | `ATIVO` | `VARCHAR(1), ADD DT_ALTERACAO TIMESTAMP` | NULL | v904 | v904 |
| 13 | `CODAGENDA` | `VARCHAR(40)` | NOT NULL | v810 | v810 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 56 | CREATE | CREATE TABLE com 7 colunas |
| 810 | CREATE | CREATE TABLE com 7 colunas |
| 810 | CREATE | CREATE TABLE com 9 colunas |
| 904 | ADD_COL | + ATIVO VARCHAR(1), ADD DT_ALTERACAO TIMESTAMP |
| 904 | ADD_COL | + DESCRICAO VARCHAR(50) |

