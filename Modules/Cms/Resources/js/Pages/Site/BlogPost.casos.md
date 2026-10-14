---
casos: Cms · Site/BlogPost — leitura de um post (`/c/blog/{slug}-{id}`)
irmaos: BlogPost.charter.md (lei) · BlogPost.tsx (código) · Modules/Cms/Tests/Feature/SitePublicoContratoTest.php (defesa)
tecnica: Caso de uso = o que o leitor precisa receber + critério de aceite verificável (Dado/Quando/Então)
por_que: o id da URL decide o post; id inexistente ou post desligado é 404 antes de renderizar, e o corpo só sai sanitizado
owner: wagner
last_run: "2026-10-14"
---

# Casos de Uso & Aceite — Site/BlogPost (`/c/blog/{slug}-{id}`)

> **Fonte dos UC:** [charter](BlogPost.charter.md) (Mission: id resolvido eager com `findOrFail` "pra o
> 404 acontecer antes de renderizar", HTML sanitizado server-side; Anti-hook "não renderiza HTML
> não-sanitizado") + SPEC Cms **US-CMS-005** (blog posts por slug, 404 quando não existe). **Não** o `.tsx`.
> **Status:** ✅ passa · 🧪 teste cita o UC, veredito pendente da lane · ⬜ não verificado · ❌ quebrou.
> Teste: `Modules/Cms/Tests/Feature/SitePublicoContratoTest.php`, lane `verticais-pest` (MySQL).

## Rastreabilidade

| UC | Caso de uso | Prio | Status |
|----|-------------|------|--------|
| UC-SBPOST-01 | Abre o post pela URL, sem login | must | 🧪 |
| UC-SBPOST-02 | Id inexistente ou post desligado dá 404 | must | 🧪 |
| UC-SBPOST-03 | O corpo sai sanitizado | must | 🧪 |

## UC-SBPOST-01 · Abre pela URL · `must`
- **Aceite:** Dado um post publicado · Quando um visitante sem sessão abre `/c/blog/<slug>-<id>` · Então recebe 200 com a tela `Site/BlogPost` e o título desse post.
- **Status:** 🧪

## UC-SBPOST-02 · 404 para id inexistente ou desligado · `must`
- **Fonte:** charter Mission (`findOrFail`) · US-CMS-005 ("404 quando slug não existe").
- **Aceite:** Dado um id que não existe · Quando o visitante abre a URL · Então recebe 404 · E dado um post desligado · Quando abre a URL dele · Então também recebe 404.
- **Status:** 🧪

## UC-SBPOST-03 · Corpo sanitizado · `must`
- **Fonte:** charter Mission + Anti-hook (anti-XSS).
- **Aceite:** Dado um post cujo corpo traz um `<script>` junto do texto · Quando o visitante abre o post · Então o texto chega e o `<script>` não.
- **Status:** 🧪
