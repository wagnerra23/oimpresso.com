---
id: dominios-patterns-05-schema-vivo-vs-reconstruido
---

# Pattern 05 — Schema vivo manda, não reconstruído

**Status**: canônico desde 2026-05-09 (lição aprendida, Fase 6)

## Contexto

Importer pode usar 2 fontes de info sobre schema do banco legacy:

1. **Schema reconstruído** — derivado de logs DDL históricos (Delphi `UpdateSQL.txt`, Liquibase `databaseChangeLog.xml`, Flyway migrations)
2. **Schema vivo** — query direto no `INFORMATION_SCHEMA` / `RDB$RELATIONS` / `pg_catalog`

## Problema observado

Fase 6 smoke do importer WR Comercial: query SQL específica `SELECT CODIGO, CODBANCO, AGENCIA, VARIACAO_CARTEIRA, ... FROM CONTAS` falhou com:

```
DatabaseError: Dynamic SQL Error
-SQL error code = -206
-Column unknown
-VARIACAO_CARTEIRA
```

Causa: `VARIACAO_CARTEIRA` foi assumida baseada em pattern (`CARTEIRA` + `_VARIACAO`) mas **não existe no schema vivo**. A coluna nunca foi criada — o pattern era falso.

Outro caso: Fase 6 — script Python tentou `INSERT INTO accounts (account_type, ...)` mas a coluna real é `account_type_id` (FK pra `account_types`). Erro: "Unknown column 'account_type'". Suposição baseada em código Laravel hipotético divergiu da realidade.

## Solução

**Sempre confirmar contra schema vivo** antes de assumir colunas:

### Origem (Firebird)
```sql
SELECT TRIM(rf.RDB$FIELD_NAME)
FROM RDB$RELATION_FIELDS rf
WHERE rf.RDB$RELATION_NAME = 'CONTAS'
ORDER BY rf.RDB$FIELD_POSITION
```

### Origem (MySQL/MariaDB legacy)
```sql
DESCRIBE <table>;
-- ou
SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = '<table>' ORDER BY ORDINAL_POSITION
```

### Destino (Laravel)
```php
\Schema::getColumnListing('accounts')
// ou
DB::select('DESCRIBE accounts')
```

### Estratégia robusta no importer

```python
# ❌ Frágil — assume colunas
sql = "SELECT col1, col2, col3 FROM <table>"
result['col4']  # KeyError quando col4 não existe

# ✅ Robusto — schema vivo + .get()
sql = "SELECT * FROM <table>"
result.get('col4', default)  # tolera ausência
```

## Quando schema reconstruído ainda é útil

- **Documentação navegável** (1 .md por tabela) — `generate-baseline.py` ✅
- **Detecção de FKs por convenção** — base pra `fk_resolver.py` ✅
- **Histórico de evolução** (UPDATE blocks que tocaram a tabela) — auditoria
- **Versionamento** — saber em qual UPDATE block uma coluna apareceu

Mas **nunca** como single source of truth pra queries em runtime.

## Drift detection (futuro)

Quando o schema reconstruído diverge do vivo, vale logar/alertar:

```python
def schema_drift_check(table_name, fb_con):
    expected = schema_at_version(table_name, target_version)  # do .txt
    actual = get_table_columns(fb_con, table_name)            # vivo
    missing = expected - actual  # colunas no .txt mas não no banco
    extra   = actual - expected  # colunas no banco mas não no .txt (ALTER manual?)
    if missing or extra:
        warn(f"Drift em {table_name}: missing={missing}, extra={extra}")
```

Tabela `<sistema>` pode ter sido ALTER-ada fora do pipeline canônico — drift indica risco.

## Quando NÃO precisa schema vivo

- Schema legacy é **frozen** (sistema descomissionado, ninguém alterando)
- Importer é one-shot definitivo, não roda mais

Mesmo nesses casos, o custo de query schema vivo é zero — sempre preferir.

## Lição aprendida (sessão 2026-05-09)

`generate-baseline.py` parseou `UpdateSQL.txt` e produziu 393 docs de tabela com **ALTA fidelidade** vs schema real. Mas:
- Tabelas pré-v6 (CONTAS, BANCOS, EMPRESA, PESSOAS) criadas em `BancoLocal.sql` ficaram com colunas faltando
- ALTER multi-add inline (UPDATE 1140 CONTAS) mostra coluna falsa colada

Solução em runtime: importer usa `SELECT *` + `.get(col, default)`. Schema reconstruído é navegação, não autoridade.
