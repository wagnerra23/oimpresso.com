---
table: PESSOAS_REPRESENTANTE
module: cadastros
created_at_version: 266
last_modified_version: 294
target_version: 1468
columns_count: 6
foreign_keys_count: 2
foreign_keys:
  CODPESSOA: PESSOAS
  CODREPRESENTANTE: REPRESENTANTE
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PESSOAS_REPRESENTANTE`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 266;
- **Última mudança:** UPDATE 294;
- **Total colunas (versão 1468):** 6

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPESSOA` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |
| `CODREPRESENTANTE` | [`REPRESENTANTE`](../../cadastros/tabelas/REPRESENTANTE.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODPESSOA` | `VARCHAR(10)` | NOT NULL | → `PESSOAS` | v266 | v266 |
| 2 | `CODREPRESENTANTE` | `VARCHAR(10)` | NOT NULL | → `REPRESENTANTE` | v266 | v266 |
| 3 | `ASSINANTE` | `varchar (1)` | NULL |  | v276 | v276 |
| 4 | `PESSOA_REPRESENTANTE_CODIGO` | `varchar (10)` | NULL |  | v294 | v294 |
| 5 | `PESSOA_REPRESENTANTE_TIPO` | `varchar (3)` | NULL |  | v294 | v294 |
| 6 | `PESSOA_REPRESENTANTE_SEQUENCIA` | `integer` | NULL |  | v294 | v294 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 266 | CREATE | CREATE TABLE com 2 colunas |
| 276 | ADD_COL | + ASSINANTE varchar (1) |
| 294 | ADD_COL | + PESSOA_REPRESENTANTE_CODIGO varchar (10) |
| 294 | ADD_COL | + PESSOA_REPRESENTANTE_TIPO varchar (3) |
| 294 | ADD_COL | + PESSOA_REPRESENTANTE_SEQUENCIA integer |

