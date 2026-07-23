---
id: dominios-patterns-07-three-mode-importer
---

# Pattern 07 — Three-mode importer (dry-run / local / prod)

**Status**: canônico desde 2026-05-09

## Contexto

Importer que escreve dados em DB de produção é arma carregada. Bug de mapping = corrupção de dados de cliente real, perda de histórico, churn pós-migração.

## Problema

- Run direto contra prod = sem rede de segurança
- Run só em dev local = não pega quirks de schema/dados de prod
- Smoke incremental sem isolamento progressivo = cascata de problemas mascarando causa raiz

## Solução — flag `--target` com 3 níveis

```python
parser.add_argument("--target", choices=["dry-run", "local", "prod"], default="dry-run")
parser.add_argument("--confirm", action="store_true",
                    help="OBRIGATÓRIO em --target prod")
```

### Modo 1 — `dry-run` (default)

- **Lê** banco legacy normalmente
- **Não conecta** no MySQL destino
- **Gera SQL** completo com placeholders preenchidos → salva em `output/dry-run-<ts>.sql`
- Retorna `account_id=999999` placeholder (placeholder pra fluxo continuar)
- **Útil pra**: validar mapping campo-a-campo antes de qualquer write; revisar SQL gerado

### Modo 2 — `local` (Herd / DB dev)

- Conecta no MySQL local (`127.0.0.1:3306`, env `MYSQL_*`)
- **Escreve real** com transação (commit no fim, rollback em erro)
- Retorna `lastrowid` real
- **Útil pra**: smoke ponta-a-ponta antes de prod; validar UI no dev (`oimpresso.test/financeiro/contas-bancarias`)

### Modo 3 — `prod` (Hostinger MySQL via Remote MySQL whitelist)

- Conecta na produção real
- **Exige `--confirm` explícito** — sem isso, aborta com exit code 2
- Mesmo fluxo `local` mas alvo é prod

## Implementação

```python
class MysqlWriter:
    def __init__(self, target='dry-run', ...):
        self.target = target
        self.con = None
        self.dry_run_lines = []

    def __enter__(self):
        if self.target in ('local', 'prod'):
            self.con = pymysql.connect(...)
        return self

    def _execute(self, sql, params, returning_id=False):
        if self.target == 'dry-run':
            self.dry_run_lines.append(format_inline(sql, params))
            return 999999 if returning_id else None
        with self.con.cursor() as cur:
            cur.execute(sql, params)
            if returning_id: return cur.lastrowid
        return None
```

## Fluxo recomendado

```
1. dry-run --limit 1                       → 1 SQL gerado, valida mapping
2. dry-run --limit 3                       → batch pequeno, vê variações
3. dry-run sem limit (ou --limit 50)       → batch grande, vê edge cases
4. local --limit 1                         → 1 INSERT real no Herd
5. local --limit 3                         → 3 + idempotência (1 update + 2 inserts em re-run)
6. local sem limit                          → batch completo no dev, valida UI
7. prod --limit 1 --confirm                → smoke prod isolado
8. prod --confirm                          → batch prod completo
```

Cada step é gate manual. Falha em qualquer step pausa o pipeline.

## Validação smoke (sessão 2026-05-09)

Steps 1-6 executados contra `servidor-crm:Banco` (Wagner) → biz=1 oimpresso local. Resultado:
- 3 contas reais importadas: Caixa 104, Itaú 341, conta inicial vazia
- Idempotência confirmada: re-run = 1 UPDATE + 2 INSERTs
- Step 7-8 pendentes (Wagner aprova quando satisfeito com local)

## Variantes futuras

- **`--target staging`** — quando oimpresso tiver ambiente staging dedicado (não tem hoje)
- **`--target replica-readonly`** — pra validar lookup queries sem risco de write acidental
- **`--save-rollback-sql`** — gera SQL `DELETE` reverso pra rollback rápido pós-prod

## Quando NÃO usar 3 modos

- Importer trivial (CSV → tabela única, sem FK, sem multi-tenant) — modo único OK
- One-shot definitivo sem necessidade de re-run — só prod com confirm

## Risco mitigado

- **Bug de mapping descoberto em prod** — dry-run + local pegam antes
- **Schema divergence prod vs dev** — step 4-6 (local) pega; step 7 (prod limit 1) confirma
- **Permissions / FKs faltando em prod** — step 7 falha com 1 só linha, não corrompe
- **Cliente em pânico** — rollback é só `DELETE WHERE legacy_source=X` (rápido + auditável)
