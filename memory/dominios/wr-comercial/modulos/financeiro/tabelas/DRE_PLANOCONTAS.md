---
table: DRE_PLANOCONTAS
module: financeiro
created_at_version: 495
last_modified_version: 1370
target_version: 1468
columns_count: 8
foreign_keys_count: 3
foreign_keys:
  CODCENTRO_CUSTO: CENTRO_CUSTO
  CODDRE: DRE
  CODPLANOCONTAS: PLANOCONTAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `DRE_PLANOCONTAS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 495;
- **Última mudança:** UPDATE 1370;
- **Total colunas (versão 1468):** 8

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCENTRO_CUSTO` | [`CENTRO_CUSTO`](../../producao/tabelas/CENTRO_CUSTO.md) |
| `CODDRE` | [`DRE`](../../financeiro/tabelas/DRE.md) |
| `CODPLANOCONTAS` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v495 | v495 |
| 2 | `CODDRE` | `INTEGER` | NOT NULL | → `DRE` | v495 | v495 |
| 3 | `CODPLANOCONTAS` | `VARCHAR(30)` | NULL | → `PLANOCONTAS` | v495 | v495 |
| 4 | `TOTAL_RECEITAS` | `DOUBLE PRECISION` | NULL |  | v495 | v495 |
| 5 | `TOTAL_DESPESAS` | `DOUBLE PRECISION` | NULL |  | v495 | v495 |
| 6 | `SALDO` | `DOUBLE PRECISION` | NULL |  | v495 | v495 |
| 7 | `TOTAL_QUANT_FINANCEIRO` | `INTEGER` | NULL |  | v495 | v495 |
| 8 | `CODCENTRO_CUSTO` | `INTEGER` | NULL | → `CENTRO_CUSTO` | v1370 | v1370 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 495 | CREATE | CREATE TABLE com 7 colunas |
| 1370 | ADD_COL | + CODCENTRO_CUSTO INTEGER |

