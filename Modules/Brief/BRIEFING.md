# BRIEFING — Modules/Brief

> Estado consolidado da capacidade — 1 página executiva (regra Tier 0
> 2026-05-15 `memory/proibicoes.md`). Atualizado por PR mexido.
> Última atualização: Wave 18 (2026-05-16).

## O que é

Daily Brief — camada L7 da arquitetura de contexto Claude (ADR 0091). Gera
~3k tokens consolidados (estado macro + em voo + decisões 24h + skills 7d +
charters apodrecendo + flags + metadata) 6x/dia via cron e expõe via tool
MCP `brief-fetch` (skill Tier A always-on `brief-first`).

**Goal:** reduzir onboarding de sessão Claude de **30k → 3k tokens** (~10x).

## Arquitetura em 5 peças

1. **`BriefGeneratorService`** — orquestra: `CALL refresh_brief_inputs_cache()` →
   lê cache → chama Brain B (sonnet-4-6) → retorna markdown.
2. **`BriefValidator`** + **`ValidationResult`** — 4 invariantes ADR 0091:
   (a) 7 headers exatos na ordem; (b) sentinela `---END---`; (c) ≤3500 tokens;
   (d) zero PII CPF/CNPJ cliente final.
3. **`GenerateBriefCommand`** — cron `0 7,11,14,17,20,23 * * *` America/Sao_Paulo.
   `--dry-run` imprime sem gravar.
4. **`BriefFetchController`** — tool MCP `POST /api/mcp/tools/brief-fetch` com
   cache 5min em `brief.current`, force_refresh restrito a Wagner com cap
   8/dia, audit em `mcp_audit_log`, telemetria skill em `mcp_skill_telemetry`.
5. **`BriefHealthCommand`** — `brief:health` (4 sinais OK/WARN/FAIL).

## Multi-tenant Tier 0 ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))

Brief é **repo-wide** — agrega estado global do projeto pro time interno
Wagner/Maiara/Felipe/Eliana/Luiz. SEM `business_id` scope. Single fonte da
verdade pra todos os agents Claude. Documentado em ADR 0091 §3.

## Observabilidade — D9 OTel

`OtelHelper::spanBiz` instrumentado em **3 superfícies** (Wave 17 + 18):

- `brief.fetch_current` — span no Controller (DB read + cache miss)
- `brief.validate` — span na validação (custo regex PII)
- `brief.validation.ok` / `brief.validation.fail` — factory ValidationResult
  (signal de regressão Brain B)

Zero-cost quando `otel.enabled=false`.

## Custo IA (ADR 0094 §princípio 4)

- **Cron 6x/dia × sonnet-4-6** ≈ ~$0.015/run × 6 = **~$0.09/dia** ≈ $2.7/mês
- **Tool MCP brief-fetch** — cache 5min, ~$0.0 por hit cached, ~$0 por miss
  (apenas DB read)
- **force_refresh** — cap 8/dia × ~$0.015 = max $0.12/dia (Wagner only)

## D6 Inertia::defer — N/A

Brief é módulo MCP/CLI puro — sem páginas Inertia. D6 score 10/10 por
ausência de overhead frontend.

## FSM — N/A

`module.json`: `governance.fsm_n_a: true`. Brief é gerador L7 stateless —
ciclo linear (refresh → Brain B → valida → grava → invalida cache). Sem
máquina de estados por entidade de negócio. FSM Pipeline (ADR 0143) aplica
a Sells/Repair, não a artefatos efêmeros de meta-contexto.

## Pendentes conhecidos

- **`POST /brief/admin/generate`** — endpoint HTTP futuro (FormRequest
  `GenerateBriefRequest` já criado Wave 18 pronto). Controller ainda inexistente.
- **Multi-tenant briefs** — `mcp_briefs.business_id` opcional pra futura
  versão por-tenant (hoje brief é global Wagner agent). Pest
  `BriefMultiTenantTest.php` documenta o gap via `markTestSkipped()` quando
  coluna ausente.

## Links

- [ADR 0091 — Daily Brief](../../memory/decisions/0091-daily-brief.md)
- [Sprint 1 docs](../../memory/sprints/s1-daily-brief/)
- [Skill `brief-first` (Tier A always-on)](../../.claude/skills/brief-first/SKILL.md)
- [CHANGELOG.md](./CHANGELOG.md)
- [SCOPE.md](./SCOPE.md)
