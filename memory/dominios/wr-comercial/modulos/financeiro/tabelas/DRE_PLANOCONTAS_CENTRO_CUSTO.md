---
table: DRE_PLANOCONTAS_CENTRO_CUSTO
module: financeiro
created_at_version: 945
last_modified_version: 1373
target_version: 1468
columns_count: 15
foreign_keys_count: 3
foreign_keys:
  CODDRE: DRE
  CODDRE_CENTRO_CUSTO: DRE_CENTRO_CUSTO
  CODPLANOCONTAS: PLANOCONTAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `DRE_PLANOCONTAS_CENTRO_CUSTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 945;
- **Última mudança:** UPDATE 1373;
- **Total colunas (versão 1468):** 15

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODDRE` | [`DRE`](../../financeiro/tabelas/DRE.md) |
| `CODDRE_CENTRO_CUSTO` | [`DRE_CENTRO_CUSTO`](../../financeiro/tabelas/DRE_CENTRO_CUSTO.md) |
| `CODPLANOCONTAS` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v945 | v945 |
| 2 | `CODDRE` | `INTEGER` | NOT NULL | → `DRE` | v945 | v945 |
| 3 | `CODDRE_CENTRO_CUSTO` | `INTEGER` | NOT NULL | → `DRE_CENTRO_CUSTO` | v945 | v945 |
| 4 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v945 | v945 |
| 5 | `PERCENTUAL_VERTICAL` | `DOUBLE PRECISION` | NULL |  | v945 | v945 |
| 6 | `PERCENTUAL_HORIZONTAL` | `DOUBLE PRECISION` | NULL |  | v945 | v945 |
| 7 | `PERCENTUAL_PARCIAL` | `DOUBLE PRECISION` | NULL |  | v945 | v945 |
| 8 | `PLANOCONTAS` | `VARCHAR(300)` | NULL |  | v947 | v947 |
| 9 | `CLASSIFICACAO` | `VARCHAR(300)` | NULL |  | v947 | v947 |
| 10 | `CODPLANOCONTAS` | `VARCHAR(20)` | NOT NULL | → `PLANOCONTAS` | v945 | v947 |
| 11 | `PERSONALIZADO` | `VARCHAR(1)` | NULL |  | v961 | v961 |
| 12 | `PERCENTUAL_VERTICAL_ABSOLUTO` | `DOUBLE PRECISION` | NULL |  | v958 | v958 |
| 13 | `SEQUENCIA` | `INTEGER` | NULL |  | v1373 | v1373 |
| 14 | `COR` | `INTEGER` | NULL |  | v1373 | v1373 |
| 15 | `COR_FONT` | `INTEGER` | NULL |  | v1373 | v1373 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 945 | CREATE | CREATE TABLE com 8 colunas |
| 947 | ADD_COL | + PLANOCONTAS VARCHAR(300) |
| 947 | ADD_COL | + CLASSIFICACAO VARCHAR(300) |
| 947 | RENAME_COL | × CODPLANO_CONTAS → CODPLANOCONTAS |
| 951 | ADD_COL | + PERSONALIZADO VARCHAR(1) |
| 958 | ADD_COL | + PERCENTUAL_VERTICAL_ABSOLUTO DOUBLE PRECISION |
| 961 | ADD_COL | + PERSONALIZADO VARCHAR(1) |
| 1373 | ADD_COL | + SEQUENCIA INTEGER |
| 1373 | ADD_COL | + COR INTEGER |
| 1373 | ADD_COL | + COR_FONT INTEGER |

