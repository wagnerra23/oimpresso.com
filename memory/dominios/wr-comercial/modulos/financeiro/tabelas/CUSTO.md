---
table: CUSTO
module: financeiro
created_at_version: 1302
last_modified_version: 1307
target_version: 1468
columns_count: 13
foreign_keys_count: 3
foreign_keys:
  CODCOMPETENCIA: COMPETENCIA
  CODEMPRESA: EMPRESA
  CODUSUARIO_FECHAMENTO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `CUSTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `financeiro` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1302;
- **Última mudança:** UPDATE 1307;
- **Total colunas (versão 1468):** 13

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCOMPETENCIA` | [`COMPETENCIA`](../../configuracao/tabelas/COMPETENCIA.md) |
| `CODEMPRESA` | [`EMPRESA`](../../cadastros/tabelas/EMPRESA.md) |
| `CODUSUARIO_FECHAMENTO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1302 | v1302 |
| 2 | `CODEMPRESA` | `INTEGER` | NOT NULL | → `EMPRESA` | v1302 | v1302 |
| 3 | `CODCOMPETENCIA` | `INTEGER` | NOT NULL | → `COMPETENCIA` | v1302 | v1302 |
| 4 | `DESCRICAO` | `VARCHAR(300)` | NULL |  | v1302 | v1302 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1302 | v1302 |
| 6 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1302 | v1302 |
| 7 | `DT_FECHAMENTO` | `TIMESTAMP` | NULL |  | v1306 | v1306 |
| 8 | `ATUALIZA_EQUIPE` | `VARCHAR(1)` | NULL |  | v1306 | v1306 |
| 9 | `ATUALIZA_FUNCIONARIO` | `VARCHAR(1)` | NULL |  | v1306 | v1306 |
| 10 | `TIPO_RATEIO` | `VARCHAR(20)` | NULL |  | v1306 | v1306 |
| 11 | `CODUSUARIO_FECHAMENTO` | `INTEGER` | NULL | → `USUARIO` | v1307 | v1307 |
| 12 | `USUARIO_FECHAMENTO` | `VARCHAR(30)` | NULL |  | v1307 | v1307 |
| 13 | `FECHOU_SEM_ATUALIZAR` | `VARCHAR(1)` | NULL |  | v1307 | v1307 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1302 | CREATE | CREATE TABLE com 6 colunas |
| 1306 | ADD_COL | + DT_FECHAMENTO TIMESTAMP |
| 1306 | ADD_COL | + ATUALIZA_EQUIPE VARCHAR(1) |
| 1306 | ADD_COL | + ATUALIZA_FUNCIONARIO VARCHAR(1) |
| 1306 | ADD_COL | + TIPO_RATEIO VARCHAR(20) |
| 1307 | ADD_COL | + CODUSUARIO_FECHAMENTO INTEGER |
| 1307 | ADD_COL | + USUARIO_FECHAMENTO VARCHAR(30) |
| 1307 | ADD_COL | + FECHOU_SEM_ATUALIZAR VARCHAR(1) |

