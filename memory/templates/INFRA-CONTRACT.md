# Infra Contract — template canônico

> **Pra que serve:** definir critérios de "done" **falsificáveis** ANTES de codar mudanças em runtime crítico (`.htaccess`, middleware, routing, Kernel, ServiceProviders, bootstrap). Catalogado após cascata de 3 PRs em 17/mai/2026 ([#1024](https://github.com/wagnerra23/oimpresso.com/pull/1024) → [#1026](https://github.com/wagnerra23/oimpresso.com/pull/1026) → [#1028](https://github.com/wagnerra23/oimpresso.com/pull/1028)) onde Claude declarou "funcionando" sem `curl -sv` em prod.
>
> **Quando obrigatório:** PR toca qualquer arquivo em:
> - `.htaccess` (raiz ou `public/`)
> - `app/Http/Middleware/**/*.php`
> - `app/Http/Kernel.php`
> - `routes/web.php` · `routes/api.php` · `routes/*.php`
> - `app/Providers/*ServiceProvider.php`
> - `bootstrap/app.php`
>
> CI workflow `infra-contract-required.yml` bloqueia merge se body do PR não contém seção `## Infra Contract` OU `## Validação prod` OU comentário escape `<!-- evidence-override: <razão> -->`.
>
> **Origem do pattern:** Anthropic Harness Design 2026 (Default-FAIL + Evidence Opening) + Sprint Contract upfront pattern. Ver [memory/sessions/2026-05-17-arte-evidencia-llm-agents.md](../sessions/2026-05-17-arte-evidencia-llm-agents.md) pra pesquisa estado-da-arte completa.

---

## Copie a seção abaixo pro PR body antes de commitar

```markdown
## Infra Contract

### 1. Happy path — comando + output esperado LITERAL

Comando que valida o fix em prod (não local). Status code e Location literais.

\`\`\`bash
$ curl -sv https://oimpresso.com/<rota-afetada> 2>&1 | grep '^< HTTP\|^< Location'
< HTTP/1.1 <STATUS-ESPERADO>
< Location: <LOCATION-ESPERADA>
\`\`\`

### 2. Regression adjacent — rotas similares que NÃO devem mudar

Listar 2-3 rotas adjacentes que servem como controle (sem regressão).

\`\`\`bash
$ curl -sv https://oimpresso.com/<rota-canonica-similar> 2>&1 | grep '^< HTTP'
< HTTP/1.1 <STATUS-ESPERADO-SEM-MUDANCA>

$ curl -sv https://oimpresso.com/<outra-rota-grupo-mesmo> 2>&1 | grep '^< HTTP'
< HTTP/1.1 <STATUS-ESPERADO-SEM-MUDANCA>
\`\`\`

### 3. Environment delta — onde Pest local NÃO prova prod

Diferenças conhecidas Hostinger Cloud Startup (LiteSpeed) vs local (Herd/nginx):

- [ ] Pest passa local — mas isso prova prod? (resposta esperada: NÃO se mexe em `.htaccess`/middleware/SCRIPT_NAME-dependent)
- [ ] `Request::create()` em Pest reproduz `SCRIPT_NAME` real? (não)
- [ ] OPcache pode cachear bytecode antigo? (sim — exigir `optimize:clear` + `opcache_reset` pós-deploy)
- [ ] LSCache (LiteSpeed) pode servir HTML stale? (sim em prod biz>1)
- [ ] `.htaccess` regex `%{THE_REQUEST}` funciona em LiteSpeed? (parcial — flaky)

Declarar explicitamente: **"Pest local + smoke Herd NÃO provam prod. Validação em prod via curl -sv obrigatória pós-deploy."**

### 4. Smoke prod pós-deploy (preencher após merge + git pull Hostinger)

\`\`\`bash
# Output real colado aqui, com timestamp:
$ curl -sv https://oimpresso.com/<rota> 2>&1 | grep '^< HTTP\|^< Location'
# (timestamp + colar saída real aqui)
\`\`\`

| Caso | Esperado | Observado | OK |
|---|---|---|---|
| Happy path | `301 → /admin` | `301 → /admin` | ✅ |
| Adjacent 1 | `302 → /login` | `302 → /login` | ✅ |
| Adjacent 2 | `200` | `200` | ✅ |
```

---

## Anti-patterns banidos

- ❌ **"Smoke OK"** ou **"✅ funcionando"** sem cole de `curl -sv` literal — banido pela skill `smoke-prod-evidence` (Tier B) + hook `block-claim-without-evidence.ps1`
- ❌ **`curl -L | grep "redirect=%{num_redirects}"`** — mostra consequência compatível, não evidência inequívoca. Use `curl -sv | grep '^< HTTP'` literal
- ❌ **"Pest verde + smoke local"** como prova de prod — não cobre SCRIPT_NAME, OPcache, LSCache, LiteSpeed quirks
- ❌ **Cobertura de 1 caso só** — sempre incluir 2-3 regression adjacent

---

## Escape valves legítimas

- **Hotfix sob pressão**: adicione `<!-- evidence-override: <razão concreta> -->` no body do PR. Wagner audita uso em [governance:detect-drift](../requisitos/Infra/RUNBOOK-governance-detect-drift.md) cron.
- **Mudança que não tem rota HTTP testável** (ex: comando artisan apenas): substitua seção `## Infra Contract` por `## Artisan Contract` com output literal de `php artisan <comando> --dry-run` esperado.

---

## Refs

- [memory/sessions/2026-05-17-arte-evidencia-llm-agents.md](../sessions/2026-05-17-arte-evidencia-llm-agents.md) — pesquisa estado-da-arte que motivou (10 WebSearch)
- [.claude/skills/smoke-prod-evidence/SKILL.md](../../.claude/skills/smoke-prod-evidence/SKILL.md) — skill Tier B pareada
- [.claude/hooks/block-claim-without-evidence.ps1](../../.claude/hooks/block-claim-without-evidence.ps1) — hook PreToolUse bloqueador (camada B)
- [.github/workflows/infra-contract-required.yml](../../.github/workflows/infra-contract-required.yml) — CI gate (camada A)
- [memory/proibicoes.md](../proibicoes.md) §"Claim sem evidência" — regra Tier 0
- [memory/decisions/0147-cascade-review-defesa-drift-time-mcp.md](../decisions/0147-cascade-review-defesa-drift-time-mcp.md) — 5 camadas Governance v3 (esta é a 6ª)
