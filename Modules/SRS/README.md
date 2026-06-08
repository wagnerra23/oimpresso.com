# Modules/SRS — System Requirements Spec

Ferramenta interna Wagner. Cofre de documentação viva — ingestão de evidências (screenshots, chat logs, erros) → IA classifica → vira requisitos estruturados em `memory/requisitos/`.

Ex-`MemCofre`. URL/permissions/config keys mantêm prefixo legacy `memcofre.*` por compat ([ADR rename Fase 3.7 PR-2](../../memory/decisions/)).

## Estado atual

- ✅ 7 entities: `DocSource`, `DocPage`, `DocRequirement`, `DocEvidence`, `DocChatMessage`, `DocLink`, `DocValidationRun`
- ✅ Multi-tenant Tier 0: `HasBusinessScope` em todas entities (Wave 12)
- ✅ LGPD audit trail Spatie + retention windows (Wave 12 + Wave 18 RETRY)
- ✅ Services: `RequirementsFileReader`, `MemoryReader`, `DocValidator`, `ChatAssistant`, `ModuleAuditor`, `DocRetentionCleaner` (Wave 18 RETRY)
- ✅ FSM N/A declarado (Wave 18 RETRY)
- 🟡 Sobreposição com MCP server canon — Wagner migrando uso pra MCP server via webhook GitHub

## Docs canônicas

- [BRIEFING](../../memory/requisitos/SRS/BRIEFING.md) — estado consolidado
- [SPEC](../../memory/requisitos/SRS/SPEC.md) — contrato user stories
- [CHANGELOG](CHANGELOG.md) — append-only por PR
- [Config/retention.php](Config/retention.php) — janelas LGPD declarativas

## Permissions

Prefixo legacy `memcofre.*` preservado:
- `memcofre.access` · `memcofre.write` · `memcofre.ingest` · `memcofre.chat`

## Pré-flight obrigatório antes de editar

1. **Avaliar se feature cabe melhor no MCP server canon** (sucessor natural)
2. Se sim, propor ADR de deprecação SRS antes de continuar
3. Se não, ler [BRIEFING](../../memory/requisitos/SRS/BRIEFING.md) + manter compat `memcofre.*` intacta
4. Skill Tier A `multi-tenant-patterns` — toda Eloquent Model usa `HasBusinessScope`
5. Tests biz=1 OR biz=99 — NUNCA biz=4

## Tests local

```bash
php artisan test --filter=Modules\\\\SRS
```

## Não inventar

- ⛔ Features novas sem avaliar MCP server primeiro
- ⛔ Quebrar compat `memcofre.*` (URLs, permissions, env keys)
- ⛔ Retention sem entry em `Config/retention.php`
