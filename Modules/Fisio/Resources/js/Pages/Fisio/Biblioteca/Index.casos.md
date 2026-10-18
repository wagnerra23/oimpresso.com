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

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` Seed dos 38 exercícios com `times_per_day`, avisos de respiração/apoio e `contraindication_flags` — revisão clínica anotada no PR (T-04), sem teste de conteúdo.
- `[BACKLOG]` Protocolos-modelo na grade.

## Trilha do tempo
- 2026-10-17 · suíte TDD da F1 — casos nascem antes da tela e do código.
