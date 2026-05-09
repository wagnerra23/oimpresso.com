---
table: REGRA_TRIBUTARIA
module: tributario
created_at_version: 1451
last_modified_version: 1464
target_version: 1468
columns_count: 19
foreign_keys_count: 3
foreign_keys:
  CODNF_CEST: NF_CEST
  CODNF_CFOP: NF_CFOP
  CODNF_NCM: NF_NCM
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `REGRA_TRIBUTARIA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `tributario` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1451;
- **Última mudança:** UPDATE 1464;
- **Total colunas (versão 1468):** 19

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODNF_CEST` | [`NF_CEST`](../../nfe/tabelas/NF_CEST.md) |
| `CODNF_CFOP` | [`NF_CFOP`](../../nfe/tabelas/NF_CFOP.md) |
| `CODNF_NCM` | [`NF_NCM`](../../nfe/tabelas/NF_NCM.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1451 | v1451 |
| 2 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v1451 | v1451 |
| 3 | `REGIME_EMPRESA` | `VARCHAR(20)` | NULL |  | v1451 | v1451 |
| 4 | `UF_ORIGEM` | `CHAR(2)` | NULL |  | v1451 | v1451 |
| 5 | `UF_DESTINO` | `CHAR(2)` | NULL |  | v1451 | v1451 |
| 6 | `CONSUMIDOR_FINAL` | `CHAR(1)` | NULL |  | v1451 | v1451 |
| 7 | `TIPO_CONTRIBUINTE` | `INTEGER` | NULL |  | v1451 | v1451 |
| 8 | `TIPO_OPERACAO` | `CHAR(1)` | NULL |  | v1451 | v1451 |
| 9 | `ATIVO` | `CHAR(1) DEFAULT 'S'` | NULL |  | v1451 | v1451 |
| 10 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1451 | v1451 |
| 11 | `CSOSN` | `VARCHAR(3)` | NULL |  | v1455 | v1455 |
| 12 | `CST` | `VARCHAR(3)` | NULL |  | v1455 | v1455 |
| 13 | `PRIORIDADE` | `INTEGER DEFAULT 0` | NULL |  | v1455 | v1455 |
| 14 | `TIPO_PRODUTO` | `VARCHAR(10)` | NULL |  | v1462 | v1462 |
| 15 | `FINALIDADE` | `SMALLINT` | NULL |  | v1462 | v1462 |
| 16 | `DESTINO` | `VARCHAR(20)` | NULL |  | v1462 | v1462 |
| 17 | `CODNF_CFOP` | `VARCHAR(4)` | NULL | → `NF_CFOP` | v1455 | v1463 |
| 18 | `CODNF_CEST` | `VARCHAR(7)` | NULL | → `NF_CEST` | v1463 | v1463 |
| 19 | `CODNF_NCM` | `VARCHAR(10)` | NULL | → `NF_NCM` | v1464 | v1464 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1451 | CREATE | CREATE TABLE com 10 colunas |
| 1455 | ADD_COL | + CFOP VARCHAR(4) |
| 1455 | ADD_COL | + TEM_ST CHAR(1) |
| 1455 | ADD_COL | + TEM_DIFAL CHAR(1) |
| 1455 | ADD_COL | + TEM_FCP CHAR(1) |
| 1455 | ADD_COL | + CSOSN VARCHAR(3) |
| 1455 | ADD_COL | + CST VARCHAR(3) |
| 1455 | ADD_COL | + ALIQUOTA_ICMS DECIMAL(5,2) |
| 1455 | ADD_COL | + REDUCAO_BC DECIMAL(5,2) |
| 1455 | ADD_COL | + TEM_REDUCAO_BC CHAR(1) |
| 1455 | ADD_COL | + TEM_ISENCAO CHAR(1) |
| 1455 | ADD_COL | + TEM_DIFERIMENTO CHAR(1) |
| 1455 | ADD_COL | + TEM_CREDITO CHAR(1) |
| 1455 | ADD_COL | + PRIORIDADE INTEGER DEFAULT 0 |
| 1462 | ADD_COL | + CST_PIS VARCHAR(2) |
| 1462 | ADD_COL | + ALIQUOTA_PIS NUMERIC(5,2) |
| 1462 | ADD_COL | + CST_COFINS VARCHAR(2) |
| 1462 | ADD_COL | + ALIQUOTA_COFINS NUMERIC(5,2) |
| 1462 | ADD_COL | + TIPO_PRODUTO VARCHAR(10) |
| 1462 | ADD_COL | + FINALIDADE SMALLINT |
| 1462 | ADD_COL | + DESTINO VARCHAR(20) |
| 1463 | RENAME_COL | × CFOP → CODNF_CFOP |
| 1463 | ADD_COL | + CODNF_CEST VARCHAR(7) |
| 1463 | DROP_COL | - TEM_ST |
| 1463 | DROP_COL | - TEM_DIFAL |
| 1463 | DROP_COL | - TEM_FCP |
| 1463 | DROP_COL | - ALIQUOTA_ICMS |
| 1463 | DROP_COL | - REDUCAO_BC |
| 1463 | DROP_COL | - TEM_REDUCAO_BC |
| 1463 | DROP_COL | - TEM_ISENCAO |
| 1463 | DROP_COL | - TEM_DIFERIMENTO |
| 1463 | DROP_COL | - CST_PIS |
| 1463 | DROP_COL | - ALIQUOTA_PIS |
| 1463 | DROP_COL | - CST_COFINS |
| 1463 | DROP_COL | - ALIQUOTA_COFINS |
| 1463 | DROP_COL | - TEM_CREDITO |
| 1464 | ADD_COL | + CODNF_NCM VARCHAR(10) |

