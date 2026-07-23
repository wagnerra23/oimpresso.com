---
id: dominios-wr-comercial-modulos-nfe-tabelas-nf-natureza-operacao-prodgrupo
table: NF_NATUREZA_OPERACAO_PRODGRUPO
module: nfe
created_at_version: 1135
last_modified_version: 1426
target_version: 1468
columns_count: 117
foreign_keys_count: 10
foreign_keys:
  CODNF_CEST: NF_CEST
  CODNF_CFOP: NF_CFOP
  CODNF_CFOP_ENTRADA: NF_CFOP
  CODNF_CFOP_ENTRADA_FORA: NF_CFOP
  CODNF_CFOP_FORA: NF_CFOP
  CODNF_CST: NF_CST
  CODNF_NATUREZA_OPERACAO: NF_NATUREZA_OPERACAO
  CODPLANOCONTAS: PLANOCONTAS
  CODPRODUTO_GRUPO: PRODUTO_GRUPO
  CODVENDA_TIPO: VENDA_TIPO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_NATUREZA_OPERACAO_PRODGRUPO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1135;
- **Última mudança:** UPDATE 1426;
- **Total colunas (versão 1468):** 117

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODNF_CEST` | [`NF_CEST`](../../nfe/tabelas/NF_CEST.md) |
| `CODNF_CFOP` | [`NF_CFOP`](../../nfe/tabelas/NF_CFOP.md) |
| `CODNF_CFOP_ENTRADA` | [`NF_CFOP`](../../nfe/tabelas/NF_CFOP.md) |
| `CODNF_CFOP_ENTRADA_FORA` | [`NF_CFOP`](../../nfe/tabelas/NF_CFOP.md) |
| `CODNF_CFOP_FORA` | [`NF_CFOP`](../../nfe/tabelas/NF_CFOP.md) |
| `CODNF_CST` | [`NF_CST`](../../nfe/tabelas/NF_CST.md) |
| `CODNF_NATUREZA_OPERACAO` | [`NF_NATUREZA_OPERACAO`](../../nfe/tabelas/NF_NATUREZA_OPERACAO.md) |
| `CODPLANOCONTAS` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |
| `CODPRODUTO_GRUPO` | [`PRODUTO_GRUPO`](../../estoque/tabelas/PRODUTO_GRUPO.md) |
| `CODVENDA_TIPO` | [`VENDA_TIPO`](../../vendas/tabelas/VENDA_TIPO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODNF_NATUREZA_OPERACAO` | `INTEGER` | NOT NULL | → `NF_NATUREZA_OPERACAO` | v1135 | v1135 |
| 2 | `CODPRODUTO_GRUPO` | `VARCHAR(15)` | NOT NULL | → `PRODUTO_GRUPO` | v1135 | v1135 |
| 3 | `CODNF_CST` | `VARCHAR(4)` | NULL | → `NF_CST` | v1135 | v1135 |
| 4 | `CODNF_CFOP` | `VARCHAR(9)` | NULL | → `NF_CFOP` | v1135 | v1135 |
| 5 | `CODNF_CFOP_FORA` | `VARCHAR(9)` | NULL | → `NF_CFOP` | v1135 | v1135 |
| 6 | `PICMS` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 7 | `PICMSST` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 8 | `PMVAST` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 9 | `PREDBC` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 10 | `PREDBCST` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 11 | `PIS_ST` | `VARCHAR(4)` | NULL |  | v1135 | v1135 |
| 12 | `COFINS_ST` | `VARCHAR(4)` | NULL |  | v1135 | v1135 |
| 13 | `IPI_ST` | `VARCHAR(4)` | NULL |  | v1135 | v1135 |
| 14 | `IPI_VBC` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 15 | `IPI_QUNID` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 16 | `IPI_VUNID` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 17 | `IPI_PIPI` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 18 | `IPI_VIPI` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 19 | `II_VBC` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 20 | `II_VDESPADU` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 21 | `II_PII` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 22 | `II_PIOF` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 23 | `PIS_VBC` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 24 | `PIS_PPIS` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 25 | `PIS_VPIS` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 26 | `PIS_QBCPROD` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 27 | `PIS_VALIQPROD` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 28 | `PISST_VBC` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 29 | `PISST_PPIS` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 30 | `PISST_VPIS` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 31 | `PISST_QBCPROD` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 32 | `PISST_VALIQPROD` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 33 | `COFINS_VBC` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 34 | `COFINS_PCOFINS` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 35 | `COFINS_VBCPROD` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 36 | `COFINS_VALIQPROD` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 37 | `COFINS_VCOFINS` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 38 | `COFINSST_VBC` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 39 | `COFINSST_PCOFINS` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 40 | `COFINSST_QBCPROD` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 41 | `COFINSST_VALIQPROD` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 42 | `COFINSST_VCOFINS` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 43 | `ISSQN_VBC` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 44 | `ISSQN_PVALIQ` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 45 | `ISSQN_VISSQN` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 46 | `ISSQN_CMUNFG` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 47 | `ISSQN_LISTSERV` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 48 | `II_VII` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 49 | `II_VIOF` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 50 | `ISSQN_VALIQ` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 51 | `ICMS_PAF` | `VARCHAR(3)` | NULL |  | v1135 | v1135 |
| 52 | `CODNF_CFOP_ENTRADA` | `VARCHAR(9)` | NULL | → `NF_CFOP` | v1135 | v1135 |
| 53 | `CODNF_CFOP_ENTRADA_FORA` | `VARCHAR(9)` | NULL | → `NF_CFOP` | v1135 | v1135 |
| 54 | `MANTEM_ONLINE` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 55 | `ICMS_MODBC` | `INTEGER` | NULL |  | v1135 | v1135 |
| 56 | `ICMS_MODBCST` | `INTEGER` | NULL |  | v1135 | v1135 |
| 57 | `PIS_COFINS_POR_QUANT` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 58 | `IPI_POR_QUANT` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 59 | `CALCULA_PIS` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 60 | `CALCULA_IPI` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 61 | `CALCULA_COFINS` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 62 | `CALCULA_ICMS_ST` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 63 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1135 | v1135 |
| 64 | `CALCULA_ICMS` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 65 | `ISSQN_TIPOTRIBUTACAO` | `INTEGER` | NULL |  | v1135 | v1135 |
| 66 | `NF_PCREDSN` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 67 | `SERVICO_NATUREZA_OPERACAO` | `INTEGER` | NULL |  | v1135 | v1135 |
| 68 | `SERVICO_REGIME_ESPECIAL_TRIBUT` | `INTEGER` | NULL |  | v1135 | v1135 |
| 69 | `SERVICO_INCENTIVADOR_CULTURAL` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 70 | `SERVICO_ISS_RETIDO` | `INTEGER` | NULL |  | v1135 | v1135 |
| 71 | `SERVICO_ALIQUOTA` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 72 | `CALCULA_II` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 73 | `REFERENCIA` | `VARCHAR(15)` | NULL |  | v1135 | v1135 |
| 74 | `IPI_CENQ` | `INTEGER` | NULL |  | v1135 | v1135 |
| 75 | `CODNF_CEST` | `VARCHAR(7)` | NULL | → `NF_CEST` | v1135 | v1135 |
| 76 | `CODVENDA_TIPO` | `INTEGER` | NULL | → `VENDA_TIPO` | v1135 | v1135 |
| 77 | `ISSQN_INCENTIVADOR_CULTURAL` | `INTEGER` | NULL |  | v1135 | v1135 |
| 78 | `COMISAO` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 79 | `VBCST_FRETE` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 80 | `VBCST_IPI` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 81 | `VBCST_CONFINS` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 82 | `VBCST_II` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 83 | `VBCST_PIS` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 84 | `VBC_FRETE` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 85 | `VBC_IPI` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 86 | `VBC_CONFINS` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 87 | `VBC_II` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 88 | `VBC_PIS` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 89 | `PREDMVAST` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 90 | `CALCULA_ISSQN` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 91 | `VBC_DESCONTO` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 92 | `VBCST_DESCONTO` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 93 | `NAO_CALCULA_VALOR_ISS` | `DOM_BOOLEAN` | NULL |  | v1135 | v1135 |
| 94 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 95 | `TEM_DIFERIMENTO` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 96 | `PDIF` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 97 | `CBENEF` | `VARCHAR(50)` | NULL |  | v1135 | v1135 |
| 98 | `OPERACAO` | `VARCHAR(50)` | NULL |  | v1135 | v1135 |
| 99 | `CONSUMIDOR_FINAL` | `VARCHAR(1)` | NOT NULL |  | v1135 | v1135 |
| 100 | `ENTRADA_SAIDA` | `VARCHAR(1)` | NULL |  | v1135 | v1135 |
| 101 | `CODPLANOCONTAS` | `VARCHAR(15)` | NULL | → `PLANOCONTAS` | v1135 | v1135 |
| 102 | `PICMS_NCONSUMIDOR_FINAL` | `DOUBLE PRECISION` | NULL |  | v1135 | v1135 |
| 103 | `CODNF_IBSCBS_CST` | `VARCHAR(10)` | NULL |  | v1422 | v1422 |
| 104 | `CODNF_IBSCBS_CLASSTRIB` | `VARCHAR(10)` | NULL |  | v1422 | v1422 |
| 105 | `IBS_UF_PDIF` | `double precision default 0` | NULL |  | v1422 | v1422 |
| 106 | `IBS_UF_ALIQ` | `DOUBLE PRECISION DEFAULT 0` | NULL |  | v1422 | v1422 |
| 107 | `IBS_UF_PREDALIQ` | `DOUBLE PRECISION DEFAULT 0` | NULL |  | v1422 | v1422 |
| 108 | `IBS_MUN_PDIF` | `double precision default 0` | NULL |  | v1422 | v1422 |
| 109 | `IBS_MUN_ALIQ` | `DOUBLE PRECISION DEFAULT 0` | NULL |  | v1422 | v1422 |
| 110 | `IBS_MUN_PREDALIQ` | `DOUBLE PRECISION DEFAULT 0` | NULL |  | v1422 | v1422 |
| 111 | `CBS_ALIQ` | `DOUBLE PRECISION DEFAULT 0` | NULL |  | v1422 | v1422 |
| 112 | `CBS_PREDALIQ` | `DOUBLE PRECISION DEFAULT 0` | NULL |  | v1422 | v1422 |
| 113 | `CBS_PDIF` | `DOUBLE PRECISION DEFAULT 0` | NULL |  | v1422 | v1422 |
| 114 | `CBS_VDEVTRIB` | `DOUBLE PRECISION DEFAULT 0` | NULL |  | v1422 | v1422 |
| 115 | `IBS_UF_VDEVTRIB` | `DOUBLE PRECISION DEFAULT 0` | NULL |  | v1422 | v1422 |
| 116 | `IBS_MUN_VDEVTRIB` | `DOUBLE PRECISION DEFAULT 0` | NULL |  | v1422 | v1422 |
| 117 | `NF_DEDUZ_ICMS_COFINS` | `VARCHAR(1), ADD NF_DEDUZ_ICMS_PIS VARCHAR(1)` | NULL |  | v1426 | v1426 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1135 | CREATE | CREATE TABLE com 102 colunas |
| 1422 | ADD_COL | + CODNF_IBSCBS_CST VARCHAR(10) |
| 1422 | ADD_COL | + CODNF_IBSCBS_CLASSTRIB VARCHAR(10) |
| 1422 | ADD_COL | + IBS_UF_PDIF double precision default 0 |
| 1422 | ADD_COL | + IBS_UF_ALIQ DOUBLE PRECISION DEFAULT 0 |
| 1422 | ADD_COL | + IBS_UF_PREDALIQ DOUBLE PRECISION DEFAULT 0 |
| 1422 | ADD_COL | + IBS_MUN_PDIF double precision default 0 |
| 1422 | ADD_COL | + IBS_MUN_ALIQ DOUBLE PRECISION DEFAULT 0 |
| 1422 | ADD_COL | + IBS_MUN_PREDALIQ DOUBLE PRECISION DEFAULT 0 |
| 1422 | ADD_COL | + CBS_ALIQ DOUBLE PRECISION DEFAULT 0 |
| 1422 | ADD_COL | + CBS_PREDALIQ DOUBLE PRECISION DEFAULT 0 |
| 1422 | ADD_COL | + CBS_PDIF DOUBLE PRECISION DEFAULT 0 |
| 1422 | ADD_COL | + CBS_VDEVTRIB DOUBLE PRECISION DEFAULT 0 |
| 1422 | ADD_COL | + IBS_UF_VDEVTRIB DOUBLE PRECISION DEFAULT 0 |
| 1422 | ADD_COL | + IBS_MUN_VDEVTRIB DOUBLE PRECISION DEFAULT 0 |
| 1426 | ADD_COL | + NF_DEDUZ_ICMS_COFINS VARCHAR(1), ADD NF_DEDUZ_ICMS_PIS VARCHAR(1) |

