---
table: SPED_C190
module: nfe
created_at_version: 882
last_modified_version: 882
target_version: 1468
columns_count: 13
foreign_keys_count: 1
foreign_keys:
  CODSPED: SPED
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `SPED_C190`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 882;
- **Última mudança:** UPDATE 882;
- **Total colunas (versão 1468):** 13

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
| 3 | `CST_ICMS` | `INTEGER` | NOT NULL |  | v882 | v882 |
| 4 | `CFOP` | `INTEGER` | NOT NULL |  | v882 | v882 |
| 5 | `ALIQ_ICMS` | `DOUBLE PRECISION` | NULL |  | v882 | v882 |
| 6 | `VL_OPR` | `DOUBLE PRECISION` | NOT NULL |  | v882 | v882 |
| 7 | `VL_BC_ICMS` | `DOUBLE PRECISION` | NOT NULL |  | v882 | v882 |
| 8 | `VL_ICMS` | `DOUBLE PRECISION` | NOT NULL |  | v882 | v882 |
| 9 | `VL_BC_ICMS_ST` | `DOUBLE PRECISION` | NOT NULL |  | v882 | v882 |
| 10 | `VL_ICMS_ST` | `DOUBLE PRECISION` | NOT NULL |  | v882 | v882 |
| 11 | `VL_RED_BC` | `DOUBLE PRECISION` | NOT NULL |  | v882 | v882 |
| 12 | `VL_IPI` | `DOUBLE PRECISION` | NOT NULL |  | v882 | v882 |
| 13 | `COD_OBS` | `VARCHAR(6)` | NULL |  | v882 | v882 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 882 | CREATE | CREATE TABLE com 13 colunas |

