---
table: FINANCEIRO_HIST_AGRUPAMENTO
module: financeiro
created_at_version: 424
last_modified_version: 424
target_version: 1468
columns_count: 5
foreign_keys_count: 2
foreign_keys:
  CODEMPRESA: EMPRESA
  CODFINANCEIRO_HISTORICO: FINANCEIRO_HISTORICO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FINANCEIRO_HIST_AGRUPAMENTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 424;
- **Última mudança:** UPDATE 424;
- **Total colunas (versão 1468):** 5

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |
| `CODFINANCEIRO_HISTORICO` | [`FINANCEIRO_HISTORICO`](../../financeiro/tabelas/FINANCEIRO_HISTORICO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v424 | v424 |
| 2 | `CODPEDIDO` | `VARCHAR(10)` | NOT NULL |  | v424 | v424 |
| 3 | `CODEMPRESA` | `VARCHAR(10)` | NOT NULL | → `EMPRESA` | v424 | v424 |
| 4 | `SEQUENCIA` | `INTEGER` | NOT NULL |  | v424 | v424 |
| 5 | `CODFINANCEIRO_HISTORICO` | `INTEGER` | NULL | → `FINANCEIRO_HISTORICO` | v424 | v424 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 424 | CREATE | CREATE TABLE com 5 colunas |

