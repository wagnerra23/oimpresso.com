---
table: EQUIPAMENTO_IMPRESSORA
module: equipamento
created_at_version: 198
last_modified_version: 198
target_version: 1468
columns_count: 6
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EQUIPAMENTO_IMPRESSORA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `equipamento` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 198;
- **Última mudança:** UPDATE 198;
- **Total colunas (versão 1468):** 6

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `VARCHAR(10)` | NOT NULL |  | v198 | v198 |
| 2 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v198 | v198 |
| 3 | `TEMPO_PAGTO_DESEJADO` | `INTEGER` | NULL |  | v198 | v198 |
| 4 | `MARCA` | `VARCHAR(50)` | NULL |  | v198 | v198 |
| 5 | `MODELO` | `VARCHAR(100)` | NULL |  | v198 | v198 |
| 6 | `QUANT_CORES` | `INTEGER` | NULL |  | v198 | v198 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 198 | CREATE | CREATE TABLE com 6 colunas |

