---
table: VENDA_FINANCEIRO
module: vendas
created_at_version: 102
last_modified_version: 1444
target_version: 1468
columns_count: 15
foreign_keys_count: 1
foreign_keys:
  CODVENDA_PRODUTO: VENDA_PRODUTO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `VENDA_FINANCEIRO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `vendas` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 102;
- **Última mudança:** UPDATE 1444;
- **Total colunas (versão 1468):** 15

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODVENDA_PRODUTO` | [`VENDA_PRODUTO`](../../vendas/tabelas/VENDA_PRODUTO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v102 | v102 |
| 2 | `PESSOA_FORNECEDOR_CODIGO` | `varchar(10)` | NULL |  | v228 | v228 |
| 3 | `PESSOA_FORNECEDOR_TIPO` | `varchar(3)` | NULL |  | v228 | v228 |
| 4 | `PESSOA_FORNECEDOR_SEQUENCIA` | `integer` | NULL |  | v228 | v228 |
| 5 | `PARCELA` | `integer` | NULL |  | v228 | v228 |
| 6 | `PREVISAO` | `double precision` | NULL |  | v381 | v381 |
| 7 | `GERADO_DO_FINANCEIRO` | `varchar (1)` | NULL |  | v422 | v422 |
| 8 | `PARCELA_ALTERADA` | `varchar (1)` | NULL |  | v422 | v422 |
| 9 | `NSU` | `VARCHAR(100)` | NULL |  | v1017 | v1017 |
| 10 | `REDE` | `VARCHAR(50)` | NULL |  | v1017 | v1017 |
| 11 | `CNPJ_CREDENCIADORA` | `VARCHAR(20)` | NULL |  | v1017 | v1017 |
| 12 | `LANCAMENTO_FUTURO` | `VARCHAR(1)` | NULL |  | v1134 | v1134 |
| 13 | `EXIGE_TEF` | `VARCHAR(1)` | NULL |  | v1197 | v1197 |
| 14 | `NF_TIPO_PAGTO` | `VARCHAR(2)` | NULL |  | v1198 | v1198 |
| 15 | `CODVENDA_PRODUTO` | `INTEGER` | NULL | → `VENDA_PRODUTO` | v1444 | v1444 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 102 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 227 | ALTER_TYPE | ~ RAZAOSOCIAL TYPE varchar (150) |
| 228 | ADD_COL | + PESSOA_FORNECEDOR_CODIGO varchar(10) |
| 228 | ADD_COL | + PESSOA_FORNECEDOR_TIPO varchar(3) |
| 228 | ADD_COL | + PESSOA_FORNECEDOR_SEQUENCIA integer |
| 228 | ADD_COL | + PARCELA integer |
| 381 | ADD_COL | + PREVISAO double precision |
| 401 | ALTER_TYPE | ~ CONTATOS TYPE VARCHAR(400) |
| 422 | ADD_COL | + GERADO_DO_FINANCEIRO varchar (1) |
| 422 | ADD_COL | + PARCELA_ALTERADA varchar (1) |
| 427 | ALTER_TYPE | ~ TIPOPAGTO TYPE VARCHAR(50) |
| 499 | ALTER_TYPE | ~ CODPLANOCONTAS TYPE VARCHAR(30) CHARACTER SET WIN1252 |
| 1017 | ADD_COL | + NSU VARCHAR(100) |
| 1017 | ADD_COL | + REDE VARCHAR(50) |
| 1017 | ADD_COL | + CNPJ_CREDENCIADORA VARCHAR(20) |
| 1017 | ADD_COL | + TEF_STATUS VARCHAR(20) |
| 1134 | ADD_COL | + LANCAMENTO_FUTURO VARCHAR(1) |
| 1153 | DROP_COL | - TEF_STATUS |
| 1197 | ADD_COL | + EXIGE_TEF VARCHAR(1) |
| 1198 | ADD_COL | + NF_TIPO_PAGTO VARCHAR(2) |
| 1444 | ADD_COL | + CODVENDA_PRODUTO INTEGER |

