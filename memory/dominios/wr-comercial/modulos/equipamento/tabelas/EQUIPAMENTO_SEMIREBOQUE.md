---
table: EQUIPAMENTO_SEMIREBOQUE
module: equipamento
created_at_version: 588
last_modified_version: 588
target_version: 1468
columns_count: 4
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EQUIPAMENTO_SEMIREBOQUE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `equipamento` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 588;
- **Última mudança:** UPDATE 588;
- **Total colunas (versão 1468):** 4

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODEQUIPAMENTO` | `INTEGER` | NOT NULL | v588 | v588 |
| 2 | `CODEQUIPAMENTO_SEMIREBOQUE` | `INTEGER` | NOT NULL | v588 | v588 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v588 | v588 |
| 4 | `CODUSUARIO` | `INTEGER` | NOT NULL | v588 | v588 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 588 | CREATE | CREATE TABLE com 4 colunas |

