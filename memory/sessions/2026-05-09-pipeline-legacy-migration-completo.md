# 2026-05-09 — Pipeline legacy migration ponta-a-ponta (Fases 0-6)

**Duração**: dia inteiro (com pausas)
**Volume**: 8 PRs mergeados (#341, #343, #345, #347, #348, #353, #354, [+ este consolidando aprendizados])
**Resultado**: pipeline Brownfield AI completo do banco Delphi WR Comercial → MySQL oimpresso, com 3 contas reais validadas em smoke local biz=1.

## Origem e escopo

Sessão começou Wagner pedindo "acesse meu banco de dados" via screenshot do **Editor de Registros de Bancos de Dados WR2 v3.5** mostrando 50 bancos Firebird registrados em `HKCU\Software\Rocha\Office Comercial\Banco\Caminhos`. Escopo cresceu sucessivamente:

1. "Ler 1 banco" → ler todos via registry API
2. "Migrar 1 cliente" → engine reusável Migration Factory
3. "Padrão de chave estrangeira" (Wagner ensinou regra crítica COD<TABELA>=FK) → integrar como convenção canônica
4. "Faça os testes Pest" → migrations Tier 0 + 5 tests passando

Resultado final: pipeline ponta-a-ponta funcional, padrões consolidados pra futuras migrações de qualquer cliente legacy (Bling/Tiny/Sankhya/concorrentes nicho gráfico).

## PRs entregues (cronológico)

| PR | Título curto | O que entregou |
|---|---|---|
| [#341](https://github.com/wagnerra23/oimpresso.com/pull/341) | ADR 0118 + estrutura + POCs Python | Segregação `dominios/` + `clientes-legacy/`; POC1 parser UpdateSQL.txt (1452 blocos v6→v1999); POC2 conexão Firebird via registry |
| [#343](https://github.com/wagnerra23/oimpresso.com/pull/343) | Schema baseline 393 tabelas | `generate-baseline.py` com DDL parser pragmático + classificador por prefixo; 15 módulos auto-classificados, 0 em `_outros` |
| [#345](https://github.com/wagnerra23/oimpresso.com/pull/345) | MAPPING.md ACL contas bancárias | Anticorruption Layer documentada (DDD Evans 2003) — único arquivo bilíngue por design |
| [#347](https://github.com/wagnerra23/oimpresso.com/pull/347) | Importer Python scripts (dry-run) | `lib/firebird_reader.py` + `lib/mysql_writer.py` + `import-contas-bancarias.py` 3-mode; smoke 3 contas reais |
| [#348](https://github.com/wagnerra23/oimpresso.com/pull/348) | Convenção COD<TABELA>=FK | `lib/fk_resolver.py`; 1090 FKs auto-detectadas em 393 docs; lookup EMPRESA via FK CODEMPRESA |
| [#353](https://github.com/wagnerra23/oimpresso.com/pull/353) | Migrations Tier 0 + Pest verde | `accounts_legacy_map` bridge + `legacy_*` em `fin_contas_bancarias`; 5 Pest tests; 3 verdes + 2 skipped |
| [#354](https://github.com/wagnerra23/oimpresso.com/pull/354) | Smoke fix schema accounts | `account_type_id` (FK→`account_types`) + `created_by` (corrige assumption errada de schema); 3 contas reais no MySQL local |
| Este | Lições aprendidas consolidadas | `dominios/wr-comercial/CONVENCOES.md` v2 + `dominios/_patterns/` (7 patterns reusáveis) + este session log |

## Lições aprendidas (5 mais valiosas)

### 1. Wagner ensinou regra que poupou meses de descoberta

**Convenção `COD<TABELA>` = FK por convenção** ([CONVENCOES.md §1](../dominios/wr-comercial/CONVENCOES.md)). Sem isso, importer ficaria adivinhando relacionamentos coluna por coluna em 393 tabelas. Com isso, `lib/fk_resolver.py` gerou 1090 FKs automaticamente + resolvido lacuna #1 do MAPPING (CODBANCO direto = FEBRABAN, não placeholder).

**Princípio**: pergunta a Wagner por convenções antes de presumir. Convenções de codebase legado raramente estão documentadas; quem desenvolveu sabe.

### 2. Análise estática NÃO substitui Pest local em Tier 0

Auto-mem [`feedback_tenancy_changes_require_pest_local`](../claude/feedback_tenancy_changes_require_pest_local.md) cobrou — 5b foi splitada em 5a (scripts, mergeable) + 5b separado (migrations, gate Pest). Pest verde local **destravou** Tier 0 com confiança real.

**Princípio**: respeitar gates Tier 0 mesmo quando autonomia foi concedida. Custo de Pest local é baixo; custo de regressão multi-tenant em prod é catastrófico.

### 3. Schema vivo manda, não reconstruído

Pattern 05 — `generate-baseline.py` produziu 393 docs com alta fidelidade vs schema real, MAS:
- Tabelas pré-v6 (CONTAS, BANCOS, EMPRESA) faltam colunas (criadas em `BancoLocal.sql` separado)
- Bug parser multi-ADD inline em UPDATE 1140 colou colunas
- Schema Laravel real (`accounts`) tinha `account_type_id` — código tinha `account_type` ENUM hipotético

Solução: importer usa `SELECT *` + `.get(col, default)`. Schema reconstruído é navegação, não autoridade.

### 4. UPSERT idempotente per-tenant resolveu 80% dos riscos

Pattern 03 — `UNIQUE(business_id, legacy_source, legacy_id)` composto fez run/re-run/cancel/retry **safe**. Sem isso, importer seria one-shot frágil — qualquer falha exigiria limpeza manual. Com isso, `--limit 1` → `--limit 3` → batch full é fluxo natural.

### 5. PowerShell quirks custam tempo

- `composer install` no Windows precisa `--ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix` (extensões Unix-only)
- `--mysql-password ""` com aspas vazias quebra argparse → usar env var `MYSQL_PASSWORD=""`
- Emojis em stdout cp1252 default crasham → `sys.stdout.reconfigure(encoding="utf-8")` no início
- Pest crash com testsuite duplicado → `phpunit <path> --no-configuration` direto
- 11 pastas Tests vazias em phpunit.xml exigem criação manual mesmo sem testes

Auto-mem candidato pra próxima sessão Windows: `reference_powershell_quirks_python_php.md`.

## Estado pós-sessão

- Pipeline pronto pra Fase 7 (visão expandida — `Modules/MigrationFactory/`)
- 8 decisões pendentes no MAPPING.md aguardam Wagner (fin_titulo_eventos, regras conciliação, Vaultwarden segredos, etc)
- 49 outros bancos de cliente (TechPress, Display Parana, Destak, ...) prontos pra rodar mesmo importer trocando `--alias` e `--target-business`
- 3 contas teste no MySQL local biz=1 (cleanup opcional via `DELETE WHERE legacy_source='wr-comercial-delphi'`)

## Métricas

- **PRs**: 8 mergeados em sequência
- **Linhas adicionadas**: ~14k (incluindo 393 docs auto-gerados)
- **Pest**: 5 tests Tier 0 verdes (3 + 2 skipped graciosos)
- **Tempo de ciclo PR→merge**: ~10min médio (autonomia Wagner concedida mid-session)
- **Smoke real**: 3 contas Wagner (CODIGO 1, 2, 3) viraram `accounts.id` 10, 11, 12 com mapping completo
- **Modelo IA**: Claude Opus 4.7 (líder SWE-bench 87.6%) operando como ACL agent

## Pendências

1. **Fase 7** — visão expandida, ADR 0119, `Modules/MigrationFactory/` UI superadmin
2. **MAPPING.md decisões #3-#8** — fin_titulo_eventos, regras conciliação, Vaultwarden, FINANCEIRO_CHEQUE
3. **Bug parser multi-ADD inline** — `lib/ddl_parser.py` ainda colando ADD subsequentes
4. **Pasta Tests vazia padrão** — phpunit.xml configurado pra módulos sem tests; valeria scaffold automático
5. **PowerShell quirks doc** — auto-mem pra evitar perder tempo na próxima sessão Windows

## Referências

- [ADR 0118 — Segregação dominios externos](../decisions/0118-segregacao-dominios-externos-clientes-legacy.md)
- [Patterns reusáveis](../dominios/_patterns/README.md)
- [CONVENCOES.md WR Comercial](../dominios/wr-comercial/CONVENCOES.md)
- [MAPPING.md financeiro](../dominios/wr-comercial/modulos/financeiro/MAPPING.md)
- Eric Evans, *Domain-Driven Design* (2003) cap. 14 — ACL
- Martin Fowler, [*StranglerFigApplication*](https://martinfowler.com/bliki/StranglerFigApplication.html) (2004)
- [Brownfield AI — TianPan abr/2026](https://tianpan.co/blog/2026-04-12-brownfield-ai-integrating-llm-features-into-legacy-codebases)
