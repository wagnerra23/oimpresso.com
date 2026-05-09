---
table: PRODUCAO_TEMPO
module: producao
created_at_version: 1050
last_modified_version: 1050
target_version: 1468
columns_count: 14
foreign_keys_count: 5
foreign_keys:
  CODCENTRO_TRABALHO: CENTRO_TRABALHO
  CODPRODUCAO: PRODUCAO
  CODPRODUCAO_OS: PRODUCAO_OS
  CODSETOR: SETOR
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUCAO_TEMPO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1050;
- **Última mudança:** UPDATE 1050;
- **Total colunas (versão 1468):** 14

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCENTRO_TRABALHO` | [`CENTRO_TRABALHO`](../../producao/tabelas/CENTRO_TRABALHO.md) |
| `CODPRODUCAO` | [`PRODUCAO`](../../producao/tabelas/PRODUCAO.md) |
| `CODPRODUCAO_OS` | [`PRODUCAO_OS`](../../producao/tabelas/PRODUCAO_OS.md) |
| `CODSETOR` | [`SETOR`](../../cadastros/tabelas/SETOR.md) |
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1050 | v1050 |
| 2 | `CODPRODUCAO` | `INTEGER` | NULL | → `PRODUCAO` | v1050 | v1050 |
| 3 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v1050 | v1050 |
| 4 | `DATA_INICIO` | `TIMESTAMP` | NULL |  | v56 | v56 |
| 5 | `DATA_FIM` | `TIMESTAMP` | NULL |  | v56 | v56 |
| 6 | `CODSETOR` | `INTEGER` | NULL | → `SETOR` | v136 | v136 |
| 7 | `CODCENTRO_TRABALHO` | `INTEGER` | NULL | → `CENTRO_TRABALHO` | v1050 | v1050 |
| 8 | `CODESTAGIO` | `INTEGER` | NULL |  | v1050 | v1050 |
| 9 | `CODPRODUCAO_OS` | `INTEGER` | NULL | → `PRODUCAO_OS` | v1050 | v1050 |
| 10 | `PESSOA_RESPONSAVEL_CODIGO` | `VARCHAR(10)` | NULL |  | v1050 | v1050 |
| 11 | `PESSOA_RESPONSAVEL_SEQUENCIA` | `INTEGER` | NULL |  | v1050 | v1050 |
| 12 | `PESSOA_RESPONSAVEL_TIPO` | `VARCHAR(3)` | NULL |  | v1050 | v1050 |
| 13 | `TEMPO_INICIO` | `TIMESTAMP` | NULL |  | v1050 | v1050 |
| 14 | `TEMPO_FIM` | `TIMESTAMP` | NULL |  | v1050 | v1050 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 56 | CREATE | CREATE TABLE com 5 colunas |
| 136 | ADD_COL | + CODSETOR INTEGER |
| 832 | DROP_TABLE | DROP TABLE |
| 1050 | CREATE | CREATE TABLE com 11 colunas |

