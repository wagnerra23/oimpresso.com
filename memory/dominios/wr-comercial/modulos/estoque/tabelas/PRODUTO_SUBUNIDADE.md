---
table: PRODUTO_SUBUNIDADE
module: estoque
created_at_version: 1170
last_modified_version: 1173
target_version: 1468
columns_count: 18
foreign_keys_count: 2
foreign_keys:
  CODPRODUTO: PRODUTO
  CODUNIDADE: UNIDADE
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_SUBUNIDADE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1170;
- **Última mudança:** UPDATE 1173;
- **Total colunas (versão 1468):** 18

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODUNIDADE` | [`UNIDADE`](../../estoque/tabelas/UNIDADE.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1170 | v1170 |
| 2 | `CODUNIDADE` | `INTEGER` | NULL | → `UNIDADE` | v1170 | v1170 |
| 3 | `CODPRODUTO` | `VARCHAR(15)` | NULL | → `PRODUTO` | v1170 | v1170 |
| 4 | `UNIDADE_RENDIMENTO` | `VARCHAR(6)` | NULL |  | v1170 | v1170 |
| 5 | `QTDADEPECA` | `DOUBLE PRECISION` | NULL |  | v1170 | v1170 |
| 6 | `COMP` | `DOUBLE PRECISION` | NULL |  | v1170 | v1170 |
| 7 | `LARG` | `DOUBLE PRECISION` | NULL |  | v1170 | v1170 |
| 8 | `ESPESSURA` | `DOUBLE PRECISION` | NULL |  | v1170 | v1170 |
| 9 | `RENDIMENTO` | `DOUBLE PRECISION` | NULL |  | v1170 | v1170 |
| 10 | `FORMULA` | `VARCHAR(50)` | NULL |  | v1170 | v1170 |
| 11 | `TEM_DIVISAO_MATERIAL` | `VARCHAR(1)` | NULL |  | v1170 | v1170 |
| 12 | `DESCRICAO_PERSONALIZADA` | `VARCHAR(50)` | NULL |  | v1170 | v1170 |
| 13 | `ESPECIFICACAO` | `VARCHAR(100)` | NULL |  | v1170 | v1170 |
| 14 | `TEM_FORNECEDOR` | `VARCHAR(1)` | NULL |  | v1170 | v1170 |
| 15 | `PESSOA_FORNECEDOR_CODIGO` | `VARCHAR(15)` | NULL |  | v1170 | v1170 |
| 16 | `PESSOA_FORNECEDOR_TIPO` | `VARCHAR(3)` | NULL |  | v1170 | v1170 |
| 17 | `PESSOA_FORNECEDOR_SEQUENCIA` | `INTEGER` | NULL |  | v1170 | v1170 |
| 18 | `UN_SUBUNIDADE_DESCRICAO` | `VARCHAR(150) CHARACTER SET WIN1252` | NULL |  | v1170 | v1173 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1170 | CREATE | CREATE TABLE com 18 colunas |
| 1173 | RENAME_COL | × DESCRICAO → UN_SUBUNIDADE_DESCRICAO |
| 1173 | ALTER_TYPE | ~ UN_SUBUNIDADE_DESCRICAO TYPE VARCHAR(150) CHARACTER SET WIN1252 |

