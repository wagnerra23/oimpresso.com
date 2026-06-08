---
table: NF_TIPO_PAGAMENTO
module: nfe
created_at_version: 1108
last_modified_version: 1108
target_version: 1468
columns_count: 2
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_TIPO_PAGAMENTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1108;
- **Última mudança:** UPDATE 1108;
- **Total colunas (versão 1468):** 2

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `VARCHAR(2)` | NOT NULL |  | v1108 | v1108 |
| 2 | `DESCRICAO` | `VARCHAR(100)` | NULL |  | v1108 | v1108 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1108 | CREATE | CREATE TABLE com 2 colunas |

