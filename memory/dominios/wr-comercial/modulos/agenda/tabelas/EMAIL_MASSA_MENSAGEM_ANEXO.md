---
table: EMAIL_MASSA_MENSAGEM_ANEXO
module: agenda
created_at_version: 730
last_modified_version: 730
target_version: 1468
columns_count: 3
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EMAIL_MASSA_MENSAGEM_ANEXO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 730;
- **Última mudança:** UPDATE 730;
- **Total colunas (versão 1468):** 3

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v730 | v730 |
| 2 | `CODEMAIL_MASSA_MENSAGEM` | `INTEGER` | NOT NULL | v730 | v730 |
| 3 | `CAMINHO` | `VARCHAR(300)` | NULL | v730 | v730 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 730 | CREATE | CREATE TABLE com 3 colunas |

