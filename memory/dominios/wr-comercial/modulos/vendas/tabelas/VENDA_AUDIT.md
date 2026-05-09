---
table: VENDA_AUDIT
module: vendas
created_at_version: 559
last_modified_version: 559
target_version: 1468
columns_count: 9
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `VENDA_AUDIT`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `vendas` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 559;
- **Última mudança:** UPDATE 559;
- **Total colunas (versão 1468):** 9

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v559 | v559 |
| 2 | `CODHISTORICO` | `INTEGER` | NULL | v559 | v559 |
| 3 | `CODUSUARIO` | `INTEGER` | NULL | v559 | v559 |
| 4 | `PESSOA_FUNCIONARIO_CODIGO` | `VARCHAR(10)` | NULL | v559 | v559 |
| 5 | `CODPEDIDO` | `VARCHAR(10)` | NULL | v559 | v559 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NOT NULL | v559 | v559 |
| 7 | `VENDA_TIPO_ANTERIOR` | `VARCHAR(60)` | NULL | v559 | v559 |
| 8 | `VENDA_TIPO_ATUAL` | `VARCHAR(60)` | NULL | v559 | v559 |
| 9 | `SITUACAO` | `VARCHAR(10)` | NULL | v559 | v559 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 559 | CREATE | CREATE TABLE com 9 colunas |

