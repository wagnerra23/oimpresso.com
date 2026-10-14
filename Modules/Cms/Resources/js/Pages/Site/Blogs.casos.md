---
casos: Cms · Site/Blogs — índice público do blog (`/c/blogs`)
irmaos: Blogs.charter.md (lei) · Blogs.tsx (código) · Modules/Cms/Tests/Feature/SitePublicoContratoTest.php (defesa)
tecnica: Caso de uso = o que o visitante precisa ver na vitrine + critério de aceite verificável (Dado/Quando/Então)
por_que: a vitrine só pode mostrar post publicado; rascunho não vaza, e a ordem é a que o editor escolheu
owner: wagner
last_run: "2026-10-14"
---

# Casos de Uso & Aceite — Site/Blogs (`/c/blogs`)

> **Fonte dos UC:** [charter](Blogs.charter.md) (Mission: "Backend entrega os posts `CmsPage type=blog`,
> `is_enabled=1`, ordenados por `priority`") + SPEC Cms **US-CMS-005** (páginas públicas por slug,
> blog incluso). **Não** o `.tsx`.
> **Fora daqui:** a busca é 100% client-side (charter Anti-hook) — não tem contrato de servidor a provar
> nesta camada.
> **Status:** ✅ passa · 🧪 teste cita o UC, veredito pendente da lane · ⬜ não verificado · ❌ quebrou.
> Teste: `Modules/Cms/Tests/Feature/SitePublicoContratoTest.php`, lane `verticais-pest` (MySQL).

## UC-SBLOG-01 · Abre sem login · `must`
- **Aceite:** Dado um visitante sem sessão · Quando abre `/c/blogs` · Então recebe 200 com a tela `Site/Blogs` e a lista de posts.
- **Status:** 🧪

## UC-SBLOG-02 · Só post publicado · `must`
- **Fonte:** charter Mission (`is_enabled=1`).
- **Aceite:** Dado um post publicado e um post desligado · Quando o visitante abre a vitrine · Então vê o publicado e não vê o desligado.
- **Status:** 🧪

## UC-SBLOG-03 · Ordem do editor · `should`
- **Fonte:** charter Mission ("ordenados por `priority`").
- **Aceite:** Dado dois posts publicados, o de prioridade 1 criado depois do de prioridade 2 · Quando o visitante abre a vitrine · Então o de prioridade 1 vem antes.
- **Status:** 🧪
