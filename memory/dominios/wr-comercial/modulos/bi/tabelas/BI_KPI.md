---
id: dominios-wr-comercial-modulos-bi-tabelas-bi-kpi
table: BI_KPI
module: bi
created_at_version: 1254
last_modified_version: 1254
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

# `BI_KPI`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `bi` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1254;
- **Última mudança:** UPDATE 1254;
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
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1254 | v1254 |
| 2 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v1254 | v1254 |
| 3 | `DESCRICAO` | `VARCHAR(500)` | NULL |  | v1254 | v1254 |
| 4 | `QUANT_REGISTROS` | `INTEGER` | NULL |  | v1254 | v1254 |
| 5 | `GRAFICO_PERIODO` | `VARCHAR(10)` | NULL |  | v1254 | v1254 |
| 6 | `GRAFICO_TIPO` | `VARCHAR(20)` | NULL |  | v1254 | v1254 |
| 7 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1254 | v1254 |
| 8 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1254 | v1254 |
| 9 | `WIDTH` | `INTEGER` | NULL |  | v1254 | v1254 |
| 10 | `HEIGHT` | `INTEGER` | NULL |  | v1254 | v1254 |
| 11 | `CODCONFIGURACAO_FILTRO` | `INTEGER` | NULL | → `CONFIGURACAO_FILTRO` | v1254 | v1254 |
| 12 | `CODCONFIGURACAO_AGRUPAMENTO` | `INTEGER` | NULL | → `CONFIGURACAO_AGRUPAMENTO` | v1254 | v1254 |
| 13 | `CODCONFIGURACAO_FORM` | `INTEGER` | NULL | → `CONFIGURACAO_FORM` | v1254 | v1254 |
| 14 | `TEM_PERIODO` | `VARCHAR(1)` | NULL |  | v1254 | v1254 |
| 15 | `TEM_QUANT_REGISTROS` | `VARCHAR(1)` | NULL |  | v1254 | v1254 |
| 16 | `SQL` | `VARCHAR(5000)` | NULL |  | v1254 | v1254 |
| 17 | `CAMPO` | `VARCHAR(100)` | NULL |  | v1254 | v1254 |
| 18 | `FORMATO` | `VARCHAR(50)` | NULL |  | v1254 | v1254 |
| 19 | `PERIODO` | `VARCHAR(20)` | NULL |  | v1254 | v1254 |
| 20 | `ABA` | `VARCHAR(50)` | NULL |  | v1254 | v1254 |
| 21 | `OBSERVACAO` | `VARCHAR(500)` | NULL |  | v1254 | v1254 |
| 22 | `BLOCO` | `VARCHAR(20)` | NULL |  | v1254 | v1254 |
| 23 | `FILTRO` | `VARCHAR(1000)` | NULL |  | v1254 | v1254 |
| 24 | `AGRUPAMENTO` | `VARCHAR(500)` | NULL |  | v1254 | v1254 |
| 25 | `CAMPOPERIODO` | `VARCHAR(255)` | NULL |  | v1254 | v1254 |
| 26 | `CAMPO_CATEGORIA` | `VARCHAR(100)` | NULL |  | v1254 | v1254 |
| 27 | `GRAFICO` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v1254 | v1254 |
| 28 | `TEM_PRINCIPAL` | `VARCHAR(1)` | NULL |  | v1254 | v1254 |
| 29 | `COLUNA1` | `VARCHAR(250)` | NULL |  | v1254 | v1254 |
| 30 | `COLUNA2` | `VARCHAR(250)` | NULL |  | v1254 | v1254 |
| 31 | `COLUNA3` | `VARCHAR(250)` | NULL |  | v1254 | v1254 |
| 32 | `COLUNA4` | `VARCHAR(250)` | NULL |  | v1254 | v1254 |
| 33 | `COLUNA5` | `VARCHAR(250)` | NULL |  | v1254 | v1254 |
| 34 | `COLUNA6` | `VARCHAR(250)` | NULL |  | v1254 | v1254 |
| 35 | `COLUNA7` | `VARCHAR(250)` | NULL |  | v1254 | v1254 |
| 36 | `COR` | `INTEGER` | NULL |  | v1254 | v1254 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1254 | CREATE | CREATE TABLE com 36 colunas |

