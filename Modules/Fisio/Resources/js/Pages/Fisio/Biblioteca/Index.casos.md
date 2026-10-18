---
id: modules-fisio-pages-fisio-biblioteca-index-casos
casos: Fisio · Biblioteca de exercícios · /fisio/biblioteca
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do fisioterapeuta + critério de aceite verificável (Dado/Quando/Então)
por_que: a biblioteca global é a mesma para todos e não pode ser alterada por um cliente; o exercício próprio de uma clínica é dado dela. Escopo errado ou esconde os globais de todo mundo ou vaza o próprio. Conteúdo clínico não revisado não pode chegar à clínica.
owner: wagner
last_run: "2026-10-18"
last_run_ci: "_pendente_ — o veredito entra quando a lane MySQL (verticais-pest) rodar FisioBibliotecaTest no PR da US-FISIO-004."
related_us: [US-FISIO-004]
---

# Casos de Uso & Aceite — Fisio · Biblioteca (`/fisio/biblioteca`)

> **Âncora:** handoff F1 (T-04, T-06) e `etapas/01-biblioteca.md` (§3.3 facetas, §3.8 governança). Os UCs não derivam do `.tsx`.
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou. **O veredito é da lane** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-FBI-01 | global para todos; próprio só para o dono | must | `FisioBibliotecaTest` |
| UC-FBI-02 | criar exercício próprio | must | `FisioBibliotecaTest` |
| UC-FBI-03 | global não se edita; próprio alheio é 404 | must | `FisioBibliotecaTest` |
| UC-FBI-04 | filtro por região e equipamento | should | `FisioBibliotecaTest` |
| UC-FBI-05 | seed L0 pendente de revisão clínica | must | `FisioBibliotecaTest` |
| UC-FBI-06 | remover só o próprio, nunca o prescrito | must | `FisioBibliotecaTest` |

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
- **Aceite:** Filtrar por região devolve só aquela região; filtrar por equipamento devolve só quem usa aquele equipamento; "sem equipamento" devolve só quem não precisa de nada.
- **Decisão:** a busca por texto saiu (ADR 0437 §14, decisão nº 3, não aceita por [W]; HANDOFF-F1 ajuste nº 3). O caso nasceu com "busca por nome" na suíte TDD e foi trocado pelo filtro de equipamento na US-FISIO-004.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioBibliotecaTest.php`
- **Status: 🧪**

## UC-FBI-05 · Conteúdo não revisado não chega à clínica `[T0-clínico]` · `must`
- **Aceite:** Dado o seed L0 (38 exercícios globais) · Então todos nascem `em_revisao`, com contraindicações só nos códigos de `condition_flags` · E a clínica não os vê · Quando a revisão publica um · Então ele aparece · E rodar o seed de novo não duplica nem sobrescreve o revisado.
- **Por quê:** ADR 0437 §12 ("revisão clínica da biblioteca por fisioterapeuta antes do seed") e etapa 01 §3.8 (estados `rascunho → em_revisao → publicado → retirado`).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioBibliotecaTest.php`
- **Status: 🧪**

## UC-FBI-06 · Remover exercício da clínica · `must`
- **Aceite:** Remover um global → 403; o de outra clínica → 404; o próprio que já está em programa de paciente → 409 (o programa e o prontuário apontam para ele); o próprio livre → removido.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioBibliotecaTest.php`
- **Status: 🧪**

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` Protocolos-modelo na grade (os protocolos-modelo globais entram com o editor de programa, US-FISIO-006).
- `[BACKLOG]` Tela de revisão clínica (fila `em_revisao`, autor ≠ revisor, CREFITO) — B-14 da etapa 01.

## Trilha do tempo
- 2026-10-17 · suíte TDD da F1 — casos nascem antes da tela e do código.
- 2026-10-18 · US-FISIO-004 — tela, rotas e seed; UC-FBI-04 troca busca por filtro de equipamento; nascem UC-FBI-05 e UC-FBI-06.
