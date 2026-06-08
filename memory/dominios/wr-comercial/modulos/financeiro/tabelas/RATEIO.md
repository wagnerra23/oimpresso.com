---
table: RATEIO
module: financeiro
created_at_version: 588
last_modified_version: 1119
target_version: 1468
columns_count: 3
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `RATEIO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 588;
- **Última mudança:** UPDATE 1119;
- **Total colunas (versão 1468):** 3

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `AGRUPAR_BOLETO_EQUIPAMENTO_SR` | `DOM_BOOLEAN` | NULL |  | v588 | v588 |
| 2 | `BOLETO_EVENTUAL_DATA` | `TIMESTAMP` | NULL |  | v1119 | v1119 |
| 3 | `BOLETO_EVENTUAL_TOTAL` | `DOUBLE PRECISION` | NULL |  | v1119 | v1119 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 588 | ADD_COL | + AGRUPAR_BOLETO_EQUIPAMENTO_SR DOM_BOOLEAN |
| 1119 | ADD_COL | + BOLETO_EVENTUAL_DATA TIMESTAMP |
| 1119 | ADD_COL | + BOLETO_EVENTUAL_TOTAL DOUBLE PRECISION |

