---
name: User profile — Wagner
description: Quem é o usuário, papel, controle sobre o servidor e nível técnico
type: user
originSessionId: 3f332cf1-9ebd-4bb2-8b41-a6a1fd23c222
---
**Usuário:** Wagner (Office Impresso, email wagnerra@gmail.com)

**Papel:** Dono/operador do site oimpresso.com, toma as decisões técnicas e administra o servidor Hostinger.

**Controle do servidor:** Wagner controla a versão do PHP na Hostinger (não a Eliana — que aparece no handoff do projeto como pessoa separada, possivelmente admin secundária ou cliente piloto). Quando precisar upgrade do PHP no servidor (ex.: para Laravel Boost/AI), basta pedir.

**Alvo de stack definido por Wagner:**
- **Laravel 13** escalável como alvo final
- **Laravel Boost** (MCP/AI-first Laravel tooling)
- **IA no core** do projeto, não como enfeite
- **PHP 8.4+** mínimo (já ciente do requisito)
- Stack UI: **React + shadcn/ui + Tailwind 4 + Inertia** — alinha com Starter Kit oficial Laravel 12+

**Estilo de comunicação:**
- Responde em português (PT-BR), curto e direto
- Pede explicação quando o jargão técnico fica denso (ex.: pediu "explique" quando mencionei Boost+Inertia+shadcn de uma vez). Preferir analogias e linguagem simples antes de aprofundar
- Decide rápido: quando dá opção A/B/C com recomendação clara, escolhe e fala "pode fazer"

**Fluxo de desenvolvimento (atualizado 2026-04-23):**
- **Setup local agora funcional**: Herd + MySQL + worktrees git (ver `reference_local_dev_setup.md`)
- Workflow normal: testar em `https://oimpresso.test` (Herd) antes de push
- **Usa Cursor também** como IDE/IA paralelo (ver `reference_cursor_collaboration.md`) — às vezes Cursor deixa trabalho uncommitted; conferir antes de começar
- **Cliente real único** em produção há 3 anos; tem tolerância para disruptive changes, cliente testa e reporta

**Stack confirmada pós-upgrades de 2026-04-23:**
- **Laravel 13.6** (upgrade em cascata 9→10→11→12→13 no mesmo dia)
- Inertia v2, Passport v13, Tinker v3, Pest v4, PHPUnit v12
- `openai-php/laravel` REMOVIDO — vai usar **Vizra ADK + Prisma** como motor de IA
- Form:: migrado pra shim sobre spatie/laravel-html (laravelcollective/html removido)
- knox/pesapal inlined em `app/Vendor/Pesapal` (sem versão L13 upstream)
- `arcanedev/log-viewer` e `barryvdh/laravel-debugbar` removidos (sem compat L13)

**Como trabalhar com ele:**
- Decide rápido quando apresento A/B/C com recomendação — só fala "pode fazer" e confia
- **Deployar pro Hostinger**: `public/build-inertia/` (assets Vite) precisa ser commitado, Hostinger shared NÃO roda `npm run build`
