---
table: FOLHA_PAGAMENTO_SALARIO
module: rh
created_at_version: 1302
last_modified_version: 1302
target_version: 1468
columns_count: 22
foreign_keys_count: 2
foreign_keys:
  CODFOLHA_PAGAMENTO: FOLHA_PAGAMENTO
  CODUSUARIO_ALTERACAO: USUARIO
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FOLHA_PAGAMENTO_SALARIO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `rh` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1302;
- **Última mudança:** UPDATE 1302;
- **Total colunas (versão 1468):** 22

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODFOLHA_PAGAMENTO` | [`FOLHA_PAGAMENTO`](../../rh/tabelas/FOLHA_PAGAMENTO.md) |
| `CODUSUARIO_ALTERACAO` | [`USUARIO`](../../cadastros/tabelas/USUARIO.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v1302 | v1302 |
| 2 | `CODFOLHA_PAGAMENTO` | `INTEGER` | NOT NULL | → `FOLHA_PAGAMENTO` | v1302 | v1302 |
| 3 | `DESCRICAO` | `VARCHAR(300)` | NULL |  | v1302 | v1302 |
| 4 | `SALARIO` | `DOUBLE PRECISION` | NULL |  | v1302 | v1302 |
| 5 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v1302 | v1302 |
| 6 | `COMPETENCIA` | `TIMESTAMP` | NULL |  | v1302 | v1302 |
| 7 | `DECIMO_TERCEIRO_MENSAL` | `DOUBLE PRECISION` | NULL |  | v1302 | v1302 |
| 8 | `DECIMO_TERCEIRO_ANUAL` | `DOUBLE PRECISION` | NULL |  | v1302 | v1302 |
| 9 | `FERIAS_MENSAL` | `DOUBLE PRECISION` | NULL |  | v1302 | v1302 |
| 10 | `FERIAS_ANUAL` | `DOUBLE PRECISION` | NULL |  | v1302 | v1302 |
| 11 | `OUTROS_ENCARGOS_MENSAL` | `DOUBLE PRECISION` | NULL |  | v1302 | v1302 |
| 12 | `OUTROS_ENCARGOS_ANUAL` | `DOUBLE PRECISION` | NULL |  | v1302 | v1302 |
| 13 | `BENEFICIOS_MENSAL` | `DOUBLE PRECISION` | NULL |  | v1302 | v1302 |
| 14 | `BENEFICIOS_ANUAL` | `DOUBLE PRECISION` | NULL |  | v1302 | v1302 |
| 15 | `TOTAL_MENSAL` | `DOUBLE PRECISION` | NULL |  | v1302 | v1302 |
| 16 | `QTD_HORAS_TRABALHADAS_MENSAL` | `DOUBLE PRECISION` | NULL |  | v1302 | v1302 |
| 17 | `VALOR_HORA` | `DOUBLE PRECISION` | NULL |  | v1302 | v1302 |
| 18 | `ATIVO` | `VARCHAR(1)` | NULL |  | v1302 | v1302 |
| 19 | `PESSOA_FUNCIONARIO_CODIGO` | `VARCHAR(10)` | NULL |  | v1302 | v1302 |
| 20 | `PESSOA_FUNCIONARIO_TIPO` | `VARCHAR(3)` | NULL |  | v1302 | v1302 |
| 21 | `PESSOA_FUNCIONARIO_SEQUENCIA` | `INTEGER` | NULL |  | v1302 | v1302 |
| 22 | `CODUSUARIO_ALTERACAO` | `INTEGER` | NULL | → `USUARIO` | v1302 | v1302 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1302 | CREATE | CREATE TABLE com 22 colunas |

