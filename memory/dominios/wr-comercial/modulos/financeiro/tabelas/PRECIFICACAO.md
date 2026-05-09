---
table: PRECIFICACAO
module: financeiro
created_at_version: 1308
last_modified_version: 1308
target_version: 1468
columns_count: 16
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRECIFICACAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1308;
- **Última mudança:** UPDATE 1308;
- **Total colunas (versão 1468):** 16

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NULL | v1308 | v1308 |
| 2 | `CODCOMPETENCIA` | `INTEGER` | NOT NULL | v1308 | v1308 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v1308 | v1308 |
| 4 | `ATIVO` | `VARCHAR(1)` | NULL | v1308 | v1308 |
| 5 | `CODEMPRESA` | `INTEGER` | NOT NULL | v1308 | v1308 |
| 6 | `DESCRICAO` | `VARCHAR(300)` | NULL | v1308 | v1308 |
| 7 | `TIPO_RATEIO` | `VARCHAR(20)` | NULL | v1308 | v1308 |
| 8 | `TOTAL_SALARIO` | `DOUBLE PRECISION` | NULL | v1308 | v1308 |
| 9 | `TOTAL_ENCARGOS` | `DOUBLE PRECISION` | NULL | v1308 | v1308 |
| 10 | `TOTAL_HORAS` | `DOUBLE PRECISION` | NULL | v1308 | v1308 |
| 11 | `DT_FECHAMENTO` | `TIMESTAMP` | NULL | v1308 | v1308 |
| 12 | `ATUALIZA_EQUIPE` | `VARCHAR(1)` | NULL | v1308 | v1308 |
| 13 | `ATUALIZA_FUNCIONARIO` | `VARCHAR(1)` | NULL | v1308 | v1308 |
| 14 | `CODUSUARIO_FECHAMENTO` | `INTEGER` | NULL | v1308 | v1308 |
| 15 | `USUARIO_FECHAMENTO` | `VARCHAR(30)` | NULL | v1308 | v1308 |
| 16 | `FECHOU_SEM_ATUALIZAR` | `VARCHAR(1)` | NULL | v1308 | v1308 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1308 | CREATE | CREATE TABLE com 16 colunas |

