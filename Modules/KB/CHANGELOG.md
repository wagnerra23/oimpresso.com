# CHANGELOG — Modules/KB

Append-only. Cada PR mergeado que toca `Modules/KB/` deve adicionar 1 linha na entrada do Wave/data.

## Wave 18 RETRY — 2026-05-16 (governança meta-97)

- `module.json`: declarado `fsm_n_a: true` + razão — KB é read-mostly, sem ciclo transacional (vs Sells/Repair ADR 0143). Fecha gap fsm-coverage falso-positivo.
- `CHANGELOG.md` (este arquivo) + `README.md` criados — preencher D3 "docs internas" boost.
- `Tests/Feature/MultiTenantTraitTest.php`: datasets cross-tenant ampliados (KbNode/KbComment/KbFavorite × biz 1↔99).
- `Tests/Unit/KbArticleServiceUnitTest.php` criado — extract Service paginate/show coverage isolado.

## Wave 17 — 2026-05-16 (D4 service extraction)

- `Services/KbArticleService.php`: extraído de `KbNodeController` (thin extraction sem regressão de payload).

## Wave 11 — 2026-05-15 (D2 saturation)

- `Entities/Concerns/BelongsToBusinessTrait.php`: trait auto-fill + global scope (defense-in-depth nível Model).
- `Tests/Feature/MultiTenantTraitTest.php`: 10+ cenários cross-tenant biz=1 vs biz=99.
- `Tests/Feature/LgpdComplianceTest.php`: audit trail Spatie + retention windows.
- `Tests/Feature/GovernanceInvariantsTest.php`: bridge invariants (is_editable=false ⇒ body_blocks IS NULL).

## ONDA 1 — 2026-05-15 (skeleton inicial)

- Skeleton 12 entities + 12 migrations + 6 controllers + permissions.
- Charter `Pages/kb/Index.charter.md` + Sync Cowork v5 commitado em `prototipo-ui/prototipos/kb/`.

---

**Como atualizar este CHANGELOG:** ao terminar uma feature PR-merge, adicionar 1 entrada datada no TOPO da seção Wave ativa. Skill `brief-update` (Tier B) auto-trigger lembra. NUNCA editar entradas antigas (append-only — drift = vetor #1 incidente catalogado).
