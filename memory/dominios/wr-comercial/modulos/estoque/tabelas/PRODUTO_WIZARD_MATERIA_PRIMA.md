---
id: dominios-wr-comercial-modulos-estoque-tabelas-produto-wizard-materia-prima
table: PRODUTO_WIZARD_MATERIA_PRIMA
module: estoque
created_at_version: 381
last_modified_version: 579
target_version: 1468
columns_count: 13
foreign_keys_count: 4
foreign_keys:
  CODPRODUTO: PRODUTO
  CODPRODUTO_MATERIA_PRIMA: PRODUTO
  CODPRODUTO_WIZARD: PRODUTO_WIZARD
  CODSETOR_DESTINO: SETOR
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_WIZARD_MATERIA_PRIMA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 381;
- **Última mudança:** UPDATE 579;
- **Total colunas (versão 1468):** 13

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODPRODUTO_MATERIA_PRIMA` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODPRODUTO_WIZARD` | [`PRODUTO_WIZARD`](../../estoque/tabelas/PRODUTO_WIZARD.md) |
| `CODSETOR_DESTINO` | [`SETOR`](../../cadastros/tabelas/SETOR.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODPRODUTO_WIZARD` | `INTEGER` | NOT NULL | → `PRODUTO_WIZARD` | v381 | v381 |
| 2 | `CODPRODUTO` | `VARCHAR(15)` | NOT NULL | → `PRODUTO` | v381 | v381 |
| 3 | `CODPRODUTO_MATERIA_PRIMA` | `VARCHAR(15)` | NOT NULL | → `PRODUTO` | v381 | v381 |
| 4 | `IS_GRUPO` | `INTEGER` | NULL |  | v389 | v389 |
| 5 | `TIPO_OBS` | `VARCHAR(20)` | NULL |  | v408 | v436 |
| 6 | `OBS_PRODUCAO` | `blob sub_type 1 segment size 80` | NULL |  | v408 | v408 |
| 7 | `ARQUIVO` | `varchar (255)` | NULL |  | v408 | v408 |
| 8 | `ARQUIVO_OBRIGATORIO` | `varchar (1)` | NULL |  | v408 | v408 |
| 9 | `FIXO` | `varchar (1)` | NULL |  | v408 | v408 |
| 10 | `CODSETOR_DESTINO` | `integer` | NULL | → `SETOR` | v408 | v408 |
| 11 | `PRAZO_ESTIMADO_MINUTOS` | `integer` | NULL |  | v408 | v408 |
| 12 | `PERC_ADICIONA` | `DOUBLE PRECISION` | NULL |  | v427 | v427 |
| 13 | `ADICIONA_VALOR` | `varchar (1)` | NULL |  | v579 | v579 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 381 | CREATE | CREATE TABLE com 3 colunas |
| 389 | ADD_COL | + IS_GRUPO INTEGER |
| 408 | ADD_COL | + TIPO_OBS varchar (10) |
| 408 | ADD_COL | + OBS_PRODUCAO blob sub_type 1 segment size 80 |
| 408 | ADD_COL | + ARQUIVO varchar (255) |
| 408 | ADD_COL | + ARQUIVO_OBRIGATORIO varchar (1) |
| 408 | ADD_COL | + FIXO varchar (1) |
| 408 | ADD_COL | + CODSETOR_DESTINO integer |
| 408 | ADD_COL | + PRAZO_ESTIMADO_MINUTOS integer |
| 427 | ADD_COL | + PERC_ADICIONA DOUBLE PRECISION |
| 436 | ALTER_TYPE | ~ TIPO_OBS TYPE VARCHAR(20) |
| 579 | ADD_COL | + ADICIONA_VALOR varchar (1) |

