---
table: CENTRO_TRABALHO_ESTAGIO
module: producao
created_at_version: 1057
last_modified_version: 1065
target_version: 1468
columns_count: 5
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CENTRO_TRABALHO_ESTAGIO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1057;
- **Última mudança:** UPDATE 1065;
- **Total colunas (versão 1468):** 5

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v1057 | v1057 |
| 2 | `CODCENTRO_TRABALHO` | `INTEGER` | NOT NULL | v1057 | v1057 |
| 3 | `CODUSUARIO` | `INTEGER` | NULL | v1057 | v1057 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v1057 | v1057 |
| 5 | `CODPRODUCAO_ESTAGIO` | `INTEGER` | NOT NULL | v1057 | v1065 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1057 | CREATE | CREATE TABLE com 5 colunas |
| 1065 | RENAME_COL | × CODESTAGIO → CODPRODUCAO_ESTAGIO |

