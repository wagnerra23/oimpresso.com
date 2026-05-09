---
table: CONFIGURACAO_FORM
module: configuracao
created_at_version: 903
last_modified_version: 903
target_version: 1468
columns_count: 5
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CONFIGURACAO_FORM`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `configuracao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 903;
- **Última mudança:** UPDATE 903;
- **Total colunas (versão 1468):** 5

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v903 | v903 |
| 2 | `DESCRICAO` | `VARCHAR(500)` | NULL |  | v903 | v903 |
| 3 | `TABELA` | `VARCHAR(255)` | NULL |  | v903 | v903 |
| 4 | `ATIVO` | `VARCHAR(1)` | NULL |  | v903 | v903 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v903 | v903 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 895 | CREATE | CREATE TABLE com 5 colunas |
| 903 | CREATE | CREATE TABLE com 5 colunas |

