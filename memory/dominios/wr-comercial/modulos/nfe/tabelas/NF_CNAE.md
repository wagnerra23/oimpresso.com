---
table: NF_CNAE
module: nfe
created_at_version: 942
last_modified_version: 1436
target_version: 1468
columns_count: 8
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_CNAE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 942;
- **Última mudança:** UPDATE 1436;
- **Total colunas (versão 1468):** 8

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `CHAR(9)` | NOT NULL |  | v942 | v942 |
| 2 | `SECAO` | `CHAR(1)` | NULL |  | v942 | v942 |
| 3 | `DIVISAO` | `CHAR(2)` | NULL |  | v942 | v942 |
| 4 | `GRUPO` | `CHAR(4)` | NULL |  | v942 | v942 |
| 5 | `CLASSE` | `CHAR(7)` | NULL |  | v942 | v942 |
| 6 | `DESCRICAO` | `VARCHAR(200)` | NULL |  | v942 | v942 |
| 7 | `ATIVO` | `VARCHAR(15)` | NULL |  | v1025 | v1436 |
| 8 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1025 | v1025 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 942 | CREATE | CREATE TABLE com 7 colunas |
| 1025 | ADD_COL | + ATIVO INTEGER |
| 1025 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 1436 | ALTER_TYPE | ~ ATIVO TYPE VARCHAR(15) |

