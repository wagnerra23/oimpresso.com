---
table: HISTORICO_SLA
module: wr_metadata
created_at_version: 1240
last_modified_version: 1244
target_version: 1468
columns_count: 19
foreign_keys_count: 4
foreign_keys:
  CODEMPRESA: EMPRESA
  CODPESSOA_CRIACAO_SLA: PESSOAS
  CODPESSOA_RESPONSAVEL: PESSOAS
  CODSLA: SLA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `HISTORICO_SLA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `wr_metadata` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1240;
- **Última mudança:** UPDATE 1244;
- **Total colunas (versão 1468):** 19

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |
| `CODPESSOA_CRIACAO_SLA` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |
| `CODPESSOA_RESPONSAVEL` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |
| `CODSLA` | [`SLA`](../../agenda/tabelas/SLA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1240 | v1240 |
| 2 | `CODEMPRESA` | `VARCHAR(250)` | NULL | → `EMPRESA` | v1240 | v1240 |
| 3 | `CODSLA` | `INTEGER` | NULL | → `SLA` | v1240 | v1240 |
| 4 | `MODULO` | `VARCHAR(100)` | NULL |  | v1240 | v1240 |
| 5 | `TABELA` | `VARCHAR(100)` | NULL |  | v1240 | v1240 |
| 6 | `CHAVE_PK1` | `INTEGER` | NULL |  | v1240 | v1240 |
| 7 | `CHAVE_PK2` | `VARCHAR(40)` | NULL |  | v1240 | v1240 |
| 8 | `CHAVE_PK3` | `VARCHAR(15)` | NULL |  | v1240 | v1240 |
| 9 | `MENSAGEM` | `VARCHAR(5000)` | NULL |  | v1240 | v1240 |
| 10 | `CONDICAO` | `VARCHAR(20)` | NULL |  | v1240 | v1240 |
| 11 | `QUANT_SEGUIDORES` | `INTEGER` | NULL |  | v1240 | v1240 |
| 12 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1240 | v1240 |
| 13 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1240 | v1240 |
| 14 | `GRAVIDADE` | `VARCHAR(30)` | NULL |  | v1244 | v1244 |
| 15 | `CODPESSOA_RESPONSAVEL` | `VARCHAR(15)` | NULL | → `PESSOAS` | v1244 | v1244 |
| 16 | `CODPESSOA_CRIACAO_SLA` | `VARCHAR(15)` | NULL | → `PESSOAS` | v1244 | v1244 |
| 17 | `DT_CRIACAO_SLA` | `TIMESTAMP` | NULL |  | v1244 | v1244 |
| 18 | `DT_NOTIFICADO` | `TIMESTAMP` | NULL |  | v1244 | v1244 |
| 19 | `CODTABELA` | `VARCHAR(40)` | NULL |  | v1244 | v1244 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1240 | CREATE | CREATE TABLE com 13 colunas |
| 1244 | ADD_COL | + GRAVIDADE VARCHAR(30) |
| 1244 | ADD_COL | + CODPESSOA_RESPONSAVEL VARCHAR(15) |
| 1244 | ADD_COL | + CODPESSOA_CRIACAO_SLA VARCHAR(15) |
| 1244 | ADD_COL | + DT_CRIACAO_SLA TIMESTAMP |
| 1244 | ADD_COL | + DT_NOTIFICADO TIMESTAMP |
| 1244 | ADD_COL | + CODTABELA VARCHAR(40) |

