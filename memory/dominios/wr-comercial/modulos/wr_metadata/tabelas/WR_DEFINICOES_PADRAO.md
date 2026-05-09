---
table: WR_DEFINICOES_PADRAO
module: wr_metadata
created_at_version: 1439
last_modified_version: 1439
target_version: 1468
columns_count: 9
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `WR_DEFINICOES_PADRAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1439;
- **Última mudança:** UPDATE 1439;
- **Total colunas (versão 1468):** 9

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `codigo` | `INTEGER` | NOT NULL | v1439 | v1439 |
| 2 | `codwr_definicoes` | `INTEGER` | NOT NULL | v1439 | v1439 |
| 3 | `campo` | `VARCHAR(100)` | NOT NULL | v1439 | v1439 |
| 4 | `valor_padrao` | `VARCHAR(500)` | NULL | v1439 | v1439 |
| 5 | `tipo_dado` | `VARCHAR(20) DEFAULT 'STRING'` | NULL | v1439 | v1439 |
| 6 | `condicao_ativa` | `VARCHAR(500)` | NULL | v1439 | v1439 |
| 7 | `ordem` | `INTEGER DEFAULT 0` | NULL | v1439 | v1439 |
| 8 | `ativo` | `VARCHAR(1) DEFAULT 'S'` | NOT NULL | v1439 | v1439 |
| 9 | `dt_alteracao` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | NULL | v1439 | v1439 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1439 | CREATE | CREATE TABLE com 9 colunas |

