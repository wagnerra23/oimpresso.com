---
table: DASHBOARDS_ATALHO_RAPIDO
module: bi
created_at_version: 1406
last_modified_version: 1406
target_version: 1468
columns_count: 2
foreign_keys_count: 2
foreign_keys:
  CODATALHO_RAPIDO: ATALHO_RAPIDO
  CODDASHBOARD: DASHBOARDS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `DASHBOARDS_ATALHO_RAPIDO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `bi` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1406;
- **Última mudança:** UPDATE 1406;
- **Total colunas (versão 1468):** 2

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODATALHO_RAPIDO` | [`ATALHO_RAPIDO`](../../ui_metadata/tabelas/ATALHO_RAPIDO.md) |
| `CODDASHBOARD` | [`DASHBOARDS`](../../bi/tabelas/DASHBOARDS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODDASHBOARD` | `INTEGER` | NOT NULL | → `DASHBOARDS` | v1406 | v1406 |
| 2 | `CODATALHO_RAPIDO` | `INTEGER` | NOT NULL | → `ATALHO_RAPIDO` | v1406 | v1406 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 994 | CREATE | CREATE TABLE com 2 colunas |
| 1406 | CREATE | CREATE TABLE com 2 colunas |

