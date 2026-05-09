---
table: EMAIL_MODELO
module: agenda
created_at_version: 560
last_modified_version: 746
target_version: 1468
columns_count: 5
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EMAIL_MODELO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 560;
- **Última mudança:** UPDATE 746;
- **Total colunas (versão 1468):** 5

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `FORM` | `VARCHAR(40)` | NULL |  | v560 | v560 |
| 2 | `PADRAO` | `DOM_BOOLEAN` | NULL |  | v570 | v570 |
| 3 | `ASSUNTO` | `VARCHAR(150)` | NULL |  | v572 | v572 |
| 4 | `ATIVO` | `VARCHAR(1)` | NULL |  | v746 | v746 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v746 | v746 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 560 | ADD_COL | + FORM VARCHAR(40) |
| 570 | ADD_COL | + PADRAO DOM_BOOLEAN |
| 572 | ADD_COL | + ASSUNTO VARCHAR(150) |
| 746 | ADD_COL | + ATIVO VARCHAR(1) |
| 746 | ADD_COL | + DT_ALTERACAO TIMESTAMP |

