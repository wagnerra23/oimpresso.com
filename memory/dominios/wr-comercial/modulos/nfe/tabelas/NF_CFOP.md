---
table: NF_CFOP
module: nfe
created_at_version: 511
last_modified_version: 940
target_version: 1468
columns_count: 12
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_CFOP`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 511;
- **Última mudança:** UPDATE 940;
- **Total colunas (versão 1468):** 12

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `ENTRADA_SAIDA` | `VARCHAR(10)` | NULL |  | v511 | v511 |
| 2 | `DEVOLUCAO` | `DOM_BOOLEAN` | NULL |  | v704 | v704 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v728 | v728 |
| 4 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v728 | v728 |
| 5 | `PODE_NFE` | `VARCHAR(1)` | NULL |  | v940 | v940 |
| 6 | `PODE_NFCE` | `VARCHAR(1)` | NULL |  | v940 | v940 |
| 7 | `PODE_DEVOLUCAO` | `VARCHAR(1)` | NULL |  | v940 | v940 |
| 8 | `PODE_TRANSPORTE` | `VARCHAR(1)` | NULL |  | v940 | v940 |
| 9 | `PODE_COMUNICACAO` | `VARCHAR(1)` | NULL |  | v940 | v940 |
| 10 | `OPERACAO` | `VARCHAR(20)` | NULL |  | v940 | v940 |
| 11 | `SUBISTITUICAO_TRIBUTARIA` | `VARCHAR(50)` | NULL |  | v940 | v940 |
| 12 | `TIPO` | `VARCHAR(50)` | NULL |  | v940 | v940 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 511 | ADD_COL | + ENTRADA_SAIDA VARCHAR(10) |
| 704 | ADD_COL | + DEVOLUCAO DOM_BOOLEAN |
| 728 | ADD_COL | + ATIVO VARCHAR(1) |
| 728 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 940 | ADD_COL | + PODE_NFE VARCHAR(1) |
| 940 | ADD_COL | + PODE_NFCE VARCHAR(1) |
| 940 | ADD_COL | + PODE_DEVOLUCAO VARCHAR(1) |
| 940 | ADD_COL | + PODE_TRANSPORTE VARCHAR(1) |
| 940 | ADD_COL | + PODE_COMUNICACAO VARCHAR(1) |
| 940 | ADD_COL | + OPERACAO VARCHAR(20) |
| 940 | ADD_COL | + SUBISTITUICAO_TRIBUTARIA VARCHAR(50) |
| 940 | ADD_COL | + TIPO VARCHAR(50) |

