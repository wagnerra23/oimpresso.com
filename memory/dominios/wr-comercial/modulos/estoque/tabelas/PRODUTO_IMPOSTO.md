---
table: PRODUTO_IMPOSTO
module: estoque
created_at_version: 73
last_modified_version: 73
target_version: 1468
columns_count: 54
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUTO_IMPOSTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `estoque` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 73;
- **Última mudança:** UPDATE 73;
- **Total colunas (versão 1468):** 54

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODPRODUTO` | `VARCHAR(15)` | NOT NULL | v73 | v73 |
| 2 | `CODNF_CST` | `VARCHAR(4)` | NULL | v73 | v73 |
| 3 | `CODNF_CFOP` | `VARCHAR(9)` | NULL | v73 | v73 |
| 4 | `CODNF_CFOP_FORA` | `VARCHAR(9)` | NULL | v73 | v73 |
| 5 | `PICMS` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 6 | `PICMSST` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 7 | `PMVAST` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 8 | `PREDBC` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 9 | `PREDBCST` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 10 | `PIS_ST` | `VARCHAR(4)` | NULL | v73 | v73 |
| 11 | `COFINS_ST` | `VARCHAR(4)` | NULL | v73 | v73 |
| 12 | `CODNF_CFOP_ENTRADA` | `VARCHAR(9)` | NULL | v73 | v73 |
| 13 | `CODNF_CFOP_ENTRADA_FORA` | `VARCHAR(9)` | NULL | v73 | v73 |
| 14 | `ICMS_PAF` | `VARCHAR(3)` | NULL | v73 | v73 |
| 15 | `IPI_ST` | `VARCHAR(4)` | NULL | v73 | v73 |
| 16 | `IPI_VBC` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 17 | `IPI_QUNID` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 18 | `IPI_VUNID` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 19 | `IPI_PIPI` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 20 | `IPI_VIPI` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 21 | `II_VBC` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 22 | `II_VDESPADU` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 23 | `II_PII` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 24 | `II_PIOF` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 25 | `PIS_VBC` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 26 | `PIS_PPIS` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 27 | `PIS_VPIS` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 28 | `PIS_QBCPROD` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 29 | `PIS_VALIQPROD` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 30 | `PISST_VBC` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 31 | `PISST_PPIS` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 32 | `PISST_VPIS` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 33 | `PISST_QBCPROD` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 34 | `PISST_VALIQPROD` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 35 | `COFINS_VBC` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 36 | `COFINS_PCOFINS` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 37 | `COFINS_VBCPROD` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 38 | `COFINS_VALIQPROD` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 39 | `COFINS_VCOFINS` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 40 | `COFINSST_VBC` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 41 | `COFINSST_PCOFINS` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 42 | `COFINSST_QBCPROD` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 43 | `COFINSST_VALIQPROD` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 44 | `COFINSST_VCOFINS` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 45 | `ISSQN_VBC` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 46 | `ISSQN_PVALIQ` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 47 | `ISSQN_VISSQN` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 48 | `ISSQN_CMUNFG` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 49 | `ISSQN_LISTSERV` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 50 | `II_VII` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 51 | `II_VIOF` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 52 | `ISSQN_VALIQ` | `DOUBLE PRECISION` | NULL | v73 | v73 |
| 53 | `ICMS_MODBC` | `INTEGER` | NULL | v73 | v73 |
| 54 | `ICMS_MODBCST` | `INTEGER` | NULL | v73 | v73 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 73 | CREATE | CREATE TABLE com 54 colunas |

