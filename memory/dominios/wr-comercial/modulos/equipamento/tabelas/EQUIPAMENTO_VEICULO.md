---
table: EQUIPAMENTO_VEICULO
module: equipamento
created_at_version: 44
last_modified_version: 1380
target_version: 1468
columns_count: 22
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EQUIPAMENTO_VEICULO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `equipamento` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 44;
- **Última mudança:** UPDATE 1380;
- **Total colunas (versão 1468):** 22

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `VARCHAR(10)` | NOT NULL |  | v44 | v44 |
| 2 | `CHASSI` | `VARCHAR(20)` | NULL |  | v44 | v44 |
| 3 | `HP` | `DOUBLE PRECISION` | NULL |  | v44 | v44 |
| 4 | `CILINDRADA` | `DOUBLE PRECISION` | NULL |  | v44 | v44 |
| 5 | `COMBUSTIVEL` | `VARCHAR(10)` | NULL |  | v44 | v44 |
| 6 | `MOTOR` | `VARCHAR(20)` | NULL |  | v44 | v44 |
| 7 | `RENAVAN` | `VARCHAR(20)` | NULL |  | v44 | v44 |
| 8 | `ANO_MODELO` | `VARCHAR(20)` | NULL |  | v44 | v44 |
| 9 | `KM` | `VARCHAR(7)` | NULL |  | v44 | v44 |
| 10 | `PASSAGEIROS` | `DOUBLE PRECISION` | NULL |  | v44 | v44 |
| 11 | `PLACA` | `VARCHAR(7)` | NULL |  | v44 | v44 |
| 12 | `PESO_LIQUIDO` | `DOUBLE PRECISION` | NULL |  | v44 | v44 |
| 13 | `PESO_BRUTO` | `DOUBLE PRECISION` | NULL |  | v44 | v44 |
| 14 | `NUMERO_SERIE` | `VARCHAR(20)` | NULL |  | v44 | v44 |
| 15 | `ANO_FABRICACAO` | `VARCHAR(20)` | NULL |  | v44 | v44 |
| 16 | `TIPO` | `VARCHAR(20)` | NULL |  | v44 | v44 |
| 17 | `ESPECIE` | `VARCHAR(20)` | NULL |  | v44 | v44 |
| 18 | `DIST` | `VARCHAR(20)` | NULL |  | v44 | v44 |
| 19 | `CONDICAO_VEICULO` | `VARCHAR(20)` | NULL |  | v44 | v44 |
| 20 | `CMOD` | `VARCHAR(20)` | NULL |  | v44 | v44 |
| 21 | `PLACA2` | `VARCHAR(7)` | NULL |  | v1380 | v1380 |
| 22 | `CHASSI2` | `VARCHAR(20)` | NULL |  | v1380 | v1380 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 44 | CREATE | CREATE TABLE com 20 colunas |
| 1380 | ADD_COL | + PLACA2 VARCHAR(7) |
| 1380 | ADD_COL | + CHASSI2 VARCHAR(20) |

