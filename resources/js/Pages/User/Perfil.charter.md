---
page: /perfil (canon Inertia) · /user/profile (legacy Blade · intacto)
component: resources/js/Pages/User/Perfil.tsx
owner: wagner
status: draft
last_validated: '2026-06-24'
charter_version: 1
parent_module: User / Conta
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0094-constituicao-v2-7-camadas-8-principios'
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0105-cliente-como-sinal-guiar-sem-mandar'
  - '0189-pageheader-canon-v3-1-cadastro-roxo'
tier: B
---

# Page Charter — /perfil (Meu perfil)

## Mission

Redesign Inertia da tela "Meu perfil" (conta do usuário logado), migrando o legado
`resources/views/user/profile.blade.php` (UltimatePOS HRM) pro canon UI v2. Origem:
handoff Cowork ComVis (`prototipo-ui/prototipos/perfil/`). **Tela de teste do protocolo
`aplicar-prototipo`** — sem sinal de cliente pagante (ADR 0105); justificativa = trabalho
de processo. PageHeader v3 canônico forçado (decisão Wagner 2026-06-24).

## Goals

- **PageHeader v3 canon** (ADR 0189/0190): título "Meu perfil" + suffix · subtítulo nome·email · SubNav (4 abas) · primary roxo universal "Salvar alterações".
- **4 abas** (estado client-side): Conta · Mais informações · Dados bancários · Segurança.
- **Conta**: prefixo, primeiro nome*, sobrenome, e-mail*, idioma + foto de perfil (avatar/initials fallback).
- **Mais informações**: nascimento, gênero, estado civil, grupo sanguíneo, responsável, contatos (3 telefones), redes sociais, documento, endereços, **campos personalizados** (`custom_field_1..4` com labels da empresa — paridade total c/ legado).
- **Dados bancários**: titular, conta, banco, código, agência, CPF/CNPJ (chaves reais `bank_details.*`) + nota LGPD.
- **Segurança**: alterar senha (senha atual + nova + confirmação, validação `confirmed`).
- **Rota nova** `/perfil` (Inertia) — o legado `/user/profile` (Blade) **fica intacto** (cutover decidido depois).

## Non-Goals

- ❌ Editar outro usuário — a tela só edita o `auth()->user()` (controller escopa por `session('user.id')`).
- ❌ Cutover do `/user/profile` legado neste PR (decisão Wagner: rota nova, legado intacto).
- ❌ Header de identidade `pf-head` do protótipo (avatar+chip no topo) — cedeu ao PageHeader canon (decisão Wagner).
- ❌ Gerenciar permissões/papéis do usuário (fora de escopo — é Equipe/Admin).

## UX Targets

- Viewport 1280×1024 e 1440 — cabe sem scroll horizontal (AppShellV2 sidebar 240 + main).
- Troca de aba instantânea (estado client-side, sem round-trip).
- Salvar com feedback (`toast` sonner) + `useForm.isDirty` controla o primary ("Salvo" quando limpo).
- Foto: preview imediato via `URL.createObjectURL` antes do upload.

## Automation Anti-hooks

- ❌ Não acessa/edita usuário de outro `business_id` nem `user_id` arbitrário (Tier 0 IRREVOGÁVEL — [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
- ❌ Não loga PII (`bank_details`, CPF, endereços) — apenas persiste; `bank_details` segue como JSON (paridade legado).
- ❌ Não troca senha sem checar a atual (`Hash::check` server-side; erro vira `ValidationException` no campo).
- ❌ Não importa `@/Components/shared/PageHeader` (deprecado) — usa o canon `@/Components/PageHeader` (pageheader-gate).

## Backend

- `App\Http\Controllers\UserController::perfil()` (Inertia render) · `perfilUpdate()` · `perfilPassword()`.
- Rotas: `GET /perfil` (`perfil`) · `POST /perfil/update` (`perfil.update`) · `POST /perfil/password` (`perfil.password`).
- Reúsa a semântica de `updateProfile`/`updatePassword` legados (mesmo conjunto de campos, `Media::uploadMedia('profile_photo')`).

## Refs

- Protótipo Cowork: `prototipo-ui/prototipos/perfil/` (perfil-page.jsx/css + perfil.png + SOURCE.md)
- Legado: `resources/views/user/profile.blade.php` + `user/form.blade.php` (chaves `bank_details.*`)
- ADR 0104 (MWART) · ADR 0189/0190 (PageHeader canon + primary roxo) · ADR 0093 (Tier 0) · ADR 0105 (cliente-sinal)
- Teste: `tests/Feature/Perfil/PerfilSmokeTest.php`
