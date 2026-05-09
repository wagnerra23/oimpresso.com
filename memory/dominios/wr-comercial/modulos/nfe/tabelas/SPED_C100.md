---
table: SPED_C100
module: nfe
created_at_version: 882
last_modified_version: 882
target_version: 1468
columns_count: 30
foreign_keys_count: 1
foreign_keys:
  CODSPED: SPED
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `SPED_C100`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 882;
- **Última mudança:** UPDATE 882;
- **Total colunas (versão 1468):** 30

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
| 3 | `IND_OPER` | `VARCHAR(1)` | NOT NULL |  | v882 | v882 |
| 4 | `IND_EMIT` | `VARCHAR(1)` | NOT NULL |  | v882 | v882 |
| 5 | `COD_PART` | `VARCHAR(60)` | NOT NULL |  | v882 | v882 |
| 6 | `COD_MOD` | `VARCHAR(2)` | NOT NULL |  | v882 | v882 |
| 7 | `COD_SIT` | `INTEGER` | NOT NULL |  | v882 | v882 |
| 8 | `SER` | `VARCHAR(10)` | NULL |  | v882 | v882 |
| 9 | `NUN_DOC` | `INTEGER` | NOT NULL |  | v882 | v882 |
| 10 | `CHV_NFE` | `INTEGER` | NULL |  | v882 | v882 |
| 11 | `DT_DOC` | `TIMESTAMP` | NOT NULL |  | v882 | v882 |
| 12 | `DT_E_S` | `TIMESTAMP` | NULL |  | v882 | v882 |
| 13 | `VL_DOC` | `DOUBLE PRECISION` | NOT NULL |  | v882 | v882 |
| 14 | `IND_PAGTO` | `VARCHAR(1)` | NOT NULL |  | v882 | v882 |
| 15 | `VL_DESC` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 16 | `VL_ABAT_NT` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 17 | `VL_MERC` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 18 | `IND_FRT` | `VARCHAR(1)` | NOT NULL |  | v882 | v882 |
| 19 | `VL_FRT` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 20 | `VL_SEG` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 21 | `VL_OUT_DA` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 22 | `VL_BC_ICMS` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 23 | `VL_ICMS` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 24 | `VL_BC_ICMS_ST` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 25 | `VL_ICMS_ST` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 26 | `VL_IPI` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 27 | `VL_PIS` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 28 | `VL_CONFINS` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 29 | `VL_PIS_ST` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 30 | `VL_CONFINS_ST` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 882 | CREATE | CREATE TABLE com 30 colunas |

