---
table: SINTEGRA_R61
module: nfe
created_at_version: 1088
last_modified_version: 1088
target_version: 1468
columns_count: 14
foreign_keys_count: 1
foreign_keys:
  CODSINTEGRA: SINTEGRA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `SINTEGRA_R61`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1088;
- **Última mudança:** UPDATE 1088;
- **Total colunas (versão 1468):** 14

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODSINTEGRA` | [`SINTEGRA`](../../nfe/tabelas/SINTEGRA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1088 | v1088 |
| 2 | `CODSINTEGRA` | `INTEGER` | NULL | → `SINTEGRA` | v1088 | v1088 |
| 3 | `DT_EMISSAO` | `TIMESTAMP` | NULL |  | v1088 | v1088 |
| 4 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v1088 | v1088 |
| 5 | `NF_VICMS` | `DOUBLE PRECISION` | NULL |  | v1088 | v1088 |
| 6 | `OUTRAS` | `DOUBLE PRECISION` | NULL |  | v1088 | v1088 |
| 7 | `NF_VBC` | `DOUBLE PRECISION` | NULL |  | v1088 | v1088 |
| 8 | `ISENTAS` | `DOUBLE PRECISION` | NULL |  | v1088 | v1088 |
| 9 | `NF_NUM_ORDEM_INICIAL` | `INTEGER` | NULL |  | v1088 | v1088 |
| 10 | `NF_NUM_ORDEM_FINAL` | `INTEGER` | NULL |  | v1088 | v1088 |
| 11 | `MODELO` | `VARCHAR(2)` | NULL |  | v1088 | v1088 |
| 12 | `SERIE` | `VARCHAR(3)` | NULL |  | v1088 | v1088 |
| 13 | `SUBSERIE` | `VARCHAR(2)` | NULL |  | v1088 | v1088 |
| 14 | `ALIQUOTA` | `DOUBLE PRECISION` | NULL |  | v1088 | v1088 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1088 | CREATE | CREATE TABLE com 14 colunas |

