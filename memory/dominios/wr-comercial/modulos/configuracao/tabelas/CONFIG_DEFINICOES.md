---
table: CONFIG_DEFINICOES
module: configuracao
created_at_version: 1442
last_modified_version: 1442
target_version: 1468
columns_count: 15
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CONFIG_DEFINICOES`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `configuracao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1442;
- **Última mudança:** UPDATE 1442;
- **Total colunas (versão 1468):** 15

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `BIGINT PRIMARY KEY` | NULL |  | v1442 | v1442 |
| 2 | `CHAVE` | `VARCHAR(200) UNIQUE` | NOT NULL |  | v1442 | v1442 |
| 3 | `DESCRICAO` | `VARCHAR(500)` | NULL |  | v1442 | v1442 |
| 4 | `TIPO` | `VARCHAR(20)` | NOT NULL |  | v1442 | v1442 |
| 5 | `ESCOPO` | `VARCHAR(20)` | NOT NULL |  | v1442 | v1442 |
| 6 | `DOMINIO` | `VARCHAR(100)` | NULL |  | v1442 | v1442 |
| 7 | `GRUPO` | `VARCHAR(100)` | NULL |  | v1442 | v1442 |
| 8 | `VALOR_PADRAO` | `VARCHAR(4000)` | NULL |  | v1442 | v1442 |
| 9 | `VALIDACAO` | `VARCHAR(4000)` | NULL |  | v1442 | v1442 |
| 10 | `OPCOES_JSON` | `VARCHAR(4000)` | NULL |  | v1442 | v1442 |
| 11 | `ORDEM` | `INTEGER DEFAULT 0` | NULL |  | v1442 | v1442 |
| 12 | `ATIVO` | `VARCHAR(1) DEFAULT 'S'` | NULL |  | v1442 | v1442 |
| 13 | `DT_CRIACAO` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | NULL |  | v1442 | v1442 |
| 14 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1442 | v1442 |
| 15 | `VERSAO` | `INTEGER` | NULL |  | v1442 | v1442 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1442 | CREATE | CREATE TABLE com 14 colunas |
| 1442 | ADD_COL | + VERSAO INTEGER |

