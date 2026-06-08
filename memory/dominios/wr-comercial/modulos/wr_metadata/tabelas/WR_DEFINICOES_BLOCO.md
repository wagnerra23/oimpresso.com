---
table: WR_DEFINICOES_BLOCO
module: wr_metadata
created_at_version: 1439
last_modified_version: 1439
target_version: 1468
columns_count: 9
foreign_keys_count: 2
foreign_keys:
  CODWR_BLOCO: WR_BLOCO
  CODWR_DEFINICOES: WR_DEFINICOES
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `WR_DEFINICOES_BLOCO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1439;
- **Última mudança:** UPDATE 1439;
- **Total colunas (versão 1468):** 9

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODWR_BLOCO` | [`WR_BLOCO`](../../wr_metadata/tabelas/WR_BLOCO.md) |
| `CODWR_DEFINICOES` | [`WR_DEFINICOES`](../../wr_metadata/tabelas/WR_DEFINICOES.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `codigo` | `INTEGER` | NOT NULL |  | v1439 | v1439 |
| 2 | `codwr_definicoes` | `INTEGER` | NOT NULL | → `WR_DEFINICOES` | v1439 | v1439 |
| 3 | `codwr_bloco` | `INTEGER` | NOT NULL | → `WR_BLOCO` | v1439 | v1439 |
| 4 | `ordem` | `INTEGER DEFAULT 0` | NULL |  | v1439 | v1439 |
| 5 | `origem` | `VARCHAR(15) DEFAULT 'SLUG'` | NOT NULL |  | v1439 | v1439 |
| 6 | `customizado` | `VARCHAR(1) DEFAULT 'N'` | NOT NULL |  | v1439 | v1439 |
| 7 | `versao_adicionado` | `INTEGER DEFAULT 1` | NOT NULL |  | v1439 | v1439 |
| 8 | `ativo` | `VARCHAR(1) DEFAULT 'S'` | NOT NULL |  | v1439 | v1439 |
| 9 | `dt_alteracao` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | NULL |  | v1439 | v1439 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1439 | CREATE | CREATE TABLE com 9 colunas |

