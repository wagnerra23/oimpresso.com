---
table: CLIENTES
module: cadastros
created_at_version: 25
last_modified_version: 192
target_version: 1468
columns_count: 5
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CLIENTES`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 25;
- **Última mudança:** UPDATE 192;
- **Total colunas (versão 1468):** 5

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `SPC_RESPONSAVEL` | `VARCHAR(1)` | NULL |  | v27 | v27 |
| 2 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v102 | v102 |
| 3 | `ATIVO` | `VARCHAR(1)` | NULL |  | v120 | v120 |
| 4 | `QUANT_MAQUINAS` | `integer` | NULL |  | v154 | v154 |
| 5 | `COBRAR_CUSTO_BOLETO` | `varchar(1)` | NULL |  | v192 | v192 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 25 | ALTER_TYPE | ~ FONE1 TYPE VARCHAR(30) |
| 25 | ALTER_TYPE | ~ FONE2 TYPE VARCHAR(30) |
| 25 | ALTER_TYPE | ~ FAX TYPE VARCHAR(30) |
| 27 | ADD_COL | + SPC_RESPONSAVEL VARCHAR(1) |
| 102 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 120 | ADD_COL | + ATIVO VARCHAR(1) |
| 153 | ALTER_TYPE | ~ MOTIVO TYPE VARCHAR(300) |
| 154 | ADD_COL | + QUANT_MAQUINAS integer |
| 191 | ALTER_TYPE | ~ ENDERECO TYPE varchar(60) |
| 192 | ADD_COL | + COBRAR_CUSTO_BOLETO varchar(1) |

