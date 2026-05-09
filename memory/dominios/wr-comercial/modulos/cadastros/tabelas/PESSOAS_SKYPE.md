---
table: PESSOAS_SKYPE
module: cadastros
created_at_version: 343
last_modified_version: 343
target_version: 1468
columns_count: 2
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PESSOAS_SKYPE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 343;
- **Última mudança:** UPDATE 343;
- **Total colunas (versão 1468):** 2

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `SKYPE_ID` | `VARCHAR(30)` | NOT NULL | v343 | v343 |
| 2 | `CODPESSOA` | `VARCHAR(10)` | NOT NULL | v343 | v343 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 343 | CREATE | CREATE TABLE com 2 colunas |

