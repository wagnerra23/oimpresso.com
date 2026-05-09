---
table: REGRA_TRIBUTARIA_IMPOSTO
module: tributario
created_at_version: 1451
last_modified_version: 1463
target_version: 1468
columns_count: 46
foreign_keys_count: 1
foreign_keys:
  CODREGRA_TRIBUTARIA: REGRA_TRIBUTARIA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `REGRA_TRIBUTARIA_IMPOSTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `tributario` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1451;
- **Última mudança:** UPDATE 1463;
- **Total colunas (versão 1468):** 46

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODREGRA_TRIBUTARIA` | [`REGRA_TRIBUTARIA`](../../tributario/tabelas/REGRA_TRIBUTARIA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1451 | v1451 |
| 2 | `CODREGRA_TRIBUTARIA` | `INTEGER` | NOT NULL | → `REGRA_TRIBUTARIA` | v1451 | v1451 |
| 3 | `CODTRIBUTO` | `VARCHAR(20)` | NULL |  | v1451 | v1451 |
| 4 | `CST` | `VARCHAR(5)` | NULL |  | v1451 | v1451 |
| 5 | `ALIQUOTA` | `NUMERIC(15,4)` | NULL |  | v1451 | v1451 |
| 6 | `MVA` | `NUMERIC(15,4)` | NULL |  | v1451 | v1451 |
| 7 | `ALIQUOTA_ST` | `NUMERIC(15,4)` | NULL |  | v1451 | v1451 |
| 8 | `REDUCAO_BASE` | `NUMERIC(15,4)` | NULL |  | v1451 | v1451 |
| 9 | `CALCULA` | `VARCHAR(1)` | NULL |  | v1463 | v1463 |
| 10 | `POR_QUANTIDADE` | `VARCHAR(1)` | NULL |  | v1463 | v1463 |
| 11 | `MODBC` | `INTEGER` | NULL |  | v1463 | v1463 |
| 12 | `MODBCST` | `INTEGER` | NULL |  | v1463 | v1463 |
| 13 | `REDUCAO_MVA` | `NUMERIC(15,4)` | NULL |  | v1463 | v1463 |
| 14 | `ALIQUOTA_NAO_CF` | `NUMERIC(15,4)` | NULL |  | v1463 | v1463 |
| 15 | `PDIF` | `NUMERIC(15,4)` | NULL |  | v1463 | v1463 |
| 16 | `PCREDSN` | `NUMERIC(15,4)` | NULL |  | v1463 | v1463 |
| 17 | `CBENEF` | `VARCHAR(50)` | NULL |  | v1463 | v1463 |
| 18 | `PAF` | `VARCHAR(3)` | NULL |  | v1463 | v1463 |
| 19 | `REDUCAO_BASE_ST` | `NUMERIC(15,4)` | NULL |  | v1463 | v1463 |
| 20 | `DEDUZ_ICMS_PIS` | `VARCHAR(1)` | NULL |  | v1463 | v1463 |
| 21 | `DEDUZ_ICMS_COFINS` | `VARCHAR(1)` | NULL |  | v1463 | v1463 |
| 22 | `VBC_FRETE` | `VARCHAR(1)` | NULL |  | v1463 | v1463 |
| 23 | `VBC_IPI` | `VARCHAR(1)` | NULL |  | v1463 | v1463 |
| 24 | `VBC_PIS` | `VARCHAR(1)` | NULL |  | v1463 | v1463 |
| 25 | `VBC_COFINS` | `VARCHAR(1)` | NULL |  | v1463 | v1463 |
| 26 | `VBC_II` | `VARCHAR(1)` | NULL |  | v1463 | v1463 |
| 27 | `VBC_DESCONTO` | `VARCHAR(1)` | NULL |  | v1463 | v1463 |
| 28 | `VBCST_FRETE` | `VARCHAR(1)` | NULL |  | v1463 | v1463 |
| 29 | `VBCST_IPI` | `VARCHAR(1)` | NULL |  | v1463 | v1463 |
| 30 | `VBCST_PIS` | `VARCHAR(1)` | NULL |  | v1463 | v1463 |
| 31 | `VBCST_COFINS` | `VARCHAR(1)` | NULL |  | v1463 | v1463 |
| 32 | `VBCST_II` | `VARCHAR(1)` | NULL |  | v1463 | v1463 |
| 33 | `VBCST_DESCONTO` | `VARCHAR(1)` | NULL |  | v1463 | v1463 |
| 34 | `CENQ` | `INTEGER` | NULL |  | v1463 | v1463 |
| 35 | `PIOF` | `NUMERIC(15,4)` | NULL |  | v1463 | v1463 |
| 36 | `ISS_RETIDO` | `INTEGER` | NULL |  | v1463 | v1463 |
| 37 | `ISS_LISTA_SERVICO` | `NUMERIC(15,4)` | NULL |  | v1463 | v1463 |
| 38 | `ISS_COD_MUN` | `INTEGER` | NULL |  | v1463 | v1463 |
| 39 | `ISS_TIPO_TRIBUTACAO` | `INTEGER` | NULL |  | v1463 | v1463 |
| 40 | `ISS_NATUREZA_OPERACAO` | `INTEGER` | NULL |  | v1463 | v1463 |
| 41 | `ISS_REGIME_ESPECIAL` | `INTEGER` | NULL |  | v1463 | v1463 |
| 42 | `ISS_INCENTIVADOR` | `VARCHAR(1)` | NULL |  | v1463 | v1463 |
| 43 | `ISS_NAO_CALCULA_VALOR` | `VARCHAR(1)` | NULL |  | v1463 | v1463 |
| 44 | `CLASSTRIB` | `VARCHAR(10)` | NULL |  | v1463 | v1463 |
| 45 | `PREDALIQ` | `NUMERIC(15,4)` | NULL |  | v1463 | v1463 |
| 46 | `VDEVTRIB` | `NUMERIC(15,4)` | NULL |  | v1463 | v1463 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1451 | CREATE | CREATE TABLE com 8 colunas |
| 1463 | ADD_COL | + CALCULA VARCHAR(1) |
| 1463 | ADD_COL | + POR_QUANTIDADE VARCHAR(1) |
| 1463 | ADD_COL | + MODBC INTEGER |
| 1463 | ADD_COL | + MODBCST INTEGER |
| 1463 | ADD_COL | + REDUCAO_MVA NUMERIC(15,4) |
| 1463 | ADD_COL | + ALIQUOTA_NAO_CF NUMERIC(15,4) |
| 1463 | ADD_COL | + PDIF NUMERIC(15,4) |
| 1463 | ADD_COL | + PCREDSN NUMERIC(15,4) |
| 1463 | ADD_COL | + CBENEF VARCHAR(50) |
| 1463 | ADD_COL | + PAF VARCHAR(3) |
| 1463 | ADD_COL | + REDUCAO_BASE_ST NUMERIC(15,4) |
| 1463 | ADD_COL | + DEDUZ_ICMS_PIS VARCHAR(1) |
| 1463 | ADD_COL | + DEDUZ_ICMS_COFINS VARCHAR(1) |
| 1463 | ADD_COL | + VBC_FRETE VARCHAR(1) |
| 1463 | ADD_COL | + VBC_IPI VARCHAR(1) |
| 1463 | ADD_COL | + VBC_PIS VARCHAR(1) |
| 1463 | ADD_COL | + VBC_COFINS VARCHAR(1) |
| 1463 | ADD_COL | + VBC_II VARCHAR(1) |
| 1463 | ADD_COL | + VBC_DESCONTO VARCHAR(1) |
| 1463 | ADD_COL | + VBCST_FRETE VARCHAR(1) |
| 1463 | ADD_COL | + VBCST_IPI VARCHAR(1) |
| 1463 | ADD_COL | + VBCST_PIS VARCHAR(1) |
| 1463 | ADD_COL | + VBCST_COFINS VARCHAR(1) |
| 1463 | ADD_COL | + VBCST_II VARCHAR(1) |
| 1463 | ADD_COL | + VBCST_DESCONTO VARCHAR(1) |
| 1463 | ADD_COL | + CENQ INTEGER |
| 1463 | ADD_COL | + PIOF NUMERIC(15,4) |
| 1463 | ADD_COL | + ISS_RETIDO INTEGER |
| 1463 | ADD_COL | + ISS_LISTA_SERVICO NUMERIC(15,4) |
| 1463 | ADD_COL | + ISS_COD_MUN INTEGER |
| 1463 | ADD_COL | + ISS_TIPO_TRIBUTACAO INTEGER |
| 1463 | ADD_COL | + ISS_NATUREZA_OPERACAO INTEGER |
| 1463 | ADD_COL | + ISS_REGIME_ESPECIAL INTEGER |
| 1463 | ADD_COL | + ISS_INCENTIVADOR VARCHAR(1) |
| 1463 | ADD_COL | + ISS_NAO_CALCULA_VALOR VARCHAR(1) |
| 1463 | ADD_COL | + CLASSTRIB VARCHAR(10) |
| 1463 | ADD_COL | + PREDALIQ NUMERIC(15,4) |
| 1463 | ADD_COL | + VDEVTRIB NUMERIC(15,4) |

