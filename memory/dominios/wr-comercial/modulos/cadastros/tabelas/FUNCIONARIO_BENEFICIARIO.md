---
table: FUNCIONARIO_BENEFICIARIO
module: cadastros
created_at_version: 1289
last_modified_version: 1289
target_version: 1468
columns_count: 6
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FUNCIONARIO_BENEFICIARIO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1289;
- **Última mudança:** UPDATE 1289;
- **Total colunas (versão 1468):** 6

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v1289 | v1289 |
| 2 | `CODIGO` | `INTEGER` | NOT NULL | v1289 | v1289 |
| 3 | `CODFUNCIONARIO` | `VARCHAR(10)` | NOT NULL | v1289 | v1289 |
| 4 | `BENEFICIARIO` | `VARCHAR(150)` | NULL | v1289 | v1289 |
| 5 | `PARENTESCO` | `VARCHAR(50)` | NULL | v1289 | v1289 |
| 6 | `DT_NASCIMENTO` | `TIMESTAMP` | NULL | v1289 | v1289 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 174 | ADD_COL | + DT_ALTERACAO timestamp |
| 1289 | CREATE | CREATE TABLE com 6 colunas |

