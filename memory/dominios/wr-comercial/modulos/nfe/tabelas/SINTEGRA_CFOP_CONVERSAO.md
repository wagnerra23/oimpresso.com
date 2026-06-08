---
table: SINTEGRA_CFOP_CONVERSAO
module: nfe
created_at_version: 1415
last_modified_version: 1415
target_version: 1468
columns_count: 7
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `SINTEGRA_CFOP_CONVERSAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1415;
- **Última mudança:** UPDATE 1415;
- **Total colunas (versão 1468):** 7

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CFOP_ENTRADA` | `VARCHAR(10)` | NOT NULL |  | v1415 | v1415 |
| 2 | `CFOP_SAIDA` | `VARCHAR(10)` | NOT NULL |  | v1415 | v1415 |
| 3 | `DESCRICAO` | `VARCHAR(250)` | NULL |  | v1415 | v1415 |
| 4 | `TIPO_OPERACAO` | `VARCHAR(20)` | NULL |  | v1415 | v1415 |
| 5 | `REQUER_ATENCAO` | `CHAR(1) DEFAULT 'N'` | NULL |  | v1415 | v1415 |
| 6 | `ATIVO` | `CHAR(1) DEFAULT 'S'` | NULL |  | v1415 | v1415 |
| 7 | `DATA_CADASTRO` | `DATE DEFAULT CURRENT_DATE` | NULL |  | v1415 | v1415 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1415 | CREATE | CREATE TABLE com 7 colunas |

