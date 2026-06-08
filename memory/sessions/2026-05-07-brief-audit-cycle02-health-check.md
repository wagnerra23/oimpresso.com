# Sessão 2026-05-07 — BRIEF audit + GUARD-02 + Hostinger git recovery (CYCLE-02 W20)

> **Contexto:** Wagner pediu pra ver % de conclusão da Constituição V2 + economia real de tokens.
> Auditoria revelou L7 Daily Brief com 3 bugs no aggregator + Hostinger em estado git quebrado (mid-rebase em branch `claude/nervous-greider-335083`).
>
> **Goal CYCLE-02 #6:** Constituição V2 health-check 0 alertas críticos por 7 dias consecutivos.

## Entregue (ordem cronológica)

1. **Auditoria L7 Daily Brief** — descobertas:
   - ✅ Cron `brief:generate` 6×/dia ativo (`0 7,11,14,17,20,23 * * *` SP)
   - ✅ Schema `mcp_briefs`/`mcp_brief_inputs_cache`/`mcp_skill_telemetry` em prod
   - ✅ Custo real $0.0004/brief × 6 = $0.024/dia (não $0.30 da ADR 0091)
   - ⚠️ 3 bugs no `refresh_brief_inputs_cache` (causa raíz dos 217 tokens vs alvo 3k)
   - ⚠️ Adoção `brief-first` skill: 2 triggers em 7d (alvo ≥90% sessões)

2. **PR #162 — GUARD-02 + BRIEF-A1** (mergeada por Wagner):
   - GUARD-02: Pest `tests/Feature/Audit/ModuleScaffoldingTest.php` (5/5 verdes) — itera 30 módulos, falha CI se módulo novo nasce sem InstallController/DataController/ServiceProvider
   - BRIEF-A1: Migration `2026_05_07_120000_fix_brief_aggregator_in_flight_adrs_activity` corrige 3 bugs:
     1. `decided_at > NOW() - INTERVAL 24 HOUR` → `decided_at >= CURDATE() - INTERVAL 1 DAY` (DATE-vs-DATETIME truncava à meia-noite)
     2. `commits_count` (sempre 0) → `mcp_activity_24h` + `mcp_distinct_tools_24h` + `mcp_distinct_users_24h`
     3. `in_flight` hardcoded NULL → populado de `mcp_tasks WHERE status IN ('doing','review')`

3. **Hostinger git recovery** — descobri produção em `claude/nervous-greider-335083` mid-rebase de `feat/sprint-2-memcofre-cockpit`. Wagner confirmou ("eu iniciei lembro do nervoso que ela me deu") + autorizou. Resolvi com `git rebase --abort` + `git checkout main` + `git reset --hard origin/main`. HEAD agora `844e1bfa`.

4. **Migration aplicada em prod 11:47** — validação:
   - Brief #5 gerado: ADRs 0087-0091 listadas, in_flight=wagner@RecurringBilling, mcp_activity_24h=122
   - Antes: tudo "—" / 0 / NULL. Tokens 217→235 (subida só 8% mas conteúdo 100% informativo)
   - Próximo passo: expandir prompt do gerador pra subir token count de verdade (Sprint 2 do brief)

5. **7 tasks registradas em SPEC.md + push** (commit `e096c40e` direto em main, webhook sincroniza DB MCP):
   - US-COPI-088 (done) BRIEF-A1
   - US-COPI-089 (done) BRIEF-A2
   - US-COPI-090 (todo) BRIEF-A3 — ADR 0096 superseding parcial 0091
   - US-COPI-091 (todo, blocked by 094) BRIEF-A4 — investigar baixa adoção brief-first
   - US-COPI-092 (todo, blocked by 088) GUARD-01 — schema snapshot Pest test
   - US-COPI-093 (done) GUARD-02
   - US-COPI-094 (todo) BRIEF-A2 follow-up — remover brief-fetch do Hostinger MCP

## Feedback canônico Wagner novo

> **"regra mcp é so CT 100 na hostinger não funciona e fica lento mcp. se for preciso temos que dividir o projeto"**

Salvo em [auto-mem feedback_mcp_so_ct100.md]. Reforça ADR 0062. Implicação: tool MCP exposed deve estar SÓ em CT 100 (`mcp.oimpresso.com`); Hostinger pode ter schema+service backend mas NÃO exposição MCP. Spawnado US-COPI-094 pra remover `brief-fetch` do Hostinger.

## Aprendizados meta

- **Tasks-create do MCP só persiste no DB se servidor tiver write access ao SPEC.md** — caso contrário só gera markdown. Solução: apender manualmente + push (webhook sincroniza). Documentar em ADR 0070 update?
- **Drift de stored procedure prod ≠ spec doc** — Sprint 1 deployed, alguém iterou via migration `2026_05_06_172445` mas spec `02-schema-aggregator.sql` ficou stale. GUARD-01 vai pegar regressão futura.
- **gpt-4o-mini gera brief informativo em 235 tokens** — alvo 3k da ADR 0091 era aspiracional, na verdade brief enxuto + dados ricos é melhor que brief verboso + placeholders.
- **Hostinger pode ficar mid-rebase silenciosamente** — em produção shared, alguém (Wagner) começou rebase e não terminou. Sintoma: `git status` mostra "rebase in progress" + branch errada. Sempre conferir `git status -uno` antes de pull/migrate.

## Pendências P1 próxima sessão

1. **US-COPI-094** — remover `brief-fetch` do Hostinger MCP server (regra MCP-só-CT-100). Investigar `Mcp::web()` em `Modules/Jana/Http/routes.php:211` + condicionar via env.
2. **US-COPI-092 GUARD-01** — Pest schema snapshot test + `procedure_drift` no `jana:health-check` (depende baseline pós-A1).
3. **US-COPI-090 BRIEF-A3** — ADR 0096 documentando model real gpt-4o-mini + atualizar checklist 0091.
4. **US-COPI-091 BRIEF-A4** — investigar adoção brief-first (depende 094 mergear).
5. **Sprint 2 do Brief (escopo novo)** — expandir prompt do gerador pra subir token count de 235 → ~1500-3000 (mais detalhe em EM VOO, ADRs, charters apodrecendo).
