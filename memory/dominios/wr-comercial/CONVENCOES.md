# Convenções de schema — Delphi WR Comercial

Regras implícitas que o time/dev original (Wagner) adotou no schema Firebird.
Não estão documentadas formalmente em DDL (FKs nem sempre têm CONSTRAINT
explícito) mas são **uniformes em todo o banco**. Importer + parser dependem
delas.

## ⭐ Convenção 1 — `COD<TABELA>` é chave estrangeira

| Padrão | Significado | Exemplo |
|---|---|---|
| `CODIGO` | **Primary Key** da tabela atual | `CONTAS.CODIGO` (PK) |
| `COD<TABELA>` | **Foreign Key** pra `<TABELA>(CODIGO)` | `FINANCEIRO_BOLETO_HISTORICO.CODCONTA` → `CONTAS(CODIGO)` |
| `COD<TABELA>_<sufixo>` | FK pra `<TABELA>` com qualificador semântico (não-FK alternativa) | `BANCOS.CODBANCO_COOPERATIVA` → `BANCOS(CODIGO)` (self-FK), `CONTAS.CODCONTA_VINCULADA` → `CONTAS(CODIGO)`, `CONTAS.CODCONTA_TRANSFERENCIA_AUTO` → `CONTAS(CODIGO)` |
| `CODIGO_<x>` | **NÃO é FK** — string identificadora externa | `CONTAS.CODIGO_CEDENTE` (string CNAB), `CONTAS.CODIGO_TRANSMISSAO` (config CNAB), `CODIGO_BKP` (backup), `CODIGO_MIGRADO` (tracking) |

### Algoritmo de inferência

Pra cada coluna `C` numa tabela:

1. Se `C == 'CODIGO'` → PK da tabela atual
2. Se `C` começa com `CODIGO_` → NÃO é FK (string ou auxiliar)
3. Se `C` começa com `COD` (e não `CODIGO`) → tentar resolver FK:
   - **Match exato**: `C[3:]` é nome de tabela existente?
     - Ex: `CODBANCO[3:] == 'BANCO'` → procura tabela `BANCO` ou `BANCOS` (singular/plural)
   - **Stripping de sufixo**: tentar truncar trailing `_XXX_YYY` até achar match
     - Ex: `CODBANCO_COOPERATIVA` → tenta `BANCO_COOPERATIVA`, falha → tenta `BANCO`/`BANCOS`, acha `BANCOS` (self-FK qualificado)
   - **Singular/plural**: tentar adicionar/remover `S` no final pra resolver
     - Ex: `CODBANCO` → tenta `BANCO` (não existe) → tenta `BANCOS` (existe) ✅

Implementado em [`scripts/legacy-migration/lib/fk_resolver.py`](../../../scripts/legacy-migration/lib/fk_resolver.py).

### Casos resolvidos validados

| Coluna | Tabela atual | FK inferida | Notas |
|---|---|---|---|
| `CODBANCO` | qualquer | `BANCOS(CODIGO)` | Plural FEBRABAN — banco_codigo é PK |
| `CODCONTA` | qualquer | `CONTAS(CODIGO)` | |
| `CODEMPRESA` | qualquer | `EMPRESA(CODIGO)` | (singular!) |
| `CODFINANCEIRO_BOLETO` | qualquer | `FINANCEIRO_BOLETO(CODIGO)` | match composto |
| `CODFORNECEDOR` | qualquer | `PESSOAS(CODIGO)` ⚠️ | Excepcional — `FORNECEDOR` é vista virtual sobre `PESSOAS WHERE TIPO='F'`. Importer resolve via `PESSOAS`. |
| `CODBANCO_COOPERATIVA` | `BANCOS` | `BANCOS(CODIGO)` (self) | Qualificador `_COOPERATIVA` |
| `CODBANCO_CONFIGURACAO` | `CONTAS` | `BANCOS(CODIGO)` | Qualificador `_CONFIGURACAO` |

### Exceções conhecidas

1. **`FORNECEDOR` não é tabela** — é vista/conceito sobre `PESSOAS`. `CODFORNECEDOR` aponta pra `PESSOAS(CODIGO)`. Importer hardcoded sabe disso.
2. **`CODEMAIL_MODELO`** → tabela `EMAIL_MODELO` (não `EMAILS_MODELO`).
3. **Coleção polimórfica** — algumas FKs apontam pra tabela diferente conforme `<TIPO>` campo (ex: `PESSOA_RESPONSAVEL_TIPO`+`PESSOA_RESPONSAVEL_CODIGO` em `BANCOS_CONCILIACAO_BANCARIA`). Não cobre via convenção — Wagner mapeia caso-a-caso.

## Convenção 2 — `CODEMPRESA` é tenant key

Equivalente Delphi do `business_id` Laravel ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).

- Cada cliente Delphi tem 1+ `EMPRESA(CODIGO)` no banco
- Importer mapeia `CODEMPRESA Delphi → business_id Laravel` via configuração per-cliente em `clientes-legacy/<alias>.md`
- Cliente "ROTA LIVRE" (alias seria `rota-livre` no registry — embora ela não esteja no registry do Wagner; está nativa no oimpresso) tem `business_id=4`
- Wagner ServidorWR2 — `CODEMPRESA Delphi=1 → business_id Laravel=1`

## Convenção 3 — Charset `WIN1252` padrão

Todas as colunas string usam `WIN1252` por default (Delphi BR legacy). Algumas explicitam `CHARACTER SET WIN1252`, outras herdam.

Importer Python conecta com `charset='WIN1252'`.

## Convenção 4 — Sufixos de versionamento de coluna

Padrões observados que indicam migração interna:

- `_BKP` — backup criado durante migração (ex: `CODFINANCEIRO_BOLETO_BKP`)
- `_MIGRADO` — flag de "registro foi migrado pra estrutura nova" (ex: `CODIGO_MIGRADO`, `TEM_MIGRADO`)
- `_FRAME` — placeholder/UI (ex: `TConsuProduto_Composicao_Frame`)
- `_HISTORICO` — log de eventos da entidade (ex: `FINANCEIRO_BOLETO_HISTORICO`)
- `_CACHE` — cache local materialized (raro)

## Convenção 5 — `ATIVO` flag

Várias tabelas têm coluna `ATIVO VARCHAR(1)` (S/N) ao invés de boolean. Importer converte:

```python
is_closed = delphi.get('ATIVO', 'S') != 'S'   # cliente Laravel boolean invertido
```

## Convenção 6 — `DT_ALTERACAO` audit

Coluna `DT_ALTERACAO TIMESTAMP` adicionada em massa em UPDATE 728 + várias depois — equivale a `updated_at` Laravel.

Pra audit, importer mapeia `DT_ALTERACAO Delphi → updated_at Laravel`.

## Convenção 7 — `CODUSUARIO` ≠ Laravel users

- `CODUSUARIO Delphi` aponta pra `USUARIO(CODIGO)` Delphi (tabela própria)
- Não confundir com `users.id` Laravel
- Importer **não migra usuários Delphi** (decisão pendente Wagner) — usa Laravel users já cadastrados

## Próximas convenções a documentar (preencher conforme aprendemos)

- [ ] Padrão de blob — quando o Delphi usa `BLOB SUB_TYPE 1` (texto) vs `SUB_TYPE 0` (binário)
- [ ] Convenção `XXX_TIPO` — campos enumerados (varchar com valores curtos: 'S'/'N', 'C'/'D', etc)
- [ ] Convenção `IS_<nome>` — booleans (`IS_VENDA`, `IS_ORCAMENTO`, `IS_NOTAFISCAL`)
- [ ] Padrão de chave composta — quando entidade tem PK (CODIGO + CODEMPRESA + ...)
