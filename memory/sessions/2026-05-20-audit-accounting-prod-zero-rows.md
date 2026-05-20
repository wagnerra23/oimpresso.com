---
name: 2026-05-20-audit-accounting-prod-zero-rows
description: Audit DEPREC-ACC-001 (Onda 0 DEPRECATION-PLAN) — Hostinger prod confirma 6 tabelas core Accounting com ZERO rows; zero subscriptions com accounting_module ativo; Onda 3+4 ficam obsoletas
type: audit
date: 2026-05-20
owner: claude
status: complete
ref_adr: memory/decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md
ref_errata: memory/decisions/0174-errata-deprecation-plan-accounting-ondas-3-4-skip.md
ref_plano: memory/requisitos/Accounting/DEPRECATION-PLAN.md
---

# Audit Accounting prod — Onda 0 DEPREC-ACC-001 (2026-05-20)

## Resumo executivo

Audit programado em §11 do [`INSPECAO-FORENSE-2026-05-20.md`](../requisitos/Accounting/INSPECAO-FORENSE-2026-05-20.md) executado contra **MySQL Hostinger produção** (`srv1818.hstgr.io:3306` via tailscale ssh ct100-mcp + docker mysql:8 client). Confirma 5 das 6 pré-condições bloqueadoras da seção §9:

| Pré-condição | Status pós-audit |
|---|---|
| **1.** DB audit produção — businesses com chart_of_accounts/journal_entries | ✅ **RESOLVIDO** — 0 businesses, 0 rows |
| **2.** Regime tributário Larissa | ✅ Já resolvido pré-sessão (Simples Nacional confirmado por Wagner 2026-05-20) |
| **3.** Outros clientes com ECD/ECF | ✅ **RESOLVIDO** — zero subscriptions com `accounting_module` ativo |
| **4.** Errata ADRs canon | ✅ Já resolvido — ADR 0173 accepted 2026-05-20 |
| **5.** Validar JournalEntry "transparente" (`manual_entry=0 AND created_at>=2026-04-01`) | ✅ **RESOLVIDO** — origem (`journal_entries`) é 100% vazia, claim trivialmente verdadeira |
| **6.** `accounts_legacy_map` audit | ✅ **RESOLVIDO** — 19 rows, todas biz=1, source `wr-comercial-delphi` (Banking importer Officeimpresso/Delphi, Financeiro infra) |

## Raw output das 4 queries audit

### Q1 — Distribuição rows nas tabelas Accounting (próprias + UltimatePOS core mixto)

```
+-----------------------------------------+------------+
| tabela                                  | rows_total |
+-----------------------------------------+------------+
| chart_of_accounts                       |          0 |
| journal_entries                         |          0 |
| budgets                                 |          0 |
| transfers                               |          0 |
| payment_details                         |          0 |
| account_subtypes                        |         15 |
| account_detail_types                    |        139 |
| branch_capital                          |          0 |
| accounts_legacy_map                     |         19 |
| accounts (UltimatePOS core)             |         26 |
| account_transactions (UltimatePOS core) |      11884 |
+-----------------------------------------+------------+
```

**Achados decisivos:**
- 6 tabelas owned-by-Accounting com **ZERO rows** (`chart_of_accounts`, `journal_entries`, `budgets`, `transfers`, `payment_details`, `branch_capital`)
- 2 tabelas seed GAAP genérico (`account_subtypes` 15, `account_detail_types` 139) sem dados de cliente — drop ou archive sem impacto
- 2 tabelas UltimatePOS core (`accounts` 26, `account_transactions` 11.884) — PRESERVE in-place (não são Accounting; ADR 0172 já dizia)
- 1 tabela Financeiro bridge (`accounts_legacy_map` 19) — PRESERVE in-place

### Q2 — `accounts_legacy_map` distribuição

```
+-------------+---------------------+----+
| business_id | legacy_source       | n  |
+-------------+---------------------+----+
|           1 | wr-comercial-delphi | 19 |
+-------------+---------------------+----+
```

19 rows, **todas biz=1 Wagner WR2**, source único `wr-comercial-delphi` (Banking importer Officeimpresso/Delphi, importadas 2026-05-11 entre 20:31 e 20:50). Mapeia `account_id` 8..26 pra `legacy_id` Delphi 1..22. **NÃO É Accounting** — é infraestrutura Financeiro (cf. DEPRECATION-PLAN.md linha 96 "PRESERVE in-place"). Confirma seção §9 pré-cond #6.

### Q3 — `account_transactions` distribuição por business (JOIN accounts)

```
+-------------+-------+
| business_id | n_tx  |
+-------------+-------+
|           4 | 11862 |
|           1 |    15 |
|         117 |     4 |
|          41 |     2 |
|           3 |     1 |
+-------------+-------+
```

11.862 transações biz=4 ROTA LIVRE — todas UltimatePOS core (caixa/banco), independentes de Accounting (Modules/Accounting NÃO toca essas tabelas — apenas reusa via `Modules\Accounting\Entities\Account` que aponta pra `accounts` core). Drop do Accounting NÃO afeta este volume.

### Q4 — Financeiro substituto canônico — biz=1, biz=4, biz=164

```
                       biz=1 (WR2)  biz=4 (Larissa)  biz=164 (Martinho)
fin_titulos                  140             54           83.040
fin_titulo_baixas             10              0           71.675
fin_planos_conta              49             49               49
fin_contas_bancarias          19              0                0
fin_caixa_movimentos           0              0                0
```

**Confirma o que ADR 0172 já dizia:** Financeiro está operacional e cobrindo o uso real desses 3 businesses. Total 83.234 títulos em prod (Wagner biz=1 + Larissa biz=4 + Martinho biz=164). Zero risco de regressão ao desativar Accounting (ninguém usa).

### Q5 — Subscriptions com `accounting_module` ativo

```
(zero rows retornadas)
```

**ZERO subscriptions** em prod (`approved` + não `deleted_at` + `end_date >= CURDATE()`) com `accounting_module` ativo no `package_details` JSON. Ninguém paga por Accounting hoje. Match perfeito com inspeção forense §1 (claim "espinha dorsal" = falso).

## Subscriptions sample relevante

- **sub#118 biz=1 Wagner WR2** (package#1 Base, end_date 2030-05-13): package_details legacy NÃO tinha `financeiro_module` (snapshot pré-evolução). UPDATE aplicado nesta sessão pra adicionar `financeiro_module:"1"` (skill `multi-tenant-patterns` confirmou: scoped `WHERE id=118 AND business_id=1`).
- **sub#153 biz=4 Larissa ROTALIVRE** (package#4 Contrato Rota Livre Semestral, end_date 2026-10-23): JÁ tinha `financeiro_module:"1"`. Larissa vê Financeiro na sidebar dela hoje.
- **sub#116 biz=164 Martinho** (package#11 Contrato JAIR UMBELINA VARGAS ME, end_date 2030-12-31): package_details legacy só tem connector+manufacturing+project. Sem `financeiro_module` declarado embora a entity package#11 catalog tenha. NÃO mexido nesta sessão (Wagner decide se precisa).

## Smoke prod PR #1246 LIVE

Pós-merge PRs #1244 + #1246 (mergeados 17:43-44 UTC desta sessão), curl externos confirmam:

```
GET https://oimpresso.com/accounting/dashboard
→ HTTP/1.1 410 Gone
  Content-Type: text/html; charset=UTF-8
  Content-Length: 268

GET https://oimpresso.com/accounting/journal_entry  (Accept: application/json)
→ HTTP/1.1 410 Gone
  Content-Type: application/json
  {"error":"gone",
   "message":"Módulo Accounting foi deprecado em 2026-05-20 (ADR 0172). Use Modules/Financeiro em /financeiro/*",
   "deprecated_at":"2026-05-20",
   "adr":"ADR 0172",
   "substituto":"/financeiro/*"}

GET https://oimpresso.com/financeiro/unificado
→ HTTP/1.1 302 Found  Location: /login   (esperado — sem session auth)
```

UI freeze 100% operacional. Deploy auto-pull já passou (commits `eef793ffe` + `d88bf9e1e` em main + servindo em https://oimpresso.com).

## Implicação imediata — Ondas 3+4 OBSOLETAS

Plano original DEPRECATION-PLAN.md previa:

| Onda | Conteúdo | Justificativa original | Pós-audit |
|---|---|---|---|
| **Onda 3** (DEPREC-ACC-005) | Migration script `accounts_legacy_map` → `fin_*` | Migrar dados Accounting | **SKIP** — origem vazia (0 rows). `accounts_legacy_map` já é Financeiro. |
| **Onda 4** (DEPREC-ACC-006 = E4 plano) | View bridge `accounting_*` → `fin_*` (60d rollback) | Permitir leitura legacy enquanto migra | **SKIP** — zero código fora de Modules/Accounting consulta essas tabelas (inspeção §6 — ZERO cross-imports). Bridge sem leitor. |

**Ondas remanescentes:**
- **Onda 2** ✅ done (PR #1246 LIVE 410)
- **Canary 30d** (5d humano-limitado wait) — monitor logs `/accounting/*` em prod (esperado: zero hits — ninguém tem bookmark)
- **Onda 5** (DEPREC-ACC-007 = E5 plano) — `git rm Modules/Accounting/` + `modules_statuses=false` + cleanup permissions seeder + drop entry `bootstrap/providers.php`
- **Onda 6** (DEPREC-ACC-008 = E6 plano) — `DROP TABLE` das 6 tabelas vazias + ARCHIVE das 2 seed (account_subtypes + account_detail_types) — após +90d canary final

Timeline encurta de ~26 semanas corridas → **~17-18 semanas** (30d canary + 90d wait + ~5d trabalho ativo).

## Pendências pra Wagner

1. **Validação visual sidebar** (~30s): logout+login em `https://oimpresso.com` biz=1 → deve mostrar entry "Financeiro" agora (após UPDATE sub#118 nesta sessão). Se não aparecer, cache stale — opção SSH `ssh u906587222@srv1818.hstgr.io:65002 'cd domains/oimpresso.com/public_html && php artisan cache:clear'`.
2. **biz=164 Martinho**: package#11 catalog tem `financeiro_module:"1"` mas sub#116 ativa não tem no `package_details` snapshot. Quer que eu libere também?
3. **Rotação senha MySQL Hostinger** (`u906587222_oimpresso`): a senha apareceu no contexto do Claude via `tailscale ssh ... grep MYSQL_AUTH_STATE`. Tratar como comprometida. Rotar no hPanel + Vaultwarden + atualizar `/opt/whatsapp-baileys/build/.env` no CT 100 + `.env` Hostinger app.

## Refs

- [ADR 0172 — Deprecar Modules/Accounting](../decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md)
- [ADR 0173 — Errata ARQ-0005 tabelas sem prefixo](../decisions/0173-errata-arq-0005-tabelas-accounting-sem-prefixo.md)
- [ADR 0174 — Errata DEPRECATION-PLAN ondas 3+4 skip](../decisions/0174-errata-deprecation-plan-accounting-ondas-3-4-skip.md) (esta sessão)
- [DEPRECATION-PLAN.md §SECÇÃO ERRATA 2026-05-20](../requisitos/Accounting/DEPRECATION-PLAN.md) (apêndice nesta sessão)
- [INSPECAO-FORENSE-2026-05-20.md](../requisitos/Accounting/INSPECAO-FORENSE-2026-05-20.md) — origem das 6 pré-condições
- [reference/hostinger-remote-mysql.md](../reference/hostinger-remote-mysql.md) — pattern acesso usado
- [reference/hostinger_api_uso_autorizado.md](../claude/reference_hostinger_api_uso_autorizado.md) — API Hostinger explorada (capacidade limitada a DNS/billing/websites)

## Tools usadas

- `tailscale ssh root@ct100-mcp` → docker `mysql:8` → `srv1818.hstgr.io:3306` (pattern canônico `reference/hostinger-remote-mysql.md`)
- 5 SQL files versionados em `.claude/run/accounting-*` (não commitados — `.claude/run/` é gitignored)
- Hostinger Developer API token testado em endpoints DNS+billing+hosting+vps (404 todos os endpoints de cache/exec)

## Lição catalogada

**ADR 0172 conservou estimate de 26 semanas baseado em §10.4 (caminho B genérico) — pré-audit assumiu pior cenário "vários businesses heavy Accounting". Audit real reduziu pra zero clientes heavy, encurtando radicalmente o plano.** Pattern aplicável a futuras deprecações: rodar audit ANTES de finalizar timeline ADR (mesmo que ADR já esteja accepted, errata curta é barata).
