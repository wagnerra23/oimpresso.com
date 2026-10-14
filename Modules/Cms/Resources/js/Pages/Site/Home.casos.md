---
casos: Cms · Site/Home — landing pública (`/`)
irmaos: Home.charter.md (lei) · Home.tsx (código) · Modules/Cms/Tests/Feature/SitePublicoContratoTest.php (defesa)
tecnica: Caso de uso = o que o visitante sem conta precisa receber + critério de aceite verificável (Dado/Quando/Então)
por_que: é a primeira impressão do produto; abrir sem login e trazer o conteúdo do CMS é o contrato, e o shell não pode esperar as seções
owner: wagner
last_run: "2026-10-14"
---

# Casos de Uso & Aceite — Site/Home (`/`)

> **Fonte dos UC:** SPEC Cms **US-CMS-001** (acceptance: `GET /` 200 sem auth, `Site/Home` com
> `testimonials`/`page`/`faqs`/`statistics`) + [charter](Home.charter.md) (Goals: as 4 seções via
> `Inertia::defer`; Non-Goal: não exige login). **Não** o `.tsx`.
> **Status:** ✅ passa · 🧪 teste cita o UC, veredito pendente da lane · ⬜ não verificado · ❌ quebrou.
> Teste: `Modules/Cms/Tests/Feature/SitePublicoContratoTest.php`, lane `verticais-pest` (MySQL).

## UC-SHOME-01 · Abre sem login · `must`
- **Persona:** visitante/prospect sem conta.
- **Fonte:** US-CMS-001 (`GET /` retorna 200 sem auth) · charter Non-Goal "não exige login".
- **Aceite:** Dado um visitante sem sessão · Quando abre `/` · Então recebe 200 com a tela `Site/Home`, sem ir ao login.
- **Status:** 🧪

## UC-SHOME-02 · Seções do CMS diferidas · `must`
- **Fonte:** US-CMS-001 (props `testimonials`, `page`, `faqs`, `statistics`) · charter Goals ("cada seção CMS vem via `Inertia::defer` per-key e hidrata em segundo turno").
- **Aceite:** Dado a primeira visita · Quando a página chega · Então as 4 seções estão declaradas como diferidas (o shell pinta sem esperá-las) · E quando o navegador pede essas 4 seções · Então as 4 chegam.
- **Status:** 🧪
