---
table: KPI_DIA
module: bi
created_at_version: 1303
last_modified_version: 1336
target_version: 1468
columns_count: 31
foreign_keys_count: 1
foreign_keys:
  CODUSUARIO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `KPI_DIA`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `bi` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1303;
- **Última mudança:** UPDATE 1336;
- **Total colunas (versão 1468):** 31

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODUSUARIO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `TABELA` | `VARCHAR(50)` | NULL |  | v1303 | v1303 |
| 2 | `CHAVE_PK1` | `INTEGER` | NULL |  | v1303 | v1303 |
| 3 | `CHAVE_PK2` | `VARCHAR(40)` | NULL |  | v1303 | v1303 |
| 4 | `CHAVE_PK3` | `VARCHAR(15)` | NULL |  | v1303 | v1303 |
| 5 | `DESCRICAO` | `VARCHAR(500)` | NULL |  | v1303 | v1303 |
| 6 | `QUANT_REGISTROS` | `INTEGER` | NULL |  | v1303 | v1303 |
| 7 | `GRAFICO_PERIODO` | `VARCHAR(10)` | NULL |  | v1303 | v1303 |
| 8 | `GRAFICO_TIPO` | `VARCHAR(20)` | NULL |  | v1303 | v1303 |
| 9 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1303 | v1303 |
| 10 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1303 | v1303 |
| 11 | `OBSERVACAO` | `VARCHAR(500)` | NULL |  | v1303 | v1303 |
| 12 | `TEM_PRINCIPAL` | `VARCHAR(1)` | NULL |  | v1303 | v1303 |
| 13 | `COR` | `INTEGER` | NULL |  | v1303 | v1303 |
| 14 | `TEXT1` | `VARCHAR(255)` | NULL |  | v1303 | v1303 |
| 15 | `TEXT2` | `VARCHAR(255)` | NULL |  | v1303 | v1303 |
| 16 | `TEXT3` | `VARCHAR(255)` | NULL |  | v1303 | v1303 |
| 17 | `TEXT4` | `VARCHAR(255)` | NULL |  | v1303 | v1303 |
| 18 | `TAG_ESTILO` | `INTEGER` | NULL |  | v1303 | v1303 |
| 19 | `GROUPINDEX` | `INTEGER` | NULL |  | v1303 | v1303 |
| 20 | `INDEXINGROUP` | `INTEGER` | NULL |  | v1303 | v1303 |
| 21 | `TAG_KPI` | `INTEGER` | NULL |  | v1303 | v1303 |
| 22 | `NIVEL` | `INTEGER` | NULL |  | v1303 | v1303 |
| 23 | `PARENT` | `VARCHAR(40)` | NULL |  | v1303 | v1303 |
| 24 | `TIPO_KPI` | `VARCHAR(50)` | NULL |  | v1303 | v1303 |
| 25 | `TAG_APP` | `INTEGER` | NULL |  | v1303 | v1303 |
| 26 | `CODUSUARIO` | `INTEGER` | NULL | → `USUARIO` | v1303 | v1303 |
| 27 | `COMPETENCIA` | `DATE` | NULL |  | v1303 | v1303 |
| 28 | `KPI_TIPO` | `VARCHAR(100)` | NULL |  | v1336 | v1336 |
| 29 | `DT_COMPETENCIA` | `DATE` | NULL |  | v1336 | v1336 |
| 30 | `VALUE1` | `DOUBLE PRECISION` | NULL |  | v1336 | v1336 |
| 31 | `META` | `DOUBLE PRECISION` | NULL |  | v1336 | v1336 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1303 | CREATE | CREATE TABLE com 27 colunas |
| 1336 | ADD_COL | + KPI_TIPO VARCHAR(100) |
| 1336 | ADD_COL | + DT_COMPETENCIA DATE |
| 1336 | ADD_COL | + VALUE1 DOUBLE PRECISION |
| 1336 | ADD_COL | + META DOUBLE PRECISION |

