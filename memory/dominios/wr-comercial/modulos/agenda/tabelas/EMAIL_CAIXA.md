---
table: EMAIL_CAIXA
module: agenda
created_at_version: 369
last_modified_version: 369
target_version: 1468
columns_count: 12
foreign_keys_count: 2
foreign_keys:
  CODEMAIL_CONTA: EMAIL_CONTA
  CODEMAIL_CONTA_CRM_DATABASE: EMAIL_CONTA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EMAIL_CAIXA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 369;
- **Última mudança:** UPDATE 369;
- **Total colunas (versão 1468):** 12

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMAIL_CONTA` | [`EMAIL_CONTA`](../../agenda/tabelas/EMAIL_CONTA.md) |
| `CODEMAIL_CONTA_CRM_DATABASE` | [`EMAIL_CONTA`](../../agenda/tabelas/EMAIL_CONTA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v369 | v369 |
| 2 | `CODCRM_DATABASE` | `INTEGER` | NOT NULL |  | v369 | v369 |
| 3 | `CODEMAIL_CONTA` | `INTEGER` | NULL | → `EMAIL_CONTA` | v369 | v369 |
| 4 | `CODEMAIL_CONTA_CRM_DATABASE` | `INTEGER` | NULL | → `EMAIL_CONTA` | v369 | v369 |
| 5 | `DESCRICAO` | `VARCHAR(50)` | NOT NULL |  | v369 | v369 |
| 6 | `PARENT` | `INTEGER` | NULL |  | v369 | v369 |
| 7 | `INDICE_IMAGEM` | `INTEGER` | NULL |  | v369 | v369 |
| 8 | `QUANT_NAO_LIDO` | `INTEGER` | NULL |  | v369 | v369 |
| 9 | `DESCRICAO_ORIGINAL` | `VARCHAR(50)` | NULL |  | v369 | v369 |
| 10 | `EMAIL` | `VARCHAR(50)` | NULL |  | v369 | v369 |
| 11 | `ATIVO` | `VARCHAR(1)` | NULL |  | v369 | v369 |
| 12 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v369 | v369 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 369 | CREATE | CREATE TABLE com 12 colunas |

