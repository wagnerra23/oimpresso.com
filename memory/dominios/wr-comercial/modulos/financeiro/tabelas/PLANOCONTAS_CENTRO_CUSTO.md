---
table: PLANOCONTAS_CENTRO_CUSTO
module: financeiro
created_at_version: 949
last_modified_version: 1262
target_version: 1468
columns_count: 4
foreign_keys_count: 1
foreign_keys:
  CODPLANOCONTAS: PLANOCONTAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PLANOCONTAS_CENTRO_CUSTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 949;
- **Última mudança:** UPDATE 1262;
- **Total colunas (versão 1468):** 4

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPLANOCONTAS` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v949 | v949 |
| 2 | `PERCENTUAL` | `DOUBLE PRECISION` | NULL |  | v949 | v949 |
| 3 | `CODPLANOCONTAS` | `VARCHAR(15)` | NOT NULL | → `PLANOCONTAS` | v949 | v961 |
| 4 | `CODCENTROCUSTO` | `INTEGER` | NULL |  | v1262 | v1262 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 949 | CREATE | CREATE TABLE com 3 colunas |
| 961 | RENAME_COL | × CODPLANOSCONTAS → CODPLANOCONTAS |
| 1262 | ADD_COL | + CODCENTROCUSTO INTEGER |

