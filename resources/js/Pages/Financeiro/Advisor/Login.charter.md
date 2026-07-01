---
page-id: financeiro-advisor-login
status: draft
owner: eliana
route: /advisor/login (POST /advisor/login)
controller: Modules\Financeiro\Http\Controllers\Advisor\AdvisorAuthController@showLogin
guard: web-advisor
related_us: [US-FIN-037]
shell: none (portal contador isolado — justificadamente fora do AppShellV2)
created: 2026-05-31
page: /advisor/login
component: resources/js/Pages/Financeiro/Advisor/Login.tsx
last_validated: "2026-05-31"
parent_module: Financeiro
related_prototype: n/a (sem protótipo Cowork — tela de auth isolada fora do AppShellV2; segue DS)
tier: B
charter_version: 1
---

# Charter — Login Portal do Contador

## Mission
Dar ao contador (persona Eliana) uma porta de entrada confiável e de marca
consistente para o portal financeiro isolado, onde ele acessa os dados dos
clientes dele. Tela única de autenticação fora do AppShell principal.

## Goals
- Autenticar via guard `web-advisor` (`form.post('/advisor/login')`).
- Aparência alinhada ao Design System v4 (tokens + componentes shadcn), apesar
  de viver fora do AppShellV2.
- Persistência de sessão opcional ("Lembrar de mim").
- Feedback de erro: validação via `form.errors.{email,password}` + credencial
  inválida via `flash.error` (controller faz `back()->with('error', ...)`).

## Non-Goals
- NÃO faz cadastro/auto-registro de contador (provisionamento é manual/admin).
- NÃO oferece recuperação de senha — NÃO existe rota de reset no guard
  `web-advisor` hoje (só GET/POST `/advisor/login`, POST `/advisor/logout`,
  GET `/advisor`). Link "esqueci a senha" fica como TODO backend (ver abaixo).
- NÃO usa AppShellV2 / sidebar / PageHeader — é portal isolado por decisão.
- NÃO expõe dados de cliente nesta tela (pré-auth).

## UX targets
- Card centralizado, max-w-md, foco automático no campo e-mail.
- Botão primário roxo (token `bg-primary`) ocupa largura total.
- Copy em PT-BR, tom profissional para contador.

## Automation / Anti-hooks
- Anti-hook: não introduzir cores cruas (slate/emerald/red) nem `style={{}}` —
  somente tokens DS. (gap original do SCREEN-GRADE-BOARD).
- Anti-hook: não adicionar link de reset de senha sem a rota backend existir.
- TODO backend: criar fluxo de reset de senha do advisor (rota + controller +
  e-mail) sob o guard `web-advisor`; depois adicionar o link "Esqueceu a senha?"
  nesta tela apontando pra rota nova.
- TODO backend: throttle no POST `/advisor/login` (controller marca como
  pendente — `RateLimiter::for('advisor-login', ...)`).
