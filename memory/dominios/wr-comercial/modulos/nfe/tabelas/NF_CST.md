---
table: NF_CST
module: nfe
created_at_version: 946
last_modified_version: 1423
target_version: 1468
columns_count: 13
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_CST`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 946;
- **Última mudança:** UPDATE 1423;
- **Total colunas (versão 1468):** 13

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `ATIVO` | `VARCHAR(1), ADD DT_ALTERACAO TIMESTAMP` | NULL |  | v946 | v946 |
| 2 | `TEM_TRIBUTO` | `VARCHAR(1), ADD TEM_BC VARCHAR(1), ADD TEM_CALCULO_ICMS VARCHAR(1), ADD TEM_ST VARCHAR(1), ADD TEM_REDUCAO VARCHAR(1), ADD TEM_BASE VARCHAR(1)` | NULL |  | v946 | v946 |
| 3 | `TEM_EXIGE_TRIBUTACAO` | `VARCHAR(1)` | NULL |  | v1423 | v1423 |
| 4 | `TEM_REDUCAO_BC_CST` | `VARCHAR(1)` | NULL |  | v1423 | v1423 |
| 5 | `TEM_REDUCAO_ALIQUOTA` | `VARCHAR(1)` | NULL |  | v1423 | v1423 |
| 6 | `TEM_TRANSFERENCIA_CREDITO` | `VARCHAR(1)` | NULL |  | v1423 | v1423 |
| 7 | `TEM_DIFERIMENTO` | `VARCHAR(1)` | NULL |  | v1423 | v1423 |
| 8 | `TEM_MONOFASICA` | `VARCHAR(1)` | NULL |  | v1423 | v1423 |
| 9 | `TEM_CREDITO_PRESUMIDO_IBS_ZFM` | `VARCHAR(1)` | NULL |  | v1423 | v1423 |
| 10 | `TEM_AJUSTE_COMPETENCIA` | `VARCHAR(1)` | NULL |  | v1423 | v1423 |
| 11 | `TEM_TRIBUTACAO_REGULAR` | `VARCHAR(1)` | NULL |  | v1423 | v1423 |
| 12 | `NUMERO_ANEXO` | `VARCHAR(10)` | NULL |  | v1423 | v1423 |
| 13 | `URL_LEGISLACAO` | `VARCHAR(500)` | NULL |  | v1423 | v1423 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 946 | ADD_COL | + ATIVO VARCHAR(1), ADD DT_ALTERACAO TIMESTAMP |
| 946 | ADD_COL | + TEM_TRIBUTO VARCHAR(1), ADD TEM_BC VARCHAR(1), ADD TEM_CALCULO_ICMS VARCHAR(1), ADD TEM_ST VARCHAR(1), ADD TEM_REDUCAO VARCHAR(1), ADD TEM_BASE VARCHAR(1) |
| 1423 | ADD_COL | + TEM_EXIGE_TRIBUTACAO VARCHAR(1) |
| 1423 | ADD_COL | + TEM_REDUCAO_BC_CST VARCHAR(1) |
| 1423 | ADD_COL | + TEM_REDUCAO_ALIQUOTA VARCHAR(1) |
| 1423 | ADD_COL | + TEM_TRANSFERENCIA_CREDITO VARCHAR(1) |
| 1423 | ADD_COL | + TEM_DIFERIMENTO VARCHAR(1) |
| 1423 | ADD_COL | + TEM_MONOFASICA VARCHAR(1) |
| 1423 | ADD_COL | + TEM_CREDITO_PRESUMIDO_IBS_ZFM VARCHAR(1) |
| 1423 | ADD_COL | + TEM_AJUSTE_COMPETENCIA VARCHAR(1) |
| 1423 | ADD_COL | + TEM_TRIBUTACAO_REGULAR VARCHAR(1) |
| 1423 | ADD_COL | + NUMERO_ANEXO VARCHAR(10) |
| 1423 | ADD_COL | + URL_LEGISLACAO VARCHAR(500) |

