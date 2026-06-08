---
table: SPED_C170
module: nfe
created_at_version: 882
last_modified_version: 882
target_version: 1468
columns_count: 37
foreign_keys_count: 1
foreign_keys:
  CODSPED: SPED
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `SPED_C170`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 882;
- **Última mudança:** UPDATE 882;
- **Total colunas (versão 1468):** 37

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODSPED` | [`SPED`](../../nfe/tabelas/SPED.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODSPED` | `INTEGER` | NOT NULL | → `SPED` | v882 | v882 |
| 2 | `CODPEDIDO` | `VARCHAR(10)` | NULL |  | v882 | v882 |
| 3 | `NUM_ITEM` | `INTEGER` | NOT NULL |  | v882 | v882 |
| 4 | `COD_ITEM` | `VARCHAR(60)` | NOT NULL |  | v882 | v882 |
| 5 | `DESCR_COMPL` | `VARCHAR(150)` | NULL |  | v882 | v882 |
| 6 | `QTD` | `DOUBLE PRECISION` | NOT NULL |  | v882 | v882 |
| 7 | `UNID` | `VARCHAR(6)` | NOT NULL |  | v882 | v882 |
| 8 | `VL_ITEM` | `DOUBLE PRECISION` | NOT NULL |  | v882 | v882 |
| 9 | `VL_DESC` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 10 | `IND_MOV` | `VARCHAR(1)` | NOT NULL |  | v882 | v882 |
| 11 | `CST_ICMS` | `INTEGER` | NOT NULL |  | v882 | v882 |
| 12 | `CFOP` | `INTEGER` | NOT NULL |  | v882 | v882 |
| 13 | `COD_NAT` | `VARCHAR(10)` | NULL |  | v882 | v882 |
| 14 | `VL_BC_ICMS` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 15 | `ALIQ_ICMS` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 16 | `VL_ICMS` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 17 | `VL_BC_ICMS_ST` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 18 | `ALIQ_ST` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 19 | `VL_ICMS_ST` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 20 | `IND_APUR` | `VARCHAR(1)` | NULL |  | v882 | v882 |
| 21 | `CST_IPI` | `VARCHAR(2)` | NULL |  | v882 | v882 |
| 22 | `COD_ENQ` | `VARCHAR(3)` | NULL |  | v882 | v882 |
| 23 | `VL_BC_IPI` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 24 | `ALIQ_IPI` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 25 | `VL_IPI` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 26 | `CST_PIS` | `INTEGER` | NULL |  | v882 | v882 |
| 27 | `VL_BC_PIS` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 28 | `ALIQ_PIS` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 29 | `QUANT_BC_PIS` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 30 | `VL_PIS` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 31 | `CST_COFINS` | `INTEGER` | NULL |  | v882 | v882 |
| 32 | `VL_BC_COFINS` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 33 | `QUANT_BC_COFINS` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 34 | `ALIQ_COFINS` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 35 | `VL_COFINS` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 36 | `COD_CTA` | `VARCHAR(150)` | NULL |  | v882 | v882 |
| 37 | `VL_ABAT_NT` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 882 | CREATE | CREATE TABLE com 37 colunas |

