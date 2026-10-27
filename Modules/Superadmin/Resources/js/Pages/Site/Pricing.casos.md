---
casos: Superadmin · Site/Pricing — vitrine pública de planos (`/pricing`)
irmaos: Pricing.charter.md (lei) · Pricing.tsx (código) · Modules/Superadmin/Tests/Feature/SitePricingContratoTest.php (defesa)
tecnica: Caso de uso = o que o prospect sem conta precisa ver + critério de aceite verificável (Dado/Quando/Então)
por_que: é a vitrine pública e lê o mesmo catálogo da tela de Pacotes; pacote privado ou desativado não pode vazar para o site
owner: wagner
last_run: "2026-10-27"
---

# Casos de Uso & Aceite — Site/Pricing (`/pricing`)

> **Fonte dos UC:** SPEC Cms **US-CMS-001** (acceptance: "Pricing público `/pricing` renderiza
> `Site/Pricing` com `packages` + `permissions`") + SPEC Superadmin **US-SUPER-009** + [charter](Pricing.charter.md)
> (Non-Goal: não exige login; Automation hook: `Package::listPackages(true)`) +
> [RUNBOOK-pacotes](../../../../../../memory/requisitos/Superadmin/RUNBOOK-pacotes.md) ("Pacote privado não
> vaza" — R7 do F1). **Não** o `.tsx`.
> **Fora daqui:** o toggle mensal/anual é estado client-side (charter) — sem contrato de servidor.
> **Status:** ✅ passa · 🧪 teste cita o UC, veredito pendente da lane · ⬜ não verificado · ❌ quebrou.
> Teste: `Modules/Superadmin/Tests/Feature/SitePricingContratoTest.php`, lane `verticais-pest` (MySQL).

## UC-SPRIC-01 · Abre sem login · `must`
- **Fonte:** US-CMS-001 · US-SUPER-009 · charter Non-Goal "não exige login".
- **Aceite:** Dado um visitante sem sessão · Quando abre `/pricing` · Então recebe 200 com a tela `Site/Pricing`, a lista de planos e o rótulo das permissões.
- **Status:** 🧪

## UC-SPRIC-02 · Privado e desativado não vazam · `must`
- **Fonte:** RUNBOOK-pacotes ("Pacote privado não vaza … a de lá exclui privados") · charter (`listPackages(true)`).
- **Aceite:** Dado um pacote ativo e público, um ativo e privado, e um desativado · Quando o visitante abre `/pricing` · Então vê só o ativo e público.
- **Status:** 🧪

---

## UC-SPRIC-03 · O fallback Blade /pricing/old saiu
- **Persona:** visitante anônimo com link antigo para `/pricing/old`.
- **Aceite:** Quando peço `/pricing/old` · Então recebo 404; e `/pricing` segue abrindo.
- **Teste:** `Modules/Superadmin/Tests/Feature/SuperadminRotasMortasTest.php` — `UC-SPRIC-03 · o fallback Blade /pricing/old saiu; /pricing segue`.
- **Regressão que defende:** US-SUPER-009: o pricing é só a tela React; dois caminhos para a mesma página voltariam a divergir.
- **Status: ⬜** — teste novo, lane `verticais-pest.yml`; espera o primeiro run.
