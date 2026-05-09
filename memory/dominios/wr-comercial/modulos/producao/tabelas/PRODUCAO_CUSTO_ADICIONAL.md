---
table: PRODUCAO_CUSTO_ADICIONAL
module: producao
created_at_version: 579
last_modified_version: 854
target_version: 1468
columns_count: 10
foreign_keys_count: 2
foreign_keys:
  CODPESSOA: PESSOAS
  CODPRODUCAO: PRODUCAO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUCAO_CUSTO_ADICIONAL`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 579;
- **Última mudança:** UPDATE 854;
- **Total colunas (versão 1468):** 10

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPESSOA` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |
| `CODPRODUCAO` | [`PRODUCAO`](../../producao/tabelas/PRODUCAO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v579 | v579 |
| 2 | `CODPRODUCAO` | `INTEGER` | NOT NULL | → `PRODUCAO` | v579 | v579 |
| 3 | `TIPO_CUSTO` | `VARCHAR(10)` | NULL |  | v579 | v579 |
| 4 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v579 | v579 |
| 5 | `COBRAR_DO_CLIENTE` | `DOM_BOOLEAN` | NULL |  | v616 | v616 |
| 6 | `APLICAR_ANTES_MARGEM` | `DOM_BOOLEAN` | NULL |  | v617 | v617 |
| 7 | `OBSERVACAO` | `VARCHAR(500)` | NULL |  | v709 | v709 |
| 8 | `LANCADO_MANUALMENTE` | `DOM_BOOLEAN` | NULL |  | v709 | v709 |
| 9 | `APLICAR_NA` | `VARCHAR(20)` | NULL |  | v715 | v715 |
| 10 | `CODPESSOA` | `VARCHAR(10)` | NULL | → `PESSOAS` | v715 | v715 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 579 | CREATE | CREATE TABLE com 5 colunas |
| 616 | ADD_COL | + COBRAR_DO_CLIENTE DOM_BOOLEAN |
| 617 | ADD_COL | + APLICAR_ANTES_MARGEM DOM_BOOLEAN |
| 709 | ADD_COL | + OBSERVACAO VARCHAR(500) |
| 709 | ADD_COL | + LANCADO_MANUALMENTE DOM_BOOLEAN |
| 715 | ADD_COL | + APLICAR_NA VARCHAR(20) |
| 715 | ADD_COL | + CODPESSOA VARCHAR(10) |
| 854 | DROP_COL | - CODCUSTO_ADICIONAL |
| 854 | DROP_COL | - CLASSIFICACAO |

