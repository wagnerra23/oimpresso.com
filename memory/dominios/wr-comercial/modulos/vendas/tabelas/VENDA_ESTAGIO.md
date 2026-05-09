---
table: VENDA_ESTAGIO
module: vendas
created_at_version: 1042
last_modified_version: 1042
target_version: 1468
columns_count: 5
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `VENDA_ESTAGIO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `vendas` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1042;
- **Última mudança:** UPDATE 1042;
- **Total colunas (versão 1468):** 5

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1042 | v1042 |
| 2 | `DESCRICAO` | `VARCHAR(50)` | NULL |  | v1042 | v1042 |
| 3 | `ICONE` | `INTEGER` | NULL |  | v1042 | v1042 |
| 4 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1042 | v1042 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1042 | v1042 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1042 | CREATE | CREATE TABLE com 5 colunas |

