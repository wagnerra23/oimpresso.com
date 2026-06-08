---
table: SPED
module: nfe
created_at_version: 882
last_modified_version: 882
target_version: 1468
columns_count: 8
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `SPED`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 882;
- **Última mudança:** UPDATE 882;
- **Total colunas (versão 1468):** 8

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v882 | v882 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v882 | v882 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v882 | v882 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v882 | v882 |
| 5 | `DT_SPED_GERADO` | `TIMESTAMP` | NULL |  | v882 | v882 |
| 6 | `DT_INICO` | `TIMESTAMP` | NULL |  | v882 | v882 |
| 7 | `DT_FIM` | `TIMESTAMP` | NULL |  | v882 | v882 |
| 8 | `PERFIL` | `VARCHAR(50)` | NULL |  | v882 | v882 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 882 | CREATE | CREATE TABLE com 8 colunas |

