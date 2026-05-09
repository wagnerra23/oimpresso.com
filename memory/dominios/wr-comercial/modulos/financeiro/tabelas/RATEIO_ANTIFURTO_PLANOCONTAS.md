---
table: RATEIO_ANTIFURTO_PLANOCONTAS
module: financeiro
created_at_version: 727
last_modified_version: 727
target_version: 1468
columns_count: 2
foreign_keys_count: 2
foreign_keys:
  CODPLANOCONTAS: PLANOCONTAS
  CODRATEIO: RATEIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `RATEIO_ANTIFURTO_PLANOCONTAS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 727;
- **Última mudança:** UPDATE 727;
- **Total colunas (versão 1468):** 2

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPLANOCONTAS` | [`PLANOCONTAS`](../../financeiro/tabelas/PLANOCONTAS.md) |
| `CODRATEIO` | [`RATEIO`](../../financeiro/tabelas/RATEIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODRATEIO` | `INTEGER` | NOT NULL | → `RATEIO` | v727 | v727 |
| 2 | `CODPLANOCONTAS` | `VARCHAR(15)` | NOT NULL | → `PLANOCONTAS` | v727 | v727 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 727 | CREATE | CREATE TABLE com 2 colunas |

