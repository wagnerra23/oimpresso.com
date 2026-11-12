---
owner: W
last_validated: "2026-11-12"
slug: site-runbook-uniafibro
title: "Site — Runbook da página da Uniafibro (uniafibro.com.br) servida pelo app do oimpresso"
type: runbook
module: Site
status: ativo
date: 2026-11-12
related_adrs:
  - 0062-separacao-runtime-hostinger-ct100
---

# RUNBOOK — página da Uniafibro (`uniafibro.com.br`)

## 1. Por que existe

Decisão [W] 2026-11-12: a Uniafibro (União das Associações de Fibromialgia de SC), que usa o
módulo Fisio, ganha uma página inicial em `uniafibro.com.br`, **hospedada no mesmo servidor e no
mesmo app Laravel do oimpresso.com** (Hostinger — INFRA.md §1), sem site separado nem WordPress.
O app Android do paciente (`com.oimpresso.fisio`) foi renomeado para "Uniafibro" no mesmo dia.

O portal do paciente **continua em `oimpresso.com/fisio/...`**: nenhuma rota do Fisio vai para o
domínio novo.

| Rota | Nome | O que faz |
|---|---|---|
| `GET uniafibro.com.br/` | `uniafibro.home` | página da associação (HTML estático `resources/uniafibro/home.html`) |
| `GET uniafibro.com.br/{caminho}` | `uniafibro.outros` | 302 para `https://uniafibro.com.br/` (não deixa abrir tela do ERP) |
| `GET www.uniafibro.com.br/{caminho?}` | `uniafibro.www` | 301 para `https://uniafibro.com.br/` |

As rotas ficam em `routes/web.php` **antes** do `/` sem domínio: o roteador devolve a primeira
rota registrada que casa.

## 2. Por que HTML estático (nem Inertia, nem Blade)

O `AppServiceProvider` fixa a raiz das URLs em `config('app.url')` (oimpresso.com). Numa página
Inertia servida em outro domínio, os scripts do Vite viriam de `https://oimpresso.com/build/...`,
outra origem, e o navegador bloquearia por CORS — é o mesmo motivo do 301 de `www.oimpresso.com`
no `.htaccess` raiz. Além disso, o layout Inertia é o shell do oimpresso (título, PWA), não a marca
da associação. A página é HTML + CSS inline, **sem JavaScript, sem analytics**, e o logo é servido
por caminho relativo (`/uniafibro/logo.png`), não por `asset()`. Não é Blade para não somar rota
nova na catraca da migração Blade→React (ADR 0277): o conteúdo não tem nada dinâmico.

## 3. O que o [W] precisa preencher

No próprio `resources/uniafibro/home.html`, marcado com `PREENCHER [W]`:

- **Quem somos** — o texto atual é provisório e neutro. Não acrescentar CNPJ, endereço, diretoria
  ou números sem confirmação.
- **Contato** — trocar o parágrafo marcado na seção Contato (hoje: "serão publicados em breve").
- **App** — está em teste interno na Play; o link entra só quando o app ficar público.

## 4. Hospedagem e DNS

- **hPanel**: `uniafibro.com.br` adicionado como domínio que aponta para o mesmo `public_html`
  do oimpresso.com (alias/parked), com SSL emitido pela Hostinger.
- **Registro.br** (DNS automático): `A` 148.135.133.115 e `AAAA` 2a02:4780:b:1918:0:3609:6c56:2
  para `@` e `www` — ou o que o hPanel indicar no momento.

## 5. Como validar

- Pest: `tests/Feature/Site/UniafibroSiteTest.php` (lane sqlite per-PR, `.github/ci-sqlite-pest.list`).
- Smoke pós-deploy:
  - `curl -sI https://uniafibro.com.br/ | head -1` → `200`
  - `curl -sI https://www.uniafibro.com.br/ | grep -i '^location'` → `https://uniafibro.com.br/`
  - `curl -sI https://oimpresso.com/login | head -1` → igual a antes (regressão do domínio principal).
