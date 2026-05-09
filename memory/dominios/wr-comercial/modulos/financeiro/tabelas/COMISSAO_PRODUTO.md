---
table: COMISSAO_PRODUTO
module: financeiro
created_at_version: 484
last_modified_version: 484
target_version: 1468
columns_count: 12
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `COMISSAO_PRODUTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 484;
- **Última mudança:** UPDATE 484;
- **Total colunas (versão 1468):** 12

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODCOMISSAO` | `INTEGER` | NOT NULL | v484 | v484 |
| 2 | `CODIGO` | `INTEGER` | NOT NULL | v484 | v484 |
| 3 | `CODPRODUTO` | `VARCHAR(15)` | NULL | v484 | v484 |
| 4 | `CODVENDA` | `VARCHAR(10)` | NULL | v484 | v484 |
| 5 | `CODVENDA_PRODUTO` | `INTEGER` | NULL | v484 | v484 |
| 6 | `PESSOA_RESPONSAVEL_CODIGO` | `VARCHAR(10)` | NULL | v484 | v484 |
| 7 | `PESSOA_RESPONSAVEL_TIPO` | `VARCHAR(3)` | NULL | v484 | v484 |
| 8 | `PESSOA_RESPONSAVEL_SEQUENCIA` | `INTEGER` | NULL | v484 | v484 |
| 9 | `GERA_COMISSAO` | `VARCHAR(1)` | NULL | v484 | v484 |
| 10 | `PERC` | `DOUBLE PRECISION` | NULL | v484 | v484 |
| 11 | `VALOR` | `DOUBLE PRECISION` | NULL | v484 | v484 |
| 12 | `VALOR_COMISSAO` | `DOUBLE PRECISION` | NULL | v484 | v484 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 484 | CREATE | CREATE TABLE com 12 colunas |

