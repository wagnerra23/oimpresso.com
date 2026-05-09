---
table: DRE_CENTRO_CUSTO
module: financeiro
created_at_version: 945
last_modified_version: 945
target_version: 1468
columns_count: 6
foreign_keys_count: 1
foreign_keys:
  CODDRE: DRE
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `DRE_CENTRO_CUSTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 945;
- **Última mudança:** UPDATE 945;
- **Total colunas (versão 1468):** 6

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODDRE` | [`DRE`](../../financeiro/tabelas/DRE.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v945 | v945 |
| 2 | `TABELA` | `VARCHAR(50)` | NULL |  | v945 | v945 |
| 3 | `DESCRICAO` | `VARCHAR(300)` | NULL |  | v945 | v945 |
| 4 | `CODDRE` | `INTEGER` | NOT NULL | → `DRE` | v945 | v945 |
| 5 | `TOTAL` | `DOUBLE PRECISION` | NULL |  | v945 | v945 |
| 6 | `PERCENTUAL_HORIZONTAL` | `DOUBLE PRECISION` | NULL |  | v945 | v945 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 945 | CREATE | CREATE TABLE com 6 colunas |

