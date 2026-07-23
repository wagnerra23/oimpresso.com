---
id: dominios-wr-comercial-modulos-bi-tabelas-dashboards
table: DASHBOARDS
module: bi
created_at_version: 1406
last_modified_version: 1406
target_version: 1468
columns_count: 36
foreign_keys_count: 4
foreign_keys:
  CODCONFIGURACAO_AGRUPAMENTO: CONFIGURACAO_AGRUPAMENTO
  CODCONFIGURACAO_FILTRO: CONFIGURACAO_FILTRO
  CODCONFIGURACAO_FORM: CONFIGURACAO_FORM
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `DASHBOARDS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `bi` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1406;
- **Última mudança:** UPDATE 1406;
- **Total colunas (versão 1468):** 36

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCONFIGURACAO_AGRUPAMENTO` | [`CONFIGURACAO_AGRUPAMENTO`](../../configuracao/tabelas/CONFIGURACAO_AGRUPAMENTO.md) |
| `CODCONFIGURACAO_FILTRO` | [`CONFIGURACAO_FILTRO`](../../configuracao/tabelas/CONFIGURACAO_FILTRO.md) |
| `CODCONFIGURACAO_FORM` | [`CONFIGURACAO_FORM`](../../configuracao/tabelas/CONFIGURACAO_FORM.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `integer` | NOT NULL |  | v1406 | v1406 |
| 2 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v1406 | v1406 |
| 3 | `DESCRICAO` | `VARCHAR(500)` | NULL |  | v1406 | v1406 |
| 4 | `QUANT_REGISTROS` | `INTEGER` | NULL |  | v1406 | v1406 |
| 5 | `GRAFICO_PERIODO` | `VARCHAR(10)` | NULL |  | v1406 | v1406 |
| 6 | `GRAFICO_TIPO` | `VARCHAR(20)` | NULL |  | v1406 | v1406 |
| 7 | `ativo` | `varchar(1)` | NULL |  | v1406 | v1406 |
| 8 | `dt_alteracao` | `timestamp` | NULL |  | v1406 | v1406 |
| 9 | `WIDTH` | `INTEGER` | NULL |  | v1406 | v1406 |
| 10 | `FILTRO` | `VARCHAR(1000)` | NULL |  | v1406 | v1406 |
| 11 | `CAMPOPERIODO` | `VARCHAR(255)` | NULL |  | v1406 | v1406 |
| 12 | `CAMPO_CATEGORIA` | `VARCHAR(100)` | NULL |  | v1406 | v1406 |
| 13 | `GRAFICO` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v1406 | v1406 |
| 14 | `TEM_PRINCIPAL` | `VARCHAR(1)` | NULL |  | v1406 | v1406 |
| 15 | `COLUNA1` | `varchar (250)` | NULL |  | v1406 | v1406 |
| 16 | `COLUNA2` | `varchar (250)` | NULL |  | v1406 | v1406 |
| 17 | `COLUNA3` | `varchar (250)` | NULL |  | v1406 | v1406 |
| 18 | `COLUNA4` | `varchar (250)` | NULL |  | v1406 | v1406 |
| 19 | `COLUNA5` | `varchar (250)` | NULL |  | v1406 | v1406 |
| 20 | `COLUNA6` | `varchar (250)` | NULL |  | v1406 | v1406 |
| 21 | `COLUNA7` | `varchar (250)` | NULL |  | v1406 | v1406 |
| 22 | `COR` | `INTEGER` | NULL |  | v1406 | v1406 |
| 23 | `HEIGHT` | `INTEGER` | NULL |  | v1406 | v1406 |
| 24 | `CODCONFIGURACAO_FILTRO` | `INTEGER` | NULL | → `CONFIGURACAO_FILTRO` | v1406 | v1406 |
| 25 | `CODCONFIGURACAO_AGRUPAMENTO` | `INTEGER` | NULL | → `CONFIGURACAO_AGRUPAMENTO` | v1406 | v1406 |
| 26 | `CODCONFIGURACAO_FORM` | `INTEGER` | NULL | → `CONFIGURACAO_FORM` | v1406 | v1406 |
| 27 | `TEM_PERIODO` | `VARCHAR(1)` | NULL |  | v1406 | v1406 |
| 28 | `TEM_QUANT_REGISTROS` | `VARCHAR(1)` | NULL |  | v1406 | v1406 |
| 29 | `SQL` | `VARCHAR(5000)` | NULL |  | v1406 | v1406 |
| 30 | `CAMPO` | `VARCHAR(100)` | NULL |  | v1406 | v1406 |
| 31 | `FORMATO` | `VARCHAR(50)` | NULL |  | v1406 | v1406 |
| 32 | `PERIODO` | `VARCHAR(20)` | NULL |  | v1406 | v1406 |
| 33 | `ABA` | `VARCHAR(50)` | NULL |  | v1406 | v1406 |
| 34 | `OBSERVACAO` | `VARCHAR(500)` | NULL |  | v1406 | v1406 |
| 35 | `BLOCO` | `VARCHAR(20)` | NULL |  | v1406 | v1406 |
| 36 | `AGRUPAMENTO` | `VARCHAR(500)` | NULL |  | v1406 | v1406 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 923 | CREATE | CREATE TABLE com 9 colunas |
| 927 | ADD_COL | + WIDTH INTEGER, ADD HEIGHT INTEGER, ADD CODCONFIGURACAO_FILTRO INTEGER, ADD CODCONFIGURACAO_AGRUPAMENTO INTEGER, ADD CODCONFIGURACAO_FORM INTEGER, ADD TEM_PERIODO VARCHAR(1), ADD TEM_QUANT_REGISTROS VARCHAR(1), ADD SQL VARCHAR(500), ADD CAMPO VARCHAR(100), ADD FORMATO VARCHAR(50), ADD PERIODO VARCHAR(20), ADD ABA VARCHAR(50), ADD OBSERVACAO VARCHAR(500), ADD BLOCO VARCHAR(20) |
| 927 | DROP_COL | - TITULO |
| 933 | ADD_COL | + FILTRO VARCHAR(1000), ADD AGRUPAMENTO VARCHAR(500) |
| 937 | ADD_COL | + CAMPOPERIODO VARCHAR(255) |
| 956 | ADD_COL | + CAMPO_CATEGORIA VARCHAR(100) |
| 956 | ADD_COL | + GRAFICO BLOB SUB_TYPE 1 SEGMENT SIZE 80 |
| 975 | ADD_COL | + TEM_PRINCIPAL VARCHAR(1) |
| 985 | ADD_COL | + COLUNA1 varchar (250) |
| 985 | ADD_COL | + COLUNA2 varchar (250) |
| 985 | ADD_COL | + COLUNA3 varchar (250) |
| 985 | ADD_COL | + COLUNA4 varchar (250) |
| 985 | ADD_COL | + COLUNA5 varchar (250) |
| 985 | ADD_COL | + COLUNA6 varchar (250) |
| 985 | ADD_COL | + COLUNA7 varchar (250) |
| 988 | ALTER_TYPE | ~ SQL TYPE VARCHAR(5000) |
| 994 | ADD_COL | + COR INTEGER |
| 1406 | CREATE | CREATE TABLE com 9 colunas |
| 1406 | ADD_COL | + WIDTH INTEGER |
| 1406 | ADD_COL | + HEIGHT INTEGER |
| 1406 | ADD_COL | + CODCONFIGURACAO_FILTRO INTEGER |
| 1406 | ADD_COL | + CODCONFIGURACAO_AGRUPAMENTO INTEGER |
| 1406 | ADD_COL | + CODCONFIGURACAO_FORM INTEGER |
| 1406 | ADD_COL | + TEM_PERIODO VARCHAR(1) |
| 1406 | ADD_COL | + TEM_QUANT_REGISTROS VARCHAR(1) |
| 1406 | ADD_COL | + SQL VARCHAR(500) |
| 1406 | ADD_COL | + CAMPO VARCHAR(100) |
| 1406 | ADD_COL | + FORMATO VARCHAR(50) |
| 1406 | ADD_COL | + PERIODO VARCHAR(20) |
| 1406 | ADD_COL | + ABA VARCHAR(50) |
| 1406 | ADD_COL | + OBSERVACAO VARCHAR(500) |
| 1406 | ADD_COL | + BLOCO VARCHAR(20) |
| 1406 | DROP_COL | - TITULO |
| 1406 | ADD_COL | + FILTRO VARCHAR(1000) |
| 1406 | ADD_COL | + AGRUPAMENTO VARCHAR(500) |
| 1406 | ADD_COL | + CAMPOPERIODO VARCHAR(255) |
| 1406 | ADD_COL | + CAMPO_CATEGORIA VARCHAR(100) |
| 1406 | ADD_COL | + GRAFICO BLOB SUB_TYPE 1 SEGMENT SIZE 80 |
| 1406 | ADD_COL | + TEM_PRINCIPAL VARCHAR(1) |
| 1406 | ADD_COL | + COLUNA1 varchar (250) |
| 1406 | ADD_COL | + COLUNA2 varchar (250) |
| 1406 | ADD_COL | + COLUNA3 varchar (250) |
| 1406 | ADD_COL | + COLUNA4 varchar (250) |
| 1406 | ADD_COL | + COLUNA5 varchar (250) |
| 1406 | ADD_COL | + COLUNA6 varchar (250) |
| 1406 | ADD_COL | + COLUNA7 varchar (250) |
| 1406 | ALTER_TYPE | ~ SQL TYPE VARCHAR(5000) |
| 1406 | ADD_COL | + COR INTEGER |

