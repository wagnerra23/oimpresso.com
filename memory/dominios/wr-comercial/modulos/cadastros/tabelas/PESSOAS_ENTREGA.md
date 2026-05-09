---
table: PESSOAS_ENTREGA
module: cadastros
created_at_version: 358
last_modified_version: 358
target_version: 1468
columns_count: 11
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PESSOAS_ENTREGA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 358;
- **Última mudança:** UPDATE 358;
- **Total colunas (versão 1468):** 11

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v358 | v358 |
| 2 | `CODPESSOA` | `VARCHAR(10)` | NOT NULL | v358 | v358 |
| 3 | `DESCRICAO` | `VARCHAR(100)` | NULL | v358 | v358 |
| 4 | `CODCIDADE` | `INTEGER` | NULL | v358 | v358 |
| 5 | `BAIRRO` | `VARCHAR(60)` | NULL | v358 | v358 |
| 6 | `ENDERECO` | `VARCHAR(60)` | NULL | v358 | v358 |
| 7 | `NUMERO` | `VARCHAR(60)` | NULL | v358 | v358 |
| 8 | `COMPLEMENTO` | `VARCHAR(60)` | NULL | v358 | v358 |
| 9 | `UF` | `VARCHAR(2)` | NULL | v358 | v358 |
| 10 | `CIDADE` | `VARCHAR(60)` | NULL | v358 | v358 |
| 11 | `CEP` | `VARCHAR(10)` | NULL | v358 | v358 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 358 | CREATE | CREATE TABLE com 11 colunas |

