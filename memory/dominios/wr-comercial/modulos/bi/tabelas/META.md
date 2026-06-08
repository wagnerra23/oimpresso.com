---
table: META
module: bi
created_at_version: 1147
last_modified_version: 1150
target_version: 1468
columns_count: 11
foreign_keys_count: 1
foreign_keys:
  CODEMPRESA: EMPRESA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `META`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `bi` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1147;
- **Última mudança:** UPDATE 1150;
- **Total colunas (versão 1468):** 11

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1147 | v1147 |
| 2 | `CODEMPRESA` | `INTEGER` | NOT NULL | → `EMPRESA` | v1147 | v1147 |
| 3 | `DESCRICAO` | `VARCHAR(100)` | NOT NULL |  | v1147 | v1147 |
| 4 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1147 | v1147 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1147 | v1147 |
| 6 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v1147 | v1147 |
| 7 | `DT_INICIO` | `TIMESTAMP` | NULL |  | v1147 | v1147 |
| 8 | `DT_FIM` | `TIMESTAMP` | NULL |  | v1147 | v1147 |
| 9 | `DIAS` | `INTEGER` | NULL |  | v1147 | v1147 |
| 10 | `SABADO_DOMINGO` | `INTEGER` | NULL |  | v1147 | v1147 |
| 11 | `DATA` | `TIMESTAMP` | NULL |  | v1150 | v1150 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1147 | CREATE | CREATE TABLE com 10 colunas |
| 1150 | ADD_COL | + DATA TIMESTAMP |

