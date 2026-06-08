---
table: NF_NCM
module: nfe
created_at_version: 179
last_modified_version: 1438
target_version: 1468
columns_count: 28
foreign_keys_count: 3
foreign_keys:
  CODNF_CEST: NF_CEST
  CODNF_CST: NF_CST
  CODNF_NBS: NF_NBS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_NCM`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 179;
- **Última mudança:** UPDATE 1438;
- **Total colunas (versão 1468):** 28

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODNF_CEST` | [`NF_CEST`](../../nfe/tabelas/NF_CEST.md) |
| `CODNF_CST` | [`NF_CST`](../../nfe/tabelas/NF_CST.md) |
| `CODNF_NBS` | [`NF_NBS`](../../nfe/tabelas/NF_NBS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `TIPO` | `varchar (10)` | NULL |  | v179 | v179 |
| 2 | `EX_TIPI` | `integer` | NULL |  | v254 | v736 |
| 3 | `ALIQ_NACIONAL` | `double precision` | NULL |  | v254 | v254 |
| 4 | `ALIQ_IMPORTACAO` | `double precision` | NULL |  | v254 | v254 |
| 5 | `ALIQ_ESTADUAL` | `DOUBLE PRECISION` | NULL |  | v446 | v446 |
| 6 | `ALIQ_MUNICIPAL` | `DOUBLE PRECISION` | NULL |  | v446 | v446 |
| 7 | `CODNF_CEST` | `VARCHAR(7)` | NULL | → `NF_CEST` | v449 | v449 |
| 8 | `CNAE` | `VARCHAR(15)` | NULL |  | v552 | v552 |
| 9 | `CTRIBUTACAO_MUNICIPIO` | `VARCHAR(15)` | NULL |  | v553 | v553 |
| 10 | `ALIQ_ISS` | `DOUBLE PRECISION` | NULL |  | v555 | v555 |
| 11 | `ATIVO` | `VARCHAR(1)` | NULL |  | v728 | v728 |
| 12 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v728 | v728 |
| 13 | `DT_VALIDADE` | `TIMESTAMP` | NULL |  | v1120 | v1120 |
| 14 | `PERCENTUAL_REDUCAO_IBS` | `DOUBLE PRECISION` | NULL |  | v1423 | v1423 |
| 15 | `PERCENTUAL_REDUCAO_CBS` | `DOUBLE PRECISION` | NULL |  | v1423 | v1423 |
| 16 | `TIPO_ALIQUOTA` | `VARCHAR(50)` | NULL |  | v1423 | v1423 |
| 17 | `TEM_CREDITO_PRESUMIDO` | `VARCHAR(1)` | NULL |  | v1423 | v1423 |
| 18 | `TEM_ESTORNO_CREDITO` | `VARCHAR(1)` | NULL |  | v1423 | v1423 |
| 19 | `TEM_TRIB_MONO_NORMAL` | `VARCHAR(1)` | NULL |  | v1423 | v1423 |
| 20 | `TEM_TRIB_MONO_RETENCAO` | `VARCHAR(1)` | NULL |  | v1423 | v1423 |
| 21 | `TEM_TRIB_MONO_RETIDA_ANT` | `VARCHAR(1)` | NULL |  | v1423 | v1423 |
| 22 | `TEM_TRIB_MONOF_COMBUST_DIFER` | `VARCHAR(1)` | NULL |  | v1423 | v1423 |
| 23 | `URL_LEGISLACAO` | `VARCHAR(500)` | NULL |  | v1423 | v1423 |
| 24 | `CODNF_CST` | `VARCHAR(10)` | NULL | → `NF_CST` | v1423 | v1423 |
| 25 | `IND_NFE` | `VARCHAR(1) DEFAULT 'N', ADD IND_NFCE VARCHAR(1) DEFAULT 'N', ADD IND_CTE VARCHAR(1) DEFAULT 'N', ADD IND_CTEOS VARCHAR(1) DEFAULT 'N', ADD IND_BPE VARCHAR(1) DEFAULT 'N', ADD IND_NF3E VARCHAR(1) DEFAULT 'N', ADD IND_NFCOM VARCHAR(1) DEFAULT 'N', ADD IND_NFSE VARCHAR(1) DEFAULT 'N', ADD IND_BPETM VARCHAR(1) DEFAULT 'N', ADD IND_BPETA VARCHAR(1) DEFAULT 'N', ADD IND_NFAG VARCHAR(1) DEFAULT 'N', ADD IND_NFSVIA VARCHAR(1) DEFAULT 'N', ADD IND_NFABI VARCHAR(1) DEFAULT 'N', ADD IND_NFGAS VARCHAR(1) DEFAULT 'N', ADD IND_DERE VARCHAR(1) DEFAULT 'N'` | NULL |  | v1424 | v1424 |
| 26 | `DT_INICIO` | `TIMESTAMP, ADD TIPO_ATO VARCHAR(100), ADD NUMERO_ATO VARCHAR(20), ADD ANO_ATO VARCHAR(4)` | NULL |  | v1425 | v1425 |
| 27 | `CODNF_NBS` | `VARCHAR(9)` | NULL | → `NF_NBS` | v1437 | v1437 |
| 28 | `DT_ATUALIZACAO` | `TIMESTAMP` | NULL |  | v1438 | v1438 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 179 | ADD_COL | + TIPO varchar (10) |
| 254 | ADD_COL | + EX_TIPI smallint |
| 254 | ADD_COL | + ALIQ_NACIONAL double precision |
| 254 | ADD_COL | + ALIQ_IMPORTACAO double precision |
| 446 | ADD_COL | + ALIQ_ESTADUAL DOUBLE PRECISION |
| 446 | ADD_COL | + ALIQ_MUNICIPAL DOUBLE PRECISION |
| 449 | ADD_COL | + CODNF_CEST VARCHAR(7) |
| 552 | ADD_COL | + CNAE VARCHAR(15) |
| 553 | ADD_COL | + CTRIBUTACAO_MUNICIPIO VARCHAR(15) |
| 555 | ADD_COL | + ALIQ_ISS DOUBLE PRECISION |
| 728 | ADD_COL | + ATIVO VARCHAR(1) |
| 728 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 736 | ALTER_TYPE | ~ EX_TIPI TYPE integer |
| 1120 | ADD_COL | + DT_VALIDADE TIMESTAMP |
| 1423 | ADD_COL | + PERCENTUAL_REDUCAO_IBS DOUBLE PRECISION |
| 1423 | ADD_COL | + PERCENTUAL_REDUCAO_CBS DOUBLE PRECISION |
| 1423 | ADD_COL | + TIPO_ALIQUOTA VARCHAR(50) |
| 1423 | ADD_COL | + TEM_CREDITO_PRESUMIDO VARCHAR(1) |
| 1423 | ADD_COL | + TEM_ESTORNO_CREDITO VARCHAR(1) |
| 1423 | ADD_COL | + TEM_TRIB_MONO_NORMAL VARCHAR(1) |
| 1423 | ADD_COL | + TEM_TRIB_MONO_RETENCAO VARCHAR(1) |
| 1423 | ADD_COL | + TEM_TRIB_MONO_RETIDA_ANT VARCHAR(1) |
| 1423 | ADD_COL | + TEM_TRIB_MONOF_COMBUST_DIFER VARCHAR(1) |
| 1423 | ADD_COL | + URL_LEGISLACAO VARCHAR(500) |
| 1423 | ADD_COL | + CODNF_CST VARCHAR(10) |
| 1424 | ADD_COL | + IND_NFE VARCHAR(1) DEFAULT 'N', ADD IND_NFCE VARCHAR(1) DEFAULT 'N', ADD IND_CTE VARCHAR(1) DEFAULT 'N', ADD IND_CTEOS VARCHAR(1) DEFAULT 'N', ADD IND_BPE VARCHAR(1) DEFAULT 'N', ADD IND_NF3E VARCHAR(1) DEFAULT 'N', ADD IND_NFCOM VARCHAR(1) DEFAULT 'N', ADD IND_NFSE VARCHAR(1) DEFAULT 'N', ADD IND_BPETM VARCHAR(1) DEFAULT 'N', ADD IND_BPETA VARCHAR(1) DEFAULT 'N', ADD IND_NFAG VARCHAR(1) DEFAULT 'N', ADD IND_NFSVIA VARCHAR(1) DEFAULT 'N', ADD IND_NFABI VARCHAR(1) DEFAULT 'N', ADD IND_NFGAS VARCHAR(1) DEFAULT 'N', ADD IND_DERE VARCHAR(1) DEFAULT 'N' |
| 1425 | ADD_COL | + DT_INICIO TIMESTAMP, ADD TIPO_ATO VARCHAR(100), ADD NUMERO_ATO VARCHAR(20), ADD ANO_ATO VARCHAR(4) |
| 1425 | ALTER_TYPE | ~ DESCRICAO TYPE VARCHAR(2000) CHARACTER SET WIN1252 |
| 1437 | ADD_COL | + CODNF_NBS VARCHAR(9) |
| 1438 | ADD_COL | + DT_ATUALIZACAO TIMESTAMP |

