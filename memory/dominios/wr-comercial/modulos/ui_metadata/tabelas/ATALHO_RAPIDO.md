---
id: dominios-wr-comercial-modulos-ui-metadata-tabelas-atalho-rapido
table: ATALHO_RAPIDO
module: ui_metadata
created_at_version: 994
last_modified_version: 995
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

# `ATALHO_RAPIDO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `ui_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 994;
- **Última mudança:** UPDATE 995;
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
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v994 | v994 |
| 2 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v994 | v994 |
| 3 | `DESCRICAO` | `VARCHAR(500)` | NULL |  | v994 | v994 |
| 4 | `QUANT_REGISTROS` | `INTEGER` | NULL |  | v994 | v994 |
| 5 | `GRAFICO_PERIODO` | `VARCHAR(10)` | NULL |  | v994 | v994 |
| 6 | `GRAFICO_TIPO` | `VARCHAR(20)` | NULL |  | v994 | v994 |
| 7 | `ATIVO` | `VARCHAR(1)` | NULL |  | v994 | v994 |
| 8 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v994 | v994 |
| 9 | `WIDTH` | `INTEGER` | NULL |  | v994 | v994 |
| 10 | `HEIGHT` | `INTEGER` | NULL |  | v994 | v994 |
| 11 | `CODCONFIGURACAO_FILTRO` | `INTEGER` | NULL | → `CONFIGURACAO_FILTRO` | v994 | v994 |
| 12 | `CODCONFIGURACAO_AGRUPAMENTO` | `INTEGER` | NULL | → `CONFIGURACAO_AGRUPAMENTO` | v994 | v994 |
| 13 | `CODCONFIGURACAO_FORM` | `INTEGER` | NULL | → `CONFIGURACAO_FORM` | v994 | v994 |
| 14 | `TEM_PERIODO` | `VARCHAR(1)` | NULL |  | v994 | v994 |
| 15 | `TEM_QUANT_REGISTROS` | `VARCHAR(1)` | NULL |  | v994 | v994 |
| 16 | `SQL` | `VARCHAR(5000)` | NULL |  | v994 | v994 |
| 17 | `CAMPO` | `VARCHAR(100)` | NULL |  | v994 | v994 |
| 18 | `FORMATO` | `VARCHAR(50)` | NULL |  | v994 | v994 |
| 19 | `PERIODO` | `VARCHAR(20)` | NULL |  | v994 | v994 |
| 20 | `ABA` | `VARCHAR(50)` | NULL |  | v994 | v994 |
| 21 | `OBSERVACAO` | `VARCHAR(500)` | NULL |  | v994 | v994 |
| 22 | `BLOCO` | `VARCHAR(20)` | NULL |  | v994 | v994 |
| 23 | `FILTRO` | `VARCHAR(1000)` | NULL |  | v994 | v994 |
| 24 | `AGRUPAMENTO` | `VARCHAR(500)` | NULL |  | v994 | v994 |
| 25 | `CAMPOPERIODO` | `VARCHAR(255)` | NULL |  | v994 | v994 |
| 26 | `CAMPO_CATEGORIA` | `VARCHAR(100)` | NULL |  | v994 | v994 |
| 27 | `GRAFICO` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v994 | v994 |
| 28 | `COLUNA1` | `VARCHAR(250)` | NULL |  | v994 | v994 |
| 29 | `COLUNA2` | `VARCHAR(250)` | NULL |  | v994 | v994 |
| 30 | `COLUNA3` | `VARCHAR(250)` | NULL |  | v994 | v994 |
| 31 | `COLUNA4` | `VARCHAR(250)` | NULL |  | v994 | v994 |
| 32 | `COLUNA5` | `VARCHAR(250)` | NULL |  | v994 | v994 |
| 33 | `COLUNA6` | `VARCHAR(250)` | NULL |  | v994 | v994 |
| 34 | `COLUNA7` | `VARCHAR(250)` | NULL |  | v994 | v994 |
| 35 | `FORMCADASTRO` | `VARCHAR(255), ADD FORMCONSULTA VARCHAR(255)` | NULL |  | v994 | v994 |
| 36 | `FILTRO_GRID` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v995 | v995 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 994 | CREATE | CREATE TABLE com 34 colunas |
| 994 | ADD_COL | + FORMCADASTRO VARCHAR(255), ADD FORMCONSULTA VARCHAR(255) |
| 995 | ADD_COL | + FILTRO_GRID BLOB SUB_TYPE 1 SEGMENT SIZE 80 |

