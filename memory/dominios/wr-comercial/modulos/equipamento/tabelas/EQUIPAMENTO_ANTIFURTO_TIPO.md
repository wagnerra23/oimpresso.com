---
table: EQUIPAMENTO_ANTIFURTO_TIPO
module: equipamento
created_at_version: 570
last_modified_version: 591
target_version: 1468
columns_count: 5
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EQUIPAMENTO_ANTIFURTO_TIPO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `equipamento` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 570;
- **Última mudança:** UPDATE 591;
- **Total colunas (versão 1468):** 5

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODEQUIPAMENTO` | `INTEGER` | NOT NULL | v570 | v570 |
| 2 | `CODANTIFURTO_TIPO` | `INTEGER` | NOT NULL | v570 | v570 |
| 3 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v570 | v570 |
| 4 | `CODUSUARIO` | `INTEGER` | NULL | v570 | v570 |
| 5 | `DT_INSTALACAO` | `DATE` | NULL | v591 | v591 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 570 | CREATE | CREATE TABLE com 4 colunas |
| 591 | ADD_COL | + DT_INSTALACAO DATE |

