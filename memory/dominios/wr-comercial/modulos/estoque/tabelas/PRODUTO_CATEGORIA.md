---
table: PRODUTO_CATEGORIA
module: estoque
created_at_version: 612
last_modified_version: 1264
target_version: 1468
columns_count: 17
foreign_keys_count: 2
foreign_keys:
  CODCENTRO_CUSTO: CENTRO_CUSTO
  CODPLANOCONTAS: PLANOCONTAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_CATEGORIA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 612;
- **Última mudança:** UPDATE 1264;
- **Total colunas (versão 1468):** 17

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCENTRO_CUSTO` | [`CENTRO_CUSTO`](../../producao/tabelas/CENTRO_CUSTO.md) |
| `CODPLANOCONTAS` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `VARCHAR(15)` | NOT NULL |  | v612 | v612 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v612 | v612 |
| 3 | `ATIVO` | `DOM_BOOLEAN` | NULL |  | v612 | v612 |
| 4 | `INDICE1` | `INTEGER` | NULL |  | v612 | v612 |
| 5 | `INDICE2` | `INTEGER` | NULL |  | v612 | v612 |
| 6 | `INDICE3` | `INTEGER` | NULL |  | v612 | v612 |
| 7 | `INDICE4` | `INTEGER` | NULL |  | v612 | v612 |
| 8 | `INDICE5` | `INTEGER` | NULL |  | v612 | v612 |
| 9 | `INDICE6` | `INTEGER` | NULL |  | v612 | v612 |
| 10 | `TIPO` | `VARCHAR(1)` | NULL |  | v612 | v612 |
| 11 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v612 | v612 |
| 12 | `CODPLANOCONTAS` | `VARCHAR(15)` | NULL | → `PLANOCONTAS` | v958 | v958 |
| 13 | `OIMPRESSO_ATIVO` | `VARCHAR(1)` | NULL |  | v1250 | v1250 |
| 14 | `OIMPRESSO_CODIGO` | `VARCHAR(15)` | NULL |  | v1250 | v1250 |
| 15 | `OIMPRESSO_DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1250 | v1250 |
| 16 | `OIMPRESSO_UPDATED_AT` | `TIMESTAMP` | NULL |  | v1250 | v1250 |
| 17 | `CODCENTRO_CUSTO` | `INTEGER` | NULL | → `CENTRO_CUSTO` | v1264 | v1264 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 612 | CREATE | CREATE TABLE com 11 colunas |
| 958 | ADD_COL | + CODPLANOCONTAS VARCHAR(15) |
| 1250 | ADD_COL | + OIMPRESSO_ATIVO VARCHAR(1) |
| 1250 | ADD_COL | + OIMPRESSO_CODIGO VARCHAR(15) |
| 1250 | ADD_COL | + OIMPRESSO_DT_ALTERACAO TIMESTAMP |
| 1250 | ADD_COL | + OIMPRESSO_UPDATED_AT TIMESTAMP |
| 1264 | ADD_COL | + CODCENTRO_CUSTO INTEGER |

