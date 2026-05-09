---
table: EMAIL_MASSA
module: agenda
created_at_version: 730
last_modified_version: 730
target_version: 1468
columns_count: 8
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EMAIL_MASSA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 730;
- **Última mudança:** UPDATE 730;
- **Total colunas (versão 1468):** 8

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v730 | v730 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL | v730 | v730 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v730 | v730 |
| 4 | `CODUSUARIO` | `INTEGER` | NOT NULL | v730 | v730 |
| 5 | `SITUACAO` | `VARCHAR(30)` | NULL | v730 | v730 |
| 6 | `CODEMAIL_CONTA` | `INTEGER` | NULL | v730 | v730 |
| 7 | `CODEMAIL_MODELO` | `INTEGER` | NULL | v730 | v730 |
| 8 | `ATIVO` | `VARCHAR(1)` | NULL | v730 | v730 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 730 | CREATE | CREATE TABLE com 7 colunas |
| 730 | ADD_COL | + ATIVO VARCHAR(1) |

