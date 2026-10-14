---
casos: Site/Register — cadastro público (`/register`)
irmaos: Register.charter.md (lei) · Register.tsx (código) · tests/Feature/Site/SiteAuthContratoTest.php (defesa)
tecnica: Caso de uso = o que quem quer criar conta precisa receber + critério de aceite verificável (Dado/Quando/Então)
por_que: o cadastro só abre quando o servidor permite, e as regras de senha e de e-mail único valem no servidor, não na tela
owner: wagner
last_run: "2026-10-14"
---

# Casos de Uso & Aceite — Site/Register (`/register`)

> **Fonte dos UC:** [charter](Register.charter.md) (Mission; Goals "senha (mín. 8) + confirmação",
> gate `allowRegistration === false`; Non-Goal "não valida unicidade de email client-side (regra
> `unique:users` é server-side)") + [login-spec 2026-04-26](../../../../memory/requisitos/Cms/login-spec-2026-04-26.md)
> (§7 cadastro fácil, social-first). **Não** o `.tsx`.
> **Fixture:** nenhuma credencial real — e-mails `@test.local` e senhas descartáveis.
> **Status:** ✅ passa · 🧪 teste cita o UC, veredito pendente da lane · ⬜ não verificado · ❌ quebrou.
> Teste: `tests/Feature/Site/SiteAuthContratoTest.php`, lane `verticais-pest` (MySQL).

## UC-SREG-01 · Abre sem sessão · `must`
- **Aceite:** Dado um visitante sem sessão · Quando abre `/register` · Então recebe 200 com a tela `Site/Register`.
- **Status:** 🧪

## UC-SREG-02 · Cadastro fechado · `must`
- **Fonte:** charter Goals (gate `allowRegistration === false` → "Cadastro indisponível") + Automation hook.
- **Aceite:** Dado `allow_registration` desligado no servidor · Quando a tela abre · Então ela recebe cadastro fechado.
- **Status:** 🧪

## UC-SREG-03 · Regra de senha no servidor · `must`
- **Fonte:** charter Goals ("senha (mín. 8) + confirmação").
- **Aceite:** Dado uma senha de 7 caracteres, ou uma confirmação diferente · Quando envio o cadastro · Então ele é recusado com erro na senha e nenhum usuário é criado.
- **Status:** 🧪

## UC-SREG-04 · E-mail único no servidor · `must`
- **Fonte:** charter Non-Goal ("regra `unique:users` é server-side").
- **Aceite:** Dado um e-mail que já pertence a um usuário · Quando envio o cadastro com ele · Então é recusado com erro no e-mail e nenhum segundo usuário é criado.
- **Status:** 🧪
