---
table: SLA
module: agenda
created_at_version: 1237
last_modified_version: 1248
target_version: 1468
columns_count: 20
foreign_keys_count: 2
foreign_keys:
  CODPESSOA_CRIACAO_SLA: PESSOAS
  CODPESSOA_RESPONSAVEL: PESSOAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `SLA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `agenda` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1237;
- **Última mudança:** UPDATE 1248;
- **Total colunas (versão 1468):** 20

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPESSOA_CRIACAO_SLA` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |
| `CODPESSOA_RESPONSAVEL` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1237 | v1237 |
| 2 | `DESCRICAO` | `VARCHAR(500)` | NULL |  | v1237 | v1237 |
| 3 | `MODULO` | `VARCHAR(100)` | NULL |  | v1237 | v1237 |
| 4 | `TABELA` | `VARCHAR(100)` | NULL |  | v1237 | v1237 |
| 5 | `NOME` | `VARCHAR(100)` | NULL |  | v1237 | v1237 |
| 6 | `FILTRO` | `VARCHAR(50)` | NULL |  | v1248 | v1248 |
| 7 | `VALOR_FILTRO` | `VARCHAR(100)` | NULL |  | v1248 | v1248 |
| 8 | `INSERIR` | `VARCHAR(1)` | NULL |  | v1237 | v1237 |
| 9 | `FINALIZAR` | `VARCHAR(1)` | NULL |  | v1237 | v1237 |
| 10 | `EXCLUIR` | `VARCHAR(1)` | NULL |  | v1237 | v1237 |
| 11 | `MODIFICAR` | `VARCHAR(1)` | NULL |  | v1237 | v1237 |
| 12 | `REATIVAR` | `VARCHAR(1)` | NULL |  | v1237 | v1237 |
| 13 | `VISIVEL` | `VARCHAR(1)` | NULL |  | v1237 | v1237 |
| 14 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1237 | v1237 |
| 15 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1237 | v1237 |
| 16 | `GRAVIDADE` | `VARCHAR(30)` | NULL |  | v1244 | v1244 |
| 17 | `CODPESSOA_RESPONSAVEL` | `VARCHAR(15)` | NULL | → `PESSOAS` | v1244 | v1244 |
| 18 | `CODPESSOA_CRIACAO_SLA` | `VARCHAR(15)` | NULL | → `PESSOAS` | v1244 | v1244 |
| 19 | `DT_CRIACAO` | `TIMESTAMP` | NULL |  | v1244 | v1244 |
| 20 | `OBSERVACAO` | `VARCHAR(5000)` | NULL |  | v1244 | v1244 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1237 | CREATE | CREATE TABLE com 15 colunas |
| 1244 | ADD_COL | + GRAVIDADE VARCHAR(30) |
| 1244 | ADD_COL | + CODPESSOA_RESPONSAVEL VARCHAR(15) |
| 1244 | ADD_COL | + CODPESSOA_CRIACAO_SLA VARCHAR(15) |
| 1244 | ADD_COL | + DT_CRIACAO TIMESTAMP |
| 1244 | ADD_COL | + OBSERVACAO VARCHAR(5000) |
| 1248 | ADD_COL | + FILTRO VARCHAR(50) |
| 1248 | ADD_COL | + VALOR_FILTRO VARCHAR(100) |

