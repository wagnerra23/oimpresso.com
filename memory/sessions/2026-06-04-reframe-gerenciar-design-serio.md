---
slug: 2026-06-04-reframe-gerenciar-design-serio
title: "Reframe: a pergunta certa não é 'reconciliar memória' — é 'por que o aparato de design (conceito 85) roda a 40?'"
type: session
status: insight
date: "2026-06-04"
authors: [claude-cowork]
grounded_in_main: "7e59bdc8e8ff (tree) · SCREEN-GRADE-METODO.md · padroes-tela/*"
---

# "Como se gerencia design de forma séria? Essa é a pergunta certa agora?"

## Resposta curta
**Não — ou só em segundo plano.** Reconciliar memória é higiene (1 dia de [CL]); não é gerenciar design — é arrumar o arquivo. A pergunta séria, grounded na autoavaliação do próprio projeto:

> **O aparato de gestão de design está DESENHADO a 85/100 e RODANDO a 40/100. O gap é 100% wiring (doc→tool), zero conceito. Quais fios eu conecto pra ele EXECUTAR — em vez de escrever mais aparato?**

## O que LI no main (✓)
- **Os 5 padrões de tela existem** (`padroes-tela/PT-01..PT-05`) — improvisação por falta de template não é mais a desculpa.
- **`SCREEN-GRADE-METODO`** é estado-da-arte: 16 dim × persona × peso-real, Beginner→Champion, score-as-code, ratchet, testa a própria validade (T1/T2/T3), benchmark vs USWDS/Soundcheck/Cortex. **Autoavaliação: conceito 85 (Advanced→Leader) · execução 40 (Developing) · ponderada ~68. "Todos os gaps são wiring, nenhum é conceitual."**
- Apparatus completo já existe: PRE-FLIGHT (grounding), REGISTRY (não-inventar), LICOES_F3 (não-repetir-erro), golden-por-arquétipo, especialistas paralelos, ui:lint + ds/* + ratchet (enforcement).

## Como se gerencia design de forma séria (a forma canônica — e onde o projeto está)
| Pilar | Existe? | Estado real |
|---|---|---|
| 1. **Uma fonte viva** (tokens→componentes→padrões→tela) consumida pelo produto | ✓ | cockpit/inertia + REGISTRY + PT-01..05 + UI-0013. Drift mora nos bundles gigantes (sells 285KB, fin 334KB), não na fonte |
| 2. **Uma régua** aplicada a TODA tela | ✓ desenhada | screen-grade roda mecânico (~239 telas), mas o juiz holístico 16-dim×benchmark (a parte séria) **não roda em escala** |
| 3. **Gates** que fazem divergir = CI vermelho | ◐ parcial | ui:lint/ds/* sim; conformance/foundation só no branch |
| 4. **Um dashboard** de nota por tela → convergência visível | ✗ | `screen-grades-baseline.json` + dimensão GovernanceV4 planejados, não ligados |
| 5. **Um dono** da decisão visual | ✓ | Claude Design plugin (ADR 0235 §3) |
| 6. **Drift pego por máquina, não por [W]** | ◐ | o sintoma recorrente ([W] pega "feio no dark", verde×roxo, foundations.css paralelo) = ISTO ainda não está ligado |

Pilar 6 é o diagnóstico: **toda vez que [W] é quem pega o problema, faltou um fio ligado.**

## A virada (a disciplina, não mais um doc)
Gerenciar design de forma séria aqui = **parar de AUTORAR governança e LIGAR a que existe + MEDIR.** Ordem (worst-first × wiring):
1. **Rodar o screen-grade full nas telas vivas** (juiz 16-dim, não só regex) → `screen-grades-baseline.json` ratchet em CI → **dashboard de convergência**. Agora o sistema mede sozinho.
2. **Ligar os gates** (conformance/foundation re-baselinados no main) → divergir = merge trava. Drift sai das mãos do [W].
3. **REGISTRY/PRE-FLIGHT/LICOES viram tool** (índice consultável / hook / auto-inject) — o que o §6 do método já aponta.
4. Só então: reconciliação de memória (lápides, DS v6 ADR) = o resto do housekeeping, em paralelo, baixo risco.

## Meta-honestidade (pra mim e pro loop)
Esta conversa inteira — e o chat do Claude Code (branch +111k, "formalizar DS v6 como ADR", "publicar PR de governança") — é **comportamento de execução-40 vestido de progresso**: produzir mais governança. A disciplina séria é resistir a isso e conectar os fios + medir. _Volume de governança ≠ design gerenciado_ (L-30, eixo design).

## ⚠️ Correção 2026-06-04 (li o git ATUALIZADO — eu superestimei o gap)

A nota "execução 40" é de **2026-05-30** e está **stale**. Estado real (✓ lido hoje):
- **37 workflows de CI ativos** — `ui-lint`, `stylelint-gate`, `eslint-gate`, `design-index-gate`, `module-grades-gate`, `visual-regression` (INFRA-ONLY), `screen-smoke-after-merge`, **`pr-ui-judge`**, etc. Ondas 1–3 do `AUTOMATION-ROADMAP` = **executadas** (o roadmap que diz "zero ondas" está stale).
- **Board de 222 telas, média 75/100** + dashboard `scorecards/screen-grade-board.html` (182KB) + baseline ratchet JSON (250KB). A medição **EXISTE e rodou**.
- **As 44 telas <70 já foram implementadas** ("código verde", Vite build 12m exit 0, 2026-05-31) em `feat/staging-ct100`.
- **`pr-ui-judge.yml` (juiz LLM semântico, 9 dim) existe mas está DEFAULT OFF** (`if: vars.PR_UI_JUDGE_ENABLED == 'true'`, ~$3/mês).

**Então o gap NÃO é construir — é fechar 3 portas, todas no [W]:**
1. **[W] aprova as 44 telas por screenshot** (gate visual ADR 0107/0114) → fecha o ratchet (0236) + desbloqueia o merge de `feat/staging-ct100`. **Maior bloqueador: trabalho feito, esperando o gate humano** (que é certo ser humano).
2. **Flip `PR_UI_JUDGE_ENABLED=true`** (+ confirmar `ANTHROPIC_API_KEY`) → juiz semântico ON em todo PR futuro = **Pilar 6 ligado** (máquina cobra). 1 switch.
3. **Re-rodar o board** (workflow 19-agentes) → média nova pós-44 (não roda barato no main loop).
+ 3 fixes de sidebar (Onda 4) = decisão de produto [W].

**Auto-correção honesta (L-26/L-27):** eu afirmei "execução 40 / dashboard não ligado" no início desta sessão **sem ler o git atual** — errado, era stale. O dashboard existe, a medição rodou, as 44 foram feitas. O reframe ("ligar > escrever") **continua certo** — só que está **mais ligado** do que eu disse; falta [W] fechar os gates. _Não construir o que já existe; fechar as portas abertas._

## Trilha do tempo
- 2026-06-04 · [CC] · reli o main; PT-01..05 existem (INDEX §2b stale); screen-grade 85-conceito/40-execução, gaps = wiring. Reframe: a pergunta certa é "ligar o aparato", não "escrever mais aparato". Pilar 6 (máquina cobra, não [W]) é o que falta.
- 2026-06-04 · **CORREÇÃO ao Pilar 6 (achado [CL]):** o **gate de pixel `visual-regression.yml` (ADR 0108) é STUB** (`continue-on-error`, travado por migration-order UltimatePOS legacy). Então "os gates pegam regressão" vale pra **lint/ESLint/Module-Grades/UI-Judge (reais)**, **NÃO** pro pixel-diff. Pra mudança **visual**, a rede real = **UI-Judge (LLM) + olho de [W]/staging**. Consequência: (1) **mudança global** (ex.: `foundations.css` font IBM Plex) **não entra blind** — precisa staging ou o gate consertado; (2) o olho de [W] em re-skin **não é redundante hoje** — é a rede. Pilar 6 está **meio-ligado** (semântico sim, pixel não). Conserto do pipeline (test browser + migration-order, ~6-8h, ADR 0108) = keystone pra fechar.
