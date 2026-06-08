---
table: WR_DEFINICOES_REGRA
module: wr_metadata
created_at_version: 1439
last_modified_version: 1439
target_version: 1468
columns_count: 13
foreign_keys_count: 1
foreign_keys:
  CODWR_BLOCO: WR_BLOCO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `WR_DEFINICOES_REGRA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1439;
- **Última mudança:** UPDATE 1439;
- **Total colunas (versão 1468):** 13

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODWR_BLOCO` | [`WR_BLOCO`](../../wr_metadata/tabelas/WR_BLOCO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `codigo` | `INTEGER` | NOT NULL |  | v1439 | v1439 |
| 2 | `codwr_bloco` | `INTEGER` | NOT NULL | → `WR_BLOCO` | v1439 | v1439 |
| 3 | `slug` | `VARCHAR(255)` | NOT NULL |  | v1439 | v1439 |
| 4 | `descricao` | `VARCHAR(100)` | NULL |  | v1439 | v1439 |
| 5 | `campo` | `VARCHAR(100)` | NOT NULL |  | v1439 | v1439 |
| 6 | `tipo_validacao` | `VARCHAR(50)` | NOT NULL |  | v1439 | v1439 |
| 7 | `mensagem_erro` | `VARCHAR(500)` | NULL |  | v1439 | v1439 |
| 8 | `mensagem_dica` | `VARCHAR(500)` | NULL |  | v1439 | v1439 |
| 9 | `condicao_ativa` | `VARCHAR(500)` | NULL |  | v1439 | v1439 |
| 10 | `parametros` | `BLOB SUB_TYPE TEXT` | NULL |  | v1439 | v1439 |
| 11 | `ordem` | `INTEGER DEFAULT 0` | NULL |  | v1439 | v1439 |
| 12 | `ativo` | `VARCHAR(1) DEFAULT 'S'` | NOT NULL |  | v1439 | v1439 |
| 13 | `dt_alteracao` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | NULL |  | v1439 | v1439 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1439 | CREATE | CREATE TABLE com 13 colunas |

