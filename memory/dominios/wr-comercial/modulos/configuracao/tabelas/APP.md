---
table: APP
module: configuracao
created_at_version: 1257
last_modified_version: 1430
target_version: 1468
columns_count: 4
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `APP`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `configuracao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1257;
- **Última mudança:** UPDATE 1430;
- **Total colunas (versão 1468):** 4

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `VARCHAR(20)` | NOT NULL |  | v1257 | v1257 |
| 2 | `CNPJCPF` | `VARCHAR(20)` | NOT NULL |  | v1257 | v1257 |
| 3 | `CHAVE` | `VARCHAR(100)` | NULL |  | v1257 | v1257 |
| 4 | `PATH` | `VARCHAR(255)` | NULL |  | v1430 | v1430 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1257 | CREATE | CREATE TABLE com 3 colunas |
| 1429 | ADD_COL | + PATH VARCHAR(255) |
| 1430 | ADD_COL | + PATH VARCHAR(255) |

