---
table: CIDADES
module: cadastros
created_at_version: 21
last_modified_version: 1300
target_version: 1468
columns_count: 16
foreign_keys_count: 1
foreign_keys:
  CODPAIS: PAIS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CIDADES`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 21;
- **Última mudança:** UPDATE 1300;
- **Total colunas (versão 1468):** 16

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPAIS` | [`PAIS`](../../cadastros/tabelas/PAIS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `PAIS` | `VARCHAR(50)` | NULL |  | v21 | v21 |
| 2 | `CODPAIS` | `INTEGER` | NULL | → `PAIS` | v21 | v21 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v49 | v49 |
| 4 | `COD_CIDADE_PROPRIO` | `VARCHAR(15)` | NULL |  | v599 | v599 |
| 5 | `NFSE_PROVEDOR` | `VARCHAR(50)` | NULL |  | v705 | v705 |
| 6 | `ATIVO` | `VARCHAR(1)` | NULL |  | v730 | v730 |
| 7 | `NFSE_RPS_SERIE_H` | `VARCHAR(50), ADD NFSE_RPS_SERIE_P VARCHAR(50)` | NULL |  | v909 | v909 |
| 8 | `NFSE_HOMOLOGADO` | `VARCHAR(1), ADD ALIQUOTA_NO_XML VARCHAR(50), ADD TIPO_RPS VARCHAR(50), ADD METODO_ENVIO VARCHAR(50), ADD TEM_LC116 VARCHAR(1), ADD TEM_HOMOLOGACAO VARCHAR(1), ADD TEM_MULTIPLOS_SERVICOS VARCHAR(1), ADD TEM_CERTIFICADO_DIGITAL VARCHAR(1)` | NULL |  | v909 | v909 |
| 9 | `NFSE_NOMEURL_H` | `VARCHAR(255), ADD NOMEURL_P VARCHAR(255), ADD VERSAODADOS VARCHAR(10), ADD VERSAOATRIB VARCHAR(10)` | NULL |  | v909 | v909 |
| 10 | `NFSE_LINKURL_H` | `VARCHAR(500)` | NULL |  | v960 | v960 |
| 11 | `NFSE_LINKURL_P` | `VARCHAR(500)` | NULL |  | v960 | v960 |
| 12 | `NFSE_BANCO_P` | `VARCHAR(50)` | NULL |  | v960 | v960 |
| 13 | `OIMPRESSO_CODIGO` | `INTEGER` | NULL |  | v1228 | v1228 |
| 14 | `OIMPRESSO_DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1228 | v1228 |
| 15 | `OIMPRESSO_ATIVO` | `VARCHAR(1)` | NULL |  | v1228 | v1228 |
| 16 | `NFSE_PROVEDOR_VERSAO` | `VARCHAR(10)` | NULL |  | v1300 | v1300 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 21 | ADD_COL | + PAIS VARCHAR(50) |
| 21 | ADD_COL | + CODPAIS INTEGER |
| 49 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 473 | ADD_COL | + COD_CIDADE_PROPRIO VARCHAR(15) |
| 487 | ADD_COL | + NFSE_PROVEDOR VARCHAR(50) |
| 487 | ADD_COL | + COD_CIDADE_PROPRIO VARCHAR(15) |
| 571 | ADD_COL | + NFSE_PROVEDOR VARCHAR(50) |
| 571 | ADD_COL | + COD_CIDADE_PROPRIO VARCHAR(15) |
| 599 | ADD_COL | + NFSE_PROVEDOR VARCHAR(50) |
| 599 | ADD_COL | + COD_CIDADE_PROPRIO VARCHAR(15) |
| 662 | ADD_COL | + NFSE_PROVEDOR VARCHAR(50) |
| 705 | ADD_COL | + NFSE_INI VARCHAR(5000) |
| 705 | ADD_COL | + NFSE_PROVEDOR VARCHAR(50) |
| 730 | ADD_COL | + ATIVO VARCHAR(1) |
| 909 | ADD_COL | + HOMOLOGADO VARCHAR(1), ADD ALIQUOTA_NO_XML VARCHAR(50), ADD TIPO_RPS VARCHAR(50), ADD METODO_ENVIO VARCHAR(50), ADD TEM_LC116 VARCHAR(1), ADD TEM_HOMOLOGACAO VARCHAR(1), ADD TEM_MULTIPLOS_SERVICOS VARCHAR(1), ADD TEM_CERTIFICADO_DIGITAL VARCHAR(1) |
| 909 | ADD_COL | + LINK VARCHAR(1200) |
| 909 | ADD_COL | + NOMEURL_H VARCHAR(255), ADD NOMEURL_P VARCHAR(255), ADD VERSAODADOS VARCHAR(10), ADD VERSAOATRIB VARCHAR(10) |
| 909 | ADD_COL | + NFSE_provedor_INI BLOB SUB_TYPE 1 SEGMENT SIZE 80 |
| 909 | ADD_COL | + PROVEDOR VARCHAR(50) |
| 909 | ADD_COL | + NFSE_RPS_SERIE_H VARCHAR(50), ADD NFSE_RPS_SERIE_P VARCHAR(50) |
| 909 | RENAME_COL | × HOMOLOGADO → NFSE_HOMOLOGADO |
| 909 | RENAME_COL | × NOMEURL_H → NFSE_NOMEURL_H |
| 909 | RENAME_COL | × NOMEURL_P → NFSE_NOMEURL_P |
| 909 | DROP_COL | - PROVEDOR |
| 909 | RENAME_COL | × NFSE_PROVEDOR_INI → INI |
| 909 | DROP_COL | - INI |
| 909 | DROP_COL | - LINK |
| 960 | ADD_COL | + NFSE_LINKURL_H VARCHAR(500) |
| 960 | ADD_COL | + NFSE_LINKURL_P VARCHAR(500) |
| 960 | ADD_COL | + NFSE_BANCO_P VARCHAR(50) |
| 960 | DROP_COL | - TEM_CERTIFICADO_DIGITAL |
| 960 | DROP_COL | - ALIQUOTA_NO_XML |
| 960 | DROP_COL | - NFSE_INI |
| 960 | DROP_COL | - METODO_ENVIO |
| 960 | DROP_COL | - TIPO_RPS |
| 1228 | ADD_COL | + OIMPRESSO_CODIGO INTEGER |
| 1228 | ADD_COL | + OIMPRESSO_DT_ALTERACAO TIMESTAMP |
| 1228 | ADD_COL | + OIMPRESSO_ATIVO VARCHAR(1) |
| 1300 | ADD_COL | + NFSE_PROVEDOR_VERSAO VARCHAR(10) |

