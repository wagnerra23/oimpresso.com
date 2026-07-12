---
page: /register
component: resources/js/Pages/Site/Register.tsx
related_prototype: n/a (herda PT-02 Formulário; useForm + <form> presentes — mas é auth público fora do AppShellV2)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Auth
related_adrs: [114, 101, 94]
tier: B
charter_version: 1
---

# Page Charter — /register (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `app/Http/Controllers/Auth/RegisterController@showRegistrationForm` (rota pública `GET /register`, middleware `guest`). Tela de cadastro Inertia social-first (PR3). `SiteLayout` (sem AppShellV2). PT-02 Formulário: `useForm` + `<form>`.

---

## Mission
Cadastro público de nova conta. O visitante cria conta via Google/Microsoft (social, quando configurado) ou pelo formulário nome/email/senha/confirmação (`POST /register`). Quando `allowRegistration === false`, a tela mostra estado "Cadastro indisponível" com CTA pra falar com o time.

---

## Goals — Features (faz)
- Botões de cadastro social Google/Microsoft; aviso quando nenhum provider configurado (`socialEnabled`).
- Formulário nome + email + senha (mín. 8) + confirmação (`useForm` → `POST /register`) com toggle mostrar/ocultar senha e labels flutuantes; erros de campo do Inertia.
- Gate `allowRegistration === false` → tela "Cadastro indisponível" + link `/c/contact-us`.
- Link "Já tem conta? Entrar" pra `/login`.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não autentica sessão existente (isso é `/login`).
- ❌ Não cria/escolhe `business_id`/tenant na tela — provisionamento do negócio acontece no backend após o cadastro.
- ❌ Não usa AppShellV2/sidebar (é pré-auth, `SiteLayout`).
- ❌ Não valida unicidade de email client-side (regra `unique:users` é server-side).

---

## UX targets
- p95 < 800ms (tela pública de cadastro) ; cabe em 1280px (ROTA LIVRE) ; `SiteLayout` (sem AppShellV2).

---

## Automation hooks (faz)
- Backend expõe `socialEnabled` (`config('services.{google,microsoft}.client_id')`) e `allowRegistration` (`config('constants.allow_registration')`) — a UI só reage.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não submete o formulário sozinha (só no clique/enter do usuário).
- ❌ Não loga/persiste a senha em client storage (PII — nunca em log).
- ❌ Não redireciona pra provider social sem ação explícita.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar validação server-side de email único + anti-spam/bruteforce em prod
