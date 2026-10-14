---
casos: Cms · Site/Page — página institucional (`/c/page/{page}`)
irmaos: Page.charter.md (lei) · Page.tsx (código) · Modules/Cms/Tests/Feature/SitePublicoContratoTest.php (defesa)
tecnica: Caso de uso = o que o visitante precisa ler + critério de aceite verificável (Dado/Quando/Então)
por_que: termos e política precisam abrir pela URL; página inexistente é 404 de verdade; e o HTML do CMS só sai sanitizado, porque a tela o injeta cru
owner: wagner
last_run: "2026-10-14"
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
