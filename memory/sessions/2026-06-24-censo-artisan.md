# Censo de Comandos Artisan — Onda D da auditoria das máquinas

**Data:** 2026-06-24 · **Origem:** Onda D do backlog da [auditoria das máquinas](2026-06-24-audit-maquinas-planos-runbooks-skills.md) — o lado PHP/artisan tinha ficado raso (o adversário pegou: só 4 de 210 comandos vistos). **Método:** workflow de 6 fatias paralelas read-only (35 `app/Console` + 175 `Modules`) → síntese. **207 comandos** lidos e classificados (signature + description + handle + cruzamento com schedule).

> ⚠️ **Pegadinha metodológica:** grepar SÓ `app/Console/Kernel.php` **subconta** o schedule. ≥6 comandos são agendados **dentro do ServiceProvider do módulo** (via `$app->booted` + `Schedule`), invisíveis ao grep do Kernel: `nfe:health` (06:07), `rb:generate-invoices` (03:00), `paymentgateway:emit-trial-expired` (08:00), `pos:autoClockOutUser` (every30min), `pos:WooCommerceSyncOrder`/`WoocommerceSyncProducts` (twiceDaily per-biz). **Auditoria de cron tem que varrer também os `*ServiceProvider.php`.**

## Visão geral
| Métrica | Valor |
|---|---|
| Total de comandos | **207** |
| Agendados (cron/Provider) | 76 (~37%) |
| Não-agendados | 131 (~63%) |
| Sem `$description` | 0 |
| Convenção `--detail` (não `--verbose`) | 100% |

**Por categoria:** health ~45 · cron-rotina ~40 · backfill-oneshot ~30 · debug-dev ~25 · integração-externa ~18 · relatório ~17 · seed ~12 · migração ~10.

## 🔴 P0 — corrigir (registrados como tarefa)
- **`paymentgateway:retry-orphan-webhooks` é GHOST-SCHEDULED.** O docblock afirma cron `everyFiveMinutes` no Kernel, mas a entry **NÃO existe** (grep Kernel + todos os Providers). Webhooks de pagamento órfãos (race: webhook chega antes da Cobrança) **nunca reprocessam em prod**; quando rodam, disparam `CobrancaPaga` (quita título = **VALOR**). Ou liga o cron, ou clientes ficam com pagamento confirmado e título em aberto. **Achado mais sério do censo.**
- **`pos:autoClockOutUser` (Essentials, every30min via Provider)** faz UPDATE de massa em `essentials_attendances` **SEM filtro `business_id`** — cross-tenant. Única violação real de isolamento multi-tenant Tier 0 do censo (comando legacy UltimatePOS). Toca ponto de TODOS os businesses.

## Flags — MORTOS / GHOST-SCHEDULED
- `pos:mapPurchaseSell` — sem schedule; única ref era chamada **comentada** em `CreateDummyBusiness`. Destrutivo (DELETE mapeamento + recalcula ESTOQUE cross-business).
- `reverb:ping` — smoke do broadcaster Reverb, substituído por Centrifugo (ADR 0058). Nome legado, sem call site.
- `mem:sync-status` / `mem:audit` / `mem:coverage` — camada auto-mem órfã: dependem de diretório `~/.claude/.../memory` + mirror `memory/claude/` **purgados** (ADR 0061 + auditoria 2026-06-07).
- `memcofre:sync-memories` (SRS) — comentado do Kernel 2026-06-07 (vetor de vazamento de credenciais + ressuscitava auto-mem, viola ADR 0061). Existe e funcional, **banido sem ADR formal**.
- `licenca-log:parse` (Officeimpresso) — docblock manda agendar `everyFiveMinutes`, sem entry no Kernel nem Provider.
- `handoff:ingest` (TeamMcp) — docblock admite trigger "wiring de deploy fora do escopo do PR-1"; loop de handoff zero-paste **pela metade** (nunca dispara).
- `governance:health` / `charter:metrics` / `module:grade-v4` — só on-demand + testes; `governance:health` docblock afirma "06:35 BRT" mas o slot é do `governance:audit`.
- `pos:generateRecurringExpense` — irmão `pos:generateSubscriptionInvoices` agendado (23:30), despesa recorrente **não** (wiring esquecido).
- **21 health-checks sem cron:** `financeiro:health`, `nfse:health`, `rb:health` + 18 `*:health` de verticais/core prometem cron 06:xx no docblock, **nunca ligado**. Só `arquivos:health-check` roda. O exit 1/2 que acenderia alerta no monitoring nunca dispara — **promessa de health-check é teatro até ligar o schedule**.

## Flags — DUPLICATAS
- **RAGAS/eval quadruplicado:** `eval:adr-discovery` ⊂ `eval:ragas-baseline` + `jana:ragas:eval` (W22) + `jana:ragas-ci-eval` (W28) + `jana:drift-sentinel` — W28 deveria substituir W22, coexistem. Custo LLM redundante.
- **`governance:audit`** declarado como meta-substituto de `governance:detect-drift` + `secrets:scan/audit` + `charter:health` — **todos coexistem agendados** em "canary 7d" sem prazo de saída → drift duplo-reportado.
- Síntese semanal 2× (`copiloto:sintese-semanal` sex 18h vs `jana:weekly-digest` seg 09h) — coexistência intencional documentada.
- `module:specs` vs `module:requirements` (técnico vs funcional, mesma descoberta).
- `ensureContaAClassificar` duplicado em `financeiro:backfill-plano-conta` + `financeiro:bridge-expense-to-titulos` (candidato a service).
- **18 `*:health` copy-paste** de 2 templates quase idênticos — débito DRY (candidato a `AbstractHealthCommand`).

## Flags — RISCO TIER 0 (além do `autoClockOutUser`)
- `pos:dummyBusiness` — `migrate:fresh` **destrutivo** (apaga TODOS os business), guardado só por `env==='demo'`; `DB::commit()` comentado.
- `business:set-pos-setting … all` — escreve `pos_settings` de TODOS business; pode togglar `allow_overselling`/`enable_msp` em massa (afeta estoque/cálculo).
- `pos:mapPurchaseSell` / `RecurringInvoice` / `RecurringExpense` / `updateRewardPoints` — iteram cross-business sem global scope (`where('business_id')` manual).
- **Pipeline ADS** (`ProcessBrainBCommand`/`ReviewDecisionsCommand`/`PlanDecisionsCommand`) — `DB::table('mcp_dual_brain_decisions')` sem filtro `business_id` e **sem o comentário `// SUPERADMIN`** que [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) exige (os demais cross-tenant documentam).
- **Credenciais em CLI:** `nfse:importar-cert --senha` e `officeimpresso:import --pass=masterkey` (default exposto no help / shell history).

## Flags — STALE/DRIFT que cega sentinela
- **`secrets:audit`** (AGENDADO daily, health): `validateHostingerApi()` lê `memory/claude/reference_hostinger_hpanel.md` **purgado** 2026-06-07 → token Hostinger sempre 'pending' (sentinela cega). Corrigir o ponteiro pro [`_INDEX-SECRETS`](../_INDEX-SECRETS.md) / CT100 `/root/.hostinger-api-token`.
- Doc-drift cosmético: `whatsapp:daemon-source-drift-check` (doc "semanal" vs Kernel daily), `nfe:health` (docblock "06:05 Kernel" vs real 06:07 Provider).
- `connector:health` checa `licenca_computador` (singular) vs `officeimpresso:health` `licenca_computadores` (plural) — um aponta tabela errada (mascarado por `Schema::hasTable`).
- `ads:auto-generate-tasks --dry-run` não implementado (handle admite "V2") — flag enganosa.

## Próximos passos
- Os 2 **P0** (`retry-orphan-webhooks` ghost · `autoClockOutUser` cross-tenant) foram registrados como tarefa separada (tocam VALOR / Tier 0 → fix careful, fora do escopo read-only deste censo).
- Candidatos de **higiene** (aposentar mortos `mem:*`/`reverb:ping`/`pos:mapPurchaseSell`; consolidar RAGAS/eval; ligar os 21 health-checks ou tirar a promessa do docblock; `AbstractHealthCommand`) ficam de backlog — nenhum urgente.
