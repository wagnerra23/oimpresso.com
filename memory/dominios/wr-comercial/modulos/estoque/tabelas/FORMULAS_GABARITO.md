---
table: FORMULAS_GABARITO
module: estoque
created_at_version: 825
last_modified_version: 825
target_version: 1468
columns_count: 10
foreign_keys_count: 2
foreign_keys:
  CODFORMULAS: FORMULAS
  CODPAI: PAIS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FORMULAS_GABARITO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 825;
- **Última mudança:** UPDATE 825;
- **Total colunas (versão 1468):** 10

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODFORMULAS` | [`FORMULAS`](../../estoque/tabelas/FORMULAS.md) |
| `CODPAI` | [`PAIS`](../../cadastros/tabelas/PAIS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v825 | v825 |
| 2 | `CODFORMULAS` | `INTEGER` | NOT NULL | → `FORMULAS` | v825 | v825 |
| 3 | `CODPAI` | `INTEGER` | NULL | → `PAIS` | v825 | v825 |
| 4 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v825 | v825 |
| 5 | `COMP_FORMULA` | `VARCHAR(1000)` | NULL |  | v825 | v825 |
| 6 | `LARG_FORMULA` | `VARCHAR(1000)` | NULL |  | v825 | v825 |
| 7 | `ESPESSURA_FORMULA` | `VARCHAR(1000)` | NULL |  | v825 | v825 |
| 8 | `QTDADEPECA_FORMULA` | `VARCHAR(1000)` | NULL |  | v825 | v825 |
| 9 | `FORMULA` | `VARCHAR(4000)` | NULL |  | v825 | v825 |
| 10 | `QUANT_FORMULA` | `VARCHAR(1000)` | NULL |  | v825 | v825 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 825 | CREATE | CREATE TABLE com 9 colunas |
| 825 | ADD_COL | + QUANT_FORMULA VARCHAR(1000) |

