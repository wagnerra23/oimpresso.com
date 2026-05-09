---
table: PESSOAS_CREDITO
module: cadastros
created_at_version: 878
last_modified_version: 878
target_version: 1468
columns_count: 6
foreign_keys_count: 1
foreign_keys:
  CODPESSOA: PESSOAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PESSOAS_CREDITO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 878;
- **Última mudança:** UPDATE 878;
- **Total colunas (versão 1468):** 6

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPESSOA` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v878 | v878 |
| 2 | `CODPESSOA` | `VARCHAR(15)` | NULL | → `PESSOAS` | v878 | v878 |
| 3 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v878 | v878 |
| 4 | `SALDO` | `DOUBLE PRECISION` | NULL |  | v878 | v878 |
| 5 | `DATA` | `TIMESTAMP` | NULL |  | v878 | v878 |
| 6 | `OBSERVACAO` | `VARCHAR(5000)` | NULL |  | v878 | v878 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 878 | CREATE | CREATE TABLE com 6 colunas |

