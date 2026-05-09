---
table: FUNCIONARIO_PONTO
module: cadastros
created_at_version: 41
last_modified_version: 758
target_version: 1468
columns_count: 10
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FUNCIONARIO_PONTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 41;
- **Última mudança:** UPDATE 758;
- **Total colunas (versão 1468):** 10

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v41 | v41 |
| 2 | `CODFUNCIONARIO` | `VARCHAR(15)` | NOT NULL | v41 | v41 |
| 3 | `ENTRADA1` | `TIMESTAMP` | NULL | v41 | v41 |
| 4 | `SAIDA1` | `TIMESTAMP` | NULL | v41 | v41 |
| 5 | `entrada2` | `timestamp` | NULL | v41 | v41 |
| 6 | `saida2` | `timestamp` | NULL | v41 | v41 |
| 7 | `OBSERVACAO` | `VARCHAR(150)` | NULL | v41 | v41 |
| 8 | `DT_ALTERACAO` | `timestamp` | NULL | v174 | v174 |
| 9 | `DIA_REFERENCIA` | `timestamp` | NULL | v274 | v274 |
| 10 | `FALTA_JUSTIFICADA` | `VARCHAR(1)` | NULL | v337 | v337 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 41 | CREATE | CREATE TABLE com 7 colunas |
| 41 | RENAME_COL | × data_entrada → entrada1 |
| 41 | RENAME_COL | × data_saida → saida1 |
| 41 | ADD_COL | + entrada2 timestamp |
| 41 | ADD_COL | + saida2 timestamp |
| 174 | ADD_COL | + DT_ALTERACAO timestamp |
| 274 | ADD_COL | + DIA_REFERENCIA timestamp |
| 336 | ADD_COL | + FALTA_JUSTIFICADA VARCHAR(1) |
| 337 | ADD_COL | + FALTA_JUSTIFICADA VARCHAR(1) |
| 758 | DROP_COL | - SAIDA2A |

