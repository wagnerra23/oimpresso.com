---
number: 173
title: "Errata ARQ-0005 — tabelas Accounting usam nomes nus (não prefixo `accounting_*`)"
status: aceito
date: "2026-05-20"
accepted_at: "2026-05-20"
decided_by: [W]
authors: [wagner]
errata_de:
  - memory/requisitos/Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md
  - memory/requisitos/Accounting/adr/arq/0001-contabilidade-isolada-do-financeiro-transacional.md
relacionada_a:
  - memory/decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md
escopo: historical-fix + forward-looking rule
---

# ADR 0173 — Errata ARQ-0005 + ARQ-0001: tabelas Accounting usam nomes nus, não prefixo `accounting_*`

## Status

**accepted** (Wagner aprovou 2026-05-20 simultaneamente à ADR 0172. Drift histórico aceito; regra forward-looking proibindo nomes nus de tabela vale daqui em diante.)

## Contexto

Duas ADRs aceitas em abril 2026 prometeram que `Modules/Accounting` usaria prefixo `accounting_*` em todas suas tabelas DB:

- [`ARQ-0005 Financeiro vs Accounting paralelo`](../../requisitos/Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md) (accepted 2026-04-24) linha 14 e seções de migration mapping
- [`ARQ-0001 Contabilidade isolada do transacional`](../../requisitos/Accounting/adr/arq/0001-contabilidade-isolada-do-financeiro-transacional.md) (accepted 2026-04-22) chumbou padrão similar

**Tabelas declaradas nas ADRs (prefixo `accounting_*`):**
- `accounting_accounts`
- `accounting_account_transactions`
- `accounting_acc_trans_mappings`
- `accounting_journal_entries`
- `accounting_budget`

**Tabelas que existem no código real (sem prefixo):**

| Migration file | Tabela criada | Data |
|---|---|---|
| `2019_07_07_065905_create_chart_of_accounts_table.php` | `chart_of_accounts` | 2019-07-07 |
| `2019_07_07_071411_create_journal_entries_table.php` | `journal_entries` | 2019-07-07 |
| `2019_07_07_073301_create_payment_types_table.php` | `payment_types` | 2019-07-07 |
| `2022_01_17_104013_create_payment_details_table.php` | `payment_details` | 2022-01-17 |
| `2022_01_19_134143_create_countries_table.php` | `countries` | 2022-01-19 |
| `2022_02_01_182711_create_transfers_table.php` | `transfers` | 2022-02-01 |
| `2022_02_03_120802_create_budgets_table.php` | `budgets` | 2022-02-03 |
| `2022_02_09_124715_create_account_detail_types_table.php` | `account_detail_types` | 2022-02-09 |
| `2022_02_09_124903_create_account_subtypes_table.php` | `account_subtypes` | 2022-02-09 |
| `2022_06_08_144938_create_branch_capital_table.php` | `branch_capital` | 2022-06-08 |

Drift descoberto em sessão `understand` 2026-05-20 (linha 78) e re-confirmado na [`INSPECAO-FORENSE-2026-05-20.md`](../../requisitos/Accounting/INSPECAO-FORENSE-2026-05-20.md) seção 2.4.

**Causa raiz histórica:** Migrations Accounting foram criadas em 2019-2022 herdando padrão UltimatePOS (sem prefixo de módulo). ADR ARQ-0005 escrita em 2026-04-24 declarou prefixo prescritivo **sem auditar nomes reais no código** — drift documentado nunca corrigido por errata até esta ADR.

## Decisão

**Duas decisões em uma errata:**

### Decisão 1 — Aceitar drift histórico (não refactor tabelas)

Aceitar oficialmente que as 10 tabelas listadas acima usam **nomes nus sem prefixo `accounting_*`**. Não vale refactor (RENAME TABLE) porque:

- ADR 0172 deprecação proposta simultânea — todas essas tabelas serão archived/dropped em E4-E6 do plano
- Refactor antes de deprecar = trabalho desperdiçado
- 4 anos de produção com nomes nus + zero quebra cross-módulo + zero cliente afetado = drift estável (não risco operacional)
- Custo de RENAME TABLE em produção (foreign keys cross-tabela + downtime potencial) > benefício documentação

### Decisão 2 — Regra forward-looking (vincular módulos futuros)

**Qualquer módulo NOVO no projeto oimpresso (post-2026-05-20) DEVE usar prefixo de módulo nas tabelas DB.**

**Padrão canon:**

```php
// ✅ CERTO — prefixo <modulo>_<entidade>
Schema::create('fin_titulos', ...)         // Financeiro
Schema::create('jana_chat_messages', ...)  // Jana
Schema::create('pcp_ordens_producao', ...) // Pcp
Schema::create('cobranca_charges', ...)    // Cobranca (Onda 35+ split)
```

```php
// ❌ ERRADO — nomes nus sem prefixo (cria drift como Accounting)
Schema::create('titulos', ...)
Schema::create('chat_messages', ...)
Schema::create('ordens_producao', ...)
```

**Exceções permitidas (legacy intocável):**
- Tabelas UltimatePOS core herdadas (`transactions`, `business`, `users`, `contacts`, etc) — mantém nomes nus por questão de não-quebra do core
- Tabelas Accounting já existentes (esta errata aceita drift)
- Tabelas system-wide compartilhadas pela infra (`migrations`, `failed_jobs`, `cache`, `permissions` Spatie, `activity_log` Spatie, etc)

**Enforcement sugerido:**
- Hook `pre-commit` (futuro, opcional Tier C) que faz `grep "Schema::create\\('[a-z_]+'" Modules/<Mod>/Database/Migrations/*.php` e alerta se nome de tabela criada não começa com prefixo do módulo (snake_case do nome do módulo, ex: `fin`, `jana`, `pcp`, `cobranca`)
- ADR follow-up se quisermos formalizar pre-commit (não escopo desta errata)

## Consequências

### Positivas

- **Governance honestamente atualizada** — ADRs canônicas (ARQ-0005, ARQ-0001) agora têm errata explícita que reflete realidade de código
- **Padrão forward-looking explícito** — qualquer dev (humano ou IA) que crie módulo novo sabe regra clara
- **Reduz drift futuro** — convenção lifecycle ADR ([ADR 0095](../0095-skills-tiers-convencao-interna.md) padrão interno) preservada
- **Zero refactor risk** — não toca em DB de produção
- **Zero trabalho desperdiçado** — tabelas Accounting morrem em ADR 0172 ondas E4-E6

### Negativas

- **Documentação histórica permanece "errada" nas ADRs antigas** — leitor de ARQ-0005 hoje vê `accounting_*`; só vê errata se também ler ADR 0173. Mitigação: este ADR aparece em `decisions-search` MCP server (default lista todas accepted incluindo erratas).

- **Convenção forward-looking sem enforcement automático hoje** — depende de revisor humano em PR. Mitigação: regra clara escrita aqui + commit-discipline review.

## Compliance check

| Princípio | Atendido? | Como |
|---|---|---|
| **Append-only ADR** ([ADR 0094](../0094-constituicao-v2-7-camadas-8-principios.md) p.7) | ✅ | Esta ADR é NOVA, não edita ARQ-0005 nem ARQ-0001 — usa frontmatter `errata_de:` |
| **Convenção lifecycle ADR** ([ADR 0095](../0095-skills-tiers-convencao-interna.md)) | ✅ | Status `proposed` → `accepted` quando Wagner aprovar; errata é forma canon de corrigir ADR aceita |
| **Multi-tenant Tier 0** ([ADR 0093](../0093-multi-tenant-isolation-tier-0.md)) | N/A | Esta ADR não toca DB |
| **LGPD** | N/A | Documentação only |

## Refs

- [memory/decisions/proposals/0172-deprecar-modulo-accounting-fundir-financeiro.md](./0172-deprecar-modulo-accounting-fundir-financeiro.md) — ADR principal proposta simultânea
- [memory/requisitos/Accounting/INSPECAO-FORENSE-2026-05-20.md](../../requisitos/Accounting/INSPECAO-FORENSE-2026-05-20.md) seção 2.4 — inventário migrations (drift catalogado)
- [memory/requisitos/Accounting/DEPRECATION-PLAN.md](../../requisitos/Accounting/DEPRECATION-PLAN.md) — plano operacional 7 ondas
- [memory/requisitos/Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md](../../requisitos/Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md) — ADR ARQ-0005 que esta errata corrige
- [memory/requisitos/Accounting/adr/arq/0001-contabilidade-isolada-do-financeiro-transacional.md](../../requisitos/Accounting/adr/arq/0001-contabilidade-isolada-do-financeiro-transacional.md) — ADR ARQ-0001 que esta errata corrige
- [ADR 0094 Constituição v2 — append-only](../0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0095 Skills tiers convenção interna](../0095-skills-tiers-convencao-interna.md)
