---
table: SINTEGRA_R74
module: nfe
created_at_version: 583
last_modified_version: 1406
target_version: 1468
columns_count: 10
foreign_keys_count: 2
foreign_keys:
  CODPRODUTO: PRODUTO
  CODSINTEGRA: SINTEGRA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `SINTEGRA_R74`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 583;
- **Última mudança:** UPDATE 1406;
- **Total colunas (versão 1468):** 10

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
| 3 | `DT_INVENTARIO` | `DATE` | NULL |  | v583 | v583 |
| 4 | `CODPRODUTO` | `VARCHAR(15) CHARACTER SET WIN1252` | NULL | → `PRODUTO` | v583 | v1406 |
| 5 | `QUANTIDADE` | `DOUBLE PRECISION` | NULL |  | v583 | v583 |
| 6 | `VALOR_PRODUTO` | `DOUBLE PRECISION` | NULL |  | v583 | v583 |
| 7 | `CODIGO_POSSE` | `VARCHAR(1)` | NULL |  | v583 | v583 |
| 8 | `CNPJ_POSSUIDOR` | `VARCHAR(14)` | NULL |  | v583 | v583 |
| 9 | `IE_POSSUIDOR` | `VARCHAR(14)` | NULL |  | v583 | v583 |
| 10 | `UF_POSSUIDOR` | `VARCHAR(2)` | NULL |  | v583 | v583 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 583 | CREATE | CREATE TABLE com 10 colunas |
| 1406 | ALTER_TYPE | ~ CODPRODUTO TYPE VARCHAR(15) CHARACTER SET WIN1252 |

