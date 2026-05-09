---
table: SINTEGRA_R54
module: nfe
created_at_version: 583
last_modified_version: 1406
target_version: 1468
columns_count: 17
foreign_keys_count: 2
foreign_keys:
  CODPRODUTO: PRODUTO
  CODSINTEGRA: SINTEGRA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `SINTEGRA_R54`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 583;
- **Última mudança:** UPDATE 1406;
- **Total colunas (versão 1468):** 17

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODSINTEGRA` | [`SINTEGRA`](../../nfe/tabelas/SINTEGRA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v583 | v583 |
| 2 | `CODSINTEGRA` | `INTEGER` | NOT NULL | → `SINTEGRA` | v583 | v583 |
| 3 | `CNPJCPF` | `VARCHAR(14)` | NULL |  | v583 | v583 |
| 4 | `MODELO` | `VARCHAR(2)` | NULL |  | v583 | v583 |
| 5 | `SERIE` | `VARCHAR(3)` | NULL |  | v583 | v583 |
| 6 | `NUMERO` | `VARCHAR(6)` | NULL |  | v583 | v583 |
| 7 | `CFOP` | `VARCHAR(4)` | NULL |  | v583 | v583 |
| 8 | `CST` | `VARCHAR(3)` | NULL |  | v583 | v583 |
| 9 | `NUM_ITEM` | `INTEGER` | NULL |  | v583 | v583 |
| 10 | `CODPRODUTO` | `VARCHAR(15) CHARACTER SET WIN1252` | NULL | → `PRODUTO` | v583 | v1406 |
| 11 | `QUANTIDADE` | `DOUBLE PRECISION` | NULL |  | v583 | v583 |
| 12 | `VALOR_PRODUTO` | `DOUBLE PRECISION` | NULL |  | v583 | v583 |
| 13 | `DESCONTO_DESPESA` | `DOUBLE PRECISION` | NULL |  | v583 | v583 |
| 14 | `ICMS_BC` | `DOUBLE PRECISION` | NULL |  | v583 | v583 |
| 15 | `ICMS_BC_ST` | `DOUBLE PRECISION` | NULL |  | v583 | v583 |
| 16 | `IPI_VALOR` | `DOUBLE PRECISION` | NULL |  | v583 | v583 |
| 17 | `ICMS_ALIQUOTA` | `DOUBLE PRECISION` | NULL |  | v583 | v583 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 583 | CREATE | CREATE TABLE com 17 colunas |
| 1406 | ALTER_TYPE | ~ CODPRODUTO TYPE VARCHAR(15) CHARACTER SET WIN1252 |

