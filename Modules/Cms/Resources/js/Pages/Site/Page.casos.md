---
casos: Cms · Site/Page — página institucional (`/c/page/{page}`)
irmaos: Page.charter.md (lei) · Page.tsx (código) · Modules/Cms/Tests/Feature/SitePublicoContratoTest.php (defesa)
tecnica: Caso de uso = o que o visitante precisa ler + critério de aceite verificável (Dado/Quando/Então)
por_que: termos e política precisam abrir pela URL; página inexistente é 404 de verdade; e o HTML do CMS só sai sanitizado, porque a tela o injeta cru
owner: wagner
last_run: "2026-10-27"
---

# Casos de Uso & Aceite — Site/Page (`/c/page/{page}`)

> **Fonte dos UC:** SPEC Cms **US-CMS-005** (acceptance: rota renderiza `Site/Page`, slug derivado do
> `title`, 404 quando o slug não existe) + [charter](Page.charter.md) (Mission: `abort(404)` e HTML
> sanitizado server-side; Anti-hook: "não renderiza HTML não-sanitizado"). **Não** o `.tsx`.
> **Fora daqui:** "404 quando pertence a outro tenant" depende de US-CMS-002 (`cms_pages` ainda sem
> `business_id`) — não vira UC até a coluna existir.
> **Status:** ✅ passa · 🧪 teste cita o UC, veredito pendente da lane · ⬜ não verificado · ❌ quebrou.
> Teste: `Modules/Cms/Tests/Feature/SitePublicoContratoTest.php`, lane `verticais-pest` (MySQL).

## UC-SPAGE-01 · Abre pelo título na URL · `must`
- **Fonte:** US-CMS-005 (rota `/c/page/{slug}` renderiza `Site/Page`; slug derivado do `title`).
- **Aceite:** Dado uma página publicada com título "Termos de uso" · Quando um visitante sem sessão abre `/c/page/termos-de-uso` · Então recebe 200 com a tela `Site/Page` e o título dessa página.
- **Status:** 🧪

## UC-SPAGE-02 · Título inexistente é 404 · `must`
- **Fonte:** US-CMS-005 ("404 quando slug não existe") · charter Mission (`abort(404)`).
- **Aceite:** Dado nenhuma página com aquele título · Quando o visitante abre a URL · Então recebe 404.
- **Status:** 🧪

## UC-SPAGE-03 · Conteúdo sanitizado · `must`
- **Fonte:** charter Mission + Anti-hook ("não renderiza HTML não-sanitizado — anti-XSS").
- **Aceite:** Dado uma página cujo conteúdo traz um `<script>` junto do texto · Quando o visitante abre a página · Então o texto chega e o `<script>` não.
- **Status:** 🧪

---

## UC-SPAGE-04 · As páginas do Superadmin em /page/{slug} usam esta tela, com HTML limpo
- **Persona:** visitante anônimo abrindo Termos, Privacidade ou outra página cadastrada em Superadmin › Páginas.
- **Aceite:** Dado uma página do Superadmin com `<b>` e `<script>` no conteúdo · Quando abro `/page/{slug}` sem login · Então recebo Inertia `Site/Page` com o título, o `<b>` e sem o `<script>`.
- **Teste:** `Modules/Superadmin/Tests/Feature/SuperadminPaginaPublicaTest.php` — `UC-SPAGE-04 · visitante sem login vê a página do Superadmin na tela Site/Page, com o HTML sem script`.
- **Regressão que defende:** US-SUPER-005 (páginas do site público) e o anti-hook desta tela de só injetar HTML sanitizado; a Blade `superadmin::pages.show` imprimia o conteúdo cru.
- **Status: ⬜** — teste novo, lane `verticais-pest.yml`; espera o primeiro run.

---

## UC-SPAGE-05 · /page/{slug} inexistente responde 404
- **Persona:** visitante com link quebrado.
- **Aceite:** Quando abro `/page/{slug}` de um slug que não existe · Então recebo 404.
- **Teste:** `Modules/Superadmin/Tests/Feature/SuperadminPaginaPublicaTest.php` — `UC-SPAGE-05 · /page/{slug} que não existe responde 404`.
- **Regressão que defende:** a tela não pode renderizar vazia para slug inválido (mesmo contrato do `/c/page/{slug}`).
- **Status: ⬜** — teste novo, lane `verticais-pest.yml`; espera o primeiro run.
