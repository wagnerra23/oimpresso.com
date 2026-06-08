---
table: RECURSO_AUSENCIA
module: rh
created_at_version: 702
last_modified_version: 702
target_version: 1468
columns_count: 6
foreign_keys_count: 1
foreign_keys:
  CODRECURSO: RECURSO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `RECURSO_AUSENCIA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `rh` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 702;
- **Última mudança:** UPDATE 702;
- **Total colunas (versão 1468):** 6

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODRECURSO` | [`RECURSO`](../../rh/tabelas/RECURSO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v702 | v702 |
| 2 | `CODRECURSO` | `INTEGER` | NULL | → `RECURSO` | v702 | v702 |
| 3 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v702 | v702 |
| 4 | `DATA_INICIO` | `TIMESTAMP` | NULL |  | v702 | v702 |
| 5 | `DATA_FIM` | `TIMESTAMP` | NULL |  | v702 | v702 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v702 | v702 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 702 | CREATE | CREATE TABLE com 6 colunas |

