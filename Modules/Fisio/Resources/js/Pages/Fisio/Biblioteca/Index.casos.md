---
id: modules-fisio-pages-fisio-biblioteca-index-casos
casos: Fisio · Biblioteca de exercícios · /fisio/biblioteca
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do fisioterapeuta + critério de aceite verificável (Dado/Quando/Então)
por_que: a biblioteca global é a mesma para todos e não pode ser alterada por um cliente; o exercício próprio de uma clínica é dado dela. Escopo errado ou esconde os globais de todo mundo ou vaza o próprio.
owner: wagner
last_run: "2026-10-17"
last_run_ci: "_pendente_ — o trio nasce com a suíte TDD da F1 (antes do código). O veredito entra quando a lane MySQL rodar FisioBibliotecaTest."
related_us: []
---

# Casos de Uso & Aceite — Fisio · Biblioteca (`/fisio/biblioteca`)

> **Âncora:** handoff F1 (T-04, T-06). Os UCs não derivam do `.tsx`.
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou. **O veredito é da lane** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-FBI-01 | global para todos; próprio só para o dono | must | `FisioBibliotecaTest` |
| UC-FBI-02 | criar exercício próprio | must | `FisioBibliotecaTest` |
| UC-FBI-03 | global não se edita; próprio alheio é 404 | must | `FisioBibliotecaTest` |
| UC-FBI-04 | filtro por região e busca | should | `FisioBibliotecaTest` |
| UC-FBI-08 | favorito por profissional e empresa; global não muda | must | `FisioBibliotecaFavoritosTest` |
| UC-FBI-09 | filtros de posição, equipamento e "só favoritos" combinados, estado na URL | should | `FisioBibliotecaFavoritosTest` |
| UC-FBI-10 | exercício próprio aceita posição e equipamento da lista fechada | should | `FisioBibliotecaFavoritosTest` |

---

## UC-FBI-01 · O que cada clínica vê `[T0]` · `must`
- **Aceite:** Dado um exercício global (`business_id` nulo), um da 98 e um da 99 · Quando cada clínica abre a biblioteca · Então ambas veem o global e só o próprio — na tela e no Eloquent.
- **Regressão que defende:** escopo por business cru escondendo os globais; exercício próprio vazando.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioBibliotecaTest.php`
- **Status: 🧪**

## UC-FBI-02 · Exercício da clínica · `must`
- **Aceite:** Quando o fisioterapeuta cria um exercício · Então ele grava com o business da sessão e `is_custom`, e a outra clínica não o vê. Sem nome ou sem passos é recusado.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioBibliotecaTest.php`
- **Status: 🧪**

## UC-FBI-03 · Global é de todos, ninguém altera · `must`
- **Aceite:** Editar um global → 403; editar o próprio de outra clínica → 404; editar o próprio → ok. Os dois primeiros não mudam nada.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioBibliotecaTest.php`
- **Status: 🧪**

## UC-FBI-04 · Achar o exercício · `should`
- **Aceite:** Filtrar por região devolve só aquela região; buscar por parte do nome encontra.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioBibliotecaTest.php`
- **Status: 🧪**

## UC-FBI-08 · Favoritos do profissional `[T0]` · `must`
- **Aceite:** Dado um exercício global, um da 98 e um da 99 · Quando o profissional da 98 marca a estrela · Então o favorito grava na tabela `fisio_exercise_favorites` com a empresa e o usuário da sessão (o que vier no corpo é ignorado), o exercício global fica idêntico, e a grade mostra a estrela marcada só para ele. Outro usuário da 98 tem a própria lista; a 99 não vê nenhum favorito da 98 e recebe 404 ao favoritar o próprio da 98. Favoritar de novo não duplica (o banco recusa duplicata); desfavoritar remove. "Só favoritos" mostra só os do usuário. Favoritar exige só `fisio.access`.
- **Regressão que defende:** favorito de um profissional aparecendo para o colega ou para outra clínica; clínica alterando o exercício global ao favoritar.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioBibliotecaFavoritosTest.php`
- **Status: 🧪**

## UC-FBI-09 · Filtros de posição e equipamento · `should`
- **Aceite:** Filtrar por posição ou por equipamento devolve só os exercícios com aquele valor; os filtros combinam entre si e com região, busca e "só favoritos". As opções são as listas fechadas de `config('fisio.biblioteca.*')` (`etapas/01-biblioteca.md` §3.3); valor fora da lista na URL é ignorado (a lista fica igual à sem filtro e o estado volta vazio). O estado do filtro fica na query string (`pose`, `equipment`, `fav`, `region`, `q`).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioBibliotecaFavoritosTest.php`
- **Status: 🧪**

## UC-FBI-10 · Posição e equipamento no exercício da clínica · `should`
- **Aceite:** Criar ou editar exercício próprio grava posição e equipamentos da lista fechada; valor fora da lista ou "Nenhum" combinado com outro equipamento → 422; lista vazia de equipamento volta a "não informado". Exercício global segue não editável (403).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioBibliotecaFavoritosTest.php`
- **Status: 🧪**

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` Seed dos 38 exercícios com `times_per_day`, avisos de respiração/apoio e `contraindication_flags` — revisão clínica anotada no PR (T-04), sem teste de conteúdo.
- `[BACKLOG]` Protocolos-modelo na grade.

## Trilha do tempo
- 2026-10-17 · suíte TDD da F1 — casos nascem antes da tela e do código.
- 2026-10-18 · US-FISIO-004 — `BibliotecaController`, tela e lane Verticais com `FisioBibliotecaTest`. Busca por texto na F1 aceita por [W] em 2026-10-18 (ADR 0437 §14 nº 3). Status segue 🧪 até o veredito da lane.
- 2026-10-18 · favoritos e filtros de posição e equipamento (comparativo Physitrack) — UC-FBI-08..10, `FisioBibliotecaFavoritosTest`. Posição passa a ser lista fechada no cadastro.
