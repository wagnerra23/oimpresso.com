---
table: CENTRO_DE_CUSTO_RATEIO
module: producao
created_at_version: 306
last_modified_version: 306
target_version: 1468
columns_count: 6
foreign_keys_count: 3
foreign_keys:
  CODEMPRESA: EMPRESA
  CODFINANCEIRO: FINANCEIRO
  CODSETOR: SETOR
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CENTRO_DE_CUSTO_RATEIO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 306;
- **Última mudança:** UPDATE 306;
- **Total colunas (versão 1468):** 6

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |
| `CODFINANCEIRO` | [`FINANCEIRO`](../../financeiro/tabelas/FINANCEIRO.md) |
| `CODSETOR` | [`SETOR`](../../cadastros/tabelas/SETOR.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `TABELA` | `VARCHAR(20)` | NOT NULL |  | v306 | v306 |
| 2 | `CODFINANCEIRO` | `INTEGER` | NOT NULL | → `FINANCEIRO` | v306 | v306 |
| 3 | `CODPEDIDO` | `VARCHAR(10)` | NOT NULL |  | v306 | v306 |
| 4 | `CODEMPRESA` | `VARCHAR(10)` | NOT NULL | → `EMPRESA` | v306 | v306 |
| 5 | `CODSETOR` | `INTEGER` | NOT NULL | → `SETOR` | v306 | v306 |
| 6 | `RATEIO` | `DOUBLE PRECISION` | NULL |  | v306 | v306 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 306 | CREATE | CREATE TABLE com 6 colunas |

