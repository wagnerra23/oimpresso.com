---
table: SOLICITACAO
module: agenda
created_at_version: 475
last_modified_version: 475
target_version: 1468
columns_count: 6
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `SOLICITACAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 475;
- **Última mudança:** UPDATE 475;
- **Total colunas (versão 1468):** 6

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `SOLICITA` | `SMALLINT` | NULL |  | v475 | v475 |
| 2 | `VERSAO_ATUAL` | `VARCHAR(20)` | NULL |  | v475 | v475 |
| 3 | `VERIFICA_SERVIDOR` | `SMALLINT` | NULL |  | v475 | v475 |
| 4 | `ERRO_CONEXAO` | `SMALLINT` | NULL |  | v475 | v475 |
| 5 | `PROGRESSO_DOWNLOAD` | `INTEGER` | NULL |  | v475 | v475 |
| 6 | `MESSAGE_ERRO` | `VARCHAR(350)` | NULL |  | v475 | v475 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 475 | CREATE | CREATE TABLE com 6 colunas |

