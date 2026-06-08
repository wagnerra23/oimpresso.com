---
table: ANTIFURTO_TIPO
module: equipamento
created_at_version: 570
last_modified_version: 1071
target_version: 1468
columns_count: 4
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `ANTIFURTO_TIPO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `equipamento` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 570;
- **Última mudança:** UPDATE 1071;
- **Total colunas (versão 1468):** 4

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v570 | v570 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v570 | v570 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v570 | v570 |
| 4 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1071 | v1071 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 570 | CREATE | CREATE TABLE com 3 colunas |
| 1071 | ADD_COL | + ATIVO VARCHAR(1) |

