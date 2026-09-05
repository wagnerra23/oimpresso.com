---
id: resources-js-pages-repair-producao-oficina-index-casos
casos: Produção · Oficina/OS (kanban) · /repair/producao-oficina
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-09-05"
last_run_ci: "0 UC executado — o trio nasce neste PR e o teste é Pest Browser (CI-only). Veredito pendente do step E2E de render · Repair/ProducaoOficina em visual-regression.yml."
---

# Casos de Uso & Aceite — Produção · Oficina/OS (kanban)

> Kanban **read-mostly** de 5 colunas sobre `JobSheet`, com vocabulário **shared multi-vertical**
> (`code/item/usage_meter/executor/slot/area` — [ADR 0121 §P8](../../../../../memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md)).
> Persona: **operador de produção** que precisa enxergar o fluxo do dia em monitor 1280px sem
> abrir cada OS.
>
> Os UC derivam do **`Index.charter.md`** (Goals · UX Targets · Non-Goals) + do
> `ProducaoOficinaController` / `KanbanProductionService` — **não** do `.tsx` nem do corpo do
> teste (§5 2026-06-05, `memory/proibicoes.md`).
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.

---

## Escopo destes UC — o eixo RENDER, e só ele

O **contrato de servidor desta tela já está defendido** por 5 suítes Pest em
`Modules/Repair/Tests/Feature/` (`ProducaoOficinaTest`, `ProducaoOficinaRefactorTest`,
`ProducaoOficinaVendaDerivadaExpandedTest`, `ProducaoOficinaOnda5CompartilharTest`,
`ProducaoOficinaFaseBVendaDerivadaCardTest`). O que **nenhuma** delas faz é **renderizar a tela
num browser**: medido em 2026-09-05 com `node scripts/qa/screen-coverage-map.mjs`, o módulo
Repair tinha **14 telas · 14 charter · 0 E2E · 0 VRT · 0 a11y** — único módulo grande zerado nos
três eixos.

Logo estes UC cobrem **só o eixo render**, e **não duplicam assert de contrato nenhum**
(§5 2026-07-09 "duplica régua consolidada"). Cada UC abaixo é citado pelo ID por
`tests/Browser/Repair/ProducaoOficinaIndexTest.php` (G-2).

**Âncora de contrato — gap declarado, não inventado:** o Repair **não tem SDD** nem `CU-*`
(`memory/requisitos/Repair/` tem ARCHITECTURE/BRIEFING/RUNBOOKs, nenhum `SDD-tela-*`), e o
`SPEC.md` não carrega as US que o charter cita (`US-REPAIR-PROD-2..5`). A âncora de cada UC é,
portanto, o **charter** + a ADR nomeada — nunca um `CU-` fabricado.

---

## Rastreabilidade UC → âncora

| UC | Comportamento | Força | Âncora de contrato | Teste | Status |
|---|---|---|---|---|---|
| UC-RPO-E1 | O kanban monta com as 5 colunas na ordem do charter | must | charter §Mission + §Goals | `ProducaoOficinaIndexTest` | 🧪 |
| UC-RPO-E2 | Cabe em 1280px sem scroll horizontal | must | charter §UX Targets | `ProducaoOficinaIndexTest` | 🧪 |
| UC-RPO-E3 | Filtro de `slot`/`area` liga o contador e o "Limpar filtros" | should | charter §Goals (US-REPAIR-PROD-3) | `ProducaoOficinaIndexTest` | 🧪 |
| UC-RPO-E4 | O drawer é o único container — zero modal, zero `<form>` | must | charter §UX Anti-patterns | `ProducaoOficinaIndexTest` | 🧪 |
| UC-RPO-E5 | Zero violação axe CRITICAL | should | ratchet level 0 (`A11yAxeBrowserTest`) | `ProducaoOficinaIndexTest` | 🧪 |
| UC-RPO-E6 | O card do kanban não é operável por teclado (**defeito medido**) | must `[A11Y]` | WCAG 2.1.1 + 2.5.7 | `ProducaoOficinaIndexTest` | 🧪 |

---

## UC-RPO-E1 · O kanban monta com as 5 colunas na ordem do charter `[must]`
- **Persona:** operador de produção — abre a tela e enxerga o fluxo do dia inteiro.
- **Aceite:** Dado um usuário autenticado do negócio · Quando abre `/repair/producao-oficina` em 1280 e em 1440 · Então vê as 5 colunas **na ordem** Recepção, Diagnóstico, Aguardando peças, Em execução, Pronto — cada uma com seu contador — e **zero erro de console**.
- **Teste:** `ProducaoOficinaIndexTest` ("UC-RPO-E1 · render — o kanban monta com as 5 colunas na ordem do charter").
- **Regressão que defende:** refactor que troca a ordem das colunas, perde uma, ou derruba a tela em 403/erro. O `ProducaoOficinaTest` prova a **prop** `columns`; este prova o que a pessoa **vê**.
- **Independente de dado:** a asserção é sobre os **cabeçalhos**, que existem com coluna cheia ou vazia (o vazio renderiza "Nenhuma OS").
- **Status: 🧪**

## UC-RPO-E2 · Cabe em 1280px sem scroll horizontal `[must]`
- **Persona:** operador em monitor 1280px — o quirk que o charter marca como crítico.
- **Aceite:** Dado a tela montada em viewport 1280x800 · Quando compara `document.documentElement.scrollWidth` com `clientWidth` · Então **não há** overflow horizontal, e as 5 colunas do grid seguem presentes.
- **Teste:** `ProducaoOficinaIndexTest` ("UC-RPO-E2 · render — cabe em 1280 sem scroll horizontal").
- **Regressão que defende:** coluna nova, `min-width` num card, ou padding que empurre o grid `grid-cols-5` além da viewport — o operador passa a rolar lateralmente pra ver "Pronto".
- **Por que só o browser prova:** é layout computado. Nenhum Pest de contrato e nenhum grep no `.tsx` responde "cabe em 1280?".
- **Status: 🧪**

## UC-RPO-E3 · Filtro de `slot`/`area` liga o contador e o "Limpar filtros" `[should]`
- **Persona:** operador que quer ver só o que está num `slot` específico da produção.
- **Aceite:** Dado o kanban sem filtro (contador na forma simples, "N OS") · Quando clica num chip de `slot` que não seja "Todos" · Então o contador passa à forma comparativa ("X de N OS") **e** aparece o botão "Limpar filtros"; Quando clica em "Limpar filtros" · Então o contador volta à forma simples e o botão some.
- **Teste:** `ProducaoOficinaIndexTest` ("UC-RPO-E3 · render — o filtro liga o contador comparativo e o Limpar filtros").
- **Regressão que defende:** o chip virar decorativo (muda de cor e não filtra), ou o "Limpar filtros" não restaurar o estado inicial. É filtro **client-side** (`useMemo`), então nenhum teste de servidor o alcança.
- **Independente de dado:** assere a **transição de forma** do contador e a presença/ausência do botão — nunca a contagem. Em `data_source` live o Controller emite `slot: null` em todo card, então filtrar por um `slot` legitimamente dá 0; cravar número aqui nasceria vermelho.
- **Status: 🧪**

## UC-RPO-E4 · O drawer é o único container — zero modal, zero `<form>` `[must]`
- **Persona:** o próprio charter — §UX Anti-patterns proíbe modal, e §Non-Goals proíbe CRUD e edição inline nesta tela.
- **Aceite:** Dado a tela montada · Então não existe `<dialog>` nem `[role="dialog"]` no documento, e não existe `<form>` dentro do `<main>`; **e** o kanban expõe exatamente 5 `<section>` de coluna (controle positivo — sem ele o guard passaria por "não achei nada").
- **Teste:** `ProducaoOficinaIndexTest` ("UC-RPO-E4 · Non-Goal — drawer é o único container, sem modal e sem form").
- **Regressão que defende:** alguém trocar o drawer por um modal, ou colar um formulário de edição inline no kanban — os dois são Non-Goal explícito do charter.
- **Status: 🧪**

## UC-RPO-E5 · Zero violação axe CRITICAL `[should]`
- **Persona:** qualquer operador com leitor de tela.
- **Aceite:** Dado a tela montada autenticada · Quando roda `axe.run()` · Então **zero** violação de severidade CRITICAL (`assertNoAccessibilityIssues(level: 0)`).
- **Teste:** `ProducaoOficinaIndexTest` ("UC-RPO-E5 · a11y — zero violação axe CRITICAL").
- **Ratchet:** mesmo piso do `A11yAxeBrowserTest` (level 0). Subir pra level 1 (critical + serious) é PR follow-up — não se sobe às cegas.
- **Status: 🧪**

## UC-RPO-E6 · O card do kanban não é operável por teclado — defeito medido, capturado `[must]` `[A11Y]`
- **Persona:** operador que não usa mouse (ou usa leitor de tela).
- **O defeito (MEDIDO no `Index.tsx` em `origin/main` e0f2b79c86, 2026-09-05):** o card é
  `<article draggable onClick={onClick}>` (`Index.tsx:418-428`) — elemento **não-interativo** com
  handler de clique. Contagem no arquivo: `role=` **0** · `tabIndex` **0** · `onKeyDown` **0** ·
  `aria-keyshortcuts` **0**. Consequência: o card **não entra na ordem de tabulação**, **não ativa
  por Enter/Espaço**, **não se anuncia** como controle, e o **arrasto entre colunas não tem
  alternativa por teclado** (WCAG 2.5.7 Dragging Movements, AA na WCAG 2.2; e WCAG 2.1.1 Keyboard).
- **Aceite (o que o teste afirma HOJE):** Dado a tela montada · Quando conta os cards do kanban e
  quantos são alcançáveis por teclado · Então **nenhum** é, e a tela declara **zero**
  `aria-keyshortcuts`. O controle positivo é a contagem de cards maior que zero — sem ele o caso
  passaria numa tela vazia.
- **Teste:** `ProducaoOficinaIndexTest` ("UC-RPO-E6 · a11y — o card do kanban não é alcançável nem operável por teclado (defeito capturado)").
- **⚠️ Este caso é de CARACTERIZAÇÃO, não de contrato satisfeito.** Ele fica **verde enquanto o
  defeito existir**. No dia em que o Design System entregar card acessível (`role="button"` +
  `tabIndex={0}` + `onKeyDown` + alternativa de teclado pro arrasto), **este caso fica VERMELHO —
  e esse vermelho é o sinal de sucesso**. O conserto é **inverter o esperado** (operáveis ===
  total), nunca deletar o caso.
- **Por que não é o axe que pega:** o axe não tem regra pra "handler de clique em elemento
  não-interativo" — ele inspeciona o DOM, e um `<article>` sem `role` é indistinguível de um
  `<article>` decorativo. Somado ao piso do gate (level 0 = CRITICAL only), esta sonda é
  **aditiva** e não duplica o UC-RPO-E5.
- **Fora de escopo desta sessão:** consertar o DS. A auditoria de export
  (`prototipo-ui/design-docs/cowork-inbox/REPAIR-ONDAS-2026-09-04.md`, itens A1/A9) mediu a mesma
  classe **no protótipo**; aqui a medição é no `.tsx` de produção. Capturar em teste é o escopo;
  consertar é decisão [W].
- **Status: 🧪**

---

## Achados declarados (não consertados nesta sessão)

1. **O charter proíbe toast, e a tela mostra toast.** §UX Anti-patterns diz `❌ Toast/snackbar`
   (`Index.charter.md:95`), mas o `VendaDerivadaCard` — importado em `Index.tsx:18`, renderizado em
   `:529` — usa `toast.success`/`toast.error` do sonner no fallback do "Compartilhar", com Pest
   GUARD verde (`ProducaoOficinaOnda5CompartilharTest`). Pela **regra de precedência**
   (`memory/proibicoes.md`), teste verde vence charter, logo **o charter é o perdedor**. Não editei:
   §UX Anti-patterns e §Non-Goals são slot de [W] (`charter-write` é proibida de inferir).
   **Decisão de [W].**
2. **Duas implementações mortas no Controller.** `ProducaoOficinaController::mapStatusesToColumns()`
   e `::findStatusForColumn()` são cópias privadas da lógica que hoje vive no
   `KanbanProductionService` — `index()` e `move()` chamam `$this->kanban->...`. O PHPStan já as
   marca como unused (`phpstan-baseline.neon:10076` e `:10082`). Fora da minha área (Controller);
   flagado, não tocado.
3. **Colisão de ID `US-REPA-002`.** O charter usa o ID para "Caminho A refactor vocabulário
   shared"; o `memory/requisitos/Repair/SPEC.md:29` usa o mesmo ID para "3 testes do Wave18 quebram
   com `base_path()`". São coisas diferentes com o mesmo identificador. Flagado, não tocado.
