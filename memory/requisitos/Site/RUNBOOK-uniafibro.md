---
owner: W
last_validated: "2026-11-15"
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

Decisão [W] 2026-11-12: a Uniafibro (União das Associações de Fibromiálgicos de Santa Catarina), que usa o
módulo Fisio, ganha uma página inicial em `uniafibro.com.br`, **hospedada no mesmo servidor e no
mesmo app Laravel do oimpresso.com** (Hostinger — INFRA.md §1), sem site separado nem WordPress.
O app Android do paciente (`com.oimpresso.fisio`) foi renomeado para "Uniafibro" no mesmo dia.

O portal do paciente **continua em `oimpresso.com/fisio/...`**: nenhuma rota do Fisio vai para o
domínio novo.

| Rota | Nome | O que faz |
|---|---|---|
| `GET uniafibro.com.br/` | `uniafibro.home` | página da associação (HTML estático `resources/uniafibro/home.html`) |
| `GET uniafibro.com.br/direitos` | `uniafibro.direitos` | índice de Seus Direitos + Guias Práticos, com busca `?q=` (§6) |
| `GET uniafibro.com.br/direitos/{slug}` | `uniafibro.direitos.pagina` | página gerada de `resources/uniafibro/direitos/{slug}.md` |
| `GET uniafibro.com.br/direitos/{slug}.pdf` | `uniafibro.direitos.pdf` | a mesma página em PDF (dompdf), `throttle:10,1` |
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

## 6. Seus Direitos e Guias Práticos (`/direitos`)

Decisão [W] 2026-11-14: conteúdo em Markdown versionado, renderizado no servidor, sem Blade, sem
JavaScript e sem banco. A home continua estática; `/direitos` é o hub, a um clique dela (botão do
topo e item destacado no menu).

- **Onde fica o texto:** `resources/uniafibro/direitos/<slug>.md`. O frontmatter obrigatório está
  em `resources/uniafibro/direitos/_LEIA-ME.md` (arquivo com `_` nunca vira página).
- **Quem renderiza:** `App\Http\Controllers\UniafibroConteudoController` +
  `App\Support\Uniafibro\Conteudo` (CommonMark com `html_input=strip`) +
  `App\Support\Uniafibro\Layout` (moldura HTML, aviso legal, menu). CSS em
  `public/uniafibro/direitos.css`.
- **Aviso legal:** `Layout::AVISO`, versão `Layout::AVISO_VERSAO`. Cada `.md` declara a versão
  com que foi revisado; o teste reprova página com versão diferente.
- **Fonte ajustável:** rádio A / A+ / A++ com `:has()` (18, 20 e 23px). Sem cookie e sem
  JavaScript, a escolha não fica salva entre páginas.
- **WhatsApp flutuante:** `config('uniafibro.whatsapp')` (`UNIAFIBRO_WHATSAPP` no `.env`). Vazio
  = botão oculto. Decisão [W] 2026-11-14: fica vazio até haver número. A home é arquivo estático e
  não lê config: quando houver número, o botão entra no `home.html` à mão.
- **Portal da Associada:** fora do menu até existir a onda 6 da ADR 0451.
- **Revisão do texto:** escrito a partir de fonte oficial (Planalto, INSS, Alesc, FCEE, TJSC),
  com link em cada ponto. Jurisprudência nova só com o [W]. Cada mudança de texto atualiza
  `updated_at`.
- **Teste:** `tests/Feature/Site/UniafibroDireitosTest.php` (lane sqlite), com páginas de exemplo
  em `tests/fixtures/uniafibro-direitos/`.
- **Smoke pós-deploy:** `curl -sI https://uniafibro.com.br/direitos | head -1` → `200`, e o mesmo
  para cada `/direitos/<slug>` e `/direitos/<slug>.pdf`.
