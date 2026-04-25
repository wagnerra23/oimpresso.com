---
name: Setup local dev — Herd + MySQL + worktrees
description: Como rodar o projeto localmente (onde é o quê) pra testar/debugar antes de subir pra Hostinger
type: reference
originSessionId: 35b2b09f-6215-4da4-babc-740643587a77
---
Workflow local iniciado em 2026-04-22.

## Herd (web server + PHP)

- Herd bin: `/c/Users/wagne/.config/herd/bin/`
- PHP atual: **8.4.20** (`herd/bin/php.bat` ou `php84.bat`)
- Composer: `herd/bin/composer.bat` (v2.9.7 após Cursor session-12)
- Herd serve `https://oimpresso.test` a partir de `D:/oimpresso.com` (worktree principal)

## DB local

- MySQL em 127.0.0.1:3306
- Database: `oimpresso` (nome fixo no .env)
- User: `root` / sem senha
- Seed do UltimatePOS já rodado localmente — tests podem assumir dados reais (padrão do PontoTestCase/EssentialsTestCase: `markTestSkipped` se não encontrar business/user)

## Credenciais de login

- `DEV_LOGIN_USERNAME=WR23` / `DEV_LOGIN_PASSWORD=Wscrct*2312` no .env

## Worktrees git

- `D:/oimpresso.com` — principal, servido pelo Herd (branch atual: `chore/upgrade-laravel-11` pós-2026-04-23)
- `D:/oimpresso.com/.claude/worktrees/*` — worktrees pra sessões Claude Code isoladas

## Limitações conhecidas

- `artisan serve` em porta alternativa (8080) funciona, mas Chrome HSTS-cacha 127.0.0.1 como HTTPS — usar `localhost:8080` ou ajustar `APP_URL` no `.env`
- `public/assets/css/color/` pode não existir em worktree novo (pré-existente do UltimatePOS); criar diretório vazio se `scandir()` crashar em /home

## Padrão pra testar mudanças Blade/view

Sempre testar no Herd `oimpresso.test` (TLS + assets compilados), não em `artisan serve` puro. Motivo: Vite manifest + session + HTTPS config são diferentes. Se for necessário isolar, `herd link` um worktree-específico (não padrão atual).
