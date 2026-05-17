---
tela: Site/Register
controller: App\Http\Controllers\Auth\RegisterController@showRegistrationForm
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

# Screen Review — Site/Register

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✗ AUSENTE — criar `Register.charter.md` pré-req Round 2.
- Controller: `RegisterController@showRegistrationForm` — **NÃO usa `Inertia::defer`** (justificado: form register leve)
- Form signup + social auth (validado em `AuthSocialTest.php`)

**Smoke browser MCP:** **pendente** (porta entrada conversion).

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: confiança alta (form leve)
- console errors: 0 OBRIGATÓRIO (conversion page)
- 1440/1280/768/375 sem scroll: form centralizado
- Acessibilidade WCAG AA obrigatória

**Desvios potenciais:**
- Validação inline campos (CPF/CNPJ BR — máscara obrigatória)
- Senha strength meter visível
- Termos/LGPD checkbox obrigatório (compliance)
- Social auth (Google/Microsoft) bundle peso
- Multi-tenant: register CRIA novo business (provisioning) — fluxo complexo back-end

**Pest GUARD pendente:**
- Register cria business + user + role admin, validação CNPJ único, LGPD opt-in registrado, social auth callback cria provisioning

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
