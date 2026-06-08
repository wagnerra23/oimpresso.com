---
table: CONFIG_VALORES
module: configuracao
created_at_version: 1442
last_modified_version: 1442
target_version: 1468
columns_count: 12
foreign_keys_count: 4
foreign_keys:
  CODEMPRESA: EMPRESA
  CODUSUARIO: USUARIO
  CODUSUARIO_ALTERACAO: USUARIO
  CODUSUARIO_CRIACAO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CONFIG_VALORES`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `configuracao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1442;
- **Última mudança:** UPDATE 1442;
- **Total colunas (versão 1468):** 12

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |
| `CODUSUARIO_ALTERACAO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |
| `CODUSUARIO_CRIACAO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `BIGINT PRIMARY KEY` | NULL |  | v1442 | v1442 |
| 2 | `CHAVE` | `VARCHAR(200)` | NOT NULL |  | v1442 | v1442 |
| 3 | `VALOR` | `VARCHAR(4000)` | NULL |  | v1442 | v1442 |
| 4 | `TIPO` | `VARCHAR(20)` | NOT NULL |  | v1442 | v1442 |
| 5 | `ESCOPO` | `VARCHAR(20)` | NOT NULL |  | v1442 | v1442 |
| 6 | `CODEMPRESA` | `INTEGER DEFAULT 0` | NULL | → `EMPRESA` | v1442 | v1442 |
| 7 | `CODUSUARIO` | `INTEGER DEFAULT 0` | NULL | → `USUARIO` | v1442 | v1442 |
| 8 | `ATIVO` | `VARCHAR(1) DEFAULT 'S'` | NULL |  | v1442 | v1442 |
| 9 | `DT_CRIACAO` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | NULL |  | v1442 | v1442 |
| 10 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1442 | v1442 |
| 11 | `CODUSUARIO_CRIACAO` | `INTEGER` | NULL | → `USUARIO` | v1442 | v1442 |
| 12 | `CODUSUARIO_ALTERACAO` | `INTEGER` | NULL | → `USUARIO` | v1442 | v1442 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1442 | CREATE | CREATE TABLE com 12 colunas |

