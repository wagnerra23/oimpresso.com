---
table: UNIDADE_SUBUNIDADE
module: estoque
created_at_version: 1154
last_modified_version: 1180
target_version: 1468
columns_count: 18
foreign_keys_count: 2
foreign_keys:
  CODUNIDADE: UNIDADE
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `UNIDADE_SUBUNIDADE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1154;
- **Última mudança:** UPDATE 1180;
- **Total colunas (versão 1468):** 18

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODUNIDADE` | [`UNIDADE`](../../estoque/tabelas/UNIDADE.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1154 | v1154 |
| 2 | `CODUNIDADE` | `INTEGER` | NULL | → `UNIDADE` | v1154 | v1154 |
| 3 | `SUBUNIDADE` | `VARCHAR(6)` | NULL |  | v1154 | v1154 |
| 4 | `QTDADEPECA` | `DOUBLE PRECISION` | NULL |  | v1154 | v1154 |
| 5 | `COMP` | `DOUBLE PRECISION` | NULL |  | v1154 | v1154 |
| 6 | `LARG` | `DOUBLE PRECISION` | NULL |  | v1154 | v1154 |
| 7 | `ESPESSURA` | `DOUBLE PRECISION` | NULL |  | v1154 | v1154 |
| 8 | `FORMULA` | `VARCHAR(50)` | NULL |  | v1157 | v1157 |
| 9 | `TEM_DIVISAO_MATERIAL` | `VARCHAR(1)` | NULL |  | v1157 | v1157 |
| 10 | `DESCRICAO_PERSONALIZADA` | `VARCHAR(50)` | NULL |  | v1166 | v1166 |
| 11 | `ESPECIFICACAO` | `VARCHAR(100)` | NULL |  | v1167 | v1167 |
| 12 | `RENDIMENTO` | `DOUBLE PRECISION` | NULL |  | v1154 | v1168 |
| 13 | `TEM_FORNECEDOR` | `VARCHAR(1)` | NULL |  | v1169 | v1169 |
| 14 | `PESSOA_FORNECEDOR_CODIGO` | `VARCHAR(15)` | NULL |  | v1169 | v1169 |
| 15 | `PESSOA_FORNECEDOR_TIPO` | `VARCHAR(3)` | NULL |  | v1169 | v1169 |
| 16 | `PESSOA_FORNECEDOR_SEQUENCIA` | `INTEGER` | NULL |  | v1169 | v1169 |
| 17 | `UN_SUBUNIDADE_DESCRICAO` | `VARCHAR(150) CHARACTER SET WIN1252` | NULL |  | v1157 | v1173 |
| 18 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v1180 | v1180 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1154 | CREATE | CREATE TABLE com 8 colunas |
| 1157 | ADD_COL | + DESCRICAO VARCHAR(50) |
| 1157 | ADD_COL | + FORMULA VARCHAR(50) |
| 1157 | ADD_COL | + TEM_DIVISAO_MATERIAL VARCHAR(1) |
| 1166 | ADD_COL | + DESCRICAO_PERSONALIZADA VARCHAR(50) |
| 1167 | ADD_COL | + ESPECIFICACAO VARCHAR(100) |
| 1168 | RENAME_COL | × QUANT → RENDIMENTO |
| 1169 | ADD_COL | + TEM_FORNECEDOR VARCHAR(1) |
| 1169 | ADD_COL | + PESSOA_FORNECEDOR_CODIGO VARCHAR(15) |
| 1169 | ADD_COL | + PESSOA_FORNECEDOR_TIPO VARCHAR(3) |
| 1169 | ADD_COL | + PESSOA_FORNECEDOR_SEQUENCIA INTEGER |
| 1171 | ALTER_TYPE | ~ DESCRICAO TYPE VARCHAR(150) CHARACTER SET WIN1252 |
| 1173 | RENAME_COL | × DESCRICAO → UN_SUBUNIDADE_DESCRICAO |
| 1180 | ADD_COL | + CODUSUARIO INTEGER |

