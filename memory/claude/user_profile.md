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

**Fluxo de desenvolvimento atual (2026-04-22):**
- **Não tem ambiente local funcional**. Herd está instalado no Windows mas nunca foi usado
- Workflow histórico: editar arquivos direto no Hostinger (produção), sem DB local, sem `.env` local
- Implicação: commit + git pull no servidor é o ciclo de deploy/teste dele. Testar localmente antes de deploy não é natural
- **Cliente real único** em produção há 3 anos; Wagner avisou que sistema está em atualização e cliente vai testar e reportar bugs — tem tolerância para disruptive changes

**Como trabalhar com ele daqui pra frente:**
- Sugerir setup Herd + DB local + .env quando começar trabalho intensivo (acelera 10×). Mas não forçar — respeitar o fluxo atual se ele preferir
- Para iterar rápido hoje: commit + push + git pull no Hostinger + `php artisan cache:clear` + `composer install`
- **Importante:** o diretório `public/build-inertia/` (assets Vite) precisa ser commitado porque Hostinger shared NÃO roda `npm run build`
