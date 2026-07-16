---
page: /login
component: resources/js/Pages/Site/Login.tsx
related_prototype: n/a (herda PT-02 Formulário; useForm + <form> presentes — mas é auth público fora do AppShellV2)
owner: wagner
status: live
last_validated: "2026-07-12"
parent_module: Auth
related_adrs: [114, 101, 94]
tier: B
charter_version: 1
---

# Page Charter — /login (LIVE)

> **Status:** **live** — promovido em 2026-07-12. Wagner aprovou Non-Goals + Anti-hooks ("Promova") **e** há sinal de prod real: `governance/route-hits.json` → `Site/Login` = **28 hits** (última 2026-07-11, middleware `ContadorHitsRota`). `live = evidência` (gate `charter-live-signal`).
>
> Backend: `app/Http/Controllers/Auth/LoginController@showLoginForm` (rota pública `GET /login`, middleware `guest`). Tela de login redesenhada em Inertia com social-first (Google/Microsoft). `SiteLayout` (sem AppShellV2). PT-02 Formulário: `useForm` + `<form>`.

---

## Mission
Porta de autenticação do produto. O usuário entra com Google/Microsoft (login social, quando configurado) ou usuário/senha. Erros de credencial vêm por flash (`status.msg`) ou `errors` do Inertia; há link pra recuperar senha (`/password/reset`) e pra criar conta (`/register`, condicionado a `allowRegistration`).

---

## Goals — Features (faz)
- Botões de login social Google (`/auth/google/redirect`) e Microsoft (`/auth/microsoft/redirect`); aviso quando nenhum provider está configurado (`socialEnabled`).
- Formulário usuário/email + senha (`useForm` → `POST /login`) com toggle mostrar/ocultar senha, "Lembrar de mim" e labels flutuantes.
- Exibe erro inicial de credencial via flash `success===0` + erros de campo do Inertia.
- Link condicional "Criar conta" quando `allowRegistration !== false`.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não cadastra usuário (isso é `/register`) nem redefine senha (isso é `/password/reset`).
- ❌ Não usa AppShellV2/sidebar (é pré-auth, `SiteLayout`).
- ❌ Não escolhe/escopa `business_id` na tela — o tenant é resolvido no backend após autenticar.
- ❌ Não faz auto-login social sem clique explícito no provider.

---

## UX targets
- p95 < 800ms (tela pública de entrada) ; cabe em 1280px (ROTA LIVRE) ; `SiteLayout` (sem AppShellV2).

---

## Automation hooks (faz)
- Backend expõe `socialEnabled` (deriva de `config('services.{google,microsoft}.client_id')`) e `allowRegistration` (`config('constants.allow_registration')`) — a UI só reage.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não submete o formulário sozinha (só no clique/enter do usuário).
- ❌ Não persiste senha em client storage nem loga credencial (PII — nunca em log).
- ❌ Não redireciona pra provider social sem ação do usuário.

---

## Follow-up (pós-live)
- [x] Wagner aprovou Non-Goals + Anti-hooks (2026-07-12, "Promova") + sinal de prod (route-hits 28)
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar fluxo de erro de credencial + rate-limit/brute-force em prod
