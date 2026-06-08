---
table: NF_ERROS
module: nfe
created_at_version: 909
last_modified_version: 944
target_version: 1468
columns_count: 12
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_ERROS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 909;
- **Última mudança:** UPDATE 944;
- **Total colunas (versão 1468):** 12

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v909 | v909 |
| 2 | `ERRO` | `VARCHAR(30)` | NULL |  | v909 | v944 |
| 3 | `TIPO_DOCUMENTO` | `VARCHAR(255)` | NULL |  | v909 | v909 |
| 4 | `DESCRICAO` | `VARCHAR(500)` | NULL |  | v909 | v909 |
| 5 | `TELA` | `VARCHAR(255)` | NULL |  | v909 | v909 |
| 6 | `COMPONENTE` | `VARCHAR(255)` | NULL |  | v909 | v909 |
| 7 | `LINK` | `VARCHAR(500)` | NULL |  | v909 | v909 |
| 8 | `OBSERVACAO` | `VARCHAR(500)` | NULL |  | v909 | v909 |
| 9 | `ATIVO` | `VARCHAR(1), ADD DT_ALTERACAO TIMESTAMP` | NULL |  | v909 | v909 |
| 10 | `RETENTAR` | `VARCHAR(1)` | NULL |  | v909 | v909 |
| 11 | `TAG` | `VARCHAR(1000)` | NULL |  | v944 | v944 |
| 12 | `CAMPO` | `VARCHAR(255)` | NULL |  | v944 | v944 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 909 | CREATE | CREATE TABLE com 8 colunas |
| 909 | ADD_COL | + ATIVO VARCHAR(1), ADD DT_ALTERACAO TIMESTAMP |
| 909 | ADD_COL | + RETENTAR VARCHAR(1) |
| 944 | ADD_COL | + TAG VARCHAR(1000) |
| 944 | ADD_COL | + CAMPO VARCHAR(255) |
| 944 | ALTER_TYPE | ~ ERRO TYPE VARCHAR(30) |

