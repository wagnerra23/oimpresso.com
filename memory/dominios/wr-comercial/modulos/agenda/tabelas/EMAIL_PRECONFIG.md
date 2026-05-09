---
table: EMAIL_PRECONFIG
module: agenda
created_at_version: 168
last_modified_version: 168
target_version: 1468
columns_count: 11
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EMAIL_PRECONFIG`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 168;
- **Última mudança:** UPDATE 168;
- **Total colunas (versão 1468):** 11

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `integer` | NOT NULL |  | v168 | v168 |
| 2 | `DESCRICAO` | `varchar(50)` | NULL |  | v168 | v168 |
| 3 | `EMAIL` | `varchar (100)` | NULL |  | v168 | v168 |
| 4 | `SMTP_ENDERECO` | `varchar (100)` | NULL |  | v168 | v168 |
| 5 | `POP3_ENDERECO` | `varchar (100)` | NULL |  | v168 | v168 |
| 6 | `SMTP_PORTA` | `integer` | NULL |  | v168 | v168 |
| 7 | `POP3_PORTA` | `integer` | NULL |  | v168 | v168 |
| 8 | `REQUER_AUTENTICACAO` | `smallint` | NULL |  | v168 | v168 |
| 9 | `AUTO_TLS` | `smallint` | NULL |  | v168 | v168 |
| 10 | `TIPO_SSL` | `smallint` | NULL |  | v168 | v168 |
| 11 | `USUARIO` | `varchar (100)` | NULL |  | v168 | v168 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 168 | CREATE | CREATE TABLE com 11 colunas |

