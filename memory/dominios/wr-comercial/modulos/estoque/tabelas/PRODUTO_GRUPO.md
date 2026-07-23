---
id: dominios-wr-comercial-modulos-estoque-tabelas-produto-grupo
table: PRODUTO_GRUPO
module: estoque
created_at_version: 25
last_modified_version: 1250
target_version: 1468
columns_count: 54
foreign_keys_count: 5
foreign_keys:
  CODNF_CEST: NF_CEST
  CODNF_CFOP_ENTRADA: NF_CFOP
  CODNF_CFOP_ENTRADA_FORA: NF_CFOP
  CODNF_NATUREZA_OPERACAO: NF_NATUREZA_OPERACAO
  CODPLANOCONTAS: PLANOCONTAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_GRUPO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 25;
- **Última mudança:** UPDATE 1250;
- **Total colunas (versão 1468):** 54

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODNF_CEST` | [`NF_CEST`](../../nfe/tabelas/NF_CEST.md) |
| `CODNF_CFOP_ENTRADA` | [`NF_CFOP`](../../nfe/tabelas/NF_CFOP.md) |
| `CODNF_CFOP_ENTRADA_FORA` | [`NF_CFOP`](../../nfe/tabelas/NF_CFOP.md) |
| `CODNF_NATUREZA_OPERACAO` | [`NF_NATUREZA_OPERACAO`](../../nfe/tabelas/NF_NATUREZA_OPERACAO.md) |
| `CODPLANOCONTAS` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODNF_CFOP_ENTRADA` | `VARCHAR(9)` | NULL | → `NF_CFOP` | v25 | v25 |
| 2 | `CODNF_CFOP_ENTRADA_FORA` | `VARCHAR(9)` | NULL | → `NF_CFOP` | v25 | v25 |
| 3 | `MANTEM_ONLINE` | `VARCHAR(1)` | NULL |  | v46 | v46 |
| 4 | `ICMS_MODBC` | `INTEGER` | NULL |  | v64 | v64 |
| 5 | `ICMS_MODBCST` | `INTEGER` | NULL |  | v64 | v64 |
| 6 | `PIS_COFINS_POR_QUANT` | `VARCHAR(1), ADD IPI_POR_QUANT VARCHAR(1)` | NULL |  | v80 | v80 |
| 7 | `CALCULA_PIS` | `VARCHAR(1), ADD CALCULA_IPI VARCHAR(1), ADD CALCULA_COFINS VARCHAR(1)` | NULL |  | v81 | v81 |
| 8 | `CALCULA_ICMS_ST` | `VARCHAR(1)` | NULL |  | v93 | v93 |
| 9 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v102 | v102 |
| 10 | `CALCULA_ICMS` | `VARCHAR(1)` | NULL |  | v133 | v133 |
| 11 | `II_VII` | `DOUBLE PRECISION` | NULL |  | v144 | v144 |
| 12 | `II_VIOF` | `DOUBLE PRECISION` | NULL |  | v144 | v144 |
| 13 | `ISSQN_VALIQ` | `DOUBLE PRECISION` | NULL |  | v144 | v144 |
| 14 | `ISSQN_TIPOTRIBUTACAO` | `INTEGER` | NULL |  | v179 | v179 |
| 15 | `SERVICO_NATUREZA_OPERACAO` | `integer` | NULL |  | v211 | v211 |
| 16 | `SERVICO_REGIME_ESPECIAL_TRIBUT` | `integer` | NULL |  | v211 | v211 |
| 17 | `SERVICO_INCENTIVADOR_CULTURAL` | `varchar (1)` | NULL |  | v211 | v211 |
| 18 | `SERVICO_ISS_RETIDO` | `integer` | NULL |  | v211 | v211 |
| 19 | `SERVICO_ALIQUOTA` | `double precision` | NULL |  | v211 | v211 |
| 20 | `CALCULA_II` | `varchar(1)` | NULL |  | v300 | v300 |
| 21 | `REFERENCIA` | `varchar (15)` | NULL |  | v317 | v317 |
| 22 | `IPI_CENQ` | `INTEGER` | NULL |  | v374 | v374 |
| 23 | `CODNF_CEST` | `VARCHAR(7)` | NULL | → `NF_CEST` | v382 | v382 |
| 24 | `ISSQN_INCENTIVADOR_CULTURAL` | `INTEGER` | NULL |  | v460 | v460 |
| 25 | `COMISAO` | `DOUBLE PRECISION` | NULL |  | v484 | v484 |
| 26 | `VBCST_FRETE` | `VARCHAR(1)` | NULL |  | v515 | v515 |
| 27 | `VBCST_IPI` | `VARCHAR(1)` | NULL |  | v515 | v515 |
| 28 | `VBCST_CONFINS` | `VARCHAR(1)` | NULL |  | v515 | v515 |
| 29 | `VBCST_II` | `VARCHAR(1)` | NULL |  | v515 | v515 |
| 30 | `VBCST_PIS` | `VARCHAR(1)` | NULL |  | v515 | v515 |
| 31 | `VBC_FRETE` | `VARCHAR(1)` | NULL |  | v515 | v515 |
| 32 | `VBC_IPI` | `VARCHAR(1)` | NULL |  | v515 | v515 |
| 33 | `VBC_CONFINS` | `VARCHAR(1)` | NULL |  | v515 | v515 |
| 34 | `VBC_II` | `VARCHAR(1)` | NULL |  | v515 | v515 |
| 35 | `VBC_PIS` | `VARCHAR(1)` | NULL |  | v515 | v515 |
| 36 | `pRedMVAST` | `DOUBLE PRECISION` | NULL |  | v534 | v534 |
| 37 | `CALCULA_ISSQN` | `VARCHAR(1)` | NULL |  | v546 | v546 |
| 38 | `VBC_DESCONTO` | `VARCHAR(1)` | NULL |  | v569 | v569 |
| 39 | `VBCST_DESCONTO` | `VARCHAR(1)` | NULL |  | v569 | v569 |
| 40 | `CODNF_NATUREZA_OPERACAO` | `INTEGER` | NULL | → `NF_NATUREZA_OPERACAO` | v662 | v662 |
| 41 | `NAO_CALCULA_VALOR_ISS` | `DOM_BOOLEAN` | NULL |  | v698 | v698 |
| 42 | `NF_PCREDSN` | `double precision` | NULL |  | v195 | v775 |
| 43 | `ATIVO` | `VARCHAR(1)` | NULL |  | v831 | v831 |
| 44 | `TEM_DIFERIMENTO` | `VARCHAR(1), ADD PDIF DOUBLE PRECISION` | NULL |  | v927 | v927 |
| 45 | `CBENEF` | `VARCHAR(50)` | NULL |  | v928 | v928 |
| 46 | `OPERACAO` | `VARCHAR(50)` | NULL |  | v944 | v944 |
| 47 | `CONSUMIDOR_FINAL` | `VARCHAR(1)` | NULL |  | v944 | v944 |
| 48 | `ENTRADA_SAIDA` | `VARCHAR(1)` | NULL |  | v944 | v944 |
| 49 | `CODPLANOCONTAS` | `VARCHAR(15)` | NULL | → `PLANOCONTAS` | v955 | v955 |
| 50 | `PICMS_NCONSUMIDOR_FINAL` | `DOUBLE PRECISION` | NULL |  | v1008 | v1008 |
| 51 | `OIMPRESSO_ATIVO` | `VARCHAR(1)` | NULL |  | v1250 | v1250 |
| 52 | `OIMPRESSO_CODIGO` | `VARCHAR(15)` | NULL |  | v1250 | v1250 |
| 53 | `OIMPRESSO_DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1250 | v1250 |
| 54 | `OIMPRESSO_UPDATED_AT` | `TIMESTAMP` | NULL |  | v1250 | v1250 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 25 | ADD_COL | + CODNF_CFOP_ENTRADA VARCHAR(9) |
| 25 | ADD_COL | + CODNF_CFOP_ENTRADA_FORA VARCHAR(9) |
| 46 | ADD_COL | + MANTEM_ONLINE VARCHAR(1) |
| 64 | ADD_COL | + ICMS_MODBC INTEGER |
| 64 | ADD_COL | + ICMS_MODBCST INTEGER |
| 80 | ADD_COL | + PIS_COFINS_POR_QUANT VARCHAR(1), ADD IPI_POR_QUANT VARCHAR(1) |
| 81 | ADD_COL | + CALCULA_PIS VARCHAR(1), ADD CALCULA_IPI VARCHAR(1), ADD CALCULA_COFINS VARCHAR(1) |
| 93 | ADD_COL | + CALCULA_ICMS_ST VARCHAR(1) |
| 102 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 133 | ADD_COL | + CALCULA_ICMS VARCHAR(1) |
| 144 | ADD_COL | + II_VII DOUBLE PRECISION |
| 144 | ADD_COL | + II_VIOF DOUBLE PRECISION |
| 144 | ADD_COL | + ISSQN_VALIQ DOUBLE PRECISION |
| 179 | ADD_COL | + ISSQN_TIPOTRIBUTACAO INTEGER |
| 195 | ADD_COL | + PCREDSN double precision |
| 211 | ADD_COL | + SERVICO_NATUREZA_OPERACAO integer |
| 211 | ADD_COL | + SERVICO_REGIME_ESPECIAL_TRIBUT integer |
| 211 | ADD_COL | + SERVICO_INCENTIVADOR_CULTURAL varchar (1) |
| 211 | ADD_COL | + SERVICO_ISS_RETIDO integer |
| 211 | ADD_COL | + SERVICO_ALIQUOTA double precision |
| 247 | ADD_COL | + CALCULA_II varchar(1) |
| 300 | ADD_COL | + CALCULA_II varchar(1) |
| 317 | ADD_COL | + REFERENCIA varchar (15) |
| 374 | ADD_COL | + IPI_CENQ INTEGER |
| 382 | ADD_COL | + CODNF_CEST VARCHAR(7) |
| 458 | ADD_COL | + ISSQN_RETIDO INTEGER |
| 460 | ADD_COL | + ISSQN_INCENTIVADOR_CULTURAL INTEGER |
| 484 | ADD_COL | + COMISAO DOUBLE PRECISION |
| 515 | ADD_COL | + VBCST_FRETE VARCHAR(1) |
| 515 | ADD_COL | + VBCST_IPI VARCHAR(1) |
| 515 | ADD_COL | + VBCST_CONFINS VARCHAR(1) |
| 515 | ADD_COL | + VBCST_II VARCHAR(1) |
| 515 | ADD_COL | + VBCST_PIS VARCHAR(1) |
| 515 | ADD_COL | + VBC_FRETE VARCHAR(1) |
| 515 | ADD_COL | + VBC_IPI VARCHAR(1) |
| 515 | ADD_COL | + VBC_CONFINS VARCHAR(1) |
| 515 | ADD_COL | + VBC_II VARCHAR(1) |
| 515 | ADD_COL | + VBC_PIS VARCHAR(1) |
| 534 | ADD_COL | + pRedMVAST DOUBLE PRECISION |
| 546 | ADD_COL | + CALCULA_ISSQN VARCHAR(1) |
| 569 | ADD_COL | + VBC_DESCONTO VARCHAR(1) |
| 569 | ADD_COL | + VBCST_DESCONTO VARCHAR(1) |
| 662 | ADD_COL | + CODNF_NATUREZA_OPERACAO INTEGER |
| 698 | ADD_COL | + NAO_CALCULA_VALOR_ISS DOM_BOOLEAN |
| 758 | DROP_COL | - ICMS_ALIQUOTA |
| 758 | DROP_COL | - ICMS_ST_ALIQUOTA |
| 758 | DROP_COL | - ICMS_ST_MARGEM |
| 758 | DROP_COL | - ICMS_REDUCAO |
| 758 | DROP_COL | - ICMS_ST_REDUCAO |
| 758 | DROP_COL | - ISSQN_RETIDO |
| 775 | RENAME_COL | × PCREDSN → NF_PCREDSN |
| 784 | DROP_COL | - PCREDSN |
| 831 | ADD_COL | + ATIVO VARCHAR(1) |
| 927 | ADD_COL | + TEM_DIFERIMENTO VARCHAR(1), ADD PDIF DOUBLE PRECISION |
| 928 | ADD_COL | + CBENEF VARCHAR(50) |
| 944 | ADD_COL | + OPERACAO VARCHAR(50) |
| 944 | ADD_COL | + CONSUMIDOR_FINAL VARCHAR(1) |
| 944 | ADD_COL | + ENTRADA_SAIDA VARCHAR(1) |
| 955 | ADD_COL | + CODPLANOCONTAS VARCHAR(15) |
| 1008 | ADD_COL | + PICMS_NCONSUMIDOR_FINAL DOUBLE PRECISION |
| 1250 | ADD_COL | + OIMPRESSO_ATIVO VARCHAR(1) |
| 1250 | ADD_COL | + OIMPRESSO_CODIGO VARCHAR(15) |
| 1250 | ADD_COL | + OIMPRESSO_DT_ALTERACAO TIMESTAMP |
| 1250 | ADD_COL | + OIMPRESSO_UPDATED_AT TIMESTAMP |

