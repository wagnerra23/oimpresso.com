---
table: NF_PROVEDOR
module: nfe
created_at_version: 909
last_modified_version: 968
target_version: 1468
columns_count: 39
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_PROVEDOR`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 909;
- **Última mudança:** UPDATE 968;
- **Total colunas (versão 1468):** 39

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v909 | v909 |
| 2 | `DESCRICAO` | `VARCHAR(500)` | NULL |  | v909 | v909 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v909 | v909 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v909 | v909 |
| 5 | `INI` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v909 | v909 |
| 6 | `LINK` | `VARCHAR(1200)` | NULL |  | v909 | v909 |
| 7 | `TEM_RECSINCRONO` | `VARCHAR(1)` | NULL |  | v960 | v960 |
| 8 | `TEM_RECEPCIONAR` | `VARCHAR(1)` | NULL |  | v960 | v960 |
| 9 | `TEM_GERAR` | `VARCHAR(1)` | NULL |  | v960 | v960 |
| 10 | `TEM_CANCELAR` | `VARCHAR(1)` | NULL |  | v960 | v960 |
| 11 | `TEM_CONSNFSE` | `VARCHAR(1)` | NULL |  | v960 | v960 |
| 12 | `TEM_CONSNFSERPS` | `VARCHAR(1)` | NULL |  | v960 | v960 |
| 13 | `TEM_CONSLOTE` | `VARCHAR(1)` | NULL |  | v960 | v960 |
| 14 | `TEM_CONSSIT` | `VARCHAR(1)` | NULL |  | v960 | v960 |
| 15 | `TEM_CERTIFICADO_DIGITAL` | `VARCHAR(1)` | NULL |  | v960 | v960 |
| 16 | `TIPO_RPS` | `VARCHAR(50)` | NULL |  | v960 | v960 |
| 17 | `METODO_ENVIO` | `VARCHAR(50)` | NULL |  | v960 | v960 |
| 18 | `ALIQUOTA_NO_XML` | `VARCHAR(50)` | NULL |  | v960 | v960 |
| 19 | `TEM_IMPRESSAO_XML_WR` | `VARCHAR(1)` | NULL |  | v960 | v960 |
| 20 | `TEM_IMPRESSAOBETAFLYNOTA` | `VARCHAR(1)` | NULL |  | v960 | v960 |
| 21 | `TEM_QUEBRADELINHARETORNO` | `VARCHAR(1)` | NULL |  | v960 | v960 |
| 22 | `TEM_CARACTERESPECIAL` | `VARCHAR(1)` | NULL |  | v960 | v960 |
| 23 | `CNAE_OBRIGATORIO` | `VARCHAR(100)` | NULL |  | v964 | v964 |
| 24 | `TEM_OBRIGATORIO_IM` | `VARCHAR(1)` | NULL |  | v964 | v964 |
| 25 | `TEM_OBRIGATORIO_CNPJ` | `VARCHAR(1)` | NULL |  | v964 | v964 |
| 26 | `TEM_OBRIGATORIO_SENHA` | `VARCHAR(1)` | NULL |  | v964 | v964 |
| 27 | `TEM_OBRIGATORIO_LOGIN` | `VARCHAR(1)` | NULL |  | v964 | v964 |
| 28 | `LC116_CODIGOTRIBUTAVELMUNICIPIO` | `VARCHAR(100)` | NULL |  | v964 | v964 |
| 29 | `LC116_SERVICO` | `VARCHAR(100)` | NULL |  | v964 | v964 |
| 30 | `TEM_SUBSTITUIR` | `VARCHAR(1)` | NULL |  | v964 | v964 |
| 31 | `QUEBRADELINHA` | `VARCHAR(30)` | NULL |  | v960 | v964 |
| 32 | `TEM_ECOMERCIAL` | `VARCHAR(1)` | NULL |  | v964 | v964 |
| 33 | `TEM_TABULACAO` | `VARCHAR(1)` | NULL |  | v964 | v964 |
| 34 | `TEM_TAGQUEBRADELINHAUNICA` | `VARCHAR(1)` | NULL |  | v964 | v964 |
| 35 | `TEM_MULTIPLOS_SERVICOS` | `VARCHAR(1)` | NULL |  | v964 | v964 |
| 36 | `COMPETENCIA` | `VARCHAR(20)` | NULL |  | v967 | v967 |
| 37 | `TEM_CODPAIS` | `VARCHAR(1)` | NULL |  | v967 | v967 |
| 38 | `PODE_PREECHER_IE_Mesmo_Mun` | `VARCHAR(1)` | NULL |  | v967 | v967 |
| 39 | `TEM_CONSULTA_APOS_ENVIO` | `VARCHAR(1)` | NULL |  | v968 | v968 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 909 | CREATE | CREATE TABLE com 5 colunas |
| 909 | ADD_COL | + LINK VARCHAR(1200) |
| 960 | ADD_COL | + TEM_RECSINCRONO VARCHAR(1) |
| 960 | ADD_COL | + TEM_RECEPCIONAR VARCHAR(1) |
| 960 | ADD_COL | + TEM_GERAR VARCHAR(1) |
| 960 | ADD_COL | + TEM_CANCELAR VARCHAR(1) |
| 960 | ADD_COL | + TEM_CONSNFSE VARCHAR(1) |
| 960 | ADD_COL | + TEM_CONSNFSERPS VARCHAR(1) |
| 960 | ADD_COL | + TEM_CONSLOTE VARCHAR(1) |
| 960 | ADD_COL | + TEM_CONSSIT VARCHAR(1) |
| 960 | ADD_COL | + TEM_CERTIFICADO_DIGITAL VARCHAR(1) |
| 960 | ADD_COL | + TIPO_RPS VARCHAR(50) |
| 960 | ADD_COL | + METODO_ENVIO VARCHAR(50) |
| 960 | ADD_COL | + ALIQUOTA_NO_XML VARCHAR(50) |
| 960 | ADD_COL | + TEM_IMPRESSAO_XML_WR VARCHAR(1) |
| 960 | ADD_COL | + TEM_IMPRESSAOBETAFLYNOTA VARCHAR(1) |
| 960 | ADD_COL | + TEM_QUEBRADELINHA VARCHAR(1) |
| 960 | ADD_COL | + TEM_QUEBRADELINHARETORNO VARCHAR(1) |
| 960 | ADD_COL | + TEM_CARACTERESPECIAL VARCHAR(1) |
| 964 | ADD_COL | + CNAE_OBRIGATORIO VARCHAR(100) |
| 964 | ADD_COL | + TEM_OBRIGATORIO_IM VARCHAR(1) |
| 964 | ADD_COL | + TEM_OBRIGATORIO_CNPJ VARCHAR(1) |
| 964 | ADD_COL | + TEM_OBRIGATORIO_SENHA VARCHAR(1) |
| 964 | ADD_COL | + TEM_OBRIGATORIO_LOGIN VARCHAR(1) |
| 964 | ADD_COL | + TEM_LC116 VARCHAR(1) |
| 964 | ADD_COL | + LC116_CODIGOTRIBUTAVELMUNICIPIO VARCHAR(100) |
| 964 | RENAME_COL | × TEM_LC116 → LC116_SERVICO |
| 964 | ALTER_TYPE | ~ LC116_SERVICO TYPE VARCHAR(100) |
| 964 | ADD_COL | + TEM_SUBSTITUIR VARCHAR(1) |
| 964 | RENAME_COL | × TEM_QUEBRADELINHA → QUEBRADELINHA |
| 964 | ALTER_TYPE | ~ QUEBRADELINHA TYPE VARCHAR(30) |
| 964 | ADD_COL | + TEM_ECOMERCIAL VARCHAR(1) |
| 964 | ADD_COL | + TEM_TABULACAO VARCHAR(1) |
| 964 | ADD_COL | + TEM_TAGQUEBRADELINHAUNICA VARCHAR(1) |
| 964 | ADD_COL | + TEM_MULTIPLOS_SERVICOS VARCHAR(1) |
| 967 | ADD_COL | + COMPETENCIA VARCHAR(20) |
| 967 | ADD_COL | + TEM_CODPAIS VARCHAR(1) |
| 967 | ADD_COL | + PODE_PREECHER_IE_Mesmo_Mun VARCHAR(1) |
| 968 | ADD_COL | + TEM_CONSULTA_APOS_ENVIO VARCHAR(1) |

