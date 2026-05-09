---
table: PRODUTO_FORNECEDOR
module: estoque
created_at_version: 230
last_modified_version: 1297
target_version: 1468
columns_count: 12
foreign_keys_count: 2
foreign_keys:
  CODPRODUTO: PRODUTO
  CODUSUARIO_AUTORIZOU: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_FORNECEDOR`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 230;
- **Última mudança:** UPDATE 1297;
- **Total colunas (versão 1468):** 12

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPRODUTO` | [`PRODUTO`](../../estoque/tabelas/PRODUTO.md) |
| `CODUSUARIO_AUTORIZOU` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODPRODUTO` | `VARCHAR(15)` | NOT NULL | → `PRODUTO` | v230 | v230 |
| 2 | `DT_ULTIMA_COMPRA` | `timestamp` | NULL |  | v695 | v695 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v230 | v230 |
| 4 | `OBSERVACAO` | `BLOB SUB_TYPE 1 SEGMENT SIZE 1024` | NULL |  | v469 | v469 |
| 5 | `CODFABRICA` | `VARCHAR(60)` | NULL |  | v681 | v681 |
| 6 | `CUSTO_FABR` | `DOUBLE PRECISION` | NULL |  | v230 | v685 |
| 7 | `CUSTO_VENDA` | `DOUBLE PRECISION` | NULL |  | v685 | v685 |
| 8 | `PESSOA_RESPONSAVEL_CODIGO` | `VARCHAR(10)` | NOT NULL |  | v230 | v754 |
| 9 | `PESSOA_RESPONSAVEL_TIPO` | `VARCHAR(3)` | NOT NULL |  | v230 | v754 |
| 10 | `PESSOA_RESPONSAVEL_SEQUENCIA` | `INTEGER` | NOT NULL |  | v230 | v754 |
| 11 | `CODFABRICA_ORIGINAL` | `VARCHAR(200)` | NULL |  | v1297 | v1297 |
| 12 | `CODUSUARIO_AUTORIZOU` | `INTEGER` | NULL | → `USUARIO` | v1297 | v1297 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 230 | CREATE | CREATE TABLE com 7 colunas |
| 469 | ADD_COL | + OBSERVACAO BLOB SUB_TYPE 1 SEGMENT SIZE 1024 |
| 681 | ADD_COL | + CODFABRICA VARCHAR(60) |
| 685 | RENAME_COL | × VALOR → CUSTO_FABR |
| 685 | ADD_COL | + CUSTO_VENDA DOUBLE PRECISION |
| 695 | ADD_COL | + DT_ULTIMA_COMPRA timestamp |
| 754 | RENAME_COL | × PESSOA_FORNECEDOR_CODIGO → PESSOA_RESPONSAVEL_CODIGO |
| 754 | RENAME_COL | × PESSOA_FORNECEDOR_TIPO → PESSOA_RESPONSAVEL_TIPO |
| 754 | RENAME_COL | × PESSOA_FORNECEDOR_SEQUENCIA → PESSOA_RESPONSAVEL_SEQUENCIA |
| 1297 | ADD_COL | + CODFABRICA_ORIGINAL VARCHAR(200) |
| 1297 | ADD_COL | + CODUSUARIO_AUTORIZOU INTEGER |

