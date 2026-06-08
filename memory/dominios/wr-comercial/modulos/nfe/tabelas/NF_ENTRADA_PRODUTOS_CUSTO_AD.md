---
table: NF_ENTRADA_PRODUTOS_CUSTO_AD
module: nfe
created_at_version: 596
last_modified_version: 854
target_version: 1468
columns_count: 8
foreign_keys_count: 2
foreign_keys:
  CODNF_ENTRADA: NF_ENTRADA
  CODNF_ENTRADA_PRODUTO: NF_ENTRADA_PRODUTOS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_ENTRADA_PRODUTOS_CUSTO_AD`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 596;
- **Última mudança:** UPDATE 854;
- **Total colunas (versão 1468):** 8

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODNF_ENTRADA` | [`NF_ENTRADA`](../../nfe/tabelas/NF_ENTRADA.md) |
| `CODNF_ENTRADA_PRODUTO` | [`NF_ENTRADA_PRODUTOS`](../../nfe/tabelas/NF_ENTRADA_PRODUTOS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v596 | v596 |
| 2 | `CODNF_ENTRADA_PRODUTO` | `INTEGER` | NULL | → `NF_ENTRADA_PRODUTOS` | v596 | v596 |
| 3 | `CODNF_ENTRADA` | `VARCHAR(10)` | NULL | → `NF_ENTRADA` | v596 | v596 |
| 4 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v596 | v596 |
| 5 | `DESCRICAO` | `VARCHAR(150)` | NULL |  | v596 | v596 |
| 6 | `OBSERVACAO` | `VARCHAR(500)` | NULL |  | v709 | v709 |
| 7 | `LANCADO_MANUALMENTE` | `DOM_BOOLEAN` | NULL |  | v709 | v709 |
| 8 | `PERCVALOR` | `varchar(10)` | NULL |  | v736 | v736 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 596 | CREATE | CREATE TABLE com 7 colunas |
| 619 | ADD_COL | + APLICAR_ANTES_MARGEM DOM_BOOLEAN |
| 619 | ADD_COL | + COBRAR_DO_CLIENTE DOM_BOOLEAN |
| 658 | DROP_COL | - APLICAR_ANTES_MARGEM |
| 658 | ADD_COL | + APLICAR_NA VARCHAR(20) |
| 696 | ADD_COL | + CODPESSOA VARCHAR(10) |
| 709 | ADD_COL | + OBSERVACAO VARCHAR(500) |
| 709 | ADD_COL | + LANCADO_MANUALMENTE DOM_BOOLEAN |
| 723 | ADD_COL | + VALOR_MEDIO DOUBLE PRECISION |
| 728 | ADD_COL | + VALOR_ANTERIOR DOUBLE PRECISION |
| 728 | DROP_COL | - VALOR_MEDIO |
| 732 | ADD_COL | + PERCVALOR VARCHAR(10) |
| 732 | RENAME_COL | × TIPO_CUSTO → PERCVALOR |
| 736 | ADD_COL | + PERCVALOR varchar(10) |
| 854 | DROP_COL | - VALOR_ANTEIROR |
| 854 | DROP_COL | - VALOR_ANTERIOR |
| 854 | DROP_COL | - CODPESSOA |
| 854 | DROP_COL | - COBRAR_DO_CLIENTE |
| 854 | DROP_COL | - APLICAR_NA |
| 854 | DROP_COL | - CODCUSTO_ADICIONAL |
| 854 | DROP_COL | - CUSTO_ANTERIOR |
| 854 | DROP_COL | - TIPO_CUSTO |

