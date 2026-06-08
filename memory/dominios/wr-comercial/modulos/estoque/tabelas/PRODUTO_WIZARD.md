---
table: PRODUTO_WIZARD
module: estoque
created_at_version: 381
last_modified_version: 579
target_version: 1468
columns_count: 16
foreign_keys_count: 2
foreign_keys:
  CODPRODUTO: PRODUTO
  CODSETOR: SETOR
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_WIZARD`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 381;
- **Última mudança:** UPDATE 579;
- **Total colunas (versão 1468):** 16

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODSETOR` | [`SETOR`](../../cadastros/tabelas/SETOR.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v381 | v381 |
| 2 | `CODPRODUTO` | `VARCHAR(15)` | NOT NULL | → `PRODUTO` | v381 | v381 |
| 3 | `PARENT` | `INTEGER` | NULL |  | v381 | v381 |
| 4 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v381 | v381 |
| 5 | `TIPO` | `VARCHAR(50)` | NULL |  | v381 | v381 |
| 6 | `OBSERVACAO` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v381 | v381 |
| 7 | `CODSETOR` | `integer` | NULL | → `SETOR` | v408 | v408 |
| 8 | `AVANCO_FIXO_COMP` | `double precision` | NULL |  | v579 | v579 |
| 9 | `AVANCO_FIXO_LARG` | `double precision` | NULL |  | v579 | v579 |
| 10 | `AVANCO_FIXO_ESP` | `double precision` | NULL |  | v579 | v579 |
| 11 | `AVANCO_PROP_COMP` | `double precision` | NULL |  | v579 | v579 |
| 12 | `AVANCO_PROP_LARG` | `double precision` | NULL |  | v579 | v579 |
| 13 | `AVANCO_PROP_ESP` | `double precision` | NULL |  | v579 | v579 |
| 14 | `PERIMETRO` | `double precision` | NULL |  | v579 | v579 |
| 15 | `PERIMETRO_FATOR` | `double precision` | NULL |  | v579 | v579 |
| 16 | `PERIMETRO_QUANT` | `double precision` | NULL |  | v579 | v579 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 381 | CREATE | CREATE TABLE com 6 colunas |
| 408 | ADD_COL | + CODSETOR integer |
| 579 | ADD_COL | + AVANCO_FIXO_COMP double precision |
| 579 | ADD_COL | + AVANCO_FIXO_LARG double precision |
| 579 | ADD_COL | + AVANCO_FIXO_ESP double precision |
| 579 | ADD_COL | + AVANCO_PROP_COMP double precision |
| 579 | ADD_COL | + AVANCO_PROP_LARG double precision |
| 579 | ADD_COL | + AVANCO_PROP_ESP double precision |
| 579 | ADD_COL | + PERIMETRO double precision |
| 579 | ADD_COL | + PERIMETRO_FATOR double precision |
| 579 | ADD_COL | + PERIMETRO_QUANT double precision |

