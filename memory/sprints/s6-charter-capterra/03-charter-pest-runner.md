# 03 — Pest GUARD test runner

> **Spec do mecanismo que transforma Non-Goals e Anti-hooks de cada charter em Pest tests automáticos.**
> Vive em `tests/Charter/CharterGuardTest.php` (auto-discovery). Roda no GitHub Action `charter-gate.yml` ([05](05-ci-gate-charter.md)).

---

## Discovery

Test base **descobre** todos os charters em runtime:

```php
// tests/Charter/CharterGuardTest.php
beforeEach(function () {
    $this->charters = collect(
        glob(base_path('resources/js/Pages/**/*.charter.md'), GLOB_BRACE)
    )->map(fn ($p) => parseCharter($p));
});
```

`parseCharter()` é helper compartilhado que retorna struct igual à output de `charter-fetch` ([02](02-charter-fetch-tool.md)).

---

## Tipos de assertion (3 tiers)

### Tier 1 — Estrutura (sempre roda, sem custo)

Pra cada charter:
- `it_has_required_frontmatter` — owner, status, last_validated, tier presentes
- `it_has_seven_sections` — 7 H2 obrigatórias
- `it_non_goals_have_emoji_prefix` — todo Non-Goal começa com ❌
- `it_anti_hooks_have_emoji_prefix` — todo Anti-hook começa com ❌
- `it_charter_not_stale_for_tier` — last_validated dentro do limite por tier

### Tier 2 — Comportamental (roda em CI, custa testes integrados)

Por charter, gerado dinamicamente a partir de `Métricas vivas`:
```php
foreach ($charter->metrics as $metricRef) {
    test("[{$charter->page}] {$metricRef}", function () use ($metricRef) {
        // Resolve ClasseTest::método e roda
    });
}
```

A Pest test referenciada vive no módulo (`Modules/<Mod>/Tests/Charters/`).

### Tier 3 — Runtime (roda só com flag `--charter-runtime`, em prod canary)

Mede em prod:
- Token economy (M1) por sessão tocando a tela
- Goal drift rate (M4) — sessão excedeu Non-Goals?

Custo médio: 1 query Meilisearch + 1 agregação `mcp_audit_log` por charter. Roda no cron `charter:health` ([F2](README.md#F2)).

---

## Formato de saída (PR comment)

Quando GUARD falha:

```
🛡️ Charter GUARD failed for /repair/dashboard

❌ Tier 1 — `it_non_goals_have_emoji_prefix`:
   Non-Goal "CRUD de OS" sem prefixo ❌ (linha 42)

✅ Tier 2 — 6/6 metrics passing
✅ Tier 3 — runtime within ratchet

Owner: @wagner — please review or update charter.
```

---

## Modo soft vs hard

| Modo | F1 default | Comportamento |
|---|---|---|
| **soft** (warn-only) | ✅ sim | Comenta no PR mas exit 0 — não bloqueia merge |
| **hard** (block) | F2+ | Tier A errors → exit 1 → CI red → merge bloqueado |

F1 entrega em soft pra coletar baseline 7d. F2 promove pra hard quando ratchet baseline aceito.

---

## Critério de aceite F1

- [ ] `tests/Charter/CharterGuardTest.php` discoverable
- [ ] Tier 1 (estrutura) roda em ≤2s pra 5 charters Tier A
- [ ] Tier 2 (metrics ref) roda quando classes existem; skip elegante quando não
- [ ] Tier 3 fica desligado por default (`--charter-runtime` opcional)
- [ ] PR comment formatado corretamente quando GUARD falha
