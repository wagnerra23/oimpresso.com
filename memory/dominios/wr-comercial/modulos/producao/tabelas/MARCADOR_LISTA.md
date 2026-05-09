---
table: MARCADOR_LISTA
module: producao
created_at_version: 390
last_modified_version: 390
target_version: 1468
columns_count: 4
foreign_keys_count: 1
foreign_keys:
  CODPLANOCONTAS: PLANOCONTAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `MARCADOR_LISTA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 390;
- **Última mudança:** UPDATE 390;
- **Total colunas (versão 1468):** 4

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPLANOCONTAS` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `MODO_MARCA` | `VARCHAR(20)` | NULL |  | v390 | v390 |
| 2 | `CODPLANOCONTAS` | `VARCHAR(15)` | NULL | → `PLANOCONTAS` | v390 | v390 |
| 3 | `FORCAR_EXIBICAO` | `VARCHAR(1)` | NULL |  | v390 | v390 |
| 4 | `SQLDESPESAS` | `BLOB SUB_TYPE 1 SEGMENT SIZE 80` | NULL |  | v390 | v390 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 390 | ADD_COL | + MODO_MARCA VARCHAR(20) |
| 390 | ADD_COL | + CODPLANOCONTAS VARCHAR(15) |
| 390 | ADD_COL | + FORCAR_EXIBICAO VARCHAR(1) |
| 390 | ADD_COL | + SQLDESPESAS BLOB SUB_TYPE 1 SEGMENT SIZE 80 |

