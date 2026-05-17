---
tela: Site/Login
controller: App\Http\Controllers\Auth\LoginController@showLoginForm
charter: null
current_round: 1
status: awaiting-smoke-browser
created_at: 2026-05-17
ux_targets:
  first_paint_ms: 800
  fcp_ms: 1200
  no_console_errors: true
  responsive_1440_no_scroll_horizontal: true
  responsive_1280_no_scroll_horizontal: true
---

# Screen Review — Site/Login

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✗ AUSENTE — criar `Login.charter.md` pré-req Round 2.
- Controller: `LoginController@showLoginForm` — **NÃO usa `Inertia::defer`** (justificado: form login é leve, defer não aplicável)
- Form login + social auth (validado em `Modules/Cms/Tests/Feature/AuthSocialTest.php`)

**Smoke browser MCP:** **pendente** (página pública gateway).

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: confiança alta (form leve)
- console errors: 0 OBRIGATÓRIO (porta entrada do produto)
- 1440/1280/768/375 sem scroll: form centralizado sempre cabe
- Acessibilidade WCAG AA: form precisa labels + aria

**Desvios potenciais:**
- Social auth (Google/Microsoft) — providers carregam scripts externos (bundle weight)
- Esqueci senha link visível
- 2FA flow se ativado (estado intermediário)
- Multi-tenant: SEM business_id em login (login determina business via session pós-auth)

**Pest GUARD pendente:**
- Login válido cria sessão, inválido mostra erro, rate limit brute-force, social auth callback OK

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
