---
table: WR_VALOR_INICIAL
module: wr_metadata
created_at_version: 1345
last_modified_version: 1430
target_version: 1468
columns_count: 12
foreign_keys_count: 1
foreign_keys:
  CODWR_CONDICAO: WR_CONDICAO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `WR_VALOR_INICIAL`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1345;
- **Última mudança:** UPDATE 1430;
- **Total colunas (versão 1468):** 12

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODWR_CONDICAO` | [`WR_CONDICAO`](../../wr_metadata/tabelas/WR_CONDICAO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1345 | v1345 |
| 2 | `CODWR_CONDICAO` | `INTEGER` | NULL | → `WR_CONDICAO` | v1345 | v1345 |
| 3 | `TABELA` | `VARCHAR(50)` | NULL |  | v1345 | v1345 |
| 4 | `CAMPO` | `VARCHAR(200)` | NULL |  | v1345 | v1345 |
| 5 | `TEM_GEN` | `VARCHAR(1)` | NULL |  | v1345 | v1345 |
| 6 | `TEM_PK` | `VARCHAR(1)` | NULL |  | v1345 | v1345 |
| 7 | `TEM_MULTEMPRESA` | `VARCHAR(1)` | NULL |  | v1345 | v1345 |
| 8 | `VALOR_INICIAL` | `VARCHAR(500)` | NULL |  | v1345 | v1345 |
| 9 | `TEM_PADRAO` | `VARCHAR(1)` | NULL |  | v1345 | v1345 |
| 10 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1345 | v1345 |
| 11 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1345 | v1345 |
| 12 | `PATH` | `VARCHAR(255)` | NULL |  | v1430 | v1430 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1345 | CREATE | CREATE TABLE com 11 colunas |
| 1430 | ADD_COL | + PATH VARCHAR(255) |

