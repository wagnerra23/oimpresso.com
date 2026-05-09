---
table: EMAIL_CONTA
module: agenda
created_at_version: 345
last_modified_version: 429
target_version: 1468
columns_count: 23
foreign_keys_count: 1
foreign_keys:
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EMAIL_CONTA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 345;
- **Última mudança:** UPDATE 429;
- **Total colunas (versão 1468):** 23

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v345 | v345 |
| 2 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v345 | v345 |
| 3 | `DESCRICAO` | `VARCHAR(50)` | NULL |  | v345 | v345 |
| 4 | `EMAIL` | `VARCHAR(100)` | NULL |  | v345 | v345 |
| 5 | `SMTP_ENDERECO` | `VARCHAR(100)` | NULL |  | v345 | v345 |
| 6 | `SMTP_PORTA` | `INTEGER` | NULL |  | v345 | v345 |
| 7 | `TIPO_SSL` | `VARCHAR(10)` | NULL |  | v345 | v345 |
| 8 | `USUARIO` | `VARCHAR(100)` | NULL |  | v345 | v345 |
| 9 | `SENHA` | `VARCHAR(50)` | NULL |  | v345 | v345 |
| 10 | `TIPO_PROTOCOLO` | `VARCHAR(10)` | NULL |  | v345 | v345 |
| 11 | `ATIVO` | `VARCHAR(1)` | NULL |  | v345 | v345 |
| 12 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v345 | v345 |
| 13 | `MENSAGEM_PADRAO` | `VARCHAR(1000)` | NULL |  | v345 | v345 |
| 14 | `NOME_EXIBICAO` | `VARCHAR(150)` | NULL |  | v345 | v345 |
| 15 | `CCO_EMPRESA` | `VARCHAR(1)` | NULL |  | v345 | v345 |
| 16 | `AUTO_TLS` | `VARCHAR(1)` | NULL |  | v345 | v345 |
| 17 | `REQUER_AUTENTICACAO` | `VARCHAR(1)` | NULL |  | v345 | v345 |
| 18 | `CODCRM_DATABASE` | `INTEGER` | NULL |  | v368 | v368 |
| 19 | `IMAP_ENDERECO` | `VARCHAR(100)` | NULL |  | v368 | v368 |
| 20 | `IMAP_PORTA` | `INTEGER` | NULL |  | v368 | v368 |
| 21 | `REMOVER_EMAIL_SERVIDOR_RECEBIDO` | `VARCHAR(1)` | NULL |  | v368 | v368 |
| 22 | `ENDERECO` | `VARCHAR(100)` | NULL |  | v345 | v429 |
| 23 | `PORTA` | `INTEGER` | NULL |  | v345 | v429 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 345 | CREATE | CREATE TABLE com 19 colunas |
| 368 | ADD_COL | + CODCRM_DATABASE INTEGER |
| 368 | ADD_COL | + IMAP_ENDERECO VARCHAR(100) |
| 368 | ADD_COL | + IMAP_PORTA INTEGER |
| 368 | ADD_COL | + REMOVER_EMAIL_SERVIDOR_RECEBIDO VARCHAR(1) |
| 429 | RENAME_COL | × POP3_ENDERECO → ENDERECO |
| 429 | RENAME_COL | × POP3_PORTA → PORTA |

