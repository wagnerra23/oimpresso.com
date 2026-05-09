---
table: PRODUTO_MOVIMENTO
module: estoque
created_at_version: 24
last_modified_version: 1149
target_version: 1468
columns_count: 15
foreign_keys_count: 2
foreign_keys:
  CODFORNECEDOR: FORNECEDOR
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_MOVIMENTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 24;
- **Última mudança:** UPDATE 1149;
- **Total colunas (versão 1468):** 15

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODFORNECEDOR` | [`FORNECEDOR`](../../cadastros/tabelas/FORNECEDOR.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `STATUS` | `VARCHAR(10)` | NULL |  | v35 | v35 |
| 2 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v102 | v102 |
| 3 | `CODUSUARIO` | `integer` | NULL | → `USUARIO` | v164 | v164 |
| 4 | `CODFORNECEDOR` | `varchar (10)` | NULL | → `FORNECEDOR` | v164 | v164 |
| 5 | `FORM` | `varchar (50)` | NULL |  | v164 | v164 |
| 6 | `CUSTO_COMPOSICAO` | `double precision` | NULL |  | v610 | v610 |
| 7 | `VALOR_COMPOSICAO` | `double precision` | NULL |  | v631 | v631 |
| 8 | `CUSTO_CENTRO_TRABALHO` | `double precision` | NULL |  | v631 | v631 |
| 9 | `AJUSTE_SALDO` | `DOM_BOOLEAN` | NULL |  | v682 | v682 |
| 10 | `ATIVO` | `VARCHAR(1)` | NULL |  | v730 | v730 |
| 11 | `PRODUTO_ESTOQUE_LOCAL` | `VARCHAR(15)` | NULL |  | v758 | v761 |
| 12 | `TIPO_USO` | `VARCHAR(50)` | NULL |  | v971 | v971 |
| 13 | `QUANT_ANTIGA` | `DOUBLE PRECISION, ADD QUANT_ATUAL DOUBLE PRECISION` | NULL |  | v971 | v971 |
| 14 | `NATUREZA` | `VARCHAR(100)` | NULL |  | v996 | v996 |
| 15 | `PESSOA_FUNCIONARIO_CODIGO` | `VARCHAR(10)` | NULL |  | v1149 | v1149 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 24 | ADD_COL | + ESTOQUE_LOCAL VARCHAR(15) |
| 35 | ADD_COL | + STATUS VARCHAR(10) |
| 102 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 164 | ADD_COL | + CODUSUARIO integer |
| 164 | ADD_COL | + CODFORNECEDOR varchar (10) |
| 164 | ADD_COL | + FORM varchar (50) |
| 610 | ADD_COL | + CUSTO_COMPOSICAO double precision |
| 631 | ADD_COL | + VALOR_COMPOSICAO double precision |
| 631 | ADD_COL | + CUSTO_CENTRO_TRABALHO double precision |
| 682 | ADD_COL | + AJUSTE_SALDO DOM_BOOLEAN |
| 721 | RENAME_COL | × CUSTO_LOJA → CUSTO_VENDA_TOTAL |
| 730 | ADD_COL | + ATIVO VARCHAR(1) |
| 758 | RENAME_COL | × ESTOQUE_LOCAL → PRODUTO_ESTOQUE_LOCAL |
| 758 | ADD_COL | + ESTOQUE_LOCAL VARCHAR(15) |
| 758 | DROP_COL | - VALOR_CUSTO |
| 758 | DROP_COL | - VALOR_LOJA |
| 758 | DROP_COL | - PRODUTO_ESTOQUE_LOCAL |
| 761 | RENAME_COL | × ESTOQUE_LOCAL → PRODUTO_ESTOQUE_LOCAL |
| 971 | ADD_COL | + TIPO_USO VARCHAR(50) |
| 971 | ADD_COL | + QUANT_ANTIGA DOUBLE PRECISION, ADD QUANT_ATUAL DOUBLE PRECISION |
| 996 | ADD_COL | + NATUREZA VARCHAR(100) |
| 1149 | ADD_COL | + PESSOA_FUNCIONARIO_CODIGO VARCHAR(10) |

