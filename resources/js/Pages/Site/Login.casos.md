---
casos: Site/Login — entrada no produto (`/login`)
irmaos: Login.charter.md (lei) · Login.tsx (código) · tests/Feature/Site/SiteAuthContratoTest.php (defesa)
tecnica: Caso de uso = o que quem chega para entrar precisa receber + critério de aceite verificável (Dado/Quando/Então)
por_que: é a porta do produto; a tela só reage ao que o servidor diz (login social, cadastro aberto) e credencial errada não pode abrir sessão
owner: wagner
last_run: "2026-10-14"
---

# Casos de Uso & Aceite — Site/Login (`/login`)

> **Fonte dos UC:** [charter](Login.charter.md) (live — Mission, Goals, Automation hook "backend expõe
> `socialEnabled` e `allowRegistration`, a UI só reage", middleware `guest`) +
> [login-spec 2026-04-26](../../../../memory/requisitos/Cms/login-spec-2026-04-26.md) (social-first;
> senha é fallback) + SPEC Cms **US-CMS-008** (login social Google/Microsoft). **Não** o `.tsx`.
> **Fixture:** nenhuma credencial real — usuário inexistente e senha descartável.
> **Fora daqui:** o fluxo Socialite (redirect/callback) já é de `Modules/Cms/Tests/Feature/AuthSocialTest.php`.
> **Status:** ✅ passa · 🧪 teste cita o UC, veredito pendente da lane · ⬜ não verificado · ❌ quebrou.
> Teste: `tests/Feature/Site/SiteAuthContratoTest.php`, lane `verticais-pest` (MySQL).

## UC-SLOGIN-01 · Abre sem sessão · `must`
- **Aceite:** Dado um visitante sem sessão · Quando abre `/login` · Então recebe 200 com a tela `Site/Login`.
- **Status:** 🧪

## UC-SLOGIN-02 · Social e cadastro vêm do servidor · `must`
- **Fonte:** charter Automation hook (`socialEnabled` deriva do `client_id` de cada provider; `allowRegistration` de `constants.allow_registration`).
- **Aceite:** Dado Google configurado, Microsoft não, e cadastro fechado · Quando a tela abre · Então ela recebe Google ligado, Microsoft desligado e cadastro fechado.
- **Status:** 🧪

## UC-SLOGIN-03 · Logado não vê o login · `should`
- **Fonte:** charter (rota com middleware `guest`).
- **Aceite:** Dado um usuário já autenticado · Quando abre `/login` · Então é redirecionado e não recebe a tela.
- **Status:** 🧪

## UC-SLOGIN-04 · Credencial errada · `must`
- **Fonte:** charter Goals ("Exibe erro inicial de credencial … + erros de campo").
- **Aceite:** Dado um usuário que não existe · Quando envia usuário e senha · Então continua sem sessão e volta com erro de campo.
- **Status:** 🧪
