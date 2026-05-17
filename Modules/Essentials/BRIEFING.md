# BRIEFING — Modules/Essentials

> Estado consolidado da capacidade. Atualizado por PR (skill `brief-update` Tier B).
> Última atualização: 2026-05-16 Wave 18 SATURATION.

## O que é

HRM essencial + utilitários administrativos do oimpresso (legado UltimatePOS expandido):
- **HRM**: EssentialsAttendance (ponto eletrônico LGPD), EssentialsLeave (afastamentos), EssentialsHoliday (feriados), Shift/EssentialsUserShift (escalas), EssentialsAllowanceAndDeduction (verbas)
- **Folha**: PayrollGroup + PayrollGroupTransaction (vínculo folha × transaction)
- **Workspace**: ToDo + EssentialsTodoComment, Reminder, KnowledgeBase, Document + DocumentShare
- **Mensageria interna**: EssentialsMessage (chat between users do mesmo business)
- **Metas**: EssentialsUserSalesTarget (targets comerciais individuais)

## Status atual

- **Module Grade v3**: 74/100 → meta 88+ via Wave 18 SATURATION (D1 +14, D6 +5, D7 +5)
- **Multi-tenant Tier 0**: 18 Entities com `HasBusinessScope`/`BelongsToBusinessViaParent` (D1 100%)
- **LGPD retention**: 18 entries em `Config/retention.php` cobrindo todo dado pessoal (D7 100%)
- **LogsActivity**: aplicado em entities sensíveis (EssentialsMessage + Document via traits Spatie herdados)
- **FSM canon (ADR 0143)**: N/A — workflow administrativo é CRUD, não pipeline estado-orientado

## Diferenciais

- Ponto eletrônico em conformidade Portaria MTP 671/2021 (append-only via `ponto_marcacoes` no módulo Ponto irmão)
- KnowledgeBase interna (wiki por business) reaproveitável pela Jana IA
- Documents com `DocumentShare` granular (share por usuário, não broadcast)
- Mensageria interna sem dependência externa (sem Slack/Teams API)

## Gaps conhecidos

- Job `essentials:retention-purge` ainda em backlog (ADR 0105 — sinal qualificado)
- Folha automatizada (cálculo + holerite PDF) escopo futuro
- UI Inertia React parcial — boa parte ainda Blade UltimatePOS

## Referências

- [ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0
- [ADR 0094](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) Constituição v2 (princípio duro #6)
- [ADR 0155](../../memory/decisions/0155-module-grade-v3.md) Module Grade v3
- [Modules/Essentials/CHANGELOG.md](CHANGELOG.md)
