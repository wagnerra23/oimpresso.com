---
table: TABFIPE_ANOMODELO
module: equipamento
created_at_version: 526
last_modified_version: 526
target_version: 1468
columns_count: 8
foreign_keys_count: 1
foreign_keys:
  CODTABFIPE_MARCA: TABFIPE_MARCA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `TABFIPE_ANOMODELO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `equipamento` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 526;
- **Última mudança:** UPDATE 526;
- **Total colunas (versão 1468):** 8

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODTABFIPE_MARCA` | [`TABFIPE_MARCA`](../../equipamento/tabelas/TABFIPE_MARCA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v526 | v526 |
| 2 | `CODTABFIPE_MARCA` | `INTEGER` | NOT NULL | → `TABFIPE_MARCA` | v526 | v526 |
| 3 | `FIPE_CODIGO` | `VARCHAR(100)` | NULL |  | v526 | v526 |
| 4 | `NAME` | `VARCHAR(100)` | NULL |  | v526 | v526 |
| 5 | `"KEY"` | `VARCHAR(100)` | NULL |  | v526 | v526 |
| 6 | `FIPE_MARCA` | `VARCHAR(100)` | NULL |  | v526 | v526 |
| 7 | `MARCA` | `VARCHAR(100)` | NULL |  | v526 | v526 |
| 8 | `FIPE_NAME` | `VARCHAR(100)` | NULL |  | v526 | v526 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 526 | CREATE | CREATE TABLE com 8 colunas |

